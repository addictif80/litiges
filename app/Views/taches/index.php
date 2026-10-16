<?php
/**
 * Liste des taches.
 * @var array $taches
 * @var string $vue
 * @var string $statutFiltre
 * @var array $utilisateurs
 * @var array $priorites
 * @var array $statuts
 */
use App\Models\Tache;

$peutEcrire = \App\Core\Auth::canWrite();
$aujourdhui = date('Y-m-d');
$onglet = static fn (string $cle, string $lib): string =>
    '<a href="' . e(url('/taches?vue=' . $cle . '&statut=' . urlencode($statutFiltre))) . '" class="btn btn-petit '
    . ($vue === $cle ? '' : 'btn-secondaire') . '">' . e($lib) . '</a>';
?>
<div class="flex mb-2" style="flex-wrap:wrap">
    <?= $onglet('moi', 'Mes tâches') ?>
    <?= $onglet('retard', 'En retard') ?>
    <?= $onglet('toutes', 'Toutes') ?>
    <form method="get" action="<?= url('/taches') ?>" class="flex">
        <input type="hidden" name="vue" value="utilisateur">
        <select name="utilisateur" aria-label="Tâches d'un utilisateur" style="width:auto">
            <option value="">Par personne…</option>
            <?php foreach ($utilisateurs as $u): ?>
                <option value="<?= (int) $u['id'] ?>"><?= e($u['full_name'] ?: $u['username']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-petit btn-secondaire">Voir</button>
    </form>
    <div class="esp"></div>
    <a href="<?= url('/taches?vue=' . urlencode($vue) . '&statut=' . ($statutFiltre === 'ouvertes' ? 'faite' : 'ouvertes')) ?>" class="petit">
        <?= $statutFiltre === 'ouvertes' ? 'Voir les tâches terminées' : 'Voir les tâches ouvertes' ?>
    </a>
</div>

<?php if ($peutEcrire): ?>
<div class="card">
    <div class="card-entete"><h3>Nouvelle tâche</h3></div>
    <form method="post" action="<?= url('/taches') ?>">
        <?= csrf_field() ?>
        <div class="card-corps">
            <div class="ligne-champs">
                <div class="form-groupe" style="grid-column:span 2">
                    <label for="titre">À faire</label>
                    <input type="text" id="titre" name="titre" maxlength="190" required>
                </div>
                <div class="form-groupe">
                    <label for="assigne_a">Assignée à</label>
                    <select id="assigne_a" name="assigne_a">
                        <?php foreach ($utilisateurs as $u): ?>
                            <option value="<?= (int) $u['id'] ?>" <?= (int) $u['id'] === (int) $authUser['id'] ? 'selected' : '' ?>><?= e($u['full_name'] ?: $u['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-groupe">
                    <label for="echeance">Échéance</label>
                    <input type="date" id="echeance" name="echeance">
                </div>
                <div class="form-groupe">
                    <label for="priorite">Priorité</label>
                    <select id="priorite" name="priorite">
                        <?php foreach ($priorites as $k => $lib): ?>
                            <option value="<?= e($k) ?>" <?= $k === 'normale' ? 'selected' : '' ?>><?= e($lib) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-groupe mb-0">
                <label for="description">Détail (facultatif)</label>
                <textarea id="description" name="description" rows="2"></textarea>
            </div>
        </div>
        <div class="card-pied"><button type="submit" class="btn">Ajouter</button></div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <?php if (empty($taches)): ?>
        <div class="vide"><div class="icone">✅</div><p>Aucune tâche à afficher.</p></div>
    <?php else: ?>
        <table class="tableau">
            <thead><tr><th>Tâche</th><th>Litige / client</th><th>Assignée à</th><th>Échéance</th><th>Priorité</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($taches as $t): ?>
                <?php $enRetard = $t['statut'] !== 'faite' && $t['echeance'] && $t['echeance'] < $aujourdhui; ?>
                <tr>
                    <td style="<?= $t['statut'] === 'faite' ? 'text-decoration:line-through;opacity:.6' : '' ?>">
                        <strong><?= e($t['titre']) ?></strong>
                        <?php if ($t['description']): ?><div class="petit texte-doux"><?= e(truncate($t['description'], 100)) ?></div><?php endif; ?>
                    </td>
                    <td class="petit">
                        <?php if ($t['litige_id']): ?><a href="<?= url('/litiges/' . (int) $t['litige_id'] . '#taches') ?>" class="mono"><?= e($t['litige_reference']) ?></a><?php endif; ?>
                        <?php if ($t['client_nom']): ?><div class="texte-doux"><?= e($t['client_nom']) ?></div><?php endif; ?>
                    </td>
                    <td class="petit"><?= e($t['assigne_nom'] ?: $t['assigne_username'] ?: '—') ?></td>
                    <td class="petit <?= $enRetard ? 'texte-rouge gras' : '' ?>"><?= $t['echeance'] ? date_fr($t['echeance']) : '—' ?></td>
                    <td><span class="badge badge-priorite-<?= e($t['priorite']) ?>"><?= e($priorites[$t['priorite']] ?? $t['priorite']) ?></span></td>
                    <td class="petit"><?= e(Tache::STATUTS[$t['statut']] ?? $t['statut']) ?></td>
                    <td class="actions">
                        <?php if ($peutEcrire): ?>
                            <form method="post" action="<?= url('/taches/' . (int) $t['id'] . '/statut') ?>" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="statut" value="<?= $t['statut'] === 'faite' ? 'a_faire' : 'faite' ?>">
                                <button type="submit" class="btn-lien"><?= $t['statut'] === 'faite' ? 'Rouvrir' : '✔ Terminer' ?></button>
                            </form>
                            <form method="post" action="<?= url('/taches/' . (int) $t['id'] . '/supprimer') ?>" class="inline" data-confirmer="Supprimer cette tâche ?">
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
