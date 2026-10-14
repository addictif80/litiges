<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Routeur minimaliste : associe methode + motif d'URL a un controleur.
 * Les parametres dynamiques s'ecrivent {nom} dans le motif.
 */
class Router
{
    /** @var array<int,array{method:string,pattern:string,regex:string,handler:mixed,guard:?string}> */
    private array $routes = [];

    public function get(string $pattern, mixed $handler, ?string $guard = null): void
    {
        $this->add('GET', $pattern, $handler, $guard);
    }

    public function post(string $pattern, mixed $handler, ?string $guard = null): void
    {
        $this->add('POST', $pattern, $handler, $guard);
    }

    private function add(string $method, string $pattern, mixed $handler, ?string $guard): void
    {
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        $this->routes[] = [
            'method'  => $method,
            'pattern' => $pattern,
            'regex'   => '#^' . $regex . '$#',
            'handler' => $handler,
            'guard'   => $guard,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            $pathMatched = true;

            if ($route['method'] !== $method) {
                continue;
            }

            if (!$this->checkGuard($route['guard'])) {
                return;
            }

            $params = array_filter($matches, static fn ($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
            $this->invoke($route['handler'], $params);
            return;
        }

        if ($pathMatched) {
            $this->abort(405, "Methode non autorisee.");
        }
        $this->abort(404, "Page introuvable.");
    }

    private function checkGuard(?string $guard): bool
    {
        if ($guard === null) {
            return true;
        }

        switch ($guard) {
            case 'guest':
                if (Auth::check()) {
                    redirect('/');
                    return false;
                }
                return true;

            case 'auth':
                if (!Auth::check()) {
                    Session::set('_intended', Request::path());
                    Flash::add('warning', "Merci de vous connecter pour continuer.");
                    redirect('/login');
                    return false;
                }
                return true;

            case 'admin':
                if (!Auth::check()) {
                    redirect('/login');
                    return false;
                }
                if (!Auth::isAdmin()) {
                    $this->abort(403, "Acces reserve a l'administrateur.");
                    return false;
                }
                return true;

            case 'write':
                if (!Auth::check()) {
                    redirect('/login');
                    return false;
                }
                if (!Auth::canWrite()) {
                    $this->abort(403, "Votre profil est en lecture seule.");
                    return false;
                }
                return true;
        }

        return true;
    }

    private function invoke(mixed $handler, array $params): void
    {
        if (is_callable($handler)) {
            call_user_func_array($handler, $params);
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            if (!class_exists($class)) {
                throw new \RuntimeException("Controleur introuvable : {$class}");
            }
            $controller = new $class();
            call_user_func_array([$controller, $method], array_values($params));
            return;
        }

        throw new \RuntimeException("Route malformee.");
    }

    private function abort(int $code, string $message): never
    {
        http_response_code($code);
        echo View::render('errors/error', ['code' => $code, 'message' => $message], Auth::check() ? 'layouts/app' : 'layouts/blank');
        exit;
    }
}
