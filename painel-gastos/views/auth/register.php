<?php

declare(strict_types=1);

use App\Core\Url;
use App\Core\View;

/**
 * @var array<string, mixed>  $old
 * @var array<string, string> $errors
 */
?>
<h2 class="card__title">Criar conta</h2>
<p class="card__hint">A conta comeca com um conjunto de categorias basicas.</p>

<form class="form" method="post" action="<?= View::e(Url::to('/registrar')) ?>" novalidate>
    <input type="hidden" name="_token" value="<?= View::e($csrfToken) ?>">

    <div class="field">
        <label class="field__label" for="name">Nome</label>
        <input class="field__input<?= isset($errors['name']) ? ' has-error' : '' ?>"
               type="text" id="name" name="name" maxlength="80" required autocomplete="name" autofocus
               value="<?= View::e((string) ($old['name'] ?? '')) ?>">
        <?php if (isset($errors['name'])) : ?>
            <span class="field__error"><?= View::e($errors['name']) ?></span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="email">E-mail</label>
        <input class="field__input<?= isset($errors['email']) ? ' has-error' : '' ?>"
               type="email" id="email" name="email" maxlength="160" required autocomplete="email"
               value="<?= View::e((string) ($old['email'] ?? '')) ?>">
        <?php if (isset($errors['email'])) : ?>
            <span class="field__error"><?= View::e($errors['email']) ?></span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="password">Senha</label>
        <input class="field__input<?= isset($errors['password']) ? ' has-error' : '' ?>"
               type="password" id="password" name="password" required autocomplete="new-password"
               minlength="8">
        <span class="field__hint">Minimo de 8 caracteres</span>
        <?php if (isset($errors['password'])) : ?>
            <span class="field__error"><?= View::e($errors['password']) ?></span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="password_confirmation">Confirmar senha</label>
        <input class="field__input<?= isset($errors['password_confirmation']) ? ' has-error' : '' ?>"
               type="password" id="password_confirmation" name="password_confirmation" required
               autocomplete="new-password">
        <?php if (isset($errors['password_confirmation'])) : ?>
            <span class="field__error"><?= View::e($errors['password_confirmation']) ?></span>
        <?php endif; ?>
    </div>

    <button class="btn btn--primary btn--block" type="submit">Criar conta</button>
</form>

<p class="auth__switch">
    Ja tem conta?
    <a href="<?= View::e(Url::to('/login')) ?>">Entrar</a>
</p>
