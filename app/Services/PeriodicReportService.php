<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Settings;
use App\Models\Litige;
use App\Services\Calcul\BalanceAgee;

/**
 * Rapport hebdomadaire par e-mail (synthese + classeur Excel des litiges ouverts).
 * Necessite le SMTP configure (Parametres > Courriel).
 */
class PeriodicReportService
{
    /**
     * @return array{envoye:bool, destinataires:int, raison:?string}
     */
    public function envoyer(bool $forcer = false): array
    {
        if (!$forcer) {
            if (!Settings::bool('rapport_hebdo_actif', false)) {
                return ['envoye' => false, 'destinataires' => 0, 'raison' => 'Rapport hebdomadaire désactivé.'];
            }
            if ((int) date('N') !== (int) Settings::get('rapport_hebdo_jour', 1)) {
                return ['envoye' => false, 'destinataires' => 0, 'raison' => "Ce n'est pas le jour d'envoi."];
            }
            if ((string) Settings::get('rapport_hebdo_dernier', '') === date('Y-m-d')) {
                return ['envoye' => false, 'destinataires' => 0, 'raison' => 'Déjà envoyé aujourd\'hui.'];
            }
        }

        $mailer = new Mailer();
        if (!$mailer->isEnabled()) {
            return ['envoye' => false, 'destinataires' => 0, 'raison' => "L'envoi d'e-mails n'est pas configuré."];
        }

        $destinataires = $this->destinataires();
        if ($destinataires === []) {
            return ['envoye' => false, 'destinataires' => 0, 'raison' => 'Aucun destinataire avec une adresse e-mail.'];
        }

        $html = $mailer->gabarit('Synthèse hebdomadaire des litiges — ' . date('d/m/Y'), $this->corps());
        $pieces = [];
        if (XlsxWriter::disponible()) {
            $pieces[] = [
                'nom'     => 'litiges_ouverts_' . date('Ymd') . '.xlsx',
                'contenu' => $this->classeur(),
                'mime'    => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ];
        }

        $envoyes = 0;
        foreach ($destinataires as $adresse) {
            if ($mailer->send($adresse, '[Litiges] Synthèse hebdomadaire du ' . date('d/m/Y'), $html, '', $pieces)) {
                $envoyes++;
            }
        }
        if ($envoyes > 0) {
            Settings::set('rapport_hebdo_dernier', date('Y-m-d'));
        } else {
            Logger::warning('Rapport hebdomadaire : aucun e-mail n\'a pu etre envoye');
        }

        return ['envoye' => $envoyes > 0, 'destinataires' => $envoyes, 'raison' => $envoyes > 0 ? null : "Échec de l'envoi."];
    }

    /** @return string[] */
    public function destinataires(): array
    {
        $regle = trim((string) Settings::get('rapport_hebdo_destinataires', 'admin'));
        if ($regle === 'admin' || $regle === 'tous') {
            $roles = $regle === 'admin' ? "('admin')" : "('admin','gestionnaire')";
            $lignes = Database::fetchAll(
                "SELECT email FROM users WHERE actif = 1 AND email IS NOT NULL AND email <> '' AND role IN {$roles}"
            );
            return array_values(array_unique(array_map(static fn (array $l): string => (string) $l['email'], $lignes)));
        }

        $adresses = array_map('trim', explode(',', $regle));
        return array_values(array_filter($adresses, static fn (string $a): bool => filter_var($a, FILTER_VALIDATE_EMAIL) !== false));
    }

    private function corps(): string
    {
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $stats    = (new Litige())->statsGlobal();
        $balance  = (new ReportService())->balanceAgee();
        $prevision = (new ReportService())->previsions(4);
        $top      = (new Litige())->topClients(5);
        $relancesRetard = (int) Database::value(
            'SELECT COUNT(*) FROM litiges l JOIN litige_statuts s ON s.id = l.statut_id
             WHERE s.cloturant = 0 AND l.prochaine_relance_at IS NOT NULL AND l.prochaine_relance_at <= CURDATE()',
            [],
            0
        );
        $tachesRetard = (int) Database::value(
            "SELECT COUNT(*) FROM taches WHERE statut <> 'faite' AND echeance IS NOT NULL AND echeance < CURDATE()",
            [],
            0
        );

        $h  = '<p>Bonjour,</p><p>Voici la situation des litiges au ' . $e(date('d/m/Y')) . '.</p>';
        $h .= '<table style="border-collapse:collapse;width:100%">';
        $ligne = static fn (string $lib, string $val): string =>
            '<tr><td style="padding:6px 8px;border-bottom:1px solid #e3e8ef">' . $lib . '</td><td style="padding:6px 8px;border-bottom:1px solid #e3e8ef;text-align:right"><strong>' . $val . '</strong></td></tr>';
        $h .= $ligne('Litiges ouverts', $e((int) ($stats['ouverts'] ?? 0)));
        $h .= $ligne('Encours à recouvrer', $e(money($stats['encours'] ?? 0)));
        $h .= $ligne('Taux de recouvrement (cumul)', $e(number_format((float) ($stats['taux_recouvrement'] ?? 0), 1, ',', ' ')) . ' %');
        $h .= $ligne('Relances en retard', $e($relancesRetard));
        $h .= $ligne('Tâches en retard', $e($tachesRetard));
        $h .= $ligne('Échéances de plans impayées (en retard)', $e(money($prevision['en_retard'])));
        $h .= $ligne('Encaissements attendus sous 4 semaines', $e(money($prevision['total_echeances'] + $prevision['total_promesses'])));
        $h .= '</table>';

        $h .= '<h3 style="margin:18px 0 6px">Balance âgée</h3><table style="border-collapse:collapse;width:100%">';
        foreach (BalanceAgee::TRANCHES as $cle => $libelle) {
            $h .= $ligne($e($libelle), $e(money($balance['total'][$cle])));
        }
        $h .= '</table>';

        if ($top !== []) {
            $h .= '<h3 style="margin:18px 0 6px">Principaux encours</h3><table style="border-collapse:collapse;width:100%">';
            foreach ($top as $c) {
                $h .= $ligne($e($c['raison_sociale']) . ' (' . (int) $c['nb'] . ')', $e(money($c['encours'])));
            }
            $h .= '</table>';
        }

        return $h . '<p style="margin-top:16px">Le détail des litiges ouverts est en pièce jointe (Excel).</p>';
    }

    private function classeur(): string
    {
        $lignes = (new Litige())->pourExport(['etat' => 'ouverts', 'tri' => 'priorite,echeance'], 5000);
        $colonnes = [
            'reference' => 'Référence', 'raison_sociale' => 'Client', 'objet' => 'Objet', 'statut_libelle' => 'Statut',
            'priorite' => 'Priorité', 'encours' => 'Encours', 'date_echeance' => 'Échéance',
            'prochaine_relance_at' => 'Prochaine relance', 'responsable_nom' => 'Responsable', 'agence_nom' => 'Agence',
        ];
        $types = ['encours' => 'nombre', 'date_echeance' => 'date', 'prochaine_relance_at' => 'date'];
        return (new ExportService())->xlsxBinaire($colonnes, $lignes, $types, 'Litiges ouverts');
    }
}
