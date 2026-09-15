<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Money;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\CsvImporter;

final class ImportController extends Controller
{
    /** Limite de upload aceito, alem do limite do proprio PHP. */
    private const MAX_BYTES = 2 * 1024 * 1024;

    public function __construct(
        private readonly CsvImporter $importer = new CsvImporter(),
    ) {
    }

    public function index(Request $request): void
    {
        $this->userId();

        $this->view('import/index', [
            'title'  => 'Importar CSV',
            // O resultado da ultima importacao vem via flash na sessao.
            'result' => Session::get('_import_result'),
        ]);

        Session::forget('_import_result');
    }

    public function store(Request $request): void
    {
        $userId  = $this->userId();
        $content = $this->readContent($request);

        if ($content === null) {
            $this->failure(
                'Envie um arquivo .csv ou cole o conteudo no campo de texto.',
                '/importar',
            );
        }

        $result = $this->importer->import(
            $userId,
            $content,
            $request->boolean('create_categories'),
        );

        Session::put('_import_result', $result);

        if ($result['imported'] === 0) {
            $this->failure('Nenhuma despesa importada. Veja os detalhes abaixo.', '/importar');
        }

        $message = sprintf(
            '%d despesa(s) importada(s), total de %s.',
            $result['imported'],
            Money::format($result['total_cents']),
        );

        if ($result['skipped'] > 0) {
            $message .= sprintf(' %d linha(s) ignorada(s).', $result['skipped']);
        }

        $this->success($message, '/importar');
    }

    /** Modelo de CSV para o usuario preencher. */
    public function template(Request $request): void
    {
        $this->userId();

        Response::download(
            "\xEF\xBB\xBF" . CsvImporter::template(),
            'modelo_importacao.csv',
        );
    }

    /**
     * Le o CSV do upload ou do campo de texto.
     *
     * O texto colado tem prioridade quando ambos vem preenchidos, porque e a
     * acao mais explicita do usuario naquele envio.
     */
    private function readContent(Request $request): ?string
    {
        $pasted = $request->input('csv_content');

        if ($pasted !== '') {
            return $pasted;
        }

        $file = $request->file('arquivo');

        if ($file === null) {
            return null;
        }

        if ($file['size'] > self::MAX_BYTES) {
            $this->failure('Arquivo maior que 2 MB. Divida a importacao em partes.', '/importar');
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            $this->failure('Upload invalido.', '/importar');
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, ['csv', 'txt'], true)) {
            $this->failure('Formato nao suportado. Envie um arquivo .csv.', '/importar');
        }

        $content = file_get_contents($file['tmp_name']);

        return $content === false || trim($content) === '' ? null : $content;
    }
}
