<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Money;
use App\Repositories\CategoryRepository;
use App\Repositories\ExpenseRepository;
use DateTimeImmutable;

/**
 * Importacao de extratos em CSV.
 *
 * Tolerante ao que costuma sair de banco e planilha: BOM UTF-8, separador
 * "," ou ";", cabecalhos com acento ou maiusculas, datas em d/m/Y ou Y-m-d e
 * valores em formato pt-BR ou en-US.
 *
 * Linhas invalidas nao abortam a importacao: sao reportadas com o numero da
 * linha e as demais seguem. A gravacao ocorre em uma unica transacao.
 */
final class CsvImporter
{
    /**
     * Cabecalho canonico => variacoes aceitas no arquivo.
     *
     * As variantes sao escritas na forma compacta, sem palavras de ligacao:
     * "Forma de Pagamento" e "Forma Pagamento" chegam aqui as duas como
     * "forma_pagamento" (ver normalize()).
     */
    private const HEADER_ALIASES = [
        'data'      => ['data', 'date', 'data_compra', 'data_lancamento', 'data_pagamento', 'dia'],
        'descricao' => ['descricao', 'description', 'historico', 'titulo', 'estabelecimento', 'lancamento'],
        'valor'     => ['valor', 'amount', 'value', 'preco', 'total', 'debito'],
        'categoria' => ['categoria', 'category', 'grupo', 'tipo'],
        'forma'     => [
            'forma', 'forma_pagamento', 'pagamento', 'payment_method', 'meio',
            'meio_pagamento', 'metodo', 'metodo_pagamento', 'tipo_pagamento',
        ],
        'obs'       => ['obs', 'observacoes', 'observacao', 'notas', 'notes', 'comentario', 'detalhes'],
    ];

    private const REQUIRED = ['data', 'descricao', 'valor', 'categoria'];

    private const MAX_ROWS = 5000;

    public function __construct(
        private readonly ExpenseRepository $expenses = new ExpenseRepository(),
        private readonly CategoryRepository $categories = new CategoryRepository(),
    ) {
    }

    /**
     * Processa o conteudo CSV.
     *
     * @param  bool $createCategories cria categorias novas em vez de rejeitar a linha
     * @return array{
     *     imported: int,
     *     skipped: int,
     *     created_categories: array<int, string>,
     *     errors: array<int, string>,
     *     total_cents: int
     * }
     */
    public function import(int $userId, string $content, bool $createCategories = true): array
    {
        $errors           = [];
        $createdCategories = [];

        $lines = $this->splitLines($content);

        if ($lines === []) {
            return $this->report(0, 0, [], ['O arquivo esta vazio.'], 0);
        }

        $delimiter = $this->detectDelimiter($lines[0]);
        $header    = $this->mapHeader($this->parseCsvLine($lines[0], $delimiter));

        $missing = array_diff(self::REQUIRED, array_keys($header));

        if ($missing !== []) {
            return $this->report(0, 0, [], [
                'Cabecalho invalido. Colunas obrigatorias ausentes: ' . implode(', ', $missing) . '.',
                'Esperado, por exemplo: data;descricao;valor;categoria;forma;obs',
            ], 0);
        }

        $known    = array_keys((array) Config::get('payment_methods', []));
        $existing = [];

        foreach ($this->categories->allForUser($userId) as $category) {
            $existing[mb_strtolower((string) $category['name'])] = (int) $category['id'];
        }

        $rows       = [];
        $skipped    = 0;
        $totalCents = 0;

        foreach (array_slice($lines, 1) as $index => $line) {
            $lineNumber = $index + 2; // +1 do cabecalho, +1 para base 1

            if (trim($line) === '') {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                $errors[] = sprintf(
                    'Limite de %d linhas por importacao atingido; o restante foi ignorado.',
                    self::MAX_ROWS,
                );
                break;
            }

            $cells  = $this->parseCsvLine($line, $delimiter);
            $parsed = $this->parseRow($cells, $header, $known);

            if (isset($parsed['error'])) {
                $errors[] = "Linha {$lineNumber}: {$parsed['error']}";
                $skipped++;

                continue;
            }

            $name = mb_strtolower($parsed['categoria']);

            if (!isset($existing[$name])) {
                if (!$createCategories) {
                    $errors[] = "Linha {$lineNumber}: categoria \"{$parsed['categoria']}\" nao cadastrada.";
                    $skipped++;

                    continue;
                }

                // Reserva o nome antes de gravar para nao duplicar categorias
                // que aparecem varias vezes no mesmo arquivo.
                $existing[$name]     = 0;
                $createdCategories[] = $parsed['categoria'];
            }

            $rows[] = $parsed;
            $totalCents += $parsed['amount_cents'];
        }

        if ($rows === []) {
            return $this->report(0, $skipped, [], $errors === [] ? ['Nenhuma linha valida encontrada.'] : $errors, 0);
        }

        $imported = Database::transaction(function () use ($userId, $rows): int {
            $count = 0;

            foreach ($rows as $row) {
                $categoryId = $this->categories->findOrCreate($userId, $row['categoria']);

                $this->expenses->create($userId, [
                    'category_id'    => $categoryId,
                    'description'    => $row['description'],
                    'amount_cents'   => $row['amount_cents'],
                    'spent_at'       => $row['spent_at'],
                    'payment_method' => $row['payment_method'],
                    'notes'          => $row['notes'],
                ]);

                $count++;
            }

            return $count;
        });

        return $this->report((int) $imported, $skipped, array_values(array_unique($createdCategories)), $errors, $totalCents);
    }

    /**
     * Valida e normaliza uma linha.
     *
     * @param  array<int, string|null> $cells
     * @param  array<string, int>      $header
     * @param  array<int, string>      $knownMethods
     * @return array<string, mixed>
     */
    private function parseRow(array $cells, array $header, array $knownMethods): array
    {
        $get = static function (string $key) use ($cells, $header): string {
            $index = $header[$key] ?? null;

            return $index === null ? '' : trim((string) ($cells[$index] ?? ''));
        };

        $description = $get('descricao');
        $category    = $get('categoria');

        if ($description === '') {
            return ['error' => 'descricao vazia.'];
        }

        if ($category === '') {
            return ['error' => 'categoria vazia.'];
        }

        $date = $this->parseDate($get('data'));

        if ($date === null) {
            return ['error' => 'data invalida ("' . $get('data') . '"); use dd/mm/aaaa ou aaaa-mm-dd.'];
        }

        $cents = Money::toCents($get('valor'));

        if ($cents === null) {
            return ['error' => 'valor invalido ("' . $get('valor') . '").'];
        }

        // Extratos costumam trazer despesas com sinal negativo.
        $cents = abs($cents);

        if ($cents === 0) {
            return ['error' => 'valor deve ser maior que zero.'];
        }

        $method = $this->parseMethod($get('forma'), $knownMethods);
        $notes  = $get('obs');

        return [
            'description'    => mb_substr($description, 0, 160),
            'categoria'      => mb_substr($category, 0, 60),
            'amount_cents'   => $cents,
            'spent_at'       => $date,
            'payment_method' => $method,
            'notes'          => $notes === '' ? null : mb_substr($notes, 0, 500),
        ];
    }

    /** Aceita d/m/Y, d-m-Y, Y-m-d e Y/m/d. */
    private function parseDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        // Ignora hora eventualmente colada na data.
        $value = (string) preg_replace('/\s+\d{1,2}:\d{2}(:\d{2})?$/', '', $value);

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d', 'd/m/y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);

            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /** @param array<int, string> $known */
    private function parseMethod(string $value, array $known): string
    {
        $normalized = $this->normalize($value);

        if ($normalized === '') {
            return 'pix';
        }

        if (in_array($normalized, $known, true)) {
            return $normalized;
        }

        // Casa rotulos livres do extrato com as chaves internas. Os valores
        // estao na forma compacta produzida por normalize(), entao
        // "Cartao de Credito" chega aqui como "cartao_credito".
        $guesses = [
            'credito'       => ['cartao_credito', 'credit', 'credito', 'cc'],
            'debito'        => ['cartao_debito', 'debit', 'debito'],
            'dinheiro'      => ['cash', 'especie', 'dinheiro'],
            'boleto'        => ['boleto', 'bank_slip'],
            'transferencia' => ['ted', 'doc', 'transferencia', 'transfer'],
            'pix'           => ['pix'],
        ];

        foreach ($guesses as $method => $aliases) {
            if (in_array($normalized, $aliases, true)) {
                return $method;
            }
        }

        return 'pix';
    }

    /**
     * Relaciona as colunas do arquivo com os nomes canonicos.
     *
     * @param  array<int, string|null> $cells
     * @return array<string, int>
     */
    private function mapHeader(array $cells): array
    {
        $map = [];

        foreach ($cells as $index => $cell) {
            $normalized = $this->normalize((string) $cell);

            foreach (self::HEADER_ALIASES as $canonical => $aliases) {
                if (in_array($normalized, $aliases, true) && !isset($map[$canonical])) {
                    $map[$canonical] = (int) $index;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * Minusculas, sem acento, com "_" no lugar de separadores e sem as
     * palavras de ligacao "de/do/da/of".
     *
     * Descartar as ligacoes e o que faz "Forma de Pagamento", "Forma
     * Pagamento" e "FORMA_DE_PAGAMENTO" colapsarem no mesmo alias, em vez de
     * exigir uma entrada na tabela para cada grafia.
     */
    private function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value));

        $value = strtr($value, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'ê' => 'e', 'è' => 'e',
            'í' => 'i', 'î' => 'i',
            'ó' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ü' => 'u', 'û' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);

        $value = (string) preg_replace('/[^a-z0-9]+/', '_', $value);
        $value = trim($value, '_');

        if ($value === '') {
            return '';
        }

        $tokens = array_values(array_filter(
            explode('_', $value),
            static fn (string $token): bool => $token !== ''
                && !in_array($token, ['de', 'do', 'da', 'dos', 'das', 'of', 'the'], true),
        ));

        // Um cabecalho que seja so uma ligacao ("de") volta intacto.
        return $tokens === [] ? $value : implode('_', $tokens);
    }

    /**
     * Quebra uma linha em celulas.
     *
     * O escape e passado explicitamente como "" (vazio) para seguir o RFC
     * 4180 -- aspas escapadas por duplicacao -- e para nao depender do valor
     * padrao do parametro, cujo uso implicito o PHP 8.4 deprecia.
     *
     * @return array<int, string|null>
     */
    private function parseCsvLine(string $line, string $delimiter): array
    {
        return str_getcsv($line, $delimiter, '"', '');
    }

    /**
     * Separa o conteudo em linhas nao vazias.
     *
     * Limitacao conhecida: um campo entre aspas que contenha quebra de linha
     * seria dividido em duas linhas. Extratos bancarios nao usam esse
     * recurso, e tratar o caso exigiria ler o arquivo via fgetcsv.
     *
     * @return array<int, string>
     */
    private function splitLines(string $content): array
    {
        // Remove BOM UTF-8 gravado por Excel e normaliza fim de linha.
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        $lines = array_values(array_filter(
            explode("\n", $content),
            static fn (string $line): bool => trim($line) !== '',
        ));

        return $lines;
    }

    /** Escolhe o separador mais frequente no cabecalho. */
    private function detectDelimiter(string $headerLine): string
    {
        $counts = [
            ';'    => substr_count($headerLine, ';'),
            ','    => substr_count($headerLine, ','),
            "\t"   => substr_count($headerLine, "\t"),
            '|'    => substr_count($headerLine, '|'),
        ];

        arsort($counts);

        $best = (string) array_key_first($counts);

        return $counts[$best] > 0 ? $best : ',';
    }

    /**
     * @param  array<int, string> $createdCategories
     * @param  array<int, string> $errors
     * @return array{imported: int, skipped: int, created_categories: array<int, string>, errors: array<int, string>, total_cents: int}
     */
    private function report(int $imported, int $skipped, array $createdCategories, array $errors, int $totalCents): array
    {
        return [
            'imported'           => $imported,
            'skipped'            => $skipped,
            'created_categories' => $createdCategories,
            // Limita o retorno para nao inundar a tela em arquivos ruins.
            'errors'             => array_slice($errors, 0, 50),
            'total_cents'        => $totalCents,
        ];
    }

    /** Modelo de arquivo oferecido para download na tela de importacao. */
    public static function template(): string
    {
        $today     = (new DateTimeImmutable('now'))->format('d/m/Y');
        $yesterday = (new DateTimeImmutable('yesterday'))->format('d/m/Y');

        return implode("\n", [
            'data;descricao;valor;categoria;forma;obs',
            "{$today};Supermercado do mes;432,90;Alimentacao;debito;compra semanal",
            "{$today};Assinatura de streaming;39,90;Lazer;credito;",
            "{$yesterday};Recarga de transporte;50,00;Transporte;pix;",
        ]) . "\n";
    }
}
