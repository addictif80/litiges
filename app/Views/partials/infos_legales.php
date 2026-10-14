<?php
/**
 * Bloc "informations légales" réutilisable (fiche client + inspection).
 *
 * Affiche la forme juridique et le code APE AVEC leur libellé officiel, ainsi
 * que les dirigeants et, le cas échéant, les autres entreprises qui leur sont
 * rattachées.
 *
 * @var array $e  Fiche entreprise (clés de CompanyLookupService::dossier()).
 */
use App\Services\ReferentielService;

$e = is_array($e ?? null) ? $e : [];

$formeBrute = $e['forme_juridique'] ?? null;
$formeCode  = $e['forme_juridique_code'] ?? null;
if ($formeCode === null && is_string($formeBrute) && preg_match('/^\d{4}$/', $formeBrute) === 1) {
    $formeCode = $formeBrute;
}
$formeLibelle = ReferentielService::formeJuridique(is_string($formeBrute) ? $formeBrute : null);

$apeCode    = $e['ape_naf'] ?? null;
$apeLibelle = $e['libelle_ape'] ?? null;
if ($apeLibelle === null || $apeLibelle === '') {
    $apeLibelle = ReferentielService::libelleApe(is_string($apeCode) ? $apeCode : null);
}
$section = $e['section_ape_libelle'] ?? null;
if ($section === null || $section === '') {
    $section = ReferentielService::section(is_string($apeCode) ? $apeCode : null);
}

$dirigeants = $e['dirigeants_detail'] ?? $e['dirigeants'] ?? [];
$dirigeants = is_array($dirigeants) ? $dirigeants : [];
?>
<dl class="definition" style="grid-template-columns:140px 1fr">
    <dt>Forme juridique</dt>
    <dd>
        <?= e($formeLibelle ?: '—') ?>
        <?php if ($formeCode): ?><span class="texte-doux petit"> (code <?= e((string) $formeCode) ?>)</span><?php endif; ?>
    </dd>

    <dt>Code APE / NAF</dt>
    <dd><?php if ($apeCode !== null && $apeCode !== ''): ?><span class="mono"><?= e((string) $apeCode) ?></span><?php else: ?>—<?php endif; ?></dd>

    <dt>Activité</dt>
    <dd>
        <?= e($apeLibelle ?: '—') ?>
        <?php if ($section): ?><div class="texte-doux petit"><?= e($section) ?></div><?php endif; ?>
    </dd>
</dl>

<h4 class="mt-2 mb-1">Dirigeants</h4>
<?php if (empty($dirigeants)): ?>
    <div class="texte-doux petit">Aucun dirigeant renseigné.</div>
<?php else: ?>
    <ul class="liste-simple">
        <?php foreach ($dirigeants as $d): ?>
            <?php
                $nom = trim((string) ($d['prenoms'] ?? '') . ' ' . (string) ($d['nom'] ?? ''));
                if ($nom === '') {
                    $nom = (string) ($d['denomination'] ?? '—');
                }
            ?>
            <li>
                <strong><?= e($nom) ?></strong>
                <?php if (!empty($d['qualite'])): ?><span class="texte-doux"> — <?= e((string) $d['qualite']) ?></span><?php endif; ?>
                <?php if (!empty($d['annee_naissance'])): ?><span class="texte-doux petit"> (né en <?= e((string) $d['annee_naissance']) ?>)</span><?php endif; ?>
                <?php if (($d['type'] ?? '') === 'personne morale'): ?> <span class="badge badge-contour">Personne morale</span><?php endif; ?>
                <?php if (!empty($d['entreprises']) && is_array($d['entreprises'])): ?>
                    <div class="texte-doux petit">
                        Autres entreprises :
                        <?php foreach (array_values($d['entreprises']) as $i => $ent): ?>
                            <?= $i > 0 ? ', ' : '' ?><a href="<?= url('/entreprise/' . rawurlencode((string) ($ent['siren'] ?? ''))) ?>"><?= e(($ent['raison_sociale'] ?? '') !== '' ? $ent['raison_sociale'] : (string) ($ent['siren'] ?? '')) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
