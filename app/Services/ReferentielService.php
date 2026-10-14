<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Acces aux referentiels officiels :
 *   - nomenclature NAF / code APE (rev. 2 et 2025) ;
 *   - sections NAF (A..U) ;
 *   - categories juridiques (niveau III).
 *
 * Les donnees sont generees dans app/Resources/referentiels/*.php a partir
 * des referentiels publics (Insee / annuaire-entreprises.data.gouv.fr).
 * Elles sont chargees paresseusement et mises en cache en memoire.
 */
class ReferentielService
{
    /** @var array<string,string>|null */
    private static ?array $nafRev2 = null;
    /** @var array<string,string>|null */
    private static ?array $naf2025 = null;
    /** @var array<string,string>|null */
    private static ?array $sections = null;
    /** @var array<string,string>|null */
    private static ?array $categories = null;

    /** Libelle d'un code APE/NAF (nomenclature rev. 2). */
    public static function naf(?string $code): ?string
    {
        $code = self::cleanCode($code);
        if ($code === null) {
            return null;
        }
        self::$nafRev2 ??= require self::chemin('naf_rev2.php');
        return self::$nafRev2[$code] ?? null;
    }

    /** Libelle d'un code APE/NAF (nomenclature 2025). */
    public static function naf2025(?string $code): ?string
    {
        $code = self::cleanCode($code);
        if ($code === null) {
            return null;
        }
        self::$naf2025 ??= require self::chemin('naf_2025.php');
        return self::$naf2025[$code] ?? null;
    }

    /** Libelle APE en essayant la nomenclature rev. 2 puis 2025. */
    public static function libelleApe(?string $code): ?string
    {
        return self::naf($code) ?? self::naf2025($code);
    }

    /** Lettre de section NAF (A..U) deduite du code APE. */
    public static function sectionCode(?string $code): ?string
    {
        $code = self::cleanCode($code);
        if ($code === null) {
            return null;
        }
        $division = (int) substr($code, 0, 2);

        return match (true) {
            $division >= 1  && $division <= 3  => 'A',
            $division >= 5  && $division <= 9  => 'B',
            $division >= 10 && $division <= 33 => 'C',
            $division === 35                   => 'D',
            $division >= 36 && $division <= 39 => 'E',
            $division >= 41 && $division <= 43 => 'F',
            $division >= 45 && $division <= 47 => 'G',
            $division >= 49 && $division <= 53 => 'H',
            $division >= 55 && $division <= 56 => 'I',
            $division >= 58 && $division <= 63 => 'J',
            $division >= 64 && $division <= 66 => 'K',
            $division === 68                   => 'L',
            $division >= 69 && $division <= 75 => 'M',
            $division >= 77 && $division <= 82 => 'N',
            $division === 84                   => 'O',
            $division === 85                   => 'P',
            $division >= 86 && $division <= 88 => 'Q',
            $division >= 90 && $division <= 93 => 'R',
            $division >= 94 && $division <= 96 => 'S',
            $division >= 97 && $division <= 98 => 'T',
            $division === 99                   => 'U',
            default                            => null,
        };
    }

    /** Libelle de la section NAF (A..U) deduite du code APE. */
    public static function section(?string $code): ?string
    {
        $lettre = self::sectionCode($code);
        if ($lettre === null) {
            return null;
        }
        self::$sections ??= require self::chemin('sections_naf.php');
        return self::$sections[$lettre] ?? null;
    }

    /**
     * Libelle d'une forme juridique. Accepte soit un code numerique (4 chiffres,
     * ex. "5499"), soit un libelle deja fourni (Pappers) qui est renvoye tel quel.
     */
    public static function formeJuridique(?string $code): ?string
    {
        $code = trim((string) $code);
        if ($code === '') {
            return null;
        }
        if (preg_match('/^\d{4}$/', $code) === 1) {
            self::$categories ??= require self::chemin('categories_juridiques.php');
            return self::$categories[$code] ?? $code;
        }
        return $code;
    }

    /** Nettoie un code (majuscules, sans espaces parasites). */
    private static function cleanCode(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));
        return $code === '' ? null : $code;
    }

    private static function chemin(string $fichier): string
    {
        return dirname(__DIR__) . '/Resources/referentiels/' . $fichier;
    }
}
