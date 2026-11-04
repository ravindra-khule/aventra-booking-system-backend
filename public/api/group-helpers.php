<?php
/**
 * Shared helpers for customer group API endpoints.
 * Not meant to be called directly.
 */

if (!function_exists('formatGroupRow')) {
    /**
     * Map a customer_groups DB row to API output (JSON columns decoded).
     */
    function formatGroupRow($row) {
        $memberIds = json_decode($row['member_ids'] ?? '[]', true) ?: [];
        return [
            'id' => (string) $row['id'],
            'name' => $row['name'],
            'description' => $row['description'] ?? '',
            'type' => $row['type'],
            'member_ids' => array_map('strval', $memberIds),
            'member_count' => count($memberIds),
            'smart_rules' => json_decode($row['smart_rules'] ?? 'null', true),
            'pricing_rules' => json_decode($row['pricing_rules'] ?? '[]', true) ?: [],
            'default_discount' => json_decode($row['default_discount'] ?? 'null', true),
            'tags' => json_decode($row['tags'] ?? '[]', true) ?: [],
            'color' => $row['color'],
            'is_active' => (bool) $row['is_active'],
            'created_by' => $row['created_by'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    /**
     * Fetch a single non-deleted group row by ID.
     */
    function fetchGroupRow($conn, $id) {
        $stmt = $conn->prepare("SELECT * FROM customer_groups WHERE id = ? AND deleted_at IS NULL");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row;
    }
}
?>
