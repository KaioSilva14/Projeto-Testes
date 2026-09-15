<?php

declare(strict_types=1);

use App\Core\Url;
use App\Core\View;

/**
 * Paginacao que preserva os filtros ativos.
 *
 * @var int                    $page
 * @var int                    $lastPage
 * @var int                    $total
 * @var string                 $path
 * @var array<string, mixed>   $query
 */

if (($lastPage ?? 1) <= 1) {
    return;
}

// Janela de no maximo 7 paginas em volta da atual.
$start = max(1, $page - 3);
$end   = min($lastPage, $start + 6);
$start = max(1, $end - 6);
?>
<nav class="pagination" aria-label="Paginacao">
    <span class="pagination__info">
        Pagina <?= View::e((string) $page) ?> de <?= View::e((string) $lastPage) ?>
        (<?= View::e((string) $total) ?> registros)
    </span>

    <div class="pagination__pages">
        <?php if ($page > 1) : ?>
            <a class="pagination__link" href="<?= View::e(Url::to($path, $query + ['pagina' => $page - 1])) ?>"
               rel="prev">Anterior</a>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++) : ?>
            <?php if ($i === $page) : ?>
                <span class="pagination__link is-active" aria-current="page"><?= View::e((string) $i) ?></span>
            <?php else : ?>
                <a class="pagination__link" href="<?= View::e(Url::to($path, $query + ['pagina' => $i])) ?>">
                    <?= View::e((string) $i) ?>
                </a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $lastPage) : ?>
            <a class="pagination__link" href="<?= View::e(Url::to($path, $query + ['pagina' => $page + 1])) ?>"
               rel="next">Proxima</a>
        <?php endif; ?>
    </div>
</nav>
