<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\CaService;

class CaController
{
    public function __construct(private readonly CaService $service)
    {
    }

    public function home(): Response
    {
        return Response::text('API de 👷🏼Consulta de CAs - Funcionando! Versão 1.2.0');
    }

    public function retornarInfoAtual(Request $request): Response
    {
        $ca = $request->getRouteParam('ca', '');
        $dados = $this->service->retornarTodasInfoAtuais($ca);
        if ($dados === null) {
            return $this->caNaoEncontrado();
        }

        return Response::json($dados);
    }

    public function retornarTodasAtualizacoes(Request $request): Response
    {
        $ca = $request->getRouteParam('ca', '');
        $dados = $this->service->retornarTodasAtualizacoes($ca);
        if ($dados === null) {
            return $this->caNaoEncontrado();
        }

        return Response::json($dados);
    }

    public function validarSituacao(Request $request): Response
    {
        $ca = $request->getRouteParam('ca', '');
        $estaValido = $this->service->caValido($ca);
        if ($estaValido === null) {
            return $this->caNaoEncontrado();
        }

        return Response::json($estaValido);
    }

    public function exportarExcel(Request $request): Response
    {
        $payload = $this->normalizarPayloadParaExportacao($request);
        if ($payload['erro'] !== null) {
            return $payload['erro'];
        }

        $resultado = $this->service->exportarExcel($payload['listaCAs'], $payload['nomeArquivo']);
        if ($resultado['success'] === false) {
            return $this->casNaoEncontrados($resultado['CAsNaoEncontrados']);
        }

        return Response::download(
            $resultado['planilha'],
            $resultado['nomeArquivo'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function exportarJson(Request $request): Response
    {
        $payload = $this->normalizarPayloadParaExportacao($request, false);
        if ($payload['erro'] !== null) {
            return $payload['erro'];
        }

        $resultado = $this->service->exportarJson($payload['listaCAs']);
        if ($resultado['success'] === false) {
            return $this->casNaoEncontrados($resultado['CAsNaoEncontrados']);
        }

        return Response::json($resultado['JSON']);
    }

    private function normalizarPayloadParaExportacao(Request $request, bool $exigeNomeArquivo = true): array
    {
        $body = $request->jsonBody();
        $lista = array_key_exists('listaCAs', $body) && is_array($body['listaCAs']) ? $body['listaCAs'] : [];
        $listaNormalizada = array_values(array_filter(array_map('strval', $lista), static fn (string $value) => $value !== ''));

        if ($listaNormalizada === []) {
            return [
                'erro' => $this->listaVazia(),
                'listaCAs' => [],
                'nomeArquivo' => null,
            ];
        }

        $nomeArquivo = $body['nomeArquivo'] ?? 'exportacao_cas';
        $nomeArquivo = is_string($nomeArquivo) ? $nomeArquivo : 'exportacao_cas';
        $nomeArquivo = $this->sanitizarNomeArquivo($nomeArquivo);

        if ($exigeNomeArquivo === false) {
            $nomeArquivo = null;
        }

        return [
            'erro' => null,
            'listaCAs' => $listaNormalizada,
            'nomeArquivo' => $nomeArquivo,
        ];
    }

    private function caNaoEncontrado(): Response
    {
        return Response::json([
            'sucess' => false,
            'erros' => ['Numero Ca não encontrado!'],
        ], 404);
    }

    private function listaVazia(): Response
    {
        return Response::json([
            'sucess' => false,
            'erros' => ['listaCAs não pode estar vazia'],
        ], 404);
    }

    private function casNaoEncontrados(array $lista): Response
    {
        return Response::json([
            'sucess' => false,
            'erros' => [
                'CAsNaoEncontrados' => $lista,
            ],
        ], 404);
    }

    private function sanitizarNomeArquivo(string $nomeArquivo): string
    {
        $sanitizado = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $nomeArquivo) ?: 'exportacao_cas';
        return trim($sanitizado, '_') !== '' ? trim($sanitizado, '_') : 'exportacao_cas';
    }
}
