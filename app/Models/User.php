<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Paginator;

final class User
{
    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /** @return array<string, mixed>|null */
    public static function findByLogin(string $login): ?array
    {
        $login = trim($login);
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE email = :email_login OR school_id = :school_login LIMIT 1');
        $stmt->execute(['email_login' => $login, 'school_login' => $login]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /** @return array<string, mixed>|null */
    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => trim($email)]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function schoolIdExists(string $schoolId, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE school_id = :school_id';
        $params = ['school_id' => trim($schoolId)];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :email';
        $params = ['email' => strtolower(trim($email))];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** @param array<string, mixed> $filters */
    public static function paginate(array $filters = [], int $page = 1, int $perPage = 10): Paginator
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(u.first_name LIKE :q_first OR u.last_name LIKE :q_last OR CONCAT(u.first_name, " ", u.last_name) LIKE :q_full
                         OR u.email LIKE :q_email OR u.school_id LIKE :q_school)';
            $needle = '%' . trim((string) $filters['q']) . '%';
            $params['q_first'] = $needle;
            $params['q_last'] = $needle;
            $params['q_full'] = $needle;
            $params['q_email'] = $needle;
            $params['q_school'] = $needle;
        }
        if (!empty($filters['role'])) {
            $where[] = 'u.role = :role';
            $params['role'] = $filters['role'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'u.status = :status';
            $params['status'] = $filters['status'];
        }

        $whereSql = implode(' AND ', $where);
        $countSql = "SELECT COUNT(*) FROM users u WHERE $whereSql";
        $dataSql = "SELECT u.* FROM users u WHERE $whereSql ORDER BY u.role = 'admin' DESC, u.created_at DESC";

        return Paginator::fromQuery($countSql, $dataSql, $params, $page, $perPage);
    }

    /** @param array<string, mixed> $data */
    public static function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO users (school_id, first_name, last_name, email, password_hash, role, department, phone, status)
             VALUES (:school_id, :first_name, :last_name, :email, :password_hash, :role, :department, :phone, :status)'
        );
        $stmt->execute([
            'school_id'     => $data['school_id'],
            'first_name'    => $data['first_name'],
            'last_name'     => $data['last_name'],
            'email'         => strtolower($data['email']),
            'password_hash' => $data['password_hash'],
            'role'          => $data['role'],
            'department'    => $data['department'] ?: null,
            'phone'         => $data['phone'] ?: null,
            'status'        => $data['status'] ?? 'active',
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE users SET school_id = :school_id, first_name = :first_name, last_name = :last_name,
             email = :email, role = :role, department = :department, phone = :phone, status = :status
             WHERE id = :id'
        );
        $stmt->execute([
            'id'         => $id,
            'school_id'  => $data['school_id'],
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'email'      => strtolower($data['email']),
            'role'       => $data['role'],
            'department' => $data['department'] ?: null,
            'phone'      => $data['phone'] ?: null,
            'status'     => $data['status'],
        ]);
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public static function updatePasswordHash(int $id, string $hash): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute(['hash' => $hash, 'id' => $id]);
    }

    public static function touchLogin(int $id): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function activeAdminCount(): int
    {
        $stmt = Database::pdo()->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'");
        return (int) $stmt->fetchColumn();
    }

    public static function countByRole(): array
    {
        $rows = Database::pdo()->query('SELECT role, COUNT(*) AS total FROM users GROUP BY role')->fetchAll();
        $counts = ['admin' => 0, 'student' => 0, 'instructor' => 0];
        foreach ($rows as $row) {
            $counts[$row['role']] = (int) $row['total'];
        }
        return $counts;
    }
}
