<?php
/**
 * Champs communs du formulaire du registre RGPD.
 * @var array $r traitement courant (ou valeurs vides pour un ajout)
 */
?>
<div class="ligne-champs">
    <div class="form-groupe"><label>Traitement</label><input type="text" name="traitement" maxlength="190" required value="<?= e($r['traitement']) ?>"></div>
    <div class="form-groupe"><label>Base légale</label><input type="text" name="base_legale" maxlength="190" required value="<?= e($r['base_legale']) ?>"></div>
    <div class="form-groupe"><label>Personnes concernées</label><input type="text" name="personnes" maxlength="190" value="<?= e($r['personnes']) ?>"></div>
    <div class="form-groupe"><label>Durée de conservation</label><input type="text" name="duree_conservation" maxlength="190" value="<?= e($r['duree_conservation']) ?>"></div>
</div>
<div class="form-groupe"><label>Finalité</label><textarea name="finalite" rows="2" required><?= e($r['finalite']) ?></textarea></div>
<div class="form-groupe"><label>Catégories de données</label><textarea name="categories_donnees" rows="2"><?= e($r['categories_donnees']) ?></textarea></div>
<div class="ligne-champs">
    <div class="form-groupe"><label>Destinataires</label><input type="text" name="destinataires" maxlength="255" value="<?= e($r['destinataires']) ?>"></div>
    <div class="form-groupe"><label>Ordre</label><input type="number" name="ordre" value="<?= (int) $r['ordre'] ?>"></div>
</div>
<div class="form-groupe"><label>Mesures de sécurité</label><textarea name="mesures_securite" rows="2"><?= e($r['mesures_securite']) ?></textarea></div>
