<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace nathannau\boardmanager2\controller;

use nathannau\boardmanager2\core\renderer;
use nathannau\boardmanager2\core\rules;
use nathannau\boardmanager2\core\store;

/**
* Actions sur les séances. Le message de la séance n'est jamais modifié :
* tout est en base, et chaque action poste un « Message auto » dans le sujet.
*/
class main
{
	/** @var store */
	protected $store;

	/** @var rules */
	protected $rules;

	/** @var renderer */
	protected $renderer;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\language\language */
	protected $language;

	protected $root_path;
	protected $php_ext;

	/** @var bool une transaction est ouverte (annulée par error()) */
	protected $in_transaction = false;

	/** @var string lien de retour affiché avec les erreurs */
	protected $back_url = '';

	public function __construct(store $store, rules $rules, renderer $renderer, \phpbb\db\driver\driver_interface $db, \phpbb\user $user, \phpbb\auth\auth $auth, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\controller\helper $helper, \phpbb\language\language $language, $root_path, $php_ext)
	{
		$this->store = $store;
		$this->rules = $rules;
		$this->renderer = $renderer;
		$this->db = $db;
		$this->user = $user;
		$this->auth = $auth;
		$this->request = $request;
		$this->template = $template;
		$this->helper = $helper;
		$this->language = $language;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	/**
	* Création ou modification de la date de la séance d'un sujet
	*/
	public function configure($topic)
	{
		$this->init();
		$topic = $this->load_topic($topic);
		$marker_post = $this->renderer->marker_post($topic['topic_id']);
		if (!$marker_post)
		{
			$this->error('BM2_ERROR_NO_MARKER');
		}
		if (!$this->is_manager() && $marker_post['poster_id'] !== $this->user_id())
		{
			$this->error('BM2_ERROR_NOT_ALLOWED');
		}

		$session = $this->store->get_session_by_topic($topic['topic_id']);
		if ($session && $this->rules->is_past($session) && !$this->is_manager())
		{
			$this->error('BM2_ERROR_PAST');
		}
		$date = $this->request->variable('session_date', $session ? $this->rules->format_date($session['session_date']) : '');
		$errors = array();

		if ($this->request->is_set_post('submit'))
		{
			$timestamp = $this->rules->parse_date($date);
			if (!check_form_key('bm2_configure'))
			{
				$errors[] = $this->language->lang('FORM_INVALID');
			}
			else if ($timestamp === false)
			{
				$errors[] = $this->language->lang('BM2_ERROR_DATE');
			}
			else
			{
				$this->begin();
				$session = $this->store->get_session_by_topic($topic['topic_id']);
				if ($session)
				{
					$this->store->update_session_date($session['session_id'], $timestamp);
				}
				else
				{
					$this->store->create_session($topic['topic_id'], $timestamp, $this->user_id());
				}
				$this->commit();

				$this->reply($topic['topic_id'], $this->language->lang('BM2_AUTO_CONFIGURE', $this->renderer->format_session_date($timestamp)));
				redirect($this->back_url);
			}
		}

		add_form_key('bm2_configure');
		$this->template->assign_vars(array(
			'BM2_ERRORS'		=> $errors,
			'BM2_SESSION_DATE'	=> $date,
			'U_BM2_BACK'		=> $this->back_url,
		));
		return $this->helper->render('@nathannau_boardmanager2/configure.html', $this->language->lang('BM2_CONFIGURE_TITLE'));
	}

	/**
	* Ouverture d'une table
	*/
	public function open($session)
	{
		$this->init();
		list($session, $topic) = $this->load_session($session);
		$manager = $this->is_manager();

		// Déjà joueur ou MJ d'une table : pas de nouvelle table (un gestionnaire peut en ouvrir une pour un autre MJ)
		if (!$manager && $this->rules->is_engaged($this->rules->load($session), $this->user_id()))
		{
			$this->error('BM2_ERROR_ENGAGED');
		}

		$data = array(
			'session_id'		=> (int) $session['session_id'],
			'name'				=> $this->request->variable('name', '', true),
			'type'				=> $this->request->variable('type', 'os'),
			'url'				=> $this->request->variable('url', '', true),
			'start_time'		=> $this->request->variable('time', '21h', true),
			'min_players'		=> $this->request->variable('min', 3),
			'max_players'		=> $this->request->variable('max', 5),
			'trigger_warning'	=> $this->request->variable('trigger_warning', '', true),
			'description'		=> $this->request->variable('description', '', true),
		);
		$mj_name = $manager ? $this->request->variable('mj', $this->user->data['username'], true) : $this->user->data['username'];
		$preregistration = $this->request->variable('preinscription', '', true);
		$errors = array();

		if ($this->request->is_set_post('submit'))
		{
			$errors = array_map(array($this->language, 'lang'), $this->rules->validate_table($data));
			if (!check_form_key('bm2_open'))
			{
				$errors[] = $this->language->lang('FORM_INVALID');
			}

			$mj = $this->resolve_mj($mj_name, array('user_id' => $this->user_id(), 'username' => $this->user->data['username']), $errors);

			if (!$errors)
			{
				$this->begin();
				$state = $this->rules->load($session);
				if ($mj['user_id'] && $this->rules->is_engaged($state, $mj['user_id']))
				{
					$this->error($mj['user_id'] === $this->user_id() ? 'BM2_ERROR_ENGAGED' : 'BM2_ERROR_USER_ENGAGED', $mj['username']);
				}

				$table_id = $this->store->add_table(array_merge($data, array(
					'mj_user_id'	=> $mj['user_id'],
					'mj_username'	=> $mj['username'],
				)));
				$no_table = $this->rules->find_user($state['players'][0], $mj['user_id']);
				if ($no_table)
				{
					$this->store->remove_player($no_table['player_id']);
				}

				// Pré-inscriptions : « * » pour un compte du forum, joueur hors forum sinon
				$names = array_filter(array_map('trim', explode("\n", str_replace("\r", '', $preregistration))), 'strlen');
				$users = $this->rules->find_users_by_name($names);
				foreach ($names as $name)
				{
					$clean = utf8_clean_string($name);
					if (isset($users[$clean]))
					{
						$this->rules->register_user($state, $table_id, $users[$clean]['user_id'], $users[$clean]['username'], $this->user_id(), true);
					}
					else
					{
						$this->rules->add_guest_player($state, $table_id, $name, $this->user_id());
					}
				}
				$this->commit();

				$message = $this->language->lang('BM2_AUTO_OPEN', $data['name'], $data['start_time'], $data['min_players'], $data['max_players']);
				redirect($this->reply($topic['topic_id'], $message));
			}
		}

		add_form_key('bm2_open');
		$this->template->assign_vars(array(
			'BM2_ERRORS'		=> $errors,
			'BM2_TABLE'			=> $data,
			'BM2_TYPES'			=> $this->type_options(),
			'BM2_MANAGER'		=> $manager,
			'BM2_MJ'			=> $mj_name,
			'BM2_PREREGISTRATION'	=> $preregistration,
			'BM2_EDIT'			=> false,
			'U_BM2_BACK'		=> $this->back_url,
		));
		return $this->helper->render('@nathannau_boardmanager2/open.html', $this->language->lang('BM2_OPEN_TITLE'));
	}

	/**
	* Modification d'une table par son MJ ou un gestionnaire (qui peut aussi changer de MJ)
	*/
	public function edit($table)
	{
		$this->init();
		list($table, $session, $topic) = $this->load_table($table);
		$this->check_table_rights($table);
		$manager = $this->is_manager();

		$data = array(
			'name'				=> $this->request->variable('name', $table['name'], true),
			'type'				=> $this->request->variable('type', $table['type']),
			'url'				=> $this->request->variable('url', $table['url'], true),
			'start_time'		=> $this->request->variable('time', $table['start_time'], true),
			'min_players'		=> $this->request->variable('min', (int) $table['min_players']),
			'max_players'		=> $this->request->variable('max', (int) $table['max_players']),
			'trigger_warning'	=> $this->request->variable('trigger_warning', $table['trigger_warning'], true),
			'description'		=> $this->request->variable('description', $table['description'], true),
		);
		$mj_name = $manager ? $this->request->variable('mj', $table['mj_username'], true) : $table['mj_username'];
		$errors = array();

		if ($this->request->is_set_post('submit'))
		{
			$errors = array_map(array($this->language, 'lang'), $this->rules->validate_table($data));
			if (!check_form_key('bm2_edit'))
			{
				$errors[] = $this->language->lang('FORM_INVALID');
			}
			$mj = $this->resolve_mj($mj_name, array('user_id' => (int) $table['mj_user_id'], 'username' => $table['mj_username']), $errors);

			if (!$errors)
			{
				$this->begin();
				$state = $this->rules->load($session);
				$table_id = (int) $table['table_id'];
				if (!isset($state['tables'][$table_id]))
				{
					$this->error('BM2_ERROR_NOT_FOUND');
				}
				$count = $this->rules->count_players($state['players'][$table_id]);
				if ($data['max_players'] < $count)
				{
					$this->error('BM2_ERROR_MAX_BELOW_PLAYERS', $count);
				}

				// Nouveau MJ : il ne doit pas être engagé ailleurs, et quitte le sans table
				if ($mj['user_id'] !== (int) $table['mj_user_id'] || $mj['username'] !== $table['mj_username'])
				{
					if ($mj['user_id'] && $this->rules->is_engaged($state, $mj['user_id']))
					{
						$this->error('BM2_ERROR_USER_ENGAGED', $mj['username']);
					}
					$no_table = $this->rules->find_user($state['players'][0], $mj['user_id']);
					if ($no_table)
					{
						$this->store->remove_player($no_table['player_id']);
					}
					$data['mj_user_id'] = $mj['user_id'];
					$data['mj_username'] = $mj['username'];
				}

				$this->store->update_table($table_id, $data);
				$this->commit();

				$message = $this->language->lang('BM2_AUTO_EDIT', $data['name'], $mj['username'], $data['start_time'], $data['min_players'], $data['max_players']);
				redirect($this->reply($topic['topic_id'], $message));
			}
		}

		add_form_key('bm2_edit');
		$this->template->assign_vars(array(
			'BM2_ERRORS'		=> $errors,
			'BM2_TABLE'			=> $data,
			'BM2_TYPES'			=> $this->type_options(),
			'BM2_MANAGER'		=> $manager,
			'BM2_MJ'			=> $mj_name,
			'BM2_EDIT'			=> true,
			'U_BM2_BACK'		=> $this->back_url,
		));
		return $this->helper->render('@nathannau_boardmanager2/open.html', $this->language->lang('BM2_EDIT_TITLE'));
	}

	/**
	* Inscription en sans table ($table = 0) ou sur une table, ou confirmation d'une pré-inscription
	*/
	public function register($session, $table)
	{
		$this->init();
		$this->check_link('nathannau_boardmanager2_register', array('session' => $session, 'table' => $table));
		list($session, $topic) = $this->load_session($session);
		$table_id = (int) $table;
		$user_id = $this->user_id();

		$this->begin();
		$state = $this->rules->load($session);
		if ($table_id && !isset($state['tables'][$table_id]))
		{
			$this->error('BM2_ERROR_NOT_FOUND');
		}

		if (!$table_id)
		{
			if ($this->rules->is_engaged($state, $user_id))
			{
				$this->error('BM2_ERROR_ENGAGED');
			}
			if ($this->rules->find_user($state['players'][0], $user_id))
			{
				$this->error('BM2_ERROR_ALREADY_NO_TABLE');
			}
			$message = $this->language->lang('BM2_AUTO_REGISTER_NO_TABLE');
		}
		else
		{
			$own = $this->rules->find_user($state['players'][$table_id], $user_id);
			$confirm = $own && $own['temporary'];
			if ($own && !$confirm)
			{
				$this->error('BM2_ERROR_ENGAGED');
			}

			// Une pré-inscription ne compte pas comme engagement pour la confirmer
			if ($confirm ? $this->rules->is_engaged_except($state, $user_id, $own['player_id']) : $this->rules->is_engaged($state, $user_id))
			{
				$this->error('BM2_ERROR_ENGAGED');
			}
			if (!$confirm)
			{
				if (!$this->is_manager() && !$this->rules->can_register_on_tables($state, $user_id))
				{
					$this->error('BM2_ERROR_LIMIT_DAYS');
				}
				if ($this->rules->count_players($state['players'][$table_id]) >= $state['tables'][$table_id]['max_players'])
				{
					$this->error('BM2_ERROR_FULL');
				}
			}
			$message = $this->language->lang('BM2_AUTO_REGISTER_TABLE', $state['tables'][$table_id]['name'], $this->mj_name($state['tables'][$table_id]));
		}

		$this->rules->register_user($state, $table_id, $user_id, $this->user->data['username'], $user_id);
		$this->commit();

		redirect($this->reply($topic['topic_id'], $message));
	}

	/**
	* Désinscription (sans table ou table)
	*/
	public function unregister($session, $table)
	{
		$this->init();
		$this->check_link('nathannau_boardmanager2_unregister', array('session' => $session, 'table' => $table));
		list($session, $topic) = $this->load_session($session);
		$table_id = (int) $table;

		$this->begin();
		$state = $this->rules->load($session);
		if (!isset($state['players'][$table_id]))
		{
			$this->error('BM2_ERROR_NOT_FOUND');
		}
		$player = $this->rules->find_user($state['players'][$table_id], $this->user_id());
		if (!$player)
		{
			$this->error('BM2_ERROR_NOT_REGISTERED');
		}
		$this->store->remove_player($player['player_id']);
		$this->commit();

		$message = $table_id
			? $this->language->lang('BM2_AUTO_UNREGISTER_TABLE', $state['tables'][$table_id]['name'], $this->mj_name($state['tables'][$table_id]))
			: $this->language->lang('BM2_AUTO_UNREGISTER_NO_TABLE');
		redirect($this->reply($topic['topic_id'], $message));
	}

	/**
	* Annulation d'une table : ses joueurs (hors pré-inscriptions) passent en sans table
	*/
	public function cancel($table)
	{
		$this->init();
		$this->check_link('nathannau_boardmanager2_cancel', array('table' => $table));
		list($table, $session, $topic) = $this->load_table($table);
		$this->check_table_rights($table);

		$this->begin();
		$state = $this->rules->load($session);
		$this->store->cancel_table($table['table_id']);
		$moved = $this->store->move_players_to_no_table($table['table_id'], $state['no_table_id']);
		$this->commit();

		$lines = array($this->language->lang('BM2_AUTO_CANCEL', $table['name'], $table['start_time'], $table['min_players'], $table['max_players']));
		foreach ($moved as $player)
		{
			$lines[] = $this->language->lang('BM2_AUTO_MOVED', $player['username']);
		}
		redirect($this->reply($topic['topic_id'], implode("\n", $lines)));
	}

	/**
	* Ajout d'un joueur par le MJ ou un gestionnaire
	*/
	public function addplayer($table)
	{
		$this->init();
		list($table, $session, $topic) = $this->load_table($table);
		$this->check_table_rights($table);
		$manager = $this->is_manager();
		$name = trim($this->request->variable('player', '', true));
		$temporary = (bool) $this->request->variable('temporary', 0);
		$errors = array();

		if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key('bm2_addplayer'))
			{
				$errors[] = $this->language->lang('FORM_INVALID');
			}
			else if ($name === '')
			{
				$errors[] = $this->language->lang('BM2_ERROR_PLAYER_NAME');
			}
			else
			{
				$account = $this->rules->find_user_by_name($name);

				$this->begin();
				$state = $this->rules->load($session);
				$table_id = (int) $table['table_id'];
				if (!isset($state['tables'][$table_id]))
				{
					$this->error('BM2_ERROR_NOT_FOUND');
				}
				if (!$manager && $this->rules->count_players($state['players'][$table_id]) >= $table['max_players'])
				{
					$this->error('BM2_ERROR_FULL');
				}

				if ($account)
				{
					if ($this->rules->is_engaged($state, $account['user_id']))
					{
						$this->error('BM2_ERROR_USER_ENGAGED', $account['username']);
					}
					$this->rules->register_user($state, $table_id, $account['user_id'], $account['username'], $this->user_id(), $temporary);
					$name = $account['username'];
				}
				else
				{
					$this->rules->add_guest_player($state, $table_id, $name, $this->user_id(), $temporary);
				}
				$this->commit();

				$key = $temporary ? 'BM2_AUTO_PREREGISTER_PLAYER' : 'BM2_AUTO_ADD_PLAYER';
				redirect($this->reply($topic['topic_id'], $this->language->lang($key, $name, $table['name'], $this->mj_name($table))));
			}
		}

		add_form_key('bm2_addplayer');
		$this->template->assign_vars(array(
			'BM2_ERRORS'		=> $errors,
			'BM2_TABLE_NAME'	=> $table['name'],
			'BM2_PLAYER'		=> $name,
			'BM2_TEMPORARY'		=> $temporary,
			'U_BM2_BACK'		=> $this->back_url,
		));
		return $this->helper->render('@nathannau_boardmanager2/addplayer.html', $this->language->lang('BM2_ADDPLAYER_TITLE', $table['name']));
	}

	/**
	* Validation d'une pré-inscription « * » par un gestionnaire
	*/
	public function validate($player)
	{
		$this->init();
		$this->check_link('nathannau_boardmanager2_validate', array('player' => $player));
		$player = $this->store->get_player($player);
		if (!$player)
		{
			$this->error('BM2_ERROR_NOT_FOUND');
		}
		list($table, $session, $topic) = $this->load_table($player['table_id']);
		if (!$this->is_manager())
		{
			$this->error('BM2_ERROR_NOT_ALLOWED');
		}

		$this->begin();
		$state = $this->rules->load($session);
		$player = $this->store->get_player($player['player_id']);
		if (!$player['temporary'] || $player['removed'])
		{
			$this->error('BM2_ERROR_NOT_TEMPORARY');
		}
		if ($player['user_id'])
		{
			// Comme une confirmation par le joueur lui-même
			if ($this->rules->is_engaged_except($state, $player['user_id'], $player['player_id']))
			{
				$this->error('BM2_ERROR_USER_ENGAGED', $player['username']);
			}
			$this->rules->register_user($state, $table['table_id'], $player['user_id'], $player['username'], $this->user_id());
		}
		else
		{
			$this->store->confirm_player($player['player_id']);
		}
		$this->commit();

		redirect($this->reply($topic['topic_id'], $this->language->lang('BM2_AUTO_VALIDATE_PLAYER', $player['username'], $table['name'], $this->mj_name($table))));
	}

	/**
	* Retrait d'un joueur : hors forum par le MJ de la table, tout joueur par un gestionnaire
	*/
	public function removeplayer($player)
	{
		$this->init();
		$this->check_link('nathannau_boardmanager2_removeplayer', array('player' => $player));
		$player = $this->store->get_player($player);
		if (!$player)
		{
			$this->error('BM2_ERROR_NOT_FOUND');
		}
		$table = $this->store->get_table($player['table_id']);
		if (!$table || $table['cancelled'])
		{
			$this->error('BM2_ERROR_NOT_FOUND');
		}
		list($session, $topic) = $this->load_session($table['session_id']);
		if ($table['no_table'])
		{
			$table = null;
		}
		$is_mj = $table && (int) $table['mj_user_id'] === $this->user_id();
		if (!$this->is_manager() && !($is_mj && !$player['user_id']))
		{
			$this->error('BM2_ERROR_NOT_ALLOWED');
		}

		$this->begin();
		$this->store->remove_player($player['player_id']);
		$this->commit();

		$message = $table
			? $this->language->lang('BM2_AUTO_REMOVE_PLAYER', $player['username'], $table['name'], $this->mj_name($table))
			: $this->language->lang('BM2_AUTO_REMOVE_PLAYER_NO_TABLE', $player['username']);
		redirect($this->reply($topic['topic_id'], $message));
	}

	// Contrôles communs

	/**
	* Langue, et refus des invités (connexion demandée) et des robots
	*/
	protected function init()
	{
		$this->language->add_lang('common', 'nathannau/boardmanager2');
		if ($this->user_id() == ANONYMOUS)
		{
			login_box();
		}
		if (!empty($this->user->data['is_bot']))
		{
			$this->error('BM2_ERROR_NOT_ALLOWED');
		}
	}

	protected function user_id()
	{
		return (int) $this->user->data['user_id'];
	}

	protected function is_manager()
	{
		return $this->rules->is_manager($this->user->data);
	}

	protected function check_link($route, array $params)
	{
		if (!check_link_hash($this->request->variable('hash', ''), $this->renderer->link_name($route, $params)))
		{
			$this->error('BM2_ERROR_LINK');
		}
	}

	/**
	* MJ de la table ou gestionnaire
	*/
	protected function check_table_rights(array $table)
	{
		if (!$this->is_manager() && (int) $table['mj_user_id'] !== $this->user_id())
		{
			$this->error('BM2_ERROR_NOT_ALLOWED');
		}
	}

	/**
	* Sujet lisible par l'utilisateur (droit de lecture du forum)
	*/
	protected function load_topic($topic_id)
	{
		$sql = 'SELECT * FROM ' . TOPICS_TABLE . ' WHERE topic_id = ' . (int) $topic_id;
		$result = $this->db->sql_query($sql);
		$topic = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$topic || !$this->auth->acl_get('f_read', $topic['forum_id']))
		{
			$this->error('BM2_ERROR_NOT_FOUND');
		}
		$marker_post = $this->renderer->marker_post($topic['topic_id']);
		$this->back_url = $marker_post
			? append_sid("{$this->root_path}viewtopic.{$this->php_ext}", 'p=' . $marker_post['post_id']) . '#p' . $marker_post['post_id']
			: append_sid("{$this->root_path}viewtopic.{$this->php_ext}", 't=' . (int) $topic['topic_id']);
		return $topic;
	}

	/**
	* @return array(session, topic)
	*/
	protected function load_session($session_id)
	{
		$session = $this->store->get_session($session_id);
		if (!$session)
		{
			$this->error('BM2_ERROR_NOT_FOUND');
		}
		$topic = $this->load_topic($session['topic_id']);
		// Séance passée : seule la date reste modifiable (par un gestionnaire, voir configure())
		if ($this->rules->is_past($session))
		{
			$this->error('BM2_ERROR_PAST');
		}
		return array($session, $topic);
	}

	/**
	* Table non annulée
	*
	* @return array(table, session, topic)
	*/
	protected function load_table($table_id)
	{
		$table = $this->store->get_table($table_id);
		if (!$table || $table['cancelled'] || $table['no_table'])
		{
			$this->error('BM2_ERROR_NOT_FOUND');
		}
		list($session, $topic) = $this->load_session($table['session_id']);
		return array($table, $session, $topic);
	}

	/**
	* MJ d'une table : $default, ou pour un gestionnaire le pseudo saisi (compte du forum ou non)
	*
	* @return array user_id, username
	*/
	protected function resolve_mj($mj_name, array $default, array &$errors)
	{
		if (!$this->is_manager() || utf8_clean_string($mj_name) === utf8_clean_string($default['username']))
		{
			return $default;
		}
		$mj_name = trim($mj_name);
		if ($mj_name === '')
		{
			$errors[] = $this->language->lang('BM2_ERROR_MJ_NAME');
			return $default;
		}
		return $this->rules->find_user_by_name($mj_name) ?: array('user_id' => 0, 'username' => $mj_name);
	}

	protected function mj_name(array $table)
	{
		return $table['mj_username'];
	}

	protected function type_options()
	{
		$types = array();
		foreach (rules::TYPES as $type)
		{
			$types[$type] = $this->language->lang('BM2_TYPE_' . strtoupper($type));
		}
		return $types;
	}

	protected function begin()
	{
		$this->store->begin();
		$this->in_transaction = true;
	}

	protected function commit()
	{
		$this->store->commit();
		$this->in_transaction = false;
	}

	/**
	* Affiche une erreur (et annule la transaction en cours)
	*/
	protected function error($key, ...$args)
	{
		if ($this->in_transaction)
		{
			$this->store->rollback();
			$this->in_transaction = false;
		}
		$message = $this->language->lang($key, ...$args);
		if ($this->back_url)
		{
			$message .= '<br /><br />' . $this->language->lang('BM2_BACK_TO_TOPIC', '<a href="' . $this->back_url . '">', '</a>');
		}
		trigger_error($message, E_USER_WARNING);
	}

	/**
	* Poste un message auto dans le sujet. S'il est l'auteur du dernier message
	* (autre que le premier), le texte y est ajouté plutôt que de créer une réponse.
	*
	* @param string $message texte sous la forme stockée par phpBB (entités HTML échappées),
	*                        comme les noms lus en base
	* @return string url du message
	*/
	protected function reply($topic_id, $message)
	{
		if (!function_exists('submit_post'))
		{
			include($this->root_path . 'includes/functions_posting.' . $this->php_ext);
		}

		$sql = 'SELECT * FROM ' . TOPICS_TABLE . ' WHERE topic_id = ' . (int) $topic_id;
		$result = $this->db->sql_query($sql);
		$topic = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$sql = 'SELECT forum_name FROM ' . FORUMS_TABLE . ' WHERE forum_id = ' . (int) $topic['forum_id'];
		$result = $this->db->sql_query($sql);
		$forum_name = $this->db->sql_fetchfield('forum_name');
		$this->db->sql_freeresult($result);

		$sql = 'SELECT * FROM ' . POSTS_TABLE . '
			WHERE topic_id = ' . (int) $topic_id . '
			ORDER BY post_time DESC, post_id DESC';
		$result = $this->db->sql_query_limit($sql, 1);
		$last = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$merge = $last
			&& (int) $last['poster_id'] === $this->user_id()
			&& (int) $last['post_id'] !== (int) $topic['topic_first_post_id'];

		$uid = $bitfield = '';
		$flags = 0;
		if ($merge)
		{
			$options = ($last['enable_bbcode'] ? OPTION_FLAG_BBCODE : 0) | ($last['enable_smilies'] ? OPTION_FLAG_SMILIES : 0) | ($last['enable_magic_url'] ? OPTION_FLAG_LINKS : 0);
			$text = generate_text_for_edit($last['post_text'], $last['bbcode_uid'], $options);
			$text = $text['text'] . "\n\n" . $message;
			generate_text_for_storage($text, $uid, $bitfield, $flags, (bool) $last['enable_bbcode'], (bool) $last['enable_magic_url'], (bool) $last['enable_smilies']);
		}
		else
		{
			$text = $message;
			generate_text_for_storage($text, $uid, $bitfield, $flags, false, false, false);
		}

		$data = array(
			'poster_id'			=> $this->user_id(),
			'forum_id'			=> (int) $topic['forum_id'],
			'topic_id'			=> (int) $topic_id,
			'icon_id'			=> $merge ? (int) $last['icon_id'] : 0,
			'enable_bbcode'		=> $merge ? (bool) $last['enable_bbcode'] : false,
			'enable_smilies'	=> $merge ? (bool) $last['enable_smilies'] : false,
			'enable_urls'		=> $merge ? (bool) $last['enable_magic_url'] : false,
			'enable_sig'		=> $merge ? (bool) $last['enable_sig'] : true,
			'message'			=> $text,
			'message_md5'		=> md5($text),
			'bbcode_bitfield'	=> $bitfield,
			'bbcode_uid'		=> $uid,
			'post_edit_reason'	=> '',
			'post_edit_locked'	=> 0,
			'notify_set'		=> true,
			'notify'			=> false,
			'post_time'			=> 0,
			'enable_indexing'	=> true,
			'force_approved_state'	=> true,
			'force_visibility'	=> true,
			'topic_posts_approved'		=> $topic['topic_posts_approved'],
			'topic_posts_unapproved'	=> $topic['topic_posts_unapproved'],
			'topic_posts_softdeleted'	=> $topic['topic_posts_softdeleted'],
			'topic_first_post_id'		=> $topic['topic_first_post_id'],
			'topic_last_post_id'		=> $topic['topic_last_post_id'],
			'topic_title'		=> $topic['topic_title'],
			'forum_name'		=> $forum_name,
		);

		$subject = $topic['topic_title'];
		if ($merge)
		{
			$data['post_id'] = (int) $last['post_id'];
			$data['poster_ip'] = $last['poster_ip'];
			$data['post_visibility'] = $last['post_visibility'];
			$data['post_subject'] = $last['post_subject'];
			$subject = $last['post_subject'];
		}
		else if (strtolower(substr($subject, 0, 3)) !== 're:')
		{
			$subject = 'Re: ' . $subject;
		}

		$poll = array();
		return submit_post($merge ? 'edit' : 'reply', $subject, $this->user->data['username'], $topic['topic_type'], $poll, $data, true);
	}
}
