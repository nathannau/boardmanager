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

	'ACP_BOARDMANAGER2_DISCORD'			=> 'Discord',
	'ACP_BM2_DISCORD_EXPLAIN'			=> 'Bot Discord de la Guilde : les membres lient leur compte Discord depuis leur profil (code à usage unique), puis s’inscrivent aux tables depuis Discord. L’administration des séances reste sur le forum.',
	'ACP_BM2_DISCORD_APPLICATION'		=> 'Application Discord',
	'ACP_BM2_DISCORD_ENABLED'			=> 'Intégration activée',
	'ACP_BM2_DISCORD_ENABLED_EXPLAIN'	=> 'Désactivée, le bot répond qu’il n’est pas disponible et la page « Discord » du profil est masquée.',
	'ACP_DISCORD_APP_ID'				=> 'Identifiant de l’application',
	'ACP_BM2_DISCORD_APP_ID_EXPLAIN'	=> 'Portail développeur Discord → votre application → General Information.',
	'ACP_DISCORD_PUBLIC_KEY'			=> 'Clé publique',
	'ACP_BM2_DISCORD_BOT_TOKEN'			=> 'Jeton du bot',
	'ACP_BM2_DISCORD_BOT_TOKEN_EXPLAIN'	=> 'Portail développeur → Bot → Reset Token. Il n’est jamais réaffiché.',
	'ACP_BM2_DISCORD_BOT_TOKEN_SET'		=> 'Un jeton est enregistré. Laissez vide pour le conserver.',
	'ACP_BM2_DISCORD_SERVER'			=> 'Serveur Discord',
	'ACP_DISCORD_GUILD_ID'				=> 'Identifiant du serveur',
	'ACP_DISCORD_CHANNEL_ID'			=> 'Identifiant du salon des séances',
	'ACP_BM2_DISCORD_CHANNEL_ID_EXPLAIN'	=> 'Salon de type « Forum », avec les étiquettes « À venir » et « Terminée ».',
	'ACP_BM2_DISCORD_SETUP'				=> 'Mise en place',
	'ACP_BM2_DISCORD_ENDPOINT'			=> 'Interactions Endpoint URL',
	'ACP_BM2_DISCORD_ENDPOINT_EXPLAIN'	=> 'À coller dans le portail développeur → General Information, après avoir enregistré la clé publique ici.',
	'ACP_BM2_DISCORD_INVITE'			=> 'Inviter le bot',
	'ACP_BM2_DISCORD_INVITE_EXPLAIN'	=> 'Ajoute le bot à un serveur, avec les permissions nécessaires.',
	'ACP_BM2_DISCORD_INVITE_LINK'		=> 'Lien d’invitation',
	'ACP_BM2_DISCORD_COMMANDS'			=> 'Commandes slash',
	'ACP_BM2_DISCORD_COMMANDS_EXPLAIN'	=> 'À lancer sur le serveur du forum pour déclarer les commandes sur le serveur Discord configuré.',
	'ACP_BM2_DISCORD_CHECK'				=> 'Vérifier le jeton',
	'ACP_BM2_DISCORD_CHECK_OK'			=> 'Jeton valide : connecté en tant que %s.',
	'ACP_BM2_DISCORD_CHECK_KO'			=> 'Échec de la vérification : %s',
	'ACP_BM2_DISCORD_SAVED'				=> 'La configuration Discord a été enregistrée.',
	'ACP_BM2_DISCORD_ERROR_ID'			=> '%s : un identifiant Discord est un nombre de 15 à 25 chiffres.',
	'ACP_BM2_DISCORD_ERROR_PUBLIC_KEY'	=> 'La clé publique doit faire 64 caractères hexadécimaux.',

	'LOG_BM2_DISCORD_SYNC_ERROR'		=> '<strong>Board Manager : échec de mise à jour de la publication Discord</strong><br />» séance %1$d : %2$s',
	'LOG_BM2_DISCORD_LINKED'			=> '<strong>Board Manager : compte Discord lié</strong><br />» %1$s ↔ %2$s',
	'LOG_BM2_DISCORD_UNLINKED'			=> '<strong>Board Manager : compte Discord délié</strong><br />» %1$s ↔ %2$s',

	'LOG_BM2_ORPHAN_DELETED'			=> '<strong>Board Manager : séance orpheline supprimée</strong><br />» séance du %1$s (ancien sujet %2$d)',
	'LOG_BM2_ORPHAN_MOVED'				=> '<strong>Board Manager : séance orpheline réaffectée</strong><br />» séance du %1$s, du sujet %2$d vers le sujet %3$d',
));
