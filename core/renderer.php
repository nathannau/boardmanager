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
* Rendu HTML d'une séance (template session.html)
*/
class renderer
{
	/** @var store */
	protected $store;

	/** @var rules */
	protected $rules;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var array cache par requête de marker_post() */
	protected $marker_posts = array();

	public function __construct(store $store, rules $rules, \phpbb\controller\helper $helper, \phpbb\template\template $template, \phpbb\user $user, \phpbb\language\language $language, \phpbb\db\driver\driver_interface $db)
	{
		$this->db = $db;
		$this->store = $store;
		$this->rules = $rules;
		$this->helper = $helper;
		$this->template = $template;
		$this->user = $user;
		$this->language = $language;
	}

	/**
	* Lien d'action protégé par un hash propre à l'utilisateur
	*/
	public function action_link($route, array $params)
	{
		$params['hash'] = generate_link_hash($this->link_name($route, $params));
		return $this->helper->route($route, $params);
	}

	/**
	* Nom du hash d'un lien d'action (le même est recalculé par le contrôleur)
	*/
	public function link_name($route, array $params)
	{
		unset($params['hash']);
		return $route . '_' . implode('_', array_map('intval', $params));
	}

	/**
	* Premier message du sujet contenant le marqueur : c'est lui qui affiche la séance
	*
	* @return array|null post_id, poster_id
	*/
	public function marker_post($topic_id)
	{
		$topic_id = (int) $topic_id;
		if (!array_key_exists($topic_id, $this->marker_posts))
		{
			$sql = 'SELECT post_id, poster_id
				FROM ' . POSTS_TABLE . '
				WHERE topic_id = ' . $topic_id . '
					AND post_text ' . $this->db->sql_like_expression($this->db->get_any_char() . '<BOARDMANAGER2>' . $this->db->get_any_char()) . '
				ORDER BY post_id';
			$result = $this->db->sql_query_limit($sql, 1);
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);
			$this->marker_posts[$topic_id] = $row ? array_map('intval', $row) : null;
		}
		return $this->marker_posts[$topic_id];
	}

	/**
	* @param int $topic_id
	* @param int $poster_id auteur du message portant le marqueur
	* @return string HTML
	*/
	public function render($topic_id, $poster_id)
	{
		$this->language->add_lang('common', 'nathannau/boardmanager2');

		$user_id = (int) $this->user->data['user_id'];
		$logged = $user_id != ANONYMOUS && empty($this->user->data['is_bot']);
		$manager = $logged && $this->rules->is_manager($this->user->data);

		$data = array(
			'CONFIGURED'	=> false,
			'U_CONFIGURE'	=> ($logged && ($manager || $user_id === (int) $poster_id))
				? $this->helper->route('nathannau_boardmanager2_configure', array('topic' => (int) $topic_id))
				: '',
		);

		$session = $this->store->get_session_by_topic($topic_id);
		if ($session)
		{
			$data = array_merge($data, $this->session_data($session, $user_id, $logged, $manager));
		}

		$this->template->assign_var('BM2', $data);
		$this->template->set_filenames(array('bm2_session' => '@nathannau_boardmanager2/session.html'));
		return $this->template->assign_display('bm2_session');
	}

	protected function session_data(array $session, $user_id, $logged, $manager)
	{
		$state = $this->rules->load($session);
		$session_id = (int) $session['session_id'];
		$engaged = $logged && $this->rules->is_engaged($state, $user_id);
		$can_add = $manager || ($logged && $this->rules->can_register_on_tables($state, $user_id));
		$users = $this->load_users($state);

		$no_table_players = $state['players'][0];
		$data = array(
			'CONFIGURED'		=> true,
			'DATE'				=> $this->format_session_date($session['session_date']),
			'NO_TABLE'			=> array(
				'COUNT'			=> $this->language->lang('BM2_NB_PLAYERS', $this->rules->count_players($no_table_players)),
				'U_REGISTER'	=> ($logged && !$engaged && !$this->rules->find_user($no_table_players, $user_id))
					? $this->action_link('nathannau_boardmanager2_register', array('session' => $session_id, 'table' => 0))
					: '',
				'PLAYERS'		=> $this->players_data($state, 0, $no_table_players, $users, $user_id, $manager),
			),
			'TABLES'			=> array(),
			'U_OPEN'			=> ($logged && ($manager || !$engaged))
				? $this->helper->route('nathannau_boardmanager2_open', array('session' => $session_id))
				: '',
		);

		foreach ($state['tables'] as $table_id => $table)
		{
			$players = $state['players'][$table_id];
			$is_mj = $logged && (int) $table['mj_user_id'] === $user_id;
			$can_manage = $manager || $is_mj;
			$full = $this->rules->count_players($players) >= $table['max_players'];
			$type = strtoupper($table['type']);

			$data['TABLES'][] = array(
				'NAME'				=> $table['name'],
				'URL'				=> $this->rules->is_valid_url($table['url']) ? $table['url'] : '',
				'TYPE'				=> in_array($table['type'], rules::TYPES, true) ? $this->language->lang('BM2_TYPE_' . $type) : '',
				'PLAYERS_RANGE'		=> $this->language->lang('BM2_PLAYERS_RANGE', $table['min_players'], $table['max_players']),
				'START_TIME'		=> $table['start_time'],
				'DESCRIPTION'		=> $table['description'],
				'TRIGGER_WARNING'	=> $table['trigger_warning'],
				'MJ'				=> $this->username_html($table['mj_user_id'], $table['mj_username'], $users),
				'MJ_GUEST'			=> !$table['mj_user_id'] && $table['mj_username'] !== '',
				'U_REGISTER'		=> ($logged && !$engaged && !$full && $can_add)
					? $this->action_link('nathannau_boardmanager2_register', array('session' => $session_id, 'table' => $table_id))
					: '',
				'U_CANCEL'			=> $can_manage ? $this->action_link('nathannau_boardmanager2_cancel', array('table' => $table_id)) : '',
				'L_CANCEL'			=> $this->language->lang($is_mj ? 'BM2_CANCEL_MY_TABLE' : 'BM2_CANCEL_TABLE'),
				'U_ADDPLAYER'		=> $can_manage ? $this->helper->route('nathannau_boardmanager2_addplayer', array('table' => $table_id)) : '',
				'PLAYERS'			=> $this->players_data($state, $table_id, $players, $users, $user_id, $can_manage, $manager),
			);
		}

		list($min, $max) = $this->rules->remaining_places($state);
		$data['REMAINING_MIN'] = $this->language->lang('BM2_REMAINING_MIN', $min);
		$data['REMAINING_MAX'] = $this->language->lang('BM2_REMAINING_MAX', $max);

		return $data;
	}

	/**
	* @param bool $can_manage MJ de la table ou gestionnaire : peut retirer les joueurs hors forum
	* @param bool $manager gestionnaire : peut retirer tous les joueurs
	*/
	protected function players_data(array $state, $table_id, array $players, array $users, $user_id, $can_manage, $manager = null)
	{
		$manager = $manager === null ? $can_manage : $manager;
		$session_id = (int) $state['session']['session_id'];
		$rows = array();
		foreach ($players as $player)
		{
			$own = $user_id != ANONYMOUS && (int) $player['user_id'] === (int) $user_id;
			$active = !$player['removed'];
			$guest = !$player['user_id'];
			$link = array('session' => $session_id, 'table' => (int) $table_id);

			$rows[] = array(
				'NAME'			=> $this->username_html($player['user_id'], $player['username'], $users),
				'GUEST'			=> $guest,
				'REMOVED'		=> !$active,
				'TEMPORARY'		=> (bool) $player['temporary'],
				'LATE'			=> $this->rules->is_late($player, $state['session']),
				'U_UNREGISTER'	=> ($own && $active) ? $this->action_link('nathannau_boardmanager2_unregister', $link) : '',
				'U_CONFIRM'		=> ($own && $active && $player['temporary']) ? $this->action_link('nathannau_boardmanager2_register', $link) : '',
				'U_REMOVE'		=> (!$own && $active && ($manager || ($can_manage && $guest)))
					? $this->action_link('nathannau_boardmanager2_removeplayer', array('player' => (int) $player['player_id']))
					: '',
			);
		}
		return $rows;
	}

	/**
	* Pseudo et couleur actuels des membres cités dans la séance
	*/
	protected function load_users(array $state)
	{
		$ids = array();
		foreach ($state['tables'] as $table)
		{
			$ids[] = (int) $table['mj_user_id'];
		}
		foreach ($state['players'] as $players)
		{
			foreach ($players as $player)
			{
				$ids[] = (int) $player['user_id'];
			}
		}
		$ids = array_filter(array_unique($ids));
		if (!$ids)
		{
			return array();
		}

		$users = array();
		$result = $this->db->sql_query('SELECT user_id, username, user_colour FROM ' . USERS_TABLE . ' WHERE ' . $this->db->sql_in_set('user_id', $ids));
		while ($row = $this->db->sql_fetchrow($result))
		{
			$users[(int) $row['user_id']] = $row;
		}
		$this->db->sql_freeresult($result);
		return $users;
	}

	protected function username_html($user_id, $username, array $users)
	{
		$user_id = (int) $user_id;
		if ($user_id && isset($users[$user_id]))
		{
			return get_username_string('full', $user_id, $users[$user_id]['username'], $users[$user_id]['user_colour']);
		}
		return $username;
	}

	public function format_session_date($timestamp)
	{
		$days = array('dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi');
		$months = array('', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre');
		$parts = explode('-', $this->rules->format_date($timestamp, 'w-j-n-Y'));
		return $days[$parts[0]] . ' ' . $parts[1] . ' ' . $months[$parts[2]] . ' ' . $parts[3];
	}
}
