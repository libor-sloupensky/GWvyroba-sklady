<?php
namespace App\Support;

final class Router
{
    private array $routes = ['GET'=>[], 'POST'=>[]];

    public function get(string $path, callable|array $handler): void { $this->routes['GET'][$path] = $handler; }
    public function post(string $path, callable|array $handler): void { $this->routes['POST'][$path] = $handler; }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = rtrim($uri, '/') ?: '/';
        $handler = $this->routes[$method][$path] ?? null;
        if (!$handler) { http_response_code(404); echo '404 Not Found'; return; }
        if (is_array($handler)) {
            [$class, $action] = $handler;
            $ctrl = new $class();
            $ctrl->$action();
            return;
        }
        $handler();
    }
}

