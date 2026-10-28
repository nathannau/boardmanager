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
	// Affichage de la séance
	'BM2_SESSION_OF'				=> 'Séance du %s',
	'BM2_NOT_CONFIGURED'			=> 'Séance non configurée',
	'BM2_CONFIGURE'					=> 'configurer la séance',
	'BM2_CHANGE_DATE'				=> 'modifier la date',
	'BM2_NO_TABLE'					=> 'Sans table',
	'BM2_NB_PLAYERS'				=> array(
		0	=> '%d joueur',
		1	=> '%d joueur',
		2	=> '%d joueurs',
	),
	'BM2_PLAYERS_RANGE'				=> '%1$d-%2$d joueurs',
	'BM2_REGISTER_NO_TABLE'			=> 'm’inscrire en sans table',
	'BM2_REGISTER_TABLE'			=> 'm’inscrire à la table',
	'BM2_UNREGISTER'				=> 'annuler ma présence',
	'BM2_CONFIRM'					=> 'confirmer ma présence',
	'BM2_CANCEL_TABLE'				=> 'annuler la table',
	'BM2_CANCEL_MY_TABLE'			=> 'annuler ma table',
	'BM2_EDIT_TABLE'				=> 'modifier la table',
	'BM2_OPEN_TABLE'				=> 'ouvrir une table',
	'BM2_ADD_PLAYER'				=> 'ajouter un joueur',
	'BM2_REMOVE_PLAYER'				=> 'retirer',
	'BM2_VALIDATE'					=> 'valider la pré-inscription',
	'BM2_BTN_REGISTER'				=> 'm’inscrire',
	'BM2_BTN_EDIT'					=> 'modifier',
	'BM2_BTN_CONFIRM'				=> 'confirmer',
	'BM2_BTN_VALIDATE'				=> 'valider',
	'BM2_TRIGGER_WARNING'			=> 'Trigger warning',
	'BM2_REMAINING_MIN'				=> array(
		0	=> 'Il reste %d place à remplir pour lancer toutes les tables',
		1	=> 'Il reste %d place à remplir pour lancer toutes les tables',
		2	=> 'Il reste %d places à remplir pour lancer toutes les tables',
	),
	'BM2_REMAINING_MAX'				=> array(
		0	=> 'Il reste un maximum de %d place',
		1	=> 'Il reste un maximum de %d place',
		2	=> 'Il reste un maximum de %d places',
	),

	'BM2_TYPE_OS'					=> 'One shot',
	'BM2_TYPE_CO'					=> 'Campagne ouverte',
	'BM2_TYPE_CF'					=> 'Campagne fermée',
	'BM2_TYPE_TS'					=> 'Table de secours',

	// Formulaires
	'BM2_CONFIGURE_TITLE'			=> 'Configuration de la séance',
	'BM2_SESSION_DATE'				=> 'Date de la séance',
	'BM2_SAVE'						=> 'Enregistrer',
	'BM2_OPEN_TITLE'				=> 'Ouverture de table',
	'BM2_FIELD_NAME'				=> 'Nom du jeu',
	'BM2_FIELD_URL'					=> 'Url du descriptif',
	'BM2_FIELD_TYPE'				=> 'Type',
	'BM2_FIELD_TIME'				=> 'Heure de début',
	'BM2_FIELD_MIN'					=> 'Nombre de joueurs minimum',
	'BM2_FIELD_MAX'					=> 'Nombre de joueurs maximum',
	'BM2_FIELD_MJ'					=> 'MJ (pseudo)',
	'BM2_FIELD_MJ_EXPLAIN'			=> 'Gestionnaires : la table peut être confiée à un autre MJ.',
	'BM2_FIELD_TRIGGER_WARNING'		=> 'Trigger warning',
	'BM2_FIELD_DESCRIPTION'			=> 'Courte description',
	'BM2_FIELD_PREREGISTRATION'		=> 'Pré-inscription',
	'BM2_FIELD_PREREGISTRATION_EXPLAIN'	=> 'Un joueur par ligne. Un pseudo du forum crée une pré-inscription « * » à confirmer ; un autre nom ajoute un joueur hors forum.',
	'BM2_OPEN_SUBMIT'				=> 'Ouvrir la table',
	'BM2_EDIT_TITLE'				=> 'Modification de la table',
	'BM2_EDIT_SUBMIT'				=> 'Enregistrer',
	'BM2_REQUIRED'					=> '* informations obligatoires',
	'BM2_ADDPLAYER_TITLE'			=> 'Ajouter un joueur à la table « %s »',
	'BM2_FIELD_PLAYER'				=> 'Nom du joueur',
	'BM2_FIELD_PLAYER_EXPLAIN'		=> 'Pseudo d’un membre du forum, ou nom d’un joueur sans compte (joueur hors forum).',
	'BM2_FIELD_TEMPORARY'			=> 'Pré-inscription',
	'BM2_FIELD_TEMPORARY_EXPLAIN'	=> 'Le joueur apparaît avec « * » tant que sa présence n’est pas confirmée.',
	'BM2_ADD'						=> 'Ajouter',
	'BM2_BACK_TO_TOPIC'				=> '%sRetour au sujet%s',

	// Erreurs
	'BM2_ERROR_NOT_FOUND'			=> 'Cette séance ou cette table n’existe pas.',
	'BM2_ERROR_NO_MARKER'			=> 'Ce message ne contient pas de séance [boardmanager2][/boardmanager2].',
	'BM2_ERROR_PAST'				=> 'Cette séance est passée : elle ne peut plus être modifiée.',
	'BM2_ERROR_NOT_ALLOWED'			=> 'Vous n’avez pas le droit d’effectuer cette action.',
	'BM2_ERROR_LINK'				=> 'Le lien utilisé n’est pas valide. Retournez sur le sujet et réessayez.',
	'BM2_ERROR_DATE'				=> 'La date n’est pas valide.',
	'BM2_ERROR_NAME'				=> 'Le nom du jeu est obligatoire.',
	'BM2_ERROR_TYPE'				=> 'Le type de table n’est pas valide.',
	'BM2_ERROR_TIME'				=> 'L’heure de début est obligatoire.',
	'BM2_ERROR_PLAYERS'				=> 'Le nombre de joueurs doit être d’au moins 1, et le maximum supérieur ou égal au minimum.',
	'BM2_ERROR_URL'					=> 'L’url doit commencer par http:// ou https://.',
	'BM2_ERROR_PLAYER_NAME'			=> 'Le nom du joueur est obligatoire.',
	'BM2_ERROR_ENGAGED'				=> 'Vous êtes déjà inscrit ou MJ sur une table de cette séance.',
	'BM2_ERROR_USER_ENGAGED'		=> '« %s » est déjà inscrit ou MJ sur une table de cette séance.',
	'BM2_ERROR_ALREADY_NO_TABLE'	=> 'Vous êtes déjà inscrit en sans table.',
	'BM2_ERROR_LIMIT_DAYS'			=> 'Les inscriptions sur les tables ouvrent 3 jours avant la séance. Inscrivez-vous en sans table en attendant.',
	'BM2_ERROR_FULL'				=> 'Cette table est complète.',
	'BM2_ERROR_MAX_BELOW_PLAYERS'	=> 'Le maximum ne peut pas être inférieur au nombre de joueurs déjà inscrits (%d).',
	'BM2_ERROR_MJ_NAME'				=> 'Le pseudo du MJ est obligatoire.',
	'BM2_ERROR_NOT_TEMPORARY'		=> 'Ce joueur n’est pas pré-inscrit.',
	'BM2_ERROR_NOT_REGISTERED'		=> 'Vous n’êtes pas inscrit ici.',

	// Discord (réponses du bot, visibles seulement par le membre)
	'BM2_DISCORD_MAINTENANCE'		=> 'Le forum est en maintenance, réessaie un peu plus tard.',
	'BM2_DISCORD_DISABLED'			=> 'Le bot de la Guilde n’est pas encore activé.',
	'BM2_DISCORD_WRONG_GUILD'		=> 'Ce bot ne fonctionne que sur le serveur Discord de la Guilde.',
	'BM2_DISCORD_UNKNOWN'			=> 'Je ne connais pas cette action.',
	'BM2_DISCORD_LINKED'			=> 'C’est fait : ton compte Discord est lié au compte du forum **%s**.',
	'BM2_DISCORD_CODE_INVALID'		=> 'Ce code n’est pas valable ou a expiré. Génère un nouveau code dans ton profil du forum (Profil → Discord).',
	'BM2_DISCORD_TOO_MANY_TRIES'	=> 'Trop de codes erronés : réessaie dans un quart d’heure.',
	'BM2_DISCORD_NOT_LINKED'		=> 'Pour t’inscrire depuis Discord, lie d’abord ton compte : dans ton profil du forum (Profil → Discord), génère un code, puis ici tape /lier, choisis la commande et colle le code. %s',
	'BM2_DISCORD_AUTO_PREFIX'		=> 'Message auto :',
	'BM2_DISCORD_NO_ACCOUNT'		=> 'Le compte du forum lié à ce compte Discord est introuvable ou désactivé.',

	// Discord : publication d'une séance
	'BM2_DISCORD_POST_TITLE'		=> 'Séance du %s',
	'BM2_DISCORD_TAG_OPEN'			=> 'À venir',
	'BM2_DISCORD_TAG_PAST'			=> 'Terminée',
	'BM2_DISCORD_PAST'				=> 'Séance terminée.',
	'BM2_DISCORD_HOWTO'				=> 'Inscris-toi avec les boutons ci-dessous (compte Discord lié au forum : Profil → Discord). La séance et ses messages sont aussi sur le forum, via le titre.',
	'BM2_DISCORD_MJ'				=> 'MJ : **%s**',
	'BM2_DISCORD_PLACES'			=> '%1$d/%2$d joueurs',
	'BM2_DISCORD_MISSING'			=> array(
		1	=> 'il manque %d joueur',
		2	=> 'il manque %d joueurs',
	),
	'BM2_DISCORD_LEGEND_TEMPORARY'	=> '* pré-inscription à confirmer',
	'BM2_DISCORD_LEGEND_LATE'		=> '⏰ inscription moins de 4 jours avant la séance',
	'BM2_DISCORD_BTN_REGISTER'		=> 'M’inscrire : %1$s de %2$s',
	'BM2_DISCORD_BTN_REGISTER_NO_MJ'	=> 'M’inscrire : %s',
	'BM2_DISCORD_BTN_NO_TABLE'		=> 'Sans table',
	'BM2_DISCORD_BTN_UNREGISTER'	=> 'Annuler ma présence',
	'BM2_DISCORD_SELECT'			=> 'Choisis une table pour t’inscrire',
	'BM2_DISCORD_ALREADY_LINKED_OTHER'	=> 'Ce compte Discord est déjà lié à un autre compte du forum. Délie-le d’abord depuis ce compte (Profil → Discord).',

	// Messages auto
	'BM2_AUTO_CONFIGURE'			=> 'Message auto : Séance configurée au %s',
	'BM2_AUTO_REGISTER_NO_TABLE'	=> 'Message auto : Inscription en sans table',
	'BM2_AUTO_REGISTER_TABLE'		=> 'Message auto : Inscription sur la table \'%1$s\' de \'%2$s\'',
	'BM2_AUTO_UNREGISTER_NO_TABLE'	=> 'Message auto : Désinscription de sans table',
	'BM2_AUTO_CANCEL_PREREGISTRATION'	=> 'Message auto : Annulation de la pré-inscription sur la table \'%1$s\' de \'%2$s\'',
	'BM2_AUTO_UNREGISTER_TABLE'		=> 'Message auto : Désinscription de la table \'%1$s\' de \'%2$s\'',
	'BM2_AUTO_OPEN'					=> 'Message auto : Ajout de la table \'%1$s\' à %2$s pour %3$d à %4$d',
	'BM2_AUTO_EDIT'					=> 'Message auto : Modification de la table \'%1$s\' de \'%2$s\' : %3$s, %4$d à %5$d joueurs',
	'BM2_AUTO_CANCEL'				=> 'Message auto : Annule la table \'%1$s\' à %2$s pour %3$d à %4$d',
	'BM2_AUTO_MOVED'				=> 'Message auto : \'%s\' passe en sans table',
	'BM2_AUTO_ADD_PLAYER'			=> 'Message auto : Ajout du joueur \'%1$s\' sur la table \'%2$s\' de \'%3$s\'',
	'BM2_AUTO_PREREGISTER_PLAYER'	=> 'Message auto : Pré-inscription du joueur \'%1$s\' sur la table \'%2$s\' de \'%3$s\'',
	'BM2_AUTO_VALIDATE_PLAYER'		=> 'Message auto : Validation de la pré-inscription de \'%1$s\' sur la table \'%2$s\' de \'%3$s\'',
	'BM2_AUTO_REMOVE_PLAYER'		=> 'Message auto : Retrait du joueur \'%1$s\' de la table \'%2$s\' de \'%3$s\'',
	'BM2_AUTO_REMOVE_PLAYER_NO_TABLE'	=> 'Message auto : Retrait du joueur \'%s\' de sans table',
));
