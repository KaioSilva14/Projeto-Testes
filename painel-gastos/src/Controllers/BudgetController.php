<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Money;
use App\Core\Period;
use App\Core\Request;
use App\Repositories\BudgetRepository;
use App\Repositories\CategoryRepository;

final class BudgetController extends Controller
{
    public function __construct(
        private readonly BudgetRepository $budgets = new BudgetRepository(),
        private readonly CategoryRepository $categories = new CategoryRepository(),
    ) {
    }

    public function index(Request $request): void
    {
        $userId = $this->userId();
        $period = $this->period($request);
        $rows   = $this->budgets->forPeriod($userId, $period);

        $budgetTotal = array_sum(array_column($rows, 'budget_cents'));
        $spentTotal  = array_sum(array_column($rows, 'spent_cents'));

        $this->view('budgets/index', [
            'title'          => 'Orcamentos',
            'period'         => $period,
            'rows'           => $rows,
            'budgetTotal'    => $budgetTotal,
            'spentTotal'     => $spentTotal,
            'remainingTotal' => $budgetTotal - $spentTotal,
            'usagePercent'   => $budgetTotal > 0 ? round($spentTotal / $budgetTotal * 100, 1) : null,
            'hasCategories'  => $this->categories->count($userId) > 0,
            'previousPeriod' => $period->previous(),
            'previousCount'  => $this->budgets->count($userId, $period->previous()),
        ]);
    }

    /**
     * Grava a tela inteira de uma vez.
     *
     * Os inputs chegam como orcamentos[category_id] = "500,00"; valores
     * vazios ou zerados removem o orcamento da categoria.
     */
    public function save(Request $request): void
    {
        $userId = $this->userId();
        $period = Period::fromString($request->input('period'));
        $inputs = $request->arrayInput('orcamentos');

        if ($inputs === []) {
            $this->failure('Nenhum orcamento informado.', '/orcamentos', ['mes' => $period->key()]);
        }

        $valid   = $this->categories->namesById($userId);
        $saved   = 0;
        $removed = 0;
        $invalid = [];

        Database::transaction(function () use ($userId, $period, $inputs, $valid, &$saved, &$removed, &$invalid): void {
            foreach ($inputs as $categoryId => $value) {
                $categoryId = (int) $categoryId;

                // Ignora ids que nao pertencem ao usuario.
                if (!isset($valid[$categoryId])) {
                    continue;
                }

                $raw = is_scalar($value) ? trim((string) $value) : '';

                if ($raw === '') {
                    $this->budgets->delete($userId, $categoryId, $period);
                    $removed++;

                    continue;
                }

                $cents = Money::toCents($raw);

                if ($cents === null || $cents < 0) {
                    $invalid[] = $valid[$categoryId];

                    continue;
                }

                $this->budgets->save($userId, $categoryId, $period, $cents);

                if ($cents > 0) {
                    $saved++;
                } else {
                    $removed++;
                }
            }
        });

        if ($invalid !== []) {
            $this->failure(
                'Valores invalidos em: ' . implode(', ', $invalid) . '. Os demais foram salvos.',
                '/orcamentos',
                ['mes' => $period->key()],
            );
        }

        $this->success(
            sprintf('Orcamentos de %s atualizados (%d definidos, %d removidos).', $period->label(), $saved, $removed),
            '/orcamentos',
            ['mes' => $period->key()],
        );
    }

    /** Copia os orcamentos do mes anterior para o mes de referencia. */
    public function copyPrevious(Request $request): void
    {
        $userId   = $this->userId();
        $period   = Period::fromString($request->input('period'));
        $previous = $period->previous();

        if ($this->budgets->count($userId, $previous) === 0) {
            $this->failure(
                'Nao ha orcamentos em ' . $previous->label() . ' para copiar.',
                '/orcamentos',
                ['mes' => $period->key()],
            );
        }

        $this->budgets->copyPeriod($userId, $previous, $period);

        $this->success(
            'Orcamentos de ' . $previous->label() . ' copiados para ' . $period->label() . '.',
            '/orcamentos',
            ['mes' => $period->key()],
        );
    }
}
