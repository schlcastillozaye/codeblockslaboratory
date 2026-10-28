<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Session;
use App\Core\ValidationException;
use App\Models\AuditLog;
use App\Models\User;

final class ProfileController extends Controller
{
    public function index(): void
    {
        $this->render('profile/index', [
            'title' => 'My Profile',
            'activeNav' => 'profile',
            'user' => Auth::user(),
        ]);
    }

    public function update(): void
    {
        $user = Auth::user();
        if (!$user) {
            redirect(url('login'));
        }

        try {
            $data = $this->validate([
                'first_name' => 'required|min:2|max:80',
                'last_name' => 'required|min:2|max:80',
                'email' => 'required|email|max:150',
                'department' => 'nullable|max:120',
                'phone' => 'nullable|max:30',
            ]);

            if (User::emailExists($data['email'], (int) $user['id'])) {
                throw new ValidationException(['email' => 'That email address is already registered.']);
            }

            User::update((int) $user['id'], [
                'school_id' => $user['school_id'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'role' => $user['role'],
                'department' => $data['department'] ?? '',
                'phone' => $data['phone'] ?? '',
                'status' => $user['status'],
            ]);

            AuditLog::record(
                'profile.updated',
                'user',
                (int) $user['id'],
                'User updated their own profile.',
                ['email' => $user['email'], 'first_name' => $user['first_name'], 'last_name' => $user['last_name']],
                ['email' => $data['email'], 'first_name' => $data['first_name'], 'last_name' => $data['last_name']]
            );

            Flash::set('success', 'Your profile was updated.');
            redirect(url('profile'));
        } catch (ValidationException $e) {
            $this->validationFailed($e, 'profile');
        }
    }

    public function password(): void
    {
        $user = Auth::user();
        if (!$user) {
            redirect(url('login'));
        }

        try {
            $data = $this->validate([
                'current_password' => 'required',
                'password' => 'required|password',
                'password_confirm' => 'required|same:password',
            ]);

            if (!password_verify((string) ($_POST['current_password'] ?? ''), $user['password_hash'])) {
                throw new ValidationException(['current_password' => 'Your current password is incorrect.']);
            }
            if (password_verify((string) $data['password'], $user['password_hash'])) {
                throw new ValidationException(['password' => 'Choose a password different from your current one.']);
            }

            User::updatePasswordHash((int) $user['id'], password_hash($data['password'], PASSWORD_DEFAULT));
            AuditLog::record('profile.password_changed', 'user', (int) $user['id'], 'User changed their password.');

            Session::regenerate();
            Csrf::rotate();
            Flash::set('success', 'Password changed. Your session was refreshed for security.');
            redirect(url('profile'));
        } catch (ValidationException $e) {
            $this->validationFailed($e, 'profile');
        }
    }
}
