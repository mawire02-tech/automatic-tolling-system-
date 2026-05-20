<?php
// app/models/DeviceModel.php

class DeviceModel extends BaseModel {

    protected string $table = 'devices';

    public function findByApiKey(string $key): ?array {
        return $this->db->fetchOne(
            "SELECT * FROM devices WHERE api_key = ? LIMIT 1",
            [$key]
        );
    }

    public function findByCode(string $code): ?array {
        return $this->db->fetchOne(
            "SELECT * FROM devices WHERE device_code = ? LIMIT 1",
            [$code]
        );
    }

    public function getOnline(): array {
        return $this->db->fetchAll(
            "SELECT * FROM devices WHERE status = 'online' ORDER BY device_name"
        );
    }

    public function heartbeat(int $id, string $ip, string $barrierStatus = 'unknown', string $firmware = ''): void {
        $this->db->execute(
            "UPDATE devices SET last_heartbeat = NOW(), status = 'online', ip_address = ?,
             barrier_status = ?, firmware_version = COALESCE(NULLIF(?,''), firmware_version)
             WHERE id = ?",
            [$ip, $barrierStatus, $firmware, $id]
        );
    }

    public function markOfflineStale(int $minutes = 2): void {
        $this->db->execute(
            "UPDATE devices SET status = 'offline'
             WHERE status = 'online' AND (last_heartbeat IS NULL OR last_heartbeat < DATE_SUB(NOW(), INTERVAL ? MINUTE))",
            [$minutes]
        );
    }

    public function incrementStats(int $id, float $amount): void {
        $this->db->execute(
            "UPDATE devices SET total_transactions = total_transactions + 1,
             total_revenue = total_revenue + ? WHERE id = ?",
            [$amount, $id]
        );
    }

    public function updateBarrierStatus(int $id, string $status): void {
        $this->db->execute(
            "UPDATE devices SET barrier_status = ? WHERE id = ?",
            [$status, $id]
        );
    }

    public function isOnline(int $id): bool {
        $device = $this->find($id);
        return $device && $device['status'] === 'online';
    }

    /**
     * Send a command to the ESP32 device via its command queue table.
     * The ESP32 polls /api/v1/device/commands on heartbeat and executes pending ones.
     */
    public function sendCommand(int $deviceId, string $command, array $params = []): int {
        return $this->db->execute(
            "INSERT INTO device_commands (device_id, command, params, status, created_at)
             VALUES (?, ?, ?, 'pending', NOW())",
            [$deviceId, $command, json_encode($params)]
        );
    }

    public function getPendingCommands(int $deviceId): array {
        return $this->db->fetchAll(
            "SELECT * FROM device_commands WHERE device_id = ? AND status = 'pending' ORDER BY created_at ASC LIMIT 5",
            [$deviceId]
        );
    }

    public function ackCommand(int $cmdId, string $result = 'ok'): void {
        $this->db->execute(
            "UPDATE device_commands SET status = 'executed', result = ?, executed_at = NOW() WHERE id = ?",
            [$result, $cmdId]
        );
    }
}
