# Sistema CAEPI (versão PHP)

Esta API expõe consultas rápidas à base pública de Certificados de Aprovação (CAEPI) publicada diariamente pelo Ministério do Trabalho. A aplicação foi reescrita em PHP puro (sem Docker) mantendo o mesmo conjunto de rotas da versão anterior em Python.

## Principais funcionalidades
- Buscar os dados atuais de um CA (`GET /CA/{ca}`)
- Listar todas as atualizações históricas de um CA (`GET /retornarTodasAtualizacoes/{ca}`)
- Verificar se um CA está válido (`GET /validarSituacao/{ca}`)
- Exportar vários CAs em formato Excel (`POST /exportarExcel`)
- Exportar vários CAs em JSON (`POST /exportarJSON`)

A API baixa automaticamente o arquivo diário `tgg_export_caepi.zip` via FTP, normaliza a tabela e mantém os campos originais da base.

## Requisitos
- PHP **8.1+** com extensões `zip`, `json`, `ftp` (ou `curl` para fallback) habilitadas
- [Composer](https://getcomposer.org/) para instalar as dependências (PhpSpreadsheet)
- Permissão de saída na porta FTP padrão (21) para baixar a base oficial

## Instalação
```bash
composer install
```

## Execução
```bash
composer start
# ou
php -S 0.0.0.0:8000 -t public
```
A aplicação responde em `http://localhost:8000`. A rota `/` retorna `HOME` (saúde-check rápida).

## Deploy no Heroku
- O buildpack PHP oficial identifica o projeto via `composer.json` e usa o `Procfile` incluído (`web: heroku-php-apache2 public/`) para apontar o docroot correto.
- As extensões `ext-zip` e `ext-ftp` são requisitadas no Composer, garantindo que o build habilite os módulos necessários para o download/extração da base.
- Como o filesystem do dyno é efêmero, o arquivo `tgg_export_caepi.txt` será baixado novamente a cada restart/redeploy (ou armazene em um serviço externo se precisar de cache persistente).

## Formato das requisições de exportação
### `POST /exportarExcel`
```json
{
  "nomeArquivo": "meu_relatorio",
  "listaCAs": ["12345", "67890"]
}
```
A resposta é um download `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` contendo apenas os CAs encontrados (a API retorna erro 404 com a lista de códigos ausentes).

### `POST /exportarJSON`
```json
{
  "listaCAs": ["12345", "67890"]
}
```
O retorno é um array JSON com todos os campos originais da base. Em caso de CA inexistente a resposta segue o padrão:
```json
{
  "sucess": false,
  "erros": {
    "CAsNaoEncontrados": ["67890"]
  }
}
```

## Atualização automática da base
- O arquivo `tgg_export_caepi.txt` é baixado e extraído automaticamente na primeira execução.
- A cada 24 horas um novo download é realizado para manter a base atualizada (sem necessidade de cron externo).
- Linhas problemáticas são registradas em `CAs_com_erros.txt` para facilitar auditoria.

## Estrutura do projeto
```
public/          # ponto de entrada (index.php)
src/             # Application, Router, Controllers e Services
config_nomes_colunas.csv
```

## Testes e próximos passos
- Execute chamadas HTTP (Postman/cURL) após rodar `composer start`.
- Caso deseje publicar em outro ambiente, basta copiar estes arquivos e configurar um processo que execute `composer install` + `php -S ...` ou outro servidor PHP (Apache/Nginx).
