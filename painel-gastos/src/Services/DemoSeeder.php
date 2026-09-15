<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Period;
use App\Repositories\BudgetRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\UserRepository;
use DateTimeImmutable;

/**
 * Popula o banco com 12 meses de dados ficticios.
 *
 * Existe para que os graficos tenham o que mostrar na primeira execucao.
 * Os valores usam mt_srand com semente fixa, entao rodar o seed duas vezes
 * gera exatamente o mesmo conjunto de dados.
 */
final class DemoSeeder
{
    public const EMAIL = 'demo@painel.local';

    public const PASSWORD = 'senha123';

    private const SEED = 20260915;

    /**
     * Catalogo de categorias: nome, cor, orcamento mensal (centavos),
     * faixa de valor por lancamento e quantos lancamentos por mes.
     *
     * @var array<int, array{0: string, 1: string, 2: int, 3: int, 4: int, 5: int, 6: int}>
     */
    private const CATALOG = [
        //  nome              cor        orcamento  min      max     qtd min  qtd max
        ['Alimentacao',     '#dc2626',  120000,    1500,   18000,   8,  14],
        ['Moradia',         '#2563eb',  180000,   40000,  180000,   1,   3],
        ['Transporte',      '#d97706',   45000,    1000,   12000,   4,   9],
        ['Saude',           '#16a34a',   40000,    3000,   35000,   1,   4],
        ['Lazer',           '#7c3aed',   30000,    2000,   20000,   2,   6],
        ['Educacao',        '#0891b2',   35000,    5000,   40000,   1,   2],
        ['Assinaturas',     '#db2777',   15000,    1990,    5990,   2,   5],
        ['Casa e utensilios', '#65a30d', 25000,    2500,   28000,   1,   4],
    ];

    /** @var array<string, array<int, string>> Descricoes por categoria. */
    private const DESCRIPTIONS = [
        'Alimentacao' => [
            'Supermercado', 'Feira livre', 'Padaria', 'Almoco no trabalho',
            'Delivery de jantar', 'Acougue', 'Cafeteria', 'Hortifruti',
        ],
        'Moradia' => ['Aluguel', 'Conta de luz', 'Conta de agua', 'Internet fibra', 'Condominio', 'Gas'],
        'Transporte' => [
            'Combustivel', 'Recarga do transporte publico', 'Aplicativo de corrida',
            'Estacionamento', 'Revisao do carro', 'Pedagio',
        ],
        'Saude' => ['Farmacia', 'Consulta medica', 'Plano de saude', 'Exames laboratoriais', 'Dentista'],
        'Lazer' => ['Cinema', 'Show', 'Bar com amigos', 'Livraria', 'Viagem de fim de semana', 'Jogo'],
        'Educacao' => ['Mensalidade do curso', 'Curso online', 'Material didatico', 'Certificacao'],
        'Assinaturas' => ['Streaming de video', 'Streaming de musica', 'Armazenamento em nuvem', 'Academia', 'Revista digital'],
        'Casa e utensilios' => ['Produtos de limpeza', 'Utensilio de cozinha', 'Reparo hidraulico', 'Decoracao', 'Ferramenta'],
    ];

    /** @var array<int, string> */
    private const METHODS = ['pix', 'debito', 'credito', 'dinheiro', 'boleto', 'transferencia'];

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly CategoryRepository $categories = new CategoryRepository(),
        private readonly ExpenseRepository $expenses = new ExpenseRepository(),
        private readonly BudgetRepository $budgets = new BudgetRepository(),
    ) {
    }

    /**
     * @param  int  $months quantos meses de historico gerar
     * @param  bool $reset  apaga os dados do usuario demo antes de gerar
     * @return array{user_id: int, categories: int, expenses: int, budgets: int, created: bool}
     */
    public function run(int $months = 12, bool $reset = false): array
    {
        mt_srand(self::SEED);

        return Database::transaction(function () use ($months, $reset): array {
            $existing = $this->users->findByEmail(self::EMAIL);
            $created  = $existing === null;

            $userId = $created
                ? $this->users->create('Usuario Demo', self::EMAIL, self::PASSWORD)
                : (int) $existing['id'];

            if ($reset && !$created) {
                Database::execute('DELETE FROM expenses WHERE user_id = ?', [$userId]);
                Database::execute('DELETE FROM budgets WHERE user_id = ?', [$userId]);
                Database::execute('DELETE FROM categories WHERE user_id = ?', [$userId]);
            }

            // Ja populado e sem --reset: nao duplica os lancamentos.
            if (!$reset && $this->expenses->count($userId) > 0) {
                return [
                    'user_id'    => $userId,
                    'categories' => $this->categories->count($userId),
                    'expenses'   => $this->expenses->count($userId),
                    'budgets'    => $this->budgets->count($userId, Period::current()),
                    'created'    => $created,
                ];
            }

            $categoryIds  = $this->seedCategories($userId);
            $expenseCount = $this->seedExpenses($userId, $categoryIds, $months);
            $budgetCount  = $this->seedBudgets($userId, $categoryIds, $months);

            return [
                'user_id'    => $userId,
                'categories' => count($categoryIds),
                'expenses'   => $expenseCount,
                'budgets'    => $budgetCount,
                'created'    => $created,
            ];
        });
    }

    /** @return array<string, int> nome => id */
    private function seedCategories(int $userId): array
    {
        $ids = [];

        foreach (self::CATALOG as [$name, $color]) {
            $existing = $this->categories->findByName($userId, $name);

            $ids[$name] = $existing !== null
                ? (int) $existing['id']
                : $this->categories->create($userId, $name, $color);
        }

        return $ids;
    }

    /** @param array<string, int> $categoryIds */
    private function seedExpenses(int $userId, array $categoryIds, int $months): int
    {
        $count   = 0;
        $periods = Period::current()->lastMonths(max(1, $months));
        $today   = (new DateTimeImmutable('now'))->format('Y-m-d');

        foreach ($periods as $index => $period) {
            // Inflaciona levemente os meses mais recentes para o grafico de
            // evolucao mostrar tendencia em vez de ruido puro.
            $trend = 1 + ($index / max(1, count($periods))) * 0.25;

            foreach (self::CATALOG as [$name, , , $min, $max, $minQty, $maxQty]) {
                $quantity = mt_rand($minQty, $maxQty);

                for ($i = 0; $i < $quantity; $i++) {
                    $day  = mt_rand(1, $period->daysInMonth());
                    $date = $period->key() . '-' . sprintf('%02d', $day);

                    // Nao inventa gastos no futuro.
                    if ($date > $today) {
                        continue;
                    }

                    $amount = (int) round(mt_rand($min, $max) * $trend);

                    $this->expenses->create($userId, [
                        'category_id'    => $categoryIds[$name],
                        'description'    => $this->description($name),
                        'amount_cents'   => max(100, $amount),
                        'spent_at'       => $date,
                        'payment_method' => self::METHODS[mt_rand(0, count(self::METHODS) - 1)],
                        'notes'          => mt_rand(1, 6) === 1 ? 'Lancamento gerado pelo seed de demonstracao.' : null,
                    ]);

                    $count++;
                }
            }
        }

        return $count;
    }

    /** @param array<string, int> $categoryIds */
    private function seedBudgets(int $userId, array $categoryIds, int $months): int
    {
        $count   = 0;
        $periods = Period::current()->lastMonths(max(1, min(3, $months)));

        foreach ($periods as $period) {
            foreach (self::CATALOG as [$name, , $budget]) {
                $this->budgets->save($userId, $categoryIds[$name], $period, $budget);
                $count++;
            }
        }

        return $count;
    }

    private function description(string $category): string
    {
        $options = self::DESCRIPTIONS[$category] ?? [$category];

        return $options[mt_rand(0, count($options) - 1)];
    }
}
