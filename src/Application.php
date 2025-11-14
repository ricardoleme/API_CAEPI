<?php

declare(strict_types=1);

namespace App;

use App\Controllers\CaController;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Services\BaseDadosCaEpi;
use App\Services\CaService;
use Throwable;

class Application
{
    private Router $router;

    /**
     * Lista de origens liberadas para CORS.
     * @var string[]
     */
    private array $allowedOrigins = [
        'http://localhost:4200',
        'https://epi-semitronic.vercel.app',
        'https://api-basecaepi.onrender.com',
        'https://v0.app/chat/epi-delivery-system-kbyJLWyB1q9',
    ];

    public function __construct()
    {
        $basePath = dirname(__DIR__);
        $baseDados = new BaseDadosCaEpi($basePath);
        $caService = new CaService($baseDados);
        $controller = new CaController($caService);

        $this->router = new Router();
        $this->router->get('/', [$controller, 'home']);
        $this->router->get('/CA/{ca}', [$controller, 'retornarInfoAtual']);
        $this->router->get('/retornarTodasAtualizacoes/{ca}', [$controller, 'retornarTodasAtualizacoes']);
        $this->router->get('/validarSituacao/{ca}', [$controller, 'validarSituacao']);
        $this->router->post('/exportarExcel', [$controller, 'exportarExcel']);
        $this->router->post('/exportarJSON', [$controller, 'exportarJson']);
    }

    public function run(): void
    {
        $request = Request::fromGlobals();
        $this->applyCors($request->header('Origin'));

        if ($request->getMethod() === 'OPTIONS') {
            Response::noContent()->send();
            return;
        }

        try {
            $response = $this->router->dispatch($request);
        } catch (Throwable $throwable) {
            $response = Response::json([
                'success' => false,
                'errors' => ['Erro interno inesperado. Consulte os logs do servidor.'],
            ], 500);
        }

        $response->send();
    }

    private function applyCors(?string $origin): void
    {
        $resolvedOrigin = $this->resolveOrigin($origin);
        header('Access-Control-Allow-Origin: ' . $resolvedOrigin);
        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }

    private function resolveOrigin(?string $origin): string
    {
        if ($origin === null) {
            return '*';
        }

        foreach ($this->allowedOrigins as $allowed) {
            if (strcasecmp($allowed, $origin) === 0) {
                return $allowed;
            }
        }

        return '*';
    }
}
