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
        $stmt = $this->db->prepare("SELECT id, name, email, role, department, phone, active, created_at FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function all(): array
    {
        $stmt = $this->db->query("SELECT id, name, email, role, department, phone, active FROM users ORDER BY name ASC");
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, password, role, department, phone, active)
            VALUES (:name, :email, :password, :role, :department, :phone, :active)
        ");
        $stmt->execute([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'password'   => password_hash($data['password'], PASSWORD_DEFAULT),
            'role'       => $data['role'] ?? 'solicitante',
            'department' => $data['department'],
            'phone'      => $data['phone'] ?? null,
            'active'     => $data['active'] ?? 1
        ]);
        return (int)$this->db->lastInsertId();
    }
}
