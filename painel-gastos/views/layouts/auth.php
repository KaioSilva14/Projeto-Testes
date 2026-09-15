<?php

declare(strict_types=1);

use App\Core\Url;
use App\Core\View;

/**
 * Layout das telas publicas (login, cadastro e paginas de erro sem sessao).
 *
 * @var string $content
 * @var string $title
 * @var string $appName
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= View::e($title) ?> &middot; <?= View::e($appName) ?></title>
    <link rel="stylesheet" href="<?= View::e(Url::asset('css/app.css')) ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>&#128202;</text></svg>">
</head>
<body class="body--centered">
<div class="auth">
    <div class="auth__brand">
        <span class="auth__logo" aria-hidden="true">&#128202;</span>
        <h1 class="auth__title"><?= View::e($appName) ?></h1>
        <p class="auth__subtitle">Controle de gastos com relatorios visuais</p>
    </div>

    <?= View::partial('partials/flash', ['flashes' => $flashes ?? []]) ?>

    <div class="card auth__card">
        <?= $content ?>
    </div>
</div>
</body>
</html>
