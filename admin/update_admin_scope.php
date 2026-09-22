<?php
require_once 'auth_check.php';
require_once '../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isFullAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Only a Full Admin can manage document scopes.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    echo json_encode(['success' => false, 'message' => 'Your session expired. Please refresh the page.']);
    exit;
}

$targetId = (int)($_POST['admin_id'] ?? 0);
$allowedDocTypes = ['Certificate of Registration', 'Certificate of Grades', 'Diploma (Copy / Authentication)'];
$selectedDocTypes = array_values(array_intersect($_POST['doc_types'] ?? [], $allowedDocTypes));

if ($targetId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid admin.']);
    exit;
}

$check = $conn->prepare("SELECT id, role FROM admin WHERE id = ?");
$check->bind_param('i', $targetId);
$check->execute();
$target = $check->get_result()->fetch_assoc();
$check->close();

if (!$target) {
    echo json_encode(['success' => false, 'message' => 'Admin account not found.']);
    exit;
}

if ($target['role'] !== 'document_admin') {
    echo json_encode(['success' => false, 'message' => 'Only Document Admin accounts have a document scope.']);
    exit;
}

$del = $conn->prepare("DELETE FROM admin_document_scope WHERE admin_id = ?");
$del->bind_param('i', $targetId);
$del->execute();
$del->close();

if (!empty($selectedDocTypes)) {
    $ins = $conn->prepare("INSERT INTO admin_document_scope (admin_id, document_type) VALUES (?, ?)");
    foreach ($selectedDocTypes as $docType) {
        $ins->bind_param('is', $targetId, $docType);
        $ins->execute();
    }
    $ins->close();
}

echo json_encode([
    'success' => true,
    'message' => 'Scope updated.',
    'data' => ['admin_id' => $targetId, 'doc_types' => $selectedDocTypes]
]);
