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
* Accès aux tables bm2_sessions, bm2_tables et bm2_players.
*
* Une séance par sujet. Le « sans table » d'une séance est une ligne de
* bm2_tables (no_table = 1), créée avec la séance : un joueur n'est donc
* rattaché qu'à une table.
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

	public function get_session_by_topic($topic_id)
	{
		return $this->fetch_one('SELECT * FROM ' . $this->sessions_table . ' WHERE topic_id = ' . (int) $topic_id);
	}

	/**
	* Crée la séance et sa ligne « sans table »
	*
	* @return int session_id
	*/
	public function create_session($topic_id, $session_date, $created_by, $source = 'v2', $created_time = null)
	{
		$data = array(
			'topic_id'		=> (int) $topic_id,
			'session_date'	=> (int) $session_date,
			'created_by'	=> (int) $created_by,
			'created_time'	=> $created_time === null ? time() : (int) $created_time,
			'source'		=> $source,
		);
		$this->db->sql_query('INSERT INTO ' . $this->sessions_table . ' ' . $this->db->sql_build_array('INSERT', $data));
		$session_id = (int) $this->db->sql_nextid();
		$this->add_no_table($session_id);
		return $session_id;
	}

	public function update_session_date($session_id, $session_date)
	{
		$this->db->sql_query('UPDATE ' . $this->sessions_table . '
			SET session_date = ' . (int) $session_date . '
			WHERE session_id = ' . (int) $session_id);
	}

	/**
	* Vide une séance (réimport v1) : il ne reste que son « sans table », vide
	*/
	public function delete_session_content($session_id)
	{
		$table_ids = array_map('intval', array_column($this->get_tables($session_id, true), 'table_id'));
		if ($table_ids)
		{
			$this->db->sql_query('DELETE FROM ' . $this->players_table . ' WHERE ' . $this->db->sql_in_set('table_id', $table_ids));
			$this->db->sql_query('DELETE FROM ' . $this->tables_table . ' WHERE ' . $this->db->sql_in_set('table_id', $table_ids));
		}
		$this->add_no_table($session_id);
	}

	/**
	* Séances dont le sujet n'existe plus, avec leur nombre de tables et de joueurs
	*/
	public function get_orphan_sessions()
	{
		return $this->fetch_all('SELECT s.*,
				(SELECT COUNT(*) FROM ' . $this->tables_table . ' t
					WHERE t.session_id = s.session_id AND t.no_table = 0) AS nb_tables,
				(SELECT COUNT(*) FROM ' . $this->players_table . ' p
					JOIN ' . $this->tables_table . ' t ON t.table_id = p.table_id
					WHERE t.session_id = s.session_id) AS nb_players
			FROM ' . $this->sessions_table . ' s
			LEFT JOIN ' . TOPICS_TABLE . ' tp ON tp.topic_id = s.topic_id
			WHERE tp.topic_id IS NULL
			ORDER BY s.session_date DESC');
	}

	public function is_orphan(array $session)
	{
		return !$this->topic_exists($session['topic_id']);
	}

	public function topic_exists($topic_id)
	{
		return (bool) $this->fetch_one('SELECT topic_id FROM ' . TOPICS_TABLE . ' WHERE topic_id = ' . (int) $topic_id);
	}

	/**
	* Rattache une séance à un autre sujet
	*/
	public function move_session($session_id, $topic_id)
	{
		$this->db->sql_query('UPDATE ' . $this->sessions_table . '
			SET topic_id = ' . (int) $topic_id . '
			WHERE session_id = ' . (int) $session_id);
	}

	/**
	* Supprime définitivement une séance, ses tables et ses joueurs
	*/
	public function delete_session($session_id)
	{
		$table_ids = array_map('intval', array_column($this->get_tables($session_id, true), 'table_id'));
		if ($table_ids)
		{
			$this->db->sql_query('DELETE FROM ' . $this->players_table . ' WHERE ' . $this->db->sql_in_set('table_id', $table_ids));
			$this->db->sql_query('DELETE FROM ' . $this->tables_table . ' WHERE ' . $this->db->sql_in_set('table_id', $table_ids));
		}
		$this->db->sql_query('DELETE FROM ' . $this->sessions_table . ' WHERE session_id = ' . (int) $session_id);
	}

	// Tables

	public function get_table($table_id)
	{
		return $this->fetch_one('SELECT * FROM ' . $this->tables_table . ' WHERE table_id = ' . (int) $table_id);
	}

	/**
	* Tables d'une séance (« sans table » compris), dans l'ordre d'ouverture
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
			'no_table'			=> 0,
			'cancelled'			=> 0,
			'cancelled_time'	=> 0,
			'created_time'		=> time(),
		), $data);
		foreach (array('session_id', 'no_table', 'min_players', 'max_players', 'mj_user_id', 'cancelled', 'cancelled_time', 'created_time') as $key)
		{
			$data[$key] = (int) $data[$key];
		}
		foreach (array('name', 'type', 'url', 'start_time', 'mj_username', 'trigger_warning', 'description') as $key)
		{
			$data[$key] = $this->text($data[$key], !in_array($key, array('url', 'trigger_warning', 'description')));
		}
		$this->db->sql_query('INSERT INTO ' . $this->tables_table . ' ' . $this->db->sql_build_array('INSERT', $data));
		return (int) $this->db->sql_nextid();
	}

	/**
	* @param array $data colonnes de bm2_tables à modifier
	*/
	public function update_table($table_id, array $data)
	{
		foreach (array('min_players', 'max_players', 'mj_user_id') as $key)
		{
			if (isset($data[$key]))
			{
				$data[$key] = (int) $data[$key];
			}
		}
		foreach (array('name', 'type', 'url', 'start_time', 'mj_username', 'trigger_warning', 'description') as $key)
		{
			if (isset($data[$key]))
			{
				$data[$key] = $this->text($data[$key], !in_array($key, array('url', 'trigger_warning', 'description')));
			}
		}
		$this->db->sql_query('UPDATE ' . $this->tables_table . ' SET ' . $this->db->sql_build_array('UPDATE', $data) . '
			WHERE table_id = ' . (int) $table_id . '
				AND no_table = 0');
	}

	protected function add_no_table($session_id)
	{
		return $this->add_table(array(
			'session_id'		=> $session_id,
			'no_table'			=> 1,
			'name'				=> '',
			'type'				=> '',
			'url'				=> '',
			'start_time'		=> '',
			'min_players'		=> 0,
			'max_players'		=> 0,
			'mj_user_id'		=> 0,
			'mj_username'		=> '',
			'trigger_warning'	=> '',
			'description'		=> '',
		));
	}

	/**
	* @return int table_id du « sans table » de la séance
	*/
	public function get_no_table_id($session_id)
	{
		$row = $this->fetch_one('SELECT table_id FROM ' . $this->tables_table . '
			WHERE session_id = ' . (int) $session_id . ' AND no_table = 1');
		return $row ? (int) $row['table_id'] : 0;
	}

	public function cancel_table($table_id)
	{
		$this->db->sql_query('UPDATE ' . $this->tables_table . '
			SET cancelled = 1, cancelled_time = ' . time() . '
			WHERE table_id = ' . (int) $table_id . '
				AND no_table = 0');
	}

	// Joueurs

	public function get_player($player_id)
	{
		return $this->fetch_one('SELECT * FROM ' . $this->players_table . ' WHERE player_id = ' . (int) $player_id);
	}

	/**
	* Joueurs des tables non annulées d'une séance, dans l'ordre d'inscription
	*/
	public function get_players($session_id)
	{
		return $this->fetch_all('SELECT p.*
			FROM ' . $this->players_table . ' p
			JOIN ' . $this->tables_table . ' t ON t.table_id = p.table_id
			WHERE t.session_id = ' . (int) $session_id . '
				AND t.cancelled = 0
			ORDER BY p.player_id');
	}

	/**
	* @param array $data colonnes de bm2_players (hors player_id)
	* @return int player_id
	*/
	public function add_player(array $data)
	{
		$data = array_merge(array(
			'user_id'		=> 0,
			'added_by'		=> 0,
			'register_date'	=> time(),
			'removed'		=> 0,
			'temporary'		=> 0,
		), $data);
		foreach (array('table_id', 'user_id', 'added_by', 'register_date', 'removed', 'temporary') as $key)
		{
			$data[$key] = (int) $data[$key];
		}
		$data['username'] = $this->text($data['username'], true);
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
	* Valide une pré-inscription sur place (joueur hors forum)
	*/
	public function confirm_player($player_id)
	{
		$this->db->sql_query('UPDATE ' . $this->players_table . '
			SET temporary = 0
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
	* Passe dans le « sans table » les joueurs présents et non temporaires d'une table
	*
	* @return array les joueurs déplacés
	*/
	public function move_players_to_no_table($table_id, $no_table_id)
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
				SET table_id = ' . (int) $no_table_id . '
				WHERE ' . $this->db->sql_in_set('player_id', $ids));
		}
		return $players;
	}

	/**
	* Texte prêt pour une colonne phpBB : caractères hors BMP (émojis) en entités,
	* comme pour les messages, et 255 caractères au plus pour les colonnes VCHAR_UNI
	*/
	protected function text($text, $short)
	{
		$text = utf8_encode_ucr((string) $text);
		return ($short && utf8_strlen($text) > 255) ? utf8_substr($text, 0, 255) : $text;
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
