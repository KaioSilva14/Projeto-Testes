<?php

declare(strict_types=1);

use App\Core\Url;
use App\Core\View;

/**
 * Pagina de erro generica (404, 405 e 500).
 *
 * @var int    $status
 * @var string $message
 */
?>
<div class="card empty-state">
    <p class="error-code"><?= View::e((string) $status) ?></p>
    <h1 class="empty-state__title"><?= View::e($message) ?></h1>
    <p class="empty-state__text">
        <?php if ($status === 404) : ?>
            O endereco acessado nao existe ou foi movido.
        <?php elseif ($status === 405) : ?>
            Essa acao nao pode ser feita por esse metodo. Volte e tente de novo.
        <?php else : ?>
            Tente novamente em instantes. Se persistir, verifique os logs do servidor.
        <?php endif; ?>
    </p>
    <div class="empty-state__actions">
        <a class="btn btn--primary" href="<?= View::e(Url::to('/')) ?>">Voltar ao painel</a>
    </div>
</div>
