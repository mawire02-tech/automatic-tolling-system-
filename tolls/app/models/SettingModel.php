<?php
// app/models/SettingModel.php

class SettingModel extends BaseModel {

    protected string $table = 'system_settings';

    private static array $cache = [];

    public function get(string $key, mixed $default = null): mixed {
        if (isset(self::$cache[$key])) return self::$cache[$key];
        $row = $this->db->fetchOne(
            "SELECT setting_value FROM system_settings WHERE setting_key = ?", [$key]
        );
        $val = $row ? $row['setting_value'] : $default;
        self::$cache[$key] = $val;
        return $val;
    }

    public function set(string $key, mixed $value, int $updatedBy = 0): void {
        $this->db->execute(
            "UPDATE system_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?",
            [(string)$value, $updatedBy ?: null, $key]
        );
        self::$cache[$key] = (string)$value;
    }

    public function getTollFee(string $vehicleType): float {
        return (float)$this->get("toll_fee_{$vehicleType}", 35.00);
    }

    public function getAllGrouped(): array {
        $rows   = $this->db->fetchAll(
            "SELECT * FROM system_settings ORDER BY setting_group, setting_key"
        );
        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['setting_group']][] = $row;
        }
        return $groups;
    }
}
