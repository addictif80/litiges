<?php
/**
 * Rapports : balance agee, recouvrement par agence, previsions d'encaissement.
 * @var array $balance
 * @var array $recouvrement
 * @var array $previsions
 * @var float $delaiMoyen
 * @var bool $xlsx
 */
use App\Services\Calcul\BalanceAgee;

$couleurs = ['courant' => '#198754', 't0_30' => '#ffc107', 't31_60' => '#fd7e14', 't61_90' => '#dc3545', 't90p' => '#842029'];
$maxTranche = max(1.0, ...array_values($balance['total']));
$maxSemaine = 1.0;
foreach ($previsions['semaines'] as $s) {
    $maxSemaine = max($maxSemaine, $s['echeances'] + $s['promesses']);
}
$maxMois = 1.0;
foreach ($recouvrement['total'] as $m) {
    $maxMois = max($maxMois, $m['ouvert'], $m['recupere']);
}
?>
<div class="grille grille-4 mb-2">
    <div class="stat accent-rouge"><div class="libelle">Encours à recouvrer</div><div class="valeur"><?= money($balance['encours']) ?></div><div class="detail"><?= (int) $balance['nb'] ?> dossier(s) avec solde</div></div>
    <div class="stat accent-orange"><div class="libelle">Ancienneté moyenne pondérée</div><div class="valeur"><?= number_format($balance['anciennete_moyenne'], 0, ',', ' ') ?> j</div><div class="detail">de retard, pondérée par le montant</div></div>
    <div class="stat accent-vert"><div class="libelle">Attendu sous 8 semaines</div><div class="valeur"><?= money($previsions['total_echeances'] + $previsions['total_promesses']) ?></div><div class="detail">échéanciers + promesses</div></div>
    <div class="stat accent"><div class="libelle">Délai moyen de recouvrement</div><div class="valeur"><?= number_format($delaiMoyen, 0, ',', ' ') ?> j</div><div class="detail">litiges clôturés</div></div>
</div>

<div class="card">
    <div class="card-entete">
        <h3>Balance âgée des créances</h3>
        <div class="esp"></div>
        <a href="<?= url('/rapports/balance-agee') ?>" class="petit">CSV</a>
        <?php if ($xlsx): ?>&nbsp;·&nbsp;<a href="<?= url('/rapports/balance-agee?format=xlsx') ?>" class="petit">Excel</a><?php endif; ?>
    </div>
    <div class="card-corps">
        <?php foreach (BalanceAgee::TRANCHES as $cle => $libelle): ?>
            <div style="margin-bottom:10px">
                <div class="flex" style="justify-content:space-between">
                    <span><span class="pastille" style="background:<?= e($couleurs[$cle]) ?>"></span> <?= e($libelle) ?></span>
                    <strong><?= money($balance['total'][$cle]) ?></strong>
                </div>
                <div class="barre-progression mt-1"><span style="width:<?= (int) round($balance['total'][$cle] / $maxTranche * 100) ?>%;background:<?= e($couleurs[$cle]) ?>"></span></div>
            </div>
        <?php endforeach; ?>
        <p class="aide">Ancienneté mesurée depuis la date d'échéance (à défaut : date de facture, puis d'ouverture).</p>
    </div>
    <?php if (!empty($balance['par_agence'])): ?>
        <table class="tableau tableau-compact">
            <thead><tr><th>Agence</th><?php foreach (BalanceAgee::TRANCHES as $libelle): ?><th class="num"><?= e($libelle) ?></th><?php endforeach; ?><th class="num">Total</th></tr></thead>
            <tbody>
            <?php foreach ($balance['par_agence'] as $agence => $tranches): ?>
                <tr>
                    <td><?= e($agence) ?></td>
                    <?php foreach (array_keys(BalanceAgee::TRANCHES) as $cle): ?><td class="num"><?= money($tranches[$cle]) ?></td><?php endforeach; ?>
                    <td class="num gras"><?= money(array_sum($tranches)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if (!empty($balance['par_client'])): ?>
<div class="card">
    <div class="card-entete"><h3>Principaux clients débiteurs</h3></div>
    <table class="tableau tableau-compact">
        <thead><tr><th>Client</th><th class="num">Dossiers</th><?php foreach (BalanceAgee::TRANCHES as $libelle): ?><th class="num"><?= e($libelle) ?></th><?php endforeach; ?><th class="num">Total</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($balance['par_client'], 0, 15) as $c): ?>
            <tr>
                <td><a href="<?= url('/clients/' . (int) $c['client_id']) ?>"><?= e($c['raison_sociale']) ?></a></td>
                <td class="num"><?= (int) $c['nb'] ?></td>
                <?php foreach (array_keys(BalanceAgee::TRANCHES) as $cle): ?><td class="num"><?= $c[$cle] > 0 ? money($c[$cle]) : '—' ?></td><?php endforeach; ?>
                <td class="num gras"><?= money($c['total']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-entete"><h3>Prévisions d'encaissement — 8 prochaines semaines</h3>
        <div class="esp"></div>
        <?php if ($previsions['en_retard'] > 0): ?><span class="petit texte-rouge gras">Déjà en retard sur les échéanciers : <?= money($previsions['en_retard']) ?></span><?php endif; ?>
    </div>
    <div class="card-corps">
        <div style="display:flex;align-items:flex-end;gap:10px;height:190px">
            <?php foreach ($previsions['semaines'] as $s): ?>
                <?php $hE = (int) round($s['echeances'] / $maxSemaine * 150); $hP = (int) round($s['promesses'] / $maxSemaine * 150); ?>
                <div style="flex:1;text-align:center" title="Semaine du <?= e(date_fr($s['debut'])) ?> : échéances <?= e(money($s['echeances'])) ?>, promesses <?= e(money($s['promesses'])) ?>">
                    <div class="petit gras"><?= ($s['echeances'] + $s['promesses']) > 0 ? e(number_format($s['echeances'] + $s['promesses'], 0, ',', ' ')) : '' ?></div>
                    <div style="height:<?= max(2, $hP) ?>px;background:#fd7e14;border-radius:5px 5px 0 0"></div>
                    <div style="height:<?= max(2, $hE) ?>px;background:var(--bleu-600)"></div>
                    <div class="petit texte-doux" style="margin-top:5px"><?= e(date('d/m', strtotime($s['debut']))) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="aide mt-1"><span class="pastille" style="background:var(--bleu-600)"></span> Échéances de plans de paiement &nbsp; <span class="pastille" style="background:#fd7e14"></span> Promesses de paiement (litiges sans échéancier)</p>
    </div>
</div>

<div class="card">
    <div class="card-entete"><h3>Recouvrement par agence — 12 derniers mois</h3></div>
    <div class="card-corps">
        <div style="display:flex;align-items:flex-end;gap:6px;height:170px">
            <?php foreach ($recouvrement['total'] as $mois => $m): ?>
                <div style="flex:1;text-align:center" title="<?= e($mois) ?> : mis en recouvrement <?= e(money($m['ouvert'])) ?>, encaissé <?= e(money($m['recupere'])) ?>">
                    <div style="display:flex;align-items:flex-end;justify-content:center;gap:2px;height:140px">
                        <div style="width:45%;height:<?= max(2, (int) round($m['ouvert'] / $maxMois * 130)) ?>px;background:#adb5bd"></div>
                        <div style="width:45%;height:<?= max(2, (int) round($m['recupere'] / $maxMois * 130)) ?>px;background:#198754"></div>
                    </div>
                    <div class="petit texte-doux"><?= e(substr($mois, 5, 2)) ?>/<?= e(substr($mois, 2, 2)) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="aide mt-1"><span class="pastille" style="background:#adb5bd"></span> Mis en recouvrement (ouverture des litiges) &nbsp; <span class="pastille" style="background:#198754"></span> Encaissé (paiements)</p>
    </div>
    <?php if (!empty($recouvrement['agences'])): ?>
        <table class="tableau tableau-compact">
            <thead><tr><th>Agence</th><th class="num">Mis en recouvrement</th><th class="num">Encaissé</th><th class="num">Taux</th></tr></thead>
            <tbody>
            <?php foreach ($recouvrement['agences'] as $agence => $mois): ?>
                <?php
                $ouvert = array_sum(array_column($mois, 'ouvert'));
                $recupere = array_sum(array_column($mois, 'recupere'));
                ?>
                <tr>
                    <td><?= e($agence) ?></td>
                    <td class="num"><?= money($ouvert) ?></td>
                    <td class="num"><?= money($recupere) ?></td>
                    <td class="num gras"><?= $ouvert > 0 ? number_format($recupere / $ouvert * 100, 1, ',', ' ') . ' %' : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
