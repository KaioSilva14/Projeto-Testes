<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Money;
use App\Core\Period;
use App\Repositories\BudgetRepository;
use App\Repositories\ExpenseRepository;

/**
 * Monta os payloads consumidos pelos graficos no navegador.
 *
 * Cada metodo devolve labels + series + cores, sem nada especifico de
 * Chart.js: trocar a biblioteca de grafico nao exige mexer aqui.
 *
 * Os valores vao em reais (float, 2 casas) porque e isso que o eixo do
 * grafico exibe; "*_cents" acompanha para tooltips exatos.
 */
final class ChartService
{
    public function __construct(
        private readonly ExpenseRepository $expenses = new ExpenseRepository(),
        private readonly BudgetRepository $budgets = new BudgetRepository(),
    ) {
    }

    /** Rosca: participacao de cada categoria no mes. */
    public function byCategory(int $userId, Period $period): array
    {
        $rows  = $this->expenses->totalsByCategory($userId, $period->firstDay(), $period->lastDay());
        $total = array_sum(array_column($rows, 'total_cents'));

        return [
            'periodo' => $period->key(),
            'rotulo'  => $period->label(),
            'total'   => $this->toReais($total),
            'labels'  => array_map(static fn (array $r): string => $r['name'], $rows),
            'cores'   => array_map(static fn (array $r): string => $r['color'], $rows),
            'valores' => array_map(fn (array $r): float => $this->toReais($r['total_cents']), $rows),
            'detalhes' => array_map(
                static fn (array $r): array => [
                    'categoria'   => $r['name'],
                    'lancamentos' => $r['expense_count'],
                    'formatado'   => Money::format($r['total_cents']),
                    'percentual'  => $total > 0 ? round($r['total_cents'] / $total * 100, 1) : 0.0,
                ],
                $rows,
            ),
        ];
    }

    /** Linha: gasto por dia e acumulado do mes. */
    public function daily(int $userId, Period $period): array
    {
        $series     = $this->expenses->dailyTotals($userId, $period);
        $cumulative = 0;
        $accumulated = [];

        foreach ($series as $day) {
            $cumulative   += $day['total_cents'];
            $accumulated[] = $this->toReais($cumulative);
        }

        return [
            'periodo'   => $period->key(),
            'rotulo'    => $period->label(),
            'labels'    => array_map(static fn (array $d): string => sprintf('%02d', $d['day']), $series),
            'diario'    => array_map(fn (array $d): float => $this->toReais($d['total_cents']), $series),
            'acumulado' => $accumulated,
            'datas'     => array_map(static fn (array $d): string => Period::formatDate($d['date']), $series),
        ];
    }

    /**
     * Barras: evolucao dos ultimos meses, com a media do intervalo.
     * A media serve de linha de referencia no grafico.
     */
    public function monthly(int $userId, Period $period, int $months = 12): array
    {
        $periods = $period->lastMonths(max(2, min(36, $months)));
        $totals  = $this->expenses->totalsByPeriod($userId, $periods);

        $active  = array_filter($totals, static fn (int $value): bool => $value > 0);
        $average = $active === [] ? 0 : (int) round(array_sum($active) / count($active));

        return [
            'labels'  => array_map(static fn (Period $p): string => $p->shortLabel(), $periods),
            'chaves'  => array_map(static fn (Period $p): string => $p->key(), $periods),
            'valores' => array_map(fn (Period $p): float => $this->toReais($totals[$p->key()]), $periods),
            'media'   => $this->toReais($average),
            'destaque' => $period->key(),
        ];
    }

    /** Barras horizontais: distribuicao por forma de pagamento. */
    public function byPaymentMethod(int $userId, Period $period): array
    {
        $rows   = $this->expenses->totalsByPaymentMethod($userId, $period->firstDay(), $period->lastDay());
        $labels = (array) Config::get('payment_methods', []);

        return [
            'periodo' => $period->key(),
            'labels'  => array_map(
                static fn (array $r): string => (string) ($labels[$r['payment_method']] ?? $r['payment_method']),
                $rows,
            ),
            'valores' => array_map(fn (array $r): float => $this->toReais($r['total_cents']), $rows),
            'contagem' => array_map(static fn (array $r): int => $r['expense_count'], $rows),
        ];
    }

    /** Barras agrupadas: orcado x gasto por categoria. */
    public function budgets(int $userId, Period $period): array
    {
        $rows = $this->budgets->definedForPeriod($userId, $period);

        return [
            'periodo' => $period->key(),
            'labels'  => array_map(static fn (array $r): string => (string) $r['name'], $rows),
            'orcado'  => array_map(fn (array $r): float => $this->toReais((int) $r['budget_cents']), $rows),
            'gasto'   => array_map(fn (array $r): float => $this->toReais((int) $r['spent_cents']), $rows),
            'uso'     => array_map(static fn (array $r): float => (float) ($r['usage_percent'] ?? 0), $rows),
            'cores'   => array_map(static fn (array $r): string => (string) $r['color'], $rows),
        ];
    }

    /**
     * Linhas multiplas: evolucao das categorias mais relevantes.
     *
     * @param array<int, Period> $periods
     */
    public function categoryTrend(int $userId, array $periods, int $topN = 5): array
    {
        $matrix = $this->expenses->matrixByCategory($userId, $periods);
        $top    = array_slice($matrix, 0, max(1, $topN));
        $keys   = array_map(static fn (Period $p): string => $p->key(), $periods);

        return [
            'labels' => array_map(static fn (Period $p): string => $p->shortLabel(), $periods),
            'series' => array_map(
                fn (array $row): array => [
                    'nome'    => $row['name'],
                    'cor'     => $row['color'],
                    'valores' => array_map(
                        fn (string $key): float => $this->toReais((int) ($row['periods'][$key] ?? 0)),
                        $keys,
                    ),
                ],
                $top,
            ),
        ];
    }

    private function toReais(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
