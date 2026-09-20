<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: track.php');
    exit;
}

$reference_no = trim($_POST['reference_no'] ?? '');
$student_number = trim($_POST['student_number'] ?? '');

if ($reference_no === '' || $student_number === '' || !csrf_verify($_POST['csrf_token'] ?? null)) {
    header('Location: track.php');
    exit;
}

$clientIp = getClientIp();
if (!checkRateLimit($conn, $clientIp)) {
    header('Location: track.php?reference_no=' . urlencode($reference_no) . '&student_number=' . urlencode($student_number));
    exit;
}
recordLookupAttempt($conn, $clientIp);

$stmt = $conn->prepare("SELECT * FROM requests WHERE reference_no = ? AND student_number = ?");
$stmt->bind_param('ss', $reference_no, $student_number);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($result && $result['request_status'] === 'Pending') {
    $update = $conn->prepare("UPDATE requests SET request_status = 'Cancelled' WHERE id = ? AND request_status = 'Pending'");
    $update->bind_param('i', $result['id']);
    $update->execute();
    $update->close();

    logAudit($conn, $result, 'cancelled', 'Pending', 'Cancelled');

    header('Location: track.php?reference_no=' . urlencode($reference_no) . '&student_number=' . urlencode($student_number) . '&cancelled=1');
    exit;
}

header('Location: track.php?reference_no=' . urlencode($reference_no) . '&student_number=' . urlencode($student_number));
exit;
