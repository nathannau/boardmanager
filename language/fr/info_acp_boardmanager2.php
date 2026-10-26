<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	'ACP_BOARDMANAGER2_TITLE'			=> 'Board Manager',
	'ACP_BOARDMANAGER2_SETTINGS'		=> 'Gestionnaires des séances',
	'ACP_BOARDMANAGER2_EXPLAIN'			=> 'Les membres des groupes choisis (ainsi que les fondateurs du forum) peuvent gérer toutes les séances : configurer la date, ouvrir une table pour un MJ, annuler une table, ajouter ou retirer n’importe quel joueur, sans la règle des 3 jours.',
	'ACP_BOARDMANAGER2_GROUPS'			=> 'Groupes gestionnaires',
	'ACP_BOARDMANAGER2_GROUPS_EXPLAIN'	=> 'Maintenez Ctrl (ou Cmd) pour sélectionner plusieurs groupes.',
	'ACP_BOARDMANAGER2_SAVED'			=> 'Les groupes gestionnaires ont été enregistrés.',
));
