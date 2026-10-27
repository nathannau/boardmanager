<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Nathan Nau
 * @license MIT (voir le fichier LICENSE)
 *
 */

namespace nathannau\boardmanager2;

/**
* Extension class
*
* Le BBCode [boardmanager2][/boardmanager2] est déclaré par l'événement
* core.text_formatter_s9e_configure_after (voir event/listener.php) :
* aucune ligne n'est écrite dans la table des BBCodes. La purge du cache
* faite par phpBB à l'activation regénère la configuration du parseur.
*/
class ext extends \phpbb\extension\base
{
	public function is_enableable()
	{
		$config = $this->container->get('config');
		return phpbb_version_compare($config['version'], '3.3.0', '>=');
	}
}
