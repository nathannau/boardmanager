<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Nathan Nau
 * @license MIT (voir le fichier LICENSE)
 *
 */

namespace nathannau\boardmanager2\core\legacy;

/**
* Joueur v1 : « nom;JJMMAAAA;flags » (flags : « - » retiré, « * » pré-inscrit)
* ou, à l'ouverture d'une table, « - nom * ». Utilisé par l'import uniquement.
*/
class Player
{
	var $name;
	var $date;
	var $removed;
	var $temporary;

	public function __construct($from = '')
	{
		$info = explode(';', $from);
		if (count($info) == 1)
		{
			$this->date = date('dmY');
			$this->removed = substr($from, 0, 2) == '- ';
			$this->temporary = substr($from, strlen($from) - 2) == ' *';
			$this->name = substr(
				$from,
				$this->removed ? 2 : 0,
				strlen($from) - ($this->removed ? 2 : 0) - ($this->temporary ? 2 : 0));
		}
		else
		{
			$this->name = $info[0];
			$this->date = $info[1];
			$this->removed = strpos(isset($info[2]) ? $info[2] : '', '-') !== false;
			$this->temporary = strpos(isset($info[2]) ? $info[2] : '', '*') !== false;
		}
	}
}
