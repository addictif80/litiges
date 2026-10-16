<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Exceptions\ValidationException;
use App\Models\Courrier;
use App\Models\Litige;
use App\Services\CourrierService;
use App\Services\ExportService;

class CourrierController extends Controller
{
    public function store(string $litigeId): void
    {
        $id = (int) $litigeId;
        if (!(new Litige())->find($id)) {
            $this->notFound('Litige introuvable.');
        }

        try {
            $courrierId = (new CourrierService())->generer(
                $id,
                $this->int('modele_id'),
                Auth::id(),
                $this->post('enregistrer_relance') !== null
            );
        } catch (ValidationException $e) {
            flash('error', e($e->getMessage()));
            $this->back('/litiges/' . $id . '#courriers');
        }

        flash('success', 'Courrier généré. Relisez-le puis imprimez-le ou téléchargez-le.');
        $this->redirect('/courriers/' . $courrierId);
    }

    public function show(string $id): void
    {
        $courrier = (new Courrier())->find((int) $id);
        if (!$courrier) {
            $this->notFound('Courrier introuvable.');
        }
        $litige = (new Litige())->findFull((int) $courrier['litige_id']);

        $this->view('courriers/show', [
            'titre'    => 'Courrier ' . $courrier['numero'],
            'courrier' => $courrier,
            'litige'   => $litige,
            'pdf'      => ExportService::pdfDisponible(),
        ]);
    }

    /** Telechargement : html (imprimable), doc (Word) ou pdf (si dompdf est installe). */
    public function download(string $id): void
    {
        $courrier = (new Courrier())->find((int) $id);
        if (!$courrier) {
            $this->notFound('Courrier introuvable.');
        }

        $format = (string) $this->input('format', 'html');
        $sujet  = (string) ($courrier['sujet'] ?: $courrier['numero']);
        $nom    = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $courrier['numero']) ?: 'courrier';

        Audit::log('courrier_telechargement', 'litige', (int) $courrier['litige_id'], ['numero' => $courrier['numero'], 'format' => $format]);

        if ($format === 'doc') {
            header('Content-Type: application/msword; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $nom . '.doc"');
            echo CourrierService::versDoc($sujet, (string) $courrier['contenu']);
            exit;
        }

        $page = CourrierService::pageHtml($sujet, (string) $courrier['contenu'], (string) $courrier['numero']);

        if ($format === 'pdf' && ExportService::pdfDisponible()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $nom . '.pdf"');
            echo ExportService::htmlVersPdf($page);
            exit;
        }

        // Page imprimable (Ctrl+P > Enregistrer au format PDF si aucun moteur PDF).
        header('Content-Type: text/html; charset=utf-8');
        header("Content-Security-Policy: sandbox; default-src 'none'; style-src 'unsafe-inline'; img-src data:");
        echo $page;
        exit;
    }

    public function suivi(string $id): void
    {
        $courrier = (new Courrier())->find((int) $id);
        if (!$courrier) {
            $this->notFound('Courrier introuvable.');
        }

        try {
            (new CourrierService())->majSuivi((int) $id, [
                'mode_envoi'   => $this->post('mode_envoi', ''),
                'numero_suivi' => $this->post('numero_suivi', ''),
                'date_envoi'   => $this->post('date_envoi', ''),
                'date_ar'      => $this->post('date_ar', ''),
                'statut_ar'    => $this->post('statut_ar', ''),
            ], Auth::id());
        } catch (ValidationException $e) {
            flash('error', e($e->getMessage()));
            $this->back('/courriers/' . $id);
        }

        flash('success', "Suivi d'envoi mis à jour.");
        $this->redirect('/courriers/' . $id);
    }

    public function destroy(string $id): void
    {
        $courrier = (new Courrier())->find((int) $id);
        if (!$courrier) {
            $this->notFound('Courrier introuvable.');
        }
        if (!Auth::isAdmin() && (int) $courrier['created_by'] !== (int) Auth::id()) {
            $this->forbidden('Vous ne pouvez supprimer que vos propres courriers.');
        }

        (new Courrier())->delete((int) $id);
        Audit::log('courrier_suppression', 'litige', (int) $courrier['litige_id'], ['numero' => $courrier['numero']]);

        flash('success', 'Courrier supprimé.');
        $this->redirect('/litiges/' . (int) $courrier['litige_id'] . '#courriers');
    }
}
