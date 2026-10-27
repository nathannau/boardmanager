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
* Liaison entre un membre du forum et son compte Discord.
*
* Le membre génère un code dans son UCP (visible de lui seul) et le saisit sur
* Discord avec /lier : Discord garantit l'identité de celui qui tape la commande.
* Le code est court, valable CODE_TTL secondes, à usage unique, et stocké haché.
*/
class discord_links
{
	const CODE_TTL = 600;
	/** Caractères sans ambiguïté de lecture (pas de 0/O, 1/I/L) */
	const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
	const CODE_LENGTH = 6;
	/** Tentatives de code erronées tolérées par compte Discord, sur FAIL_TTL secondes */
	const MAX_FAILS = 5;
	const FAIL_TTL = 900;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\cache\driver\driver_interface */
	protected $cache;

	protected $links_table;
	protected $codes_table;

	public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\cache\driver\driver_interface $cache, $links_table, $codes_table)
	{
		$this->db = $db;
		$this->cache = $cache;
		$this->links_table = $links_table;
		$this->codes_table = $codes_table;
	}

	public function get_by_user($user_id)
	{
		return $this->fetch_one('SELECT * FROM ' . $this->links_table . ' WHERE user_id = ' . (int) $user_id);
	}

	public function get_by_discord($discord_id)
	{
		return $this->fetch_one('SELECT * FROM ' . $this->links_table . "
			WHERE discord_id = '" . $this->db->sql_escape((string) $discord_id) . "'");
	}

	/**
	* Nouveau code de liaison pour un membre (remplace le précédent)
	*
	* @return string code affiché, par exemple « K7Q-4M2 »
	*/
	public function create_code($user_id)
	{
		$code = '';
		$max = strlen(self::CODE_ALPHABET) - 1;
		for ($i = 0; $i < self::CODE_LENGTH; $i++)
		{
			$code .= self::CODE_ALPHABET[random_int(0, $max)];
		}

		$this->db->sql_query('DELETE FROM ' . $this->codes_table . ' WHERE user_id = ' . (int) $user_id);
		$this->db->sql_query('INSERT INTO ' . $this->codes_table . ' ' . $this->db->sql_build_array('INSERT', array(
			'user_id'		=> (int) $user_id,
			'code_hash'		=> $this->hash($code),
			'expires_time'	=> time() + self::CODE_TTL,
		)));

		return substr($code, 0, 3) . '-' . substr($code, 3);
	}

	/**
	* Code en attente d'un membre (pour savoir s'il en a un en cours)
	*/
	public function get_pending_code($user_id)
	{
		return $this->fetch_one('SELECT * FROM ' . $this->codes_table . '
			WHERE user_id = ' . (int) $user_id . '
				AND expires_time > ' . time());
	}

	/**
	* Lie le compte Discord qui a saisi le code au membre qui l'a généré
	*
	* @return int user_id du membre lié
	* @throws action_exception
	*/
	public function link($code, $discord_id, $discord_username)
	{
		$discord_id = (string) $discord_id;
		$fail_key = '_bm2_discord_fail_' . preg_replace('/\D/', '', $discord_id);
		$fails = (int) $this->cache->get($fail_key);
		if ($fails >= self::MAX_FAILS)
		{
			throw new action_exception('BM2_DISCORD_TOO_MANY_TRIES');
		}

		$row = $this->fetch_one('SELECT * FROM ' . $this->codes_table . "
			WHERE code_hash = '" . $this->db->sql_escape($this->hash($code)) . "'
				AND expires_time > " . time());
		if (!$row)
		{
			$this->cache->put($fail_key, $fails + 1, self::FAIL_TTL);
			throw new action_exception('BM2_DISCORD_CODE_INVALID');
		}

		$user_id = (int) $row['user_id'];
		$existing = $this->get_by_discord($discord_id);
		if ($existing && (int) $existing['user_id'] !== $user_id)
		{
			throw new action_exception('BM2_DISCORD_ALREADY_LINKED_OTHER');
		}

		$this->db->sql_transaction('begin');
		$this->db->sql_query('DELETE FROM ' . $this->codes_table . ' WHERE user_id = ' . $user_id);
		$this->db->sql_query('DELETE FROM ' . $this->links_table . ' WHERE user_id = ' . $user_id);
		$this->db->sql_query('INSERT INTO ' . $this->links_table . ' ' . $this->db->sql_build_array('INSERT', array(
			'user_id'			=> $user_id,
			'discord_id'		=> $discord_id,
			'discord_username'	=> utf8_encode_ucr(utf8_substr((string) $discord_username, 0, 255)),
			'linked_time'		=> time(),
		)));
		$this->db->sql_transaction('commit');
		$this->cache->destroy($fail_key);

		return $user_id;
	}

	public function unlink($user_id)
	{
		$this->db->sql_query('DELETE FROM ' . $this->links_table . ' WHERE user_id = ' . (int) $user_id);
		$this->db->sql_query('DELETE FROM ' . $this->codes_table . ' WHERE user_id = ' . (int) $user_id);
	}

	/**
	* Code normalisé (majuscules, sans tiret ni espace) puis haché
	*/
	protected function hash($code)
	{
		return hash('sha256', preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $code)));
	}

	protected function fetch_one($sql)
	{
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		return $row ?: null;
	}
}
