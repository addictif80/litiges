<?php
/**
 * Dossier d'entreprise (inspection) - aucun client requis.
 * @var array $e  Fiche entreprise (CompanyLookupService::dossier()).
 */
$peutEcrire = \App\Core\Auth::canWrite();
$solv       = is_array($e['solvabilite'] ?? null) ? $e['solvabilite'] : [];
$finances   = is_array($e['finances_historique'] ?? null) ? $e['finances_historique'] : [];
$procedures = is_array($e['procedures'] ?? null) ? $e['procedures'] : [];
$parAnnee   = is_array($e['procedures_par_annee'] ?? null) ? $e['procedures_par_annee'] : [];
$enCours    = is_array($e['procedure_en_cours'] ?? null) ? $e['procedure_en_cours'] : null;

$champsCreation = array_filter([
    'siren'             => $e['siren'] ?? null,
    'siret'             => $e['siret'] ?? null,
    'raison_sociale'    => $e['raison_sociale'] ?? null,
    'forme_juridique'   => $e['forme_juridique'] ?? null,
    'ape_naf'           => $e['ape_naf'] ?? null,
    'libelle_ape'       => $e['libelle_ape'] ?? null,
    'adresse'           => $e['adresse'] ?? null,
    'complement_adresse' => $e['complement_adresse'] ?? null,
    'code_postal'       => $e['code_postal'] ?? null,
    'ville'             => $e['ville'] ?? null,
    'effectif'          => $e['effectif'] ?? null,
    'date_creation'     => $e['date_creation'] ?? null,
    'capital'           => $e['capital'] ?? null,
    'chiffre_affaires'  => $e['chiffre_affaires'] ?? null,
    'resultat_net'      => $e['resultat_net'] ?? null,
    'statut_entreprise' => $e['statut_entreprise'] ?? null,
], static fn ($v): bool => $v !== null && $v !== '');
$query        = http_build_query($champsCreation);
$urlCreation  = url('/clients/nouveau') . ($query !== '' ? '?' . $query : '');
?>
<div class="fil"><a href="<?= url('/clients') ?>">Clients</a> / <a href="<?= url('/entreprise') ?>">Inspecter une entreprise</a> / <?= e($e['raison_sociale'] ?: 'Entreprise') ?></div>

<div class="entete-fiche">
    <div class="infos" style="flex:1;min-width:280px">
        <h1>
            <?= e($e['raison_sociale'] ?: '(sans dénomination)') ?>
            <?php if ($enCours): ?><span class="badge" style="background:var(--rouge)">Procédure en cours</span><?php endif; ?>
            <?php if (($e['statut_entreprise'] ?? '') === 'Cessee'): ?><span class="badge" style="background:var(--gris)">Cessée</span><?php endif; ?>
        </h1>
        <div class="meta">
            <?php if (!empty($e['siren'])): ?><span class="mono">SIREN <?= e($e['siren']) ?></span><?php endif; ?>
            <?php if (!empty($e['ville'])): ?><span>📍 <?= e(trim(($e['code_postal'] ?? '') . ' ' . ($e['ville'] ?? ''))) ?></span><?php endif; ?>
            <?php if (!empty($e['statut_entreprise'])): ?><span>Situation : <?= e($e['statut_entreprise']) ?></span><?php endif; ?>
            <?php if (!empty($e['_source'])): ?><span class="petit">Source : <?= e($e['_source']) ?></span><?php endif; ?>
        </div>
    </div>
    <div class="flex">
        <?php if ($peutEcrire): ?>
            <a href="<?= e($urlCreation) ?>" class="btn">+ Créer un client</a>
        <?php endif; ?>
    </div>
</div>

<div class="grille" style="grid-template-columns:2fr 1fr;align-items:start">
    <div>
        <div class="card">
            <div class="card-entete"><h3>Identité</h3></div>
            <div class="card-corps">
                <dl class="definition" style="grid-template-columns:150px 1fr">
                    <dt>Dénomination</dt><dd><?= e($e['raison_sociale'] ?: '—') ?></dd>
                    <dt>SIREN</dt><dd class="mono"><?= e($e['siren'] ?: '—') ?></dd>
                    <dt>SIRET (siège)</dt><dd class="mono petit"><?= e($e['siret'] ?: '—') ?></dd>
                    <dt>TVA intra.</dt><dd><?= e($e['tva_intra'] ?: '—') ?></dd>
                    <dt>Date de création</dt><dd><?= date_fr($e['date_creation'] ?? null) ?></dd>
                    <dt>Capital</dt><dd><?= ($e['capital'] ?? null) !== null ? money($e['capital']) : '—' ?></dd>
                    <dt>Effectif</dt><dd><?= e($e['effectif'] ?: '—') ?></dd>
                </dl>
                <div class="mt-2">
                    <label>Adresse du siège</label>
                    <div class="petit texte-doux">
                        <?= e($e['adresse'] ?: '') ?><br>
                        <?php if (!empty($e['complement_adresse'])): ?><?= e($e['complement_adresse']) ?><br><?php endif; ?>
                        <?= e(trim(($e['code_postal'] ?? '') . ' ' . ($e['ville'] ?? ''))) ?><br>
                        <?= e($e['pays'] ?: '') ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-entete"><h3>Données financières (par exercice)</h3></div>
            <?php if (empty($finances)): ?>
                <div class="card-corps texte-doux">Aucun exercice comptable publié.</div>
            <?php else: ?>
                <table class="tableau tableau-compact">
                    <thead><tr><th>Exercice</th><th class="num">Chiffre d'affaires</th><th class="num">Résultat net</th></tr></thead>
                    <tbody>
                    <?php foreach ($finances as $f): ?>
                        <tr>
                            <td><strong><?= (int) $f['annee'] ?></strong></td>
                            <td class="num"><?= ($f['ca'] ?? null) !== null ? money((float) $f['ca']) : '—' ?></td>
                            <td class="num <?= ((float) ($f['resultat_net'] ?? 0)) < 0 ? 'texte-rouge' : '' ?>"><?= ($f['resultat_net'] ?? null) !== null ? money((float) $f['resultat_net']) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-entete">
                <h3>Procédures collectives &amp; annonces (BODACC)</h3>
                <?php if ($enCours): ?><span class="badge" style="background:var(--rouge)">En cours</span><?php endif; ?>
            </div>
            <?php if (empty($procedures)): ?>
                <div class="card-corps texte-doux">Aucune procédure collective ni radiation publiée au BODACC.</div>
            <?php else: ?>
                <div class="card-corps">
                    <?php if ($enCours): ?>
                        <div class="alerte alerte-warning" style="margin-top:0">
                            <strong>Procédure en cours :</strong> <?= e($enCours['libelle'] ?? $enCours['type'] ?? '') ?>
                            <?php if (!empty($enCours['tribunal'])): ?> — tribunal de <?= e($enCours['tribunal']) ?><?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($parAnnee as $annee => $annonces): ?>
                        <h4 class="mt-2 mb-1"><?= (int) $annee ?></h4>
                        <table class="tableau tableau-compact">
                            <thead><tr><th>Type</th><th>Famille</th><th>Parution</th><th>Tribunal</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($annonces as $a): ?>
                                <tr>
                                    <td><strong><?= e($a['type'] ?: '—') ?></strong>
                                        <?php if (!empty($a['nature'])): ?><div class="petit texte-doux"><?= e($a['nature']) ?></div><?php endif; ?>
                                    </td>
                                    <td class="petit"><?= e($a['famille'] ?: '—') ?></td>
                                    <td class="petit"><?= date_fr($a['date_parution'] ?? null) ?></td>
                                    <td class="petit"><?= e($a['tribunal'] ?: '—') ?></td>
                                    <td class="actions">
                                        <?php if (!empty($a['url'])): ?><a href="<?= e($a['url']) ?>" target="_blank" rel="noopener" class="btn-lien">Annonce ↗</a><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endforeach; ?>
                    <p class="petit texte-doux mt-1">Source : BODACC (données ouvertes DILA).</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-entete"><h3>Informations légales &amp; dirigeants</h3></div>
            <div class="card-corps">
                <?php include __DIR__ . '/../partials/infos_legales.php'; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-entete"><h3>Solvabilité (score interne)</h3></div>
            <div class="card-corps">
                <div class="flex" style="align-items:center;gap:10px">
                    <span class="badge" style="background:<?= e($solv['couleur'] ?? 'var(--gris)') ?>;font-size:15px"><?= (int) ($solv['score'] ?? 0) ?>/100</span>
                    <strong><?= e($solv['label'] ?? '—') ?></strong>
                </div>
                <?php if (!empty($solv['facteurs'])): ?>
                    <ul class="petit texte-doux mt-2" style="margin:0;padding-left:18px">
                        <?php foreach ($solv['facteurs'] as $facteur): ?><li><?= e($facteur) ?></li><?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="petit texte-doux mt-2">Pas assez de données financières pour affiner le score.</div>
                <?php endif; ?>
                <div class="petit texte-doux mt-2">Calcul local et gratuit, à partir des données publiques.</div>
            </div>
        </div>
    </div>
</div>
