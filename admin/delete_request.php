<?php
require_once 'auth_check.php';
require_once '../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

function delete_fail($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    delete_fail('Invalid request method.', 405);
}

/* CSRF check */
$sentToken = $_POST['csrf_token'] ?? '';
$validToken = false;
if (function_exists('verify_csrf_token')) {
    $validToken = verify_csrf_token($sentToken);
} elseif (function_exists('csrf_verify')) {
    $validToken = csrf_verify($sentToken);
} else {
    $validToken = isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sentToken);
}
if (!$validToken) {
    delete_fail('Session expired. Please refresh the page and try again.', 403);
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    delete_fail('Invalid request.');
}

$scopeIn = documentScopeInClause($conn);
$scopeAnd = $scopeIn !== null ? " AND document_type IN ($scopeIn)" : '';

$stmt = $conn->prepare("DELETE FROM requests WHERE id = ? AND request_status = 'Rejected'{$scopeAnd}");
$stmt->bind_param('i', $id);
$stmt->execute();

if ($stmt->affected_rows < 1) {
    delete_fail('Only rejected requests can be deleted, or the request no longer exists.', 404);
}

echo json_encode(['success' => true]);
