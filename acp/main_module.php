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
* Choix des groupes gestionnaires des séances
*/
class main_module
{
	public $page_title;
	public $tpl_name;
	public $u_action;

	public function main($id, $mode)
	{
		global $phpbb_container;

		/** @var \phpbb\config\db_text $config_text */
		$config_text = $phpbb_container->get('config_text');
		/** @var \phpbb\db\driver\driver_interface $db */
		$db = $phpbb_container->get('dbal.conn');
		/** @var \phpbb\language\language $language */
		$language = $phpbb_container->get('language');
		/** @var \phpbb\request\request $request */
		$request = $phpbb_container->get('request');
		/** @var \phpbb\template\template $template */
		$template = $phpbb_container->get('template');
		/** @var \nathannau\boardmanager2\core\rules $rules */
		$rules = $phpbb_container->get('nathannau.boardmanager2.rules');

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
			$groups[(int) $row['group_id']] = ($row['group_type'] == GROUP_SPECIAL) ? $language->lang('G_' . $row['group_name']) : $row['group_name'];
		}
		$db->sql_freeresult($result);

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}
			$selected = array_values(array_intersect(array_map('intval', $request->variable('bm2_manager_groups', array(0))), array_keys($groups)));
			$config_text->set('bm2_manager_groups', json_encode($selected));
			trigger_error($language->lang('ACP_BOARDMANAGER2_SAVED') . adm_back_link($this->u_action));
		}

		$selected = $rules->get_manager_groups();
		foreach ($groups as $group_id => $name)
		{
			$template->assign_block_vars('bm2_groups', array(
				'ID'		=> $group_id,
				'NAME'		=> $name,
				'SELECTED'	=> in_array($group_id, $selected, true),
			));
		}
		$template->assign_vars(array(
			'BM2_GROUPS_SIZE'	=> min(15, max(5, count($groups))),
			'U_ACTION'			=> $this->u_action,
		));
	}
}
