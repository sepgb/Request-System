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
$action = $_POST['action'] ?? '';

if ($action === 'delete') {
    // "Delete notifications": hide the selected entries from the bell. The audit_log
    // rows themselves are kept — Statistics still needs them — so this only flips
    // is_dismissed. Needs: ALTER TABLE audit_log ADD COLUMN is_dismissed TINYINT(1) NOT NULL DEFAULT 0;
    $ids = array_values(array_unique(array_filter(
        array_map('intval', explode(',', (string)($_POST['ids'] ?? ''))),
        function ($v) {
            return $v > 0;
        }
    )));
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No notifications selected.']);
        exit;
    }
    $in = implode(',', array_slice($ids, 0, 500));   // integers only, safe to inline
    $conn->query("UPDATE audit_log SET is_dismissed = 1 WHERE id IN ({$in}) {$activityAnd}");
} elseif ($id > 0) {
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
    ? "WHERE is_dismissed = 0 AND document_type IN ($scopeIn) AND is_read = 0"
    : "WHERE is_dismissed = 0 AND is_read = 0";
$unreadCount = (int)($conn->query("SELECT COUNT(*) AS c FROM audit_log {$unreadWhere}")->fetch_assoc()['c'] ?? 0);

echo json_encode(['success' => true, 'unread' => $unreadCount]);
