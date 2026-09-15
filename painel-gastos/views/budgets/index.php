<?php

declare(strict_types=1);

use App\Core\Money;
use App\Core\Period;
use App\Core\Url;
use App\Core\View;

/**
 * Orcamentos do mes: um formulario unico com todas as categorias.
 *
 * @var Period                           $period
 * @var array<int, array<string, mixed>> $rows
 * @var int                              $budgetTotal
 * @var int                              $spentTotal
 * @var int                              $remainingTotal
 * @var float|null                        $usagePercent
 * @var bool                             $hasCategories
 * @var Period                           $previousPeriod
 * @var int                              $previousCount
 */
?>

<?= View::partial('partials/page-header', [
    'heading'    => 'Orcamentos',
    'subtitle'   => 'Defina um teto de gasto por categoria em ' . $period->label(),
    'period'     => $period,
    'periodPath' => '/orcamentos',
]) ?>

<?php if (!$hasCategories) : ?>
    <div class="card empty-state">
        <h2 class="empty-state__title">Crie categorias primeiro</h2>
        <p class="empty-state__text">O orcamento e definido por categoria.</p>
        <div class="empty-state__actions">
            <a class="btn btn--primary" href="<?= View::e(Url::to('/categorias')) ?>">Ir para categorias</a>
        </div>
    </div>
    <?php return; ?>
<?php endif; ?>

<section class="kpis kpis--3" aria-label="Resumo do orcamento">
    <article class="kpi">
        <span class="kpi__label">Orcado</span>
        <strong class="kpi__value"><?= View::e(Money::format($budgetTotal)) ?></strong>
        <span class="kpi__meta"><?= View::e($period->label()) ?></span>
    </article>

    <article class="kpi">
        <span class="kpi__label">Gasto</span>
        <strong class="kpi__value"><?= View::e(Money::format($spentTotal)) ?></strong>
        <span class="kpi__meta">
            <?php if ($usagePercent === null) : ?>
                nenhum orcamento definido
            <?php else : ?>
                <?= View::e(number_format($usagePercent, 1, ',', '.')) ?>% do orcado
            <?php endif; ?>
        </span>
    </article>

    <article class="kpi">
        <span class="kpi__label"><?= $remainingTotal >= 0 ? 'Disponivel' : 'Excedido' ?></span>
        <strong class="kpi__value <?= $remainingTotal >= 0 ? '' : 'is-up' ?>">
            <?= View::e(Money::format(abs($remainingTotal))) ?>
        </strong>
        <span class="kpi__meta">
            <?= $remainingTotal >= 0 ? 'ainda cabe no orcamento' : 'acima do planejado' ?>
        </span>
    </article>
</section>

<?php if ($budgetTotal > 0) : ?>
    <section class="card chart-card">
        <header class="card__header">
            <h2 class="card__title">Orcado x gasto</h2>
            <p class="card__hint">Comparacao por categoria em <?= View::e($period->label()) ?></p>
        </header>
        <div class="chart chart--tall">
            <canvas data-chart="orcamentos"
                    data-url="<?= View::e(Url::to('/api/orcamentos', ['mes' => $period->key()])) ?>"
                    role="img"
                    aria-label="Grafico de barras comparando valor orcado e valor gasto por categoria"></canvas>
        </div>
    </section>
<?php endif; ?>

<form class="card" method="post" action="<?= View::e(Url::to('/orcamentos')) ?>">
    <input type="hidden" name="_token" value="<?= View::e($csrfToken) ?>">
    <input type="hidden" name="period" value="<?= View::e($period->key()) ?>">

    <header class="card__header">
        <h2 class="card__title">Limites por categoria</h2>
        <p class="card__hint">Deixe em branco ou zere para remover o orcamento</p>
    </header>

    <div class="table-wrap">
        <table class="table">
            <caption class="sr-only">Orcamento e realizado por categoria</caption>
            <thead>
                <tr>
                    <th scope="col">Categoria</th>
                    <th scope="col" class="num">Orcamento (R$)</th>
                    <th scope="col" class="num">Gasto</th>
                    <th scope="col" class="num">Saldo</th>
                    <th scope="col">Uso</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <?php
                    $percent = $row['usage_percent'];
                    $status  = match (true) {
                        $percent === null => 'none',
                        $percent >= 100   => 'estourado',
                        $percent >= 80    => 'atencao',
                        default           => 'ok',
                    };
                    ?>
                    <tr>
                        <td>
                            <span class="dot" style="--dot: <?= View::e($row['color']) ?>"></span>
                            <?= View::e($row['name']) ?>
                        </td>
                        <td class="num">
                            <input class="field__input field__input--money"
                                   type="text" inputmode="decimal"
                                   name="orcamentos[<?= (int) $row['category_id'] ?>]"
                                   placeholder="0,00"
                                   aria-label="Orcamento de <?= View::e($row['name']) ?>"
                                   value="<?= View::e($row['budget_cents'] > 0
                                       ? Money::toInputValue((int) $row['budget_cents'])
                                       : '') ?>">
                        </td>
                        <td class="num"><?= View::e(Money::format((int) $row['spent_cents'])) ?></td>
                        <td class="num <?= $row['budget_cents'] > 0 && $row['remaining_cents'] < 0 ? 'is-up' : '' ?>">
                            <?= $row['budget_cents'] > 0
                                ? View::e(Money::format((int) $row['remaining_cents']))
                                : '<span class="muted">&#8212;</span>' ?>
                        </td>
                        <td>
                            <?php if ($percent === null) : ?>
                                <span class="muted">sem orcamento</span>
                            <?php else : ?>
                                <div class="progress" role="progressbar"
                                     aria-valuenow="<?= View::e((string) round($percent)) ?>"
                                     aria-valuemin="0" aria-valuemax="100"
                                     aria-label="Uso do orcamento de <?= View::e($row['name']) ?>">
                                    <div class="progress__bar is-<?= View::e($status) ?>"
                                         style="width: <?= View::e((string) min(100, $percent)) ?>%"></div>
                                </div>
                                <span class="progress__label is-<?= View::e($status) ?>">
                                    <?= View::e(number_format((float) $percent, 1, ',', '.')) ?>%
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th scope="row">Total</th>
                    <td class="num strong"><?= View::e(Money::formatPlain($budgetTotal)) ?></td>
                    <td class="num strong"><?= View::e(Money::format($spentTotal)) ?></td>
                    <td class="num strong"><?= View::e(Money::format($remainingTotal)) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="form__footer">
        <button class="btn btn--primary" type="submit">Salvar orcamentos</button>
    </div>
</form>

<?php if ($previousCount > 0) : ?>
    <div class="card">
        <div class="card__row">
            <div>
                <h2 class="card__title">Repetir o mes anterior</h2>
                <p class="card__hint">
                    Copia os <?= View::e((string) $previousCount) ?> orcamento(s) de
                    <?= View::e($previousPeriod->label()) ?> para <?= View::e($period->label()) ?>,
                    sobrescrevendo os valores atuais.
                </p>
            </div>
            <form method="post" action="<?= View::e(Url::to('/orcamentos/copiar')) ?>"
                  data-confirm="Copiar os orcamentos de <?= View::e($previousPeriod->label()) ?> e sobrescrever os atuais?">
                <input type="hidden" name="_token" value="<?= View::e($csrfToken) ?>">
                <input type="hidden" name="period" value="<?= View::e($period->key()) ?>">
                <button class="btn btn--ghost" type="submit">Copiar de <?= View::e($previousPeriod->shortLabel()) ?></button>
            </form>
        </div>
    </div>
<?php endif; ?>
