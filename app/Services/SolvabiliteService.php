<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Score de solvabilite interne (100 % gratuit, aucun appel externe).
 *
 * Remplace un score de credit payant : il est calcule localement a partir
 * des donnees publiques de l'entreprise (identite, finances, effectif,
 * anciennete, procedure collective) et, si disponible, de l'encours client
 * interne (litiges non clotures) — c'est la plus-value par rapport a un
 * score externe.
 *
 * Le score va de 0 (risque maximal) a 100 (tres bonne sante percue).
 */
class SolvabiliteService
{
    /**
     * @param array<string,mixed> $entreprise Donnees entreprise (memes cles que les champs client).
     * @param float|null          $encours     Encours client interne (litiges non clotures).
     * @return array{score:int,label:string,couleur:string,facteurs:array<int,string>}
     */
    public function calculer(array $entreprise, ?float $encours = null): array
    {
        $facteurs = [];
        $score    = 50;

        $procedure = trim((string) ($entreprise['procedure_collective'] ?? ''));
        $statut    = mb_strtolower(trim((string) ($entreprise['statut_entreprise'] ?? '')));

        // 1. Procedure collective : signal fort (on attenue si elle semble closee).
        if ($procedure !== '') {
            $p = mb_strtolower($procedure);
            if (
                str_contains($p, 'clot')
                || str_contains($p, 'clos')
                || str_contains($p, 'fin de')
                || str_contains($p, 'radiat')
                || str_contains($p, 'termi')
                || str_contains($p, 'levee')
                || str_contains($p, 'rétract')
                || str_contains($p, 'retract')
            ) {
                $facteurs[] = 'Procédure collective (historique) : ' . $procedure;
            } else {
                $score -= 45;
                $facteurs[] = 'Procédure collective : ' . $procedure;
            }
        }

        // 2. Situation administrative.
        if ($statut !== '' && (
            str_contains($statut, 'cess')
            || str_contains($statut, 'radie')
            || str_contains($statut, 'liquid')
        )) {
            $score -= 25;
            $facteurs[] = 'Entreprise cessée / radiée';
        }

        // 3. Rentabilite (resultat net).
        $resultat = $this->nombre($entreprise['resultat_net'] ?? null);
        $ca       = $this->nombre($entreprise['chiffre_affaires'] ?? null);

        if ($resultat !== null) {
            if ($resultat > 0) {
                $score += 15;
                if ($ca !== null && $ca > 0 && ($resultat / $ca) >= 0.05) {
                    $score += 5;
                }
                $facteurs[] = 'Résultat net positif';
            } else {
                $score -= 20;
                if ($ca !== null && $ca > 0 && ($resultat / $ca) <= -0.10) {
                    $score -= 10;
                }
                $facteurs[] = 'Résultat net négatif';
            }
        }

        // 4. Chiffre d'affaires.
        if ($ca !== null && $ca > 0) {
            if ($ca >= 1_000_000) {
                $score += 10;
                $facteurs[] = "Chiffre d'affaires ≥ 1 M€";
            } elseif ($ca >= 100_000) {
                $score += 5;
            }
        }

        // 5. Anciennete.
        $anciennete = $this->ancienneteAnnees($entreprise['date_creation'] ?? null);
        if ($anciennete !== null) {
            if ($anciennete >= 10) {
                $score += 10;
                $facteurs[] = 'Ancienneté ≥ 10 ans';
            } elseif ($anciennete >= 5) {
                $score += 5;
            } elseif ($anciennete < 1) {
                $score -= 10;
                $facteurs[] = 'Entreprise de moins de 1 an';
            } elseif ($anciennete < 2) {
                $score -= 5;
            }
        }

        // 6. Capital social.
        $capital = $this->nombre($entreprise['capital'] ?? null);
        if ($capital !== null && $capital >= 50_000) {
            $score += 5;
        }

        // 7. Effectif.
        $effectif = (string) ($entreprise['effectif'] ?? '');
        if ($effectif !== '' && preg_match('/(\d+)/', str_replace(' ', '', $effectif), $m) === 1) {
            if ((int) $m[1] >= 50) {
                $score += 5;
            }
        }

        // 8. Exposition interne : encours vs capacite financiere.
        if ($encours !== null && $encours > 0) {
            if ($ca !== null && $ca > 0) {
                if ($encours > $ca) {
                    $score -= 20;
                    $facteurs[] = "Encours interne supérieur au chiffre d'affaires";
                } elseif ($encours > 0.5 * $ca) {
                    $score -= 10;
                    $facteurs[] = "Encours interne supérieur à 50 % du chiffre d'affaires";
                }
            } elseif ($encours > 50_000) {
                $score -= 10;
                $facteurs[] = 'Encours interne élevé';
            }
        }

        $score = max(0, min(100, $score));

        return [
            'score'    => $score,
            'label'    => $this->label($score),
            'couleur'  => $this->couleur($score),
            'facteurs' => $facteurs,
        ];
    }

    /** Representation compacte destinee a la colonne clients.score_solvabilite. */
    public function chaine(array $entreprise, ?float $encours = null): string
    {
        $r = $this->calculer($entreprise, $encours);
        return $r['score'] . '/100 ' . $r['label'];
    }

    public function label(int $score): string
    {
        if ($score >= 75) {
            return 'Bon';
        }
        if ($score >= 60) {
            return 'Correct';
        }
        if ($score >= 45) {
            return 'Moyen';
        }
        if ($score >= 30) {
            return 'Fragile';
        }
        if ($score >= 15) {
            return 'Risqué';
        }
        return 'Critique';
    }

    public function couleur(int $score): string
    {
        if ($score >= 75) {
            return '#198754';
        }
        if ($score >= 60) {
            return '#20c997';
        }
        if ($score >= 45) {
            return '#ffc107';
        }
        if ($score >= 30) {
            return '#fd7e14';
        }
        if ($score >= 15) {
            return '#dc3545';
        }
        return '#842029';
    }

    private function nombre(mixed $valeur): ?float
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }
        if (is_int($valeur) || is_float($valeur)) {
            return (float) $valeur;
        }

        $clean = preg_replace('/[^0-9,.\-]/', '', (string) $valeur) ?? '';
        if ($clean === '') {
            return null;
        }
        // Virgule decimale a la francaise : 12 345,67 -> 12345.67
        if (str_contains($clean, ',')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function ancienneteAnnees(mixed $date): ?int
    {
        if ($date === null || $date === '') {
            return null;
        }
        $ts = strtotime((string) $date);
        if ($ts === false) {
            return null;
        }
        return (int) floor((time() - $ts) / (365.25 * 86400));
    }
}
