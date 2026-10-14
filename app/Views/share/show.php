<?php
/**
 * Fiche d'un litige en consultation publique (lecture seule).
 * @var array $partage
 * @var array $litige
 * @var array $relances
 * @var array $types
 */
$cloture = (int) $litige['statut_cloturant'] === 1;
$retardRelance = !empty($litige['prochaine_relance_at']) && !$cloture
    && strtotime((string) $litige['prochaine_relance_at']) <= strtotime('today');
?>
<div class="fil">
    <a href="<?= url('/partage/consultation') ?>">Litiges</a> /
    <span class="mono"><?= e($litige['reference']) ?></span>
</div>

<div class="entete-fiche">
    <div class="infos" style="flex:1;min-width:280px">
        <h1>
            <?= e($litige['reference']) ?>
            <span class="badge" style="background:<?= e($litige['statut_couleur']) ?>"><?= e($litige['statut_libelle']) ?></span>
            <span class="badge badge-priorite-<?= e($litige['priorite']) ?>"><?= e(ucfirst($litige['priorite'])) ?></span>
        </h1>
        <div class="meta">
            <span>🏢 <?= e($litige['raison_sociale']) ?><?= $litige['client_siren'] ? ' — SIREN ' . e($litige['client_siren']) : '' ?></span>
            <span>📅 Ouvert le <?= date_fr($litige['date_ouverture']) ?></span>
            <?php if ($litige['agence_nom']): ?><span>🏬 <?= e($litige['agence_nom']) ?></span><?php endif; ?>
        </div>
    </div>
    <div class="flex no-print">
        <button type="button" class="btn btn-secondaire" onclick="window.print()">🖨 Imprimer</button>
        <a href="<?= url('/partage/consultation') ?>" class="btn btn-secondaire">&larr; Retour</a>
    </div>
</div>

<div class="grille grille-4 mb-2">
    <div class="stat accent"><div class="libelle">Montant principal</div><div class="valeur"><?= money($litige['montant_principal']) ?></div></div>
    <div class="stat accent-orange"><div class="libelle">Pénalités / frais</div><div class="valeur"><?= money($litige['montant_penalites']) ?></div></div>
    <div class="stat accent-vert"><div class="libelle">Récupéré</div><div class="valeur"><?= money($litige['montant_recupere']) ?></div></div>
    <div class="stat accent-rouge"><div class="libelle">Encours</div><div class="valeur"><?= money($litige['encours']) ?></div>
        <div class="detail"><?= $cloture ? 'Litige clôturé' . ($litige['finalite_libelle'] ? ' — ' . e($litige['finalite_libelle']) : '') : ($retardRelance ? '⚠ Relance en retard' : 'En cours') ?></div>
    </div>
</div>

<div class="grille" style="grid-template-columns:2fr 1fr;align-items:start">
    <div class="card">
        <div class="card-entete"><h3>Suivi &amp; relances (<?= count($relances) ?>)</h3></div>
        <div class="card-corps">
            <?php if (empty($relances)): ?>
                <p class="texte-doux">Aucune relance enregistrée pour ce litige.</p>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($relances as $r): ?>
                        <?php
                        $classe = 'orange';
                        if (in_array($r['type_action'], ['mise_en_demeure'], true)) { $classe = 'rouge'; }
                        if (in_array($r['type_action'], ['promesse_paiement', 'echelonnement'], true)) { $classe = 'vert'; }
                        ?>
                        <div class="timeline-item <?= $classe ?>">
                            <div class="tl-entete">
                                <span class="tl-titre"><?= e(\App\Models\Relance::TYPES[$r['type_action']] ?? $r['type_action']) ?></span>
                                <span class="badge badge-contour"><?= e(\App\Models\Relance::CANAUX[$r['canal']] ?? $r['canal']) ?></span>
                                <span class="tl-date"><?= datetime_fr($r['date_action']) ?></span>
                            </div>
                            <?php if ($r['objet']): ?><div class="gras petit" style="margin-top:3px"><?= e($r['objet']) ?></div><?php endif; ?>
                            <?php if ($r['contenu']): ?><div class="tl-corps"><?= e($r['contenu']) ?></div><?php endif; ?>
                            <div class="tl-pied">
                                <?php if (!empty($r['resultat'])): ?>Résultat : <?= e($r['resultat']) ?><?php endif; ?>
                                <?php if ($r['montant_promis'] !== null): ?> · Promis : <?= money($r['montant_promis']) ?><?= $r['date_promesse'] ? ' le ' . date_fr($r['date_promesse']) : '' ?><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-entete"><h3>Informations</h3></div>
            <div class="card-corps">
                <dl class="definition" style="grid-template-columns:130px 1fr">
                    <dt>Objet</dt><dd><?= e($litige['objet']) ?></dd>
                    <dt>Type</dt><dd><?= e($types[$litige['type_litige']] ?? ($litige['type_litige'] ?: '—')) ?></dd>
                    <dt>N° facture</dt><dd><?= e($litige['numero_facture'] ?: '—') ?></dd>
                    <dt>N° contrat</dt><dd><?= e($litige['numero_contrat'] ?: '—') ?></dd>
                    <dt>Matériel</dt><dd><?= e($litige['materiel'] ?: '—') ?></dd>
                    <dt>Échéance</dt><dd><?= date_fr($litige['date_echeance']) ?></dd>
                    <dt>Prochaine relance</dt><dd class="<?= $retardRelance ? 'texte-rouge gras' : '' ?>"><?= date_fr($litige['prochaine_relance_at']) ?></dd>
                    <?php if ($cloture): ?>
                        <dt>Date de clôture</dt><dd><?= date_fr($litige['date_cloture']) ?></dd>
                        <dt>Finalité</dt><dd><?= e($litige['finalite_libelle'] ?: '—') ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>
</div>
