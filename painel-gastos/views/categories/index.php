<?php

declare(strict_types=1);

use App\Core\Money;
use App\Core\Period;
use App\Core\Url;
use App\Core\View;

/**
 * Categorias: criacao, edicao inline e total gasto no mes de referencia.
 *
 * @var Period                           $period
 * @var array<int, array<string, mixed>> $categories
 * @var string                           $suggested
 * @var array<string, mixed>             $old
 * @var array<string, string>            $errors
 */

$totalMonth = array_sum(array_column($categories, 'total_cents'));
?>

<?= View::partial('partials/page-header', [
    'heading'    => 'Categorias',
    'subtitle'   => sprintf(
        '%d categoria(s) &middot; %s gastos em %s',
        count($categories),
        Money::format((int) $totalMonth),
        $period->label(),
    ),
    'period'     => $period,
    'periodPath' => '/categorias',
]) ?>

<div class="grid grid--sidebar">
    <section class="card">
        <header class="card__header">
            <h2 class="card__title">Nova categoria</h2>
            <p class="card__hint">A cor identifica a categoria nos graficos</p>
        </header>

        <form class="form" method="post" action="<?= View::e(Url::to('/categorias')) ?>" novalidate>
            <input type="hidden" name="_token" value="<?= View::e($csrfToken) ?>">

            <div class="field">
                <label class="field__label" for="name">Nome *</label>
                <input class="field__input<?= isset($errors['name']) ? ' has-error' : '' ?>"
                       type="text" id="name" name="name" maxlength="60" required
                       placeholder="Ex.: Alimentacao"
                       value="<?= View::e((string) ($old['name'] ?? '')) ?>">
                <?php if (isset($errors['name'])) : ?>
                    <span class="field__error"><?= View::e($errors['name']) ?></span>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field__label" for="color">Cor</label>
                <div class="color-row">
                    <input class="field__color" type="color" id="color" name="color"
                           value="<?= View::e((string) ($old['color'] ?? $suggested)) ?>">
                    <span class="field__hint">Sugerida automaticamente da paleta</span>
                </div>
                <?php if (isset($errors['color'])) : ?>
                    <span class="field__error"><?= View::e($errors['color']) ?></span>
                <?php endif; ?>
            </div>

            <button class="btn btn--primary btn--block" type="submit">Criar categoria</button>
        </form>
    </section>

    <section class="card">
        <header class="card__header">
            <h2 class="card__title">Categorias cadastradas</h2>
            <p class="card__hint">Totais referentes a <?= View::e($period->label()) ?></p>
        </header>

        <?php if ($categories === []) : ?>
            <div class="empty-state">
                <h3 class="empty-state__title">Nenhuma categoria ainda</h3>
                <p class="empty-state__text">
                    Crie a primeira categoria no formulario ao lado. Toda despesa
                    precisa estar em uma categoria.
                </p>
            </div>
        <?php else : ?>
            <div class="table-wrap">
                <table class="table">
                    <caption class="sr-only">Categorias e totais do mes</caption>
                    <thead>
                        <tr>
                            <th scope="col">Cor</th>
                            <th scope="col">Nome</th>
                            <th scope="col" class="num">Lancamentos</th>
                            <th scope="col" class="num">Total no mes</th>
                            <th scope="col" class="num">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category) : ?>
                            <?php $id = (int) $category['id']; ?>
                            <tr>
                                <td>
                                    <form class="inline" method="post"
                                          action="<?= View::e(Url::to('/categorias/' . $id)) ?>"
                                          id="cat-<?= $id ?>">
                                        <input type="hidden" name="_token" value="<?= View::e($csrfToken) ?>">
                                        <input class="field__color field__color--sm" type="color" name="color"
                                               value="<?= View::e($category['color']) ?>"
                                               aria-label="Cor de <?= View::e($category['name']) ?>">
                                    </form>
                                </td>
                                <td>
                                    <input class="field__input field__input--inline" type="text" name="name"
                                           form="cat-<?= $id ?>" maxlength="60"
                                           value="<?= View::e($category['name']) ?>"
                                           aria-label="Nome de <?= View::e($category['name']) ?>">
                                </td>
                                <td class="num"><?= View::e((string) $category['expense_count']) ?></td>
                                <td class="num strong"><?= View::e(Money::format((int) $category['total_cents'])) ?></td>
                                <td class="num nowrap">
                                    <button class="btn btn--sm btn--primary" type="submit" form="cat-<?= $id ?>">
                                        Salvar
                                    </button>

                                    <?php if ((int) $category['expense_count'] > 0) : ?>
                                        <a class="btn btn--sm btn--ghost"
                                           href="<?= View::e(Url::to('/despesas', [
                                               'mes'       => $period->key(),
                                               'categoria' => $id,
                                           ])) ?>">Ver</a>
                                    <?php else : ?>
                                        <form class="inline" method="post"
                                              action="<?= View::e(Url::to('/categorias/' . $id . '/excluir')) ?>"
                                              data-confirm="Excluir a categoria &quot;<?= View::e($category['name']) ?>&quot;?">
                                            <input type="hidden" name="_token" value="<?= View::e($csrfToken) ?>">
                                            <button class="btn btn--sm btn--danger" type="submit">Excluir</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p class="card__footnote">
                Categorias com lancamentos nao podem ser excluidas: reclassifique
                ou remova as despesas primeiro para preservar o historico.
            </p>
        <?php endif; ?>
    </section>
</div>
