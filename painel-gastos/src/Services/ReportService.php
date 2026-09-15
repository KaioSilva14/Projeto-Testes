<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Period;
use App\Repositories\BudgetRepository;
use App\Repositories\ExpenseRepository;

/**
 * Regras de calculo dos indicadores. Fica separada dos controllers para que
 * dashboard, relatorios e API produzam exatamente os mesmos numeros.
 */
final class ReportService
{
    public function __construct(
        private readonly ExpenseRepository $expenses = new ExpenseRepository(),
        private readonly BudgetRepository $budgets = new BudgetRepository(),
    ) {
    }

    /**
     * Indicadores do mes de referencia comparados ao mes anterior.
     *
     * @return array{
     *     total_cents: int,
     *     expense_count: int,
     *     average_cents: int,
     *     max_cents: int,
     *     daily_average_cents: int,
     *     projection_cents: int,
     *     previous_total_cents: int,
     *     variation_percent: float|null,
     *     variation_cents: int,
     *     budget_total_cents: int,
     *     budget_usage_percent: float|null,
     *     budget_remaining_cents: int,
     *     elapsed_days: int,
     *     days_in_month: int
     * }
     */
    public function kpis(int $userId, Period $period): array
    {
        $summary       = $this->expenses->summary($userId, $period->firstDay(), $period->lastDay());
        $previous      = $period->previous();
        $previousTotal = $this->expenses->totalBetween($userId, $previous->firstDay(), $previous->lastDay());

        $elapsed     = max(1, $period->elapsedDays());
        $daysInMonth = $period->daysInMonth();
        $total       = $summary['total_cents'];

        $dailyAverage = (int) round($total / $elapsed);

        // Em meses fechados a projecao e o proprio total realizado.
        $projection = $period->isCurrentMonth()
            ? $dailyAverage * $daysInMonth
            : $total;

        $budgetTotal = $this->budgets->totalForPeriod($userId, $period);

        return [
            'total_cents'            => $total,
            'expense_count'          => $summary['expense_count'],
            'average_cents'          => $summary['average_cents'],
            'max_cents'              => $summary['max_cents'],
            'daily_average_cents'    => $dailyAverage,
            'projection_cents'       => $projection,
            'previous_total_cents'   => $previousTotal,
            'variation_percent'      => $this->variation($previousTotal, $total),
            'variation_cents'        => $total - $previousTotal,
            'budget_total_cents'     => $budgetTotal,
            'budget_usage_percent'   => $budgetTotal > 0 ? round($total / $budgetTotal * 100, 1) : null,
            'budget_remaining_cents' => $budgetTotal - $total,
            'elapsed_days'           => $period->elapsedDays(),
            'days_in_month'          => $daysInMonth,
        ];
    }

    /**
     * Variacao percentual entre dois valores.
     *
     * Retorna null quando a base e zero: "aumento infinito" nao informa nada,
     * e a view exibe "-" nesse caso.
     */
    public function variation(int $from, int $to): ?float
    {
        if ($from === 0) {
            return null;
        }

        return round(($to - $from) / abs($from) * 100, 1);
    }

    /**
     * Comparativo categoria x competencia usado na tela de relatorios.
     *
     * @param  array<int, Period> $periods
     * @return array{
     *     periods: array<int, Period>,
     *     rows: array<int, array<string, mixed>>,
     *     period_totals: array<string, int>,
     *     grand_total_cents: int,
     *     average_cents: int,
     *     best_period: string|null,
     *     worst_period: string|null
     * }
     */
    public function comparison(int $userId, array $periods): array
    {
        $rows         = $this->expenses->matrixByCategory($userId, $periods);
        $periodTotals = $this->expenses->totalsByPeriod($userId, $periods);
        $grandTotal   = array_sum($periodTotals);

        // Meses sem nenhum lancamento nao entram na media, senao um mes
        // futuro incluido no intervalo derrubaria o indicador.
        $active = array_filter($periodTotals, static fn (int $value): bool => $value > 0);

        $best  = null;
        $worst = null;

        if ($active !== []) {
            $best  = (string) array_search(max($active), $active, true);
            $worst = (string) array_search(min($active), $active, true);
        }

        return [
            'periods'           => $periods,
            'rows'              => $rows,
            'period_totals'     => $periodTotals,
            'grand_total_cents' => $grandTotal,
            'average_cents'     => $active === [] ? 0 : (int) round($grandTotal / count($active)),
            'best_period'       => $best,
            'worst_period'      => $worst,
        ];
    }

    /**
     * Situacao de cada orcamento, classificada para colorir a barra.
     *
     * @return array<int, array<string, mixed>>
     */
    public function budgetStatus(int $userId, Period $period): array
    {
        return array_map(
            static function (array $row): array {
                $percent = $row['usage_percent'] ?? 0.0;

                $row['status'] = match (true) {
                    $percent >= 100 => 'estourado',
                    $percent >= 80  => 'atencao',
                    default         => 'ok',
                };

                return $row;
            },
            $this->budgets->definedForPeriod($userId, $period),
        );
    }

    /**
     * Intervalo padrao de relatorio: os N ultimos meses com dados, limitado
     * ao primeiro lancamento do usuario.
     *
     * @return array{from: Period, to: Period}
     */
    public function defaultReportRange(int $userId, int $months = 6): array
    {
        $to   = Period::current();
        $from = $to->shift(-($months - 1));

        $firstPeriod = $this->expenses->firstPeriod($userId);

        if ($firstPeriod !== null && $firstPeriod > $from->key()) {
            $from = Period::fromString($firstPeriod);
        }

        return ['from' => $from, 'to' => $to];
    }
}
