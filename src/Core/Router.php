<?php

declare(strict_types=1);

namespace SysRevAI\Core;

/**
 * Tiny regex router with route parameters and named middleware.
 *
 * Handlers are either a callable or a [ControllerClass::class, 'method'] pair.
 * Route params ({id}) are passed to the handler as ordered arguments.
 */
final class Router
{
    /** @var array<int,array{method:string,regex:string,params:string[],handler:mixed,middleware:string[]}> */
    private array $routes = [];

    public function get(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function add(string $method, string $path, mixed $handler, array $middleware = []): void
    {
        $params = [];
        $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', static function ($m) use (&$params) {
            $params[] = $m[1];
            return '([^/]+)';
        }, $path);

        $this->routes[] = [
            'method'     => $method,
            'regex'      => '#^' . $regex . '$#',
            'params'     => $params,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = '/' . trim(parse_url($uri, PHP_URL_PATH) ?: '/', '/');
        $method = strtoupper($method);

        // CSRF guard for all state-changing requests.
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            Csrf::check();
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            foreach ($route['middleware'] as $mw) {
                if (!$this->runMiddleware($mw)) {
                    return; // middleware already issued a redirect/response
                }
            }

            $args = array_slice($matches, 1);
            $this->invoke($route['handler'], $args);
            return;
        }

        $this->notFound();
    }

    private function runMiddleware(string $name): bool
    {
        return match ($name) {
            'auth' => Auth::check() ? true : $this->redirect('/login'),
            'guest' => Auth::check() ? $this->redirect('/dashboard') : true,
            'admin' => Auth::hasRole('owner', 'admin') ? true : $this->forbidden(),
            default => true,
        };
    }

    private function invoke(mixed $handler, array $args): void
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $instance = new $class();
            $instance->$method(...$args);
            return;
        }
        if (is_callable($handler)) {
            $handler(...$args);
            return;
        }
        throw new \RuntimeException('Invalid route handler.');
    }

    private function redirect(string $to): bool
    {
        header('Location: ' . $to);
        return false;
    }

    private function notFound(): void
    {
        http_response_code(404);
        echo View::render('errors/404', [], 'layouts/auth');
    }

    private function forbidden(): bool
    {
        http_response_code(403);
        echo View::render('errors/403', [], 'layouts/auth');
        return false;
    }
}
