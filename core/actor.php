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
* Membre au nom duquel une action est faite : l'utilisateur connecté sur le
* forum, ou un membre retrouvé par son identifiant (Discord).
*/
class actor
{
	/** @var int */
	public $user_id;

	/** @var string pseudo (forme stockée par phpBB) */
	public $username;

	/** @var array ligne de la table des utilisateurs */
	public $data;

	/** @var \phpbb\auth\auth permissions de ce membre */
	public $auth;

	/** @var bool gestionnaire des séances */
	public $manager;

	public function __construct(array $data, \phpbb\auth\auth $auth, $manager)
	{
		$this->user_id = (int) $data['user_id'];
		$this->username = $data['username'];
		$this->data = $data;
		$this->auth = $auth;
		$this->manager = (bool) $manager;
	}
}
