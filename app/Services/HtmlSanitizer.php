<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Nettoyage HTML par liste blanche (modeles de courriers) : seules des balises
 * de mise en forme sont conservees, jamais de script, de gestionnaire
 * d'evenement ni de lien "javascript:".
 */
final class HtmlSanitizer
{
    private const BALISES = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tfoot',
        'tr', 'th', 'td', 'h1', 'h2', 'h3', 'h4', 'span', 'div', 'a', 'hr', 'blockquote', 'sub', 'sup',
    ];

    /** Attributs autorises par balise ("*" = toutes les balises). */
    private const ATTRIBUTS = [
        '*' => ['style', 'class'],
        'a' => ['href', 'title'],
        'td' => ['colspan', 'rowspan', 'align'],
        'th' => ['colspan', 'rowspan', 'align'],
    ];

    public static function nettoyer(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = new \DOMDocument();
        $precedent = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="racine">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($precedent);

        $racine = $doc->getElementById('racine');
        if ($racine === null) {
            return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
        }

        self::nettoyerNoeud($racine);

        $sortie = '';
        foreach ($racine->childNodes as $enfant) {
            $sortie .= $doc->saveHTML($enfant);
        }
        return $sortie;
    }

    private static function nettoyerNoeud(\DOMNode $noeud): void
    {
        $enfants = [];
        foreach ($noeud->childNodes as $enfant) {
            $enfants[] = $enfant;
        }

        foreach ($enfants as $enfant) {
            if ($enfant instanceof \DOMText) {
                continue;
            }
            if (!$enfant instanceof \DOMElement) {
                $noeud->removeChild($enfant); // commentaires, instructions...
                continue;
            }

            $balise = strtolower($enfant->tagName);
            if (!in_array($balise, self::BALISES, true)) {
                // Balise interdite : on garde le texte, jamais les balises (script/style supprimes en entier).
                if (in_array($balise, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'svg', 'math'], true)) {
                    $noeud->removeChild($enfant);
                    continue;
                }
                self::nettoyerNoeud($enfant);
                while ($enfant->firstChild) {
                    $noeud->insertBefore($enfant->firstChild, $enfant);
                }
                $noeud->removeChild($enfant);
                continue;
            }

            self::nettoyerAttributs($enfant, $balise);
            self::nettoyerNoeud($enfant);
        }
    }

    private static function nettoyerAttributs(\DOMElement $element, string $balise): void
    {
        $autorises = array_merge(self::ATTRIBUTS['*'], self::ATTRIBUTS[$balise] ?? []);
        $aSupprimer = [];
        foreach ($element->attributes as $attribut) {
            $nom = strtolower($attribut->name);
            $valeur = $attribut->value;

            if (!in_array($nom, $autorises, true)) {
                $aSupprimer[] = $attribut->name;
                continue;
            }
            if ($nom === 'href' && !preg_match('#^(https?://|mailto:|tel:)#i', trim($valeur))) {
                $aSupprimer[] = $attribut->name;
            }
            if ($nom === 'style' && !self::styleSur($valeur)) {
                $aSupprimer[] = $attribut->name;
            }
        }
        foreach ($aSupprimer as $nom) {
            $element->removeAttribute($nom);
        }
    }

    private static function styleSur(string $style): bool
    {
        if (preg_match('/url\s*\(|expression|javascript|@import|behavior|-moz-binding/i', $style)) {
            return false;
        }
        return preg_match('/^[a-zA-Z0-9\s:;,.#%()\-\'"]*$/', $style) === 1;
    }
}
