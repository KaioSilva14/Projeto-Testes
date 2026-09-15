<?php

declare(strict_types=1);

use App\Core\Money;
use App\Core\Period;
use App\Core\Url;
use App\Core\View;
use App\Support\ExpenseFilter;

/**
 * Listagem de despesas com filtros, ordenacao e paginacao.
 *
 * @var ExpenseFilter                    $filter
 * @var array<string, mixed>             $result
 * @var array<int, array<string, mixed>> $categories
 * @var array<string, string>            $methods
 * @var Period                           $period
 */

$query = $filter->toQuery();

/** Monta o link de ordenacao de uma coluna preservando os filtros. */
$sortLink = static function (string $column) use ($filter, $query): string {
    return Url::to('/despesas', $query + [
        'ordenar' => $column,
        'direcao' => $filter->toggleDirection($column),
    ]);
};

$sortMark = static function (string $column) use ($filter): string {
    if ($filter->sort !== $column) {
        return '';
    }

    return $filter->direction === 'asc' ? ' &#9650;' : ' &#9660;';
};

$actions = '<a class="btn btn--ghost" href="'
    . View::e(Url::to('/despesas/exportar', $query))
    . '">Exportar CSV</a>'
    . '<a class="btn btn--primary" href="' . View::e(Url::to('/despesas/nova')) . '">+ Nova despesa</a>';
?>

<?= View::partial('partials/page-header', [
    'heading'  => 'Despesas',
    'subtitle' => sprintf(
        '%d lancamento(s) de %s a %s, somando %s',
        (int) $result['total'],
        Period::formatDate($filter->from),
        Period::formatDate($filter->to),
        Money::format((int) $result['total_cents']),
    ),
    'actions'  => $actions,
]) ?>

<form class="card filters" method="get" action="<?= View::e(Url::to('/despesas')) ?>">
    <div class="filters__grid">
        <div class="field">
            <label class="field__label" for="de">De</label>
            <input class="field__input" type="date" id="de" name="de" value="<?= View::e($filter->from) ?>">
        </div>

        <div class="field">
            <label class="field__label" for="ate">Ate</label>
            <input class="field__input" type="date" id="ate" name="ate" value="<?= View::e($filter->to) ?>">
        </div>

        <div class="field">
            <label class="field__label" for="categoria">Categoria</label>
            <select class="field__input" id="categoria" name="categoria">
                <option value="">Todas</option>
                <?php foreach ($categories as $category) : ?>
                    <option value="<?= View::e((string) $category['id']) ?>"
                        <?= $filter->categoryId === (int) $category['id'] ? 'selected' : '' ?>>
                        <?= View::e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label class="field__label" for="forma">Forma</label>
            <select class="field__input" id="forma" name="forma">
                <option value="">Todas</option>
                <?php foreach ($methods as $value => $label) : ?>
                    <option value="<?= View::e($value) ?>"
                        <?= $filter->paymentMethod === $value ? 'selected' : '' ?>>
                        <?= View::e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field field--wide">
            <label class="field__label" for="q">Buscar</label>
            <input class="field__input" type="search" id="q" name="q"
                   value="<?= View::e($filter->search) ?>"
                   placeholder="descricao ou observacao">
        </div>

        <div class="field field--actions">
            <button class="btn btn--primary" type="submit">Filtrar</button>
            <?php if ($filter->isFiltered()) : ?>
                <a class="btn btn--ghost" href="<?= View::e(Url::to('/despesas', [
                    'de'  => $filter->from,
                    'ate' => $filter->to,
                ])) ?>">Limpar</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($filter->sort !== 'data' || $filter->direction !== 'desc') : ?>
        <input type="hidden" name="ordenar" value="<?= View::e($filter->sort) ?>">
        <input type="hidden" name="direcao" value="<?= View::e($filter->direction) ?>">
    <?php endif; ?>
</form>

<div class="card">
    <?php if ($result['items'] === []) : ?>
        <div class="empty-state">
            <h2 class="empty-state__title">Nenhuma despesa encontrada</h2>
            <p class="empty-state__text">
                <?= $filter->isFiltered()
                    ? 'Nenhum lancamento corresponde aos filtros aplicados.'
                    : 'Nao ha lancamentos no periodo selecionado.' ?>
            </p>
            <div class="empty-state__actions">
                <a class="btn btn--primary" href="<?= View::e(Url::to('/despesas/nova')) ?>">Lancar despesa</a>
                <a class="btn btn--ghost" href="<?= View::e(Url::to('/importar')) ?>">Importar CSV</a>
            </div>
        </div>
    <?php else : ?>
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">Despesas do periodo filtrado</caption>
                <thead>
                    <tr>
                        <th scope="col"><a class="th-sort" href="<?= View::e($sortLink('data')) ?>">Data<?= $sortMark('data') ?></a></th>
                        <th scope="col"><a class="th-sort" href="<?= View::e($sortLink('descricao')) ?>">Descricao<?= $sortMark('descricao') ?></a></th>
                        <th scope="col"><a class="th-sort" href="<?= View::e($sortLink('categoria')) ?>">Categoria<?= $sortMark('categoria') ?></a></th>
                        <th scope="col"><a class="th-sort" href="<?= View::e($sortLink('forma')) ?>">Forma<?= $sortMark('forma') ?></a></th>
                        <th scope="col" class="num"><a class="th-sort" href="<?= View::e($sortLink('valor')) ?>">Valor<?= $sortMark('valor') ?></a></th>
                        <th scope="col" class="num">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['items'] as $expense) : ?>
                        <tr>
                            <td class="nowrap"><?= View::e(Period::formatDate((string) $expense['spent_at'])) ?></td>
                            <td>
                                <strong><?= View::e($expense['description']) ?></strong>
                                <?php if (($expense['notes'] ?? '') !== '') : ?>
                                    <span class="table__note"><?= View::e($expense['notes']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="nowrap">
                                <span class="dot" style="--dot: <?= View::e($expense['category_color']) ?>"></span>
                                <?= View::e($expense['category_name']) ?>
                            </td>
                            <td class="nowrap">
                                <?= View::e($methods[$expense['payment_method']] ?? $expense['payment_method']) ?>
                            </td>
                            <td class="num strong"><?= View::e(Money::format((int) $expense['amount_cents'])) ?></td>
                            <td class="num nowrap">
                                <a class="btn btn--sm btn--ghost"
                                   href="<?= View::e(Url::to('/despesas/' . (int) $expense['id'] . '/editar')) ?>">Editar</a>

                                <form class="inline" method="post"
                                      action="<?= View::e(Url::to('/despesas/' . (int) $expense['id'] . '/excluir')) ?>"
                                      data-confirm="Excluir a despesa &quot;<?= View::e($expense['description']) ?>&quot;?">
                                    <input type="hidden" name="_token" value="<?= View::e($csrfToken) ?>">
                                    <button class="btn btn--sm btn--danger" type="submit">Excluir</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row" colspan="4">Total da pagina</th>
                        <td class="num strong">
                            <?= View::e(Money::format(
                                (int) array_sum(array_column($result['items'], 'amount_cents')),
                            )) ?>
                        </td>
                        <td></td>
                    </tr>
                    <tr>
                        <th scope="row" colspan="4">Total do filtro</th>
                        <td class="num strong"><?= View::e(Money::format((int) $result['total_cents'])) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?= View::partial('partials/pagination', [
            'page'     => (int) $result['page'],
            'lastPage' => (int) $result['last_page'],
            'total'    => (int) $result['total'],
            'path'     => '/despesas',
            'query'    => $query,
        ]) ?>
    <?php endif; ?>
</div>
