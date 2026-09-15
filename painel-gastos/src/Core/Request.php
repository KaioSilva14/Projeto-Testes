<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Leitura da requisicao HTTP corrente, ja normalizada.
 */
final class Request
{
    /** @var array<string, string> */
    private array $routeParams = [];

    private string $method;

    private string $path;

    private string $basePath;

    public function __construct()
    {
        $this->method   = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $this->basePath = $this->resolveBasePath();
        $this->path     = $this->resolvePath();
    }

    /**
     * Subdiretorio em que a aplicacao esta publicada.
     *
     * Permite servir tanto em http://localhost:8000/ (servidor embutido) como
     * em http://localhost/painel-gastos/public/ (Apache/XAMPP).
     */
    private function resolveBasePath(): string
    {
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $base   = rtrim(str_replace('\\', '/', dirname($script)), '/');

        return $base === '/' ? '' : $base;
    }

    private function resolvePath(): string
    {
        $uri  = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';

        if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath));
        }

        $path = '/' . trim(rawurldecode($path), '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /** @param array<string, string> $params */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function routeParam(string $key, ?string $default = null): ?string
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function routeParamInt(string $key): int
    {
        return (int) ($this->routeParams[$key] ?? 0);
    }

    /** Valor de $_GET, sempre como string trimada. */
    public function query(string $key, ?string $default = null): ?string
    {
        $value = $_GET[$key] ?? null;

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function queryInt(string $key, int $default = 0): int
    {
        $value = $this->query($key);

        return $value === null || $value === '' ? $default : (int) $value;
    }

    /** Valor de $_POST, sempre como string trimada. */
    public function input(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? null;

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function inputInt(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        return $value === '' ? $default : (int) $value;
    }

    public function has(string $key): bool
    {
        return isset($_POST[$key]) || isset($_GET[$key]);
    }

    public function boolean(string $key): bool
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? null;

        return in_array($value, ['1', 'on', 'true', 'sim', true, 1], true);
    }

    /**
     * Todos os campos de $_POST como strings trimadas.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $data = [];

        foreach ($_POST as $key => $value) {
            $data[(string) $key] = is_scalar($value) ? trim((string) $value) : $value;
        }

        return $data;
    }

    /**
     * Campo de array em $_POST (ex.: orcamentos[12] = "500,00").
     *
     * @return array<string|int, mixed>
     */
    public function arrayInput(string $key): array
    {
        $value = $_POST[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * Arquivo enviado, ou null quando ausente/invalido.
     *
     * @return array{name: string, tmp_name: string, size: int, error: int}|null
     */
    public function file(string $key): ?array
    {
        $file = $_FILES[$key] ?? null;

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        return [
            'name'     => (string) ($file['name'] ?? ''),
            'tmp_name' => (string) ($file['tmp_name'] ?? ''),
            'size'     => (int) ($file['size'] ?? 0),
            'error'    => (int) ($file['error'] ?? 0),
        ];
    }

    /** Preserva os filtros atuais ao montar links (paginacao, ordenacao). */
    public function withQuery(array $overrides = [], array $except = []): string
    {
        $params = $_GET;

        foreach ($overrides as $key => $value) {
            if ($value === null || $value === '') {
                unset($params[$key]);
                continue;
            }

            $params[$key] = $value;
        }

        foreach ($except as $key) {
            unset($params[$key]);
        }

        $query = http_build_query($params);

        return $query === '' ? '' : '?' . $query;
    }

    public function wantsJson(): bool
    {
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');

        return str_contains($accept, 'application/json')
            || str_starts_with($this->path, '/api/');
    }
}
