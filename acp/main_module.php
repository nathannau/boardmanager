<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Nathan Nau
 * @license MIT (voir le fichier LICENSE)
 *
 */

namespace nathannau\boardmanager2\acp;

/**
* Pages ACP : groupes gestionnaires (settings) et séances orphelines (orphans)
*/
class main_module
{
	public $page_title;
	public $tpl_name;
	public $u_action;

	/** @var \Symfony\Component\DependencyInjection\ContainerInterface */
	protected $container;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	public function main($id, $mode)
	{
		global $phpbb_container;

		$this->container = $phpbb_container;
		$this->language = $phpbb_container->get('language');
		$this->request = $phpbb_container->get('request');
		$this->template = $phpbb_container->get('template');

		if ($mode === 'orphans')
		{
			$this->orphans();
		}
		else if ($mode === 'discord')
		{
			$this->discord();
		}
		else
		{
			$this->settings();
		}
	}

	/**
	* Choix des groupes gestionnaires des séances
	*/
	protected function settings()
	{
		/** @var \phpbb\config\db_text $config_text */
		$config_text = $this->container->get('config_text');
		/** @var \phpbb\db\driver\driver_interface $db */
		$db = $this->container->get('dbal.conn');
		/** @var \nathannau\boardmanager2\core\rules $rules */
		$rules = $this->container->get('nathannau.boardmanager2.rules');

		$this->tpl_name = 'acp_boardmanager2';
		$this->page_title = 'ACP_BOARDMANAGER2_SETTINGS';
		$form_key = 'nathannau_boardmanager2';
		add_form_key($form_key);

		$groups = array();
		$result = $db->sql_query('SELECT group_id, group_name, group_type
			FROM ' . GROUPS_TABLE . '
			ORDER BY group_type DESC, group_name');
		while ($row = $db->sql_fetchrow($result))
		{
			$groups[(int) $row['group_id']] = ($row['group_type'] == GROUP_SPECIAL) ? $this->language->lang('G_' . $row['group_name']) : $row['group_name'];
		}
		$db->sql_freeresult($result);

		if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}
			$selected = array_values(array_intersect(array_map('intval', $this->request->variable('bm2_manager_groups', array(0))), array_keys($groups)));
			$config_text->set('bm2_manager_groups', json_encode($selected));
			trigger_error($this->language->lang('ACP_BOARDMANAGER2_SAVED') . adm_back_link($this->u_action));
		}

		$selected = $rules->get_manager_groups();
		foreach ($groups as $group_id => $name)
		{
			$this->template->assign_block_vars('bm2_groups', array(
				'ID'		=> $group_id,
				'NAME'		=> $name,
				'SELECTED'	=> in_array($group_id, $selected, true),
			));
		}
		$this->template->assign_vars(array(
			'BM2_GROUPS_SIZE'	=> min(15, max(5, count($groups))),
			'U_ACTION'			=> $this->u_action,
		));
	}

	/**
	* Séances dont le sujet a été supprimé : réaffectation à un autre sujet ou suppression
	*/
	protected function orphans()
	{
		/** @var \nathannau\boardmanager2\core\store $store */
		$store = $this->container->get('nathannau.boardmanager2.store');
		/** @var \nathannau\boardmanager2\core\renderer $renderer */
		$renderer = $this->container->get('nathannau.boardmanager2.renderer');
		/** @var \phpbb\log\log_interface $log */
		$log = $this->container->get('log');
		/** @var \phpbb\user $user */
		$user = $this->container->get('user');

		$this->tpl_name = 'acp_boardmanager2_orphans';
		$this->page_title = 'ACP_BOARDMANAGER2_ORPHANS';
		$form_key = 'nathannau_boardmanager2_orphans';
		add_form_key($form_key);

		$action = $this->request->variable('action', '');
		$session_id = $this->request->variable('session_id', 0);
		$session = $session_id ? $store->get_session($session_id) : null;
		if ($action && (!$session || !$store->is_orphan($session)))
		{
			trigger_error($this->language->lang('ACP_BOARDMANAGER2_ORPHAN_NOT_FOUND') . adm_back_link($this->u_action), E_USER_WARNING);
		}
		$label = $session ? $renderer->format_session_date($session['session_date']) : '';

		if ($action === 'delete')
		{
			if (confirm_box(true))
			{
				$store->begin();
				$store->delete_session($session['session_id']);
				$store->commit();
				$this->container->get('nathannau.boardmanager2.discord_sync')->forget($session['session_id']);
				$log->add('admin', $user->data['user_id'], $user->ip, 'LOG_BM2_ORPHAN_DELETED', false, array($label, $session['topic_id']));
				trigger_error($this->language->lang('ACP_BOARDMANAGER2_ORPHAN_DELETED') . adm_back_link($this->u_action));
			}
			confirm_box(false, $this->language->lang('ACP_BOARDMANAGER2_ORPHAN_DELETE_CONFIRM', $label), build_hidden_fields(array(
				'action'		=> 'delete',
				'session_id'	=> (int) $session['session_id'],
			)));
			redirect($this->u_action);
		}

		if ($action === 'move')
		{
			if (!check_form_key($form_key))
			{
				trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}
			$topic_id = $this->target_topic($this->request->variable('target_' . (int) $session['session_id'], ''));
			$error = $this->check_target($topic_id, $store, $renderer);
			if ($error)
			{
				trigger_error($error . adm_back_link($this->u_action), E_USER_WARNING);
			}
			$store->move_session($session['session_id'], $topic_id);
			$this->container->get('nathannau.boardmanager2.discord_sync')->sync_session($session['session_id']);
			$log->add('admin', $user->data['user_id'], $user->ip, 'LOG_BM2_ORPHAN_MOVED', false, array($label, $session['topic_id'], $topic_id));
			trigger_error($this->language->lang('ACP_BOARDMANAGER2_ORPHAN_MOVED', $topic_id) . adm_back_link($this->u_action));
		}

		foreach ($store->get_orphan_sessions() as $row)
		{
			$this->template->assign_block_vars('bm2_orphans', array(
				'ID'			=> (int) $row['session_id'],
				'DATE'			=> $renderer->format_session_date($row['session_date']),
				'TOPIC_ID'		=> (int) $row['topic_id'],
				'SOURCE'		=> $row['source'],
				'TABLES'		=> (int) $row['nb_tables'],
				'PLAYERS'		=> (int) $row['nb_players'],
				'U_DELETE'		=> $this->u_action . '&amp;action=delete&amp;session_id=' . (int) $row['session_id'],
			));
		}
		$this->template->assign_vars(array(
			'U_ACTION'	=> $this->u_action . '&amp;action=move',
		));
	}

	/**
	* Configuration de l'application Discord
	*/
	protected function discord()
	{
		/** @var \phpbb\config\config $config */
		$config = $this->container->get('config');
		/** @var \phpbb\config\db_text $config_text */
		$config_text = $this->container->get('config_text');
		/** @var \nathannau\boardmanager2\core\discord_api $api */
		$api = $this->container->get('nathannau.boardmanager2.discord_api');

		$this->tpl_name = 'acp_boardmanager2_discord';
		$this->page_title = 'ACP_BOARDMANAGER2_DISCORD';
		$form_key = 'nathannau_boardmanager2_discord';
		add_form_key($form_key);
		$errors = array();
		$check = '';

		$values = array(
			'bm2_discord_enabled'		=> (int) $config['bm2_discord_enabled'],
			'bm2_discord_app_id'		=> $config['bm2_discord_app_id'],
			'bm2_discord_public_key'	=> $config['bm2_discord_public_key'],
			'bm2_discord_guild_id'		=> $config['bm2_discord_guild_id'],
			'bm2_discord_channel_id'	=> $config['bm2_discord_channel_id'],
		);

		if ($this->request->is_set_post('submit') || $this->request->is_set_post('check'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}
		}

		if ($this->request->is_set_post('submit'))
		{
			$values = array(
				'bm2_discord_enabled'		=> $this->request->variable('bm2_discord_enabled', 0) ? 1 : 0,
				'bm2_discord_app_id'		=> trim($this->request->variable('bm2_discord_app_id', '')),
				'bm2_discord_public_key'	=> strtolower(trim($this->request->variable('bm2_discord_public_key', ''))),
				'bm2_discord_guild_id'		=> trim($this->request->variable('bm2_discord_guild_id', '')),
				'bm2_discord_channel_id'	=> trim($this->request->variable('bm2_discord_channel_id', '')),
			);
			foreach (array('bm2_discord_app_id', 'bm2_discord_guild_id', 'bm2_discord_channel_id') as $key)
			{
				if ($values[$key] !== '' && !preg_match('/^\d{15,25}$/', $values[$key]))
				{
					$errors[] = $this->language->lang('ACP_BM2_DISCORD_ERROR_ID', $this->language->lang(strtoupper('ACP_' . substr($key, 4))));
				}
			}
			if ($values['bm2_discord_public_key'] !== '' && !preg_match('/^[0-9a-f]{64}$/', $values['bm2_discord_public_key']))
			{
				$errors[] = $this->language->lang('ACP_BM2_DISCORD_ERROR_PUBLIC_KEY');
			}
			$token = trim($this->request->variable('bm2_discord_bot_token', ''));

			if (!$errors)
			{
				foreach ($values as $key => $value)
				{
					$config->set($key, $value);
				}
				// Jeton laissé vide : on garde le jeton enregistré
				if ($token !== '')
				{
					$config_text->set('bm2_discord_bot_token', $token);
				}
				trigger_error($this->language->lang('ACP_BM2_DISCORD_SAVED') . adm_back_link($this->u_action));
			}
		}

		if ($this->request->is_set_post('check'))
		{
			try
			{
				$bot = $api->bot_user();
				$check = $this->language->lang('ACP_BM2_DISCORD_CHECK_OK', $bot['username']);
			}
			catch (\RuntimeException $e)
			{
				$errors[] = $this->language->lang('ACP_BM2_DISCORD_CHECK_KO', $e->getMessage());
			}
		}

		$this->template->assign_vars(array_merge(array_change_key_case($values, CASE_UPPER), array(
			'BM2_ERRORS'			=> $errors,
			'BM2_CHECK'				=> $check,
			'BM2_TOKEN_SET'			=> trim((string) $config_text->get('bm2_discord_bot_token')) !== '',
			'BM2_ENDPOINT_URL'		=> generate_board_url() . '/app.php/boardmanager2/discord',
			'BM2_INVITE_URL'		=> $config['bm2_discord_app_id'] !== '' ? $api->invite_url() : '',
			'U_ACTION'				=> $this->u_action,
		)));
	}

	/**
	* Sujet désigné par un lien (t= ou p=) ou par son numéro
	*
	* @return int topic_id, 0 si introuvable
	*/
	protected function target_topic($target)
	{
		$target = html_entity_decode(trim($target), ENT_QUOTES, 'UTF-8');
		if (preg_match('/^\d+$/', $target))
		{
			return (int) $target;
		}
		if (preg_match('/[?&]t=(\d+)/', $target, $match))
		{
			return (int) $match[1];
		}
		if (preg_match('/[?&]p=(\d+)/', $target, $match) || preg_match('/#p(\d+)$/', $target, $match))
		{
			/** @var \phpbb\db\driver\driver_interface $db */
			$db = $this->container->get('dbal.conn');
			$result = $db->sql_query('SELECT topic_id FROM ' . POSTS_TABLE . ' WHERE post_id = ' . (int) $match[1]);
			$topic_id = (int) $db->sql_fetchfield('topic_id');
			$db->sql_freeresult($result);
			return $topic_id;
		}
		return 0;
	}

	/**
	* @return string message d'erreur, vide si le sujet peut recevoir la séance
	*/
	protected function check_target($topic_id, $store, $renderer)
	{
		if (!$topic_id || !$store->topic_exists($topic_id))
		{
			return $this->language->lang('ACP_BOARDMANAGER2_TARGET_NOT_FOUND');
		}
		if ($store->get_session_by_topic($topic_id))
		{
			return $this->language->lang('ACP_BOARDMANAGER2_TARGET_HAS_SESSION', $topic_id);
		}
		if (!$renderer->marker_post($topic_id))
		{
			return $this->language->lang('ACP_BOARDMANAGER2_TARGET_NO_MARKER', $topic_id);
		}
		return '';
	}
}
