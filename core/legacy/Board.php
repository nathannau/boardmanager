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
* Table v1 (une section du bloc [boardmanager]). Utilisée par l'import uniquement.
*/
class Board
{
	const NAME = 'table';
	const TYPE = 'type';
	const URL = 'url';
	const TIME = 'heure';
	const MIN = 'min';
	const MAX = 'max';
	const MJ = 'mj';
	const PLAYER = 'joueur';
	const TRIGGER_WARNING = 'tg';
	const DESCRIPTION = 'description';

	var $isWithoutTable;
	var $name;
	var $type;
	var $url;
	var $time;
	var $minPlayer;
	var $maxPlayer;
	var $mj;
	var $players;
	var $trigger_warning;
	var $description;

	public function __construct($from = '')
	{
		$this->isWithoutTable = true;
		$this->players = array();

		$from = str_replace("\r", "", $from);
		$lines = explode("\n", $from);
		foreach ($lines as $line)
		{
			$detail = explode(':', $line, 2);
			if (count($detail) < 2)
			{
				continue;
			}
			switch ($detail[0])
			{
				case self::NAME:
					$this->name = $detail[1];
					$this->isWithoutTable = $this->name == "";
					break;
				case self::TYPE:
					$this->type = $detail[1];
					break;
				case self::URL:
					$this->url = $detail[1];
					if ($this->url != '' && preg_match('/<a href="([^"]*)"/i', $this->url, $match))
					{
						$this->url = $match[1];
					}
					break;
				case self::TIME:
					$this->time = $detail[1];
					break;
				case self::MIN:
					$this->minPlayer = $detail[1];
					break;
				case self::MAX:
					$this->maxPlayer = $detail[1];
					break;
				case self::MJ:
					$this->mj = $detail[1];
					break;
				case self::TRIGGER_WARNING:
					$this->trigger_warning = $detail[1];
					break;
				case self::DESCRIPTION:
					$this->description = $detail[1];
					break;
				case self::PLAYER:
					$this->players[] = new Player($detail[1]);
					break;
			}
		}
	}
}
