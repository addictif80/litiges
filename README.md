# Locavente — Gestion des litiges

Application web PHP/MySQL de gestion des litiges de **location de matériel BTP, de véhicules
et de machines de chantier** : suivi des dossiers, relances, recouvrement, contacts clients,
pièces jointes, statistiques, export CSV et consultation en lecture seule pour les agences.

Elle inclut également une **messagerie interne** (discussions 1-à-1, groupes et canaux d'agence),
des **notes client** partageables en lecture seule, un **partage de litige par lien public**,
une **inspection d'entreprise sans création de client** (avec historique financier et procédures
collectives par année), et une **analyse de confiance assistée par IA** (1min.ai, facultative).

Les données des entreprises clientes sont récupérées automatiquement depuis l'**API Recherche
d'entreprises de l'État** (gratuite, sans clé) et enrichies avec le **BODACC** (procédures
collectives, radiations) et un **score de solvabilité interne** — le tout gratuit. **Pappers**
reste disponible en option si une clé API est fournie.

---

## Sommaire

1. [Fonctionnalités](#fonctionnalités)
2. [Prérequis](#prérequis)
3. [Installation](#installation)
4. [Mise à jour d'une installation existante](#mise-à-jour-dune-installation-existante)
   - [Migrations automatiques](#migrations-automatiques)
5. [Configuration](#configuration)
6. [Tâche planifiée (cron)](#tâche-planifiée-cron)
7. [Sources de données entreprises](#sources-de-données-entreprises)
8. [Inspection d'une entreprise](#inspection-dune-entreprise)
9. [Rôles et permissions](#rôles-et-permissions)
10. [Messagerie interne](#messagerie-interne)
11. [Notes client](#notes-client)
12. [Analyse de confiance (IA 1min.ai)](#analyse-de-confiance-ia-1minai)
13. [Accès en lecture seule (agences)](#accès-en-lecture-seule-agences)
14. [Notifications](#notifications)
15. [Sécurité](#sécurité)
16. [Structure du projet](#structure-du-projet)
17. [Sauvegarde et maintenance](#sauvegarde-et-maintenance)
18. [Dépannage](#dépannage)

---

## Fonctionnalités

- **Authentification** par identifiant ou email, comptes gérés par un administrateur.
- **Litiges** : référence automatique (`LIT-AAAA-NNNN`), client, agence, objet, type, montants
  (principal, pénalités, récupéré), échéance, priorité, responsable, statut et finalité.
- **Statuts multiples** : un litige change d'étape (Nouveau, En cours, Mise en demeure…)
  et se clôture avec une **finalité** (Règlement total, Irrécouvrable, Prescription…).
  Ces référentiels sont configurables par l'administrateur.
- **Relances / suivi** : historique des contacts (appel, email, courrier, mise en demeure,
  promesse de paiement…), résultat, montant promis, prochaine action.
- **Rappels automatiques** : génération quotidienne de notifications internes et, si le SMTP
  est configuré, d'un e-mail récapitulatif aux administrateurs (relances dues, promesses non
  honorées, échéances proches).
- **Notes internes** et **documents** (pièces jointes) par litige, stockés hors racine web.
- **Clients et contacts** : fiche entreprise alimentée automatiquement (SIREN, SIRET, adresse,
  effectif, procédure collective…), score de solvabilité interne, gestion des contacts et des
  documents client.
- **Notes client** : notes partagées entre gestionnaires, visibles en **lecture seule** par les
  agences (y compris via le lien public éventuel).
- **Inspection d'entreprise** : recherchez n'importe quelle entreprise (nom, SIREN, SIRET) et
  consultez son dossier complet **sans créer de client** — identité, libellés des codes, historique
  financier par exercice, procédures collectives actuelles et par année, dirigeants et autres
  entreprises rattachées, score de solvabilité.
- **Messagerie interne** : discussions **1-à-1**, **groupes** et **canaux d'agence**, avec compteur
  de messages non lus et rafraîchissement automatique.
- **Analyse de confiance (IA)** : sur la fiche client, un bouton interroge **1min.ai** pour décider
  s'il faut faire confiance au client, avec un compte rendu détaillé (facultatif, clé requise).
- **Partage public d'un litige** : génération d'un **lien en lecture seule** (jeton) partageable
  sans compte.
- **Tableau de bord & statistiques** : encours, taux de recouvrement, répartition par statut,
  évolution mensuelle, top clients, encours par agence et par gestionnaire.
- **Export CSV** des litiges et du journal d'audit.
- **Journal d'audit** complet (connexions, créations, modifications, suppressions, exports…).
- **Notifications internes** : création de client, création/modification/résolution de litige,
  note sur un litige et nouveau message (ce dernier réservé aux personnes concernées).
- **Accès lecture seule** pour les agences via un **code d'accès**, sans création de compte.
- **API JSON interne** pour l'auto-complétion des entreprises.

---

## Prérequis

- **PHP 8.0+** avec les extensions `pdo`, `pdo_mysql`, `json`, `mbstring`, `openssl`.
- **MySQL 8** ou **MariaDB 10.4+**.
- Un serveur web (Apache, Nginx, LiteSpeed/CyberPanel…) qui pointe vers le dossier `public/`.
- Facultatif : **Composer** (pour PHPMailer, utilisé si l'envoi d'emails est activé).

Vérifié par l'installeur : l'écran d'installation liste les extensions manquantes le cas échéant.

---

## Installation

### 1. Déploiement des fichiers

Placez le contenu du projet sur le serveur, par exemple :

```
/var/www/litiges.locavente.fr/
├── app/
├── config/
├── cron/
├── database/
├── public/        ← racine web (document root)
├── storage/
└── ...
```

> **Important** : la racine web (document root) doit pointer sur le dossier **`public/`**,
> jamais sur la racine du projet. Cela protège `app/`, `config/`, `storage/` et `cron/`.

Sur CyberPanel : *Websites → Create Website*, puis réglez le *Document Root* sur
`.../public`. Créez ensuite la base MySQL et l'utilisateur associé.

### 2. Permissions

Le serveur web doit pouvoir écrire dans `config/` (uniquement pendant l'installation) et `storage/` :

```bash
chown -R <utilisateur-site>:<utilisateur-site> config storage
chmod -R 775 config storage
```

### 3. (Facultatif) Dépendances Composer

Pour l'envoi d'emails via SMTP (PHPMailer) :

```bash
cd /var/www/litiges.locavente.fr
composer install --no-dev --optimize-autoloader
```

Sans Composer, l'application fonctionne : l'autoloader PSR-4 interne prend le relais et les
emails retombent sur la fonction `mail()` de PHP.

### 4. Installation web

Ouvrez `https://votre-domaine/install.php` et renseignez :

1. Les accès **MySQL** (serveur, port, base — elle sera créée si absente, utilisateur, mot de passe).
2. Le **nom de l'application** et l'**URL de base** (sans slash final).
3. Le **compte administrateur** (identifiant, nom, email, mot de passe de 10 caractères minimum).

L'installeur crée alors la base, importe `database/schema.sql`, écrit `config/config.php`
et crée l'administrateur.

### 5. Après l'installation

- **Supprimez `public/install.php`** (sécurité).
- Vérifiez que `https://votre-domaine/config/config.php` renvoie **403/404**.
- Configurez la [tâche cron](#tâche-planifiée-cron).
- Connectez-vous avec le compte administrateur créé.

---

## Mise à jour d'une installation existante

Les évolutions de la base de données sont appliquées **automatiquement** : à chaque
chargement de page, l'application exécute les fichiers présents dans
`database/migrations/` qui n'ont pas encore été joués (suivi dans la table `migrations`) :

```bash
git pull
```

C'est tout : aucune commande SQL à lancer. Le mécanisme est décrit plus bas
(voir [Migrations automatiques](#migrations-automatiques)).

> Pour forcer une reprise (droits MySQL corrigés, base restaurée, etc.), ouvrez
> **Administration → Base de données** et cliquez sur « Appliquer maintenant ».

> Pour une **installation neuve**, `database/schema.sql` contient déjà tout : aucune
> migration n'est nécessaire (elles sont simplement marquées comme appliquées).

### Migrations automatiques

Le suivi est géré par `app/Core/Migrator.php`, appelé au chargement de chaque page :

1. il compare l'empreinte des fichiers de `database/migrations/` à celle mémorisée dans
   `storage/migrations.state` ; si rien n'a changé, **aucune requête SQL** n'est faite ;
2. sinon, il exécute les fichiers non encore enregistrés dans la table `migrations`.

**Ajouter une évolution** (table, colonne, données de référence…) revient donc à déposer un
fichier `database/migrations/AAAA-MM-JJ-description.sql` et à faire un `git pull`.

Règles d'écriture :

| Règle | Détail |
|---|---|
| Un fichier = une évolution | nommé `AAAA-MM-JJ-description.sql` ; l'ordre alphabétique fait foi. |
| SQL simple | instructions séparées par `;`. **Pas de `DELIMITER` ni de procédures stockées.** |
| Idempotent | la migration peut être relancée après une erreur partielle : `CREATE TABLE IF NOT EXISTS`, garde via `information_schema`, `INSERT … ON DUPLICATE KEY UPDATE`, etc. |
| Ne jamais modifier une migration déjà appliquée | elle ne sera pas rejouée : créez-en une nouvelle. |
| Droits MySQL | l'utilisateur doit pouvoir `CREATE` / `ALTER` / `INDEX`. |

**En cas d'échec**, l'application reste disponible : l'erreur est écrite dans
`storage/logs/migrations.log` et visible dans **Administration → Base de données**
(bouton « Appliquer maintenant » après correction).

---

## Configuration

La configuration se trouve dans **`config/config.php`** (généré par l'installeur, modèle dans
`config/config.example.php`). Ce fichier **ne doit jamais être versionné ni exposé** au web.

Principales clés :

| Clé | Rôle |
|---|---|
| `app.url` | URL de base (sans slash final). |
| `app.timezone` / `app.locale` | Fuseau horaire et locale (`Europe/Paris`, `fr_FR`). |
| `db.*` | Connexion MySQL. |
| `security.*` | Longueur minimale du mot de passe, verrouillage, durée de session, HTTPS forcé. |
| `apis.recherche_entreprises` | API de l'État (gratuite, activée par défaut). |
| `apis.bodacc` | API BODACC (annonces légales, gratuite, activée par défaut). |
| `apis.pappers.api_token` | Clé Pappers (optionnelle). |
| `apis.oneminai.api_key` | Clé API 1min.ai pour l'analyse de confiance (optionnelle). |
| `mail.*` | Paramètres SMTP (optionnels). |

De nombreux réglages (nom de l'application, délais de relance, source société, SMTP,
jeton Pappers…) sont aussi modifiables depuis **Paramètres** dans l'application, et sont
stockés en base (table `settings`) : ils priment sur `config.php` lorsqu'ils sont renseignés.

---

## Tâche planifiée (cron)

Le script **`cron/rappels.php`** génère les rappels (notifications internes + e-mail récapitulatif
si le SMTP est activé). À exécuter une fois par jour.

```bash
# Test manuel
php /var/www/litiges.locavente.fr/cron/rappels.php

# Simulation (aucune écriture en base)
php /var/www/litiges.locavente.fr/cron/rappels.php --dry-run
```

Exemple de crontab (tous les jours à 7 h) :

```cron
0 7 * * * /usr/bin/php /var/www/litiges.locavente.fr/cron/rappels.php --quiet
```

Sur CyberPanel, cette entrée peut être ajoutée via *Cron Jobs*. Utilisez le **chemin absolu**
de PHP (`/usr/bin/php`) et un **chemin absolu** vers le script.

Types de rappels générés :

- `relance_due` — la date de prochaine relance est atteinte ;
- `promesse_depassee` — une promesse de paiement n'a pas été honorée ;
- `echeance_proche` — l'échéance du litige approche (seuil configurable).

Le journal d'exécution est écrit dans `storage/logs/cron-rappels.log`.

---

## Sources de données entreprises

La pile par défaut est **100 % gratuite et sans clé** :

- **API Recherche d'entreprises** (État) : identité complète (SIREN, SIRET, TVA, forme
  juridique, code APE, adresse, effectif, date de création, capital, chiffres clés, statut) ;
- **BODACC** (annonces légales, DILA) : procédures collectives (redressement, liquidation,
  sauvegarde, conciliation) et radiations ;
- **Score de solvabilité interne** : calcul local (aucun service payant) à partir des données
  publiques (résultat net, chiffre d'affaires, ancienneté, effectif) et de l'encours client
  interne (litiges non clôturés).

Le fournisseur principal se choisit dans **Paramètres** :

- **`auto`** (par défaut) : Pappers si une clé est présente, sinon l'API de l'État ;
- **`recherche_entreprises`** : API publique **Recherche d'entreprises** (État, gratuite, sans clé) ;
- **`pappers`** : **Pappers** uniquement (clé requise).

Quel que soit le fournisseur, la fiche est enrichie par le BODACC et le score interne, tous deux
activables dans **Paramètres → Source de données entreprises**. Pappers n'est donc **plus
nécessaire** : il apporte seulement un score financier maison en complément.

Depuis la fiche client, le widget de recherche permet de saisir un nom ou un SIREN, de choisir
l'entreprise puis d'importer automatiquement : SIREN, SIRET, TVA, forme juridique, code APE,
adresse, effectif, date de création, capital, chiffres clés, statut, procédure collective et score
de solvabilité.

Les **codes** renvoyés par l'API (forme juridique, APE/NAF) sont **traduits en clair** grâce à des
référentiels officiels embarqués (`app/Resources/referentiels/`) : libellé de la catégorie
juridique, libellé NAF rev. 2 / NAF 2025 et section d'activité. Les **dirigeants** et les
**autres entreprises** auxquelles ils sont rattachés sont également affichés.

Clé Pappers (optionnelle) : créez un compte sur [pappers.fr](https://www.pappers.fr) et collez le
jeton dans **Paramètres → Source de données entreprises**.

---

## Inspection d'une entreprise

Le menu **Inspecter une entreprise** (rubrique Clients) permet d'analyser **n'importe quelle
entreprise sans créer de client** :

1. Recherchez par **nom, SIREN ou SIRET**.
2. Ouvrez le dossier complet :
   - identité (SIREN, SIRET, TVA, adresse, capital, effectif, date de création) ;
   - **forme juridique** et **code APE** avec leur **libellé** et la section d'activité ;
   - **données financières par exercice** (chiffre d'affaires, résultat net) — **actuelles et
     historiques** ;
   - **procédures collectives** : situation en cours et **annonces BODACC groupées par année** ;
   - **dirigeants** et **autres entreprises** liées à chacun ;
   - **score de solvabilité** interne.
3. Le bouton **Créer un client** reporte les informations vers le formulaire de création.

Aucun client n'est requis pour consulter ce dossier, ce qui permet d'évaluer une entreprise en
amont d'une relation commerciale.

---

## Rôles et permissions

| Rôle | Droits |
|---|---|
| **Administrateur** (`admin`) | Accès total : utilisateurs, agences, paramètres, statuts, journal d'audit, suppressions. |
| **Gestionnaire** (`gestionnaire`) | Création/modification des litiges, clients, relances, notes, documents, partages. |
| **Consultant** (`consultant`) | Consultation des litiges, clients, messagerie… (droits en lecture, configurables). |
| **Lecteur** (`lecteur`) | Consultation uniquement (aucune modification). |

L'administrateur a toujours accès à tous les modules. Pour les autres rôles, les droits
d'accès à chaque module (Litiges, Clients, Relances, Messagerie) se règlent dans
**Paramètres → Droits d'accès par rôle**. Par défaut, *gestionnaire* a tous les accès et
*consultant* / *lecteur* sont en lecture seule.

Les codes d'accès en lecture seule (voir ci-dessous) sont indépendants des comptes utilisateurs.

---

## Messagerie interne

Le menu **Messagerie** permet trois types de conversations :

- **1-à-1** : discussion privée entre deux utilisateurs ;
- **Groupe** : discussion nommée réunissant plusieurs utilisateurs ;
- **Canal d'agence** : conversation visible par tous les utilisateurs d'une même agence.

Les messages non lus sont signalés par un compteur dans la barre de navigation et le fil se
rafraîchit automatiquement. L'accès à la messagerie dépend du module **Messagerie** de chaque
rôle.

---

## Notes client

Chaque fiche client comporte des **notes** rédigées par les gestionnaires. Elles sont :

- **modifiables** par les utilisateurs ayant le droit d'écriture sur le module Clients ;
- visibles en **lecture seule** par les agences connectées ;
- visibles en **lecture seule** via le lien public éventuel de la fiche/litige.

L'auteur d'une note (ou un administrateur) peut la supprimer.

---

## Analyse de confiance (IA 1min.ai)

Sur la fiche client, le bouton **Analyser** interroge **1min.ai** (API compatible OpenAI) pour
produire une recommandation (*faire confiance / faire attention / ne pas faire confiance*) et un
compte rendu argumenté. Le résultat est enregistré sur le client.

L'analyse est **facultative** : elle nécessite une clé API saisie dans **Paramètres →
Intelligence artificielle** (ou `apis.oneminai.api_key`). La clé reste **côté serveur** et n'est
jamais exposée au navigateur. Sans clé, la fonctionnalité reste simplement désactivée.

---

## Accès en lecture seule (agences)

Un **code d'accès** peut être créé pour une agence, un client, un litige précis ou l'ensemble
des litiges. L'agence consulte alors en lecture seule, sans compte :

1. Menu **Accès lecture seule → + Nouveau code d'accès** (libellé, périmètre, expiration optionnelle).
2. Communiquez l'adresse **`https://votre-domaine/partage`** et le **code**.
3. L'agence saisit le code et accède à la liste des litiges de son périmètre, puis aux fiches.

Les codes peuvent être désactivés ou supprimés à tout moment ; les accès sont journalisés.

### Partage public d'un litige (lien)

Depuis la fiche d'un litige, le bouton **Partager** génère (ou réutilise) un **lien public en
lecture seule** de la forme `https://votre-domaine/p/<jeton>`. Ce lien, transmissible à un tiers
(avocat, assureur, confrère…), n'exige **aucun compte** et ne permet aucune modification. Le
jeton peut être révoqué en supprimant le partage associé.

---

## Notifications

Les **notifications internes** signalent :

| Événement | Destinataires |
|---|---|
| **Création de client** | utilisateurs ayant accès au module Clients (hors auteur). |
| **Création de litige** | utilisateurs ayant accès au module Litiges (hors auteur). |
| **Modification / changement de statut d'un litige** | idem. |
| **Résolution d'un litige** | idem (message dédié). |
| **Note sur un litige** | idem. |
| **Nouveau message de messagerie** | **uniquement les personnes concernées** : membres de la conversation, ou utilisateurs de l'agence pour un canal d'agence (hors auteur). |
| **Rappels automatiques** (cron) | responsable / administrateurs (relances dues, promesses non honorées, échéances proches). |

Chaque notification renvoie vers l'élément concerné (client, litige, conversation) via un lien.

---

## Sécurité

- Mots de passe hashés en **bcrypt** ; longueur minimale configurable (10 par défaut).
- **Verrouillage temporaire** du compte après plusieurs échecs de connexion.
- Jetons **CSRF** sur tous les formulaires POST (vérification globale).
- Requêtes SQL **préparées** partout (PDO), échappement HTML systématique (`e()`).
- **Sessions** régénérées à la connexion, expiration par inactivité, cookies `HttpOnly` + `SameSite`.
- **Changement de mot de passe forcé** à la première connexion si demandé.
- Pièces jointes **hors racine web** (`storage/uploads`) et `.htaccess` de protection.
- **Journal d'audit** complet des actions sensibles.
- Recommandé : servir le site en **HTTPS** (`security.force_https = true`), supprimer
  `public/install.php` et restreindre l'accès à `config/` et `storage/`.

---

## Structure du projet

```
app/
├── bootstrap.php            # Amorçage (autoload, config, base, session, migrations)
├── helpers.php              # Fonctions utilitaires (e(), url(), money()…)
├── Controllers/             # Contrôleurs (une classe par domaine)
├── Core/                    # Noyau : Router, Database, Auth, Csrf, View, Migrator…
├── Models/                  # Modèles (accès base de données)
├── Services/                # APIs entreprises, IA, uploads, emails, rappels, export
│   └── Api/                 # Clients API Recherche d'entreprises / BODACC / Pappers / 1min.ai
├── Resources/referentiels/  # Libellés officiels (NAF, catégories juridiques, sections)
└── Views/                   # Gabarits PHP (layouts, pages, partials)

config/
├── config.example.php       # Modèle de configuration
└── config.php               # Configuration réelle (généré, non versionné)

cron/
└── rappels.php              # Tâche planifiée (rappels automatiques)

database/
├── schema.sql               # Schéma MySQL + données de référence (installation neuve)
└── migrations/              # Scripts SQL appliqués automatiquement au chargement des pages

public/                      # ← RACINE WEB
├── index.php                # Front controller (routes)
├── install.php              # Installeur (à supprimer après installation)
└── assets/                  # CSS / JS

storage/                     # Données non publiques (uploads, logs)
```

---

## Sauvegarde et maintenance

- **Base de données** : sauvegardez régulièrement la base MySQL (`mysqldump`).
- **Pièces jointes** : sauvegardez le dossier `storage/uploads/`.
- **Configuration** : conservez une copie de `config/config.php`.

Exemple de sauvegarde :

```bash
mysqldump -u <utilisateur> -p <base> > litiges_$(date +%F).sql
tar czf uploads_$(date +%F).tar.gz storage/uploads
```

Après une mise à jour du code, pensez à vider le cache de l'OPcache si vous l'utilisez.

---

## Dépannage

| Symptôme | Piste |
|---|---|
| Page blanche / erreur 500 | Activez temporairement `app.debug = true` dans `config.php`, consultez le journal PHP. |
| Table / colonne manquante (« Unknown table », « Unknown column ») | Migrations non appliquées : ouvrez **Administration → Base de données** et cliquez sur « Appliquer maintenant ». Détails : `storage/logs/migrations.log`. |
| Migration bloquée | Vérifiez les droits MySQL (`CREATE`, `ALTER`, `INDEX`) et que `storage/` est inscriptible. |
| « Configuration introuvable » | `config/config.php` absent : relancez l'installation. |
| Le cron ne fait rien | Vérifiez le chemin absolu de PHP et du script ; testez avec `--dry-run`. |
| Pas d'emails | Renseignez les paramètres SMTP et cochez « Envoyer les notifications par email ». |
| Données entreprise vides | Vérifiez la connexion sortante HTTPS ; les sources de l'État et le BODACC sont gratuites et sans clé (Pappers est optionnel). |
| URL d'assets incorrecte | Ajustez `app.url` (sans slash final) dans `config.php`. |

---

© Locavente — Gestion des litiges. Version 1.0.
