<?php

declare(strict_types=1);

namespace App\Http;

class Request
{
    private string $method;
    private string $path;
    private array $query;
    private array $headers;
    private string $rawBody;
    private ?array $jsonBody = null;
    private array $routeParams = [];

    private function __construct(string $method, string $path, array $query, array $headers, string $rawBody)
    {
        $this->method = strtoupper($method);
        $this->path = $path === '' ? '/' : $path;
        $this->query = $query;
        $this->headers = $this->normalizeHeaders($headers);
        $this->rawBody = $rawBody;
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $queryString = parse_url($uri, PHP_URL_QUERY);
        parse_str($queryString ?? '', $query);

        $headers = function_exists('getallheaders') ? (array) getallheaders() : self::serverHeaders();
        $body = file_get_contents('php://input') ?: '';

        return new self($method, $path, $query, $headers, $body);
    }

    public function withRouteParams(array $params): self
    {
        $clone = clone $this;
        $clone->routeParams = $params;
        return $clone;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getRouteParam(string $name, ?string $default = null): ?string
    {
        return $this->routeParams[$name] ?? $default;
    }

    public function jsonBody(): array
    {
        if ($this->jsonBody !== null) {
            return $this->jsonBody;
        }

        if ($this->rawBody === '') {
            $this->jsonBody = [];
            return $this->jsonBody;
        }

        $decoded = json_decode($this->rawBody, true);
        $this->jsonBody = is_array($decoded) ? $decoded : [];

        return $this->jsonBody;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $normalized = strtolower($name);
        return $this->headers[$normalized] ?? $default;
    }

    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtolower((string) $key)] = is_array($value) ? implode(',', $value) : (string) $value;
        }

        return $normalized;
    }

    private static function serverHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$headerName] = $value;
            }
        }

        return $headers;
    }
}
