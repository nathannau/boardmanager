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
* Règles métier, reprises de la v1 (core/legacy).
*
* Les dates de séance sont des timestamps de minuit, heure de Paris.
*/
class rules
{
	/** Inscrits à moins de LIMIT_DAYS jours de la séance : affichés en rouge */
	const LIMIT_DAYS = 4;
	const TIMEZONE = 'Europe/Paris';

	const TYPES = array('os', 'co', 'cf', 'ts');

	/** @var store */
	protected $store;

	/** @var \phpbb\config\db_text */
	protected $config_text;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var array cache par requête de is_manager() */
	protected $managers = array();

	public function __construct(store $store, \phpbb\config\db_text $config_text, \phpbb\db\driver\driver_interface $db)
	{
		$this->store = $store;
		$this->config_text = $config_text;
		$this->db = $db;
	}

	// Gestionnaires

	/**
	* @return int[] groupes dont les membres gèrent toutes les séances
	*/
	public function get_manager_groups()
	{
		$groups = json_decode((string) $this->config_text->get('bm2_manager_groups'), true);
		return is_array($groups) ? array_map('intval', $groups) : array();
	}

	/**
	* Fondateur du forum, ou membre (non en attente) d'un groupe gestionnaire
	*
	* @param array $user_data $user->data
	*/
	public function is_manager(array $user_data)
	{
		$user_id = (int) $user_data['user_id'];
		if ($user_id == ANONYMOUS || !empty($user_data['is_bot']))
		{
			return false;
		}
		if (!isset($this->managers[$user_id]))
		{
			if ((int) $user_data['user_type'] === USER_FOUNDER)
			{
				$this->managers[$user_id] = true;
			}
			else
			{
				$groups = $this->get_manager_groups();
				if (!function_exists('group_memberships'))
				{
					global $phpbb_root_path, $phpEx;
					include($phpbb_root_path . 'includes/functions_user.' . $phpEx);
				}
				$this->managers[$user_id] = $groups && group_memberships($groups, $user_id, true);
			}
		}
		return $this->managers[$user_id];
	}

	// Comptes

	/**
	* Recherche des comptes par pseudo (comparaison sur username_clean)
	*
	* @param string[] $names pseudos (forme stockée par phpBB, entités HTML comprises)
	* @return array username_clean => array(user_id, username)
	*/
	public function find_users_by_name(array $names)
	{
		$clean = array();
		foreach ($names as $name)
		{
			$name = trim($name);
			if ($name !== '')
			{
				$clean[] = utf8_clean_string($name);
			}
		}
		if (!$clean)
		{
			return array();
		}

		$users = array();
		$result = $this->db->sql_query('SELECT user_id, username, username_clean
			FROM ' . USERS_TABLE . '
			WHERE ' . $this->db->sql_in_set('username_clean', array_unique($clean)) . '
				AND user_type <> ' . USER_IGNORE);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$users[$row['username_clean']] = array('user_id' => (int) $row['user_id'], 'username' => $row['username']);
		}
		$this->db->sql_freeresult($result);
		return $users;
	}

	/**
	* @return array|null array(user_id, username) du compte portant ce pseudo
	*/
	public function find_user_by_name($name)
	{
		$users = $this->find_users_by_name(array($name));
		$clean = utf8_clean_string(trim($name));
		return isset($users[$clean]) ? $users[$clean] : null;
	}

	// Dates

	/**
	* @param string $date AAAA-MM-JJ
	* @return int|false minuit (Paris) de ce jour
	*/
	public function parse_date($date)
	{
		$d = \DateTime::createFromFormat('!Y-m-d', $date, new \DateTimeZone(self::TIMEZONE));
		return ($d && $d->format('Y-m-d') === $date) ? $d->getTimestamp() : false;
	}

	/**
	* Date v1 : JJMM suivi de l'année. Comme mktime() en v1, une année
	* sur 2 ou 3 chiffres (« 090916 », « 1305016 ») est dans les années 2000.
	*
	* @param string $date JJMMAAAA (format v1)
	* @return int|false
	*/
	public function parse_v1_date($date)
	{
		if (!preg_match('/^(\d{2})(\d{2})(\d{2,4})$/', $date, $match))
		{
			return false;
		}
		$year = (int) $match[3];
		if ($year < 100)
		{
			$year += 2000;
		}
		if ($year > 2100 || !checkdate((int) $match[2], (int) $match[1], $year))
		{
			return false;
		}
		// Les colonnes TIMESTAMP de phpBB sont non signées
		$timestamp = $this->parse_date(sprintf('%04d-%02d-%02d', $year, $match[2], $match[1]));
		return ($timestamp !== false && $timestamp >= 0) ? $timestamp : false;
	}

	public function format_date($timestamp, $format = 'Y-m-d')
	{
		$d = new \DateTime('@' . (int) $timestamp);
		$d->setTimezone(new \DateTimeZone(self::TIMEZONE));
		return $d->format($format);
	}

	/**
	* Minuit (Paris) du jour contenant $timestamp
	*/
	public function midnight($timestamp)
	{
		return $this->parse_date($this->format_date($timestamp));
	}

	/**
	* Nombre de jours calendaires entre deux timestamps (Paris)
	*/
	public function days_between($from, $to)
	{
		$tz = new \DateTimeZone(self::TIMEZONE);
		$d1 = new \DateTime($this->format_date($from), $tz);
		$d2 = new \DateTime($this->format_date($to), $tz);
		return (int) $d1->diff($d2)->format('%r%a');
	}

	/**
	* Séance passée : à partir du lendemain de sa date
	*/
	public function is_past(array $session)
	{
		return $this->midnight(time()) > (int) $session['session_date'];
	}

	/**
	* Inscrit tardif (affiché en rouge) : moins de LIMIT_DAYS jours avant la séance
	*/
	public function is_late(array $player, array $session)
	{
		return $this->days_between($player['register_date'], $session['session_date']) < self::LIMIT_DAYS;
	}

	// État d'une séance

	/**
	* Charge une séance complète. Le « sans table » est rangé sous l'identifiant 0.
	*
	* @return array session, no_table_id, tables (table_id => table, hors sans table),
	*               players (table_id => joueurs, 0 pour le sans table)
	*/
	public function load($session)
	{
		$state = array('session' => $session, 'no_table_id' => 0, 'tables' => array(), 'players' => array(0 => array()));
		foreach ($this->store->get_tables($session['session_id']) as $table)
		{
			if ($table['no_table'])
			{
				$state['no_table_id'] = (int) $table['table_id'];
				continue;
			}
			$state['tables'][(int) $table['table_id']] = $table;
			$state['players'][(int) $table['table_id']] = array();
		}
		foreach ($this->store->get_players($session['session_id']) as $player)
		{
			$table_id = (int) $player['table_id'] === $state['no_table_id'] ? 0 : (int) $player['table_id'];
			if (isset($state['players'][$table_id]))
			{
				$state['players'][$table_id][] = $player;
			}
		}
		return $state;
	}

	/**
	* Identifiant réel en base (0 = le « sans table » de la séance)
	*/
	public function table_key(array $state, $table_id)
	{
		return $table_id ? (int) $table_id : $state['no_table_id'];
	}

	/**
	* Nombre de joueurs présents (non retirés, pré-inscriptions comprises) d'une table
	*/
	public function count_players(array $players)
	{
		$count = 0;
		foreach ($players as $player)
		{
			if (!$player['removed'])
			{
				$count++;
			}
		}
		return $count;
	}

	/**
	* Ligne active (non retirée) d'un utilisateur dans une liste de joueurs
	*/
	public function find_user(array $players, $user_id)
	{
		if (!$user_id)
		{
			return null;
		}
		foreach ($players as $player)
		{
			if ((int) $player['user_id'] === (int) $user_id && !$player['removed'])
			{
				return $player;
			}
		}
		return null;
	}

	/**
	* L'utilisateur est-il déjà joueur (même pré-inscrit) ou MJ d'une table de la séance ?
	*/
	public function is_engaged(array $state, $user_id)
	{
		foreach ($state['tables'] as $table_id => $table)
		{
			if ((int) $table['mj_user_id'] === (int) $user_id || $this->find_user($state['players'][$table_id], $user_id))
			{
				return true;
			}
		}
		return false;
	}

	/**
	* Règle J-3 : l'inscription sur une table est possible si la date de référence
	* (inscription en sans table, sinon maintenant) est au plus tard 3 jours avant la séance.
	*/
	public function can_register_on_tables(array $state, $user_id)
	{
		$no_table = $this->find_user($state['players'][0], $user_id);
		$reference = $no_table ? $this->midnight($no_table['register_date']) : time();
		return ($state['session']['session_date'] - $reference) / 86400 >= self::LIMIT_DAYS - 1;
	}

	/**
	* Places restantes : minimum pour lancer toutes les tables, et maximum
	*/
	public function remaining_places(array $state)
	{
		$min = 0;
		$max = 0;
		foreach ($state['tables'] as $table_id => $table)
		{
			$count = $this->count_players($state['players'][$table_id]);
			$min += max(0, $table['min_players'] - $count);
			$max += max(0, $table['max_players'] - $count);
		}
		return array($min, $max);
	}

	// Actions (à appeler dans une transaction, sur un état relu dans cette transaction)

	/**
	* Inscrit un utilisateur du forum sur une table (ou en sans table si $table_id = 0).
	* Sur une table : il quitte le sans table en gardant sa date d'inscription d'origine
	* et sa pré-inscription « * » éventuelle est supprimée.
	*
	* @return int player_id
	*/
	public function register_user(array $state, $table_id, $user_id, $username, $added_by, $temporary = false)
	{
		$date = time();
		if ($table_id && !$temporary)
		{
			$no_table = $this->find_user($state['players'][0], $user_id);
			if ($no_table)
			{
				$date = $no_table['register_date'];
				$this->store->remove_player($no_table['player_id']);
			}
			$this->store->delete_temporary($table_id, $user_id);
		}
		return $this->store->add_player(array(
			'table_id'		=> $this->table_key($state, $table_id),
			'user_id'		=> (int) $user_id,
			'username'		=> $username,
			'added_by'		=> (int) $added_by,
			'register_date'	=> $date,
			'temporary'		=> $temporary ? 1 : 0,
		));
	}

	/**
	* Joueur sans compte sur le forum, ajouté par le MJ ou un gestionnaire
	*/
	public function add_guest_player(array $state, $table_id, $name, $added_by, $temporary = false)
	{
		return $this->store->add_player(array(
			'table_id'		=> $this->table_key($state, $table_id),
			'user_id'		=> 0,
			'username'		=> $name,
			'added_by'		=> (int) $added_by,
			'temporary'		=> $temporary ? 1 : 0,
		));
	}

	/**
	* L'utilisateur est-il engagé ailleurs que par sa ligne $player_id ?
	*/
	public function is_engaged_except(array $state, $user_id, $player_id)
	{
		foreach ($state['players'] as $table_id => $players)
		{
			$state['players'][$table_id] = array_filter($players, function ($player) use ($player_id) {
				return (int) $player['player_id'] !== (int) $player_id;
			});
		}
		return $this->is_engaged($state, $user_id);
	}

	/**
	* Valide les données d'ouverture de table
	*
	* @return string[] clés de langue des erreurs
	*/
	public function validate_table(array $data)
	{
		$errors = array();
		if (trim($data['name']) === '')
		{
			$errors[] = 'BM2_ERROR_NAME';
		}
		if (!in_array($data['type'], self::TYPES, true))
		{
			$errors[] = 'BM2_ERROR_TYPE';
		}
		if (trim($data['start_time']) === '')
		{
			$errors[] = 'BM2_ERROR_TIME';
		}
		if ($data['min_players'] < 1 || $data['max_players'] < $data['min_players'])
		{
			$errors[] = 'BM2_ERROR_PLAYERS';
		}
		if ($data['url'] !== '' && !$this->is_valid_url($data['url']))
		{
			$errors[] = 'BM2_ERROR_URL';
		}
		return $errors;
	}

	public function is_valid_url($url)
	{
		return (bool) preg_match('#^https?://[^\s"<>]+$#i', $url);
	}
}
