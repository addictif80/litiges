<?php
/**
 * Tableau de bord.
 * @var array $stats
 */
$encours         = (float) ($stats['encours'] ?? 0);
$montantTotal    = (float) ($stats['montant_total'] ?? 0);
$recupere        = (float) ($stats['montant_recupere'] ?? 0);
$taux            = (float) ($stats['taux_recouvrement'] ?? 0);
$maxStatut       = 1;
foreach ($parStatut as $s) { $maxStatut = max($maxStatut, (int) $s['nb']); }
$maxEvo          = 1;
foreach ($evolution as $e) { $maxEvo = max($maxEvo, (int) $e['nb']); }
?>

<div class="grille grille-4 mb-2">
    <div class="stat accent">
        <div class="libelle">Litiges ouverts</div>
        <div class="valeur"><?= (int) ($stats['ouverts'] ?? 0) ?></div>
        <div class="detail">sur <?= (int) ($stats['total'] ?? 0) ?> au total</div>
    </div>
    <div class="stat accent-orange">
        <div class="libelle">Encours à recouvrer</div>
        <div class="valeur"><?= money($encours) ?></div>
        <div class="detail">litiges non clôturés</div>
    </div>
    <div class="stat accent-vert">
        <div class="libelle">Montant récupéré</div>
        <div class="valeur"><?= money($recupere) ?></div>
        <div class="detail"><?= number_format($taux, 1, ',', ' ') ?> % du total</div>
    </div>
    <div class="stat accent-rouge">
        <div class="libelle">Relances en retard</div>
        <div class="valeur"><?= (int) ($nbRelancesDues ?? 0) ?></div>
        <div class="detail"><a href="<?= url('/litiges?relance_due=1&etat=ouverts') ?>">Voir la liste</a></div>
    </div>
</div>

<div class="grille grille-2">
    <div class="card">
        <div class="card-entete">
            <h3>Répartition par statut</h3>
            <div class="esp"></div>
            <span class="petit texte-doux"><?= (int) ($stats['clotures'] ?? 0) ?> clôturé(s)</span>
        </div>
        <div class="card-corps">
            <?php if (empty($parStatut)): ?>
                <p class="texte-doux">Aucun litige enregistré.</p>
            <?php else: ?>
                <?php foreach ($parStatut as $s): ?>
                    <div style="margin-bottom:12px">
                        <div class="flex" style="justify-content:space-between">
                            <span class="tag-statut">
                                <span class="pastille" style="background:<?= e($s['couleur']) ?>"></span>
                                <?= e($s['libelle']) ?>
                            </span>
                            <span class="petit"><strong><?= (int) $s['nb'] ?></strong> · <?= money($s['encours']) ?></span>
                        </div>
                        <div class="barre-progression mt-1">
                            <span style="width:<?= (int) round((int) $s['nb'] / $maxStatut * 100) ?>%;background:<?= e($s['couleur']) ?>"></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-entete">
            <h3>Évolution des ouvertures</h3>
            <div class="esp"></div>
            <span class="petit texte-doux">12 derniers mois</span>
        </div>
        <div class="card-corps">
            <?php if (empty($evolution)): ?>
                <p class="texte-doux">Pas encore de données.</p>
            <?php else: ?>
                <div style="display:flex;align-items:flex-end;gap:8px;height:170px">
                    <?php foreach ($evolution as $e): ?>
                        <div style="flex:1;text-align:center" title="<?= e($e['periode']) ?> : <?= (int) $e['nb'] ?> litige(s) - <?= money($e['montant']) ?>">
                            <div style="height:<?= (int) round((int) $e['nb'] / $maxEvo * 130) ?>px;background:linear-gradient(180deg,var(--bleu-600),var(--bleu));border-radius:6px 6px 0 0;min-height:4px"></div>
                            <div class="petit texte-doux" style="margin-top:5px"><?= e(substr((string) $e['periode'], 5, 2)) ?>/<?= e(substr((string) $e['periode'], 2, 2)) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grille grille-2">
    <div class="card">
        <div class="card-entete">
            <h3>🔔 Relances à effectuer</h3>
            <div class="esp"></div>
            <a href="<?= url('/litiges?relance_due=1&etat=ouverts') ?>" class="petit">Tout voir</a>
        </div>
        <?php if (empty($relancesDues)): ?>
            <div class="card-corps texte-doux">Aucune relance en retard. 👌</div>
        <?php else: ?>
            <table class="tableau tableau-compact">
                <tbody>
                <?php foreach ($relancesDues as $l): ?>
                    <tr>
                        <td>
                            <a href="<?= url('/litiges/' . (int) $l['id']) ?>" class="gras"><?= e($l['reference']) ?></a>
                            <div class="petit texte-doux"><?= e($l['raison_sociale']) ?></div>
                        </td>
                        <td class="num"><?= money($l['encours']) ?></td>
                        <td class="num petit">
                            <?php $j = days_until($l['prochaine_relance_at']); ?>
                            <span class="<?= $j !== null && $j < 0 ? 'texte-rouge gras' : 'texte-doux' ?>">
                                <?= date_fr($l['prochaine_relance_at']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-entete">
            <h3>Top clients (encours)</h3>
        </div>
        <?php if (empty($topClients)): ?>
            <div class="card-corps texte-doux">Aucun encours.</div>
        <?php else: ?>
            <table class="tableau tableau-compact">
                <tbody>
                <?php foreach ($topClients as $c): ?>
                    <tr>
                        <td>
                            <a href="<?= url('/clients/' . (int) $c['id']) ?>"><?= e($c['raison_sociale']) ?></a>
                            <div class="petit texte-doux"><?= (int) $c['nb'] ?> litige(s)</div>
                        </td>
                        <td class="num gras"><?= money($c['encours']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="grille grille-2">
    <div class="card">
        <div class="card-entete">
            <h3>Litiges récents</h3>
            <div class="esp"></div>
            <a href="<?= url('/litiges') ?>" class="petit">Tout voir</a>
        </div>
        <?php if (empty($derniers)): ?>
            <div class="card-corps texte-doux">Aucun litige pour l'instant. <a href="<?= url('/litiges/nouveau') ?>">Créer le premier</a>.</div>
        <?php else: ?>
            <table class="tableau tableau-compact">
                <tbody>
                <?php foreach ($derniers as $l): ?>
                    <tr>
                        <td>
                            <a href="<?= url('/litiges/' . (int) $l['id']) ?>" class="gras"><?= e($l['reference']) ?></a>
                            <div class="petit texte-doux"><?= e(truncate($l['objet'], 46)) ?></div>
                        </td>
                        <td><?= e($l['raison_sociale']) ?></td>
                        <td>
                            <span class="badge" style="background:<?= e($l['statut_couleur']) ?>"><?= e($l['statut_libelle']) ?></span>
                        </td>
                        <td class="num"><?= money($l['encours']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-entete"><h3>Dernières actions (relances)</h3></div>
        <?php if (empty($relancesRecentes)): ?>
            <div class="card-corps texte-doux">Aucune relance enregistrée.</div>
        <?php else: ?>
            <div class="card-corps">
                <div class="timeline">
                    <?php foreach ($relancesRecentes as $r): ?>
                        <div class="timeline-item">
                            <div class="tl-entete">
                                <span class="tl-titre"><?= e(\App\Enums\TypeAction::tryFrom($r['type_action'])?->label() ?? $r['type_action']) ?></span>
                                <span class="tl-date"><?= datetime_fr($r['date_action']) ?></span>
                            </div>
                            <div class="tl-corps petit">
                                <a href="<?= url('/litiges/' . (int) $r['litige_id']) ?>"><?= e($r['reference']) ?></a>
                                — <?= e($r['raison_sociale']) ?><br>
                                <?= e(truncate($r['contenu'] ?: $r['objet'], 110)) ?>
                            </div>
                            <div class="tl-pied">par <?= e($r['user_nom'] ?: 'utilisateur supprimé') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($creancesUrgentes)): ?>
<div class="alerte alerte-warning mb-2">
    <strong>⚠ Délais de déclaration de créance à surveiller</strong>
    <?php foreach ($creancesUrgentes as $c): ?>
        <div class="petit">
            <a href="<?= url('/litiges/' . (int) $c['id']) ?>"><?= e($c['reference']) ?></a> — <?= e($c['raison_sociale']) ?>
            (<?= e($c['procedure_collective']) ?>) : avant le <strong><?= date_fr($c['limite']) ?></strong>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="grille grille-2">
    <div class="card">
        <div class="card-entete"><h3>✅ Mes tâches</h3><div class="esp"></div><a href="<?= url('/taches') ?>" class="petit">Tout voir</a></div>
        <?php if (empty($mesTaches)): ?>
            <div class="card-corps texte-doux">Aucune tâche en cours. 👌</div>
        <?php else: ?>
            <table class="tableau tableau-compact">
                <tbody>
                <?php foreach ($mesTaches as $t): ?>
                    <?php $retard = $t['echeance'] && $t['echeance'] < date('Y-m-d'); ?>
                    <tr>
                        <td><?= e($t['titre']) ?>
                            <?php if ($t['litige_id']): ?><div class="petit"><a href="<?= url('/litiges/' . (int) $t['litige_id'] . '#taches') ?>"><?= e($t['litige_reference']) ?></a></div><?php endif; ?></td>
                        <td class="num petit <?= $retard ? 'texte-rouge gras' : 'texte-doux' ?>"><?= $t['echeance'] ? date_fr($t['echeance']) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-entete"><h3>💶 Encaissements attendus (4 semaines)</h3><div class="esp"></div><a href="<?= url('/rapports') ?>" class="petit">Rapports</a></div>
        <?php if ($previsions === null): ?>
            <div class="card-corps texte-doux">Indisponible.</div>
        <?php else: ?>
            <div class="card-corps">
                <div class="flex" style="justify-content:space-between"><span>Échéances de plans de paiement</span><strong><?= money($previsions['total_echeances']) ?></strong></div>
                <div class="flex" style="justify-content:space-between"><span>Promesses de paiement</span><strong><?= money($previsions['total_promesses']) ?></strong></div>
                <?php if ($previsions['en_retard'] > 0): ?>
                    <div class="flex texte-rouge" style="justify-content:space-between"><span>Échéances déjà en retard</span><strong><?= money($previsions['en_retard']) ?></strong></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="grille grille-2">
    <div class="card">
        <div class="card-entete"><h3>Encours par agence</h3></div>
        <?php if (empty($parAgence)): ?>
            <div class="card-corps texte-doux">Aucune donnée.</div>
        <?php else: ?>
            <table class="tableau tableau-compact">
                <thead><tr><th>Agence</th><th class="num">Litiges</th><th class="num">Encours</th></tr></thead>
                <tbody>
                <?php foreach ($parAgence as $a): ?>
                    <tr>
                        <td><?= e($a['agence']) ?></td>
                        <td class="num"><?= (int) $a['nb'] ?></td>
                        <td class="num"><?= money($a['encours']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-entete"><h3>Encours par gestionnaire</h3></div>
        <?php if (empty($parResponsable)): ?>
            <div class="card-corps texte-doux">Aucune donnée.</div>
        <?php else: ?>
            <table class="tableau tableau-compact">
                <thead><tr><th>Gestionnaire</th><th class="num">Litiges</th><th class="num">Encours</th></tr></thead>
                <tbody>
                <?php foreach ($parResponsable as $r): ?>
                    <tr>
                        <td><?= e($r['responsable']) ?></td>
                        <td class="num"><?= (int) $r['nb'] ?></td>
                        <td class="num"><?= money($r['encours']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
