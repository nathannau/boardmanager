# Plan : bot Discord pour Board Manager

## Objectif

Permettre aux joueurs, depuis Discord, de voir les tables des séances à venir et de s'y inscrire par un bouton, avec exactement les mêmes règles que sur le forum.

## Décisions

- **Discord est réservé aux joueurs.** Les seules actions possibles sont : s'inscrire à une table, s'inscrire en sans table, annuler sa présence, confirmer une pré-inscription. Toute l'administration (configurer, ouvrir, modifier ou annuler une table, ajouter ou retirer des joueurs, gestionnaires) reste sur le forum.
- **Le bot est intégré à l'extension.** Les interactions Discord (commandes slash, boutons) sont reçues en HTTP par un contrôleur de l'extension. Il n'y a ni processus permanent ni conteneur supplémentaire.
- **Les comptes sont liés par un code à usage unique**, généré sur le forum et saisi sur Discord : le membre génère le code dans son UCP, puis tape `/lier CODE` sur Discord. Le pseudo ou l'identifiant Discord saisi dans le profil a été écarté, car n'importe qui pourrait usurper un autre membre.
- **Une publication par séance future dans un salon « Forum » de Discord.** Le premier message de la publication est le tableau des tables avec les boutons ; il est mis à jour à chaque changement. Les membres discutent librement dans la publication, sous le tableau, qui reste toujours en tête. Pas de commande qui reposte des listes, donc aucun message périmé.
- **Une séance passée** voit son tableau passer en « Séance terminée », sans boutons, et sa publication reçoit l'étiquette « Terminée ». La publication est conservée.
- **Les confirmations sont publiques** : l'inscription apparaît directement dans le message de la séance. Les refus (J-3, table pleine, déjà engagé…) ne sont visibles que par le joueur concerné.
- **Le bot n'accède jamais directement à la base** : toutes les actions passent par les règles de l'extension.
- **Les joueurs apparaissent sous leur pseudo du forum** dans les messages, jamais sous leur pseudo Discord.
- **Une seule version, publiée à la fin** : les étapes ne sont pas déployées séparément. Le tout sort en une version, `v2.2.0`.

## Rappel : fonctionnement retenu côté Discord

| Mécanisme | Utilisation | Processus permanent |
|---|---|---|
| Interactions HTTP (« Interactions Endpoint URL ») | `/lier`, clics sur les boutons | non |
| API REST avec le jeton du bot | publier et modifier les messages des séances | non |
| Gateway (WebSocket) | non utilisée | — |

Conséquence purement esthétique : le bot apparaît « hors ligne » dans la liste des membres.

## Prérequis (Nathan)

1. Créer l'application Discord dans le portail développeur (Applications → New Application) et récupérer l'identifiant de l'application, la clé publique et le jeton du bot.
2. Disposer d'un serveur Discord de test privé pour la validation.
3. Créer sur le serveur de la Guilde un salon de type **Forum** pour les séances, avec deux étiquettes : « À venir » et « Terminée ». Fournir l'identifiant du serveur et celui de ce salon.
4. Donner au bot, sur ce salon : voir le salon, créer des publications, envoyer des messages, intégrer des liens, lire l'historique, gérer les fils (pour changer l'étiquette et le titre d'une publication).

## Étape 0 : préparation du code

Aucun changement visible.

- Sortir les actions de `controller/main.php` (s'inscrire, se désinscrire, confirmer, etc.) dans un service `core/actions.php`. Ce service reçoit explicitement l'utilisateur concerné : ses données, ses droits de lecture du forum et son statut de gestionnaire.
- Faire poster les messages auto au nom de cet utilisateur, et non de la session en cours.
- Le contrôleur web ne fait plus qu'appeler le service.
- Test : rejouer sur une copie de test tout le scénario déjà validé. Le comportement doit rester identique.

## Étape 1 : liaison forum ↔ Discord

### Données (migration `v2_2_0`, commune aux étapes 1 et 2)

| Table | Colonnes |
|---|---|
| `bm2_discord_links` | `user_id` (unique), `discord_id` (unique), `discord_username`, `linked_time` |
| `bm2_discord_codes` | `user_id` (unique), `code_hash`, `expires_time` |

- Un seul code actif par membre. Il est valable 10 minutes, ne sert qu'une fois et n'est stocké que haché.
- Générer un nouveau code annule le précédent.

### UCP : onglet « Discord »

- Sans compte lié : bouton « Générer un code de liaison ». Le forum affiche le code (format `K7Q-4M2`) avec la consigne « tape `/lier K7Q-4M2` sur Discord » et l'avertissement « Ne communique jamais ce code ».
- Avec un compte lié : le pseudo Discord s'affiche, avec un bouton « Délier ».

### ACP : page « Discord »

Identifiant de l'application, clé publique, jeton du bot, identifiant du serveur, identifiant du salon Forum des séances (les étiquettes « À venir » et « Terminée » sont retrouvées par leur nom), et un interrupteur « activé ». Ces secrets sont stockés dans la configuration du forum, jamais dans git.

### Contrôleur d'interactions : `POST /app.php/boardmanager2/discord`

- Vérification de la signature Ed25519 (`X-Signature-Ed25519`, `X-Signature-Timestamp`) sur le corps brut de la requête, avec `sodium_crypto_sign_verify_detached()`. En cas d'échec : 401.
- Réponse au PING (`{"type": 1}`).
- La route n'est pas bloquée par le mode maintenance du forum : elle répond « forum en maintenance » à Discord.
- `/lier CODE` :
  - code valide → liaison, code consommé, réponse éphémère « Compte lié à *pseudo du forum* » ;
  - code invalide ou expiré, ou compte Discord déjà lié à un autre membre → réponse éphémère explicite.

### Commande console : `boardmanager2:discord-commands`

Déclare les commandes slash auprès de Discord, sur le serveur indiqué (d'abord le serveur de test).

### Journal d'administration

Les liaisons et déliaisons y sont inscrites.

## Étape 2 : messages des séances

### Données (même migration `v2_2_0`)

`bm2_discord_messages` : `session_id` (unique), `thread_id` (la publication ; dans un salon Forum, son premier message a le même identifiant), `state_hash` (pour ne modifier le message que s'il a changé), `updated_time`.

### Publication d'une séance

- Titre de la publication : « Séance du vendredi 20 novembre 2026 ». Il est mis à jour si la date change.
- Étiquette : « À venir », puis « Terminée ».

### Contenu du tableau (premier message de la publication)

- En-tête : date de la séance et lien vers le sujet du forum.
- Pour chaque table : nom, type, heure, MJ, joueurs (pré-inscriptions « * », inscrits tardifs signalés), places restantes, trigger warning. MJ et joueurs sont affichés sous leur pseudo du forum.
- Le sans table et le récapitulatif des places restantes.
- Boutons :
  - « M'inscrire : *nom de la table* » pour chaque table non pleine ;
  - « Sans table » ;
  - « Annuler ma présence » ;
  - « Confirmer ma pré-inscription ».
- Discord limite un message à 25 boutons (5 lignes de 5) : prévoir le cas d'un grand nombre de tables, par exemple avec un menu de sélection.

### Cycle de vie

- **Séance configurée** sur le forum : création de la publication dans le salon Forum, avec le tableau comme premier message.
- **Clic sur un bouton** :
  1. le membre est retrouvé par son identifiant Discord ;
  2. le service d'actions est appelé, avec les mêmes règles et le même message auto dans le sujet ;
  3. la réponse Discord « UPDATE_MESSAGE » modifie le message cliqué, qui sert de confirmation publique.
  - Un refus est indiqué par une réponse éphémère.
  - Un membre non lié reçoit une réponse éphémère « Lie d'abord ton compte » avec un lien vers l'UCP.
- **Changement fait sur le forum** (inscription, table ouverte, modifiée ou annulée, date changée) : appel à l'API REST pour modifier le tableau (et le titre de la publication si la date a changé). Le délai d'attente est court, et un échec de Discord ne doit jamais faire échouer l'action sur le forum.
- **Séance passée** (à partir du lendemain) : le tableau passe en « Séance terminée », sans boutons, et la publication reçoit l'étiquette « Terminée ».
- **Tâche planifiée** (cron phpBB) :
  - resynchronisation des tableaux des séances futures (rattrape les appels échoués) ;
  - clôture des séances passées ;
  - création des publications manquantes pour les séances configurées.

## Stratégie de test

1. **Copie de test, sans Discord** : générer une paire de clés Ed25519 de test et signer soi-même de fausses interactions (PING, `/lier`, clics). On vérifie ainsi la signature (y compris le refus d'une signature invalide), la liaison, les inscriptions et tous les cas d'erreur, ainsi que la non-régression du forum après l'étape 0.
2. **Déploiement de la version complète, Discord désactivé** : l'interrupteur de la page ACP « Discord » reste éteint. Le forum fonctionne comme avant.
3. **Validation réelle sur le serveur Discord de test** : interrupteur allumé, commandes et salon Forum de test configurés uniquement sur le serveur privé.
4. **Ouverture** : configuration du serveur et du salon Forum de la Guilde, déclaration des commandes, annonce aux membres.

## Déploiement (une seule fois, à la fin)

1. Fusion de `v2-dev` dans `v2` sur le forum, puis purge du cache.
2. Migration `v2_2_0` : désactiver puis réactiver l'extension dans l'ACP. Ne jamais utiliser « Supprimer les données ».
3. Push sur GitHub et tag `v2.2.0`.

## Points ouverts

- Une publication « Terminée » doit-elle aussi être verrouillée (plus de nouveaux messages) ou archivée ? Par défaut : seulement l'étiquette.
