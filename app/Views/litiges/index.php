<?php
/**
 * Liste des litiges + filtres.
 * @var array $litiges
 * @var array $filtres
 */
$q = $_GET;
unset($q['page']);
$exportUrl = url('/litiges') . '?' . http_build_query(array_merge($q, ['format' => 'csv']));
?>
<div class="flex mb-2">
    <a href="<?= url('/litiges/nouveau') ?>" class="btn">+ Nouveau litige</a>
    <a href="<?= e($exportUrl) ?>" class="btn btn-secondaire">⬇ Exporter (CSV)</a>
    <div class="esp"></div>
    <span class="petit texte-doux"><?= (int) $total ?> litige(s)</span>
</div>

<div class="card">
    <div class="card-entete"><h3>Filtres</h3></div>
    <div class="card-corps">
        <form method="get" action="<?= url('/litiges') ?>">
            <div class="filtres">
                <div>
                    <label>Recherche</label>
                    <input type="search" name="q" value="<?= e($filtres['q']) ?>" placeholder="Réf., client, facture…">
                </div>
                <div>
                    <label>Statut</label>
                    <select name="statut_id">
                        <option value="">Tous</option>
                        <?php foreach ($statuts as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= (int) $filtres['statut_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['libelle']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>État</label>
                    <select name="etat">
                        <option value="">Tous</option>
                        <option value="ouverts" <?= $filtres['etat'] === 'ouverts' ? 'selected' : '' ?>>Ouverts</option>
                        <option value="clotures" <?= $filtres['etat'] === 'clotures' ? 'selected' : '' ?>>Clôturés</option>
                    </select>
                </div>
                <div>
                    <label>Agence</label>
                    <select name="agence_id">
                        <option value="">Toutes</option>
                        <?php foreach ($agences as $a): ?>
                            <option value="<?= (int) $a['id'] ?>" <?= (int) $filtres['agence_id'] === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Gestionnaire</label>
                    <select name="responsable_id">
                        <option value="">Tous</option>
                        <?php foreach ($responsables as $u): ?>
                            <option value="<?= (int) $u['id'] ?>" <?= (int) $filtres['responsable_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['full_name'] ?: $u['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Priorité</label>
                    <select name="priorite">
                        <option value="">Toutes</option>
                        <?php foreach (['basse' => 'Basse', 'normale' => 'Normale', 'haute' => 'Haute', 'critique' => 'Critique'] as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $filtres['priorite'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Type</label>
                    <select name="type_litige">
                        <option value="">Tous</option>
                        <?php foreach ($types as $k => $v): ?>
                            <option value="<?= e($k) ?>" <?= $filtres['type_litige'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Ouverture du</label>
                    <input type="date" name="date_from" value="<?= e($filtres['date_from']) ?>">
                </div>
                <div>
                    <label>au</label>
                    <input type="date" name="date_to" value="<?= e($filtres['date_to']) ?>">
                </div>
                <div>
                    <label>Tri</label>
                    <select name="tri">
                        <?php foreach (['recent' => 'Plus récents', 'ancien' => 'Plus anciens', 'montant' => 'Encours décroissant', 'montant_asc' => 'Encours croissant', 'echeance' => 'Échéance', 'client' => 'Client', 'priorite' => 'Priorité'] as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $filtres['tri'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="check" style="margin-top:26px">
                        <input type="checkbox" name="relance_due" value="1" <?= !empty($filtres['relance_due']) ? 'checked' : '' ?>>
                        Relances en retard
                    </label>
                </div>
                <div class="flex">
                    <button type="submit" class="btn">Filtrer</button>
                    <a href="<?= url('/litiges') ?>" class="btn btn-secondaire">Réinitialiser</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <?php if (empty($litiges)): ?>
        <div class="vide">
            <div class="icone">⚖️</div>
            <p>Aucun litige ne correspond à ces critères.</p>
        </div>
    <?php else: ?>
        <table class="tableau">
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Client</th>
                    <th>Objet</th>
                    <th>Statut</th>
                    <th>Priorité</th>
                    <th>Ouverture</th>
                    <th>Échéance / relance</th>
                    <th class="num">Encours</th>
                    <th>Gestionnaire</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($litiges as $l): ?>
                <?php
                $retard = !empty($l['prochaine_relance_at'])
                    && !(int) $l['statut_cloturant']
                    && strtotime((string) $l['prochaine_relance_at']) <= strtotime('today');
                ?>
                <tr>
                    <td>
                        <a href="<?= url('/litiges/' . (int) $l['id']) ?>" class="gras mono"><?= e($l['reference']) ?></a>
                        <?php if (!empty($l['numero_facture'])): ?>
                            <div class="petit texte-doux">Fact. <?= e($l['numero_facture']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= url('/clients/' . (int) $l['client_id']) ?>"><?= e($l['raison_sociale']) ?></a>
                        <?php if (!empty($l['procedure_collective_libelle'])): ?><?php endif; ?>
                    </td>
                    <td><?= e(truncate($l['objet'], 48)) ?>
                        <?php if (!empty($l['type_litige'])): ?><div class="petit texte-doux"><?= e($types[$l['type_litige']] ?? $l['type_litige']) ?></div><?php endif; ?>
                    </td>
                    <td>
                        <span class="badge" style="background:<?= e($l['statut_couleur']) ?>"><?= e($l['statut_libelle']) ?></span>
                        <?php if (!empty($l['finalite_libelle'])): ?>
                            <div class="petit texte-doux" style="margin-top:3px">↳ <?= e($l['finalite_libelle']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-priorite-<?= e($l['priorite']) ?>"><?= e(ucfirst($l['priorite'])) ?></span></td>
                    <td class="petit"><?= date_fr($l['date_ouverture']) ?></td>
                    <td class="petit">
                        <?php if (!empty($l['date_echeance'])): ?>
                            <div>Éch. <?= date_fr($l['date_echeance']) ?></div>
                        <?php endif; ?>
                        <?php if (!$retard && !empty($l['prochaine_relance_at'])): ?>
                            <div class="texte-doux">Relance <?= date_fr($l['prochaine_relance_at']) ?></div>
                        <?php elseif ($retard): ?>
                            <div class="texte-rouge gras">⚠ Relance due <?= date_fr($l['prochaine_relance_at']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="num gras"><?= money($l['encours']) ?></td>
                    <td class="petit"><?= e($l['responsable_nom'] ?: $l['responsable_username'] ?: '—') ?></td>
                    <td class="actions">
                        <a href="<?= url('/litiges/' . (int) $l['id']) ?>" class="btn-lien">Ouvrir</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        $page = $page;
        $pages = $pages;
        include __DIR__ . '/../partials/pagination.php';
        ?>
    <?php endif; ?>
</div>
