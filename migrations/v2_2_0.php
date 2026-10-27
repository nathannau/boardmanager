<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Nathan Nau
 * @license MIT (voir le fichier LICENSE)
 *
 */

namespace nathannau\boardmanager2\migrations;

/**
* Intégration Discord : liaison des comptes, messages des séances, configuration, pages ACP et UCP
*/
class v2_2_0 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'bm2_discord_links');
	}

	static public function depends_on()
	{
		return array('\nathannau\boardmanager2\migrations\v2_0_1');
	}

	public function update_schema()
	{
		return array(
			'add_tables' => array(
				// Compte Discord lié à chaque membre (un pour un)
				$this->table_prefix . 'bm2_discord_links' => array(
					'COLUMNS' => array(
						'user_id'			=> array('UINT', 0),
						'discord_id'		=> array('VCHAR:32', ''),
						'discord_username'	=> array('VCHAR_UNI', ''),
						'linked_time'		=> array('TIMESTAMP', 0),
					),
					'PRIMARY_KEY' => 'user_id',
					'KEYS' => array(
						'discord_id'	=> array('UNIQUE', 'discord_id'),
					),
				),
				// Code de liaison en attente (un par membre, haché, à usage unique)
				$this->table_prefix . 'bm2_discord_codes' => array(
					'COLUMNS' => array(
						'user_id'			=> array('UINT', 0),
						'code_hash'			=> array('VCHAR:64', ''),
						'expires_time'		=> array('TIMESTAMP', 0),
					),
					'PRIMARY_KEY' => 'user_id',
					'KEYS' => array(
						'code_hash'		=> array('UNIQUE', 'code_hash'),
					),
				),
				// Publication Discord de chaque séance
				$this->table_prefix . 'bm2_discord_messages' => array(
					'COLUMNS' => array(
						'session_id'		=> array('UINT', 0),
						'thread_id'			=> array('VCHAR:32', ''),
						'state_hash'		=> array('VCHAR:40', ''),
						'updated_time'		=> array('TIMESTAMP', 0),
					),
					'PRIMARY_KEY' => 'session_id',
				),
			),
		);
	}

	public function revert_schema()
	{
		return array(
			'drop_tables' => array(
				$this->table_prefix . 'bm2_discord_links',
				$this->table_prefix . 'bm2_discord_codes',
				$this->table_prefix . 'bm2_discord_messages',
			),
		);
	}

	public function update_data()
	{
		return array(
			array('config.add', array('bm2_discord_enabled', 0)),
			array('config.add', array('bm2_discord_app_id', '')),
			array('config.add', array('bm2_discord_public_key', '')),
			array('config.add', array('bm2_discord_guild_id', '')),
			array('config.add', array('bm2_discord_channel_id', '')),
			array('config.add', array('bm2_discord_sync_last', 0, true)),
			// Secret : hors de la table config (mise en cache et chargée à chaque page)
			array('config_text.add', array('bm2_discord_bot_token', '')),

			array('module.add', array(
				'acp',
				'ACP_BOARDMANAGER2_TITLE',
				array(
					'module_basename'	=> '\nathannau\boardmanager2\acp\main_module',
					'modes'				=> array('discord'),
				),
			)),
			array('module.add', array(
				'ucp',
				'UCP_PROFILE',
				array(
					'module_basename'	=> '\nathannau\boardmanager2\ucp\main_module',
					'modes'				=> array('discord'),
				),
			)),
		);
	}
}
