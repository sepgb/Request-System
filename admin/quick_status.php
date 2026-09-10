<?php
require_once 'auth_check.php';
require_once '../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$id             = (int)($_POST['id'] ?? 0);
$request_status = trim($_POST['request_status'] ?? '');
$validStatuses  = ['Pending', 'Ready for Pickup'];

if ($id <= 0 || !in_array($request_status, $validStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

$stmt = $conn->prepare("UPDATE requests SET request_status = ? WHERE id = ?");
$stmt->bind_param('si', $request_status, $id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Status updated.',
        'data' => ['id' => $id, 'request_status' => $request_status]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed: ' . $conn->error]);
}
$stmt->close();
