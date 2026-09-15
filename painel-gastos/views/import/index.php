<?php

declare(strict_types=1);

use App\Core\Money;
use App\Core\Url;
use App\Core\View;

/**
 * Importacao de CSV: upload, colagem de texto e relatorio do ultimo envio.
 *
 * @var array<string, mixed>|null $result
 */
?>

<?= View::partial('partials/page-header', [
    'heading'  => 'Importar CSV',
    'subtitle' => 'Carregue um extrato ou planilha para alimentar o painel',
    'actions'  => '<a class="btn btn--ghost" href="' . View::e(Url::to('/importar/modelo'))
        . '">Baixar modelo</a>',
]) ?>

<?php if (is_array($result)) : ?>
    <section class="card <?= $result['imported'] > 0 ? 'card--success' : 'card--danger' ?>">
        <header class="card__header">
            <h2 class="card__title">Resultado da importacao</h2>
        </header>

        <ul class="result-list">
            <li>
                <strong><?= View::e((string) $result['imported']) ?></strong> despesa(s) importada(s)
                <?php if ((int) $result['total_cents'] > 0) : ?>
                    &middot; total de <?= View::e(Money::format((int) $result['total_cents'])) ?>
                <?php endif; ?>
            </li>
            <li><strong><?= View::e((string) $result['skipped']) ?></strong> linha(s) ignorada(s)</li>
            <?php if ($result['created_categories'] !== []) : ?>
                <li>
                    Categorias criadas:
                    <?= View::e(implode(', ', $result['created_categories'])) ?>
                </li>
            <?php endif; ?>
        </ul>

        <?php if ($result['errors'] !== []) : ?>
            <details class="details" open>
                <summary class="details__summary">
                    Ver <?= View::e((string) count($result['errors'])) ?> aviso(s)
                </summary>
                <ul class="error-list">
                    <?php foreach ($result['errors'] as $error) : ?>
                        <li><?= View::e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>

        <?php if ((int) $result['imported'] > 0) : ?>
            <div class="empty-state__actions">
                <a class="btn btn--primary" href="<?= View::e(Url::to('/')) ?>">Ver no painel</a>
                <a class="btn btn--ghost" href="<?= View::e(Url::to('/despesas')) ?>">Ver despesas</a>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<div class="grid grid--sidebar-right">
    <section class="card">
        <header class="card__header">
            <h2 class="card__title">Enviar dados</h2>
            <p class="card__hint">Envie um arquivo ou cole as linhas diretamente</p>
        </header>

        <form class="form" method="post" action="<?= View::e(Url::to('/importar')) ?>"
              enctype="multipart/form-data">
            <input type="hidden" name="_token" value="<?= View::e($csrfToken) ?>">

            <div class="field">
                <label class="field__label" for="arquivo">Arquivo CSV</label>
                <input class="field__input field__input--file" type="file" id="arquivo" name="arquivo"
                       accept=".csv,text/csv,text/plain">
                <span class="field__hint">Ate 2 MB e 5.000 linhas por envio</span>
            </div>

            <div class="field">
                <label class="field__label" for="csv_content">Ou cole o conteudo</label>
                <textarea class="field__input field__input--area field__input--mono"
                          id="csv_content" name="csv_content" rows="8"
                          placeholder="data;descricao;valor;categoria;forma;obs&#10;15/09/2026;Supermercado;432,90;Alimentacao;debito;"></textarea>
                <span class="field__hint">O texto colado tem prioridade sobre o arquivo</span>
            </div>

            <div class="field field--check">
                <label class="check">
                    <input type="checkbox" name="create_categories" value="1" checked>
                    <span>Criar categorias que ainda nao existem</span>
                </label>
                <span class="field__hint">
                    Desmarcado, linhas com categoria desconhecida sao ignoradas e reportadas
                </span>
            </div>

            <button class="btn btn--primary" type="submit">Importar</button>
        </form>
    </section>

    <aside class="card">
        <header class="card__header">
            <h2 class="card__title">Formato esperado</h2>
        </header>

        <p class="card__text">
            A primeira linha deve conter os nomes das colunas. O separador pode
            ser <code>;</code> ou <code>,</code> e a deteccao e automatica.
        </p>

        <table class="table table--compact">
            <caption class="sr-only">Colunas aceitas na importacao</caption>
            <thead>
                <tr>
                    <th scope="col">Coluna</th>
                    <th scope="col">Obrigatoria</th>
                    <th scope="col">Formato</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>data</code></td><td>sim</td><td>dd/mm/aaaa ou aaaa-mm-dd</td></tr>
                <tr><td><code>descricao</code></td><td>sim</td><td>texto</td></tr>
                <tr><td><code>valor</code></td><td>sim</td><td>1234,56 ou 1234.56</td></tr>
                <tr><td><code>categoria</code></td><td>sim</td><td>texto</td></tr>
                <tr><td><code>forma</code></td><td>nao</td><td>pix, debito, credito...</td></tr>
                <tr><td><code>obs</code></td><td>nao</td><td>texto</td></tr>
            </tbody>
        </table>

        <ul class="hint-list">
            <li>Nomes de coluna com acento ou em maiusculas sao aceitos.</li>
            <li>Valores negativos viram despesas positivas (extratos usam sinal).</li>
            <li>BOM UTF-8 do Excel e removido automaticamente.</li>
            <li>Linhas invalidas nao cancelam a importacao: sao listadas no resultado.</li>
            <li>A importacao sempre adiciona; ela nao substitui lancamentos existentes.</li>
        </ul>
    </aside>
</div>
