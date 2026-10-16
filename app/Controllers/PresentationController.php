<?php
declare(strict_types=1);

namespace App\Controllers;

/**
 * Diaporama de presentation des fonctionnalites du panel.
 * Page autonome (sans le gabarit de l'application), accessible aux
 * utilisateurs connectes.
 */
class PresentationController extends Controller
{
    public function index(): void
    {
        $this->view('presentation/index', [
            'titre' => 'Presentation des fonctionnalites',
        ], null);
    }
}
