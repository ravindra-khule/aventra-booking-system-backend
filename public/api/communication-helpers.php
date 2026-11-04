<?php
/**
 * Shared helpers for communication log API endpoints.
 * Not meant to be called directly.
 */

if (!function_exists('formatCommunicationRow')) {
    /**
     * Map a communication_logs DB row to API output (JSON columns decoded).
     */
    function formatCommunicationRow($row) {
        return [
            'id' => (string) $row['id'],
            'customer_id' => $row['customer_id'] !== null ? (string) $row['customer_id'] : null,
            'type' => $row['type'],
            'direction' => $row['direction'],
            'status' => $row['status'],
            'subject' => $row['subject'],
            'content' => $row['content'] ?? '',
            'sender_name' => $row['sender_name'],
            'sender_email' => $row['sender_email'],
            'sender_phone' => $row['sender_phone'],
            'sender_type' => $row['sender_type'],
            'recipient_name' => $row['recipient_name'],
            'recipient_email' => $row['recipient_email'],
            'recipient_phone' => $row['recipient_phone'],
            'sent_at' => $row['sent_at'],
            'delivered_at' => $row['delivered_at'],
            'read_at' => $row['read_at'],
            'response_time' => $row['response_time'] !== null ? (int) $row['response_time'] : null,
            'template_id' => $row['template_id'],
            'template_name' => $row['template_name'],
            'internal_notes' => $row['internal_notes'],
            'attachments' => json_decode($row['attachments'] ?? '[]', true) ?: [],
            'tags' => json_decode($row['tags'] ?? '[]', true) ?: [],
            'related_booking_id' => $row['related_booking_id'],
            'related_booking_reference' => $row['related_booking_reference'],
            'metadata' => json_decode($row['metadata'] ?? 'null', true),
            'created_at' => $row['created_at'],
        ];
    }

    /**
     * Normalize an ISO-8601/datetime string to MySQL 'Y-m-d H:i:s'.
     * Returns null for empty/invalid input.
     */
    function normalizeTimestamp($value) {
        if ($value === null || $value === '') {
            return null;
        }
        $ts = strtotime((string) $value);
        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
    }

    /**
     * Fetch a single non-deleted communication log row by ID.
     */
    function fetchCommunicationRow($conn, $id) {
        $stmt = $conn->prepare("SELECT * FROM communication_logs WHERE id = ? AND deleted_at IS NULL");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row;
    }
}
?>
