<?php
namespace App\Models;
use App\Core\Model;

class PasswordReset extends Model {
    protected string $table = 'password_resets';

    public function create(string $email): string {
        // Delete any existing tokens for this email
        $this->exec('DELETE FROM password_resets WHERE email = ?', [$email]);
        $token = bin2hex(random_bytes(32));
        $this->insert([
            'email'      => $email,
            'token'      => $token,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return $token;
    }

    public function findValid(string $token): ?array {
        return $this->qOne(
            'SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()',
            [$token]
        );
    }

    public function delete(string $token): void {
        $this->exec('DELETE FROM password_resets WHERE token = ?', [$token]);
    }
}
