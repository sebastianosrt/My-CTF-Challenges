<?php

namespace Herbarium\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes[] = ['GET', $path, $handler];
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes[] = ['POST', $path, $handler];
    }

    public function run(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        header('Content-Type: application/json');

        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri    = rtrim($uri, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route[0] !== $method) {
                continue;
            }

            $regex = $this->compile($route[1]);

            if (preg_match($regex, $uri, $matches)) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[] = $value;
                    }
                }
                call_user_func_array($route[2], $params);
                return;
            }
        }

        json_response(['error' => 'Endpoint not found'], 404);
    }

    private function compile(string $path): string
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    public function put(string $path, callable $handler): void
    {
        $this->routes[] = ['PUT', $path, $handler];
    }

    public function delete(string $path, callable $handler): void
    {
        $this->routes[] = ['DELETE', $path, $handler];
    }

    public function count(): int
    {
        return count($this->routes);
    }

    public function patch(string $path, callable $handler): void
    {
        $this->routes[] = ['PATCH', $path, $handler];
    }

    public function getRoutes(): array
    {
        $out = [];
        foreach ($this->routes as $route) {
            $out[] = ['method' => $route[0], 'path' => $route[1]];
        }
        return $out;
    }

    public function has(string $method, string $path): bool
    {
        foreach ($this->routes as $route) {
            if ($route[0] === $method && $route[1] === $path) {
                return true;
            }
        }
        return false;
    }

    public function __wakeup()
    {
        $this->routes = [];
        throw new \BadMethodCallException('Cannot unserialize ' . __CLASS__);
    }
}
