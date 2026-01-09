<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace nathannau\boardmanager\core;

/**
* 
*/
class Player
{
    var $name;
    var $date;
    var $removed;
    var $temporary;
    
    public function __construct($from='')
    {
		$info = explode(';', html_entity_decode($from, ENT_COMPAT|ENT_QUOTES));
		if (count($info)==1)
		{
			$this->date = date('dmY');
			$this->removed = substr($from, 0, 2)=='- ';
			$this->temporary = substr($from, strlen($from)-2)==' *';
			$this->name = substr(
				$from, 
				$this->removed?2:0, 
				strlen($from)-($this->removed?2:0)-($this->temporary?2:0));
		}
		else
		{
			$this->name = $info[0];
			$this->date = $info[1];
			$this->removed = strpos($info[2],'-')!==false;
			$this->temporary = strpos($info[2],'*')!==false;
		}
    }

	public function Hash()
	{
		return md5($this->ToBBCode());
	}

    public function ToBBCode()
    {
		return $this->name.';'.$this->date.';'.($this->removed?'-':'').($this->temporary?'*':'');
    }

    public function DayTo($toDate)
    {
		$d1 = mktime(0, 0, 0, (int)substr($this->date, 2, 2), (int)substr($this->date, 0, 2), (int)substr($this->date, 4, 4));
		$d2 = mktime(0, 0, 0, (int)substr($toDate, 2, 2), (int)substr($toDate, 0, 2), (int)substr($toDate, 4, 4));
		return ($d2-$d1)/86400;
    }
	
	
}
