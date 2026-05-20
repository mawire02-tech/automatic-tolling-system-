<?php
// app/models/RfidModel.php

class RfidModel extends BaseModel {

    protected string $table = 'rfid_cards';

    public function findByUid(string $uid): ?array {
        return $this->db->fetchOne(
            "SELECT rc.*,
               v.plate_number, v.vehicle_type AS vtype, v.status AS vstatus, v.id AS vehicle_id,
               u.id AS uid, u.wallet_balance, u.full_name, u.status AS ustatus
             FROM rfid_cards rc
             LEFT JOIN vehicles v ON rc.vehicle_id = v.id
             LEFT JOIN users u    ON rc.user_id    = u.id
             WHERE rc.card_uid = ?
             LIMIT 1",
            [$uid]
        );
    }

    public function touch(string $uid): void {
        $this->db->execute(
            "UPDATE rfid_cards SET last_used = NOW() WHERE card_uid = ?",
            [$uid]
        );
    }

    public function assignToVehicle(string $uid, int $vehicleId, int $userId): void {
        $existing = $this->db->fetchOne("SELECT id FROM rfid_cards WHERE card_uid = ? LIMIT 1", [$uid]);
        if ($existing) {
            $this->db->execute(
                "UPDATE rfid_cards SET vehicle_id = ?, user_id = ?, status = 'active' WHERE card_uid = ?",
                [$vehicleId, $userId, $uid]
            );
        } else {
            $this->db->execute(
                "INSERT INTO rfid_cards (card_uid, vehicle_id, user_id, status) VALUES (?, ?, ?, 'active')",
                [$uid, $vehicleId, $userId]
            );
        }
    }
}
