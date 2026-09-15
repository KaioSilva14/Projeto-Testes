<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Atalhos para escrever a resposta HTTP.
 *
 * Os metodos que encerram a requisicao usam exit para impedir que o
 * controller continue executando depois de um redirect.
 */
final class Response
{
    public static function html(string $content, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $content;
    }

    /** @param array<string, mixed>|list<mixed> $data */
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');

        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        );

        exit;
    }

    /** @param array<string, mixed> $query */
    public static function redirect(string $path, array $query = []): never
    {
        header('Location: ' . Url::to($path, $query), true, 302);

        exit;
    }

    /** Volta para a pagina anterior, ou para a raiz se nao houver referer. */
    public static function back(string $fallback = '/'): never
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');

        if ($referer !== '') {
            header('Location: ' . $referer, true, 302);

            exit;
        }

        self::redirect($fallback);
    }

    /** Envia um arquivo gerado em memoria como download. */
    public static function download(string $content, string $filename, string $contentType = 'text/csv'): never
    {
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? 'download.csv';

        http_response_code(200);
        header('Content-Type: ' . $contentType . '; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-store');

        echo $content;

        exit;
    }
}
