<?php

declare(strict_types=1);

namespace App\Http;

class Router
{
    /** @var array<int, array{method:string, regex:string, handler:callable}> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    private function addRoute(string $method, string $path, callable $handler): void
    {
        $normalizedPath = $this->normalizePath($path);
        $regex = $this->buildRegex($normalizedPath);

        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => $regex,
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $path = $this->normalizePath($request->getPath());
        $method = $request->getMethod();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['regex'], $path, $matches) === 1) {
                $params = $this->extractParams($matches);
                $requestWithParams = $request->withRouteParams($params);
                $handler = $route['handler'];
                $response = $handler($requestWithParams);
                return $response instanceof Response ? $response : Response::json($response ?? []);
            }
        }

        return Response::json([
            'success' => false,
            'errors' => ['Rota não encontrada.'],
        ], 404);
    }

    private function extractParams(array $matches): array
    {
        $params = [];
        foreach ($matches as $key => $value) {
            if (!is_int($key)) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    private function normalizePath(string $path): string
    {
        if ($path === '') {
            return '/';
        }

        $trimmed = rtrim($path, '/');
        return $trimmed === '' ? '/' : $trimmed;
    }

    private function buildRegex(string $path): string
    {
        $pattern = preg_replace('#\{([a-zA-Z0-9_]+)\}#', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
}
