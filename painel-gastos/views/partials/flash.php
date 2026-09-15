<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Mensagens de sucesso/erro da requisicao anterior.
 *
 * @var array<int, array{type: string, message: string}> $flashes
 */

if (($flashes ?? []) === []) {
    return;
}
?>
<div class="flashes" role="status" aria-live="polite">
    <?php foreach ($flashes as $flash) : ?>
        <?php $type = $flash['type'] === 'success' ? 'success' : 'error'; ?>
        <div class="alert alert--<?= View::e($type) ?>">
            <span class="alert__icon" aria-hidden="true"><?= $type === 'success' ? '&#10003;' : '&#9888;' ?></span>
            <span><?= View::e($flash['message']) ?></span>
            <button class="alert__close" type="button" data-dismiss aria-label="Fechar aviso">&times;</button>
        </div>
    <?php endforeach; ?>
</div>
