<?php
/** @var array $statuts @var array $finalites */
$transitions = $transitions ?? [];
$reglesStatut = $reglesStatut ?? [];
$reglesDisponibles = $reglesDisponibles ?? [];
$tousStatuts = $statuts;
$bloc = function (string $categorie, array $items) use ($transitions, $reglesStatut, $reglesDisponibles, $tousStatuts): void { ?>
    <div class="card">
        <div class="card-entete">
            <h3><?= $categorie === 'statut' ? 'Statuts (étapes du litige)' : 'Finalités (issues de clôture)' ?></h3>
            <div class="esp"></div>
            <details>
                <summary class="btn btn-petit" style="cursor:pointer;list-style:none">+ Ajouter</summary>
                <form method="post" action="<?= url('/parametres/statuts') ?>" class="mt-2" style="min-width:460px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="categorie" value="<?= e($categorie) ?>">
                    <div class="ligne-champs">
                        <div class="form-groupe"><label>Libellé <span class="req">*</span></label><input type="text" name="libelle" required></div>
                        <div class="form-groupe"><label>Code</label><input type="text" name="code" placeholder="auto si vide"></div>
                        <div class="form-groupe"><label>Couleur</label><input type="color" name="couleur" value="#0d6efd" style="height:40px"></div>
                        <div class="form-groupe"><label>Ordre</label><input type="number" name="ordre" value="0"></div>
                    </div>
                    <div class="flex mb-2">
                        <label class="check"><input type="checkbox" name="actif" value="1" checked> Actif</label>
                        <label class="check"><input type="checkbox" name="cloturant" value="1"> Clôturant</label>
                    </div>
                    <button type="submit" class="btn btn-petit">Créer</button>
                </form>
            </details>
        </div>
        <?php if (empty($items)): ?>
            <div class="card-corps texte-doux">Aucun élément.</div>
        <?php else: ?>
            <table class="tableau">
                <thead><tr><th>Libellé</th><th>Code</th><th>Couleur</th><th class="num">Ordre</th><th>Clôturant</th><th>Actif</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($items as $s): ?>
                    <tr id="statut-<?= (int) $s['id'] ?>">
                        <td><span class="badge" style="background:<?= e($s['couleur']) ?>"><?= e($s['libelle']) ?></span>
                            <?php if ((int) $s['systeme'] === 1): ?><span class="petit texte-doux">(système)</span><?php endif; ?>
                        </td>
                        <td class="mono petit"><?= e($s['code']) ?></td>
                        <td><span class="pastille" style="background:<?= e($s['couleur']) ?>"></span> <span class="petit mono"><?= e($s['couleur']) ?></span></td>
                        <td class="num petit"><?= (int) $s['ordre'] ?></td>
                        <td class="petit"><?= (int) $s['cloturant'] === 1 ? 'Oui' : '—' ?></td>
                        <td class="petit"><?= (int) $s['actif'] === 1 ? 'Oui' : 'Non' ?></td>
                        <td class="actions">
                            <details>
                                <summary class="btn-lien" style="cursor:pointer">Modifier</summary>
                                <form method="post" action="<?= url('/parametres/statuts/' . (int) $s['id']) ?>" class="mt-2" style="min-width:460px">
                                    <?= csrf_field() ?>
                                    <div class="ligne-champs">
                                        <div class="form-groupe"><label>Libellé</label><input type="text" name="libelle" value="<?= e($s['libelle']) ?>" required></div>
                                        <div class="form-groupe"><label>Code</label><input type="text" name="code" value="<?= e($s['code']) ?>" <?= (int) $s['systeme'] === 1 ? 'readonly' : '' ?>></div>
                                        <div class="form-groupe"><label>Couleur</label><input type="color" name="couleur" value="<?= e($s['couleur']) ?>" style="height:40px"></div>
                                        <div class="form-groupe"><label>Ordre</label><input type="number" name="ordre" value="<?= (int) $s['ordre'] ?>"></div>
                                    </div>
                                    <div class="flex mb-2">
                                        <label class="check"><input type="checkbox" name="actif" value="1" <?= (int) $s['actif'] === 1 ? 'checked' : '' ?>> Actif</label>
                                        <label class="check"><input type="checkbox" name="cloturant" value="1" <?= (int) $s['cloturant'] === 1 ? 'checked' : '' ?>> Clôturant</label>
                                    </div>
                                    <button type="submit" class="btn btn-petit">Enregistrer</button>
                                </form>
                            </details>
                            <?php if ($categorie === 'statut'): ?>
                                <?php $reglesCourantes = $reglesStatut[(int) $s['id']] ?? []; $vers = $transitions[(int) $s['id']] ?? []; ?>
                                <details>
                                    <summary class="btn-lien" style="cursor:pointer">Workflow<?= ($vers || $reglesCourantes) ? ' ●' : '' ?></summary>
                                    <form method="post" action="<?= url('/parametres/statuts/' . (int) $s['id'] . '/workflow') ?>" class="mt-2" style="min-width:460px">
                                        <?= csrf_field() ?>
                                        <p class="aide"><strong>Passages autorisés depuis ce statut</strong> — ne rien cocher autorise tous les statuts.</p>
                                        <div class="flex mb-2" style="flex-wrap:wrap;gap:6px 14px">
                                            <?php foreach ($tousStatuts as $cible): ?>
                                                <?php if ((int) $cible['id'] === (int) $s['id']) { continue; } ?>
                                                <label class="check"><input type="checkbox" name="transitions[]" value="<?= (int) $cible['id'] ?>" <?= in_array((int) $cible['id'], $vers, true) ? 'checked' : '' ?>> <?= e($cible['libelle']) ?></label>
                                            <?php endforeach; ?>
                                        </div>
                                        <p class="aide"><strong>Pré-requis pour entrer dans ce statut</strong></p>
                                        <div class="mb-2">
                                            <?php foreach ($reglesDisponibles as $cle => $libelleRegle): ?>
                                                <label class="check"><input type="checkbox" name="regles[]" value="<?= e($cle) ?>" <?= in_array($cle, $reglesCourantes, true) ? 'checked' : '' ?>> Exiger <?= e($libelleRegle) ?></label>
                                            <?php endforeach; ?>
                                        </div>
                                        <button type="submit" class="btn btn-petit">Enregistrer le workflow</button>
                                    </form>
                                </details>
                            <?php endif; ?>
                            <?php if ((int) $s['systeme'] !== 1): ?>
                                <form method="post" action="<?= url('/parametres/statuts/' . (int) $s['id'] . '/supprimer') ?>" class="inline" data-confirmer="Supprimer cet élément ?">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn-lien rouge">Suppr.</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php };
?>
<?php $bloc('statut', $statuts); ?>
<?php $bloc('finalite', $finalites); ?>
