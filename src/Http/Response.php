<?php

declare(strict_types=1);

namespace App\Http;

class Response
{
    private string $body;
    private int $status;
    private array $headers;

    public function __construct(string $body, int $status = 200, array $headers = [])
    {
        $this->body = $body;
        $this->status = $status;
        $this->headers = $headers;
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $encoded = json_encode([
                'success' => false,
                'errors' => ['Falha ao serializar JSON.'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            $status = 500;
        }

        $headers = array_merge(['Content-Type' => 'application/json; charset=utf-8'], $headers);

        return new self($encoded, $status, $headers);
    }

    public static function text(string $body, int $status = 200, array $headers = []): self
    {
        $headers = array_merge(['Content-Type' => 'text/plain; charset=utf-8'], $headers);
        return new self($body, $status, $headers);
    }

    public static function download(string $binary, string $filename, string $contentType): self
    {
        return new self(
            $binary,
            200,
            [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => (string) strlen($binary),
            ]
        );
    }

    public static function noContent(): self
    {
        return new self('', 204);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $key => $value) {
            header($key . ': ' . $value);
        }

        echo $this->body;
    }
}
