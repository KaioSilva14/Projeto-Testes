<?php

declare(strict_types=1);

use App\Core\Money;
use App\Core\Url;
use App\Core\View;

/* As views rodam no namespace global, por isso DateTimeImmutable nao precisa
   (nem pode) ser importado com use. */

/**
 * Formulario de despesa, usado tanto na criacao quanto na edicao.
 *
 * @var array<string, mixed>|null        $expense
 * @var array<int, array<string, mixed>> $categories
 * @var array<string, string>            $methods
 * @var array<string, mixed>             $old
 * @var array<string, string>            $errors
 * @var string                           $action
 * @var string                           $title
 */

$isEdit = $expense !== null;

/**
 * Valor de um campo: o que o usuario acabou de digitar tem prioridade sobre
 * o que esta salvo, para nao perder dados apos um erro de validacao.
 */
$value = static function (string $field, mixed $fallback = '') use ($old): string {
    return (string) ($old[$field] ?? $fallback);
};

$today = (new DateTimeImmutable('now'))->format('Y-m-d');

$amountFallback = $isEdit
    ? Money::toInputValue((int) $expense['amount_cents'])
    : '';
?>

<?= View::partial('partials/page-header', [
    'heading'  => $title,
    'subtitle' => $isEdit
        ? 'Alterar um lancamento existente'
        : 'Registrar um novo gasto',
    'actions'  => '<a class="btn btn--ghost" href="' . View::e(Url::to('/despesas')) . '">Voltar</a>',
]) ?>

<form class="card form" method="post" action="<?= View::e(Url::to($action)) ?>" novalidate>
    <input type="hidden" name="_token" value="<?= View::e($csrfToken) ?>">

    <div class="form__grid">
        <div class="field field--wide">
            <label class="field__label" for="description">Descricao *</label>
            <input class="field__input<?= isset($errors['description']) ? ' has-error' : '' ?>"
                   type="text" id="description" name="description" maxlength="160" required
                   placeholder="Ex.: Supermercado do mes"
                   value="<?= View::e($value('description', $isEdit ? $expense['description'] : '')) ?>">
            <?php if (isset($errors['description'])) : ?>
                <span class="field__error"><?= View::e($errors['description']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="field__label" for="amount">Valor (R$) *</label>
            <input class="field__input<?= isset($errors['amount']) ? ' has-error' : '' ?>"
                   type="text" inputmode="decimal" id="amount" name="amount" required
                   placeholder="0,00"
                   value="<?= View::e($value('amount', $amountFallback)) ?>">
            <span class="field__hint">Aceita 1234,56 ou 1234.56</span>
            <?php if (isset($errors['amount'])) : ?>
                <span class="field__error"><?= View::e($errors['amount']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="field__label" for="spent_at">Data *</label>
            <input class="field__input<?= isset($errors['spent_at']) ? ' has-error' : '' ?>"
                   type="date" id="spent_at" name="spent_at" required
                   max="<?= View::e($today) ?>"
                   value="<?= View::e($value('spent_at', $isEdit ? $expense['spent_at'] : $today)) ?>">
            <?php if (isset($errors['spent_at'])) : ?>
                <span class="field__error"><?= View::e($errors['spent_at']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="field__label" for="category_id">Categoria *</label>
            <select class="field__input<?= isset($errors['category_id']) ? ' has-error' : '' ?>"
                    id="category_id" name="category_id" required>
                <option value="">Selecione...</option>
                <?php $selected = (int) $value('category_id', $isEdit ? $expense['category_id'] : 0); ?>
                <?php foreach ($categories as $category) : ?>
                    <option value="<?= View::e((string) $category['id']) ?>"
                        <?= $selected === (int) $category['id'] ? 'selected' : '' ?>>
                        <?= View::e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="field__hint">
                <a href="<?= View::e(Url::to('/categorias')) ?>">gerenciar categorias</a>
            </span>
            <?php if (isset($errors['category_id'])) : ?>
                <span class="field__error"><?= View::e($errors['category_id']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="field__label" for="payment_method">Forma de pagamento *</label>
            <select class="field__input<?= isset($errors['payment_method']) ? ' has-error' : '' ?>"
                    id="payment_method" name="payment_method" required>
                <?php $method = $value('payment_method', $isEdit ? $expense['payment_method'] : 'pix'); ?>
                <?php foreach ($methods as $key => $label) : ?>
                    <option value="<?= View::e($key) ?>" <?= $method === $key ? 'selected' : '' ?>>
                        <?= View::e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['payment_method'])) : ?>
                <span class="field__error"><?= View::e($errors['payment_method']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field field--full">
            <label class="field__label" for="notes">Observacoes</label>
            <textarea class="field__input field__input--area<?= isset($errors['notes']) ? ' has-error' : '' ?>"
                      id="notes" name="notes" rows="3" maxlength="500"
                      placeholder="Opcional"><?= View::e($value('notes', $isEdit ? ($expense['notes'] ?? '') : '')) ?></textarea>
            <?php if (isset($errors['notes'])) : ?>
                <span class="field__error"><?= View::e($errors['notes']) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="form__footer">
        <button class="btn btn--primary" type="submit">
            <?= $isEdit ? 'Salvar alteracoes' : 'Registrar despesa' ?>
        </button>
        <a class="btn btn--ghost" href="<?= View::e(Url::to('/despesas')) ?>">Cancelar</a>
    </div>
</form>

<?php if ($isEdit) : ?>
    <?php /* Formulario separado: HTML nao permite <form> aninhado. */ ?>
    <div class="card card--danger">
        <div class="card__row">
            <div>
                <h2 class="card__title">Excluir despesa</h2>
                <p class="card__hint">A exclusao e definitiva e nao pode ser desfeita.</p>
            </div>
            <form method="post"
                  action="<?= View::e(Url::to('/despesas/' . (int) $expense['id'] . '/excluir')) ?>"
                  data-confirm="Excluir esta despesa? A acao nao pode ser desfeita.">
                <input type="hidden" name="_token" value="<?= View::e($csrfToken) ?>">
                <button class="btn btn--danger" type="submit">Excluir despesa</button>
            </form>
        </div>
    </div>
<?php endif; ?>
