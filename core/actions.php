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
* Actions des joueurs (s'inscrire, sans table, confirmer, annuler sa présence),
* communes au forum et à Discord. Chaque action est faite au nom d'un membre
* (actor) et refusée par une action_exception.
*/
class actions
{
	/** @var store */
	protected $store;

	/** @var rules */
	protected $rules;

	/** @var poster */
	protected $poster;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\language\language */
	protected $language;

	public function __construct(store $store, rules $rules, poster $poster, \phpbb\db\driver\driver_interface $db, \phpbb\user $user, \phpbb\auth\auth $auth, \phpbb\language\language $language)
	{
		$this->store = $store;
		$this->rules = $rules;
		$this->poster = $poster;
		$this->db = $db;
		$this->user = $user;
		$this->auth = $auth;
		$this->language = $language;
	}

	// Membres

	/**
	* Le membre connecté sur le forum
	*/
	public function current_actor()
	{
		return new actor($this->user->data, $this->auth, $this->rules->is_manager($this->user->data));
	}

	/**
	* Un membre retrouvé par son identifiant, avec ses propres permissions
	*
	* @return actor|null null si le compte n'existe pas ou est inactif
	*/
	public function actor_for_user($user_id)
	{
		$sql = 'SELECT * FROM ' . USERS_TABLE . '
			WHERE user_id = ' . (int) $user_id . '
				AND ' . $this->db->sql_in_set('user_type', array(USER_NORMAL, USER_FOUNDER));
		$result = $this->db->sql_query($sql);
		$data = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		if (!$data)
		{
			return null;
		}

		$auth = new \phpbb\auth\auth();
		$auth->acl($data);
		return new actor($data, $auth, $this->rules->is_manager($data));
	}

	// Contrôles communs

	/**
	* Sujet lisible par le membre (droit de lecture du forum)
	*
	* @throws action_exception
	*/
	public function topic_for(actor $actor, $topic_id)
	{
		$result = $this->db->sql_query('SELECT * FROM ' . TOPICS_TABLE . ' WHERE topic_id = ' . (int) $topic_id);
		$topic = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$topic || !$actor->auth->acl_get('f_read', $topic['forum_id']))
		{
			throw new action_exception('BM2_ERROR_NOT_FOUND');
		}
		return $topic;
	}

	/**
	* Séance existante, non passée, dans un sujet lisible par le membre
	*
	* @return array(session, topic)
	* @throws action_exception
	*/
	public function session_for(actor $actor, $session_id)
	{
		$session = $this->store->get_session($session_id);
		if (!$session)
		{
			throw new action_exception('BM2_ERROR_NOT_FOUND');
		}
		$topic = $this->topic_for($actor, $session['topic_id']);
		// Séance passée : seule la date reste modifiable (par un gestionnaire, sur le forum)
		if ($this->rules->is_past($session))
		{
			throw new action_exception('BM2_ERROR_PAST');
		}
		return array($session, $topic);
	}

	// Actions des joueurs

	/**
	* Inscription en sans table ($table_id = 0) ou sur une table, ou confirmation d'une pré-inscription
	*
	* @return string url du message auto
	* @throws action_exception
	*/
	public function register(actor $actor, $session_id, $table_id)
	{
		list($session, $topic) = $this->session_for($actor, $session_id);
		$table_id = (int) $table_id;
		$user_id = $actor->user_id;

		$message = $this->transaction(function () use ($actor, $session, $table_id, $user_id) {
			$state = $this->rules->load($session);
			if ($table_id && !isset($state['tables'][$table_id]))
			{
				throw new action_exception('BM2_ERROR_NOT_FOUND');
			}

			if (!$table_id)
			{
				if ($this->rules->is_engaged($state, $user_id))
				{
					throw new action_exception('BM2_ERROR_ENGAGED');
				}
				if ($this->rules->find_user($state['players'][0], $user_id))
				{
					throw new action_exception('BM2_ERROR_ALREADY_NO_TABLE');
				}
				$message = $this->language->lang('BM2_AUTO_REGISTER_NO_TABLE');
			}
			else
			{
				$table = $state['tables'][$table_id];
				$own = $this->rules->find_user($state['players'][$table_id], $user_id);
				$confirm = $own && $own['temporary'];
				if ($own && !$confirm)
				{
					throw new action_exception('BM2_ERROR_ENGAGED');
				}

				// Une pré-inscription ne compte pas comme engagement pour la confirmer
				if ($confirm ? $this->rules->is_engaged_except($state, $user_id, $own['player_id']) : $this->rules->is_engaged($state, $user_id))
				{
					throw new action_exception('BM2_ERROR_ENGAGED');
				}
				if (!$confirm)
				{
					if (!$actor->manager && !$this->rules->can_register_on_tables($state, $user_id))
					{
						throw new action_exception('BM2_ERROR_LIMIT_DAYS');
					}
					if ($this->rules->count_players($state['players'][$table_id]) >= $table['max_players'])
					{
						throw new action_exception('BM2_ERROR_FULL');
					}
				}
				$message = $this->language->lang('BM2_AUTO_REGISTER_TABLE', $table['name'], $table['mj_username']);
			}

			$this->rules->register_user($state, $table_id, $user_id, $actor->username, $user_id);
			return $message;
		});

		return $this->poster->reply($topic['topic_id'], $message, $actor);
	}

	/**
	* Désinscription (sans table ou table)
	*
	* @return string url du message auto
	* @throws action_exception
	*/
	public function unregister(actor $actor, $session_id, $table_id)
	{
		list($session, $topic) = $this->session_for($actor, $session_id);
		$table_id = (int) $table_id;

		$message = $this->transaction(function () use ($actor, $session, $table_id) {
			$state = $this->rules->load($session);
			if (!isset($state['players'][$table_id]))
			{
				throw new action_exception('BM2_ERROR_NOT_FOUND');
			}
			$player = $this->rules->find_user($state['players'][$table_id], $actor->user_id);
			if (!$player)
			{
				throw new action_exception('BM2_ERROR_NOT_REGISTERED');
			}
			$this->store->remove_player($player['player_id']);

			return $table_id
				? $this->language->lang('BM2_AUTO_UNREGISTER_TABLE', $state['tables'][$table_id]['name'], $state['tables'][$table_id]['mj_username'])
				: $this->language->lang('BM2_AUTO_UNREGISTER_NO_TABLE');
		});

		return $this->poster->reply($topic['topic_id'], $message, $actor);
	}

	/**
	* Exécute $work dans une transaction, annulée si l'action est refusée
	*/
	protected function transaction(callable $work)
	{
		$this->store->begin();
		try
		{
			$result = $work();
		}
		catch (action_exception $e)
		{
			$this->store->rollback();
			throw $e;
		}
		$this->store->commit();
		return $result;
	}
}
