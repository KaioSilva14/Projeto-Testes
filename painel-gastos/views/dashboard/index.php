<?php

declare(strict_types=1);

use App\Core\Money;
use App\Core\Period;
use App\Core\Url;
use App\Core\View;

/**
 * Painel principal: indicadores do mes, graficos e listas de apoio.
 *
 * @var Period                          $period
 * @var array<string, mixed>            $kpis
 * @var array<int, array<string, mixed>> $categoryTotals
 * @var array<int, array<string, mixed>> $budgets
 * @var array<int, array<string, mixed>> $recent
 * @var array<int, array<string, mixed>> $topExpenses
 * @var bool                            $hasCategories
 * @var bool                            $hasExpenses
 */

$variation = $kpis['variation_percent'];
$mes       = $period->key();

/** Classe e sinal do indicador de variacao (gastar menos e bom). */
$variationClass = match (true) {
    $variation === null => 'is-neutral',
    $variation > 0      => 'is-up',
    $variation < 0      => 'is-down',
    default             => 'is-neutral',
};

$actions = '<a class="btn btn--primary" href="' . View::e(Url::to('/despesas/nova'))
    . '">+ Nova despesa</a>';
?>

<?= View::partial('partials/page-header', [
    'heading'    => 'Painel de ' . $period->label(),
    'subtitle'   => $period->isCurrentMonth()
        ? sprintf('Dia %d de %d do mes', $kpis['elapsed_days'], $kpis['days_in_month'])
        : 'Mes fechado',
    'period'     => $period,
    'periodPath' => '/',
    'actions'    => $actions,
]) ?>

<?php if (!$hasExpenses) : ?>
    <div class="card empty-state">
        <h2 class="empty-state__title">Seu painel esta vazio</h2>
        <p class="empty-state__text">
            Cadastre despesas manualmente ou importe um extrato em CSV para ver
            os graficos e relatorios se formarem.
        </p>
        <div class="empty-state__actions">
            <?php if ($hasCategories) : ?>
                <a class="btn btn--primary" href="<?= View::e(Url::to('/despesas/nova')) ?>">Lancar despesa</a>
            <?php else : ?>
                <a class="btn btn--primary" href="<?= View::e(Url::to('/categorias')) ?>">Criar categorias</a>
            <?php endif; ?>
            <a class="btn btn--ghost" href="<?= View::e(Url::to('/importar')) ?>">Importar CSV</a>
        </div>
    </div>
<?php endif; ?>

<section class="kpis" aria-label="Indicadores do mes">
    <article class="kpi">
        <span class="kpi__label">Total gasto</span>
        <strong class="kpi__value"><?= View::e(Money::format($kpis['total_cents'])) ?></strong>
        <span class="kpi__meta <?= $variationClass ?>">
            <?php if ($variation === null) : ?>
                sem base de comparacao
            <?php else : ?>
                <?= $variation > 0 ? '&#9650;' : ($variation < 0 ? '&#9660;' : '&#8212;') ?>
                <?= View::e(number_format(abs($variation), 1, ',', '.')) ?>%
                vs. <?= View::e($period->previous()->shortLabel()) ?>
            <?php endif; ?>
        </span>
    </article>

    <article class="kpi">
        <span class="kpi__label">Media diaria</span>
        <strong class="kpi__value"><?= View::e(Money::format($kpis['daily_average_cents'])) ?></strong>
        <span class="kpi__meta">
            <?php if ($period->isCurrentMonth()) : ?>
                projecao do mes: <?= View::e(Money::format($kpis['projection_cents'])) ?>
            <?php else : ?>
                <?= View::e((string) $kpis['days_in_month']) ?> dias no mes
            <?php endif; ?>
        </span>
    </article>

    <article class="kpi">
        <span class="kpi__label">Lancamentos</span>
        <strong class="kpi__value"><?= View::e((string) $kpis['expense_count']) ?></strong>
        <span class="kpi__meta">
            ticket medio <?= View::e(Money::format($kpis['average_cents'])) ?>
        </span>
    </article>

    <article class="kpi">
        <span class="kpi__label">Orcamento do mes</span>
        <?php if ($kpis['budget_total_cents'] > 0) : ?>
            <strong class="kpi__value"><?= View::e(Money::format($kpis['budget_total_cents'])) ?></strong>
            <?php $usage = (float) $kpis['budget_usage_percent']; ?>
            <span class="kpi__meta <?= $usage >= 100 ? 'is-up' : 'is-neutral' ?>">
                <?= View::e(number_format($usage, 1, ',', '.')) ?>% usado &middot;
                <?= $kpis['budget_remaining_cents'] >= 0 ? 'restam' : 'excedeu' ?>
                <?= View::e(Money::format(abs((int) $kpis['budget_remaining_cents']))) ?>
            </span>
        <?php else : ?>
            <strong class="kpi__value kpi__value--muted">&#8212;</strong>
            <span class="kpi__meta">
                <a href="<?= View::e(Url::to('/orcamentos', ['mes' => $mes])) ?>">definir orcamentos</a>
            </span>
        <?php endif; ?>
    </article>
</section>

<section class="grid grid--2">
    <article class="card chart-card">
        <header class="card__header">
            <h2 class="card__title">Evolucao diaria</h2>
            <p class="card__hint">Gasto por dia e total acumulado em <?= View::e($period->label()) ?></p>
        </header>
        <div class="chart chart--tall">
            <canvas data-chart="diario"
                    data-url="<?= View::e(Url::to('/api/diario', ['mes' => $mes])) ?>"
                    role="img"
                    aria-label="Grafico de linha com o gasto diario e o acumulado do mes"></canvas>
        </div>
    </article>

    <article class="card chart-card">
        <header class="card__header">
            <h2 class="card__title">Composicao por categoria</h2>
            <p class="card__hint">Participacao de cada categoria no total do mes</p>
        </header>
        <div class="chart chart--tall">
            <canvas data-chart="categorias"
                    data-url="<?= View::e(Url::to('/api/categorias', ['mes' => $mes])) ?>"
                    role="img"
                    aria-label="Grafico de rosca com a divisao dos gastos por categoria"></canvas>
        </div>
    </article>
</section>

<section class="grid grid--2">
    <article class="card chart-card">
        <header class="card__header">
            <h2 class="card__title">Ultimos 12 meses</h2>
            <p class="card__hint">Total mensal e media do periodo</p>
        </header>
        <div class="chart chart--tall">
            <canvas data-chart="mensal"
                    data-url="<?= View::e(Url::to('/api/mensal', ['mes' => $mes, 'meses' => 12])) ?>"
                    role="img"
                    aria-label="Grafico de barras com o total gasto em cada um dos ultimos doze meses"></canvas>
        </div>
    </article>

    <article class="card chart-card">
        <header class="card__header">
            <h2 class="card__title">Formas de pagamento</h2>
            <p class="card__hint">Como os gastos do mes foram pagos</p>
        </header>
        <div class="chart chart--tall">
            <canvas data-chart="formas"
                    data-url="<?= View::e(Url::to('/api/formas-pagamento', ['mes' => $mes])) ?>"
                    role="img"
                    aria-label="Grafico de barras horizontais com o total por forma de pagamento"></canvas>
        </div>
    </article>
</section>

<section class="grid grid--2">
    <article class="card">
        <header class="card__header">
            <h2 class="card__title">Orcamento por categoria</h2>
            <a class="card__action" href="<?= View::e(Url::to('/orcamentos', ['mes' => $mes])) ?>">ajustar</a>
        </header>

        <?php if ($budgets === []) : ?>
            <p class="muted">
                Nenhum orcamento definido para <?= View::e($period->label()) ?>.
                <a href="<?= View::e(Url::to('/orcamentos', ['mes' => $mes])) ?>">Definir agora</a>.
            </p>
        <?php else : ?>
            <ul class="budget-list">
                <?php foreach ($budgets as $budget) : ?>
                    <?php $percent = (float) ($budget['usage_percent'] ?? 0); ?>
                    <li class="budget-list__item">
                        <div class="budget-list__head">
                            <span class="dot" style="--dot: <?= View::e($budget['color']) ?>"></span>
                            <span class="budget-list__name"><?= View::e($budget['name']) ?></span>
                            <span class="budget-list__values">
                                <?= View::e(Money::format((int) $budget['spent_cents'])) ?>
                                <span class="muted">/ <?= View::e(Money::format((int) $budget['budget_cents'])) ?></span>
                            </span>
                        </div>
                        <div class="progress" role="progressbar"
                             aria-valuenow="<?= View::e((string) round($percent)) ?>"
                             aria-valuemin="0" aria-valuemax="100"
                             aria-label="Uso do orcamento de <?= View::e($budget['name']) ?>">
                            <div class="progress__bar is-<?= View::e((string) $budget['status']) ?>"
                                 style="width: <?= View::e((string) min(100, $percent)) ?>%"></div>
                        </div>
                        <span class="budget-list__meta is-<?= View::e((string) $budget['status']) ?>">
                            <?= View::e(number_format($percent, 1, ',', '.')) ?>% usado
                            <?php if ($budget['remaining_cents'] < 0) : ?>
                                &middot; excedeu <?= View::e(Money::format(abs((int) $budget['remaining_cents']))) ?>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>

    <article class="card">
        <header class="card__header">
            <h2 class="card__title">Maiores gastos do mes</h2>
            <a class="card__action" href="<?= View::e(Url::to('/despesas', ['mes' => $mes, 'ordenar' => 'valor'])) ?>">ver todos</a>
        </header>

        <?php if ($topExpenses === []) : ?>
            <p class="muted">Nenhuma despesa em <?= View::e($period->label()) ?>.</p>
        <?php else : ?>
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
        <?php endif; ?>
    </article>
</section>

<section class="grid grid--2">
    <article class="card">
        <header class="card__header">
            <h2 class="card__title">Totais por categoria</h2>
            <p class="card__hint"><?= View::e($period->label()) ?></p>
        </header>

        <?php if ($categoryTotals === []) : ?>
            <p class="muted">Sem lancamentos neste mes.</p>
        <?php else : ?>
            <?php $maxTotal = max(array_column($categoryTotals, 'total_cents')); ?>
            <table class="table table--compact">
                <caption class="sr-only">Total gasto por categoria em <?= View::e($period->label()) ?></caption>
                <thead>
                    <tr>
                        <th scope="col">Categoria</th>
                        <th scope="col" class="num">Lancamentos</th>
                        <th scope="col" class="num">Total</th>
                        <th scope="col" class="num">Share</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categoryTotals as $row) : ?>
                        <?php $share = $kpis['total_cents'] > 0
                            ? $row['total_cents'] / $kpis['total_cents'] * 100
                            : 0; ?>
                        <tr>
                            <td>
                                <a class="link-reset" href="<?= View::e(Url::to('/despesas', [
                                    'mes'       => $mes,
                                    'categoria' => $row['id'],
                                ])) ?>">
                                    <span class="dot" style="--dot: <?= View::e($row['color']) ?>"></span>
                                    <?= View::e($row['name']) ?>
                                </a>
                            </td>
                            <td class="num"><?= View::e((string) $row['expense_count']) ?></td>
                            <td class="num"><?= View::e(Money::format((int) $row['total_cents'])) ?></td>
                            <td class="num">
                                <div class="bar-inline" aria-hidden="true">
                                    <span class="bar-inline__fill"
                                          style="width: <?= View::e((string) round($row['total_cents'] / max(1, $maxTotal) * 100)) ?>%;
                                                 background: <?= View::e($row['color']) ?>"></span>
                                </div>
                                <?= View::e(number_format($share, 1, ',', '.')) ?>%
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </article>

    <article class="card">
        <header class="card__header">
            <h2 class="card__title">Lancamentos recentes</h2>
            <a class="card__action" href="<?= View::e(Url::to('/despesas')) ?>">ver despesas</a>
        </header>

        <?php if ($recent === []) : ?>
            <p class="muted">Nada lancado ainda.</p>
        <?php else : ?>
            <ul class="feed">
                <?php foreach ($recent as $expense) : ?>
                    <li class="feed__item">
                        <span class="dot dot--lg" style="--dot: <?= View::e($expense['category_color']) ?>"></span>
                        <span class="feed__body">
                            <strong><?= View::e($expense['description']) ?></strong>
                            <span class="feed__meta">
                                <?= View::e($expense['category_name']) ?>
                                &middot; <?= View::e(Period::formatDate((string) $expense['spent_at'])) ?>
                            </span>
                        </span>
                        <span class="feed__value"><?= View::e(Money::format((int) $expense['amount_cents'])) ?></span>
                        <a class="feed__edit"
                           href="<?= View::e(Url::to('/despesas/' . (int) $expense['id'] . '/editar')) ?>"
                           aria-label="Editar <?= View::e($expense['description']) ?>">editar</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>
</section>
