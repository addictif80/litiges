<?php /** @var array $clients */ ?>
<div class="flex mb-2">
    <a href="<?= url('/clients/nouveau') ?>" class="btn">+ Nouveau client</a>
    <div class="esp"></div>
    <span class="petit texte-doux"><?= (int) $total ?> client(s)</span>
</div>

<div class="card">
    <div class="card-corps">
        <form method="get" action="<?= url('/clients') ?>" class="flex">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher par raison sociale, SIREN, SIRET, ville…" style="max-width:420px">
            <button type="submit" class="btn">Rechercher</button>
            <?php if ($q !== ''): ?><a href="<?= url('/clients') ?>" class="btn btn-secondaire">Réinitialiser</a><?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <?php if (empty($clients)): ?>
        <div class="vide"><div class="icone">🏢</div><p>Aucun client trouvé.</p></div>
    <?php else: ?>
        <table class="tableau">
            <thead>
                <tr>
                    <th>Raison sociale</th><th>SIREN</th><th>Ville</th>
                    <th class="num">Litiges</th><th class="num">Encours</th><th>Situation</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($clients as $c): ?>
                <tr>
                    <td><a href="<?= url('/clients/' . (int) $c['id']) ?>" class="gras"><?= e($c['raison_sociale']) ?></a>
                        <?php if ($c['forme_juridique']): ?><div class="petit texte-doux"><?= e($c['forme_juridique']) ?></div><?php endif; ?>
                    </td>
                    <td class="mono petit"><?= e($c['siren'] ?: '—') ?></td>
                    <td><?= e($c['ville'] ?: '—') ?></td>
                    <td class="num"><?= (int) $c['nb_litiges'] ?></td>
                    <td class="num <?= (float) $c['encours'] > 0 ? 'texte-rouge gras' : '' ?>"><?= money($c['encours']) ?></td>
                    <td>
                        <?php if (!empty($c['procedure_collective'])): ?>
                            <span class="badge" style="background:var(--rouge)">Procédure collective</span>
                        <?php elseif (!empty($c['statut_entreprise']) && stripos((string) $c['statut_entreprise'], 'cess') !== false): ?>
                            <span class="badge" style="background:var(--gris)"><?= e($c['statut_entreprise']) ?></span>
                        <?php else: ?>
                            <span class="petit texte-vert">● <?= e($c['statut_entreprise'] ?: 'Actif') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="actions"><a href="<?= url('/clients/' . (int) $c['id']) ?>" class="btn-lien">Ouvrir</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php $pageParam = 'page'; include __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</div>
