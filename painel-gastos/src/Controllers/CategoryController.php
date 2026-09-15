<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\CategoryRepository;

final class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryRepository $categories = new CategoryRepository(),
    ) {
    }

    public function index(Request $request): void
    {
        $userId = $this->userId();
        $period = $this->period($request);

        $this->view('categories/index', [
            'title'      => 'Categorias',
            'period'     => $period,
            'categories' => $this->categories->withTotals($userId, $period->firstDay(), $period->lastDay()),
            'suggested'  => $this->categories->nextColor($userId),
            'old'        => Session::pullOld(),
            'errors'     => Session::pullErrors(),
        ]);
    }

    public function store(Request $request): void
    {
        $userId    = $this->userId();
        $validator = $this->validator($request, $userId);

        if ($validator->fails()) {
            $this->withErrors($validator, $request->all(), '/categorias');
        }

        $this->categories->create(
            $userId,
            $request->input('name'),
            $this->color($request, $userId),
        );

        $this->success('Categoria "' . $request->input('name') . '" criada.', '/categorias');
    }

    public function update(Request $request): void
    {
        $userId = $this->userId();
        $id     = $request->routeParamInt('id');

        if ($this->categories->find($userId, $id) === null) {
            $this->failure('Categoria nao encontrada.', '/categorias');
        }

        $validator = $this->validator($request, $userId, $id);

        if ($validator->fails()) {
            $this->withErrors($validator, $request->all(), '/categorias');
        }

        $this->categories->update(
            $userId,
            $id,
            $request->input('name'),
            $this->color($request, $userId),
        );

        $this->success('Categoria atualizada.', '/categorias');
    }

    /**
     * Exclui a categoria.
     *
     * Categorias com despesas sao mantidas: apagar em cascata destruiria
     * historico, e o banco tem ON DELETE RESTRICT. A mensagem explica o que
     * fazer em vez de apenas recusar.
     */
    public function destroy(Request $request): void
    {
        $userId   = $this->userId();
        $id       = $request->routeParamInt('id');
        $category = $this->categories->find($userId, $id);

        if ($category === null) {
            $this->failure('Categoria nao encontrada.', '/categorias');
        }

        $count = $this->categories->expenseCount($userId, $id);

        if ($count > 0) {
            $this->failure(
                sprintf(
                    'A categoria "%s" tem %d despesa(s) vinculada(s). Reclassifique ou exclua essas despesas primeiro.',
                    (string) $category['name'],
                    $count,
                ),
                '/categorias',
            );
        }

        $this->categories->delete($userId, $id);

        $this->success('Categoria "' . (string) $category['name'] . '" excluida.', '/categorias');
    }

    private function validator(Request $request, int $userId, int $ignoreId = 0): Validator
    {
        $validator = Validator::make($request->all())
            ->required('name', 'Nome')
            ->minLength('name', 2, 'Nome')
            ->maxLength('name', 60, 'Nome')
            ->hexColor('color', 'Cor');

        if ($this->categories->nameExists($userId, $request->input('name'), $ignoreId)) {
            $validator->add('name', 'Ja existe uma categoria com esse nome.');
        }

        return $validator;
    }

    /** Cor informada ou a proxima disponivel na paleta. */
    private function color(Request $request, int $userId): string
    {
        $color = $request->input('color');

        return $color === '' ? $this->categories->nextColor($userId) : $color;
    }
}
