<?php
require_once 'auth_check.php';
require_once '../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM requests WHERE id = ? AND request_status = 'Ready for Pickup'");
$stmt->bind_param('i', $id);
$stmt->execute();

if ($stmt->affected_rows === 1) {
    echo json_encode(['success' => true, 'message' => 'Request marked as claimed and removed.', 'data' => ['id' => $id]]);
} else {
    echo json_encode(['success' => false, 'message' => 'Request not found or not yet Ready for Pickup.']);
}
$stmt->close();
