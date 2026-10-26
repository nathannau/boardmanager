<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace nathannau\boardmanager2\core;

/**
* Accès aux tables bm2_sessions, bm2_tables et bm2_players.
*
* Les écritures ne gèrent pas elles-mêmes les transactions : chaque action
* du contrôleur les encadre avec begin() / commit().
*/
class store
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	protected $sessions_table;
	protected $tables_table;
	protected $players_table;

	public function __construct(\phpbb\db\driver\driver_interface $db, $sessions_table, $tables_table, $players_table)
	{
		$this->db = $db;
		$this->sessions_table = $sessions_table;
		$this->tables_table = $tables_table;
		$this->players_table = $players_table;
	}

	public function begin()
	{
		$this->db->sql_transaction('begin');
	}

	public function commit()
	{
		$this->db->sql_transaction('commit');
	}

	public function rollback()
	{
		$this->db->sql_transaction('rollback');
	}

	// Séances

	public function get_session($session_id)
	{
		return $this->fetch_one('SELECT * FROM ' . $this->sessions_table . ' WHERE session_id = ' . (int) $session_id);
	}

	public function get_session_by_post($post_id)
	{
		return $this->fetch_one('SELECT * FROM ' . $this->sessions_table . ' WHERE post_id = ' . (int) $post_id);
	}

	/**
	* @return int session_id
	*/
	public function create_session($post_id, $topic_id, $session_date, $created_by, $source = 'v2', $created_time = null)
	{
		$data = array(
			'post_id'		=> (int) $post_id,
			'topic_id'		=> (int) $topic_id,
			'session_date'	=> (int) $session_date,
			'created_by'	=> (int) $created_by,
			'created_time'	=> $created_time === null ? time() : (int) $created_time,
			'source'		=> $source,
		);
		$this->db->sql_query('INSERT INTO ' . $this->sessions_table . ' ' . $this->db->sql_build_array('INSERT', $data));
		return (int) $this->db->sql_nextid();
	}

	public function update_session_date($session_id, $session_date)
	{
		$this->db->sql_query('UPDATE ' . $this->sessions_table . '
			SET session_date = ' . (int) $session_date . '
			WHERE session_id = ' . (int) $session_id);
	}

	/**
	* Supprime les tables et joueurs d'une séance (réimport v1)
	*/
	public function delete_session_content($session_id)
	{
		$this->db->sql_query('DELETE FROM ' . $this->players_table . ' WHERE session_id = ' . (int) $session_id);
		$this->db->sql_query('DELETE FROM ' . $this->tables_table . ' WHERE session_id = ' . (int) $session_id);
	}

	// Tables

	public function get_table($table_id)
	{
		return $this->fetch_one('SELECT * FROM ' . $this->tables_table . ' WHERE table_id = ' . (int) $table_id);
	}

	/**
	* Tables d'une séance, dans l'ordre d'ouverture
	*/
	public function get_tables($session_id, $include_cancelled = false)
	{
		$sql = 'SELECT * FROM ' . $this->tables_table . '
			WHERE session_id = ' . (int) $session_id .
			($include_cancelled ? '' : ' AND cancelled = 0') . '
			ORDER BY table_id';
		return $this->fetch_all($sql);
	}

	/**
	* @param array $data colonnes de bm2_tables (hors table_id)
	* @return int table_id
	*/
	public function add_table(array $data)
	{
		$data = array_merge(array(
			'cancelled'			=> 0,
			'cancelled_time'	=> 0,
			'created_time'		=> time(),
		), $data);
		foreach (array('session_id', 'min_players', 'max_players', 'mj_user_id', 'cancelled', 'cancelled_time', 'created_time') as $key)
		{
			$data[$key] = (int) $data[$key];
		}
		$this->db->sql_query('INSERT INTO ' . $this->tables_table . ' ' . $this->db->sql_build_array('INSERT', $data));
		return (int) $this->db->sql_nextid();
	}

	public function cancel_table($table_id)
	{
		$this->db->sql_query('UPDATE ' . $this->tables_table . '
			SET cancelled = 1, cancelled_time = ' . time() . '
			WHERE table_id = ' . (int) $table_id);
	}

	// Joueurs

	public function get_player($player_id)
	{
		return $this->fetch_one('SELECT * FROM ' . $this->players_table . ' WHERE player_id = ' . (int) $player_id);
	}

	/**
	* Joueurs d'une séance (toutes tables confondues, table_id = 0 pour le sans table),
	* dans l'ordre d'inscription
	*/
	public function get_players($session_id)
	{
		return $this->fetch_all('SELECT * FROM ' . $this->players_table . '
			WHERE session_id = ' . (int) $session_id . '
			ORDER BY player_id');
	}

	/**
	* @param array $data colonnes de bm2_players (hors player_id)
	* @return int player_id
	*/
	public function add_player(array $data)
	{
		$data = array_merge(array(
			'table_id'		=> 0,
			'user_id'		=> 0,
			'added_by'		=> 0,
			'register_date'	=> time(),
			'removed'		=> 0,
			'temporary'		=> 0,
		), $data);
		foreach (array('session_id', 'table_id', 'user_id', 'added_by', 'register_date', 'removed', 'temporary') as $key)
		{
			$data[$key] = (int) $data[$key];
		}
		$this->db->sql_query('INSERT INTO ' . $this->players_table . ' ' . $this->db->sql_build_array('INSERT', $data));
		return (int) $this->db->sql_nextid();
	}

	/**
	* Marque un joueur comme retiré (il reste affiché barré)
	*/
	public function remove_player($player_id)
	{
		$this->db->sql_query('UPDATE ' . $this->players_table . '
			SET removed = 1
			WHERE player_id = ' . (int) $player_id);
	}

	/**
	* Supprime les lignes de pré-inscription (« * ») d'un utilisateur sur une table
	*/
	public function delete_temporary($table_id, $user_id)
	{
		$this->db->sql_query('DELETE FROM ' . $this->players_table . '
			WHERE table_id = ' . (int) $table_id . '
				AND user_id = ' . (int) $user_id . '
				AND user_id <> 0
				AND temporary = 1');
	}

	/**
	* Passe en sans table les joueurs présents et non temporaires d'une table
	*
	* @return array les joueurs déplacés
	*/
	public function move_players_to_no_table($table_id)
	{
		$players = $this->fetch_all('SELECT * FROM ' . $this->players_table . '
			WHERE table_id = ' . (int) $table_id . '
				AND removed = 0
				AND temporary = 0
			ORDER BY player_id');
		if ($players)
		{
			$ids = array_map('intval', array_column($players, 'player_id'));
			$this->db->sql_query('UPDATE ' . $this->players_table . '
				SET table_id = 0
				WHERE ' . $this->db->sql_in_set('player_id', $ids));
		}
		return $players;
	}

	protected function fetch_one($sql)
	{
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		return $row ?: null;
	}

	protected function fetch_all($sql)
	{
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);
		return $rows ?: array();
	}
}
