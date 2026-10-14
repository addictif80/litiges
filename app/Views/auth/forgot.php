<div class="page-connexion">
    <div class="carte-connexion">
        <div class="entete">
            <h1>Mot de passe oublié</h1>
            <p>Saisissez l'adresse email associée à votre compte</p>
        </div>
        <div class="corps">
            <form method="post" action="<?= url('/forgot') ?>" autocomplete="off">
                <?= csrf_field() ?>
                <div class="form-groupe">
                    <label for="email">Adresse email</label>
                    <input type="email" id="email" name="email" value="<?= e(old('email')) ?>" required autofocus>
                    <div class="aide">Un lien de réinitialisation valable 1 heure vous sera transmis.</div>
                </div>
                <button type="submit" class="btn">Envoyer le lien</button>
            </form>
            <div class="lien-secondaire">
                <a href="<?= url('/login') ?>">&larr; Retour à la connexion</a>
            </div>
        </div>
    </div>
</div>
