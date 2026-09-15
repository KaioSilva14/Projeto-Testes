<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Money;
use App\Core\Period;
use App\Core\Url;
use App\Core\View;

/**
 * Relatorios: comparativo mensal por categoria, tendencia e maiores gastos.
 *
 * @var Period                           $from
 * @var Period                           $to
 * @var array<string, mixed>             $comparison
 * @var array<int, array<string, mixed>> $topExpenses
 * @var array<int, array<string, mixed>> $paymentTotals
 * @var int                              $monthCount
 */

/** @var array<int, Period> $periods */
$periods      = $comparison['periods'];
$rows         = $comparison['rows'];
$periodTotals = $comparison['period_totals'];
$grandTotal   = (int) $comparison['grand_total_cents'];
$methodLabels = (array) Config::get('payment_methods', []);

$exportUrl = Url::to('/relatorios/exportar', ['de' => $from->key(), 'ate' => $to->key()]);

$actions = '<a class="btn btn--ghost" href="' . View::e($exportUrl) . '">Exportar CSV</a>';
?>

<?= View::partial('partials/page-header', [
    'heading'  => 'Relatorios',
    'subtitle' => sprintf(
        '%s a %s &middot; %d meses &middot; total de %s',
        $from->label(),
        $to->label(),
        $monthCount,
        Money::format($grandTotal),
    ),
    'actions'  => $actions,
]) ?>

<form class="card filters" method="get" action="<?= View::e(Url::to('/relatorios')) ?>">
    <div class="filters__grid filters__grid--compact">
        <div class="field">
            <label class="field__label" for="de">Competencia inicial</label>
            <input class="field__input" type="month" id="de" name="de" value="<?= View::e($from->key()) ?>">
        </div>

        <div class="field">
            <label class="field__label" for="ate">Competencia final</label>
            <input class="field__input" type="month" id="ate" name="ate" value="<?= View::e($to->key()) ?>">
        </div>

        <div class="field field--actions">
            <button class="btn btn--primary" type="submit">Gerar</button>
            <a class="btn btn--ghost" href="<?= View::e(Url::to('/relatorios')) ?>">Padrao</a>
        </div>
    </div>
</form>

<?php if ($grandTotal === 0) : ?>
    <div class="card empty-state">
        <h2 class="empty-state__title">Sem dados no intervalo</h2>
        <p class="empty-state__text">
            Nao ha despesas entre <?= View::e($from->label()) ?> e <?= View::e($to->label()) ?>.
        </p>
        <div class="empty-state__actions">
            <a class="btn btn--primary" href="<?= View::e(Url::to('/despesas/nova')) ?>">Lancar despesa</a>
            <a class="btn btn--ghost" href="<?= View::e(Url::to('/importar')) ?>">Importar CSV</a>
        </div>
    </div>
    <?php return; ?>
<?php endif; ?>

<section class="kpis kpis--3" aria-label="Resumo do periodo">
    <article class="kpi">
        <span class="kpi__label">Total do periodo</span>
        <strong class="kpi__value"><?= View::e(Money::format($grandTotal)) ?></strong>
        <span class="kpi__meta"><?= View::e((string) $monthCount) ?> meses analisados</span>
    </article>

    <article class="kpi">
        <span class="kpi__label">Media mensal</span>
        <strong class="kpi__value"><?= View::e(Money::format((int) $comparison['average_cents'])) ?></strong>
        <span class="kpi__meta">considera apenas meses com lancamentos</span>
    </article>

    <article class="kpi">
        <span class="kpi__label">Maior e menor mes</span>
        <strong class="kpi__value kpi__value--sm">
            <?php if ($comparison['best_period'] !== null) : ?>
                <?= View::e(Period::fromString((string) $comparison['best_period'])->shortLabel()) ?>
                <span class="muted">/</span>
                <?= View::e(Period::fromString((string) $comparison['worst_period'])->shortLabel()) ?>
            <?php else : ?>
                &#8212;
            <?php endif; ?>
        </strong>
        <span class="kpi__meta">
            <?php if ($comparison['best_period'] !== null) : ?>
                <?= View::e(Money::format((int) $periodTotals[$comparison['best_period']])) ?>
                e <?= View::e(Money::format((int) $periodTotals[$comparison['worst_period']])) ?>
            <?php endif; ?>
        </span>
    </article>
</section>

<section class="card chart-card">
    <header class="card__header">
        <h2 class="card__title">Tendencia das principais categorias</h2>
        <p class="card__hint">As 5 categorias de maior gasto no periodo, mes a mes</p>
    </header>
    <div class="chart chart--tall">
        <canvas data-chart="tendencia"
                data-url="<?= View::e(Url::to('/api/tendencia', [
                    'de'  => $from->key(),
                    'ate' => $to->key(),
                    'top' => 5,
                ])) ?>"
                role="img"
                aria-label="Grafico de linhas com a evolucao mensal das cinco maiores categorias"></canvas>
    </div>
</section>

<section class="card">
    <header class="card__header">
        <h2 class="card__title">Comparativo por categoria</h2>
        <p class="card__hint">Valores em <?= View::e((string) Config::get('app.currency', 'R$')) ?>, por competencia</p>
    </header>

    <div class="table-wrap table-wrap--scroll">
        <table class="table table--matrix">
            <caption class="sr-only">
                Gasto por categoria em cada competencia entre
                <?= View::e($from->label()) ?> e <?= View::e($to->label()) ?>
            </caption>
            <thead>
                <tr>
                    <th scope="col" class="sticky-col">Categoria</th>
                    <?php foreach ($periods as $p) : ?>
                        <th scope="col" class="num"><?= View::e($p->shortLabel()) ?></th>
                    <?php endforeach; ?>
                    <th scope="col" class="num">Total</th>
                    <th scope="col" class="num">Media</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <th scope="row" class="sticky-col">
                            <span class="dot" style="--dot: <?= View::e($row['color']) ?>"></span>
                            <?= View::e($row['name']) ?>
                        </th>
                        <?php foreach ($periods as $p) : ?>
                            <?php $value = (int) ($row['periods'][$p->key()] ?? 0); ?>
                            <td class="num <?= $value === 0 ? 'muted' : '' ?>">
                                <?= $value === 0 ? '&#8212;' : View::e(Money::formatPlain($value)) ?>
                            </td>
                        <?php endforeach; ?>
                        <td class="num strong"><?= View::e(Money::formatPlain((int) $row['total_cents'])) ?></td>
                        <td class="num">
                            <?= View::e(Money::formatPlain((int) round($row['total_cents'] / max(1, $monthCount)))) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th scope="row" class="sticky-col">Total</th>
                    <?php foreach ($periods as $p) : ?>
                        <td class="num strong"><?= View::e(Money::formatPlain((int) ($periodTotals[$p->key()] ?? 0))) ?></td>
                    <?php endforeach; ?>
                    <td class="num strong"><?= View::e(Money::formatPlain($grandTotal)) ?></td>
                    <td class="num strong"><?= View::e(Money::formatPlain((int) $comparison['average_cents'])) ?></td>
                </tr>
                <tr>
                    <th scope="row" class="sticky-col">Variacao</th>
                    <?php $previous = null; ?>
                    <?php foreach ($periods as $p) : ?>
                        <?php
                        $current = (int) ($periodTotals[$p->key()] ?? 0);
                        $delta   = $previous === null || $previous === 0
                            ? null
                            : round(($current - $previous) / $previous * 100, 1);
                        $previous = $current;
                        ?>
                        <td class="num <?= $delta === null ? 'muted' : ($delta > 0 ? 'is-up' : 'is-down') ?>">
                            <?php if ($delta === null) : ?>
                                &#8212;
                            <?php else : ?>
                                <?= $delta > 0 ? '+' : '' ?><?= View::e(number_format($delta, 1, ',', '.')) ?>%
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <p class="card__footnote">
        Percentuais comparam cada mes com o mes imediatamente anterior da tabela.
    </p>
</section>

<div class="grid grid--2">
    <section class="card">
        <header class="card__header">
            <h2 class="card__title">Maiores gastos do periodo</h2>
        </header>

        <ol class="ranked">
            <?php foreach ($topExpenses as $index => $expense) : ?>
                <li class="ranked__item">
                    <span class="ranked__position"><?= View::e((string) ($index + 1)) ?></span>
                    <span class="ranked__body">
                        <strong class="ranked__title"><?= View::e($expense['description']) ?></strong>
                        <span class="ranked__meta">
                            <span class="dot" style="--dot: <?= View::e($expense['category_color']) ?>"></span>
                            <?= View::e($expense['category_name']) ?>
                            &middot; <?= View::e(Period::formatDate((string) $expense['spent_at'])) ?>
                        </span>
                    </span>
                    <span class="ranked__value"><?= View::e(Money::format((int) $expense['amount_cents'])) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <section class="card">
        <header class="card__header">
            <h2 class="card__title">Por forma de pagamento</h2>
            <p class="card__hint">Acumulado do periodo</p>
        </header>

        <table class="table table--compact">
            <caption class="sr-only">Total por forma de pagamento no periodo</caption>
            <thead>
                <tr>
                    <th scope="col">Forma</th>
                    <th scope="col" class="num">Lancamentos</th>
                    <th scope="col" class="num">Total</th>
                    <th scope="col" class="num">Share</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($paymentTotals as $row) : ?>
                    <tr>
                        <td><?= View::e($methodLabels[$row['payment_method']] ?? $row['payment_method']) ?></td>
                        <td class="num"><?= View::e((string) $row['expense_count']) ?></td>
                        <td class="num strong"><?= View::e(Money::format((int) $row['total_cents'])) ?></td>
                        <td class="num">
                            <?= View::e(number_format($row['total_cents'] / max(1, $grandTotal) * 100, 1, ',', '.')) ?>%
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>
