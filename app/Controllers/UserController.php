<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\BusinessException;
use App\Core\Flash;
use App\Core\ValidationException;
use App\Models\AuditLog;
use App\Models\User;

final class UserController extends Controller
{
    public function index(): void
    {
        $filters = [
            'q' => (string) $this->input('q', ''),
            'role' => (string) $this->input('role', ''),
            'status' => (string) $this->input('status', ''),
        ];

        $paginator = User::paginate($filters, max(1, $this->intInput('page', 1)), 10);

        $this->render('users/index', [
            'title' => 'User Accounts',
            'activeNav' => 'users',
            'filters' => $filters,
            'paginator' => $paginator,
            'roleCounts' => User::countByRole(),
        ]);
    }

    public function create(): void
    {
        $this->render('users/form', [
            'title' => 'Add User Account',
            'activeNav' => 'users',
            'user' => null,
        ]);
    }

    public function store(): void
    {
        try {
            $data = $this->validate($this->rules(true));
            $this->assertUnique($data);

            $id = User::create([
                'school_id' => $data['school_id'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => strtolower($data['email']),
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                'role' => $data['role'],
                'department' => $data['department'] ?? '',
                'phone' => $data['phone'] ?? '',
                'status' => $data['status'],
            ]);

            AuditLog::record(
                'user.created',
                'user',
                $id,
                'Created ' . $data['role'] . ' account for ' . $data['first_name'] . ' ' . $data['last_name'] . '.',
                null,
                ['email' => $data['email'], 'role' => $data['role'], 'status' => $data['status']]
            );

            Flash::set('success', 'User account created. Share the initial password through a secure channel.');
            redirect(url('users'));
        } catch (ValidationException $e) {
            $this->validationFailed($e, 'users/create');
        }
    }

    public function edit(string $id): void
    {
        $user = User::find((int) $id);
        if (!$user) {
            $this->notFound('That user account does not exist.');
        }

        $this->render('users/form', [
            'title' => 'Edit ' . $user['first_name'] . ' ' . $user['last_name'],
            'activeNav' => 'users',
            'user' => $user,
        ]);
    }

    public function update(string $id): void
    {
        $userId = (int) $id;
        $existing = User::find($userId);
        if (!$existing) {
            $this->notFound('That user account does not exist.');
        }

        try {
            $data = $this->validate($this->rules(false));
            $this->assertUnique($data, $userId);

            $newRole = $data['role'];
            $newStatus = $data['status'];
            $removingAdmin = $existing['role'] === 'admin' && ($newRole !== 'admin' || $newStatus !== 'active');

            if ($removingAdmin && User::activeAdminCount() <= 1) {
                throw new ValidationException(['role' => 'The system must keep at least one active administrator.']);
            }
            if ($userId === (int) Auth::id() && ($newStatus !== 'active' || $newRole !== 'admin')) {
                throw new ValidationException(['status' => 'You cannot deactivate or demote your own administrator account.']);
            }

            User::update($userId, [
                'school_id' => $data['school_id'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => strtolower($data['email']),
                'role' => $newRole,
                'department' => $data['department'] ?? '',
                'phone' => $data['phone'] ?? '',
                'status' => $newStatus,
            ]);

            if (!empty($data['password'])) {
                User::updatePasswordHash($userId, password_hash($data['password'], PASSWORD_DEFAULT));
            }

            if ($existing['role'] !== $newRole) {
                AuditLog::record('user.role_changed', 'user', $userId, 'Changed role of ' . $existing['email'] . ' from ' . $existing['role'] . ' to ' . $newRole . '.');
            }
            if ($existing['status'] !== $newStatus) {
                AuditLog::record('user.status_changed', 'user', $userId, 'Changed account status of ' . $existing['email'] . ' from ' . $existing['status'] . ' to ' . $newStatus . '.');
            }

            AuditLog::record(
                'user.updated',
                'user',
                $userId,
                'Updated user account ' . $existing['email'] . '.',
                ['email' => $existing['email'], 'role' => $existing['role'], 'status' => $existing['status']],
                ['email' => $data['email'], 'role' => $newRole, 'status' => $newStatus]
            );

            Flash::set('success', 'User account updated.' . (!empty($data['password']) ? ' Password was reset.' : ''));
            redirect(url('users'));
        } catch (ValidationException $e) {
            $this->validationFailed($e, 'users/' . $userId . '/edit');
        }
    }

    /** @return array<string, string> */
    private function rules(bool $creating): array
    {
        $rules = [
            'first_name' => 'required|min:2|max:80',
            'last_name' => 'required|min:2|max:80',
            'school_id' => 'required|max:30|regex:/^[A-Za-z0-9][A-Za-z0-9-]{2,29}$/',
            'email' => 'required|email|max:150',
            'role' => 'required|in:admin,student,instructor',
            'department' => 'nullable|max:120',
            'phone' => 'nullable|max:30',
            'status' => 'required|in:active,inactive',
        ];

        if ($creating) {
            $rules['password'] = 'required|password';
            $rules['password_confirm'] = 'required|same:password';
        } else {
            $rules['password'] = 'nullable|password';
            $rules['password_confirm'] = 'nullable|same:password';
        }

        return $rules;
    }

    /** @param array<string, mixed> $data */
    private function assertUnique(array $data, ?int $exceptId = null): void
    {
        if (User::schoolIdExists((string) $data['school_id'], $exceptId)) {
            throw new ValidationException(['school_id' => 'That school/employee ID is already registered.']);
        }
        if (User::emailExists((string) $data['email'], $exceptId)) {
            throw new ValidationException(['email' => 'That email address is already registered.']);
        }
    }
}
