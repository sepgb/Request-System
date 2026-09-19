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

$id             = (int)($_POST['id'] ?? 0);
$request_status = trim($_POST['request_status'] ?? '');
$validStatuses  = ['Pending', 'Processing', 'Ready for Pickup', 'Rejected'];

if ($id <= 0 || !in_array($request_status, $validStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

// Snapshot the row BEFORE changing it, so the audit log has the old status.
$snap = $conn->prepare(
    "SELECT id, reference_no, student_number, full_name, document_type, request_status
       FROM requests WHERE id = ?"
);
$snap->bind_param('i', $id);
$snap->execute();
$before = $snap->get_result()->fetch_assoc();
$snap->close();

if (!$before) {
    echo json_encode(['success' => false, 'message' => 'Request not found.']);
    exit;
}

$stmt = $conn->prepare("UPDATE requests SET request_status = ? WHERE id = ?");
$stmt->bind_param('si', $request_status, $id);

if ($stmt->execute()) {
    $stmt->close();

    if ($before['request_status'] !== $request_status) {
        logAudit($conn, $before, 'status_change', $before['request_status'], $request_status);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Status updated.',
        'data' => ['id' => $id, 'request_status' => $request_status]
    ]);
} else {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Update failed: ' . $conn->error]);
}
