<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace nathannau\boardmanager2\migrations;

class v2_0_0 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'bm2_sessions');
	}

	static public function depends_on()
	{
		return array('\phpbb\db\migration\data\v330\v330');
	}

	public function update_schema()
	{
		return array(
			'add_tables' => array(
				$this->table_prefix . 'bm2_sessions' => array(
					'COLUMNS' => array(
						'session_id'		=> array('UINT', null, 'auto_increment'),
						'topic_id'			=> array('UINT', 0),
						'session_date'		=> array('TIMESTAMP', 0),
						'created_by'		=> array('UINT', 0),
						'created_time'		=> array('TIMESTAMP', 0),
						'source'			=> array('VCHAR:4', 'v2'),
					),
					'PRIMARY_KEY' => 'session_id',
					'KEYS' => array(
						'topic_id'	=> array('UNIQUE', 'topic_id'),
					),
				),
				$this->table_prefix . 'bm2_tables' => array(
					'COLUMNS' => array(
						'table_id'			=> array('UINT', null, 'auto_increment'),
						'session_id'		=> array('UINT', 0),
						'no_table'			=> array('BOOL', 0),
						'name'				=> array('VCHAR_UNI', ''),
						'type'				=> array('VCHAR:4', ''),
						'url'				=> array('TEXT_UNI', ''),
						'start_time'		=> array('VCHAR_UNI', ''),
						'min_players'		=> array('USINT', 0),
						'max_players'		=> array('USINT', 0),
						'mj_user_id'		=> array('UINT', 0),
						'mj_username'		=> array('VCHAR_UNI', ''),
						'trigger_warning'	=> array('TEXT_UNI', ''),
						'description'		=> array('TEXT_UNI', ''),
						'cancelled'			=> array('BOOL', 0),
						'cancelled_time'	=> array('TIMESTAMP', 0),
						'created_time'		=> array('TIMESTAMP', 0),
					),
					'PRIMARY_KEY' => 'table_id',
					'KEYS' => array(
						'session_id'	=> array('INDEX', 'session_id'),
					),
				),
				$this->table_prefix . 'bm2_players' => array(
					'COLUMNS' => array(
						'player_id'			=> array('UINT', null, 'auto_increment'),
						'table_id'			=> array('UINT', 0),
						'user_id'			=> array('UINT', 0),
						'username'			=> array('VCHAR_UNI', ''),
						'added_by'			=> array('UINT', 0),
						'register_date'		=> array('TIMESTAMP', 0),
						'removed'			=> array('BOOL', 0),
						'temporary'			=> array('BOOL', 0),
					),
					'PRIMARY_KEY' => 'player_id',
					'KEYS' => array(
						'table_id'		=> array('INDEX', 'table_id'),
						'user_id'		=> array('INDEX', 'user_id'),
					),
				),
			),
		);
	}

	public function revert_schema()
	{
		return array(
			'drop_tables' => array(
				$this->table_prefix . 'bm2_sessions',
				$this->table_prefix . 'bm2_tables',
				$this->table_prefix . 'bm2_players',
			),
		);
	}

	public function update_data()
	{
		return array(
			array('config_text.add', array('bm2_manager_groups', '[]')),
			array('custom', array(array($this, 'set_default_manager_groups'))),

			array('module.add', array(
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_BOARDMANAGER2_TITLE',
			)),
			array('module.add', array(
				'acp',
				'ACP_BOARDMANAGER2_TITLE',
				array(
					'module_basename'	=> '\nathannau\boardmanager2\acp\main_module',
					'modes'				=> array('settings'),
				),
			)),
		);
	}

	/**
	* Gestionnaires par défaut : administrateurs et modérateurs globaux
	*/
	public function set_default_manager_groups()
	{
		$sql = 'SELECT group_id
			FROM ' . GROUPS_TABLE . '
			WHERE ' . $this->db->sql_in_set('group_name', array('ADMINISTRATORS', 'GLOBAL_MODERATORS')) . '
				AND group_type = ' . GROUP_SPECIAL;
		$result = $this->db->sql_query($sql);
		$group_ids = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$group_ids[] = (int) $row['group_id'];
		}
		$this->db->sql_freeresult($result);

		$config_text = new \phpbb\config\db_text($this->db, $this->table_prefix . 'config_text');
		$config_text->set('bm2_manager_groups', json_encode($group_ids));
	}
}
