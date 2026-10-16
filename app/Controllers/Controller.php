<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;

/**
 * Controleur de base : rendu de vues, redirections, acces aux entrees.
 */
abstract class Controller
{
    protected function view(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        echo View::render($view, $data, $layout);
    }

    protected function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function redirect(string $path): never
    {
        redirect($path);
    }

    /** Revenir a la page precedente (ou a un fallback). */
    protected function back(string $fallback = '/'): never
    {
        // On ne revient au referer que s'il pointe vraiment vers notre hote
        // (comparaison sur l'hote analyse, pas sur une sous-chaine).
        $referer     = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $hoteReferer = strtolower((string) parse_url($referer, PHP_URL_HOST));
        $port        = parse_url($referer, PHP_URL_PORT);
        $hoteComplet = $hoteReferer . ($port ? ':' . $port : '');
        $hote        = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($referer !== '' && $hote !== '' && $hoteComplet === $hote) {
            header('Location: ' . $referer);
            exit;
        }
        redirect($fallback);
    }

    protected function errorPage(int $code, string $message): never
    {
        http_response_code($code);
        echo View::render('errors/error', ['code' => $code, 'message' => $message], Auth::check() ? 'layouts/app' : 'layouts/blank');
        exit;
    }

    protected function notFound(string $message = 'Élément introuvable.'): never
    {
        $this->errorPage(404, $message);
    }

    protected function forbidden(string $message = 'Action non autorisée.'): never
    {
        $this->errorPage(403, $message);
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        return Request::input($key, $default);
    }

    protected function post(string $key, mixed $default = null): mixed
    {
        return Request::post($key, $default);
    }

    protected function int(string $key, int $default = 0): int
    {
        return Request::integer($key, $default);
    }

    /** Reaffiche le formulaire avec les erreurs et les valeurs saisies. */
    protected function backWithErrors(string $path, array $errors, array $old = []): never
    {
        $_SESSION['_errors'] = $errors;
        $_SESSION['_old']    = $old;
        $this->redirect($path);
    }
}
