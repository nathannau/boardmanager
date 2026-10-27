<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Nathan Nau
 * @license MIT (voir le fichier LICENSE)
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

	'ACP_BOARDMANAGER2_ORPHANS'			=> 'Séances orphelines',
	'ACP_BOARDMANAGER2_ORPHANS_EXPLAIN'	=> 'Séances dont le sujet a été supprimé. Elles ne sont plus affichées nulle part : vous pouvez les rattacher à un autre sujet, qui doit contenir [boardmanager2][/boardmanager2] et ne pas avoir déjà de séance, ou les supprimer définitivement.',
	'ACP_BOARDMANAGER2_NO_ORPHANS'		=> 'Aucune séance orpheline.',
	'ACP_BOARDMANAGER2_ORPHAN_DATE'		=> 'Séance du',
	'ACP_BOARDMANAGER2_ORPHAN_TOPIC'	=> 'Ancien sujet',
	'ACP_BOARDMANAGER2_ORPHAN_SOURCE'	=> 'Origine',
	'ACP_BOARDMANAGER2_ORPHAN_TABLES'	=> 'Tables',
	'ACP_BOARDMANAGER2_ORPHAN_PLAYERS'	=> 'Joueurs',
	'ACP_BOARDMANAGER2_ORPHAN_TARGET'	=> 'Sujet de destination',
	'ACP_BOARDMANAGER2_ORPHAN_TARGET_PLACEHOLDER'	=> 'lien du sujet ou d’un message, ou n° du sujet',
	'ACP_BOARDMANAGER2_ORPHAN_MOVE'		=> 'Réaffecter',
	'ACP_BOARDMANAGER2_ORPHAN_DELETE'	=> 'Supprimer',
	'ACP_BOARDMANAGER2_ORPHAN_DELETE_CONFIRM'	=> 'Supprimer définitivement la séance du %s, ses tables et ses joueurs ?',
	'ACP_BOARDMANAGER2_ORPHAN_DELETED'	=> 'La séance a été supprimée.',
	'ACP_BOARDMANAGER2_ORPHAN_MOVED'	=> 'La séance a été rattachée au sujet %d.',
	'ACP_BOARDMANAGER2_ORPHAN_NOT_FOUND'	=> 'Cette séance n’existe pas ou n’est pas orpheline.',
	'ACP_BOARDMANAGER2_TARGET_NOT_FOUND'	=> 'Le sujet de destination est introuvable.',
	'ACP_BOARDMANAGER2_TARGET_HAS_SESSION'	=> 'Le sujet %d a déjà une séance.',
	'ACP_BOARDMANAGER2_TARGET_NO_MARKER'	=> 'Le sujet %d ne contient pas [boardmanager2][/boardmanager2] : ajoutez-le d’abord dans un de ses messages.',

	'LOG_BM2_ORPHAN_DELETED'			=> '<strong>Board Manager : séance orpheline supprimée</strong><br />» séance du %1$s (ancien sujet %2$d)',
	'LOG_BM2_ORPHAN_MOVED'				=> '<strong>Board Manager : séance orpheline réaffectée</strong><br />» séance du %1$s, du sujet %2$d vers le sujet %3$d',
));
