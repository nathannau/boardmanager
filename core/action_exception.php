<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Nathan Nau
 * @license MIT (voir le fichier LICENSE)
 *
 */

namespace nathannau\boardmanager2\core;


/**
* Action refusée : clé de langue (et paramètres) du message à afficher
*/
class action_exception extends \Exception
{
	/** @var string */
	public $key;

	/** @var array */
	public $args;

	public function __construct($key, ...$args)
	{
		parent::__construct($key);
		$this->key = $key;
		$this->args = $args;
	}
}
