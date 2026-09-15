<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Period;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ExpenseRepository;
use App\Services\CsvExporter;
use App\Services\ReportService;

final class ReportController extends Controller
{
    /** Teto de meses no comparativo, para a tabela continuar legivel. */
    private const MAX_MONTHS = 24;

    public function __construct(
        private readonly ExpenseRepository $expenses = new ExpenseRepository(),
        private readonly ReportService $reports = new ReportService(),
    ) {
    }

    public function index(Request $request): void
    {
        $userId              = $this->userId();
        ['from' => $from, 'to' => $to] = $this->range($request, $userId);

        $periods    = $this->limit(Period::range($from, $to));
        $comparison = $this->reports->comparison($userId, $periods);

        $this->view('reports/index', [
            'title'       => 'Relatorios',
            'from'        => $from,
            'to'          => $to,
            'comparison'  => $comparison,
            'topExpenses' => $this->expenses->topExpenses(
                $userId,
                $from->firstDay(),
                $to->lastDay(),
                10,
            ),
            'paymentTotals' => $this->expenses->totalsByPaymentMethod(
                $userId,
                $from->firstDay(),
                $to->lastDay(),
            ),
            'monthCount' => count($periods),
        ]);
    }

    /** Exporta o comparativo exibido na tela. */
    public function export(Request $request): void
    {
        $userId = $this->userId();
        ['from' => $from, 'to' => $to] = $this->range($request, $userId);

        $periods    = $this->limit(Period::range($from, $to));
        $comparison = $this->reports->comparison($userId, $periods);

        if ($comparison['grand_total_cents'] === 0) {
            $this->failure('Nao ha dados no intervalo selecionado.', '/relatorios', [
                'de'  => $from->key(),
                'ate' => $to->key(),
            ]);
        }

        Response::download(
            (new CsvExporter())->comparison(
                $periods,
                $comparison['rows'],
                $comparison['period_totals'],
            ),
            CsvExporter::filename('relatorio', $from->key(), $to->key()),
        );
    }

    /**
     * Intervalo de competencias da tela.
     *
     * Sem parametros, usa os ultimos 6 meses a partir do primeiro
     * lancamento do usuario.
     *
     * @return array{from: Period, to: Period}
     */
    private function range(Request $request, int $userId): array
    {
        $fromInput = $request->query('de');
        $toInput   = $request->query('ate');

        if (!Period::isValid($fromInput) || !Period::isValid($toInput)) {
            return $this->reports->defaultReportRange($userId, 6);
        }

        $from = Period::fromString($fromInput);
        $to   = Period::fromString($toInput);

        if ($from->key() > $to->key()) {
            [$from, $to] = [$to, $from];
        }

        return ['from' => $from, 'to' => $to];
    }

    /**
     * Mantem apenas os ultimos MAX_MONTHS periodos do intervalo.
     *
     * @param  array<int, Period> $periods
     * @return array<int, Period>
     */
    private function limit(array $periods): array
    {
        return count($periods) <= self::MAX_MONTHS
            ? $periods
            : array_slice($periods, -self::MAX_MONTHS);
    }
}
