# Locavente — Gestion des litiges

Application web PHP/MySQL de gestion des litiges de **location de matériel BTP, de véhicules
et de machines de chantier** : suivi des dossiers, relances, recouvrement, contacts clients,
pièces jointes, statistiques, export CSV et consultation en lecture seule pour les agences.

Les données des entreprises clientes sont récupérées automatiquement depuis l'**API Recherche
d'entreprises de l'État** (gratuite, sans clé) ou depuis **Pappers** (si une clé API est fournie).

---

## Sommaire

1. [Fonctionnalités](#fonctionnalités)
2. [Prérequis](#prérequis)
3. [Installation](#installation)
4. [Configuration](#configuration)
5. [Tâche planifiée (cron)](#tâche-planifiée-cron)
6. [Sources de données entreprises](#sources-de-données-entreprises)
7. [Rôles et permissions](#rôles-et-permissions)
8. [Accès en lecture seule (agences)](#accès-en-lecture-seule-agences)
9. [Sécurité](#sécurité)
10. [Structure du projet](#structure-du-projet)
11. [Sauvegarde et maintenance](#sauvegarde-et-maintenance)
12. [Dépannage](#dépannage)

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
  effectif, procédure collective…), gestion des contacts et des documents client.
- **Tableau de bord & statistiques** : encours, taux de recouvrement, répartition par statut,
  évolution mensuelle, top clients, encours par agence et par gestionnaire.
- **Export CSV** des litiges et du journal d'audit.
- **Journal d'audit** complet (connexions, créations, modifications, suppressions, exports…).
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
| `apis.pappers.api_token` | Clé Pappers (optionnelle). |
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

La fiche client peut être pré-remplie depuis une source externe, choisie dans **Paramètres** :

- **`auto`** (par défaut) : Pappers si une clé est présente, sinon l'API de l'État ;
- **`recherche_entreprises`** : API publique **Recherche d'entreprises** (État, gratuite, sans clé) ;
- **`pappers`** : **Pappers** uniquement (clé requise).

Depuis la fiche client, le widget de recherche permet de saisir un nom ou un SIREN, de choisir
l'entreprise puis d'importer automatiquement : SIREN, SIRET, TVA, forme juridique, code APE,
adresse, effectif, date de création, capital, chiffres clés, statut et procédure collective.

Clé Pappers : créez un compte sur [pappers.fr](https://www.pappers.fr) et collez le jeton
dans **Paramètres → Source de données entreprises**. Sans clé, l'API de l'État est utilisée.

---

## Rôles et permissions

| Rôle | Droits |
|---|---|
| **Administrateur** (`admin`) | Accès total : utilisateurs, agences, paramètres, statuts, journal d'audit, suppressions. |
| **Gestionnaire** (`gestionnaire`) | Création/modification des litiges, clients, relances, notes, documents, partages. |
| **Lecteur** (`lecteur`) | Consultation uniquement (aucune modification). |

Les codes d'accès en lecture seule (voir ci-dessous) sont indépendants des comptes utilisateurs.

---

## Accès en lecture seule (agences)

Un **code d'accès** peut être créé pour une agence, un client, un litige précis ou l'ensemble
des litiges. L'agence consulte alors en lecture seule, sans compte :

1. Menu **Accès lecture seule → + Nouveau code d'accès** (libellé, périmètre, expiration optionnelle).
2. Communiquez l'adresse **`https://votre-domaine/partage`** et le **code**.
3. L'agence saisit le code et accède à la liste des litiges de son périmètre, puis aux fiches.

Les codes peuvent être désactivés ou supprimés à tout moment ; les accès sont journalisés.

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
├── bootstrap.php            # Amorçage (autoload, config, base, session)
├── helpers.php              # Fonctions utilitaires (e(), url(), money()…)
├── Controllers/             # Contrôleurs (une classe par domaine)
├── Core/                    # Noyau : Router, Database, Auth, Csrf, View, Validator…
├── Models/                  # Modèles (accès base de données)
├── Services/                # APIs entreprises, uploads, emails, rappels, export
│   └── Api/                 # Clients API Pappers / Recherche d'entreprises
└── Views/                   # Gabarits PHP (layouts, pages, partials)

config/
├── config.example.php       # Modèle de configuration
└── config.php               # Configuration réelle (généré, non versionné)

cron/
└── rappels.php              # Tâche planifiée (rappels automatiques)

database/
└── schema.sql               # Schéma MySQL + données de référence

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
| « Configuration introuvable » | `config/config.php` absent : relancez l'installation. |
| Le cron ne fait rien | Vérifiez le chemin absolu de PHP et du script ; testez avec `--dry-run`. |
| Pas d'emails | Renseignez les paramètres SMTP et cochez « Envoyer les notifications par email ». |
| Données entreprise vides | Vérifiez la connexion sortante HTTPS ; la clé Pappers est optionnelle (l'API de l'État suffit). |
| URL d'assets incorrecte | Ajustez `app.url` (sans slash final) dans `config.php`. |

---

© Locavente — Gestion des litiges. Version 1.0.
