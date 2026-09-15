<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\CategoryRepository;
use App\Repositories\UserRepository;
use App\Services\DemoSeeder;

final class AuthController extends Controller
{
    /** Categorias iniciais criadas junto com a conta. */
    private const STARTER_CATEGORIES = [
        ['Alimentacao', '#dc2626'],
        ['Moradia', '#2563eb'],
        ['Transporte', '#d97706'],
        ['Saude', '#16a34a'],
        ['Lazer', '#7c3aed'],
        ['Outros', '#64748b'],
    ];

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly CategoryRepository $categories = new CategoryRepository(),
    ) {
    }

    public function showLogin(Request $request): void
    {
        $this->view('auth/login', [
            'title'      => 'Entrar',
            'old'        => Session::pullOld(),
            'errors'     => Session::pullErrors(),
            'demoEmail'  => DemoSeeder::EMAIL,
            // O atalho de demonstracao so aparece se o seed ja rodou.
            'hasDemo'    => $this->users->findByEmail(DemoSeeder::EMAIL) !== null,
        ], 'layouts/auth');
    }

    public function login(Request $request): void
    {
        $email    = $request->input('email');
        $password = $request->input('password');

        $validator = Validator::make($request->all())
            ->required('email', 'E-mail')
            ->email('email', 'e-mail')
            ->required('password', 'Senha');

        if ($validator->fails()) {
            $this->withErrors($validator, $request->all(), '/login');
        }

        if (!Auth::attempt($email, $password)) {
            Session::flashInput($request->all(), ['email' => 'E-mail ou senha incorretos.']);
            $this->failure('Nao foi possivel entrar. Verifique os dados.', '/login');
        }

        $this->success('Bem-vindo de volta!', '/');
    }

    public function showRegister(Request $request): void
    {
        $this->view('auth/register', [
            'title'  => 'Criar conta',
            'old'    => Session::pullOld(),
            'errors' => Session::pullErrors(),
        ], 'layouts/auth');
    }

    public function register(Request $request): void
    {
        $data = $request->all();

        $validator = Validator::make($data)
            ->required('name', 'Nome')
            ->minLength('name', 2, 'Nome')
            ->maxLength('name', 80, 'Nome')
            ->required('email', 'E-mail')
            ->email('email', 'e-mail')
            ->maxLength('email', 160, 'E-mail')
            ->required('password', 'Senha')
            ->minLength('password', 8, 'Senha')
            ->matches('password_confirmation', 'password', 'A confirmacao de senha');

        if ($this->users->emailExists($request->input('email'))) {
            $validator->add('email', 'Este e-mail ja esta cadastrado.');
        }

        if ($validator->fails()) {
            $this->withErrors($validator, $data, '/registrar');
        }

        // Conta e categorias iniciais em uma unica transacao: se algo falhar,
        // nao sobra usuario sem categoria (o cadastro de despesa exigiria uma).
        $userId = Database::transaction(function () use ($request): int {
            $userId = $this->users->create(
                $request->input('name'),
                $request->input('email'),
                $request->input('password'),
            );

            foreach (self::STARTER_CATEGORIES as [$name, $color]) {
                $this->categories->create($userId, $name, $color);
            }

            return $userId;
        });

        Auth::login((int) $userId);

        $this->success('Conta criada! Comece cadastrando suas despesas.', '/despesas/nova');
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        Session::start();

        $this->success('Sessao encerrada.', '/login');
    }
}
