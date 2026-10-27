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
* Erreur d'un appel à Discord ; le code est le statut HTTP (0 si Discord est injoignable)
*/
class discord_exception extends \RuntimeException
{
}
