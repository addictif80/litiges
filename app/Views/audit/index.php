<?php
/**
 * Journal d'audit global (administrateurs).
 * @var array $lignes
 * @var array $filtres
 * @var array $actions
 * @var array $users
 */
$params = $_GET;
unset($params['page']);
$exportUrl = url('/audit') . '?' . http_build_query(array_merge($params, ['format' => 'csv']));
?>
<div class="flex mb-2">
    <a href="<?= e($exportUrl) ?>" class="btn btn-secondaire">⬇ Exporter (CSV)</a>
    <div class="esp"></div>
    <span class="petit texte-doux"><?= (int) $total ?> évènement(s)</span>
</div>

<div class="card">
    <div class="card-entete"><h3>Filtres</h3></div>
    <div class="card-corps">
        <form method="get" action="<?= url('/audit') ?>">
            <div class="filtres">
                <div>
                    <label>Recherche</label>
                    <input type="search" name="q" value="<?= e($filtres['q']) ?>" placeholder="Utilisateur, action, IP, détails…">
                </div>
                <div>
                    <label>Action</label>
                    <select name="action">
                        <option value="">Toutes</option>
                        <?php foreach ($actions as $a): ?>
                            <option value="<?= e($a['action']) ?>" <?= $filtres['action'] === $a['action'] ? 'selected' : '' ?>><?= e($a['action']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Utilisateur</label>
                    <select name="user_id">
                        <option value="">Tous</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int) $u['id'] ?>" <?= (int) $filtres['user_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['full_name'] ?: $u['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Du</label>
                    <input type="date" name="date_from" value="<?= e($filtres['date_from']) ?>">
                </div>
                <div>
                    <label>au</label>
                    <input type="date" name="date_to" value="<?= e($filtres['date_to']) ?>">
                </div>
                <div class="flex">
                    <button type="submit" class="btn">Filtrer</button>
                    <a href="<?= url('/audit') ?>" class="btn btn-secondaire">Réinitialiser</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <?php if (empty($lignes)): ?>
        <div class="vide"><div class="icone">📋</div><p>Aucun évènement pour ces critères.</p></div>
    <?php else: ?>
        <table class="tableau">
            <thead>
                <tr>
                    <th>Date</th><th>Utilisateur</th><th>Action</th><th>Entité</th><th class="num">ID</th><th>IP</th><th>Détails</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lignes as $l): ?>
                <tr>
                    <td class="petit" style="white-space:nowrap"><?= datetime_fr($l['created_at']) ?></td>
                    <td class="petit"><?= e($l['username'] ?: '—') ?></td>
                    <td><span class="badge badge-contour"><?= e($l['action']) ?></span></td>
                    <td class="petit"><?= e($l['entite'] ?: '—') ?></td>
                    <td class="num petit"><?= $l['entite_id'] !== null ? (int) $l['entite_id'] : '—' ?></td>
                    <td class="mono petit"><?= e($l['ip'] ?: '—') ?></td>
                    <td class="petit texte-doux">
                        <?php if (!empty($l['details'])): ?>
                            <span title="<?= e($l['details']) ?>"><?= e(truncate($l['details'], 80)) ?></span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php include __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</div>
