<?php
// app/models/TopupModel.php

class TopupModel extends BaseModel {

    protected string $table = 'topup_requests';

    public function hasPending(int $userId): bool {
        return (bool)$this->db->fetchOne(
            "SELECT id FROM topup_requests WHERE user_id = ? AND status = 'pending' LIMIT 1",
            [$userId]
        );
    }

    public function getWithUser(string $status = 'pending'): array {
        return $this->db->fetchAll(
            "SELECT tr.*, u.full_name, u.username, u.wallet_balance
             FROM topup_requests tr JOIN users u ON tr.user_id = u.id
             WHERE tr.status = ? ORDER BY tr.requested_at DESC",
            [$status]
        );
    }

    public function approve(int $id, int $adminId, string $note = ''): void {
        $req = $this->find($id);
        if (!$req || $req['status'] !== 'pending') return;
        $this->db->beginTransaction();
        try {
            $this->db->execute(
                "UPDATE topup_requests SET status='approved', admin_note=?, processed_by=?, processed_at=NOW() WHERE id=?",
                [$note, $adminId, $id]
            );
            $this->db->execute(
                "UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?",
                [$req['amount'], $req['user_id']]
            );
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function reject(int $id, int $adminId, string $note = ''): void {
        $this->db->execute(
            "UPDATE topup_requests SET status='rejected', admin_note=?, processed_by=?, processed_at=NOW() WHERE id=?",
            [$note, $adminId, $id]
        );
    }
}
