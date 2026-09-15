<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Renderizador de templates PHP puro, com layout e dados compartilhados.
 */
final class View
{
    /** @var array<string, mixed> */
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /**
     * Renderiza o template e, quando informado, o embute no layout.
     *
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        $content = self::capture($template, $data);

        if ($layout === null) {
            return $content;
        }

        return self::capture($layout, array_merge($data, ['content' => $content]));
    }

    /** Inclui um trecho reutilizavel de dentro de outro template. */
    public static function partial(string $template, array $data = []): string
    {
        return self::capture($template, $data);
    }

    /** @param array<string, mixed> $data */
    private static function capture(string $template, array $data): string
    {
        $file = BASE_PATH . '/views/' . ltrim($template, '/') . '.php';

        if (!is_file($file)) {
            throw new RuntimeException("View nao encontrada: {$template}");
        }

        // extract() apos o merge: dados especificos sobrescrevem compartilhados.
        extract(array_merge(self::$shared, $data), EXTR_OVERWRITE);

        ob_start();

        try {
            require $file;
        } catch (Throwable $e) {
            ob_end_clean();

            throw $e;
        }

        return (string) ob_get_clean();
    }

    /** Escapa texto para HTML. Usado como e() nos templates. */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Serializa dados para consumo por JavaScript embutido na pagina. */
    public static function json(mixed $value): string
    {
        return (string) json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
        );
    }
}
