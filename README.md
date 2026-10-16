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
   - [Migrations](#migrations)
5. [Configuration](#configuration)
6. [Tâches planifiées (cron)](#tâches-planifiées-cron)
7. [Sources de données entreprises](#sources-de-données-entreprises)
8. [Inspection d'une entreprise](#inspection-dune-entreprise)
9. [Rôles et permissions](#rôles-et-permissions)
10. [Recouvrement : paiements, échéancier, pénalités](#recouvrement--paiements-échéancier-pénalités)
11. [Courriers et suivi d'envoi](#courriers-et-suivi-denvoi)
12. [Workflow par statut](#workflow-par-statut)
13. [Procédures collectives et déclaration de créance](#procédures-collectives-et-déclaration-de-créance)
14. [Import de données et ERP BLG](#import-de-données-et-erp-blg)
15. [Rapports et prévisions](#rapports-et-prévisions)
16. [Tâches, filtres enregistrés, actions groupées](#tâches-filtres-enregistrés-actions-groupées)
17. [API REST, webhooks et agenda iCal](#api-rest-webhooks-et-agenda-ical)
18. [Messagerie interne](#messagerie-interne)
19. [Notes client](#notes-client)
20. [Analyse de confiance (IA 1min.ai)](#analyse-de-confiance-ia-1minai)
21. [Accès en lecture seule (agences)](#accès-en-lecture-seule-agences)
22. [Notifications](#notifications)
23. [Sécurité](#sécurité)
24. [RGPD](#rgpd)
25. [Structure du projet](#structure-du-projet)
26. [Tests et intégration continue](#tests-et-intégration-continue)
27. [Sauvegarde et maintenance](#sauvegarde-et-maintenance)
28. [Dépannage](#dépannage)

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
- **Journal des paiements** (le montant récupéré en est la somme), **échéanciers** avec imputation
  automatique, **pénalités de retard** calculées chaque jour (intérêts + indemnité forfaitaire).
- **Courriers types** (relance, mise en demeure, échéancier, déclaration de créance) avec fusion de
  variables, impression / Word / PDF, **suivi d'envoi et d'accusé de réception**.
- **Workflow par statut** : transitions autorisées et pré-requis (mise en demeure, AR reçu, échéancier…).
- **Surveillance BODACC** planifiée et **délai de déclaration de créance** en cas de procédure collective.
- **Import CSV / Excel** (clients, factures impayées, règlements) et **synchronisation avec l'ERP BLG**
  par dépôt de fichiers, avec rapprochement automatique des règlements.
- **Rapports** : balance âgée, recouvrement par agence et par mois, prévisions d'encaissement,
  **rapport hebdomadaire par e-mail** avec classeur Excel.
- **Tâches** assignables, **filtres enregistrés**, **actions groupées**, tri multi-critères,
  **mentions @** dans les notes, détection de **doublons**.
- **API REST** à jetons, **webhooks** signés, **agenda iCal** (Outlook, Google, Apple).
- **Sécurité renforcée** : double authentification (TOTP), limitation de tentatives, en-têtes CSP/HSTS,
  contrôle du contenu des fichiers, antivirus ClamAV optionnel.
- **RGPD** : registre des traitements, durées de conservation, export et anonymisation d'un contact.
- **Présentation intégrée** : un diaporama (menu « Présentation », page `/presentation`) qui
  décrit les fonctionnalités du panel — navigation clavier, plein écran, vue d'ensemble,
  impression/export PDF.

---

## Prérequis

- **PHP 8.1+** avec les extensions `pdo`, `pdo_mysql`, `json`, `mbstring`, `openssl`.
- Recommandées : `zip` (exports et imports Excel), `curl` (API entreprises, webhooks), `fileinfo`
  (contrôle du contenu des pièces jointes), `dom` (nettoyage des modèles de courriers).
- **MySQL 8** ou **MariaDB 10.4+**.
- Un serveur web (Apache, Nginx, LiteSpeed/CyberPanel…) qui pointe vers le dossier `public/`.
- Facultatif : **Composer** (PHPMailer pour le SMTP ; `dompdf/dompdf` pour de vrais fichiers PDF).

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

```bash
git pull
composer install --no-dev --optimize-autoloader   # si vous utilisez Composer
php cron/migrate.php                              # applique les évolutions de la base
```

> Cette version requiert **PHP 8.1 ou plus** et ajoute de nouvelles tables (paiements, échéances,
> courriers, tâches, API…). Faites une **sauvegarde de la base** avant la première mise à jour.

> Pour forcer une reprise (droits MySQL corrigés, base restaurée, etc.), ouvrez
> **Administration → Base de données** et cliquez sur « Appliquer maintenant ».

> Pour une **installation neuve**, l'installeur importe `database/schema.sql` puis applique lui-même
> toutes les migrations.

### Migrations

Le suivi est géré par `app/Core/Migrator.php` (table `migrations`). Deux modes, réglés par
`app.auto_migrate` dans `config/config.php` :

| Mode | Fonctionnement |
|---|---|
| `true` (valeur par défaut si la clé est absente, donc **mises à jour compatibles**) | les migrations en attente sont appliquées automatiquement au chargement d'une page. Pratique, mais une requête web peut modifier la structure de la base. |
| `false` (**recommandé en production**, valeur du nouveau `config.example.php`) | aucune migration depuis une requête web : lancez `php cron/migrate.php` après chaque mise à jour (`--status` pour voir l'état). Une bannière prévient les administrateurs si des migrations sont en attente. |

**Ajouter une évolution** : déposez un fichier dans `database/migrations/` nommé `AAAA-MM-JJ-NN-description.sql`
ou `.php`. Les migrations `.php` retournent une fonction `(PDO $pdo): void` et disposent des aides
`App\Core\Schema` (`ajouterColonne`, `ajouterIndex`…), ce qui évite les procédures stockées.

| Règle | Détail |
|---|---|
| Un fichier = une évolution | l'ordre alphabétique fait foi. |
| SQL simple (`.sql`) | instructions séparées par `;`. **Pas de `DELIMITER` ni de procédures stockées.** |
| Idempotent | la migration peut être relancée après une erreur partielle (`CREATE TABLE IF NOT EXISTS`, `Schema::ajouterColonne`, `ON DUPLICATE KEY UPDATE`…). |
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
| `app.key` | Clé qui chiffre les secrets en base (2FA, webhooks). **Ne plus la modifier** une fois l'application utilisée. |
| `app.auto_migrate` | Migrations automatiques au chargement des pages (voir [Migrations](#migrations)). |
| `security.*` | Longueur minimale du mot de passe, verrouillage, durée de session, HTTPS forcé. |
| `security.force_https` | Redirige http → https (hors localhost) et envoie HSTS. |
| `security.trusted_proxies` | IP des reverse-proxies / CDN dont on accepte `X-Forwarded-For`. **À renseigner derrière un proxy**, sinon toutes les visites semblent venir du proxy (limitation de tentatives faussée). |
| `security.csp` | `enforce` (défaut), `report-only` ou `off` : politique de sécurité du contenu. |
| `integrations.*` | Autorise (déconseillé) les webhooks en http ou vers le réseau privé. |
| `apis.recherche_entreprises` | API de l'État (gratuite, activée par défaut). |
| `apis.bodacc` | API BODACC (annonces légales, gratuite, activée par défaut). |
| `apis.pappers.api_token` | Clé Pappers (optionnelle). |
| `apis.oneminai.api_key` | Clé API 1min.ai pour l'analyse de confiance (optionnelle). |
| `mail.*` | Paramètres SMTP (optionnels). |

De nombreux réglages (nom de l'application, délais de relance, source société, SMTP,
jeton Pappers…) sont aussi modifiables depuis **Paramètres** dans l'application, et sont
stockés en base (table `settings`) : ils priment sur `config.php` lorsqu'ils sont renseignés.

---

## Tâches planifiées (cron)

Le script **`cron/quotidien.php`** regroupe les traitements quotidiens. À exécuter **une fois par jour** :

| Tâche | Rôle |
|---|---|
| `migrations` | applique les migrations de base de données en attente |
| `blg` | importe les exports déposés par l'ERP BLG (factures impayées, règlements) |
| `penalites` | recalcule les pénalités de retard des litiges en mode automatique |
| `rappels` | notifications internes + e-mail récapitulatif aux administrateurs |
| `bodacc` | surveille les procédures collectives des clients ayant un litige en cours |
| `rapport` | envoie le rapport hebdomadaire (au jour configuré) |
| `rgpd` | anonymise / purge selon les durées de conservation |
| `entretien` | nettoie les compteurs de limitation et les fichiers d'import temporaires |

```bash
# Test manuel (simulation : n'écrit rien quand c'est possible)
php /var/www/litiges.locavente.fr/cron/quotidien.php --dry-run

# Une seule tâche
php /var/www/litiges.locavente.fr/cron/quotidien.php --only=penalites,rappels
```

Exemple de crontab :

```cron
# Traitements quotidiens à 7 h
0 7 * * * /usr/bin/php /var/www/litiges.locavente.fr/cron/quotidien.php --quiet
# Envoi des webhooks en attente / rejeu des échecs (toutes les 5 minutes)
*/5 * * * * /usr/bin/php /var/www/litiges.locavente.fr/cron/webhooks.php --quiet
```

Sur CyberPanel, ces entrées s'ajoutent via *Cron Jobs*. Utilisez le **chemin absolu** de PHP (`/usr/bin/php`) et
un **chemin absolu** vers le script.

Autres scripts : `cron/rappels.php` (rappels seuls, ancien point d'entrée toujours valable),
`cron/rapport.php [--force]` (rapport hebdomadaire immédiat), `cron/migrate.php [--status]`.

Types de rappels générés : relance à effectuer, promesse non honorée, échéance proche, **échéance de plan de paiement
impayée**, **tâches en retard**, **délai de déclaration de créance** (J-30, 15, 7, 3, 1 et jour J) et
**courrier sans accusé de réception** après 10 jours.

Journaux : `storage/logs/cron-quotidien.log`, `storage/logs/cron-rappels.log`, `storage/logs/app-AAAA-MM.log` (erreurs).

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

## Recouvrement : paiements, échéancier, pénalités

Depuis la fiche d'un litige, onglet **Paiements & échéancier** :

- **Paiements** : chaque règlement reçu est saisi (date, montant, mode, référence). Le **montant récupéré** du litige
  est toujours la somme des paiements ; il n'est plus saisi à la main. Les soldes déjà saisis avant cette version ont
  été repris sous la forme d'un paiement « Reprise du solde ». Quand l'encours tombe à zéro, le responsable est
  invité à clôturer le litige.
- **Échéancier** : nombre d'échéances, périodicité (hebdomadaire, tous les 15 jours, mensuelle), premier versement
  facultatif. Les centimes d'arrondi sont reportés sur la dernière échéance. Les paiements enregistrés *après* la
  création du plan sont imputés dans l'ordre sur les échéances (payée / partielle / en retard) ; la prochaine
  relance devient la première échéance impayée, et une alerte est émise en cas d'échéance manquée.
- **Pénalités de retard** : intérêts simples au taux annuel (paramètre global ou taux propre au litige) sur le capital
  restant dû, calculés jour par jour (base 365) en tenant compte de la date de chaque paiement, plus l'indemnité
  forfaitaire de recouvrement (40 € par défaut). Avec le « calcul automatique », le cron met le montant à jour chaque
  jour et l'historique conserve les variations. Le point de départ est la date d'échéance, à défaut la date de facture,
  à défaut la date d'ouverture.

> Les taux et indemnités par défaut sont des **valeurs de départ à faire valider** par votre conseil
> (art. L.441-10 et D.441-5 du Code de commerce).

---

## Courriers et suivi d'envoi

*Administration → Modèles de courriers* : quatre modèles sont fournis (relance amiable, mise en demeure, confirmation
d'échéancier, déclaration de créance). Ils utilisent des variables `{{client.raison_sociale}}`,
`{{litige.encours}}`, `{{echeancier.tableau}}`, `{{penalites.detail}}`… (liste complète dans l'éditeur). Le HTML saisi
est **nettoyé par liste blanche** (aucun script ni gestionnaire d'événement).

> Les modèles fournis sont des **exemples** : faites-les valider par votre conseil avant usage.

Dans l'onglet **Courriers** d'un litige : choisir un modèle, relire le courrier généré, puis l'**imprimer**, le
télécharger en **Word** ou, si `dompdf/dompdf` est installé (`composer require dompdf/dompdf`), en **PDF**. Chaque
courrier est numéroté (`COUR-AAAA-NNNN`), archivé dans les documents du litige et consigné dans le suivi.

**Suivi d'envoi et accusé de réception** : mode d'envoi (LRAR papier, LRE, huissier, e-mail, main propre), numéro de
suivi, date d'envoi, date et statut de l'AR. Pour une mise en demeure, la réception de l'AR fixe la prochaine relance à
l'expiration du délai accordé ; l'absence d'AR après 10 jours déclenche une alerte.

> L'application **suit** les envois recommandés mais ne les **transmet pas** : aucun prestataire de LRE ou de signature
> électronique (AR24, Maileva, Yousign…) n'est branché. Un prestataire pourrait l'être via l'API et les webhooks.

---

## Workflow par statut

*Administration → Statuts & finalités → Workflow* (par statut) :

- **Passages autorisés** depuis ce statut (rien de coché = tout est permis, comportement historique) ;
- **Pré-requis** pour entrer dans le statut : au moins une relance, une mise en demeure (relance ou courrier généré),
  un courrier envoyé, un accusé de réception reçu, une pièce jointe, un échéancier, un paiement, un gestionnaire affecté.

Les règles s'appliquent partout (fiche, édition, actions groupées, API, imports). Un administrateur peut cocher
« Forcer » ; le forçage est tracé dans l'audit. Aucune règle n'est configurée par défaut.

---

## Procédures collectives et déclaration de créance

Le cron interroge chaque jour le **BODACC** pour un lot de clients ayant un litige en cours. Si une procédure collective
apparaît (nouvelle annonce), l'application : met à jour la fiche client, consigne l'événement dans chaque litige ouvert,
**notifie** les responsables et les administrateurs, et émet le webhook `procedure_collective.detectee`.

La fiche litige affiche alors un bandeau avec le **délai de déclaration de créance** (2 mois après la publication au BODACC
par défaut — art. R.622-24 du Code de commerce — **à vérifier avec votre conseil**, 4 mois hors métropole), le bouton
« Générer la déclaration de créance » et « Marquer comme déclarée ». Des rappels sont envoyés à J-30, J-15, J-7, J-3, J-1 et J.

---

## Import de données et ERP BLG

*Menu Import de données* : fichiers **CSV** (séparateur et encodage détectés, y compris Windows-1252), **TXT** ou
**Excel (.xlsx)**. Trois types : *clients*, *litiges (factures impayées)* et *règlements*. Étapes : dépôt du fichier,
**correspondance des colonnes** (proposée automatiquement d'après les intitulés), **simulation** sans écriture, import,
rapport ligne par ligne et historique.

- Les litiges sont rapprochés par (client, n° de facture) pour éviter les doublons ; l'option « mettre à jour » actualise
  le montant et l'échéance.
- Les règlements sont rapprochés par n° de facture et enregistrés dans le journal des paiements ; un règlement déjà
  importé n'est **jamais compté deux fois**.

**ERP BLG (blgCloud)** : aucune API publique de blgCloud n'est documentée ; l'intégration repose donc sur les **exports
CSV/Excel** de l'ERP :

1. Dans *Paramètres → ERP BLG*, indiquez le **dossier surveillé** et activez la synchronisation.
2. Déposez-y les exports (export planifié, copie, SFTP ou dossier partagé). Le nom du fichier détermine son type :
   `*client*`, `*facture*`/`*impaye*`/`*echeance*`, `*reglement*`/`*paiement*`/`*encaissement*`.
3. À la première utilisation, ouvrez le fichier dans *Import de données*, ajustez la correspondance des colonnes puis
   cliquez sur **Enregistrer comme profil BLG** : le cron quotidien appliquera ce profil. Les fichiers traités sont
   archivés dans `archive/` ; un même fichier n'est jamais importé deux fois.

Si BLG fournit une API, le point d'extension est `App\Services\Import\BlgService` (le moteur d'import reste le même).

---

## Rapports et prévisions

*Menu Rapports & prévisions* : **balance âgée** (non échu, 0-30, 31-60, 61-90, +90 jours) par agence et par client avec
export CSV/Excel, **ancienneté moyenne pondérée** des encours, **recouvrement par agence et par mois** (mis en
recouvrement vs encaissé), **prévisions d'encaissement** sur 8 semaines (échéances de plans de paiement + promesses). Le
tableau de bord affiche les tâches du jour, les encaissements attendus et les délais de créance urgents.

Un **rapport hebdomadaire** (synthèse + classeur Excel des litiges ouverts) peut être envoyé par e-mail
(*Paramètres → Rapport hebdomadaire*, SMTP requis).

> Le DSO au sens strict nécessite le chiffre d'affaires facturé, que l'application ne détient pas : il n'est pas calculé.

---

## Tâches, filtres enregistrés, actions groupées

- **Tâches** (menu *Tâches* et onglet de chaque litige) : titre, responsable, échéance, priorité ; notification à
  l'assignation, rappel quotidien en cas de retard.
- **Filtres enregistrés** sur la liste des litiges (personnels ou partagés avec l'équipe) ; **tri sur deux critères**.
- **Actions groupées** : cocher des litiges puis changer le gestionnaire, la priorité ou le statut (le workflow s'applique).
- **Exports** de la liste : CSV, Excel, PDF/impression (tous les résultats filtrés, pas seulement la page affichée).
- **Mentions** : `@identifiant` dans une note de litige ou de client notifie la personne citée.
- **Doublons** : à la création, un avertissement apparaît si le client a déjà un litige avec la même facture (ou le même
  contrat et le même montant) ; il faut cocher « Créer quand même ».

---

## API REST, webhooks et agenda iCal

- **API REST** (`/api/v1`) avec **jetons** créés dans *Administration → API & webhooks* : lecture et création de
  litiges, paiements (idempotents), relances, changement de statut. Documentation : [docs/api.md](docs/api.md).
- **Webhooks** signés (HMAC-SHA256) sur les événements : litige créé / modifié / changement de statut / clôturé,
  paiement, relance, échéance manquée, procédure collective. Réessais espacés, désactivation après 20 échecs, adresses du
  réseau interne refusées. Cron : `cron/webhooks.php`.
- **Agenda iCal** : *Mon profil → Agenda* fournit une adresse privée à ajouter dans Outlook, Google Agenda ou Apple
  Calendrier (relances, échéances, paiements attendus, promesses, tâches). La synchronisation se fait par abonnement
  (lecture seule, rafraîchie par le logiciel d'agenda).

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

Le **modèle** se choisit dans le même écran (champ `oneminai_model`, suggéré par une liste). Il
s'agit d'un identifiant exact de l'API compatible OpenAI de 1min.ai — la liste de référence est
fournie par **`GET https://api.1min.ai/openai/v1/models`**. Les modèles de raisonnement
(GPT‑5/GPT‑6, `o3`, DeepSeek, GLM) **refusent** `temperature` ; le service l'omet automatiquement
pour eux.

Le bouton **Tester la connexion** valide la clé (sans lancer de génération) et vérifie que le
modèle est accessible. En cas d'échec, le message d'erreur réel renvoyé par 1min.ai (statut HTTP +
message) s'affiche et est journalisé dans `storage/logs/ia.log` (et dans l'audit :
`ia_analyse_erreur` / `ia_test_connexion`).

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

### Sécuriser un partage

Depuis *Accès lecture seule* (colonne « Sécurité / journal ») :

- **Expiration automatique** : par défaut 30 jours (*Paramètres → Sécurité avancée*), « sans expiration » restant
  possible à la création ; prolongation possible.
- **Mot de passe facultatif** (8 caractères minimum), demandé après le code ou le lien.
- **Documents choisis un par un** : le partage n'affiche que les pièces que vous avez marquées « Rendre public » sur
  la fiche du litige, et seulement si l'option « Afficher les documents » est cochée pour ce partage.
- **Journal des consultations** : accès par code/lien, litiges consultés, documents téléchargés, adresse IP, échecs de
  mot de passe. Trop d'échecs bloquent l'adresse IP quelques minutes.

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
- **Double authentification (TOTP)** compatible Google/Microsoft Authenticator, Authy, 1Password : secret chiffré en base
  (AES-256-GCM), protection contre le rejeu, **8 codes de secours** à usage unique, réinitialisation par un administrateur.
  Politique réglable : facultative, obligatoire pour les administrateurs ou pour tous (*Paramètres → Sécurité avancée*).
  Le secret s'enrôle en le saisissant (ou via le lien `otpauth://` sur mobile) : aucun QR code n'est généré.
- **Limitation de tentatives** (table `rate_limits`) sur la connexion, le code 2FA, la réinitialisation de mot de passe,
  les codes et liens de partage public, l'API et le flux iCal. L'adresse IP n'est lue dans `X-Forwarded-For` que depuis
  un proxy déclaré dans `security.trusted_proxies`.
- **Verrouillage temporaire** du compte après plusieurs échecs de connexion.
- La réinitialisation de mot de passe **n'affiche plus jamais le lien** à l'écran (il n'est envoyé que par e-mail) ;
  sans SMTP, un administrateur réinitialise le mot de passe depuis l'administration.
- Jetons **CSRF** sur tous les formulaires POST (sauf l'API, authentifiée par jeton Bearer).
- Requêtes SQL **préparées** partout (PDO), échappement HTML systématique (`e()`).
- **En-têtes de sécurité** : Content-Security-Policy avec *nonce* (aucun script inline non signé), HSTS, `X-Frame-Options`,
  `Referrer-Policy`, `Permissions-Policy`. Si une page s'affiche mal après la mise à jour, passez `security.csp` à
  `report-only` le temps de corriger.
- **Sessions** régénérées à la connexion, expiration par inactivité, cookies `HttpOnly` + `SameSite`.
- **Pièces jointes** : extension autorisée, taille, **type réel du contenu (finfo) cohérent avec l'extension**, jamais
  d'extension exécutable, stockage **hors racine web**, téléchargement en pièce jointe, **droits du module** du litige ou
  du client vérifiés, antivirus **ClamAV** facultatif (*Paramètres → Sécurité avancée*).
- **Webhooks** : adresses internes refusées (anti-SSRF), connexion épinglée sur l'IP validée, aucune redirection suivie.
- **Exports** CSV/Excel : neutralisation des formules (`=`, `+`, `-`, `@`).
- **Journal d'audit** complet des actions sensibles et **journal d'erreurs** (`storage/logs/app-AAAA-MM.log`).
- Recommandé : servir le site en **HTTPS**, supprimer `public/install.php`, restreindre l'accès à `config/` et
  `storage/`, et désactiver les migrations automatiques en production (`app.auto_migrate = false`).

---

## RGPD

*Administration → RGPD* : **registre des traitements** pré-rempli (modifiable), **durées de conservation**
(anonymisation des contacts et, en option, suppression des litiges clos au-delà de N mois, purge du journal d'audit) avec
simulation avant exécution, exécutée aussi par le cron. Sur la fiche client, chaque contact dispose d'un **export RGPD**
(JSON) et d'un bouton **Anonymiser** (droit à l'effacement).

> Les durées proposées (ex. 60 mois) et les bases légales du registre sont des **points de départ** à valider avec votre
> délégué à la protection des données ou votre conseil.

---

## Structure du projet

```
app/
├── bootstrap.php            # Amorçage (autoload, config, en-têtes, base, session, migrations)
├── helpers.php              # Fonctions utilitaires (e(), url(), money(), csp_nonce()…)
├── Controllers/             # Contrôleurs HTTP, minces (une classe par domaine)
├── Core/                    # Noyau : Router, Database, Auth, Totp, Crypto, RateLimiter, SecurityHeaders, Migrator…
├── Enums/                   # Types de litige, priorités, rôles, types d'action, modes de paiement
├── Exceptions/              # Erreurs métier (validation, doublon, workflow)
├── Models/                  # Modèles (accès base de données)
├── Services/                # Règles métier : LitigeService, PaiementService, EcheanceService, PenaltyService,
│   │                        #   CourrierService, WorkflowService, WebhookService, ReportService, IcalService…
│   ├── Api/                 # Clients API Recherche d'entreprises / BODACC / Pappers / 1min.ai
│   ├── Calcul/              # Calculs purs et testés : pénalités, échéancier, balance âgée
│   └── Import/              # Import CSV/Excel et synchronisation BLG
├── Resources/referentiels/  # Libellés officiels (NAF, catégories juridiques, sections)
└── Views/                   # Gabarits PHP (layouts, pages, partials)

config/
├── config.example.php       # Modèle de configuration
└── config.php               # Configuration réelle (généré, non versionné)

cron/
├── quotidien.php            # Traitements quotidiens (rappels, pénalités, BODACC, BLG, RGPD…)
├── webhooks.php             # Envoi / rejeu des webhooks
├── rapport.php              # Rapport hebdomadaire
├── migrate.php              # Migrations en ligne de commande
└── rappels.php              # Rappels seuls (ancien point d'entrée)

database/
├── schema.sql               # Schéma de base (l'installeur applique ensuite les migrations)
└── migrations/              # Évolutions .sql et .php, idempotentes

docs/api.md                  # Documentation de l'API REST et des webhooks
tests/                       # Tests unitaires PHPUnit

public/                      # ← RACINE WEB
├── index.php                # Front controller (routes)
├── install.php              # Installeur (à supprimer après installation)
└── assets/                  # CSS / JS

storage/                     # Données non publiques (uploads, logs, imports)
```

---

## Tests et intégration continue

```bash
composer install
vendor/bin/phpunit            # tests unitaires (aucune base de données requise)
vendor/bin/phpstan analyse    # analyse statique
```

Les tests couvrent les calculs (pénalités, échéancier, balance âgée), la 2FA (vecteurs RFC 6238), l'analyse des imports,
le nettoyage HTML, le chiffrement, la détection d'adresses privées (SSRF), la cohérence extension / contenu des fichiers,
la résolution d'IP derrière un proxy, l'iCal et les exports Excel. La CI GitHub Actions (`.github/workflows/ci.yml`)
vérifie la syntaxe et lance les tests sur PHP 8.1, 8.2 et 8.3 ; PHPStan y est informatif (niveau 1).

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
| « L'analyse IA a échoué » | Cliquez sur **Tester la connexion** dans *Paramètres → Intelligence artificielle* : le message exact (clé invalide, modèle inconnu, quota, réseau) s'affiche. Détails : `storage/logs/ia.log` et le journal d'audit. |
| Erreur IA « HTTP 400 » sur un modèle récent | Modèle de raisonnement (GPT‑5/6, o3, DeepSeek, GLM) : la limite de jetons est élargie automatiquement ; vérifiez surtout que l'identifiant du modèle est exact. |
| URL d'assets incorrecte | Ajustez `app.url` (sans slash final) dans `config.php`. |
| Bannière « migrations en attente » | `app.auto_migrate` est à `false` : lancez `php cron/migrate.php` après la mise à jour. |
| Une page s'affiche mal / un bouton ne répond plus après la mise à jour | Probable blocage par la politique CSP : ouvrez la console du navigateur, puis mettez `security.csp` à `report-only` en attendant correction. |
| Boucle de redirection http ↔ https | Votre proxy / CDN ne transmet pas `X-Forwarded-Proto` : passez `security.force_https` à `false` et laissez le proxy faire la redirection. |
| Tout le monde est bloqué en même temps (« Trop de tentatives ») | Derrière un proxy, déclarez son IP dans `security.trusted_proxies` pour que la vraie IP du visiteur soit utilisée. |
| Impossible de se connecter après avoir perdu son téléphone (2FA) | Utilisez un code de secours, ou demandez à un administrateur *Utilisateurs → Réinit. 2FA*. |
| Export Excel ou import `.xlsx` indisponible | Activez l'extension PHP `zip`. |
| Le PDF d'un courrier n'est pas proposé | `composer require dompdf/dompdf` ; sinon utilisez la version imprimable puis « Enregistrer au format PDF ». |
| Les webhooks restent « en attente » | Planifiez `cron/webhooks.php` (toutes les 5 minutes) et vérifiez que l'URL est publique et en https. |
| L'API répond 401 avec un jeton valide | Le serveur supprime l'en-tête `Authorization` : la règle de `public/.htaccess` le rétablit sous Apache ; sous Nginx, ajoutez `fastcgi_param HTTP_AUTHORIZATION $http_authorization;`. |

---

© Locavente — Gestion des litiges. Version 1.1.
