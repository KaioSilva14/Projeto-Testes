<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Period;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;

/**
 * Base dos controllers: renderizacao, flash e redirecionamentos.
 */
abstract class Controller
{
    /**
     * Renderiza uma view dentro do layout e envia a resposta.
     *
     * @param array<string, mixed> $data
     */
    protected function view(string $template, array $data = [], string $layout = 'layouts/app'): void
    {
        Response::html(View::render($template, $data, $layout));
    }

    /** Id do usuario autenticado (rotas protegidas garantem que existe). */
    protected function userId(): int
    {
        return Auth::requireId();
    }

    /** Mes de referencia da tela, vindo de ?mes=YYYY-MM. */
    protected function period(Request $request): Period
    {
        return Period::fromString($request->query('mes'));
    }

    protected function success(string $message, string $path, array $query = []): never
    {
        Session::flash('success', $message);

        Response::redirect($path, $query);
    }

    protected function failure(string $message, string $path, array $query = []): never
    {
        Session::flash('error', $message);

        Response::redirect($path, $query);
    }

    /**
     * Devolve o usuario ao formulario preservando o que ele digitou e os
     * erros de cada campo.
     *
     * @param array<string, mixed> $input
     */
    protected function withErrors(Validator $validator, array $input, string $path, array $query = []): never
    {
        Session::flashInput($input, $validator->errors());
        Session::flash('error', 'Corrija os campos destacados e tente novamente.');

        Response::redirect($path, $query);
    }
}
