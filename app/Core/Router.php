<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Minimal MVC router with route middleware and safe error rendering.
 *
 * Supported patterns: /equipment, /equipment/{id}, /equipment/{id}/edit
 * Middleware: 'auth', 'role:admin', 'role:admin|instructor'
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, handler:callable, middleware:array}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    private function add(string $method, string $pattern, callable $handler, array $middleware): void
    {
        $pattern = '/' . trim($pattern, '/');
        if ($pattern === '//') {
            $pattern = '/';
        }
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[0-9]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        $this->routes[] = [
            'method'     => $method,
            'pattern'    => $pattern,
            'regex'      => $regex,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        $path = $this->normalise($path);
        $matchedPath = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            $matchedPath = true;
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            if (!$this->runMiddleware($route['middleware'])) {
                return;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            try {
                ($route['handler'])(...array_values($params));
            } catch (Throwable $e) {
                $this->handleException($e);
            }
            return;
        }

        if ($matchedPath) {
            http_response_code(405);
            View::render('errors/405', ['title' => 'Method Not Allowed'], 'layouts/error');
            return;
        }

        http_response_code(404);
        View::render('errors/404', ['title' => 'Page Not Found'], 'layouts/error');
    }

    /** @param array<int, string> $middleware */
    private function runMiddleware(array $middleware): bool
    {
        foreach ($middleware as $rule) {
            if ($rule === 'auth') {
                if (!Auth::check()) {
                    Flash::set('warning', 'Please sign in to continue.');
                    Session::set('_intended_url', $_SERVER['REQUEST_URI'] ?? url('dashboard'));
                    redirect(url('login'));
                }
                continue;
            }

            if (str_starts_with($rule, 'role:')) {
                $roles = explode('|', substr($rule, 5));
                if (!Auth::hasRole($roles)) {
                    http_response_code(403);
                    View::render('errors/403', ['title' => 'Access Denied'], 'layouts/error');
                    return false;
                }
            }
        }
        return true;
    }

    private function normalise(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        $path = rawurldecode($path);

        $base = base_path();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        if (isset($_GET['route']) && is_string($_GET['route'])) {
            $path = '/' . trim($_GET['route'], '/');
        }

        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }

    private function handleException(Throwable $e): void
    {
        error_log('[CodeBlocks] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        Database::rollback();

        // Diagnostics are written to the server log only. Even when debug mode is
        // enabled, visitors receive a friendly page without paths, SQL, or traces.
        http_response_code(500);
        View::render('errors/500', ['title' => 'Server Error'], 'layouts/error');
    }
}
