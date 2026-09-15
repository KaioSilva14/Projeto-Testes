<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Money;
use App\Core\Period;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\CategoryRepository;
use App\Repositories\ExpenseRepository;
use App\Services\CsvExporter;
use App\Support\ExpenseFilter;
use DateTimeImmutable;

final class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseRepository $expenses = new ExpenseRepository(),
        private readonly CategoryRepository $categories = new CategoryRepository(),
    ) {
    }

    public function index(Request $request): void
    {
        $userId = $this->userId();
        $filter = ExpenseFilter::fromRequest($request);
        $result = $this->expenses->paginate($userId, $filter);

        $this->view('expenses/index', [
            'title'      => 'Despesas',
            'filter'     => $filter,
            'result'     => $result,
            'categories' => $this->categories->allForUser($userId),
            'methods'    => (array) Config::get('payment_methods', []),
            'period'     => Period::fromString($request->query('mes')),
        ]);
    }

    public function create(Request $request): void
    {
        $userId     = $this->userId();
        $categories = $this->categories->allForUser($userId);

        if ($categories === []) {
            $this->failure(
                'Cadastre pelo menos uma categoria antes de lancar despesas.',
                '/categorias',
            );
        }

        $this->view('expenses/form', [
            'title'      => 'Nova despesa',
            'expense'    => null,
            'categories' => $categories,
            'methods'    => (array) Config::get('payment_methods', []),
            'old'        => Session::pullOld(),
            'errors'     => Session::pullErrors(),
            'action'     => '/despesas',
        ]);
    }

    public function store(Request $request): void
    {
        $userId    = $this->userId();
        $validator = $this->validator($request, $userId);

        if ($validator->fails()) {
            $this->withErrors($validator, $request->all(), '/despesas/nova');
        }

        $payload = $this->payload($request);

        $this->expenses->create($userId, $payload);

        $this->success(
            'Despesa registrada: ' . Money::format($payload['amount_cents']) . '.',
            '/despesas',
            ['mes' => substr($payload['spent_at'], 0, 7)],
        );
    }

    public function edit(Request $request): void
    {
        $userId  = $this->userId();
        $expense = $this->expenses->find($userId, $request->routeParamInt('id'));

        if ($expense === null) {
            $this->failure('Despesa nao encontrada.', '/despesas');
        }

        $this->view('expenses/form', [
            'title'      => 'Editar despesa',
            'expense'    => $expense,
            'categories' => $this->categories->allForUser($userId),
            'methods'    => (array) Config::get('payment_methods', []),
            'old'        => Session::pullOld(),
            'errors'     => Session::pullErrors(),
            'action'     => '/despesas/' . (int) $expense['id'],
        ]);
    }

    public function update(Request $request): void
    {
        $userId = $this->userId();
        $id     = $request->routeParamInt('id');

        if ($this->expenses->find($userId, $id) === null) {
            $this->failure('Despesa nao encontrada.', '/despesas');
        }

        $validator = $this->validator($request, $userId);

        if ($validator->fails()) {
            $this->withErrors($validator, $request->all(), '/despesas/' . $id . '/editar');
        }

        $payload = $this->payload($request);

        $this->expenses->update($userId, $id, $payload);

        $this->success('Despesa atualizada.', '/despesas', ['mes' => substr($payload['spent_at'], 0, 7)]);
    }

    public function destroy(Request $request): void
    {
        $userId = $this->userId();
        $id     = $request->routeParamInt('id');

        if ($this->expenses->delete($userId, $id) === 0) {
            $this->failure('Despesa nao encontrada.', '/despesas');
        }

        $this->success('Despesa excluida.', '/despesas');
    }

    /** Exporta a listagem com os filtros atuais. */
    public function export(Request $request): void
    {
        $userId = $this->userId();
        $filter = ExpenseFilter::fromRequest($request);
        $rows   = $this->expenses->allForFilter($userId, $filter);

        if ($rows === []) {
            $this->failure('Nenhuma despesa no filtro atual para exportar.', '/despesas', $filter->toQuery());
        }

        Response::download(
            (new CsvExporter())->expenses($rows),
            CsvExporter::filename('despesas', $filter->from, $filter->to),
        );
    }

    // ------------------------------------------------------------------

    /**
     * Regras de validacao do formulario de despesa.
     *
     * A categoria precisa pertencer ao usuario: sem essa checagem seria
     * possivel anexar uma despesa a categoria de outra conta.
     */
    private function validator(Request $request, int $userId): Validator
    {
        $methods = array_keys((array) Config::get('payment_methods', []));

        $validator = Validator::make($request->all())
            ->required('description', 'Descricao')
            ->maxLength('description', 160, 'Descricao')
            ->required('amount', 'Valor')
            ->money('amount', 'Valor')
            ->required('spent_at', 'Data')
            ->date('spent_at', 'Data')
            ->required('category_id', 'Categoria')
            ->required('payment_method', 'Forma de pagamento')
            ->in('payment_method', $methods, 'Forma de pagamento')
            ->maxLength('notes', 500, 'Observacoes');

        $categoryId = $request->inputInt('category_id');

        if ($categoryId > 0 && $this->categories->find($userId, $categoryId) === null) {
            $validator->add('category_id', 'Categoria invalida.');
        }

        $date = $request->input('spent_at');

        if ($date !== '' && $date > (new DateTimeImmutable('now'))->format('Y-m-d')) {
            $validator->add('spent_at', 'A data nao pode estar no futuro.');
        }

        return $validator;
    }

    /**
     * Converte a requisicao ja validada no formato do repositorio.
     *
     * @return array{category_id: int, description: string, amount_cents: int, spent_at: string, payment_method: string, notes: string|null}
     */
    private function payload(Request $request): array
    {
        $notes = $request->input('notes');

        return [
            'category_id'    => $request->inputInt('category_id'),
            'description'    => $request->input('description'),
            'amount_cents'   => (int) Money::toCents($request->input('amount')),
            'spent_at'       => $request->input('spent_at'),
            'payment_method' => $request->input('payment_method'),
            'notes'          => $notes === '' ? null : $notes,
        ];
    }
}
