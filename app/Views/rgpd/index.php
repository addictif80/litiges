<?php
/**
 * RGPD : registre des traitements et durees de conservation.
 * @var array $registre
 * @var array|null $simulation
 */
$s = static fn (string $cle, mixed $defaut = '') => setting($cle, $defaut);
?>
<?php if ($simulation): ?>
    <div class="alerte alerte-info mb-2">
        <strong>Simulation de purge (rien n'a été modifié) :</strong>
        <?= (int) $simulation['contacts_anonymises'] ?> contact(s) à anonymiser,
        <?= (int) $simulation['litiges_supprimes'] ?> litige(s) à supprimer,
        <?= (int) $simulation['audit_purge'] ?> ligne(s) d'audit à purger.
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-entete"><h3>Durées de conservation</h3></div>
    <form method="post" action="<?= url('/rgpd/parametres') ?>">
        <?= csrf_field() ?>
        <div class="card-corps">
            <div class="ligne-champs">
                <div class="form-groupe">
                    <label for="rgpd_retention_mois">Conserver les dossiers clos pendant (mois)</label>
                    <input type="number" id="rgpd_retention_mois" name="rgpd_retention_mois" min="0" max="600" value="<?= (int) $s('rgpd_retention_mois', 0) ?>">
                    <div class="aide">0 = aucune purge automatique. 60 mois (5 ans) correspond à la prescription commerciale usuelle : à valider avec votre conseil.</div>
                </div>
                <div class="form-groupe">
                    <label for="audit_retention_mois">Conserver le journal d'audit (mois)</label>
                    <input type="number" id="audit_retention_mois" name="audit_retention_mois" min="0" max="600" value="<?= (int) $s('audit_retention_mois', 0) ?>">
                    <div class="aide">0 = conservation illimitée.</div>
                </div>
            </div>
            <label class="check"><input type="checkbox" name="rgpd_anonymiser_contacts" value="1" <?= (int) $s('rgpd_anonymiser_contacts', 1) === 1 ? 'checked' : '' ?>> Anonymiser les contacts des clients dont tous les litiges sont clos depuis plus longtemps que la durée ci-dessus</label>
            <label class="check"><input type="checkbox" name="rgpd_supprimer_litiges" value="1" <?= (int) $s('rgpd_supprimer_litiges', 0) === 1 ? 'checked' : '' ?>> <strong>Supprimer définitivement</strong> les litiges clos (et leurs pièces jointes) au-delà de cette durée</label>
        </div>
        <div class="card-pied flex">
            <button type="submit" class="btn btn-vert">Enregistrer</button>
        </div>
    </form>
    <div class="card-pied flex">
        <form method="post" action="<?= url('/rgpd/simuler') ?>"><?= csrf_field() ?><button type="submit" class="btn btn-secondaire">Simuler la purge</button></form>
        <form method="post" action="<?= url('/rgpd/purger') ?>" data-confirmer="Lancer la purge maintenant ? Les anonymisations et suppressions sont définitives.">
            <?= csrf_field() ?><button type="submit" class="btn btn-rouge">Exécuter la purge</button>
        </form>
        <span class="petit texte-doux">Exécutée aussi chaque nuit par le cron quotidien.</span>
    </div>
</div>

<div class="card">
    <div class="card-entete"><h3>Registre des traitements (art. 30 RGPD)</h3></div>
    <?php foreach ($registre as $r): ?>
        <details class="card-corps" style="border-bottom:1px solid #eef2f7">
            <summary style="cursor:pointer"><strong><?= e($r['traitement']) ?></strong> <span class="petit texte-doux">— conservation : <?= e($r['duree_conservation']) ?></span></summary>
            <form method="post" action="<?= url('/rgpd/registre') ?>" class="mt-2">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <?php include __DIR__ . '/_champs_registre.php'; ?>
                <div class="flex">
                    <button type="submit" class="btn btn-petit">Enregistrer</button>
                </div>
            </form>
            <form method="post" action="<?= url('/rgpd/registre/' . (int) $r['id'] . '/supprimer') ?>" class="mt-1" data-confirmer="Supprimer ce traitement du registre ?">
                <?= csrf_field() ?><button type="submit" class="btn-lien rouge">Supprimer</button>
            </form>
        </details>
    <?php endforeach; ?>
    <details class="card-corps">
        <summary style="cursor:pointer;color:var(--bleu-600);font-weight:600">+ Ajouter un traitement</summary>
        <form method="post" action="<?= url('/rgpd/registre') ?>" class="mt-2">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="0">
            <?php $r = ['traitement' => '', 'finalite' => '', 'base_legale' => '', 'categories_donnees' => '', 'personnes' => '', 'destinataires' => '', 'duree_conservation' => '', 'mesures_securite' => '', 'ordre' => 100]; ?>
            <?php include __DIR__ . '/_champs_registre.php'; ?>
            <button type="submit" class="btn btn-petit">Ajouter</button>
        </form>
    </details>
</div>

<div class="card">
    <div class="card-entete"><h3>Droits des personnes</h3></div>
    <div class="card-corps">
        <ul>
            <li><strong>Accès / portabilité</strong> : depuis la fiche client, bouton « Export RGPD » sur un contact (fichier JSON de toutes les données détenues).</li>
            <li><strong>Effacement</strong> : bouton « Anonymiser » sur le contact (les comptes rendus qui le citent sont neutralisés, la trace du dossier est conservée).</li>
            <li><strong>Traçabilité</strong> : chaque export, anonymisation et purge est enregistré dans le journal d'audit.</li>
        </ul>
    </div>
</div>
