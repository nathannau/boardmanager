<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace nathannau\boardmanager2\acp;

class main_info
{
	public function module()
	{
		return array(
			'filename'	=> '\nathannau\boardmanager2\acp\main_module',
			'title'		=> 'ACP_BOARDMANAGER2_TITLE',
			'modes'		=> array(
				'settings'	=> array(
					'title'	=> 'ACP_BOARDMANAGER2_SETTINGS',
					'auth'	=> 'ext_nathannau/boardmanager2 && acl_a_board',
					'cat'	=> array('ACP_BOARDMANAGER2_TITLE'),
				),
			),
		);
	}
}
