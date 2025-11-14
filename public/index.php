<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'errors' => ['Dependências não instaladas. Execute "composer install" antes de iniciar a API.'],
    ], JSON_UNESCAPED_UNICODE);
    exit(1);
}

require_once $autoload;

$app = new App\Application();
$app->run();
