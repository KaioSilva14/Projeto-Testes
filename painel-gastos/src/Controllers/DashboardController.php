<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Repositories\CategoryRepository;
use App\Repositories\ExpenseRepository;
use App\Services\ReportService;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly ExpenseRepository $expenses = new ExpenseRepository(),
        private readonly CategoryRepository $categories = new CategoryRepository(),
        private readonly ReportService $reports = new ReportService(),
    ) {
    }

    public function index(Request $request): void
    {
        $userId = $this->userId();
        $period = $this->period($request);

        $categoryTotals = $this->expenses->totalsByCategory(
            $userId,
            $period->firstDay(),
            $period->lastDay(),
        );

        $this->view('dashboard/index', [
            'title'          => 'Painel',
            'period'         => $period,
            'kpis'           => $this->reports->kpis($userId, $period),
            'categoryTotals' => $categoryTotals,
            'budgets'        => $this->reports->budgetStatus($userId, $period),
            'recent'         => $this->expenses->recent($userId, 6),
            'topExpenses'    => $this->expenses->topExpenses($userId, $period->firstDay(), $period->lastDay(), 5),
            'hasCategories'  => $this->categories->count($userId) > 0,
            'hasExpenses'    => $this->expenses->count($userId) > 0,
        ]);
    }
}
