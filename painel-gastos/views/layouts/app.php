<?php

declare(strict_types=1);

use App\Core\Url;
use App\Core\View;

/**
 * Layout das telas autenticadas: barra lateral fixa + area de conteudo.
 *
 * @var string                    $content
 * @var string                    $title
 * @var string                    $appName
 * @var array<string, mixed>|null $authUser
 * @var string                    $currentPath
 */

$menu = [
    ['/', 'Painel', 'M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z'],
    ['/despesas', 'Despesas', 'M4 4h16v4H4zM4 10h16v10H4zm3 3h6v2H7z'],
    ['/categorias', 'Categorias', 'M4 4h7v7H4zm9 0h7v7h-7zM4 13h7v7H4zm9 0h7v7h-7z'],
    ['/orcamentos', 'Orcamentos', 'M3 6h18v4H3zm0 6h12v3H3zm0 5h8v3H3z'],
    ['/relatorios', 'Relatorios', 'M4 19V5h2v14zm5 0V9h2v10zm5 0v-7h2v7zm5 0V3h2v16z'],
    ['/importar', 'Importar CSV', 'M12 3v10m0 0l-4-4m4 4l4-4M4 17v3h16v-3'],
];
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
<body>
<a class="skip-link" href="#conteudo">Ir para o conteudo</a>

<div class="layout">
    <aside class="sidebar" id="barra-lateral">
        <div class="sidebar__brand">
            <span class="sidebar__logo" aria-hidden="true">&#128202;</span>
            <span class="sidebar__name"><?= View::e($appName) ?></span>
        </div>

        <nav class="nav" aria-label="Menu principal">
            <?php foreach ($menu as [$path, $label, $icon]) : ?>
                <?php
                $isActive = $path === '/'
                    ? $currentPath === '/'
                    : str_starts_with($currentPath, $path);
                ?>
                <a class="nav__item<?= $isActive ? ' is-active' : '' ?>"
                   href="<?= View::e(Url::to($path)) ?>"
                   <?= $isActive ? 'aria-current="page"' : '' ?>>
                    <svg class="nav__icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="<?= View::e($icon) ?>" fill="currentColor" stroke="currentColor"
                              stroke-width="0.5" stroke-linejoin="round"/>
                    </svg>
                    <span><?= View::e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar__footer">
            <a class="btn btn--primary btn--block" href="<?= View::e(Url::to('/despesas/nova')) ?>">
                + Nova despesa
            </a>

            <?php if ($authUser !== null) : ?>
                <div class="user">
                    <div class="user__avatar" aria-hidden="true">
                        <?= View::e(mb_strtoupper(mb_substr((string) $authUser['name'], 0, 1))) ?>
                    </div>
                    <div class="user__info">
                        <strong class="user__name"><?= View::e($authUser['name']) ?></strong>
                        <span class="user__email"><?= View::e($authUser['email']) ?></span>
                    </div>
                </div>

                <form method="post" action="<?= View::e(Url::to('/sair')) ?>">
                    <input type="hidden" name="_token" value="<?= View::e($csrfToken) ?>">
                    <button class="btn btn--ghost btn--block" type="submit">Sair</button>
                </form>
            <?php endif; ?>
        </div>
    </aside>

    <main class="main" id="conteudo">
        <?= View::partial('partials/flash', ['flashes' => $flashes ?? []]) ?>
        <?= $content ?>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= View::e(Url::asset('js/charts.js')) ?>"></script>
<script src="<?= View::e(Url::asset('js/app.js')) ?>"></script>
</body>
</html>
