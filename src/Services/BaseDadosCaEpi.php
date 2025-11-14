<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use ZipArchive;

class BaseDadosCaEpi
{
    private const BASE_FILENAME = 'tgg_export_caepi.txt';
    private const ZIP_FILENAME = 'tgg_export_caepi.zip';
    private const COLUMN_CONFIG = 'config_nomes_colunas.csv';
    private const ERROR_FILENAME = 'CAs_com_erros.txt';
    private const FTP_HOST = 'ftp.mtps.gov.br';
    private const FTP_PATH = 'portal/fiscalizacao/seguranca-e-saude-no-trabalho/caepi/';
    private const COLUMN_COUNT = 19;
    private const SECONDS_TTL = 24 * 60 * 60;

    private ?array $baseDados = null;

    public function __construct(private readonly string $rootPath)
    {
    }

    public function retornarBaseDados(): array
    {
        if ($this->precisaAtualizarBase()) {
            $this->baixarArquivoBaseCaEPI();
            $this->baseDados = null;
        }

        if ($this->baseDados === null) {
            $this->baseDados = $this->transformarEmArrayAssociativo();
        }

        return $this->baseDados;
    }

    private function precisaAtualizarBase(): bool
    {
        $arquivoBase = $this->path(self::BASE_FILENAME);
        if (!file_exists($arquivoBase)) {
            return true;
        }

        $ultimaAtualizacao = filemtime($arquivoBase) ?: 0;
        return (time() - $ultimaAtualizacao) > self::SECONDS_TTL;
    }

    private function baixarArquivoBaseCaEPI(): void
    {
        $zipPath = $this->path(self::ZIP_FILENAME);
        $this->apagarArquivoSeExistir(self::BASE_FILENAME);
        $this->apagarArquivoSeExistir(self::ZIP_FILENAME);

        $this->downloadArquivoZip($zipPath);
        $this->extrairZip($zipPath);
        @unlink($zipPath);
    }

    private function transformarEmArrayAssociativo(): array
    {
        $linhas = $this->retornarCAsSemErros();
        if ($linhas === []) {
            throw new RuntimeException('Não foi possível carregar os dados do CAEPI.');
        }

        $nomesColunas = $this->retornaNomesColunas();
        if ($this->pareceCabecalho($linhas[0])) {
            array_shift($linhas);
        }

        $dataset = [];
        foreach ($linhas as $linha) {
            $linha = array_pad($linha, count($nomesColunas), null);
            $dataset[] = array_combine($nomesColunas, $linha);
        }

        return $dataset;
    }

    private function retornaNomesColunas(): array
    {
        $arquivoConfig = $this->path(self::COLUMN_CONFIG);
        if (!file_exists($arquivoConfig)) {
            throw new RuntimeException('Arquivo de configuração de colunas não encontrado.');
        }

        $linhas = file($arquivoConfig, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($linhas === false || !isset($linhas[0])) {
            throw new RuntimeException('Arquivo de configuração de colunas está vazio.');
        }

        $primeiraLinha = trim((string) $linhas[0]);
        $colunas = array_map('trim', explode(',', $primeiraLinha));
        if (count($colunas) !== self::COLUMN_COUNT) {
            throw new RuntimeException('A configuração de colunas não corresponde ao formato esperado.');
        }

        return $colunas;
    }

    private function retornarCAsSemErros(): array
    {
        $arquivoBase = $this->path(self::BASE_FILENAME);
        if (!file_exists($arquivoBase)) {
            throw new RuntimeException('Arquivo base não encontrado.');
        }

        $handle = fopen($arquivoBase, 'r');
        if ($handle === false) {
            throw new RuntimeException('Não foi possível abrir o arquivo base.');
        }

        $validos = [];
        $invalidos = [];

        while (($linha = fgets($handle)) !== false) {
            $linha = rtrim($linha, "\r\n");
            if ($linha === '') {
                continue;
            }

            $colunas = str_getcsv($linha, '|', '"');
            if (count($colunas) > self::COLUMN_COUNT) {
                $tratado = $this->tratarCasComErros($linha);
                if ($tratado['success']) {
                    $colunas = $tratado['linha'];
                } else {
                    $invalidos[] = $linha . PHP_EOL;
                    continue;
                }
            }

            $validos[] = $colunas;
        }

        fclose($handle);

        if ($invalidos !== []) {
            $this->criarArquivoComErros($invalidos);
        }

        return $validos;
    }

    private function tratarCasComErros(string $linha): array
    {
        $colunas = preg_split('/(?<! )\|/', $linha) ?: [];
        if (count($colunas) > self::COLUMN_COUNT) {
            return ['success' => false, 'linha' => $linha];
        }

        return ['success' => true, 'linha' => $colunas];
    }

    private function criarArquivoComErros(array $linhas): void
    {
        $arquivoErros = $this->path(self::ERROR_FILENAME);
        file_put_contents($arquivoErros, implode('', $linhas));
    }

    private function pareceCabecalho(array $linha): bool
    {
        $primeiraColuna = $linha[0] ?? '';
        return stripos($primeiraColuna, 'registro') !== false;
    }

    private function downloadArquivoZip(string $zipPath): void
    {
        if (function_exists('ftp_connect')) {
            $this->downloadViaExtensaoFtp($zipPath);
            return;
        }

        $this->downloadViaStream($zipPath);
    }

    private function downloadViaExtensaoFtp(string $zipPath): void
    {
        $conexao = ftp_connect(self::FTP_HOST);
        if ($conexao === false) {
            throw new RuntimeException('Não foi possível conectar ao servidor FTP.');
        }

        if (!ftp_login($conexao, 'anonymous', 'anonymous')) {
            ftp_close($conexao);
            throw new RuntimeException('Falha ao autenticar no FTP.');
        }

        ftp_pasv($conexao, true);

        $remoteDirectory = rtrim(self::FTP_PATH, '/');
        if (!ftp_chdir($conexao, $remoteDirectory)) {
            ftp_close($conexao);
            throw new RuntimeException('Caminho remoto não encontrado no FTP.');
        }

        $handle = fopen($zipPath, 'w+');
        if ($handle === false) {
            ftp_close($conexao);
            throw new RuntimeException('Não foi possível criar o arquivo ZIP local.');
        }

        $sucesso = ftp_fget($conexao, $handle, self::ZIP_FILENAME, FTP_BINARY);
        fclose($handle);
        ftp_close($conexao);

        if ($sucesso === false) {
            throw new RuntimeException('Falha ao baixar o arquivo ZIP via FTP.');
        }
    }

    private function downloadViaStream(string $zipPath): void
    {
        $remotePath = rtrim(self::FTP_PATH, '/');
        $url = sprintf('ftp://%s/%s/%s', self::FTP_HOST, $remotePath, self::ZIP_FILENAME);
        $context = stream_context_create([
            'ftp' => [
                'overwrite' => true,
            ],
        ]);

        if (!@copy($url, $zipPath, $context)) {
            throw new RuntimeException('Falha ao baixar o arquivo ZIP via stream FTP.');
        }
    }

    private function extrairZip(string $zipPath): void
    {
        $zip = new ZipArchive();
        $resultado = $zip->open($zipPath);
        if ($resultado !== true) {
            throw new RuntimeException('Não foi possível abrir o arquivo ZIP baixado.');
        }

        if (!$zip->extractTo($this->rootPath)) {
            $zip->close();
            throw new RuntimeException('Falha ao extrair o arquivo ZIP.');
        }

        $zip->close();
    }

    private function path(string $arquivo): string
    {
        return rtrim($this->rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $arquivo;
    }

    private function apagarArquivoSeExistir(string $arquivo): void
    {
        $caminho = $this->path($arquivo);
        if (file_exists($caminho)) {
            @unlink($caminho);
        }
    }
}
