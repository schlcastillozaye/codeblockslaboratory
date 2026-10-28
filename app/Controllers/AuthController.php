<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Session;
use App\Services\AuthService;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect(url('dashboard'));
        }

        $this->render('auth/login', [
            'title' => 'Sign in',
            'bodyClass' => 'auth-body',
        ], 'layouts/auth');
    }

    public function login(): void
    {
        $login = (string) $this->input('login', '');
        $password = (string) ($_POST['password'] ?? '');

        $service = new AuthService();
        $result = $service->login($login, $password);

        if (!$result['ok']) {
            Flash::set('error', $result['message']);
            Flash::setOld(['login' => $login]);
            redirect(url('login'));
        }

        $intended = Session::pull('_intended_url');
        $safeIntended = null;
        if (is_string($intended) && $intended !== '') {
            $path = parse_url($intended, PHP_URL_PATH) ?: '';
            $host = parse_url($intended, PHP_URL_HOST);
            if ($host === null || $host === ($_SERVER['HTTP_HOST'] ?? '')) {
                $safeIntended = $path;
            }
        }

        Flash::set('success', $result['message']);
        redirect($safeIntended && str_contains($safeIntended, base_path()) ? $safeIntended : url('dashboard'));
    }

    public function logout(): void
    {
        (new AuthService())->logout();
        redirect(url('login'));
    }
}
