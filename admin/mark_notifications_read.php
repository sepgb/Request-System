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

$scopeIn = documentScopeInClause($conn);
$activityAnd = $scopeIn !== null ? "AND document_type IN ($scopeIn) " : '';

$id = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    // Mark just this one notification read.
    $stmt = $conn->prepare("UPDATE audit_log SET is_read = 1 WHERE id = ? {$activityAnd}");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
} else {
    // No id given — "Mark all as read": everything unread in this admin's scope.
    $conn->query(
        "UPDATE audit_log SET is_read = 1 WHERE is_read = 0 "
            . ($scopeIn !== null ? "AND document_type IN ($scopeIn)" : '')
    );
}

$unreadWhere = $scopeIn !== null
    ? "WHERE document_type IN ($scopeIn) AND is_read = 0"
    : "WHERE is_read = 0";
$unreadCount = (int)($conn->query("SELECT COUNT(*) AS c FROM audit_log {$unreadWhere}")->fetch_assoc()['c'] ?? 0);

echo json_encode(['success' => true, 'unread' => $unreadCount]);
