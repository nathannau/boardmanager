# Board Manager pour phpBB

Extension phpBB 3.3 qui gère la mise en place des tables de jeu de la Guilde : chaque sujet de séance affiche ses tables, leurs MJ et leurs joueurs, et les membres s'y inscrivent en un clic.

Les séances sont stockées en base de données. Le message du sujet ne contient qu'un marqueur :

```
[boardmanager2][/boardmanager2]
```

## Installation

Le nom de l'extension est `nathannau/boardmanager2` : le dépôt doit être placé dans `ext/nathannau/boardmanager2` (et non `boardmanager`, le nom du dépôt).

```
cd <racine du forum>/ext/nathannau
git clone git@github.com:nathannau/boardmanager.git boardmanager2
```

Puis, dans l'ACP : **Personnaliser → Gérer les extensions → Board Manager Extension v2 → Activer**, et **Purger le cache** depuis l'index de l'ACP.

L'activation crée les tables `bm2_sessions`, `bm2_tables` et `bm2_players` (avec le préfixe du forum), le module ACP et la liste des groupes gestionnaires.

Prérequis : phpBB 3.3, PHP 7.1 ou plus.

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
- **Inscrits tardifs** : les inscriptions faites moins de 4 jours avant la séance sont affichées en rouge.
- **Pré-inscription** : marquée « * », elle compte dans les places et doit être confirmée par le joueur (ou validée par un gestionnaire).
- **Séance passée** : à partir du lendemain de la séance, plus aucune action n'est possible ; seuls les gestionnaires peuvent encore en modifier la date.

### Gestionnaires

Les gestionnaires sont les membres des groupes choisis dans **ACP → Extensions → Board Manager** (par défaut : Administrateurs et Modérateurs globaux), ainsi que les fondateurs du forum. Sur toutes les séances, ils peuvent :

- configurer et modifier la date ;
- ouvrir une table pour un autre MJ, modifier une table et en changer le MJ ;
- annuler n'importe quelle table ;
- ajouter ou retirer n'importe quel joueur, valider une pré-inscription ;
- passer outre la règle des 3 jours et le nombre maximum de joueurs.

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

## Structure

| Dossier / fichier | Rôle |
|---|---|
| `migrations/v2_0_0.php` | Tables, module ACP, groupes gestionnaires par défaut |
| `event/listener.php` | Déclaration du BBCode marqueur, affichage de la séance dans le sujet |
| `controller/main.php` | Actions (configurer, ouvrir, modifier, s'inscrire, annuler, ajouter, valider, retirer) |
| `core/store.php` | Accès aux tables `bm2_*` |
| `core/rules.php` | Règles métier |
| `core/renderer.php` | Données du template de la séance |
| `core/legacy/` | Lecteurs du format v1, utilisés par l'import |
| `console/import_v1.php` | Commande d'import |
| `acp/`, `adm/style/` | Page ACP des groupes gestionnaires |
| `styles/all/` | Templates et CSS |
| `language/fr/` | Textes |

Les liens d'action sont protégés par un hash propre à chaque membre (`generate_link_hash`) et les formulaires par une clé de formulaire phpBB. Les droits sont vérifiés côté serveur à chaque action.
