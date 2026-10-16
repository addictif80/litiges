/* =====================================================================
   Diaporama de présentation — navigation (autonome, sans dépendance).
   Clavier : ←/→ ↑/↓ Espace PageUp/PageDown Début/Fin, F (plein écran),
   O (vue d'ensemble), Échap. Tactile : balayage. Souris : clic sur les
   bords + barre de contrôle. L'URL reflète la diapositive (#3).
   ===================================================================== */
(function () {
    'use strict';

    var deck = document.querySelector('.deck');
    if (!deck) { return; }

    var slides = Array.prototype.slice.call(deck.querySelectorAll('.slide'));
    var total = slides.length;
    if (!total) { return; }

    var couramment = 0;
    var progressFill = document.querySelector('.deck-progress > span');
    var compteur = document.querySelector('.deck-bar .compteur');
    var dotsBox = document.querySelector('.deck-dots');
    var overview = document.querySelector('.overview');
    var overviewGrille = overview ? overview.querySelector('.overview-grille') : null;

    /* ---------- Construction des points + vue d'ensemble ---------- */
    var dots = [];
    if (dotsBox) {
        slides.forEach(function (slide, i) {
            var b = document.createElement('button');
            b.type = 'button';
            b.setAttribute('aria-label', 'Diapositive ' + (i + 1));
            b.addEventListener('click', function () { goTo(i); });
            dotsBox.appendChild(b);
            dots.push(b);
        });
    }

    var ovItems = [];
    if (overviewGrille) {
        slides.forEach(function (slide, i) {
            var titre = slide.getAttribute('data-title') || ('Diapositive ' + (i + 1));
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'ov-item';
            item.innerHTML = '<span class="n">' + String(i + 1).padStart(2, '0') + '</span>' +
                             '<span class="t"></span>';
            item.querySelector('.t').textContent = titre;
            item.addEventListener('click', function () { fermerOverview(); goTo(i); });
            overviewGrille.appendChild(item);
            ovItems.push(item);
        });
    }

    /* ---------- Affichage ---------- */
    function rendre(i) {
        slides.forEach(function (slide, n) {
            slide.classList.toggle('is-active', n === i);
        });
        if (progressFill) { progressFill.style.width = ((i + 1) / total * 100) + '%'; }
        if (compteur) { compteur.textContent = (i + 1) + ' / ' + total; }
        dots.forEach(function (d, n) { d.classList.toggle('is-active', n === i); });
        ovItems.forEach(function (o, n) { o.classList.toggle('is-active', n === i); });

        var cover = slides[i].classList.contains('slide--cover') || slides[i].classList.contains('slide--end');
        var hint = document.querySelector('.deck-hint');
        if (hint) { hint.classList.toggle('clair', cover); }

        // Remonter le contenu de la diapositive si elle défile.
        slides[i].scrollTop = 0;
    }

    function goTo(i) {
        if (i < 0) { i = 0; }
        if (i > total - 1) { i = total - 1; }
        couramment = i;
        rendre(i);
        try {
            if (history.replaceState) { history.replaceState(null, '', '#' + (i + 1)); }
        } catch (err) { /* file:// ou contexte restreint : on ignore */ }
    }
    function next() { goTo(couramment + 1); }
    function prev() { goTo(couramment - 1); }

    /* ---------- Vue d'ensemble ---------- */
    function ouvrirOverview() { if (overview) { overview.classList.add('is-open'); } }
    function fermerOverview() { if (overview) { overview.classList.remove('is-open'); } }
    function basculerOverview() {
        if (!overview) { return; }
        overview.classList.contains('is-open') ? fermerOverview() : ouvrirOverview();
    }

    /* ---------- Plein écran ---------- */
    function basculerPleinEcran() {
        if (!document.fullscreenElement) {
            (document.documentElement.requestFullscreen || function () {}).call(document.documentElement);
        } else if (document.exitFullscreen) {
            document.exitFullscreen();
        }
    }

    /* ---------- Clavier ---------- */
    document.addEventListener('keydown', function (e) {
        if (e.target && /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) { return; }
        switch (e.key) {
            case 'ArrowRight': case 'ArrowDown': case 'PageDown': case ' ':
                e.preventDefault(); next(); break;
            case 'ArrowLeft': case 'ArrowUp': case 'PageUp':
                e.preventDefault(); prev(); break;
            case 'Home': e.preventDefault(); goTo(0); break;
            case 'End': e.preventDefault(); goTo(total - 1); break;
            case 'f': case 'F': e.preventDefault(); basculerPleinEcran(); break;
            case 'o': case 'O': e.preventDefault(); basculerOverview(); break;
            case 'Escape': fermerOverview(); break;
        }
    });

    /* ---------- Clics sur les bords ---------- */
    deck.addEventListener('click', function (e) {
        if (e.target.closest('a, button, .overview')) { return; }
        var x = e.clientX / window.innerWidth;
        if (x > 0.72) { next(); }
        else if (x < 0.28) { prev(); }
    });

    /* ---------- Tactile (balayage) ---------- */
    var x0 = null, y0 = null;
    deck.addEventListener('touchstart', function (e) {
        if (e.touches.length !== 1) { return; }
        x0 = e.touches[0].clientX; y0 = e.touches[0].clientY;
    }, { passive: true });
    deck.addEventListener('touchend', function (e) {
        if (x0 === null) { return; }
        var dx = e.changedTouches[0].clientX - x0;
        var dy = e.changedTouches[0].clientY - y0;
        x0 = null; y0 = null;
        if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) { dx < 0 ? next() : prev(); }
    }, { passive: true });

    /* ---------- Boutons de la barre ---------- */
    var btnPrev = document.querySelector('[data-deck="prev"]');
    var btnNext = document.querySelector('[data-deck="next"]');
    var btnFull = document.querySelector('[data-deck="full"]');
    var btnOv   = document.querySelector('[data-deck="overview"]');
    if (btnPrev) { btnPrev.addEventListener('click', prev); }
    if (btnNext) { btnNext.addEventListener('click', next); }
    if (btnFull) { btnFull.addEventListener('click', basculerPleinEcran); }
    if (btnOv)   { btnOv.addEventListener('click', basculerOverview); }

    /* ---------- Démarrage ---------- */
    var depart = parseInt((location.hash || '').replace('#', ''), 10);
    goTo(isNaN(depart) ? 0 : depart - 1);
})();
