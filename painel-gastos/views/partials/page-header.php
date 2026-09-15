<?php

declare(strict_types=1);

use App\Core\Period;
use App\Core\Url;
use App\Core\View;

/**
 * Cabecalho de pagina com titulo, subtitulo, seletor de mes e acoes.
 *
 * @var string      $heading
 * @var string      $subtitle
 * @var Period|null $period      exibe o seletor de mes quando informado
 * @var string      $periodPath  rota que recebe o parametro ?mes=
 * @var string      $actions     HTML adicional a direita (botoes)
 */

$period     = $period ?? null;
$periodPath = $periodPath ?? '/';
?>
<header class="page-header">
    <div class="page-header__text">
        <h1 class="page-header__title"><?= View::e($heading) ?></h1>
        <?php if (($subtitle ?? '') !== '') : ?>
            <p class="page-header__subtitle"><?= View::e($subtitle) ?></p>
        <?php endif; ?>
    </div>

    <div class="page-header__tools">
        <?php if ($period instanceof Period) : ?>
            <div class="month-nav" role="group" aria-label="Mes de referencia">
                <a class="month-nav__arrow"
                   href="<?= View::e(Url::to($periodPath, ['mes' => $period->previous()->key()])) ?>"
                   aria-label="Mes anterior">&lsaquo;</a>

                <form class="month-nav__form" method="get" action="<?= View::e(Url::to($periodPath)) ?>">
                    <label class="sr-only" for="mes">Mes de referencia</label>
                    <input class="month-nav__input" type="month" id="mes" name="mes"
                           value="<?= View::e($period->key()) ?>" data-auto-submit>
                    <noscript><button class="btn btn--sm" type="submit">Ir</button></noscript>
                </form>

                <?php if ($period->isCurrentMonth()) : ?>
                    <span class="month-nav__arrow is-disabled" aria-hidden="true">&rsaquo;</span>
                <?php else : ?>
                    <a class="month-nav__arrow"
                       href="<?= View::e(Url::to($periodPath, ['mes' => $period->next()->key()])) ?>"
                       aria-label="Mes seguinte">&rsaquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?= $actions ?? '' ?>
    </div>
</header>
