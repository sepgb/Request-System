<?php
require_once 'auth_check.php';
require_once '../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function respond(array $payload): void
{
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Invalid request method.']);
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    respond(['success' => false, 'message' => 'Your session expired. Please refresh the page.']);
}

session_write_close();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    respond(['success' => false, 'message' => 'Invalid request.']);
}

try {
    $conn->query('SET SESSION innodb_lock_wait_timeout = 5');
    $snap = $conn->prepare(
        "SELECT id, reference_no, student_number, full_name, document_type, request_status, date_requested
           FROM requests WHERE id = ? AND request_status = 'Ready for Pickup'"
    );
    $snap->bind_param('i', $id);
    $snap->execute();
    $before = $snap->get_result()->fetch_assoc();
    $snap->close();

    if (!$before) {
        respond(['success' => false, 'message' => 'Request not found or not yet Ready for Pickup.']);
    }

    $scope = getAdminDocumentScope();
    if ($scope !== null && !in_array($before['document_type'], $scope, true)) {
        respond(['success' => false, 'message' => 'You do not have permission to manage this document type.']);
    }

    $stmt = $conn->prepare("DELETE FROM requests WHERE id = ? AND request_status = 'Ready for Pickup'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $deleted = ($stmt->affected_rows === 1);
    $stmt->close();

    if (!$deleted) {
        respond(['success' => false, 'message' => 'Request not found or not yet Ready for Pickup.']);
    }

    try {
        logAudit($conn, $before, 'claimed', $before['request_status'], null, $before['date_requested']);
    } catch (Throwable $e) {
        error_log('claim_request.php audit log failed: ' . $e->getMessage());
    }

    respond(['success' => true, 'message' => 'Request marked as claimed and removed.', 'data' => ['id' => $id]]);
} catch (Throwable $e) {
    error_log('claim_request.php failed: ' . $e->getMessage());
    respond(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
