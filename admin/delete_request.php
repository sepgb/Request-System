<?php
require_once 'auth_check.php';
require_once '../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

/* Make database problems throw instead of silently waiting/returning false. */
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

/*
 * Release the PHP session lock right away. PHP locks the session file for the
 * whole request, so if this endpoint ever stalls, every other admin page (like
 * Statistics) would sit "loading" behind it. $_SESSION is still readable below.
 */
session_write_close();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    respond(['success' => false, 'message' => 'Invalid request.']);
}

try {
    /* Fail fast (5s) instead of waiting ~50s if another connection holds a lock. */
    $conn->query('SET SESSION innodb_lock_wait_timeout = 5');

    // Snapshot the row BEFORE deleting it — this is the only record that
    // survives once the row is gone.
    $snap = $conn->prepare(
        "SELECT id, reference_no, student_number, full_name, document_type, request_status
           FROM requests WHERE id = ? AND request_status = 'Rejected'"
    );
    $snap->bind_param('i', $id);
    $snap->execute();
    $before = $snap->get_result()->fetch_assoc();
    $snap->close();

    if (!$before) {
        respond(['success' => false, 'message' => 'Request not found or not Rejected.']);
    }

    $scope = getAdminDocumentScope();
    if ($scope !== null && !in_array($before['document_type'], $scope, true)) {
        respond(['success' => false, 'message' => 'You do not have permission to manage this document type.']);
    }

    $stmt = $conn->prepare("DELETE FROM requests WHERE id = ? AND request_status = 'Rejected'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $deleted = ($stmt->affected_rows === 1);
    $stmt->close();

    if (!$deleted) {
        respond(['success' => false, 'message' => 'Request not found or not Rejected.']);
    }

    /* The row is already gone; a logging problem must not turn this into an error. */
    try {
        logAudit($conn, $before, 'deleted', $before['request_status'], null);
    } catch (Throwable $e) {
        error_log('delete_request.php audit log failed: ' . $e->getMessage());
    }

    respond(['success' => true, 'message' => 'Rejected request deleted.', 'data' => ['id' => $id]]);
} catch (Throwable $e) {
    error_log('delete_request.php failed: ' . $e->getMessage());
    respond(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
