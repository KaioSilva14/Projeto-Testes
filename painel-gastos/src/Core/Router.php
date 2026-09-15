<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Roteador simples com parametros nomeados e duas travas de acesso:
 * "auth" (exige login) e "guest" (apenas visitantes).
 *
 * Toda rota POST tem o token CSRF verificado antes do controller rodar.
 */
final class Router
{
    /**
     * @var array<int, array{
     *     method: string,
     *     regex: string,
     *     params: array<int, string>,
     *     handler: array{0: class-string, 1: string},
     *     auth: bool,
     *     guest: bool
     * }>
     */
    private array $routes = [];

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param array{auth?: bool, guest?: bool} $options
     */
    public function get(string $path, array $handler, array $options = []): void
    {
        $this->add('GET', $path, $handler, $options);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param array{auth?: bool, guest?: bool} $options
     */
    public function post(string $path, array $handler, array $options = []): void
    {
        $this->add('POST', $path, $handler, $options);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param array{auth?: bool, guest?: bool} $options
     */
    private function add(string $method, string $path, array $handler, array $options): void
    {
        $params = [];

        // "/despesas/{id}/editar" -> "#^/despesas/(?P<id>[^/]+)/editar$#"
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static function (array $matches) use (&$params): string {
                $params[] = $matches[1];

                return '(?P<' . $matches[1] . '>[^/]+)';
            },
            $path,
        );

        if ($pattern === null) {
            throw new RuntimeException("Rota invalida: {$path}");
        }

        $this->routes[] = [
            'method'  => $method,
            'regex'   => '#^' . $pattern . '$#',
            'params'  => $params,
            'handler' => $handler,
            'auth'    => (bool) ($options['auth'] ?? false),
            'guest'   => (bool) ($options['guest'] ?? false),
        ];
    }

    public function dispatch(Request $request): void
    {
        $path          = $request->path();
        $pathMatched   = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            $pathMatched = true;

            if ($route['method'] !== $request->method()) {
                continue;
            }

            $params = [];

            foreach ($route['params'] as $name) {
                $params[$name] = (string) ($matches[$name] ?? '');
            }

            $request->setRouteParams($params);

            $this->runGuards($request, $route);

            [$class, $action] = $route['handler'];

            if (!class_exists($class) || !method_exists($class, $action)) {
                throw new RuntimeException("Handler inexistente: {$class}::{$action}");
            }

            $controller = new $class();
            $controller->{$action}($request);

            return;
        }

        // Caminho existe, mas nao com esse verbo HTTP.
        if ($pathMatched) {
            $this->fail($request, 405, 'Metodo nao permitido para esta rota.');
        }

        $this->fail($request, 404, 'Pagina nao encontrada.');
    }

    /** @param array{auth: bool, guest: bool} $route */
    private function runGuards(Request $request, array $route): void
    {
        if ($request->isPost() && !Csrf::check($request->input('_token'))) {
            Session::flash('error', 'Sessao expirada ou token invalido. Tente novamente.');

            Response::redirect(Auth::check() ? '/' : '/login');
        }

        if ($route['auth'] && !Auth::check()) {
            Session::flash('error', 'Faca login para continuar.');

            Response::redirect('/login');
        }

        if ($route['guest'] && Auth::check()) {
            Response::redirect('/');
        }
    }

    private function fail(Request $request, int $status, string $message): never
    {
        if ($request->wantsJson()) {
            Response::json(['erro' => $message], $status);
        }

        http_response_code($status);

        echo View::render('errors/404', [
            'status'  => $status,
            'message' => $message,
        ], Auth::check() ? 'layouts/app' : 'layouts/auth');

        exit;
    }
}
