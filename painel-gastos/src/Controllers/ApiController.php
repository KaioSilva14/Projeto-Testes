<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Period;
use App\Core\Request;
use App\Core\Response;
use App\Services\ChartService;
use App\Services\ReportService;

/**
 * Endpoints JSON que alimentam os graficos.
 *
 * Ficam separados das telas para que os dados possam ser recarregados sem
 * recarregar a pagina, e para permitir que outro cliente (app, planilha)
 * consuma os mesmos numeros.
 *
 * Todas as rotas exigem sessao autenticada e devolvem apenas dados do
 * usuario logado.
 */
final class ApiController extends Controller
{
    public function __construct(
        private readonly ChartService $charts = new ChartService(),
        private readonly ReportService $reports = new ReportService(),
    ) {
    }

    /** GET /api/resumo?mes=YYYY-MM */
    public function summary(Request $request): void
    {
        $userId = $this->userId();
        $period = $this->period($request);

        Response::json([
            'periodo'    => $period->key(),
            'rotulo'     => $period->label(),
            'indicadores' => $this->reports->kpis($userId, $period),
        ]);
    }

    /** GET /api/categorias?mes=YYYY-MM */
    public function byCategory(Request $request): void
    {
        Response::json($this->charts->byCategory($this->userId(), $this->period($request)));
    }

    /** GET /api/diario?mes=YYYY-MM */
    public function daily(Request $request): void
    {
        Response::json($this->charts->daily($this->userId(), $this->period($request)));
    }

    /** GET /api/mensal?mes=YYYY-MM&meses=12 */
    public function monthly(Request $request): void
    {
        Response::json($this->charts->monthly(
            $this->userId(),
            $this->period($request),
            $request->queryInt('meses', 12),
        ));
    }

    /** GET /api/formas-pagamento?mes=YYYY-MM */
    public function byPaymentMethod(Request $request): void
    {
        Response::json($this->charts->byPaymentMethod($this->userId(), $this->period($request)));
    }

    /** GET /api/orcamentos?mes=YYYY-MM */
    public function budgets(Request $request): void
    {
        Response::json($this->charts->budgets($this->userId(), $this->period($request)));
    }

    /** GET /api/tendencia?de=YYYY-MM&ate=YYYY-MM&top=5 */
    public function trend(Request $request): void
    {
        $userId = $this->userId();

        $to   = Period::fromString($request->query('ate'));
        $from = Period::isValid($request->query('de'))
            ? Period::fromString($request->query('de'))
            : $to->shift(-5);

        // Trava o intervalo: um "de" muito antigo na URL geraria centenas de
        // colunas e uma resposta inutilmente grande.
        $periods = Period::range($from, $to);
        $periods = array_slice($periods, -24);

        Response::json($this->charts->categoryTrend(
            $userId,
            $periods,
            $request->queryInt('top', 5),
        ));
    }
}
