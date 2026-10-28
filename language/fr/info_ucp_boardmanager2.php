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
	'UCP_BOARDMANAGER2_DISCORD'			=> 'Discord',
	'UCP_BM2_DISCORD_EXPLAIN'			=> 'Lie ton compte Discord pour t’inscrire aux tables directement depuis le serveur Discord de la Guilde.',
	'UCP_BM2_DISCORD_LINKED_TO'			=> 'Ton compte est lié au compte Discord <strong>%1$s</strong> (depuis le %2$s).',
	'UCP_BM2_DISCORD_NOT_LINKED'		=> 'Aucun compte Discord n’est lié. Génère un code, puis tape-le sur Discord avec la commande /lier.',
	'UCP_BM2_DISCORD_PENDING'			=> 'Un code est déjà en cours de validité ; en générer un nouveau annule le précédent.',
	'UCP_BM2_DISCORD_CODE_INTRO'		=> 'Ton code de liaison :',
	'UCP_BM2_DISCORD_COPY'				=> 'Copier le code',
	'UCP_BM2_DISCORD_COPIED'			=> 'Code copié ✓',
	'UCP_BM2_DISCORD_STEP_COMMAND'		=> 'Sur le serveur Discord de la Guilde, tape <strong>/lier</strong> et choisis la commande dans la liste qui s’affiche (coller « /lier » d’un bloc ne fonctionne pas).',
	'UCP_BM2_DISCORD_STEP_PASTE'		=> 'Colle le code dans le champ <strong>code</strong> proposé par Discord.',
	'UCP_BM2_DISCORD_STEP_SEND'			=> 'Appuie sur Entrée : le bot confirme la liaison.',
	'UCP_BM2_DISCORD_CODE_VALIDITY'		=> 'Ce code est valable %d minutes et ne sert qu’une fois.',
	'UCP_BM2_DISCORD_CODE_WARNING'		=> 'Ne communique jamais ce code : il permettrait à quelqu’un d’autre d’agir en ton nom.',
	'UCP_BM2_DISCORD_GENERATE'			=> 'Générer un code de liaison',
	'UCP_BM2_DISCORD_NEW_CODE'			=> 'Générer un nouveau code',
	'UCP_BM2_DISCORD_UNLINK'			=> 'Délier mon compte Discord',
	'UCP_BM2_DISCORD_UNLINKED'			=> 'Ton compte Discord a été délié.',
));
