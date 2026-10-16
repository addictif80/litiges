<?php
/**
 * Creation / edition d'un modele de courrier.
 * @var array $modele
 * @var string $mode
 * @var array<string,string> $variables
 * @var array<string,string> $types
 */
$edition = $mode === 'edition';
$action = $edition ? url('/parametres/modeles/' . (int) $modele['id'] . '/modifier') : url('/parametres/modeles');
$v = static fn (string $cle, mixed $defaut = '') => old($cle, $modele[$cle] ?? $defaut);
$err = static fn (string $cle): ?string => $errors[$cle] ?? null;
?>
<div class="fil"><a href="<?= url('/parametres/modeles') ?>">Modèles de courriers</a> / <?= $edition ? 'Modifier' : 'Nouveau' ?></div>

<form method="post" action="<?= e($action) ?>" class="grille" style="grid-template-columns:2fr 1fr;align-items:start">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-corps">
            <div class="ligne-champs">
                <div class="form-groupe">
                    <label for="nom">Nom du modèle <span class="req">*</span></label>
                    <input type="text" id="nom" name="nom" maxlength="150" required value="<?= e($v('nom')) ?>">
                    <?php if ($err('nom')): ?><div class="msg-erreur"><?= e($err('nom')) ?></div><?php endif; ?>
                </div>
                <div class="form-groupe">
                    <label for="code">Code <span class="req">*</span></label>
                    <input type="text" id="code" name="code" maxlength="40" required value="<?= e($v('code')) ?>" <?= ($edition && (int) ($modele['systeme'] ?? 0) === 1) ? 'readonly' : '' ?>>
                    <?php if ($err('code')): ?><div class="msg-erreur"><?= e($err('code')) ?></div><?php endif; ?>
                </div>
                <div class="form-groupe">
                    <label for="type">Type</label>
                    <select id="type" name="type">
                        <?php foreach ($types as $k => $lib): ?>
                            <option value="<?= e($k) ?>" <?= $v('type') === $k ? 'selected' : '' ?>><?= e($lib) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-groupe">
                <label for="sujet">Objet du courrier</label>
                <input type="text" id="sujet" name="sujet" maxlength="190" value="<?= e($v('sujet')) ?>">
            </div>
            <div class="form-groupe">
                <label for="contenu">Contenu (HTML simple : p, br, strong, ul, li, table…) <span class="req">*</span></label>
                <textarea id="contenu" name="contenu" rows="22" class="mono" style="font-size:13px" required><?= e($v('contenu')) ?></textarea>
                <?php if ($err('contenu')): ?><div class="msg-erreur"><?= e($err('contenu')) ?></div><?php endif; ?>
            </div>
            <label class="check"><input type="checkbox" name="actif" value="1" <?= (int) $v('actif', 1) === 1 ? 'checked' : '' ?>> Modèle actif (proposé à la génération)</label>
        </div>
        <div class="card-pied flex">
            <button type="submit" class="btn btn-vert">Enregistrer</button>
            <a href="<?= url('/parametres/modeles') ?>" class="btn btn-secondaire">Annuler</a>
        </div>
    </div>

    <div class="card">
        <div class="card-entete"><h3>Variables disponibles</h3></div>
        <div class="card-corps">
            <p class="aide">Écrivez-les entre doubles accolades, par exemple <code>{{client.raison_sociale}}</code>. Les valeurs sont insérées automatiquement.</p>
            <table class="tableau tableau-compact">
                <?php foreach ($variables as $cle => $description): ?>
                    <tr><td class="mono petit" style="white-space:nowrap">{{<?= e($cle) ?>}}</td><td class="petit texte-doux"><?= e($description) ?></td></tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</form>
