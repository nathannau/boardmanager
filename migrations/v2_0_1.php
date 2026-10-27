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
* Page ACP des séances orphelines
*/
class v2_0_1 extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return array('\nathannau\boardmanager2\migrations\v2_0_0');
	}

	public function update_data()
	{
		return array(
			array('module.add', array(
				'acp',
				'ACP_BOARDMANAGER2_TITLE',
				array(
					'module_basename'	=> '\nathannau\boardmanager2\acp\main_module',
					'modes'				=> array('orphans'),
				),
			)),
		);
	}
}
