<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CaService
{
    public function __construct(private readonly BaseDadosCaEpi $baseDados)
    {
    }

    public function retornarTodasAtualizacoes(string $ca): ?array
    {
        $ca = trim($ca);
        if ($ca === '') {
            return null;
        }

        $registros = [];
        foreach ($this->baseDados->retornarBaseDados() as $linha) {
            if (($linha['RegistroCA'] ?? null) === $ca) {
                $registros[] = $linha;
            }
        }

        return $registros === [] ? null : $registros;
    }

    public function retornarTodasInfoAtuais(string $ca): ?array
    {
        $atualizacoes = $this->retornarTodasAtualizacoes($ca);
        if ($atualizacoes === null) {
            return null;
        }

        return $atualizacoes[array_key_last($atualizacoes)];
    }

    public function caValido(string $ca): ?bool
    {
        $registro = $this->retornarTodasInfoAtuais($ca);
        if ($registro === null) {
            return null;
        }

        return ($registro['Situacao'] ?? '') === 'VÁLIDO';
    }

    public function exportarExcel(array $listaCAs, string $nomeArquivo): array
    {
        $filtrados = $this->filtrarPorCAs($listaCAs);
        $naoEncontrados = $this->retornaCAsNaoEncontrados($filtrados, $listaCAs);
        if ($naoEncontrados !== []) {
            return [
                'success' => false,
                'CAsNaoEncontrados' => $naoEncontrados,
            ];
        }

        $planilha = $this->gerarPlanilha($filtrados);

        return [
            'success' => true,
            'planilha' => $planilha,
            'nomeArquivo' => $nomeArquivo . '.xlsx',
        ];
    }

    public function exportarJson(array $listaCAs): array
    {
        $filtrados = $this->filtrarPorCAs($listaCAs);
        $naoEncontrados = $this->retornaCAsNaoEncontrados($filtrados, $listaCAs);
        if ($naoEncontrados !== []) {
            return [
                'success' => false,
                'CAsNaoEncontrados' => $naoEncontrados,
            ];
        }

        return [
            'success' => true,
            'JSON' => $filtrados,
        ];
    }

    private function filtrarPorCAs(array $listaCAs): array
    {
        $listaNormalizada = [];
        foreach ($listaCAs as $valor) {
            $valorNormalizado = trim((string) $valor);
            if ($valorNormalizado !== '' && !in_array($valorNormalizado, $listaNormalizada, true)) {
                $listaNormalizada[] = $valorNormalizado;
            }
        }

        if ($listaNormalizada === []) {
            return [];
        }

        $base = $this->baseDados->retornarBaseDados();
        $mapa = array_fill_keys($listaNormalizada, null);

        foreach ($base as $registro) {
            $codigo = $registro['RegistroCA'] ?? null;
            if ($codigo !== null && array_key_exists($codigo, $mapa)) {
                $mapa[$codigo] = $registro;
            }
        }

        $resultado = [];
        foreach ($listaNormalizada as $codigo) {
            if ($mapa[$codigo] !== null) {
                $resultado[] = $mapa[$codigo];
            }
        }

        return $resultado;
    }

    private function retornaCAsNaoEncontrados(array $filtrados, array $listaOriginal): array
    {
        $encontrados = array_map(static fn (array $registro): string => (string) $registro['RegistroCA'], $filtrados);
        $naoEncontrados = [];
        foreach ($listaOriginal as $valor) {
            $valor = trim((string) $valor);
            if ($valor === '') {
                continue;
            }

            if (!in_array($valor, $encontrados, true) && !in_array($valor, $naoEncontrados, true)) {
                $naoEncontrados[] = $valor;
            }
        }

        return $naoEncontrados;
    }

    private function gerarPlanilha(array $registros): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        if ($registros === []) {
            return '';
        }

        $colunas = array_keys($registros[0]);
        $sheet->fromArray($colunas, null, 'A1');

        $linhaAtual = 2;
        foreach ($registros as $registro) {
            $sheet->fromArray(array_values($registro), null, 'A' . $linhaAtual);
            $linhaAtual++;
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $planilha = (string) ob_get_clean();
        $spreadsheet->disconnectWorksheets();

        return $planilha;
    }
}
