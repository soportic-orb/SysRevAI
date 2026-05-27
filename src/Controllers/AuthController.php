<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;

final class AuthController
{
    public function showLogin(): void
    {
        echo View::render('auth/login', [
            'error' => Session::pullFlash('login_error'),
            'email' => Session::pullFlash('login_email', ''),
        ], 'layouts/auth');
    }

    public function login(): void
    {
        $email    = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            Session::flash('login_error', __('auth.invalid_credentials'));
            Session::flash('login_email', $email);
            redirect('/login');
        }

        if (!Auth::attempt($email, $password)) {
            Session::flash('login_error', __('auth.invalid_credentials'));
            Session::flash('login_email', $email);
            redirect('/login');
        }

        redirect('/dashboard');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/login');
    }
}
