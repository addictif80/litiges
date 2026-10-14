<?php
/**
 * Inspection d'entreprise - recherche (aucun client requis).
 * @var string $q
 * @var array $resultats
 * @var string|null $source
 * @var array $sources
 */
?>
<div class="fil"><a href="<?= url('/clients') ?>">Clients</a> / Inspecter une entreprise</div>

<div class="entete-fiche">
    <div class="infos" style="flex:1;min-width:280px">
        <h1>🔎 Inspecter une entreprise</h1>
        <div class="meta">
            <span>Recherchez une entreprise par nom, SIREN ou SIRET, même si elle n'est pas encore enregistrée comme client.</span>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-corps">
        <form method="get" action="<?= url('/entreprise') ?>" class="flex">
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Nom, SIREN ou SIRET…" style="flex:1;min-width:240px" autofocus>
            <button type="submit" class="btn">Rechercher</button>
        </form>
        <div class="petit texte-doux mt-1">
            Sources actives :
            <?php $actives = []; foreach (($sources ?? []) as $nom => $ok) { if ($ok) { $actives[] = e($nom); } } ?>
            <?= $actives ? implode(', ', $actives) : 'aucune' ?>
        </div>
    </div>
</div>

<?php if ($q !== '' && mb_strlen($q) < 2): ?>
    <div class="alerte alerte-warning">Saisissez au moins 2 caractères.</div>
<?php elseif ($q !== ''): ?>
    <div class="card">
        <div class="card-entete">
            <h3><?= count($resultats) ?> résultat(s)</h3>
            <?php if ($source): ?><div class="esp"></div><span class="petit texte-doux">source : <?= e($source) ?></span><?php endif; ?>
        </div>
        <?php if (empty($resultats)): ?>
            <div class="vide"><div class="icone">🔎</div><p>Aucune entreprise trouvée pour « <?= e($q) ?> ».</p></div>
        <?php else: ?>
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Entreprise</th>
                        <th>SIREN</th>
                        <th>Adresse</th>
                        <th>Activité</th>
                        <th>Situation</th>
                        <th class="actions"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($resultats as $r): ?>
                    <tr>
                        <td>
                            <strong><?= e($r['raison_sociale'] ?: '(sans dénomination)') ?></strong>
                            <?php if (!empty($r['forme_juridique'])): ?><div class="petit texte-doux"><?= e($r['forme_juridique']) ?></div><?php endif; ?>
                        </td>
                        <td class="mono"><?= e($r['siren'] ?? '—') ?></td>
                        <td class="petit"><?= e(trim(($r['code_postal'] ?? '') . ' ' . ($r['ville'] ?? ''))) ?: '—' ?></td>
                        <td class="petit"><?= e($r['libelle_ape'] ?: ($r['ape_naf'] ?: '—')) ?></td>
                        <td class="petit">
                            <?= e($r['statut_entreprise'] ?: '—') ?>
                            <?php if (!empty($r['procedure_collective'])): ?><div class="texte-rouge petit gras">⚠ <?= e($r['procedure_collective']) ?></div><?php endif; ?>
                        </td>
                        <td class="actions">
                            <?php if (!empty($r['siren'])): ?>
                                <a href="<?= url('/entreprise/' . rawurlencode((string) $r['siren'])) ?>" class="btn btn-petit">Inspecter</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endif; ?>
