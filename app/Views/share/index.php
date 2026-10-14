<?php
/**
 * Liste des litiges en consultation publique (lecture seule).
 * @var array $partage
 * @var array $litiges
 * @var array $filtres
 * @var array $statuts
 */
?>
<div class="flex mb-2">
    <div>
        <h2 style="margin-bottom:2px">Litiges consultables</h2>
        <div class="petit texte-doux">
            Périmètre : <strong><?= e((new \App\Models\Partage())->scopeLabel($partage)) ?></strong>
        </div>
    </div>
    <div class="esp"></div>
    <span class="petit texte-doux"><?= (int) $total ?> litige(s)</span>
</div>

<div class="card">
    <div class="card-corps">
        <form method="get" action="<?= url('/partage/consultation') ?>">
            <div class="filtres">
                <div>
                    <label>Recherche</label>
                    <input type="search" name="q" value="<?= e($filtres['q']) ?>" placeholder="Référence, client, objet…">
                </div>
                <div>
                    <label>Statut</label>
                    <select name="statut_id">
                        <option value="">Tous</option>
                        <?php foreach ($statuts as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= (int) ($filtres['statut_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['libelle']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>État</label>
                    <select name="etat">
                        <option value="">Tous</option>
                        <option value="ouverts" <?= ($filtres['etat'] ?? '') === 'ouverts' ? 'selected' : '' ?>>Ouverts</option>
                        <option value="clotures" <?= ($filtres['etat'] ?? '') === 'clotures' ? 'selected' : '' ?>>Clôturés</option>
                    </select>
                </div>
                <div class="flex">
                    <button type="submit" class="btn">Filtrer</button>
                    <a href="<?= url('/partage/consultation') ?>" class="btn btn-secondaire">Réinitialiser</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <?php if (empty($litiges)): ?>
        <div class="vide"><div class="icone">⚖️</div><p>Aucun litige ne correspond à ces critères.</p></div>
    <?php else: ?>
        <table class="tableau">
            <thead>
                <tr>
                    <th>Référence</th><th>Client</th><th>Objet</th><th>Statut</th>
                    <th>Ouverture</th><th>Échéance</th><th class="num">Encours</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($litiges as $l): ?>
                <tr>
                    <td class="mono gras"><?= e($l['reference']) ?></td>
                    <td><?= e($l['raison_sociale']) ?></td>
                    <td><?= e(truncate($l['objet'], 48)) ?></td>
                    <td><span class="badge" style="background:<?= e($l['statut_couleur']) ?>"><?= e($l['statut_libelle']) ?></span>
                        <?php if (!empty($l['finalite_libelle'])): ?>
                            <div class="petit texte-doux">↳ <?= e($l['finalite_libelle']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="petit"><?= date_fr($l['date_ouverture']) ?></td>
                    <td class="petit"><?= date_fr($l['date_echeance']) ?></td>
                    <td class="num gras"><?= money($l['encours']) ?></td>
                    <td class="actions"><a href="<?= url('/partage/consultation/' . (int) $l['id']) ?>" class="btn-lien">Consulter</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php include __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</div>
