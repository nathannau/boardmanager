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
use nathannau\boardmanager2\core\actions;
use nathannau\boardmanager2\core\discord_links;
use nathannau\boardmanager2\core\discord_panel;
use nathannau\boardmanager2\core\discord_sync;
use nathannau\boardmanager2\core\rules;
use nathannau\boardmanager2\core\store;
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
	const MESSAGE_COMPONENT = 3;
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

	/** @var actions */
	protected $actions;

	/** @var rules */
	protected $rules;

	/** @var store */
	protected $store;

	/** @var discord_panel */
	protected $panel;

	/** @var discord_sync */
	protected $sync;

	public function __construct(\phpbb\config\config $config, \Symfony\Component\HttpFoundation\Request $symfony_request, \phpbb\language\language $language, \phpbb\user $user, \phpbb\log\log_interface $log, discord_links $links, \phpbb\db\driver\driver_interface $db, actions $actions, rules $rules, store $store, discord_panel $panel, discord_sync $sync)
	{
		$this->db = $db;
		$this->actions = $actions;
		$this->rules = $rules;
		$this->store = $store;
		$this->panel = $panel;
		$this->sync = $sync;
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
		if ((int) $interaction['type'] === self::MESSAGE_COMPONENT && isset($interaction['data']['custom_id']))
		{
			return $this->component($interaction, $discord_user);
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
	* Clic sur un bouton (ou choix dans le menu) du tableau d'une séance : l'action est faite
	* au nom du membre lié, puis le tableau est mis à jour pour tous (confirmation publique).
	* Un refus n'est montré qu'au membre.
	*/
	protected function component(array $interaction, array $discord_user)
	{
		$parts = explode(':', (string) $interaction['data']['custom_id']);
		if (count($parts) < 3 || $parts[0] !== 'bm2' || !ctype_digit($parts[2]))
		{
			return $this->ephemeral($this->language->lang('BM2_DISCORD_UNKNOWN'));
		}
		$action = $parts[0] . ':' . $parts[1];
		$session_id = (int) $parts[2];

		$link = $this->links->get_by_discord($discord_user['id']);
		if (!$link)
		{
			return $this->ephemeral($this->language->lang('BM2_DISCORD_NOT_LINKED', generate_board_url() . '/ucp.php?i=-nathannau-boardmanager2-ucp-main_module&mode=discord'));
		}
		$actor = $this->actions->actor_for_user($link['user_id']);
		if (!$actor)
		{
			return $this->ephemeral($this->language->lang('BM2_DISCORD_NO_ACCOUNT'));
		}

		try
		{
			switch ($action)
			{
				case discord_panel::ID_REGISTER:
					$this->actions->register($actor, $session_id, isset($parts[3]) ? (int) $parts[3] : 0);
				break;

				case discord_panel::ID_SELECT:
					$values = isset($interaction['data']['values']) ? $interaction['data']['values'] : array();
					$this->actions->register($actor, $session_id, $values ? (int) $values[0] : 0);
				break;

				case discord_panel::ID_UNREGISTER:
					$this->actions->unregister($actor, $session_id, $this->own_table($actor->user_id, $session_id));
				break;

				default:
					return $this->ephemeral($this->language->lang('BM2_DISCORD_UNKNOWN'));
			}
		}
		catch (action_exception $e)
		{
			return $this->ephemeral('❌ ' . discord_panel::plain($this->language->lang($e->key, ...$e->args)));
		}

		// Le tableau est mis à jour par l'API (rattrapé par la tâche cron en cas d'échec),
		// et le clic reçoit une confirmation publique dans la publication, comme les messages auto du forum
		$this->sync->sync_session($session_id);
		return $this->public_message($this->confirmation($actor->username, $this->actions->last_message));
	}

	/**
	* « ✅ **Pseudo** : Inscription sur la table 'Dune' de 'melkor' », depuis le message auto du forum
	*/
	protected function confirmation($username, $message)
	{
		$lines = array();
		foreach (explode("\n", $message) as $line)
		{
			$line = trim(preg_replace('/^' . preg_quote($this->language->lang('BM2_DISCORD_AUTO_PREFIX'), '/') . '\s*/u', '', trim($line)));
			if ($line !== '')
			{
				$lines[] = discord_panel::markdown($line);
			}
		}
		return '✅ **' . discord_panel::markdown($username) . '** : ' . implode("\n", $lines);
	}

	/**
	* Message visible de tous dans la publication
	*/
	protected function public_message($text)
	{
		return new JsonResponse(array(
			'type'	=> self::RESPONSE_MESSAGE,
			'data'	=> array(
				'content'			=> $text,
				'allowed_mentions'	=> array('parse' => array()),
			),
		));
	}

	/**
	* Table où annuler la présence du membre : une table d'abord, sinon le sans table
	*
	* @return int table_id (0 pour le sans table)
	* @throws action_exception
	*/
	protected function own_table($user_id, $session_id)
	{
		$session = $this->store->get_session($session_id);
		if (!$session)
		{
			throw new action_exception('BM2_ERROR_NOT_FOUND');
		}
		$state = $this->rules->load($session);
		$fallback = null;
		foreach ($state['players'] as $table_id => $players)
		{
			if (!$this->rules->find_user($players, $user_id))
			{
				continue;
			}
			if ($table_id)
			{
				return $table_id;
			}
			$fallback = 0;
		}
		if ($fallback === null)
		{
			throw new action_exception('BM2_ERROR_NOT_REGISTERED');
		}
		return $fallback;
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
