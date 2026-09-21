<?php
require_once 'auth_check.php';
require_once '../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    echo json_encode(['success' => false, 'message' => 'Your session expired. Please refresh the page.']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// Snapshot the row BEFORE deleting it — this is the only record that
// survives once the row is gone.
$snap = $conn->prepare(
    "SELECT id, reference_no, student_number, full_name, document_type, request_status
       FROM requests WHERE id = ? AND request_status = 'Ready for Pickup'"
);
$snap->bind_param('i', $id);
$snap->execute();
$before = $snap->get_result()->fetch_assoc();
$snap->close();

if (!$before) {
    echo json_encode(['success' => false, 'message' => 'Request not found or not yet Ready for Pickup.']);
    exit;
}

$scope = getAdminDocumentScope();
if ($scope !== null && !in_array($before['document_type'], $scope, true)) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to manage this document type.']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM requests WHERE id = ? AND request_status = 'Ready for Pickup'");
$stmt->bind_param('i', $id);
$stmt->execute();

if ($stmt->affected_rows === 1) {
    logAudit($conn, $before, 'claimed', $before['request_status'], null);
    echo json_encode(['success' => true, 'message' => 'Request marked as claimed and removed.', 'data' => ['id' => $id]]);
} else {
    echo json_encode(['success' => false, 'message' => 'Request not found or not yet Ready for Pickup.']);
}
$stmt->close();
