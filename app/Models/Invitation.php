<?php
namespace App\Models;

use App\Core\Model;

class Invitation extends Model {
    protected string $table = 'invitations';

    public function generate(int $groupId, string $email, int $invitedBy): string {
        $token = bin2hex(random_bytes(32));
        $this->exec(
            "INSERT INTO invitations (group_id, email, token, invited_by, status, expires_at, created_at)
             VALUES (?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 7 DAY), NOW())",
            [$groupId, $email, $token, $invitedBy]
        );
        return $token;
    }

    public function findValid(string $token): ?array {
        return $this->qOne(
            "SELECT * FROM invitations WHERE token = ? AND status = 'pending' AND expires_at > NOW()",
            [$token]
        );
    }

    public function markAccepted(string $token): void {
        $this->exec("UPDATE invitations SET status = 'accepted' WHERE token = ?", [$token]);
    }
}
