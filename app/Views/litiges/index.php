<?php
/**
 * Liste des litiges + filtres, filtres enregistres, actions groupees, exports.
 * @var array $litiges
 * @var array $filtres
 * @var array|null $client
 * @var array $criteres
 * @var array $filtresSauvegardes
 */
$q = $_GET;
unset($q['page'], $q['format'], $q['filtre']);
$urlExport = static fn (string $format): string => url('/litiges') . '?' . http_build_query(array_merge($q, ['format' => $format]));
$peutEcrire = \App\Core\Auth::canWrite();
$estAdmin = \App\Core\Auth::isAdmin();

// Tri multi-criteres : "priorite,echeance" => tri principal + tri secondaire.
$parties = explode(',', (string) $filtres['tri']);
$tri1 = $parties[0] ?? 'recent';
$tri2 = $parties[1] ?? '';
?>
<div class="flex mb-2">
    <?php if ($peutEcrire): ?><a href="<?= url('/litiges/nouveau') ?>" class="btn">+ Nouveau litige</a><?php endif; ?>
    <details style="position:relative">
        <summary class="btn btn-secondaire" style="cursor:pointer;list-style:none">⬇ Exporter ▾</summary>
        <div class="card" style="position:absolute;z-index:20;min-width:210px;margin:4px 0 0">
            <div class="card-corps" style="padding:6px">
                <a href="<?= e($urlExport('csv')) ?>" style="display:block;padding:8px 10px">CSV (Excel)</a>
                <?php if (!empty($xlsxDisponible)): ?><a href="<?= e($urlExport('xlsx')) ?>" style="display:block;padding:8px 10px">Excel (.xlsx)</a><?php endif; ?>
                <a href="<?= e($urlExport('pdf')) ?>" style="display:block;padding:8px 10px" target="_blank" rel="noopener">PDF / impression</a>
            </div>
        </div>
    </details>
    <?php if ($peutEcrire): ?><a href="<?= url('/import') ?>" class="btn btn-secondaire">⬆ Importer</a><?php endif; ?>
    <div class="esp"></div>
    <span class="petit texte-doux"><?= (int) $total ?> litige(s)</span>
</div>

<div class="card">
    <div class="card-entete">
        <h3>Filtres</h3>
        <div class="esp"></div>
        <?php if (!empty($filtresSauvegardes)): ?>
            <form method="get" action="<?= url('/litiges') ?>" class="flex" style="gap:6px">
                <select name="filtre" aria-label="Mes filtres enregistrés" style="width:auto">
                    <option value="">★ Mes filtres…</option>
                    <?php foreach ($filtresSauvegardes as $f): ?>
                        <option value="<?= (int) $f['id'] ?>"><?= e($f['nom']) ?><?= (int) $f['partage'] === 1 ? ' (partagé)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-petit btn-secondaire">Appliquer</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="card-corps">
        <form method="get" action="<?= url('/litiges') ?>" id="form-filtres">
            <div class="filtres">
                <div>
                    <label>Recherche</label>
                    <input type="search" name="q" value="<?= e($filtres['q']) ?>" placeholder="Réf., client, facture…">
                </div>
                <div>
                    <label>Client</label>
                    <div data-client-picker data-nom="client_id" data-url="<?= url('/api/clients/suggestions') ?>"
                         data-valeur="<?= $client ? (int) $client['id'] : '' ?>" data-libelle="<?= e($client['raison_sociale'] ?? '') ?>"></div>
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
                        <?php foreach ($priorites as $k => $v): ?>
                            <option value="<?= e($k) ?>" <?= $filtres['priorite'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
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
                    <label>Tri principal</label>
                    <select name="tri">
                        <?php foreach ($criteres as $k => $v): ?>
                            <option value="<?= e($k) ?>" <?= $tri1 === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>puis par</label>
                    <select name="tri2">
                        <option value="">—</option>
                        <?php foreach ($criteres as $k => $v): ?>
                            <option value="<?= e($k) ?>" <?= $tri2 === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="check" style="margin-top:26px">
                        <input type="checkbox" name="relance_due" value="1" <?= !empty($filtres['relance_due']) ? 'checked' : '' ?>>
                        Relances en retard
                    </label>
                </div>
                <div>
                    <label class="check" style="margin-top:26px">
                        <input type="checkbox" name="promesse_depassee" value="1" <?= !empty($filtres['promesse_depassee']) ? 'checked' : '' ?>>
                        Promesses non tenues
                    </label>
                </div>
                <div class="flex">
                    <button type="submit" class="btn">Filtrer</button>
                    <a href="<?= url('/litiges') ?>" class="btn btn-secondaire">Réinitialiser</a>
                </div>
            </div>
        </form>

        <details class="mt-2">
            <summary class="petit" style="cursor:pointer;color:var(--bleu-600)">★ Enregistrer ces critères comme filtre</summary>
            <form method="post" action="<?= url('/filtres') ?>" class="flex mt-1" style="flex-wrap:wrap;gap:8px">
                <?= csrf_field() ?>
                <?php foreach ($filtres as $cle => $valeur): ?>
                    <?php if ($valeur !== '' && $valeur !== 0 && $valeur !== '0' && $cle !== 'tri'): ?>
                        <input type="hidden" name="<?= e($cle) ?>" value="<?= e($valeur) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
                <input type="hidden" name="tri" value="<?= e($filtres['tri']) ?>">
                <input type="text" name="nom" placeholder="Nom du filtre (ex : Impayés critiques Lyon)" maxlength="100" required style="min-width:280px">
                <?php if ($peutEcrire): ?><label class="check"><input type="checkbox" name="partage" value="1"> Partager avec l'équipe</label><?php endif; ?>
                <button type="submit" class="btn btn-petit">Enregistrer</button>
            </form>
            <?php if (!empty($filtresSauvegardes)): ?>
                <div class="petit mt-1">
                    Mes filtres :
                    <?php foreach ($filtresSauvegardes as $f): ?>
                        <span style="margin-right:10px"><a href="<?= url('/litiges?filtre=' . (int) $f['id']) ?>"><?= e($f['nom']) ?></a>
                        <?php if ($estAdmin || (int) $f['user_id'] === (int) \App\Core\Auth::id()): ?>
                            <form method="post" action="<?= url('/filtres/' . (int) $f['id'] . '/supprimer') ?>" class="inline" data-confirmer="Supprimer ce filtre ?">
                                <?= csrf_field() ?><button type="submit" class="btn-lien rouge" title="Supprimer">✕</button>
                            </form>
                        <?php endif; ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </details>
    </div>
</div>

<?php if ($peutEcrire): ?>
<form method="post" action="<?= url('/litiges/actions-groupees') ?>" id="form-groupe" data-confirmer="Appliquer cette modification aux litiges sélectionnés ?"></form>
<div class="card" data-barre-groupee hidden>
    <div class="card-corps flex" style="flex-wrap:wrap;gap:10px;align-items:center" data-bloc-scope>
        <strong><span data-nb-selection>0</span> sélectionné(s)</strong>
        <input type="hidden" name="_token" form="form-groupe" value="<?= e(\App\Core\Csrf::token()) ?>">
        <select name="action" form="form-groupe" data-bloc-switch style="width:auto">
            <option value="responsable">Changer le gestionnaire</option>
            <option value="priorite">Changer la priorité</option>
            <option value="statut">Changer le statut</option>
        </select>
        <span data-bloc="responsable">
            <select name="responsable_id" form="form-groupe" style="width:auto">
                <option value="">— Non affecté —</option>
                <?php foreach ($responsables as $u): ?>
                    <option value="<?= (int) $u['id'] ?>"><?= e($u['full_name'] ?: $u['username']) ?></option>
                <?php endforeach; ?>
            </select>
        </span>
        <span data-bloc="priorite" hidden>
            <select name="priorite" form="form-groupe" style="width:auto">
                <?php foreach ($priorites as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
            </select>
        </span>
        <span data-bloc="statut" hidden>
            <select name="statut_id" form="form-groupe" style="width:auto">
                <?php foreach ($statuts as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['libelle']) ?></option><?php endforeach; ?>
            </select>
        </span>
        <?php if ($estAdmin): ?><label class="check"><input type="checkbox" name="forcer_workflow" value="1" form="form-groupe"> Forcer le workflow</label><?php endif; ?>
        <button type="submit" form="form-groupe" class="btn btn-petit">Appliquer</button>
    </div>
</div>
<?php endif; ?>

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
                    <?php if ($peutEcrire): ?><th style="width:28px"><input type="checkbox" data-cocher-tout aria-label="Tout sélectionner"></th><?php endif; ?>
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
                    <?php if ($peutEcrire): ?><td><input type="checkbox" name="ids[]" value="<?= (int) $l['id'] ?>" form="form-groupe" aria-label="Sélectionner <?= e($l['reference']) ?>"></td><?php endif; ?>
                    <td>
                        <a href="<?= url('/litiges/' . (int) $l['id']) ?>" class="gras mono"><?= e($l['reference']) ?></a>
                        <?php if (!empty($l['numero_facture'])): ?>
                            <div class="petit texte-doux">Fact. <?= e($l['numero_facture']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><a href="<?= url('/clients/' . (int) $l['client_id']) ?>"><?= e($l['raison_sociale']) ?></a></td>
                    <td><?= e(truncate($l['objet'], 48)) ?>
                        <?php if (!empty($l['type_litige'])): ?><div class="petit texte-doux"><?= e($types[$l['type_litige']] ?? $l['type_litige']) ?></div><?php endif; ?>
                    </td>
                    <td>
                        <span class="badge" style="background:<?= e($l['statut_couleur']) ?>"><?= e($l['statut_libelle']) ?></span>
                        <?php if (!empty($l['finalite_libelle'])): ?>
                            <div class="petit texte-doux" style="margin-top:3px">↳ <?= e($l['finalite_libelle']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-priorite-<?= e($l['priorite']) ?>"><?= e($priorites[$l['priorite']] ?? $l['priorite']) ?></span></td>
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
