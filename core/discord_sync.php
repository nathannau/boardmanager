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
* Publications Discord des séances, dans le salon Forum configuré :
* une publication par séance future, dont le premier message est le tableau.
*
* Discord n'est appelé que si le tableau a changé (empreinte state_hash).
* Un échec n'interrompt jamais l'action faite sur le forum : il est journalisé,
* et la tâche cron rattrape.
*/
class discord_sync
{
	/** Séances passées encore surveillées par la tâche cron (pour les clore) */
	const CLOSE_WINDOW = 1209600;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var discord_api */
	protected $api;

	/** @var discord_panel */
	protected $panel;

	/** @var store */
	protected $store;

	/** @var rules */
	protected $rules;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\log\log_interface */
	protected $log;

	protected $messages_table;
	protected $sessions_table;

	/** @var array|null étiquettes du salon (nom en minuscules => id), chargées une fois par requête */
	protected $tags;

	public function __construct(\phpbb\config\config $config, discord_api $api, discord_panel $panel, store $store, rules $rules, \phpbb\db\driver\driver_interface $db, \phpbb\log\log_interface $log, $messages_table, $sessions_table)
	{
		$this->config = $config;
		$this->api = $api;
		$this->panel = $panel;
		$this->store = $store;
		$this->rules = $rules;
		$this->db = $db;
		$this->log = $log;
		$this->messages_table = $messages_table;
		$this->sessions_table = $sessions_table;
	}

	public function enabled()
	{
		return $this->config['bm2_discord_enabled'] && $this->config['bm2_discord_channel_id'] !== '' && $this->api->is_configured();
	}

	/**
	* Met à jour (ou crée) la publication d'une séance ; sans effet si Discord est désactivé
	*
	* @return bool false en cas d'échec (journalisé)
	*/
	public function sync_session($session_id)
	{
		if (!$this->enabled())
		{
			return true;
		}
		$session = $this->store->get_session($session_id);
		if (!$session)
		{
			return true;
		}
		try
		{
			$this->sync($session);
			return true;
		}
		catch (discord_exception $e)
		{
			$this->log->add('critical', ANONYMOUS, '', 'LOG_BM2_DISCORD_SYNC_ERROR', false, array((int) $session_id, $e->getMessage()));
			return false;
		}
	}

	/**
	* Tâche cron : crée les publications manquantes des séances futures, met à jour
	* celles qui ont changé et clôt celles des séances passées
	*
	* @return int nombre de séances traitées sans erreur
	*/
	public function sync_due($limit = 20)
	{
		if (!$this->enabled())
		{
			return 0;
		}
		$sql = 'SELECT s.session_id
			FROM ' . $this->sessions_table . ' s
			LEFT JOIN ' . $this->messages_table . ' m ON m.session_id = s.session_id
			WHERE s.session_date >= ' . (int) ($this->rules->midnight(time()) - self::CLOSE_WINDOW) . '
				AND (m.session_id IS NOT NULL OR s.session_date >= ' . (int) $this->rules->midnight(time()) . ')
			ORDER BY s.session_date';
		$result = $this->db->sql_query_limit($sql, $limit);
		$ids = array_map('intval', array_column($this->db->sql_fetchrowset($result), 'session_id'));
		$this->db->sql_freeresult($result);

		$done = 0;
		foreach ($ids as $session_id)
		{
			$done += $this->sync_session($session_id) ? 1 : 0;
		}
		return $done;
	}

	/**
	* La séance n'existe plus (séance orpheline supprimée) : on oublie sa publication
	*/
	public function forget($session_id)
	{
		$this->db->sql_query('DELETE FROM ' . $this->messages_table . ' WHERE session_id = ' . (int) $session_id);
	}

	/**
	* @throws discord_exception
	*/
	protected function sync(array $session)
	{
		$past = $this->rules->is_past($session);
		$title = $this->panel->title($session);
		$message = $this->panel->message($session);
		$hash = $this->hash($session, $message);
		$row = $this->get_row($session['session_id']);

		if (!$row)
		{
			// Pas de publication pour une séance déjà passée (historique importé de la v1, par exemple)
			if (!$past)
			{
				$this->create($session, $title, $message, $hash);
			}
			return;
		}
		if ($row['state_hash'] === $hash)
		{
			return;
		}

		$thread_id = $row['thread_id'];
		try
		{
			// Une publication inactive est archivée par Discord, et une séance repoussée peut être verrouillée
			$this->api->edit_thread($thread_id, $past
				? array('archived' => false, 'locked' => false)
				: array('archived' => false, 'locked' => false, 'name' => $title) + $this->tag_change(false));
			$this->api->edit_message($thread_id, $thread_id, $message);
			if ($past)
			{
				$this->api->edit_thread($thread_id, array('name' => $title, 'locked' => true) + $this->tag_change(true));
			}
		}
		catch (discord_exception $e)
		{
			// Publication supprimée sur Discord : on la recrée si la séance est à venir
			if ($e->getCode() === 404)
			{
				$this->forget($session['session_id']);
				if (!$past)
				{
					$this->create($session, $title, $message, $hash);
				}
				return;
			}
			throw $e;
		}
		$this->save_row($session['session_id'], $thread_id, $hash);
	}

	protected function create(array $session, $title, array $message, $hash)
	{
		$tag = $this->tag_id(false);
		$thread = $this->api->create_forum_post($this->config['bm2_discord_channel_id'], $title, $message, $tag ? array($tag) : array());
		$this->save_row($session['session_id'], $thread['id'], $hash);
	}

	/**
	* @return array changement d'étiquette (« À venir » ou « Terminée »), vide si l'étiquette n'existe pas
	*/
	protected function tag_change($past)
	{
		$tag = $this->tag_id($past);
		return $tag ? array('applied_tags' => array($tag)) : array();
	}

	protected function tag_id($past)
	{
		if ($this->tags === null)
		{
			$this->tags = array();
			$channel = $this->api->get_channel($this->config['bm2_discord_channel_id']);
			foreach (isset($channel['available_tags']) ? $channel['available_tags'] : array() as $tag)
			{
				$this->tags[self::tag_key($tag['name'])] = $tag['id'];
			}
		}
		$name = self::tag_key(discord_panel::plain($this->panel->tag_name($past)));
		return isset($this->tags[$name]) ? $this->tags[$name] : null;
	}

	/**
	* Nom d'étiquette comparable : sans accents, espaces ni casse (« À venir » = « A venir »)
	*/
	protected static function tag_key($name)
	{
		$name = trim((string) $name);
		if (class_exists('Normalizer'))
		{
			$name = preg_replace('/\p{Mn}/u', '', \Normalizer::normalize($name, \Normalizer::FORM_D));
		}
		return preg_replace('/\s+/u', ' ', utf8_strtolower($name));
	}

	protected function hash(array $session, array $message)
	{
		return sha1(json_encode(array($this->panel->title($session), $this->rules->is_past($session), $message)));
	}

	protected function get_row($session_id)
	{
		$result = $this->db->sql_query('SELECT * FROM ' . $this->messages_table . ' WHERE session_id = ' . (int) $session_id);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		return $row ?: null;
	}

	protected function save_row($session_id, $thread_id, $hash)
	{
		$this->forget($session_id);
		$this->db->sql_query('INSERT INTO ' . $this->messages_table . ' ' . $this->db->sql_build_array('INSERT', array(
			'session_id'	=> (int) $session_id,
			'thread_id'		=> (string) $thread_id,
			'state_hash'	=> $hash,
			'updated_time'	=> time(),
		)));
	}
}
