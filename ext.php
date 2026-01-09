<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
* For full copyright and license information, please see
* the docs/CREDITS.txt file.
*
*/

namespace nathannau\boardmanager;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
* Extension class for board announcements
*/
class ext extends \phpbb\extension\base
{
	/**
	* Single enable step that installs any included migrations
	*
	* @param mixed $old_state State returned by previous call of this method
	* @return false Indicates no further steps are required
	*/
	public function enable_step($old_state)
	{
		// Load the acp_bbcode class
		if (!class_exists('acp_bbcodes'))
		{
			global $phpbb_root_path, $phpEx;
			include($phpbb_root_path . 'includes/acp/acp_bbcodes.'.$phpEx);
		}
		
		$container = $this->container;
		$db = $container->get('dbal.conn');

		$sql = 'DELETE FROM ' . BBCODES_TABLE . " WHERE LOWER(bbcode_tag) = 'boardmanager'";
		$db->sql_query($sql);

		$sql = 'SELECT MAX(bbcode_id) AS max_bbcode_id FROM ' . BBCODES_TABLE;
		$result = $db->sql_query($sql);
		$max_bbcode_id = $db->sql_fetchfield('max_bbcode_id');
		$db->sql_freeresult($result);

		if ($max_bbcode_id)
		{
			$bbcode_id = $max_bbcode_id + 1;
			// Make sure it is greater than the core BBCode ids...
			if ($bbcode_id <= NUM_CORE_BBCODES) $bbcode_id = NUM_CORE_BBCODES + 1;
		}
		else
			$bbcode_id = NUM_CORE_BBCODES + 1;

		if ($bbcode_id <= BBCODE_LIMIT)
		{
			$bbcode_tool = new \acp_bbcodes();

			// Build the BBCodes
			$data = array(
				'bbcode_helpline'		=> 'Board Manager',
				'bbcode_match'			=> '[boardmanager {NUMBER}]{TEXT}[/boardmanager]',
				'bbcode_tpl'			=> '{TEXT}',
				'display_on_posting'	=> false,
				'bbcode_id'				=> $bbcode_id,
			);
			
			$data += $bbcode_tool->build_regexp($data['bbcode_match'], $data['bbcode_tpl']);
			
			$sql = 'INSERT INTO ' . BBCODES_TABLE . ' ' . $db->sql_build_array('INSERT', $data);
			$db->sql_query($sql);
		}
		
		return parent::enable_step($old_state);
	}

	/**
	* Single disable step that does nothing
	*
	* @param mixed $old_state State returned by previous call of this method
	* @return false Indicates no further steps are required
	*/
	public function disable_step($old_state)
	{
		$container = $this->container;
		$db = $container->get('dbal.conn');

		$sql = 'DELETE FROM ' . BBCODES_TABLE . " WHERE LOWER(bbcode_tag) = 'boardmanager'";
		$db->sql_query($sql);

		return parent::disable_step($old_state);
	}

}
