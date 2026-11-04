<?php
// ⚠️ CORS and Content-Type headers MUST be set first, before any output
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
header('Access-Control-Allow-Headers: Origin, Content-Type, Authorization, X-Requested-With, Accept');
header('Access-Control-Max-Age: 86400');
header('Access-Control-Allow-Credentials: false');
header('Content-Type: application/json; charset=UTF-8');

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'CORS preflight OK']);
    exit;
}

/**
 * PUT /api/communication-logs-update.php
 * Update a communication log (status, notes, tags, etc.)
 * Body: { id, ...fields to update }
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/communication-helpers.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $body = json_decode(file_get_contents('php://input'), true);
    
    $id = (int) ($body['id'] ?? 0);
    if (!$id) {
        sendJSON(['success' => false, 'error' => 'Log ID is required'], 400);
    }
    
    $conn = getDB();
    
    if (!fetchCommunicationRow($conn, $id)) {
        sendJSON(['success' => false, 'error' => 'Communication log not found'], 404);
    }
    
    $updateFields = [];
    $types = '';
    $values = [];
    
    // Scalar fields: apiKey => column
    $scalarMap = [
        'customerId' => 'customer_id',
        'customer_id' => 'customer_id',
        'type' => 'type',
        'direction' => 'direction',
        'status' => 'status',
        'subject' => 'subject',
        'content' => 'content',
        'internalNotes' => 'internal_notes',
        'internal_notes' => 'internal_notes',
        'templateId' => 'template_id',
        'template_id' => 'template_id',
        'templateName' => 'template_name',
        'template_name' => 'template_name',
        'relatedBookingId' => 'related_booking_id',
        'related_booking_id' => 'related_booking_id',
        'relatedBookingReference' => 'related_booking_reference',
        'related_booking_reference' => 'related_booking_reference',
    ];
    
    $handledCols = [];
    foreach ($scalarMap as $key => $column) {
        if (array_key_exists($key, $body) && !isset($handledCols[$column])) {
            $updateFields[] = "$column = ?";
            $types .= 's';
            $values[] = $body[$key];
            $handledCols[$column] = true;
        }
    }
    
    // Nested sender/recipient objects
    if (isset($body['sender']) && is_array($body['sender'])) {
        $senderCols = ['name' => 'sender_name', 'email' => 'sender_email', 'phone' => 'sender_phone', 'type' => 'sender_type'];
        foreach ($senderCols as $k => $col) {
            if (array_key_exists($k, $body['sender'])) {
                $updateFields[] = "$col = ?";
                $types .= 's';
                $values[] = $body['sender'][$k];
            }
        }
    }
    if (isset($body['recipient']) && is_array($body['recipient'])) {
        $recCols = ['name' => 'recipient_name', 'email' => 'recipient_email', 'phone' => 'recipient_phone'];
        foreach ($recCols as $k => $col) {
            if (array_key_exists($k, $body['recipient'])) {
                $updateFields[] = "$col = ?";
                $types .= 's';
                $values[] = $body['recipient'][$k];
            }
        }
    }
    
    // Timestamp fields (normalize ISO-8601 -> MySQL datetime)
    $tsMap = [
        'sentAt' => 'sent_at',
        'sent_at' => 'sent_at',
        'deliveredAt' => 'delivered_at',
        'delivered_at' => 'delivered_at',
        'readAt' => 'read_at',
        'read_at' => 'read_at',
    ];
    foreach ($tsMap as $key => $column) {
        if (array_key_exists($key, $body) && !isset($handledCols[$column])) {
            $updateFields[] = "$column = ?";
            $types .= 's';
            $values[] = normalizeTimestamp($body[$key]);
            $handledCols[$column] = true;
        }
    }
    
    if (isset($body['responseTime']) || isset($body['response_time'])) {
        $updateFields[] = "response_time = ?";
        $types .= 'i';
        $values[] = isset($body['responseTime']) ? (int) $body['responseTime'] : (int) $body['response_time'];
    }
    
    // JSON fields
    $jsonMap = [
        'attachments' => 'attachments',
        'tags' => 'tags',
        'metadata' => 'metadata',
    ];
    $handledJson = [];
    foreach ($jsonMap as $key => $column) {
        if (array_key_exists($key, $body) && !isset($handledJson[$column])) {
            $updateFields[] = "$column = ?";
            $types .= 's';
            $values[] = $body[$key] === null ? null : json_encode($body[$key]);
            $handledJson[$column] = true;
        }
    }
    
    if (empty($updateFields)) {
        sendJSON(['success' => false, 'error' => 'No fields to update'], 400);
    }
    
    $updateFields[] = "updated_at = NOW()";
    
    $sql = "UPDATE communication_logs SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendJSON(['success' => false, 'error' => 'Query prepare failed'], 500);
    }
    
    $types .= 'i';
    $values[] = $id;
    $stmt->bind_param($types, ...$values);
    
    if (!$stmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Failed to update communication log'], 500);
    }
    $stmt->close();
    
    $row = fetchCommunicationRow($conn, $id);
    $conn->close();
    
    sendJSON([
        'success' => true,
        'message' => 'Communication log updated successfully',
        'data' => formatCommunicationRow($row)
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>
