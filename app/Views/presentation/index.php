<?php
/**
 * Diaporama de présentation des fonctionnalités (page autonome, plein écran).
 * Rendu sans le gabarit de l'application : View::render(..., null).
 *
 * @var string $titre
 * @var string $appName
 */
$appName = $appName ?? 'Gestion des litiges';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titre ?? 'Présentation') ?> · <?= e($appName) ?></title>
    <link rel="stylesheet" href="<?= asset('css/presentation.css') ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚖️</text></svg>">
    <noscript><style>
        body.deck-body { overflow: auto; }
        .deck { position: static; overflow: visible; }
        .slide { position: static; opacity: 1; visibility: visible; transform: none; min-height: auto; padding: 40px 22px; overflow: visible; }
        .deck-bar, .deck-dots, .deck-hint, .deck-progress { display: none !important; }
    </style></noscript>
</head>
<body class="deck-body">

<div class="deck-progress"><span></span></div>

<nav class="deck-dots" aria-label="Diapositives"></nav>

<div class="deck-hint">← → naviguer · <b>F</b> plein écran · <b>O</b> sommaire</div>

<div class="overview" role="dialog" aria-label="Sommaire du diaporama">
    <h2>Sommaire</h2>
    <div class="overview-grille"></div>
</div>

<div class="deck">

    <!-- 1 — Couverture -->
    <section class="slide slide--cover" data-title="Couverture">
        <div class="slide-inner">
            <span class="ruban">Présentation des fonctionnalités</span>
            <div class="logo">⚖️</div>
            <h1>Gestion des litiges</h1>
            <p class="accroche">Location de matériel BTP, de véhicules et de machines de chantier.</p>
            <p class="signature"><?= e($appName) ?> · Version 1.0 · <?= date('Y') ?></p>
        </div>
    </section>

    <!-- 2 — Sommaire -->
    <section class="slide" data-title="Sommaire">
        <div class="slide-inner">
            <span class="kicker">Plan</span>
            <h2>Ce que fait le panel</h2>
            <p class="sous">Une plateforme unique pour suivre, relancer et recouvrer les litiges — de l'ouverture du dossier jusqu'à la clôture.</p>
            <div class="grille grille-3">
                <div class="carte"><span class="ico">▦</span><h3>Tableau de bord</h3><p>Encours, recouvrement, statistiques et tendances.</p></div>
                <div class="carte"><span class="ico">⚖</span><h3>Litiges</h3><p>Dossiers, statuts, finalités, relances et documents.</p></div>
                <div class="carte"><span class="ico">🏢</span><h3>Clients &amp; contacts</h3><p>Fiches enrichies, solvabilité, inspection d'entreprise.</p></div>
                <div class="carte"><span class="ico">💬</span><h3>Messagerie</h3><p>1-à-1, groupes et canaux d'agence.</p></div>
                <div class="carte"><span class="ico">🔗</span><h3>Partage lecture seule</h3><p>Codes d'accès et liens publics pour les agences.</p></div>
                <div class="carte"><span class="ico">🛠</span><h3>Administration</h3><p>Rôles, paramètres, audit et migrations automatiques.</p></div>
            </div>
        </div>
    </section>

    <!-- 3 — Objectifs -->
    <section class="slide" data-title="Pourquoi cet outil">
        <div class="slide-inner">
            <span class="kicker">Objectifs</span>
            <h2>Centraliser et sécuriser le recouvrement</h2>
            <div class="duo">
                <ul class="liste">
                    <li><strong>Un seul endroit</strong> pour tous les litiges, <span class="douce">au lieu de tableurs éparpillés.</span></li>
                    <li><strong>Ne rien oublier</strong> : relances, échéances et promesses de paiement suivies automatiquement.</li>
                    <li><strong>Décider vite</strong> : solvabilité et analyse de confiance du client en un coup d'œil.</li>
                    <li><strong>Ouvrir aux agences</strong> en lecture seule, sans créer de compte.</li>
                    <li><strong>Tracer</strong> toutes les actions dans un journal d'audit.</li>
                </ul>
                <div class="grille grille-2">
                    <div class="carte"><span class="ico">⚡</span><h3>Rapide</h3><p>Données client pré-remplies depuis les API publiques.</p></div>
                    <div class="carte"><span class="ico">🔒</span><h3>Sûr</h3><p>Rôles, CSRF, mots de passe chiffrés, pièces hors racine web.</p></div>
                    <div class="carte"><span class="ico">🆓</span><h3>Économique</h3><p>Sources de données gratuites ; l'IA reste optionnelle.</p></div>
                    <div class="carte"><span class="ico">📈</span><h3>Pilotable</h3><p>Indicateurs de recouvrement en temps réel.</p></div>
                </div>
            </div>
        </div>
    </section>

    <!-- 4 — Tableau de bord -->
    <section class="slide" data-title="Tableau de bord">
        <div class="slide-inner">
            <span class="kicker">Pilotage</span>
            <h2>Tableau de bord &amp; statistiques</h2>
            <p class="sous">Tout l'état du recouvrement sur un écran, actualisé à chaque visite.</p>
            <div class="stats">
                <div class="stat"><div class="val">€</div><div class="lib">Encours total</div></div>
                <div class="stat"><div class="val">%</div><div class="lib">Taux de recouvrement</div></div>
                <div class="stat"><div class="val">◷</div><div class="lib">Évolution mensuelle</div></div>
                <div class="stat"><div class="val">🏆</div><div class="lib">Top clients</div></div>
            </div>
            <div class="note bleu">
                <span>📊</span>
                <div>Répartition par statut, encours par <strong>agence</strong> et par <strong>gestionnaire</strong>, litiges à relancer aujourd'hui — et <strong>export CSV</strong> à tout moment.</div>
            </div>
        </div>
    </section>

    <!-- 5 — Litiges -->
    <section class="slide" data-title="Gestion des litiges">
        <div class="slide-inner">
            <span class="kicker">Cœur métier</span>
            <h2>Gestion des litiges</h2>
            <div class="duo">
                <div class="etapes">
                    <div class="etape"><span class="num">1</span><div><b>Ouverture du dossier</b><span>Référence automatique <em>LIT-AAAA-NNNN</em>, client, agence, objet et type.</span></div></div>
                    <div class="etape"><span class="num">2</span><div><b>Suivi &amp; relances</b><span>Montants (principal, pénalités, récupéré), échéance, priorité, responsable.</span></div></div>
                    <div class="etape"><span class="num">3</span><div><b>Changement d'étape</b><span>Nouveau, en cours, mise en demeure… avec historique complet.</span></div></div>
                    <div class="etape"><span class="num">4</span><div><b>Clôture</b><span>Finalité : règlement total, irrécouvrable, prescription…</span></div></div>
                </div>
                <div>
                    <div style="margin-bottom:14px">
                        <span class="pastille bleu">Priorité</span>
                        <span class="pastille orange">Échéance proche</span>
                        <span class="pastille rouge">Mise en demeure</span>
                        <span class="pastille vert">Récupéré</span>
                    </div>
                    <ul class="liste liste-bleu">
                        <li>Notes internes et <strong>pièces jointes</strong> par litige.</li>
                        <li>Partage public en lecture seule (<code>/p/…</code>).</li>
                        <li>Statuts et finalités <strong>personnalisables</strong> par l'administrateur.</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- 6 — Statuts & finalités -->
    <section class="slide" data-title="Statuts &amp; finalités">
        <div class="slide-inner">
            <span class="kicker">Référentiels</span>
            <h2>Statuts, finalités &amp; priorités</h2>
            <p class="sous">Chaque structure travaille avec ses propres étapes : les listes se paramètrent sans toucher au code.</p>
            <div class="grille grille-3">
                <div class="carte"><span class="ico">🔵</span><h3>Statuts</h3><p>Nouveau, en cours, mise en demeure, contentieux… Indicateur « clôturant » par étape.</p></div>
                <div class="carte"><span class="ico">🏁</span><h3>Finalités de clôture</h3><p>Règlement total, partiel, irrécouvrable, prescription, geste commercial.</p></div>
                <div class="carte"><span class="ico">⚠️</span><h3>Priorités &amp; types</h3><p>Niveaux de priorité et types de litige adaptés à l'activité.</p></div>
            </div>
            <div class="note">🛠️ Réglable depuis <strong>Paramètres → Statuts &amp; finalités</strong> : ajout, renommage, ordre et couleur.</div>
        </div>
    </section>

    <!-- 7 — Relances & rappels -->
    <section class="slide" data-title="Relances &amp; rappels automatiques">
        <div class="slide-inner">
            <span class="kicker">Suivi</span>
            <h2>Relances &amp; rappels automatiques</h2>
            <div class="duo">
                <ul class="liste">
                    <li><strong>Historique des contacts</strong> : appel, e-mail, courrier, mise en demeure, promesse de paiement…</li>
                    <li><strong>Résultat, montant promis</strong> et <strong>prochaine action</strong> datée.</li>
                    <li>Chaque gestionnaire voit <strong>ses</strong> relances à faire.</li>
                </ul>
                <div class="grille grille-1">
                    <div class="etape"><span class="num">🔔</span><div><b>Relance due</b><span>La date de prochaine relance est atteinte.</span></div></div>
                    <div class="etape"><span class="num">🤝</span><div><b>Promesse dépassée</b><span>Un engagement de paiement n'a pas été honoré.</span></div></div>
                    <div class="etape"><span class="num">⏳</span><div><b>Échéance proche</b><span>Le délai du litige approche (seuil configurable).</span></div></div>
                </div>
            </div>
            <div class="note bleu">⏱️ Généré <strong>chaque jour</strong> par la tâche planifiée, en notifications internes et — si le SMTP est configuré — par un e-mail récapitulatif aux administrateurs.</div>
        </div>
    </section>

    <!-- 8 — Clients -->
    <section class="slide" data-title="Clients &amp; solvabilité">
        <div class="slide-inner">
            <span class="kicker">Clients</span>
            <h2>Fiches clients enrichies</h2>
            <p class="sous">Saisissez un nom ou un SIREN : l'essentiel de la fiche se remplit tout seul.</p>
            <div class="grille grille-3">
                <div class="carte"><span class="ico">🏢</span><h3>Identité</h3><p>SIREN, SIRET, TVA, forme juridique, code APE, adresse, effectif, capital…</p></div>
                <div class="carte"><span class="ico">📉</span><h3>Solvabilité</h3><p>Score interne calculé à partir des données publiques et de l'encours maison.</p></div>
                <div class="carte"><span class="ico">📇</span><h3>Contacts &amp; documents</h3><p>Interlocuteurs multiples et pièces jointes rattachées à la fiche.</p></div>
            </div>
            <div class="note">🤖 Un bouton <strong>Analyser</strong> lance l'analyse de confiance par IA (voir plus loin).</div>
        </div>
    </section>

    <!-- 9 — Inspection -->
    <section class="slide" data-title="Inspection d'entreprise">
        <div class="slide-inner">
            <span class="kicker">Avant de s'engager</span>
            <h2>Inspecter une entreprise — sans créer de client</h2>
            <div class="duo">
                <ul class="liste liste-bleu">
                    <li>Recherche par <strong>nom, SIREN ou SIRET</strong>.</li>
                    <li><strong>Données financières par exercice</strong>, actuelles et historiques.</li>
                    <li><strong>Procédures collectives</strong> et annonces BODACC <strong>groupées par année</strong>.</li>
                    <li><strong>Dirigeants</strong> et autres entreprises liées.</li>
                    <li>Score de solvabilité interne.</li>
                </ul>
                <div class="carte" style="text-align:center">
                    <span class="ico">🔎</span>
                    <h3>Évaluer en amont</h3>
                    <p>Ouvrez le dossier d'une entreprise <strong>prospect</strong> ou d'un confrère, puis, si besoin, un clic la transforme en <strong>client</strong>.</p>
                    <div style="margin-top:12px"><span class="pastille bleu">Menu : Inspecter une entreprise</span></div>
                </div>
            </div>
        </div>
    </section>

    <!-- 10 — Sources de données -->
    <section class="slide" data-title="Sources de données">
        <div class="slide-inner">
            <span class="kicker">Données</span>
            <h2>Sources officielles, gratuites par défaut</h2>
            <div class="grille grille-3">
                <div class="carte"><span class="ico">🏛️</span><h3>Recherche d'entreprises</h3><p>API de l'État, <strong>gratuite et sans clé</strong> : identité, effectif, chiffres clés, statut.</p></div>
                <div class="carte"><span class="ico">📰</span><h3>BODACC</h3><p>Annonces légales : procédures collectives et radiations.</p></div>
                <div class="carte"><span class="ico">🧮</span><h3>Score interne</h3><p>Calcul local, aucun service payant.</p></div>
            </div>
            <ul class="liste" style="margin-top:18px">
                <li>Fournisseur <strong>au choix</strong> : automatique, API de l'État, ou <strong>Pappers</strong> (optionnel, clé requise).</li>
                <li><strong>Référentiels embarqués</strong> : codes NAF et catégories juridiques traduits en clair.</li>
            </ul>
        </div>
    </section>

    <!-- 11 — IA -->
    <section class="slide" data-title="Analyse de confiance par IA">
        <div class="slide-inner">
            <span class="kicker">Intelligence artificielle</span>
            <h2>Analyse de confiance (1min.ai)</h2>
            <div class="duo">
                <div>
                    <ul class="liste">
                        <li>Sur la fiche client, un bouton <strong>Analyser</strong> interroge l'IA.</li>
                        <li>Verdict clair : <span class="pastille vert">faire confiance</span> <span class="pastille orange">faire attention</span> <span class="pastille rouge">ne pas faire confiance</span></li>
                        <li>Compte rendu argumenté enregistré sur le client.</li>
                    </ul>
                </div>
                <div class="grille grille-1">
                    <div class="carte"><span class="ico">🔑</span><h3>Facultatif &amp; sécurisé</h3><p>Clé saisie dans <strong>Paramètres → IA</strong>, stockée <strong>côté serveur</strong>, jamais exposée. Sans clé, la fonction reste désactivée.</p></div>
                    <div class="carte"><span class="ico">🧪</span><h3>Modèle au choix</h3><p>Liste de modèles compatible OpenAI, bouton <strong>Tester la connexion</strong> et journal des erreurs.</p></div>
                </div>
            </div>
        </div>
    </section>

    <!-- 12 — Messagerie -->
    <section class="slide" data-title="Messagerie interne">
        <div class="slide-inner">
            <span class="kicker">Collaboration</span>
            <h2>Messagerie interne</h2>
            <p class="sous">Discuter du dossier sans quitter l'application.</p>
            <div class="grille grille-3">
                <div class="carte"><span class="ico">👥</span><h3>1-à-1</h3><p>Discussion privée entre deux utilisateurs.</p></div>
                <div class="carte"><span class="ico">🗂️</span><h3>Groupes</h3><p>Conversation nommée réunissant plusieurs personnes.</p></div>
                <div class="carte"><span class="ico">🏬</span><h3>Canal d'agence</h3><p>Visible par tous les utilisateurs d'une même agence.</p></div>
            </div>
            <div class="note bleu">💬 Compteur de <strong>messages non lus</strong> dans la barre de navigation et fil qui se <strong>rafraîchit automatiquement</strong>. L'agence de chaque personne est affichée à côté de son nom.</div>
        </div>
    </section>

    <!-- 13 — Notes & documents -->
    <section class="slide" data-title="Notes client &amp; documents">
        <div class="slide-inner">
            <span class="kicker">Mémoire du dossier</span>
            <h2>Notes client &amp; documents</h2>
            <div class="duo">
                <div class="carte">
                    <span class="ico">📝</span>
                    <h3>Notes client</h3>
                    <p>Rédigées par les gestionnaires, <strong>modifiables</strong> par les profils autorisés et visibles en <strong>lecture seule</strong> par les agences — y compris via un lien public.</p>
                </div>
                <div class="carte">
                    <span class="ico">📎</span>
                    <h3>Pièces jointes</h3>
                    <p>Documents par litige <em>et</em> par client, stockés <strong>hors racine web</strong> et protégés.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 14 — Partage lecture seule -->
    <section class="slide" data-title="Partage en lecture seule">
        <div class="slide-inner">
            <span class="kicker">Ouverture</span>
            <h2>Partage en lecture seule</h2>
            <div class="grille grille-2">
                <div class="carte">
                    <span class="ico">🔑</span>
                    <h3>Code d'accès</h3>
                    <p>Pour une <strong>agence</strong>, un <strong>client</strong>, un <strong>litige</strong> ou <strong>tout</strong> : l'agence consulte sans compte, sur le périmètre défini, avec expiration optionnelle.</p>
                </div>
                <div class="carte">
                    <span class="ico">🔗</span>
                    <h3>Lien public de litige</h3>
                    <p>Un bouton <strong>Partager</strong> génère un lien <code>/p/&lt;jeton&gt;</code> à transmettre à un tiers (avocat, assureur). Aucune modification possible, révocable à tout moment.</p>
                </div>
            </div>
            <div class="note">👁️ Tous les accès en lecture seule sont <strong>journalisés</strong>.</div>
        </div>
    </section>

    <!-- 15 — Notifications -->
    <section class="slide" data-title="Notifications">
        <div class="slide-inner">
            <span class="kicker">Rester informé</span>
            <h2>Notifications internes</h2>
            <table class="mini-table">
                <thead><tr><th>Événement</th><th>Destinataires</th></tr></thead>
                <tbody>
                    <tr><td>Création de client</td><td>Utilisateurs ayant accès au module Clients (hors auteur)</td></tr>
                    <tr><td>Création / modification / résolution de litige</td><td>Idem module Litiges</td></tr>
                    <tr><td>Note sur un litige</td><td>Idem</td></tr>
                    <tr><td>Nouveau message de messagerie</td><td>Uniquement les personnes concernées</td></tr>
                    <tr><td>Rappels automatiques (cron)</td><td>Responsable et administrateurs</td></tr>
                </tbody>
            </table>
            <div class="note bleu">🔔 Chaque notification renvoie directement vers le client, le litige ou la conversation concernée.</div>
        </div>
    </section>

    <!-- 16 — Rôles -->
    <section class="slide" data-title="Rôles &amp; permissions">
        <div class="slide-inner">
            <span class="kicker">Droits d'accès</span>
            <h2>Rôles &amp; permissions</h2>
            <div class="grille grille-2">
                <div class="carte"><span class="ico">🛡️</span><h3>Administrateur</h3><p>Accès total : utilisateurs, agences, paramètres, statuts, audit, suppressions.</p></div>
                <div class="carte"><span class="ico">🧑‍💼</span><h3>Gestionnaire</h3><p>Création et modification des litiges, clients, relances, notes, documents, partages.</p></div>
                <div class="carte"><span class="ico">🔍</span><h3>Consultant</h3><p>Consultation des litiges, clients, messagerie — droits configurables.</p></div>
                <div class="carte"><span class="ico">👀</span><h3>Lecteur</h3><p>Consultation uniquement, aucune modification.</p></div>
            </div>
            <div class="note">⚙️ Les accès par module (Litiges, Clients, Relances, Messagerie) se règlent finement dans <strong>Paramètres → Droits d'accès par rôle</strong>.</div>
        </div>
    </section>

    <!-- 17 — Sécurité & audit -->
    <section class="slide" data-title="Sécurité &amp; journal d'audit">
        <div class="slide-inner">
            <span class="kicker">Confiance</span>
            <h2>Sécurité &amp; journal d'audit</h2>
            <div class="duo">
                <ul class="liste">
                    <li>Mots de passe <strong>chiffrés (bcrypt)</strong> et verrouillage après échecs répétés.</li>
                    <li>Protection <strong>CSRF</strong> sur tous les formulaires.</li>
                    <li>Requêtes SQL <strong>préparées</strong> et échappement systématique.</li>
                    <li>Sessions sécurisées, expiration par inactivité, HTTPS possible.</li>
                    <li>Changement de mot de passe forcé à la première connexion si besoin.</li>
                </ul>
                <div class="carte">
                    <span class="ico">📋</span>
                    <h3>Journal d'audit</h3>
                    <p>Connexions, créations, modifications, suppressions et exports sont tracés. Le journal est consultable et <strong>exportable en CSV</strong> par l'administrateur.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 18 — Déploiement & conclusion -->
    <section class="slide slide--end" data-title="Déploiement &amp; conclusion">
        <div class="slide-inner">
            <span class="ruban">Déploiement</span>
            <h2>Simple à installer, simple à faire évoluer</h2>
            <p class="sous">PHP 8 · MySQL, racine web sur <code>public/</code>. Les évolutions de la base s'appliquent <strong>automatiquement</strong> : un simple <code>git pull</code>, aucune commande SQL. Installable comme application (PWA), elle fonctionne aussi hors ligne sur mobile.</p>
            <div style="margin-top:24px">
                <span class="pastille">🖥️ PHP 8+ / MySQL</span>
                <span class="pastille">🔄 Migrations automatiques</span>
                <span class="pastille">📱 PWA hors ligne</span>
                <span class="pastille">🧩 Sans dépendance payante</span>
            </div>
            <p class="signature" style="margin-top:40px">Merci — <?= e($appName) ?></p>
        </div>
    </section>

</div>

<div class="deck-bar" role="toolbar" aria-label="Contrôles du diaporama">
    <button type="button" data-deck="prev" title="Précédent (←)">‹</button>
    <span class="compteur">1 / 1</span>
    <button type="button" data-deck="next" title="Suivant (→)">›</button>
    <span class="sep"></span>
    <button type="button" data-deck="overview" title="Sommaire (O)">☰</button>
    <button type="button" data-deck="full" title="Plein écran (F)">⛶</button>
    <span class="sep"></span>
    <a href="<?= url('/') ?>" title="Quitter la présentation">✕</a>
</div>

<script src="<?= asset('js/presentation.js') ?>"></script>
</body>
</html>
