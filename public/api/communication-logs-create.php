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
 * POST /api/communication-logs-create.php
 * Create a communication log entry
 * Body: { customerId, type, direction, status?, subject?, content, sender?,
 *         recipient?, sentAt?, deliveredAt?, readAt?, responseTime?,
 *         templateId?, templateName?, internalNotes?, attachments?, tags?,
 *         relatedBookingId?, relatedBookingReference?, metadata? }
 * `sender`/`recipient` accept objects: { name, email?, phone?, type? }
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/communication-helpers.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $body = json_decode(file_get_contents('php://input'), true);
    
    $type = $body['type'] ?? '';
    $direction = $body['direction'] ?? '';
    $content = $body['content'] ?? '';
    
    if (!in_array($type, ['email', 'sms', 'call', 'note'], true)) {
        sendJSON(['success' => false, 'error' => 'Invalid type (email, sms, call, note)'], 400);
    }
    if (!in_array($direction, ['inbound', 'outbound'], true)) {
        sendJSON(['success' => false, 'error' => 'Invalid direction (inbound, outbound)'], 400);
    }
    if ($content === '') {
        sendJSON(['success' => false, 'error' => 'content is required'], 400);
    }
    
    $sender = is_array($body['sender'] ?? null) ? $body['sender'] : [];
    $recipient = is_array($body['recipient'] ?? null) ? $body['recipient'] : [];
    
    $customerId = isset($body['customerId']) && $body['customerId'] !== '' ? (int) $body['customerId'] : (isset($body['customer_id']) ? (int) $body['customer_id'] : null);
    $status = $body['status'] ?? 'sent';
    $subject = $body['subject'] ?? null;
    $senderName = $sender['name'] ?? null;
    $senderEmail = $sender['email'] ?? null;
    $senderPhone = $sender['phone'] ?? null;
    $senderType = in_array($sender['type'] ?? '', ['customer', 'staff'], true) ? $sender['type'] : 'staff';
    $recipientName = $recipient['name'] ?? null;
    $recipientEmail = $recipient['email'] ?? null;
    $recipientPhone = $recipient['phone'] ?? null;
    $sentAt = normalizeTimestamp($body['sentAt'] ?? $body['sent_at'] ?? null) ?? date('Y-m-d H:i:s');
    $deliveredAt = normalizeTimestamp($body['deliveredAt'] ?? $body['delivered_at'] ?? null);
    $readAt = normalizeTimestamp($body['readAt'] ?? $body['read_at'] ?? null);
    $responseTime = isset($body['responseTime']) ? (int) $body['responseTime'] : (isset($body['response_time']) ? (int) $body['response_time'] : null);
    $templateId = $body['templateId'] ?? $body['template_id'] ?? null;
    $templateName = $body['templateName'] ?? $body['template_name'] ?? null;
    $internalNotes = $body['internalNotes'] ?? $body['internal_notes'] ?? null;
    $attachments = isset($body['attachments']) ? json_encode($body['attachments']) : '[]';
    $tags = isset($body['tags']) ? json_encode($body['tags']) : '[]';
    $relatedBookingId = $body['relatedBookingId'] ?? $body['related_booking_id'] ?? null;
    $relatedBookingRef = $body['relatedBookingReference'] ?? $body['related_booking_reference'] ?? null;
    $metadata = isset($body['metadata']) ? json_encode($body['metadata']) : null;
    
    $conn = getDB();
    
    $sql = "INSERT INTO communication_logs 
            (customer_id, type, direction, status, subject, content,
             sender_name, sender_email, sender_phone, sender_type,
             recipient_name, recipient_email, recipient_phone,
             sent_at, delivered_at, read_at, response_time,
             template_id, template_name, internal_notes,
             attachments, tags, related_booking_id, related_booking_reference, metadata) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        sendJSON(['success' => false, 'error' => 'Query prepare failed'], 500);
    }
    
    $stmt->bind_param(
        'isssssssssssssssissssssss',
        $customerId, $type, $direction, $status, $subject, $content,
        $senderName, $senderEmail, $senderPhone, $senderType,
        $recipientName, $recipientEmail, $recipientPhone,
        $sentAt, $deliveredAt, $readAt, $responseTime,
        $templateId, $templateName, $internalNotes,
        $attachments, $tags, $relatedBookingId, $relatedBookingRef, $metadata
    );
    
    if (!$stmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Failed to create communication log: ' . $stmt->error], 500);
    }
    
    $logId = $stmt->insert_id;
    $stmt->close();
    
    $row = fetchCommunicationRow($conn, $logId);
    $conn->close();
    
    sendJSON([
        'success' => true,
        'message' => 'Communication log created successfully',
        'data' => formatCommunicationRow($row)
    ], 201);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>
