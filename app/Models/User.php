<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class User extends Model
{
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT id, name, email, role, department, phone, active, avatar, created_at FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function all(): array
    {
        $stmt = $this->db->query("SELECT id, name, email, role, department, phone, active, avatar FROM users ORDER BY name ASC");
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, password, role, department, phone, active, avatar)
            VALUES (:name, :email, :password, :role, :department, :phone, :active, :avatar)
        ");
        $stmt->execute([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'password'   => password_hash($data['password'], PASSWORD_DEFAULT),
            'role'       => $data['role'] ?? 'solicitante',
            'department' => $data['department'],
            'phone'      => $data['phone'] ?? null,
            'active'     => $data['active'] ?? 1,
            'avatar'     => $data['avatar'] ?? null
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [
            'name = :name',
            'email = :email',
            'role = :role',
            'department = :department',
            'phone = :phone',
            'active = :active'
        ];
        $params = [
            'id'         => $id,
            'name'       => $data['name'],
            'email'      => $data['email'],
            'role'       => $data['role'],
            'department' => $data['department'],
            'phone'      => $data['phone'] ?? null,
            'active'     => $data['active'] ?? 1
        ];

        if (!empty($data['password'])) {
            $fields[] = 'password = :password';
            $params['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if (array_key_exists('avatar', $data)) {
            $fields[] = 'avatar = :avatar';
            $params['avatar'] = $data['avatar'];
        }

        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function resetPassword(int $id, string $newPassword): bool
    {
        $stmt = $this->db->prepare("UPDATE users SET password = :p WHERE id = :id");
        return $stmt->execute([
            'id' => $id,
            'p'  => password_hash($newPassword, PASSWORD_DEFAULT)
        ]);
    }

    public function toggleStatus(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE users SET active = IF(active = 1, 0, 1) WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function updateProfile(int $id, array $data): bool
    {
        $fields = [
            'name = :name',
            'email = :email',
            'phone = :phone'
        ];
        $params = [
            'id'    => $id,
            'name'  => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null
        ];

        if (!empty($data['password'])) {
            $fields[] = 'password = :password';
            $params['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if (array_key_exists('avatar', $data)) {
            $fields[] = 'avatar = :avatar';
            $params['avatar'] = $data['avatar'];
        }

        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}
