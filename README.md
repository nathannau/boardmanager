# Board Manager pour phpBB

Extension phpBB 3.3 qui gère la mise en place des tables de jeu de la Guilde : chaque sujet de séance affiche ses tables, leurs MJ et leurs joueurs, et les membres s'y inscrivent en un clic, depuis le forum ou depuis Discord.

Les séances sont stockées en base de données. Le message du sujet ne contient qu'un marqueur :

```
[boardmanager2][/boardmanager2]
```

## Sommaire

1. [Prérequis](#prérequis)
2. [Installation sur le forum](#installation-sur-le-forum)
3. [Mise à jour de l'extension](#mise-à-jour-de-lextension)
4. [Utilisation](#utilisation)
5. [Intégration Discord](#intégration-discord)
6. [Import des séances v1](#import-des-séances-v1)
7. [Dépannage](#dépannage)
8. [Structure du code](#structure-du-code)

## Prérequis

- phpBB 3.3 (testé en 3.3.19), PHP 7.1 ou plus (testé en 8.5).
- Pour Discord :
  - extension PHP **sodium** (vérification des signatures Discord) et **curl** ;
  - extension **intl** conseillée (comparaison des étiquettes sans accents) ;
  - le forum doit être joignable **en HTTPS depuis Internet**, et le reverse proxy doit transmettre le corps des requêtes sans le modifier ;
  - si le nom du forum a une adresse IPv6 (AAAA), elle doit être joignable elle aussi.

## Installation sur le forum

### 1. Copier l'extension

Le nom de l'extension est `nathannau/boardmanager2` : le dépôt doit être placé dans `ext/nathannau/boardmanager2`, et non dans `boardmanager` (le nom du dépôt).

```
cd <racine du forum>/ext/nathannau
git clone git@github.com:nathannau/boardmanager.git boardmanager2
```

Les fichiers doivent être lisibles par l'utilisateur du serveur web.

### 2. Activer l'extension

Dans l'ACP :

1. **Personnaliser → Gérer les extensions → Board Manager Extension v2 → Activer**.
2. **Index de l'administration → Purger le cache**. Sans cette purge, les nouvelles routes et les commandes console ne sont pas prises en compte.

L'activation applique les migrations, qui créent :

- les tables `bm2_sessions`, `bm2_tables`, `bm2_players` (séances), `bm2_discord_links`, `bm2_discord_codes`, `bm2_discord_messages` (Discord), avec le préfixe du forum ;
- les pages ACP **Extensions → Board Manager** (Gestionnaires des séances, Séances orphelines, Discord) ;
- la page UCP **Profil → Discord**, visible seulement quand l'intégration Discord est activée ;
- la configuration, avec par défaut les groupes Administrateurs et Modérateurs globaux comme gestionnaires, et Discord désactivé.

### 3. Choisir les gestionnaires

**ACP → Extensions → Board Manager → Gestionnaires des séances** : sélectionner les groupes dont les membres peuvent gérer toutes les séances (voir [Gestionnaires](#gestionnaires)).

### 4. Les commandes console

Les commandes se lancent depuis la racine du forum, avec l'utilisateur du serveur web, pour que les fichiers de cache aient le bon propriétaire. Avec l'image Docker de la Guilde (conteneur `laguilde-forum`, utilisateur `application`) :

```
docker exec -u application laguilde-forum php /app/bin/phpbbcli.php <commande>
```

| Commande | Rôle |
|---|---|
| `cache:purge` | Purge le cache (équivalent du bouton de l'ACP) |
| `boardmanager2:import-v1` | Import des séances de la v1 (voir [Import](#import-des-séances-v1)) |
| `boardmanager2:discord-commands` | Déclare les commandes slash du bot sur le serveur Discord |
| `cron:run cron.task.nathannau.boardmanager2.discord_sync` | Lance immédiatement la synchronisation Discord |

## Mise à jour de l'extension

1. Mettre à jour les fichiers (`git pull`, ou fusion de la branche de développement).
2. **Purger le cache immédiatement**. Tant que le cache n'est pas purgé, les pages des sujets avec séance peuvent être en erreur si la mise à jour ajoute des routes.
3. Si la mise à jour contient une **nouvelle migration** (nouveau fichier dans `migrations/`) : **ACP → Gérer les extensions → Désactiver**, puis **Activer**. La réactivation applique la migration ; les données sont conservées.

> ⚠️ Ne jamais utiliser **« Supprimer les données »** sur `nathannau/boardmanager2` : cette action supprime toutes les séances, tables, joueurs et liaisons Discord.

## Utilisation

1. Créer le sujet de la séance avec `[boardmanager2][/boardmanager2]` dans le message. Une seule séance par sujet : elle s'affiche dans le premier message qui contient le marqueur.
2. Cliquer sur **configurer la séance** pour choisir la date (auteur du message ou gestionnaire).
3. Les membres peuvent alors :
   - s'inscrire en **sans table** ;
   - **ouvrir une table** (nom, type, url, heure, nombre de joueurs, trigger warning, description, pré-inscriptions) et en devenir le MJ ;
   - s'**inscrire à une table**, confirmer une pré-inscription, annuler leur présence.
4. Le MJ d'une table peut la **modifier**, l'**annuler** (ses joueurs passent en sans table), **ajouter un joueur** (membre du forum ou personne sans compte, en pré-inscription ou non) et retirer les joueurs sans compte.

Chaque action poste un « Message auto » dans le sujet (ajouté au dernier message si son auteur est la même personne).

### Règles

- **Un seul engagement par séance** : on ne peut pas être joueur ou MJ de deux tables.
- **Règle des 3 jours** : l'inscription directe sur une table n'est plus possible dans les 3 derniers jours avant la séance, sauf pour les membres inscrits en sans table avant J-3.
- **Inscrits tardifs** : les inscriptions faites moins de 4 jours avant la séance sont signalées (en rouge sur le forum, ⏰ sur Discord).
- **Pré-inscription** : marquée « * », elle compte dans les places. Elle est confirmée quand le joueur s'inscrit sur cette table (ou validée par un gestionnaire). S'inscrire sur une **autre** table annule la pré-inscription.
- **Séance passée** : à partir du lendemain de la séance, plus aucune action n'est possible ; seuls les gestionnaires peuvent encore en modifier la date.

### Gestionnaires

Les gestionnaires sont les membres des groupes choisis dans **ACP → Extensions → Board Manager → Gestionnaires des séances** (par défaut : Administrateurs et Modérateurs globaux), ainsi que les fondateurs du forum. Sur toutes les séances, ils peuvent :

- configurer et modifier la date ;
- ouvrir une table pour un autre MJ, modifier une table et en changer le MJ ;
- annuler n'importe quelle table ;
- ajouter ou retirer n'importe quel joueur, valider une pré-inscription ;
- passer outre la règle des 3 jours et le nombre maximum de joueurs.

### Séances orphelines

Supprimer un sujet ne supprime pas sa séance. Les séances dont le sujet n'existe plus sont listées dans **ACP → Extensions → Board Manager → Séances orphelines**, où un administrateur peut :

- les **réaffecter** à un autre sujet (lien du sujet ou d'un de ses messages, ou numéro du sujet), qui doit contenir `[boardmanager2][/boardmanager2]` et ne pas avoir déjà de séance ;
- les **supprimer** définitivement, avec leurs tables et leurs joueurs.

Chaque action est inscrite dans le journal d'administration.

## Intégration Discord

Les joueurs voient les séances à venir sur Discord et s'y inscrivent par des boutons, avec exactement les mêmes règles que sur le forum. L'administration des séances reste sur le forum.

### Fonctionnement

- **Aucun bot ne tourne en permanence** : Discord envoie les commandes et les clics au forum (« Interactions Endpoint URL », requêtes signées vérifiées par le forum), et le forum modifie les messages Discord par l'API REST, avec le jeton du bot. Le bot apparaît donc « hors ligne » dans la liste des membres, c'est normal.
- **Liaison des comptes** : chaque membre lie son compte Discord depuis son profil du forum (**Profil → Discord**). Le forum affiche un code à usage unique (valable 10 minutes), que le membre tape sur Discord avec `/lier CODE`. Un pseudo saisi à la main ne suffirait pas : n'importe qui pourrait se faire passer pour un autre.
- **Une publication par séance future** dans un salon de type **Forum** :
  - titre « Séance du vendredi 20 novembre 2026 », étiquette « À venir » ;
  - premier message : le tableau des tables (MJ, joueurs présents sous leur pseudo du forum, pré-inscriptions, places) avec un bouton **« M'inscrire : jeu de MJ »** par table, **« Sans table »** et **« Annuler ma présence »** ;
  - les membres discutent librement dans la publication, sous le tableau.
- **Clic sur un bouton** : l'action est faite au nom du membre lié, avec le même message auto dans le sujet du forum. Un succès est confirmé par un message **public** dans la publication (« ✅ **Pseudo** : … ») ; un refus (table complète, déjà inscrit, règle des 3 jours…) par un message visible **du seul joueur**, affiché tout en bas de la publication.
- **Mises à jour** : après chaque action faite sur le forum, la publication est mise à jour (titre si la date change, tableau). Si Discord ne répond pas, l'action sur le forum n'est jamais bloquée : l'erreur est inscrite au journal des erreurs de l'ACP, et une tâche cron (toutes les heures) rattrape.
- **Séance passée** : le lendemain de la séance, son tableau passe en « Séance terminée » sans boutons, la publication reçoit l'étiquette « Terminée » et elle est **verrouillée**. Si un gestionnaire repousse la date, la publication est déverrouillée et repasse en « À venir ».
- Les séances déjà passées (historique) n'ont pas de publication.

### Étape 1 : créer l'application Discord

Sur <https://discord.com/developers/applications> :

1. **New Application**, avec le nom affiché du bot (par exemple « BoardManager »).
2. **General Information** :
   - noter l'**Application ID** et la **Public Key** ;
   - **App Icon** : l'icône sert aussi d'avatar au bot ;
   - laisser **Interactions Endpoint URL** vide pour l'instant (étape 5).
3. **Installation** :
   - **Installation Contexts** : cocher seulement **Guild Install** ;
   - **Install Link** : **None** (on invite le bot avec le lien de l'ACP, qui contient les bonnes permissions).
4. **Bot** :
   - **Réinitialiser le token** : copier le jeton et le conserver dans un gestionnaire de mots de passe. Il n'est affiché qu'une fois, et quiconque le possède contrôle le bot. Il ne doit jamais être publié ni mis dans git ;
   - **Bot public** : **désactivé**, pour que personne d'autre ne puisse inviter le bot. Si Discord refuse (« Private application cannot have a default authorization link »), passer d'abord **Install Link** à **None** ;
   - **Privileged Gateway Intents** (Presence, Server Members, Message Content) : **tous désactivés**.
   - La grille « Permissions du bot » de cette page n'est qu'un calculateur : rien à cocher.

### Étape 2 : préparer le serveur Discord

1. Le salon Forum demande un **serveur communautaire** : **Paramètres du serveur → Activer la communauté**.
2. Créer un salon de type **Forum** (par exemple « séances »).
3. Dans ce salon, **Étiquettes** : créer **« À venir »** et **« Terminée »**. Les noms sont reconnus sans tenir compte des accents ni de la casse (« A venir » convient aussi). Cocher « Seuls les modérateurs peuvent appliquer cette étiquette ».
4. **Paramètres utilisateur → Avancés → Mode développeur** : activé. Puis :
   - clic droit sur l'icône du serveur → **Copier l'identifiant du serveur** ;
   - clic droit sur le salon Forum → **Copier l'identifiant du salon**.

### Étape 3 : configurer le forum

**ACP → Extensions → Board Manager → Discord** :

1. Saisir l'**identifiant de l'application**, la **clé publique**, le **jeton du bot**, l'**identifiant du serveur** et l'**identifiant du salon des séances**. Laisser **Intégration activée** sur **Non** pour l'instant.
2. **Envoyer**, puis **Vérifier le jeton** : la page doit afficher « Jeton valide : connecté en tant que … ».

Le jeton n'est jamais réaffiché ; pour le conserver, laisser le champ vide lors des enregistrements suivants.

### Étape 4 : inviter le bot et régler les permissions du salon

1. Sur la page ACP Discord, ouvrir le **lien d'invitation** et choisir le serveur. Le lien donne au bot les permissions nécessaires : voir les salons, envoyer des messages (y compris dans les fils), intégrer des liens, lire l'historique, créer des fils publics, gérer les fils.
2. Dans les **permissions du salon Forum**, pour **@everyone**, on peut refuser **« Créer des posts »** : les membres répondent dans les publications du bot sans en créer. Ce refus s'appliquerait aussi au bot ; il faut donc ajouter son rôle :
   **Permissions → Permissions avancées → + → rôle du bot (BoardManager)**, et cliquer sur la **coche verte ✓** (pas « / », qui ne fait qu'hériter) de :
   - **Voir le salon**
   - **Créer des posts**
   - **Envoyer des messages dans les posts**
   - **Gérer les posts** (étiquettes, titre, verrouillage)
   - **Intégrer des liens**
   - **Voir les anciens posts**

   puis **Enregistrer les modifications**.

### Étape 5 : déclarer l'URL d'interactions

Dans le portail développeur, **General Information → Interactions Endpoint URL** : coller l'URL affichée sur la page ACP Discord, de la forme

```
https://forum.example.org/app.php/boardmanager2/discord
```

puis **Save Changes**. Discord envoie alors deux requêtes de vérification, une valide et une mal signée ; l'URL n'est acceptée que si le forum répond correctement aux deux. La clé publique doit donc déjà être enregistrée dans l'ACP. L'URL répond même quand le forum est en maintenance, et même si l'intégration est désactivée.

Une application Discord n'a qu'une seule URL d'interactions : avec un forum de test, l'application (ou une deuxième application) doit pointer vers lui le temps des essais.

### Étape 6 : déclarer les commandes et activer

1. Déclarer la commande `/lier` sur le serveur configuré :

   ```
   php bin/phpbbcli.php boardmanager2:discord-commands
   ```

   Option `--guild=ID` pour un autre serveur, `--remove` pour retirer les commandes du bot.
2. **ACP → Board Manager → Discord → Intégration activée : Oui**, puis **Envoyer**.
3. Lancer une première synchronisation, qui crée les publications des séances à venir (sinon la tâche cron le fait dans l'heure, ou à la prochaine action sur une séance) :

   ```
   php bin/phpbbcli.php cron:run cron.task.nathannau.boardmanager2.discord_sync
   ```

4. Annoncer aux membres : **Profil → Discord → Générer un code**, puis `/lier CODE` sur le serveur Discord.

La tâche cron de phpBB doit tourner : avec le cron « web » de phpBB (réglage par défaut), elle est déclenchée par les visites du forum ; avec le cron système, elle doit être planifiée.

### Désactiver Discord

**Intégration activée : Non** : le bot répond « pas encore activé », la page Discord du profil est masquée et les publications ne sont plus mises à jour. Les liaisons sont conservées.

## Import des séances v1

Jusqu'à la v1.1, la séance était écrite dans le texte du message (`[boardmanager JJMMAAAA]…[/boardmanager]`). La commande suivante importe ces séances en base :

```
php bin/phpbbcli.php boardmanager2:import-v1 [options]
```

| Option | Effet |
|---|---|
| `--dry-run` | Analyse et rapport (séances, tables, joueurs, pseudos sans compte), sans aucune écriture. |
| `--update` | Réimporte aussi les séances v1 déjà importées (leurs tables et joueurs sont remplacés). |
| `--rewrite-posts` | Remplace le bloc v1 des messages par `[boardmanager2][/boardmanager2]`. Après cela, la v1 n'affiche plus ces messages : à n'utiliser qu'à la bascule. |
| `--post=ID` | Limite le traitement à un message. |

L'import est idempotent : un sujet qui a déjà une séance est ignoré (sauf avec `--update`). Les pseudos v1 sont rattachés aux comptes par leur nom ; ceux qui ne correspondent à aucun compte deviennent des joueurs sans compte.

Bascule conseillée : sauvegarde de la base, forum désactivé, `--dry-run`, puis `--rewrite-posts`, puis désactivation et suppression des données de l'extension v1 `nathannau/boardmanager`.

Après l'import, vérifier qu'aucune ancienne séance n'a une date dans le futur (année mal saisie en v1) : elle afficherait des boutons d'inscription et serait publiée sur Discord. La corriger avec « modifier la date ».

## Dépannage

| Symptôme | Cause et solution |
|---|---|
| Page d'un sujet en erreur après une mise à jour | Cache non purgé : **Purger le cache**. |
| La page ACP Discord ou Profil → Discord n'apparaît pas | Migration non appliquée : désactiver puis réactiver l'extension. La page du profil n'apparaît qu'avec l'intégration activée. |
| « Vérifier le jeton » : 401 Unauthorized | Jeton faux ou réinitialisé : en générer un nouveau dans le portail et le saisir dans l'ACP. |
| Discord refuse l'URL d'interactions | Clé publique absente ou fausse dans l'ACP, URL non joignable en HTTPS depuis Internet (DNS, IPv6, certificat), ou corps de requête modifié par le reverse proxy. |
| `/lier` n'apparaît pas sur Discord | Commande non déclarée (`boardmanager2:discord-commands`), ou bot absent du serveur. |
| Journal des erreurs : « 403 : Missing Access » | Le bot ne peut pas créer de publication dans le salon : ajouter son rôle aux permissions du salon avec la coche verte (voir étape 4). |
| Les publications n'ont pas d'étiquette | Étiquettes « À venir » et « Terminée » absentes du salon Forum. |
| Un clic ne semble rien faire | Les refus sont visibles du seul joueur, tout en bas de la publication (« Toi seul(e) peux voir celui-ci »). |
| « L'interaction a échoué » | Le forum n'a pas répondu à Discord dans les 3 secondes, ou n'était pas joignable. Vérifier les journaux du reverse proxy (requêtes `POST /app.php/boardmanager2/discord`). |
| Une publication supprimée à la main n'est pas recréée | Elle est recréée au prochain changement de la séance, sur le forum ou sur Discord. |

Les échecs d'appel à Discord sont inscrits dans **ACP → Maintenance → Journal des erreurs** (« Board Manager : échec de mise à jour de la publication Discord »). Les liaisons et déliaisons de comptes sont dans le **journal d'administration**.

## Structure du code

| Dossier / fichier | Rôle |
|---|---|
| `migrations/v2_0_0.php` | Tables des séances, module ACP, groupes gestionnaires par défaut |
| `migrations/v2_0_1.php` | Page ACP des séances orphelines |
| `migrations/v2_2_0.php` | Intégration Discord : tables, configuration, pages ACP et UCP |
| `event/listener.php` | BBCode marqueur, affichage de la séance dans le sujet, route Discord autorisée en maintenance |
| `controller/main.php` | Actions du forum (configurer, ouvrir, modifier, s'inscrire, annuler, ajouter, valider, retirer) |
| `controller/discord.php` | Interactions Discord : signature, `/lier`, boutons |
| `core/actions.php`, `core/actor.php`, `core/poster.php` | Actions des joueurs communes au forum et à Discord, au nom d'un membre ; messages auto |
| `core/store.php` | Accès aux tables `bm2_*` des séances |
| `core/rules.php` | Règles métier |
| `core/renderer.php` | Données du template de la séance |
| `core/discord_links.php` | Liaison des comptes (codes à usage unique) |
| `core/discord_api.php` | Appels à l'API REST de Discord |
| `core/discord_panel.php` | Tableau d'une séance pour Discord (embed et boutons) |
| `core/discord_sync.php` | Création et mise à jour des publications Discord |
| `cron/discord_sync.php` | Tâche cron de rattrapage |
| `core/legacy/` | Lecteurs du format v1, utilisés par l'import |
| `console/` | Commandes `import-v1` et `discord-commands` |
| `acp/`, `adm/style/` | Pages ACP : gestionnaires, séances orphelines, Discord |
| `ucp/` | Page Profil → Discord |
| `styles/all/` | Templates et CSS |
| `language/fr/` | Textes (`language/en/` en est une copie, pour le repli de phpBB) |

Les liens d'action du forum sont protégés par un hash propre à chaque membre (`generate_link_hash`) et les formulaires par une clé de formulaire phpBB. Les requêtes Discord sont vérifiées par leur signature Ed25519 et leur horodatage. Les droits sont vérifiés côté serveur à chaque action.
