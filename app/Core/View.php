<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Flash;
use App\Core\Settings;

/**
 * Rendu des vues PHP avec layout.
 */
class View
{
    /** @var array<string,mixed> */
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $data = array_merge(self::$shared, $data);

        $data['flash']       = Flash::pull();
        $data['old']         = $_SESSION['_old'] ?? [];
        $data['errors']      = $_SESSION['_errors'] ?? [];
        $data['authUser']    = Auth::user();
        $data['appName']     = (string) Settings::get('app_nom', Config::get('app.name', 'Gestion des litiges'));
        $data['currentPath'] = Request::path();
        $data['basePath']    = Request::basePath();

        try {
            $content = self::capture(Config::basePath('app/Views/' . $view . '.php'), $data);

            if ($layout === null) {
                return $content;
            }

            return self::capture(Config::basePath('app/Views/' . $layout . '.php'), array_merge($data, ['content' => $content]));
        } finally {
            unset($_SESSION['_old'], $_SESSION['_errors']);
        }
    }

    private static function capture(string $__file, array $__data): string
    {
        if (!is_file($__file)) {
            throw new \RuntimeException("Vue introuvable : {$__file}");
        }
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            include $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
