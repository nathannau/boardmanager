<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Nathan Nau
 * @license MIT (voir le fichier LICENSE)
 *
 */

namespace nathannau\boardmanager2\core;

/**
* Appels à l'API REST de Discord, avec le jeton du bot
*/
class discord_api
{
	const BASE_URL = 'https://discord.com/api/v10/';
	/** Délai court : une action sur le forum ne doit jamais attendre Discord longtemps */
	const TIMEOUT = 5;

	/** Permissions du bot : voir le salon, envoyer des messages (et dans les fils), intégrer des liens,
	* lire l'historique, gérer les fils (étiquettes, titre, verrouillage), créer des fils publics */
	const BOT_PERMISSIONS = 1024 | 2048 | 16384 | 65536 | 17179869184 | 34359738368 | 274877906944;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\config\db_text */
	protected $config_text;

	public function __construct(\phpbb\config\config $config, \phpbb\config\db_text $config_text)
	{
		$this->config = $config;
		$this->config_text = $config_text;
	}

	public function is_configured()
	{
		return $this->config['bm2_discord_app_id'] !== '' && $this->bot_token() !== '';
	}

	/**
	* Lien d'invitation du bot sur un serveur
	*/
	public function invite_url()
	{
		return 'https://discord.com/oauth2/authorize?client_id=' . rawurlencode($this->config['bm2_discord_app_id'])
			. '&scope=bot%20applications.commands&permissions=' . self::BOT_PERMISSIONS;
	}

	/**
	* @return array réponse décodée
	* @throws \RuntimeException message d'erreur de Discord
	*/
	public function request($method, $path, $body = null)
	{
		$client = new \GuzzleHttp\Client(array(
			'base_uri'	=> self::BASE_URL,
			'timeout'	=> self::TIMEOUT,
			'http_errors'	=> false,
		));
		$options = array(
			'headers' => array(
				'Authorization'	=> 'Bot ' . $this->bot_token(),
				'User-Agent'	=> 'DiscordBot (https://github.com/nathannau/boardmanager, 2.2)',
			),
		);
		if ($body !== null)
		{
			$options['json'] = $body;
		}

		try
		{
			$response = $client->request($method, ltrim($path, '/'), $options);
		}
		catch (\GuzzleHttp\Exception\GuzzleException $e)
		{
			throw new \RuntimeException('Discord injoignable : ' . $e->getMessage());
		}

		$data = json_decode((string) $response->getBody(), true);
		if ($response->getStatusCode() >= 300)
		{
			$detail = is_array($data) && isset($data['message']) ? $data['message'] : (string) $response->getBody();
			throw new \RuntimeException('Discord a répondu ' . $response->getStatusCode() . ' : ' . $detail);
		}
		return is_array($data) ? $data : array();
	}

	/**
	* Remplace les commandes slash de l'application sur un serveur
	*/
	public function put_guild_commands($guild_id, array $commands)
	{
		return $this->request('PUT', 'applications/' . rawurlencode($this->config['bm2_discord_app_id']) . '/guilds/' . rawurlencode($guild_id) . '/commands', $commands);
	}

	/**
	* Identité du bot (vérifie le jeton)
	*/
	public function bot_user()
	{
		return $this->request('GET', 'users/@me');
	}

	protected function bot_token()
	{
		return trim((string) $this->config_text->get('bm2_discord_bot_token'));
	}
}
