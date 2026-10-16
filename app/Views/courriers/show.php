<?php
/**
 * Apercu d'un courrier genere + suivi d'envoi / accuse de reception.
 * @var array $courrier
 * @var array $litige
 * @var bool $pdf
 */
use App\Models\Courrier;

$peutEcrire = \App\Core\Auth::canWrite();
$couleursAr = ['non_envoye' => '#6c757d', 'en_attente' => '#fd7e14', 'recu' => '#198754', 'refuse' => '#dc3545', 'non_reclame' => '#dc3545'];
?>
<div class="fil">
    <a href="<?= url('/litiges') ?>">Litiges</a> /
    <a href="<?= url('/litiges/' . (int) $courrier['litige_id'] . '#courriers') ?>" class="mono"><?= e($litige['reference'] ?? '') ?></a> /
    <span class="mono"><?= e($courrier['numero']) ?></span>
</div>

<div class="flex mb-2">
    <a href="<?= url('/courriers/' . (int) $courrier['id'] . '/telecharger?format=html') ?>" target="_blank" rel="noopener" class="btn">🖨 Version imprimable</a>
    <a href="<?= url('/courriers/' . (int) $courrier['id'] . '/telecharger?format=doc') ?>" class="btn btn-secondaire">📝 Word (.doc)</a>
    <?php if ($pdf): ?><a href="<?= url('/courriers/' . (int) $courrier['id'] . '/telecharger?format=pdf') ?>" class="btn btn-secondaire">📄 PDF</a><?php endif; ?>
    <div class="esp"></div>
    <?php if ($peutEcrire): ?>
        <form method="post" action="<?= url('/courriers/' . (int) $courrier['id'] . '/supprimer') ?>" data-confirmer="Supprimer ce courrier ?">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-rouge btn-petit">Supprimer</button>
        </form>
    <?php endif; ?>
</div>

<div class="grille" style="grid-template-columns:2fr 1fr;align-items:start">
    <div class="card">
        <div class="card-entete"><h3><?= e($courrier['sujet'] ?: $courrier['numero']) ?></h3></div>
        <div class="card-corps">
            <?php /* Contenu fabrique par l'application a partir d'un modele administrateur ; les valeurs sont echappees a la fusion. */ ?>
            <div style="background:#fff;border:1px solid var(--bordure, #dde3ec);padding:24px;line-height:1.55"><?= $courrier['contenu'] ?></div>
            <p class="petit texte-doux mt-1">Une copie est archivée dans les documents du litige (catégorie « courrier »).</p>
        </div>
    </div>

    <div class="card">
        <div class="card-entete"><h3>Suivi d'envoi</h3></div>
        <form method="post" action="<?= url('/courriers/' . (int) $courrier['id'] . '/suivi') ?>">
            <?= csrf_field() ?>
            <div class="card-corps">
                <p>Statut : <span class="badge" style="background:<?= e($couleursAr[$courrier['statut_ar']] ?? '#6c757d') ?>"><?= e(Courrier::STATUTS_AR[$courrier['statut_ar']] ?? $courrier['statut_ar']) ?></span></p>
                <fieldset <?= $peutEcrire ? '' : 'disabled' ?> style="border:0;padding:0;margin:0">
                    <div class="form-groupe">
                        <label for="mode_envoi">Mode d'envoi</label>
                        <select id="mode_envoi" name="mode_envoi">
                            <option value="">—</option>
                            <?php foreach (Courrier::MODES_ENVOI as $k => $lib): ?>
                                <option value="<?= e($k) ?>" <?= $courrier['mode_envoi'] === $k ? 'selected' : '' ?>><?= e($lib) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-groupe">
                        <label for="numero_suivi">N° de suivi / recommandé</label>
                        <input type="text" id="numero_suivi" name="numero_suivi" maxlength="80" value="<?= e($courrier['numero_suivi']) ?>">
                    </div>
                    <div class="form-groupe">
                        <label for="date_envoi">Date d'envoi</label>
                        <input type="date" id="date_envoi" name="date_envoi" value="<?= e($courrier['date_envoi']) ?>">
                    </div>
                    <div class="form-groupe">
                        <label for="date_ar">Date de l'accusé de réception</label>
                        <input type="date" id="date_ar" name="date_ar" value="<?= e($courrier['date_ar']) ?>">
                    </div>
                    <div class="form-groupe">
                        <label for="statut_ar">Statut</label>
                        <select id="statut_ar" name="statut_ar">
                            <?php foreach (Courrier::STATUTS_AR as $k => $lib): ?>
                                <option value="<?= e($k) ?>" <?= $courrier['statut_ar'] === $k ? 'selected' : '' ?>><?= e($lib) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="aide">Pour une mise en demeure, la réception de l'AR fixe la prochaine relance à l'expiration du délai accordé.</div>
                    </div>
                </fieldset>
            </div>
            <?php if ($peutEcrire): ?><div class="card-pied"><button type="submit" class="btn">Enregistrer le suivi</button></div><?php endif; ?>
        </form>
    </div>
</div>
