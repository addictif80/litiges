<?php /** @var array $agences */ ?>
<div class="card">
    <div class="card-entete">
        <h3>Agences / établissements</h3>
        <div class="esp"></div>
        <details>
            <summary class="btn btn-petit" style="cursor:pointer;list-style:none">+ Nouvelle agence</summary>
            <form method="post" action="<?= url('/agences') ?>" class="mt-2" style="min-width:420px">
                <?= csrf_field() ?>
                <div class="ligne-champs">
                    <div class="form-groupe"><label>Nom <span class="req">*</span></label><input type="text" name="nom" required></div>
                    <div class="form-groupe"><label>Code</label><input type="text" name="code" placeholder="Ex : AG-LYON"></div>
                    <div class="form-groupe"><label>Ville</label><input type="text" name="ville"></div>
                    <div class="form-groupe"><label>Téléphone</label><input type="tel" name="telephone"></div>
                    <div class="form-groupe"><label>Email</label><input type="email" name="email"></div>
                    <div class="form-groupe"><label>Adresse</label><input type="text" name="adresse"></div>
                </div>
                <label class="check mb-2"><input type="checkbox" name="actif" value="1" checked> Active</label>
                <button type="submit" class="btn btn-petit">Créer</button>
            </form>
        </details>
    </div>
    <?php if (empty($agences)): ?>
        <div class="card-corps texte-doux">Aucune agence enregistrée.</div>
    <?php else: ?>
        <table class="tableau">
            <thead><tr><th>Nom</th><th>Code</th><th>Ville</th><th>Téléphone</th><th class="num">Litiges</th><th class="num">Clôturés</th><th>État</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($agences as $a): ?>
                <tr>
                    <td><strong><?= e($a['nom']) ?></strong></td>
                    <td class="mono petit"><?= e($a['code'] ?: '—') ?></td>
                    <td class="petit"><?= e($a['ville'] ?: '—') ?></td>
                    <td class="petit"><?= e($a['telephone'] ?: '—') ?></td>
                    <td class="num"><?= (int) $a['nb_litiges'] ?></td>
                    <td class="num"><?= (int) $a['nb_clotures'] ?></td>
                    <td><?= (int) $a['actif'] === 1 ? '<span class="petit texte-vert">● Active</span>' : '<span class="petit texte-doux">○ Inactive</span>' ?></td>
                    <td class="actions">
                        <details>
                            <summary class="btn-lien" style="cursor:pointer">Modifier</summary>
                            <form method="post" action="<?= url('/agences/' . (int) $a['id']) ?>" class="mt-2" style="min-width:420px">
                                <?= csrf_field() ?>
                                <div class="ligne-champs">
                                    <div class="form-groupe"><label>Nom</label><input type="text" name="nom" value="<?= e($a['nom']) ?>" required></div>
                                    <div class="form-groupe"><label>Code</label><input type="text" name="code" value="<?= e($a['code']) ?>"></div>
                                    <div class="form-groupe"><label>Ville</label><input type="text" name="ville" value="<?= e($a['ville']) ?>"></div>
                                    <div class="form-groupe"><label>Téléphone</label><input type="tel" name="telephone" value="<?= e($a['telephone']) ?>"></div>
                                    <div class="form-groupe"><label>Email</label><input type="email" name="email" value="<?= e($a['email']) ?>"></div>
                                    <div class="form-groupe"><label>Adresse</label><input type="text" name="adresse" value="<?= e($a['adresse']) ?>"></div>
                                </div>
                                <label class="check mb-2"><input type="checkbox" name="actif" value="1" <?= (int) $a['actif'] === 1 ? 'checked' : '' ?>> Active</label>
                                <button type="submit" class="btn btn-petit">Enregistrer</button>
                            </form>
                        </details>
                        <form method="post" action="<?= url('/agences/' . (int) $a['id'] . '/supprimer') ?>" class="inline" data-confirmer="Supprimer cette agence ?">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn-lien rouge">Suppr.</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
