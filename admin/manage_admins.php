<?php
require_once 'auth_check.php';
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isFullAdmin()) {
    header('Location: statistics.php');
    exit;
}

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminPhoto = $_SESSION['admin_photo'] ?? null;
$nameParts = explode(' ', trim($adminName));
$firstInitial = strtoupper(substr($nameParts[0], 0, 1));
$secondInitial = strtoupper(substr(end($nameParts), 0, 1));
$adminInitial = $firstInitial . $secondInitial;
$currentAdminPage = basename($_SERVER['PHP_SELF'] ?? '');

$allDocTypes = ['Certificate of Registration', 'Certificate of Grades', 'Diploma (Copy / Authentication)'];

/* Sidebar status counts — Full Admin only reaches this page, so unscoped */
$stats = $conn->query("
    SELECT
        SUM(request_status = 'Pending') AS pending,
        SUM(request_status = 'Processing') AS processing,
        SUM(request_status = 'Ready for Pickup') AS ready,
        SUM(request_status = 'Rejected') AS rejected,
        COUNT(*) AS total
    FROM requests
")->fetch_assoc();

$admins = [];
$res = $conn->query("SELECT id, username, full_name, role FROM admin WHERE role = 'document_admin' ORDER BY full_name ASC");
while ($row = $res->fetch_assoc()) {
    $row['scope'] = [];
    $admins[$row['id']] = $row;
}

$scopeRes = $conn->query("SELECT admin_id, document_type FROM admin_document_scope");
while ($s = $scopeRes->fetch_assoc()) {
    if (isset($admins[$s['admin_id']])) {
        $admins[$s['admin_id']]['scope'][] = $s['document_type'];
    }
}

/* Per-admin stats, scoped to each admin's assigned document types */
foreach ($admins as $id => &$a) {
    $stats4 = ['pending' => 0, 'processing' => 0, 'ready' => 0, 'claimed' => 0];

    if (!empty($a['scope'])) {
        $escaped = array_map(function ($v) use ($conn) {
            return "'" . $conn->real_escape_string($v) . "'";
        }, $a['scope']);
        $inClause = implode(',', $escaped);

        $row4 = $conn->query("
            SELECT
                SUM(request_status = 'Pending') AS pending,
                SUM(request_status = 'Processing') AS processing,
                SUM(request_status = 'Ready for Pickup') AS ready
            FROM requests
            WHERE document_type IN ($inClause)
        ")->fetch_assoc();

        $stats4['pending']    = (int)($row4['pending'] ?? 0);
        $stats4['processing'] = (int)($row4['processing'] ?? 0);
        $stats4['ready']      = (int)($row4['ready'] ?? 0);

        $stats4['claimed'] = (int)($conn->query("
            SELECT COUNT(*) AS c FROM audit_log
            WHERE action = 'claimed' AND document_type IN ($inClause)
        ")->fetch_assoc()['c'] ?? 0);
    }

    $a['stats'] = $stats4;
}
unset($a);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins | Registrar Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="admin-body">

    <div class="admin-shell">

        <!-- SIDEBAR -->
        <aside class="admin-sidebar">
            <div class="sidebar-brand">
                <span class="sidebar-brand-icon" aria-hidden="true">
                    <img src="../assets/images/logo.png" alt="" class="sidebar-brand-img">
                </span>
                <div class="univ">
                    <span class="sidebar-brand-text">University of Rizal System</span>
                    <span class="sidebar-office">Registrar Office - Morong</span>
                </div>
            </div>

            <!-- General -->
            <nav class="sidebar-nav sidebar-nav-general">
                <a href="statistics.php" class="sidebar-link<?php echo $currentAdminPage === 'statistics.php' ? ' active' : ''; ?>">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M5 19V10M11 19V5M17 19V13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span>Statistics</span>
                </a>
                <a href="manage_admins.php" class="sidebar-link<?php echo $currentAdminPage === 'manage_admins.php' ? ' active' : ''; ?>">
                    <svg viewBox="0 0 24 24" fill="none">
                        <circle cx="9" cy="8" r="3.2" stroke="currentColor" stroke-width="1.6" />
                        <path d="M3.5 19c0-3.3 2.5-5.6 5.5-5.6s5.5 2.3 5.5 5.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                        <path d="M15.5 8.5a2.8 2.8 0 1 0 0-5.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                        <path d="M17.5 13.6c2.2.5 3.7 2.3 3.7 5.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                    </svg>
                    <span>Manage Admins</span>
                </a>
            </nav>

            <!-- Status -->
            <div class="sidebar-section-label">Status</div>

            <nav class="sidebar-nav sidebar-nav-status">
                <a href="dashboard.php" class="sidebar-link">
                    <span>All requests</span>
                    <span class="sidebar-count"><?php echo (int)$stats['total']; ?></span>
                </a>
                <a href="dashboard.php?status=Pending" class="sidebar-link">
                    <span>Pending</span>
                    <span class="sidebar-count"><?php echo (int)$stats['pending']; ?></span>
                </a>
                <a href="dashboard.php?status=Processing" class="sidebar-link">
                    <span>Processing</span>
                    <span class="sidebar-count"><?php echo (int)$stats['processing']; ?></span>
                </a>
                <a href="dashboard.php?status=Ready+for+Pickup" class="sidebar-link">
                    <span>Ready for pickup</span>
                    <span class="sidebar-count"><?php echo (int)$stats['ready']; ?></span>
                </a>
                <a href="dashboard.php?status=Rejected" class="sidebar-link">
                    <span>Rejected</span>
                    <span class="sidebar-count"><?php echo (int)$stats['rejected']; ?></span>
                </a>
            </nav>
        </aside>

        <!-- MAIN CONTENT -->
        <div class="admin-content">

            <!-- TOPBAR -->
            <header class="admin-topbar">
                <div class="topbar-status-row">
                    <a href="dashboard.php" class="topbar-link">
                        <svg viewBox="0 0 24 24" fill="none">
                            <rect x="3.5" y="3.5" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.5" />
                            <rect x="13.5" y="3.5" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.5" />
                            <rect x="3.5" y="13.5" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.5" />
                            <rect x="13.5" y="13.5" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.5" />
                        </svg>
                        <span>Admin Dashboard</span>
                    </a>

                    <!-- Admin profile -->
                    <div class="topbar-profile" id="profileMenu">
                        <button type="button" class="topbar-profile-trigger" id="profileTrigger"
                            aria-haspopup="true" aria-expanded="false">
                            <span class="sidebar-avatar" id="topbarAvatar"><?php echo avatarContent($adminPhoto, $adminInitial, '../'); ?></span>
                            <div class="sidebar-profile-info">
                                <span class="sidebar-admin-name"><?php echo htmlspecialchars($adminName); ?></span>
                                <span class="sidebar-profile-greeting">Admin</span>
                            </div>
                            <svg class="topbar-profile-chevron" viewBox="0 0 24 24" fill="none">
                                <path d="M6 9L12 15L18 9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>

                        <div class="profile-dropdown" id="profileDropdown" hidden>
                            <div class="profile-dropdown-header">
                                <span class="profile-dropdown-avatar" id="dropdownAvatar"><?php echo avatarContent($adminPhoto, $adminInitial, '../'); ?></span>
                                <div>
                                    <span class="profile-dropdown-name"><?php echo htmlspecialchars($adminName); ?></span>
                                    <span class="profile-dropdown-role">Full Admin</span>
                                </div>
                            </div>
                            <div class="profile-dropdown-office">
                                <span class="profile-dropdown-office-label">Registrar Office</span>
                                <span class="profile-dropdown-office-value">Morong</span>
                            </div>
                            <div class="profile-dropdown-items">
                                <button type="button" class="profile-dropdown-item" id="openEditProfile">
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <circle cx="12" cy="8" r="3.4" stroke="currentColor" stroke-width="1.6" />
                                        <path d="M5 20c0-3.6 3.1-6.2 7-6.2s7 2.6 7 6.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                                    </svg>
                                    Edit Profile
                                </button>
                                <a href="logout.php" class="profile-dropdown-item profile-dropdown-item-danger">
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M9 4H6A1.5 1.5 0 0 0 4.5 5.5V18.5A1.5 1.5 0 0 0 6 20H9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                                        <path d="M14 16L18 12L14 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M18 12H9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                                    </svg>
                                    Sign Out
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- MANAGE ADMINS CONTENT -->
            <main class="stats-page">


                <?php if (empty($admins)): ?>
                    <p class="stats-empty">No Document Admin accounts yet. New ones created at signup will appear here.</p>
                <?php else: ?>
                    <div class="admin-cards">
                        <?php foreach ($admins as $a): ?>
                            <div class="flip-card" data-admin-id="<?php echo $a['id']; ?>">
                                <div class="flip-card-inner">

                                    <!-- FRONT -->
                                    <div class="flip-card-front admin-manage-card">
                                        <div class="admin-manage-header">
                                            <span class="sidebar-avatar admin-manage-avatar">
                                                <?php
                                                $np = explode(' ', trim($a['full_name']));
                                                echo htmlspecialchars(strtoupper(substr($np[0], 0, 1)) . strtoupper(substr(end($np), 0, 1)));
                                                ?>
                                            </span>
                                            <div>
                                                <span class="admin-manage-name"><?php echo htmlspecialchars($a['full_name']); ?></span>
                                                <span class="admin-manage-username">@<?php echo htmlspecialchars($a['username']); ?></span>
                                            </div>
                                        </div>

                                        <div class="admin-manage-stats-grid">
                                            <div class="admin-manage-stat">
                                                <span class="admin-manage-stat-value"><?php echo $a['stats']['pending']; ?></span>
                                                <span class="admin-manage-stat-label">Pending</span>
                                            </div>
                                            <div class="admin-manage-stat">
                                                <span class="admin-manage-stat-value"><?php echo $a['stats']['processing']; ?></span>
                                                <span class="admin-manage-stat-label">Processing</span>
                                            </div>
                                            <div class="admin-manage-stat">
                                                <span class="admin-manage-stat-value"><?php echo $a['stats']['ready']; ?></span>
                                                <span class="admin-manage-stat-label">Ready</span>
                                            </div>
                                            <div class="admin-manage-stat">
                                                <span class="admin-manage-stat-value"><?php echo $a['stats']['claimed']; ?></span>
                                                <span class="admin-manage-stat-label">Claimed</span>
                                            </div>
                                        </div>

                                        <div class="admin-manage-footer">
                                            <div class="admin-manage-assigned">
                                                <span class="admin-manage-assigned-label">Documents Assigned</span>
                                                <div class="admin-manage-assigned-list">
                                                    <?php if (empty($a['scope'])): ?>
                                                        <span class="admin-manage-assigned-empty">None yet</span>
                                                    <?php else: ?>
                                                        <?php foreach ($a['scope'] as $docType): ?>
                                                            <span class="admin-manage-doc-row"><?php echo htmlspecialchars($docType); ?></span>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <button type="button" class="btn-modal btn-modal-save admin-manage-edit-btn flip-open-btn">
                                                Edit
                                            </button>
                                        </div>
                                    </div>

                                    <!-- BACK -->
                                    <div class="flip-card-back admin-manage-card">
                                        <div class="admin-manage-back-header">
                                            <span>Edit Assignment</span>
                                        </div>

                                        <div class="admin-manage-back-alert"></div>

                                        <div class="doc-type-checkboxes admin-manage-back-checkboxes">
                                            <?php foreach ($allDocTypes as $docType): ?>
                                                <label class="doc-type-checkbox">
                                                    <input type="checkbox" class="scope-checkbox" value="<?php echo htmlspecialchars($docType); ?>"
                                                        <?php echo in_array($docType, $a['scope'], true) ? 'checked' : ''; ?>>
                                                    <?php echo htmlspecialchars($docType); ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="admin-manage-back-footer">
                                            <button type="button" class="btn-modal btn-modal-cancel flip-cancel-btn">Cancel</button>
                                            <button type="button" class="btn-modal btn-modal-save flip-save-btn">Save Changes</button>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- EDIT PROFILE MODAL -->
    <div class="modal-overlay" id="editProfileOverlay" hidden>
        <div class="edit-profile-box" role="dialog" aria-modal="true" aria-labelledby="editProfileTitle">
            <div class="edit-profile-header">
                <span class="edit-profile-header-icon">
                    <svg viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="8" r="3.4" stroke="currentColor" stroke-width="1.6" />
                        <path d="M5 20c0-3.6 3.1-6.2 7-6.2s7 2.6 7 6.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                    </svg>
                </span>
                <div class="edit-profile-header-text">
                    <h3 id="editProfileTitle">Edit Profile</h3>
                    <p>Update your account information</p>
                </div>
                <button type="button" class="edit-profile-close" id="editProfileClose" aria-label="Close">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M6 6L18 18M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                    </svg>
                </button>
            </div>

            <form id="editProfileForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <div class="edit-profile-body">
                    <div id="editProfileAlert"></div>

                    <div class="edit-profile-avatar-row">
                        <span class="edit-profile-avatar" id="editProfileAvatar"><?php echo avatarContent($adminPhoto, $adminInitial, '../'); ?></span>
                        <div>
                            <span class="edit-profile-avatar-name" id="editProfileAvatarName"><?php echo htmlspecialchars($adminName); ?></span>
                            <span class="edit-profile-avatar-role">Full Admin</span>
                            <button type="button" class="edit-profile-photo-btn" id="editProfilePhotoBtn">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M12 16V4M12 4L7 9M12 4L17 9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M4 16V18.5A1.5 1.5 0 0 0 5.5 20H18.5A1.5 1.5 0 0 0 20 18.5V16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                                </svg>
                                Change Photo
                            </button>
                            <input type="file" id="editProfilePhotoInput" name="photo" accept="image/png, image/jpeg, image/webp" hidden>
                        </div>
                    </div>

                    <div class="edit-profile-section">
                        <div class="edit-profile-section-label">
                            <svg viewBox="0 0 24 24" fill="none">
                                <rect x="3" y="5" width="18" height="14" rx="2.5" stroke="currentColor" stroke-width="1.5" />
                                <path d="M3 8H21" stroke="currentColor" stroke-width="1.5" />
                            </svg>
                            Account Information
                        </div>
                        <div class="edit-profile-field">
                            <label for="edit_full_name">Full Name</label>
                            <input type="text" id="edit_full_name" name="full_name" value="<?php echo htmlspecialchars($adminName); ?>" required>
                        </div>
                        <div class="edit-profile-field">
                            <label for="edit_username">Username</label>
                            <input type="text" id="edit_username" name="username" value="<?php echo htmlspecialchars($_SESSION['admin_username'] ?? ''); ?>" required>
                            <p class="edit-profile-hint">Your login username. Must be unique across the system.</p>
                        </div>
                    </div>

                    <div class="edit-profile-section">
                        <div class="edit-profile-section-label">
                            <svg viewBox="0 0 24 24" fill="none">
                                <rect x="5" y="10.5" width="14" height="9.5" rx="2" stroke="currentColor" stroke-width="1.5" />
                                <path d="M8 10.5V7.5A4 4 0 0 1 16 7.5V10.5" stroke="currentColor" stroke-width="1.5" />
                            </svg>
                            Change Password
                        </div>
                        <div class="edit-profile-field">
                            <label for="edit_current_password">Current Password</label>
                            <input type="password" id="edit_current_password" name="current_password" autocomplete="off">
                        </div>
                        <div class="edit-profile-field">
                            <label for="edit_new_password">New Password</label>
                            <input type="password" id="edit_new_password" name="new_password" autocomplete="new-password" minlength="8">
                        </div>
                        <div class="edit-profile-field">
                            <label for="edit_confirm_password">Confirm New Password</label>
                            <input type="password" id="edit_confirm_password" name="confirm_password" autocomplete="new-password" minlength="8">
                            <p class="edit-profile-hint">Leave the password fields blank if you don't want to change your password.</p>
                        </div>
                    </div>
                </div>

                <div class="edit-profile-footer">
                    <button type="button" class="btn-modal btn-modal-cancel" id="editProfileCancel">Cancel</button>
                    <button type="submit" class="btn-modal btn-modal-save" id="editProfileSave">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- TOAST NOTIFICATIONS -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        window.CSRF_TOKEN = <?php echo json_encode(csrf_token()); ?>;
    </script>
    <script src="../assets/js/admin.js"></script>
    <script src="../assets/js/manage_admins.js"></script>

</body>

</html>