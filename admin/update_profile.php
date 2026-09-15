<?php
require_once 'auth_check.php';
require_once '../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$adminId = (int)$_SESSION['admin_id'];

$fullName        = trim($_POST['full_name'] ?? '');
$username        = trim($_POST['username'] ?? '');
$currentPassword = $_POST['current_password'] ?? '';
$newPassword     = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if ($fullName === '' || $username === '') {
    echo json_encode(['success' => false, 'message' => 'Full name and username are required.']);
    exit;
}

if (!preg_match('/^[A-Za-z0-9._-]{4,50}$/', $username)) {
    echo json_encode(['success' => false, 'message' => 'Username must be 4-50 characters, using only letters, numbers, dot, underscore or dash.']);
    exit;
}

// Username must stay unique (excluding this admin's own row)
$check = $conn->prepare("SELECT id FROM admin WHERE username = ? AND id != ?");
$check->bind_param('si', $username, $adminId);
$check->execute();
$taken = $check->get_result()->num_rows > 0;
$check->close();

if ($taken) {
    echo json_encode(['success' => false, 'message' => 'That username is already taken.']);
    exit;
}

// Fetch current stored password hash + photo (needed for verification / cleanup)
$stmt = $conn->prepare("SELECT password, photo FROM admin WHERE id = ?");
$stmt->bind_param('i', $adminId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Admin account not found.']);
    exit;
}

$newHash = null;

if ($currentPassword !== '' || $newPassword !== '' || $confirmPassword !== '') {
    if ($currentPassword === '') {
        echo json_encode(['success' => false, 'message' => 'Enter your current password to set a new one.']);
        exit;
    }
    if (!password_verify($currentPassword, $row['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }
    if (strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters.']);
        exit;
    }
    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'New password and confirmation do not match.']);
        exit;
    }
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
}

// ---------------------------------------------------------------
// Profile photo (optional)
// ---------------------------------------------------------------
$newPhotoPath = null; // relative path from site root; null = not being changed this save

if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['photo'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Photo upload failed. Please try again.']);
        exit;
    }

    $maxBytes = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $maxBytes) {
        echo json_encode(['success' => false, 'message' => 'Photo must be smaller than 2MB.']);
        exit;
    }

    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowedMime[$mime]) || @getimagesize($file['tmp_name']) === false) {
        echo json_encode(['success' => false, 'message' => 'Photo must be a JPG, PNG, or WEBP image.']);
        exit;
    }

    $ext = $allowedMime[$mime];
    $uploadDir = '../assets/uploads/admin_photos/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'admin_' . $adminId . '_' . time() . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        echo json_encode(['success' => false, 'message' => 'Could not save photo. Please try again.']);
        exit;
    }

    // Clean up the old photo file, if any
    if (!empty($row['photo'])) {
        $oldPath = '../' . $row['photo'];
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    $newPhotoPath = 'assets/uploads/admin_photos/' . $filename;
}

// ---------------------------------------------------------------
// Persist changes
// ---------------------------------------------------------------
$setParts   = ['full_name = ?', 'username = ?'];
$bindTypes  = 'ss';
$bindValues = [$fullName, $username];

if ($newHash !== null) {
    $setParts[]   = 'password = ?';
    $bindTypes   .= 's';
    $bindValues[] = $newHash;
}

if ($newPhotoPath !== null) {
    $setParts[]   = 'photo = ?';
    $bindTypes   .= 's';
    $bindValues[] = $newPhotoPath;
}

$bindTypes   .= 'i';
$bindValues[] = $adminId;

$sql    = 'UPDATE admin SET ' . implode(', ', $setParts) . ' WHERE id = ?';
$update = $conn->prepare($sql);
$update->bind_param($bindTypes, ...$bindValues);

if ($update->execute()) {
    $update->close();

    $_SESSION['admin_name']     = $fullName;
    $_SESSION['admin_username'] = $username;
    if ($newPhotoPath !== null) {
        $_SESSION['admin_photo'] = $newPhotoPath;
    }

    $nameParts     = explode(' ', trim($fullName));
    $firstInitial  = strtoupper(substr($nameParts[0], 0, 1));
    $secondInitial = strtoupper(substr(end($nameParts), 0, 1));

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated.',
        'data' => [
            'full_name' => $fullName,
            'username'  => $username,
            'initials'  => $firstInitial . $secondInitial,
            'photo'     => $newPhotoPath !== null ? $newPhotoPath : ($row['photo'] ?? null)
        ]
    ]);
} else {
    $update->close();
    echo json_encode(['success' => false, 'message' => 'Update failed: ' . $conn->error]);
}
