<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Money;
use App\Core\Period;

/**
 * Geracao de CSV para download.
 *
 * Usa ";" como separador e grava o BOM UTF-8 porque e o que o Excel em
 * portugues abre corretamente sem passar pelo assistente de importacao.
 * O arquivo gerado tem o mesmo cabecalho que o importador aceita, entao
 * exportar e reimportar funciona sem edicao manual.
 */
final class CsvExporter
{
    private const DELIMITER = ';';

    private const BOM = "\xEF\xBB\xBF";

    /** @param array<int, array<string, mixed>> $expenses */
    public function expenses(array $expenses): string
    {
        $labels = (array) Config::get('payment_methods', []);

        $rows = [['data', 'descricao', 'valor', 'categoria', 'forma', 'obs']];

        foreach ($expenses as $expense) {
            $method = (string) $expense['payment_method'];

            $rows[] = [
                Period::formatDate((string) $expense['spent_at']),
                (string) $expense['description'],
                Money::formatPlain((int) $expense['amount_cents']),
                (string) $expense['category_name'],
                (string) ($labels[$method] ?? $method),
                (string) ($expense['notes'] ?? ''),
            ];
        }

        return $this->build($rows);
    }

    /**
     * Comparativo categoria x competencia, no mesmo layout da tela.
     *
     * @param array<int, Period>                $periods
     * @param array<int, array<string, mixed>>  $matrix
     * @param array<string, int>                $periodTotals
     */
    public function comparison(array $periods, array $matrix, array $periodTotals): string
    {
        $header = ['categoria'];

        foreach ($periods as $period) {
            $header[] = $period->shortLabel();
        }

        $header[] = 'total';

        $rows = [$header];

        foreach ($matrix as $row) {
            $line = [(string) $row['name']];

            foreach ($periods as $period) {
                $line[] = Money::formatPlain((int) ($row['periods'][$period->key()] ?? 0));
            }

            $line[] = Money::formatPlain((int) $row['total_cents']);
            $rows[] = $line;
        }

        $totals = ['TOTAL'];

        foreach ($periods as $period) {
            $totals[] = Money::formatPlain((int) ($periodTotals[$period->key()] ?? 0));
        }

        $totals[] = Money::formatPlain((int) array_sum($periodTotals));
        $rows[]   = $totals;

        return $this->build($rows);
    }

    /**
     * Serializa as linhas usando o proprio fputcsv, via stream em memoria,
     * para que aspas e separadores dentro dos campos sejam escapados.
     *
     * @param array<int, array<int, string>> $rows
     */
    private function build(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        foreach ($rows as $row) {
            fputcsv($handle, $row, self::DELIMITER, '"', '');
        }

        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return self::BOM . $content;
    }

    /** Nome de arquivo com o intervalo embutido. */
    public static function filename(string $prefix, string $from, string $to): string
    {
        return sprintf('%s_%s_a_%s.csv', $prefix, $from, $to);
    }
}
