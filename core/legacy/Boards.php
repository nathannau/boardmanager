<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace nathannau\boardmanager2\core\legacy;

/**
* Contenu d'un bloc [boardmanager JJMMAAAA]…[/boardmanager] v1, sections séparées
* par « ---------- ». Utilisé par l'import uniquement.
*/
class Boards
{
	const SEPARATOR = "----------";

	/** @var Board[] */
	public $boards;

	/** @var string JJMMAAAA */
	public $date;

	public function __construct($from, $date)
	{
		$this->boards = array();
		$this->date = $date;

		$from = preg_replace('|<br\s*/?>|i', '', $from);
		foreach (explode(self::SEPARATOR, $from) as $board_data)
		{
			$this->boards[] = new Board($board_data);
		}
	}
}
