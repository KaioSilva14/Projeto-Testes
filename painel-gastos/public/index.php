<?php

declare(strict_types=1);

/**
 * Front controller: toda requisicao entra por aqui.
 *
 * Servidor embutido:  php -S localhost:8000 -t public
 * Apache/XAMPP:       aponte o DocumentRoot para esta pasta (ou use o .htaccess)
 */

use App\Controllers\ApiController;
use App\Controllers\AuthController;
use App\Controllers\BudgetController;
use App\Controllers\CategoryController;
use App\Controllers\DashboardController;
use App\Controllers\ExpenseController;
use App\Controllers\ImportController;
use App\Controllers\ReportController;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Migrator;
use App\Core\Period;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;

require dirname(__DIR__) . '/src/bootstrap.php';

$request = new Request();

Url::setBase($request->basePath());
Session::start();

// Cria as tabelas na primeira execucao (schema.sql e idempotente).
Migrator::ensure();

// Dados disponiveis em qualquer view, inclusive nos layouts.
View::share('appName', (string) Config::get('app.name', 'Painel de Gastos'));
View::share('authUser', Auth::user());
View::share('csrfToken', Csrf::token());
View::share('currentPath', $request->path());
View::share('flashes', Session::pullFlash());
View::share('currentPeriod', Period::fromString($request->query('mes')));
View::share('title', (string) Config::get('app.name', 'Painel de Gastos'));

$router = new Router();

// ---------------------------------------------------------------- publico
$router->get('/login', [AuthController::class, 'showLogin'], ['guest' => true]);
$router->post('/login', [AuthController::class, 'login'], ['guest' => true]);
$router->get('/registrar', [AuthController::class, 'showRegister'], ['guest' => true]);
$router->post('/registrar', [AuthController::class, 'register'], ['guest' => true]);
$router->post('/sair', [AuthController::class, 'logout']);

// ------------------------------------------------------------------ painel
$router->get('/', [DashboardController::class, 'index'], ['auth' => true]);

// ---------------------------------------------------------------- despesas
$router->get('/despesas', [ExpenseController::class, 'index'], ['auth' => true]);
$router->get('/despesas/nova', [ExpenseController::class, 'create'], ['auth' => true]);
$router->post('/despesas', [ExpenseController::class, 'store'], ['auth' => true]);
$router->get('/despesas/exportar', [ExpenseController::class, 'export'], ['auth' => true]);
$router->get('/despesas/{id}/editar', [ExpenseController::class, 'edit'], ['auth' => true]);
$router->post('/despesas/{id}', [ExpenseController::class, 'update'], ['auth' => true]);
$router->post('/despesas/{id}/excluir', [ExpenseController::class, 'destroy'], ['auth' => true]);

// -------------------------------------------------------------- categorias
$router->get('/categorias', [CategoryController::class, 'index'], ['auth' => true]);
$router->post('/categorias', [CategoryController::class, 'store'], ['auth' => true]);
$router->post('/categorias/{id}', [CategoryController::class, 'update'], ['auth' => true]);
$router->post('/categorias/{id}/excluir', [CategoryController::class, 'destroy'], ['auth' => true]);

// -------------------------------------------------------------- orcamentos
$router->get('/orcamentos', [BudgetController::class, 'index'], ['auth' => true]);
$router->post('/orcamentos', [BudgetController::class, 'save'], ['auth' => true]);
$router->post('/orcamentos/copiar', [BudgetController::class, 'copyPrevious'], ['auth' => true]);

// -------------------------------------------------------------- relatorios
$router->get('/relatorios', [ReportController::class, 'index'], ['auth' => true]);
$router->get('/relatorios/exportar', [ReportController::class, 'export'], ['auth' => true]);

// --------------------------------------------------------------- importar
$router->get('/importar', [ImportController::class, 'index'], ['auth' => true]);
$router->post('/importar', [ImportController::class, 'store'], ['auth' => true]);
$router->get('/importar/modelo', [ImportController::class, 'template'], ['auth' => true]);

// ---------------------------------------------------- API JSON (graficos)
$router->get('/api/resumo', [ApiController::class, 'summary'], ['auth' => true]);
$router->get('/api/categorias', [ApiController::class, 'byCategory'], ['auth' => true]);
$router->get('/api/diario', [ApiController::class, 'daily'], ['auth' => true]);
$router->get('/api/mensal', [ApiController::class, 'monthly'], ['auth' => true]);
$router->get('/api/formas-pagamento', [ApiController::class, 'byPaymentMethod'], ['auth' => true]);
$router->get('/api/orcamentos', [ApiController::class, 'budgets'], ['auth' => true]);
$router->get('/api/tendencia', [ApiController::class, 'trend'], ['auth' => true]);

try {
    $router->dispatch($request);
} catch (Throwable $e) {
    http_response_code(500);

    if (Config::get('app.debug') === true) {
        // Em desenvolvimento, mostra o erro real para facilitar o diagnostico.
        header('Content-Type: text/plain; charset=utf-8');

        echo "Erro: {$e->getMessage()}\n\n";
        echo "Arquivo: {$e->getFile()}:{$e->getLine()}\n\n";
        echo $e->getTraceAsString();

        exit;
    }

    error_log('[painel-gastos] ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());

    if ($request->wantsJson()) {
        Response::json(['erro' => 'Erro interno.'], 500);
    }

    Response::html(View::render('errors/404', [
        'status'  => 500,
        'message' => 'Algo deu errado ao processar sua solicitacao.',
    ], Auth::check() ? 'layouts/app' : 'layouts/auth'), 500);
}
