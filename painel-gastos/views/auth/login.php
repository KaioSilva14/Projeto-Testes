<?php

declare(strict_types=1);

use App\Core\Url;
use App\Core\View;

/**
 * @var array<string, mixed>  $old
 * @var array<string, string> $errors
 * @var string                $demoEmail
 * @var bool                  $hasDemo
 */
?>
<h2 class="card__title">Entrar</h2>

<form class="form" method="post" action="<?= View::e(Url::to('/login')) ?>" novalidate>
    <input type="hidden" name="_token" value="<?= View::e($csrfToken) ?>">

    <div class="field">
        <label class="field__label" for="email">E-mail</label>
        <input class="field__input<?= isset($errors['email']) ? ' has-error' : '' ?>"
               type="email" id="email" name="email" required autocomplete="email" autofocus
               value="<?= View::e((string) ($old['email'] ?? '')) ?>">
        <?php if (isset($errors['email'])) : ?>
            <span class="field__error"><?= View::e($errors['email']) ?></span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="password">Senha</label>
        <input class="field__input<?= isset($errors['password']) ? ' has-error' : '' ?>"
               type="password" id="password" name="password" required autocomplete="current-password">
        <?php if (isset($errors['password'])) : ?>
            <span class="field__error"><?= View::e($errors['password']) ?></span>
        <?php endif; ?>
    </div>

    <button class="btn btn--primary btn--block" type="submit">Entrar</button>
</form>

<?php if ($hasDemo) : ?>
    <div class="auth__demo">
        <strong>Conta de demonstracao</strong>
        <p>
            E-mail <code><?= View::e($demoEmail) ?></code><br>
            Senha <code>senha123</code>
        </p>
    </div>
<?php endif; ?>

<p class="auth__switch">
    Ainda nao tem conta?
    <a href="<?= View::e(Url::to('/registrar')) ?>">Criar conta</a>
</p>
