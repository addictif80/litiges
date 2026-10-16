<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Models\Litige;
use App\Services\Calcul\BalanceAgee;
use App\Services\ExportService;
use App\Services\ReportService;
use App\Services\XlsxWriter;

class ReportController extends Controller
{
    public function index(): void
    {
        $service = new ReportService();
        $this->view('rapports/index', [
            'titre'       => 'Rapports & prévisions',
            'balance'     => $service->balanceAgee(),
            'recouvrement' => $service->recouvrementParAgence(12),
            'previsions'  => $service->previsions(8),
            'delaiMoyen'  => (float) ((new Litige())->statsGlobal()['delai_moyen'] ?? 0),
            'xlsx'        => XlsxWriter::disponible(),
        ]);
    }

    /** Export de la balance agee par client (CSV ou Excel). */
    public function exportBalance(): void
    {
        $balance = (new ReportService())->balanceAgee(null, 5000);

        $colonnes = ['raison_sociale' => 'Client', 'nb' => 'Litiges'];
        $types    = ['nb' => 'nombre'];
        foreach (BalanceAgee::TRANCHES as $cle => $libelle) {
            $colonnes[$cle] = $libelle;
            $types[$cle]    = 'nombre';
        }
        $colonnes['total'] = 'Total';
        $types['total']    = 'nombre';

        $clients = $balance['par_client'];
        Audit::log('export_balance_agee', 'rapport', null, ['nb' => count($clients)]);

        $nom = 'balance_agee_' . date('Ymd');
        if ($this->input('format') === 'xlsx' && XlsxWriter::disponible()) {
            (new ExportService())->xlsx($nom, $colonnes, $clients, $types, 'Balance âgée');
        }
        (new ExportService())->csv($nom, $colonnes, $clients);
    }
}
