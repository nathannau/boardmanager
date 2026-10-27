<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Nathan Nau
 * @license MIT (voir le fichier LICENSE)
 *
 */

namespace nathannau\boardmanager2\controller;

use nathannau\boardmanager2\core\action_exception;
use nathannau\boardmanager2\core\discord_links;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
* Point d'entrée des interactions Discord (« Interactions Endpoint URL ») :
* Discord y envoie, signées, les commandes slash et les clics sur les boutons.
*/
class discord
{
	// Types d'interaction et de réponse (API Discord)
	const PING = 1;
	const APPLICATION_COMMAND = 2;
	const RESPONSE_PONG = 1;
	const RESPONSE_MESSAGE = 4;
	const FLAG_EPHEMERAL = 64;

	/** Écart toléré avec l'horodatage signé, contre le rejeu d'une requête */
	const MAX_CLOCK_SKEW = 300;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \Symfony\Component\HttpFoundation\Request */
	protected $symfony_request;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\log\log_interface */
	protected $log;

	/** @var discord_links */
	protected $links;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	public function __construct(\phpbb\config\config $config, \Symfony\Component\HttpFoundation\Request $symfony_request, \phpbb\language\language $language, \phpbb\user $user, \phpbb\log\log_interface $log, discord_links $links, \phpbb\db\driver\driver_interface $db)
	{
		$this->db = $db;
		$this->config = $config;
		$this->symfony_request = $symfony_request;
		$this->language = $language;
		$this->user = $user;
		$this->log = $log;
		$this->links = $links;
	}

	public function interactions()
	{
		$body = $this->symfony_request->getContent();
		if (!$this->verify_signature($body))
		{
			return new Response('invalid request signature', 401);
		}

		$interaction = json_decode($body, true);
		if (!is_array($interaction) || !isset($interaction['type']))
		{
			return new Response('bad request', 400);
		}
		if ((int) $interaction['type'] === self::PING)
		{
			return new JsonResponse(array('type' => self::RESPONSE_PONG));
		}

		$this->language->add_lang('common', 'nathannau/boardmanager2');
		if ($this->config['board_disable'])
		{
			return $this->ephemeral($this->language->lang('BM2_DISCORD_MAINTENANCE'));
		}
		if (!$this->config['bm2_discord_enabled'])
		{
			return $this->ephemeral($this->language->lang('BM2_DISCORD_DISABLED'));
		}

		$discord_user = isset($interaction['member']['user']) ? $interaction['member']['user'] : (isset($interaction['user']) ? $interaction['user'] : null);
		if (!$discord_user || empty($discord_user['id']))
		{
			return new Response('bad request', 400);
		}

		if ((int) $interaction['type'] === self::APPLICATION_COMMAND && isset($interaction['data']['name']) && $interaction['data']['name'] === 'lier')
		{
			return $this->command_link($interaction, $discord_user);
		}
		return $this->ephemeral($this->language->lang('BM2_DISCORD_UNKNOWN'));
	}

	/**
	* /lier CODE : lie le compte Discord au membre qui a généré le code dans son UCP
	*/
	protected function command_link(array $interaction, array $discord_user)
	{
		$code = '';
		foreach (isset($interaction['data']['options']) ? $interaction['data']['options'] : array() as $option)
		{
			if ($option['name'] === 'code')
			{
				$code = (string) $option['value'];
			}
		}

		try
		{
			$user_id = $this->links->link($code, $discord_user['id'], $discord_user['username']);
		}
		catch (action_exception $e)
		{
			return $this->ephemeral($this->language->lang($e->key, ...$e->args));
		}

		$username = $this->username($user_id);
		$this->log->add('admin', $user_id, $this->user->ip, 'LOG_BM2_DISCORD_LINKED', false, array($username, $discord_user['username']));
		return $this->ephemeral($this->language->lang('BM2_DISCORD_LINKED', $this->markdown($username)));
	}

	/**
	* Signature Ed25519 de Discord sur « horodatage + corps brut », avec la clé publique de l'application
	*/
	protected function verify_signature($body)
	{
		$signature = (string) $this->symfony_request->headers->get('X-Signature-Ed25519', '');
		$timestamp = (string) $this->symfony_request->headers->get('X-Signature-Timestamp', '');
		$public_key = (string) $this->config['bm2_discord_public_key'];

		if (!ctype_xdigit($signature) || strlen($signature) !== 128 || !ctype_digit($timestamp)
			|| !ctype_xdigit($public_key) || strlen($public_key) !== 64
			|| abs(time() - (int) $timestamp) > self::MAX_CLOCK_SKEW)
		{
			return false;
		}

		try
		{
			return sodium_crypto_sign_verify_detached(sodium_hex2bin($signature), $timestamp . $body, sodium_hex2bin($public_key));
		}
		catch (\SodiumException $e)
		{
			return false;
		}
	}

	/**
	* Réponse visible seulement par le membre qui a agi
	*/
	protected function ephemeral($text)
	{
		return new JsonResponse(array(
			'type'	=> self::RESPONSE_MESSAGE,
			'data'	=> array(
				'content'			=> $text,
				'flags'				=> self::FLAG_EPHEMERAL,
				'allowed_mentions'	=> array('parse' => array()),
			),
		));
	}

	protected function username($user_id)
	{
		$result = $this->db->sql_query('SELECT username FROM ' . USERS_TABLE . ' WHERE user_id = ' . (int) $user_id);
		$username = (string) $this->db->sql_fetchfield('username');
		$this->db->sql_freeresult($result);
		return $username;
	}

	/**
	* Texte stocké par phpBB (entités HTML) vers texte Discord, sans mise en forme involontaire
	*/
	protected function markdown($text)
	{
		$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
		return preg_replace('/([\\\\*_~`|>#\[\]()-])/', '\\\\$1', $text);
	}
}
