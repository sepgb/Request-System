<?php
require_once 'auth_check.php';
require_once '../config/db.php';
require_once '../includes/functions.php';

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminPhoto = $_SESSION['admin_photo'] ?? null;
$nameParts = explode(' ', trim($adminName));
$firstInitial = strtoupper(substr($nameParts[0], 0, 1));
$secondInitial = strtoupper(substr(end($nameParts), 0, 1));
$adminInitial = $firstInitial . $secondInitial;
$currentAdminPage = basename($_SERVER['PHP_SELF'] ?? '');
$initialStatusFilter = trim($_GET['status'] ?? '');

/* ---------------------------------------------------------------
   Top stat cards
   --------------------------------------------------------------- */
$scopeIn = documentScopeInClause($conn);
$scopeWhere = $scopeIn !== null ? " WHERE document_type IN ($scopeIn)" : '';
$scopeAnd = $scopeIn !== null ? " AND document_type IN ($scopeIn)" : '';

$statusCounts = $conn->query("
    SELECT
        SUM(request_status = 'Pending') AS pending,
        SUM(request_status = 'Processing') AS processing,
        SUM(request_status = 'Ready for Pickup') AS ready,
        SUM(request_status = 'Rejected') AS rejected,
        COUNT(*) AS active_total
    FROM requests{$scopeWhere}
")->fetch_assoc();

$pending = (int)($statusCounts['pending'] ?? 0);
$processing = (int)($statusCounts['processing'] ?? 0);
$ready = (int)($statusCounts['ready'] ?? 0);
$rejected = (int)($statusCounts['rejected'] ?? 0);
$activeTotal = (int)($statusCounts['active_total'] ?? 0);

$claimedTotal = (int)($conn->query(
    "SELECT COUNT(*) AS c FROM audit_log WHERE action = 'claimed'{$scopeAnd}"
)->fetch_assoc()['c'] ?? 0);

$submittedTotal = $activeTotal + $claimedTotal;

/* ---------------------------------------------------------------
   Document type breakdown (current + all-time claimed)
   --------------------------------------------------------------- */
$docCounts = [];

$res = $conn->query("SELECT document_type, COUNT(*) AS c FROM requests{$scopeWhere} GROUP BY document_type");
while ($row = $res->fetch_assoc()) {
    $docCounts[$row['document_type']] = ($docCounts[$row['document_type']] ?? 0) + (int)$row['c'];
}

$res = $conn->query("SELECT document_type, COUNT(*) AS c FROM audit_log WHERE action = 'claimed'{$scopeAnd} GROUP BY document_type");
while ($row = $res->fetch_assoc()) {
    $docCounts[$row['document_type']] = ($docCounts[$row['document_type']] ?? 0) + (int)$row['c'];
}

arsort($docCounts);
$maxDocCount = !empty($docCounts) ? max($docCounts) : 0;

/* ---------------------------------------------------------------
   Last 14 days — requests submitted vs. documents claimed
   --------------------------------------------------------------- */
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $days[$d] = ['submitted' => 0, 'claimed' => 0];
}

$res = $conn->query("
    SELECT DATE(date_requested) AS d, COUNT(*) AS c
    FROM requests
    WHERE date_requested >= (CURDATE() - INTERVAL 13 DAY){$scopeAnd}
    GROUP BY DATE(date_requested)
");
while ($row = $res->fetch_assoc()) {
    if (isset($days[$row['d']])) $days[$row['d']]['submitted'] = (int)$row['c'];
}

$res = $conn->query("
    SELECT DATE(created_at) AS d, COUNT(*) AS c
    FROM audit_log
    WHERE action = 'claimed' AND created_at >= (CURDATE() - INTERVAL 13 DAY){$scopeAnd}
    GROUP BY DATE(created_at)
");
while ($row = $res->fetch_assoc()) {
    if (isset($days[$row['d']])) $days[$row['d']]['claimed'] = (int)$row['c'];
}

$maxDayCount = 1;
foreach ($days as $v) {
    $maxDayCount = max($maxDayCount, $v['submitted'], $v['claimed']);
}

/* ---------------------------------------------------------------
   Recent activity
   --------------------------------------------------------------- */
$activityLimit = 5;
$fullActivityLimit = 200; // sane cap for the "see all" popup

$activityWhere = $scopeIn !== null ? "WHERE document_type IN ($scopeIn) " : '';
$fullActivity = $conn->query("SELECT * FROM audit_log {$activityWhere}ORDER BY created_at DESC LIMIT {$fullActivityLimit}");
$allActivityRows = [];
while ($row = $fullActivity->fetch_assoc()) {
    $allActivityRows[] = $row;
}

$activityRows = array_slice($allActivityRows, 0, $activityLimit);
$hasMoreActivity = count($allActivityRows) > $activityLimit;
function renderActivityItem(array $a): void
{
    $isClaimed = $a['action'] === 'claimed';
    echo '<div class="activity-item">';
    echo '<span class="activity-dot' . ($isClaimed ? ' is-claimed' : '') . '"></span>';
    echo '<div class="activity-text">';
    if ($isClaimed) {
        echo '<p><strong>' . htmlspecialchars($a['performed_by_username'] ?? 'Admin') . '</strong>'
            . ' marked <strong>' . htmlspecialchars($a['reference_no']) . '</strong>'
            . ' (' . htmlspecialchars($a['full_name']) . ') as claimed</p>';
    } else {
        echo '<p><strong>' . htmlspecialchars($a['performed_by_username'] ?? 'Admin') . '</strong>'
            . ' changed <strong>' . htmlspecialchars($a['reference_no']) . '</strong>'
            . ' from ' . htmlspecialchars($a['old_status'] ?? '—')
            . ' to ' . htmlspecialchars($a['new_status'] ?? '—') . '</p>';
    }
    echo '<span class="activity-meta">' . date('M j, Y \a\t g:i A', strtotime($a['created_at'])) . '</span>';
    echo '</div></div>';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics | Registrar Admin</title>
    <script>
        (function() {
            try {
                if (localStorage.getItem('urs_admin_theme') === 'light') {
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            } catch (e) {}
        })();
    </script>
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
            <div class="sidebar-section-label">
                General
            </div>

            <nav class="sidebar-nav sidebar-nav-general">
                <a href="statistics.php" class="sidebar-link<?php echo $currentAdminPage === 'statistics.php' ? ' active' : ''; ?>">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M5 19V10M11 19V5M17 19V13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span>Statistics</span>
                </a>
                <?php if (isFullAdmin()): ?>
                    <a href="manage_admins.php" class="sidebar-link<?php echo $currentAdminPage === 'manage_admins.php' ? ' active' : ''; ?>">
                        <svg viewBox="0 0 24 24" fill="none">
                            <circle cx="9" cy="8" r="3.2" stroke="currentColor" stroke-width="1.6" />
                            <path d="M3.5 19c0-3.3 2.5-5.6 5.5-5.6s5.5 2.3 5.5 5.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                            <path d="M15.5 8.5a2.8 2.8 0 1 0 0-5.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                            <path d="M17.5 13.6c2.2.5 3.7 2.3 3.7 5.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                        </svg>
                        <span>Manage Admins</span>
                    </a>
                <?php endif; ?>
            </nav>

            <!-- Status -->
            <div class="sidebar-section-label">Status</div>

            <nav class="sidebar-nav sidebar-nav-status">
                <a href="dashboard.php" class="sidebar-link">
                    <span>All requests</span>
                    <span class="sidebar-count"><?php echo $activeTotal; ?></span>
                </a>
                <a href="dashboard.php?status=Pending" class="sidebar-link">
                    <span>Pending</span>
                    <span class="sidebar-count"><?php echo $pending; ?></span>
                </a>
                <a href="dashboard.php?status=Processing" class="sidebar-link">
                    <span>Processing</span>
                    <span class="sidebar-count"><?php echo $processing; ?></span>
                </a>
                <a href="dashboard.php?status=Ready+for+Pickup" class="sidebar-link">
                    <span>Ready for pickup</span>
                    <span class="sidebar-count"><?php echo $ready; ?></span>
                </a>
                <a href="dashboard.php?status=Rejected" class="sidebar-link">
                    <span>Rejected</span>
                    <span class="sidebar-count"><?php echo $rejected; ?></span>
                </a>
            </nav>
        </aside>

        <!-- MAIN CONTENT -->
        <div class="admin-content">

            <!-- TOPBAR -->
            <header class="admin-topbar">
                <div class="topbar-status-row">
                    <a href="dashboard.php"
                        class="topbar-link"
                        data-status="">
                        <svg viewBox="0 0 24 24" fill="none">
                            <rect
                                x="3.5"
                                y="3.5"
                                width="7"
                                height="7"
                                rx="1.5"
                                stroke="currentColor"
                                stroke-width="1.5" />

                            <rect
                                x="13.5"
                                y="3.5"
                                width="7"
                                height="7"
                                rx="1.5"
                                stroke="currentColor"
                                stroke-width="1.5" />

                            <rect
                                x="3.5"
                                y="13.5"
                                width="7"
                                height="7"
                                rx="1.5"
                                stroke="currentColor"
                                stroke-width="1.5" />

                            <rect
                                x="13.5"
                                y="13.5"
                                width="7"
                                height="7"
                                rx="1.5"
                                stroke="currentColor"
                                stroke-width="1.5" />
                        </svg>

                        <span>Admin Dashboard</span>
                    </a>

                    <!-- Light / dark mode toggle -->
                    <button type="button" class="theme-toggle" id="themeToggle" role="switch"
                        aria-checked="false" aria-label="Switch to light mode" title="Toggle light / dark mode">
                        <span class="theme-toggle-track">
                            <svg class="theme-toggle-icon theme-toggle-icon-sun" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="4.2" stroke="currentColor" stroke-width="1.8" />
                                <path d="M12 2.5V5M12 19V21.5M4.5 12H2M22 12H19.5M5.6 5.6L7.3 7.3M18.4 5.6L16.7 7.3M5.6 18.4L7.3 16.7M18.4 18.4L16.7 16.7"
                                    stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                            <svg class="theme-toggle-icon theme-toggle-icon-moon" viewBox="0 0 24 24" fill="none">
                                <path d="M20.5 14.5A8.5 8.5 0 1 1 9.5 3.5a7 7 0 0 0 11 11Z" stroke="currentColor"
                                    stroke-width="1.8" stroke-linejoin="round" />
                            </svg>
                            <span class="theme-toggle-thumb"></span>
                        </span>
                    </button>

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
                                <path d="M6 9L12 15L18 9" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>

                        <div class="profile-dropdown" id="profileDropdown" hidden>
                            <div class="profile-dropdown-header">
                                <span class="profile-dropdown-avatar" id="dropdownAvatar"><?php echo avatarContent($adminPhoto, $adminInitial, '../'); ?></span>
                                <div>
                                    <span class="profile-dropdown-name"><?php echo htmlspecialchars($adminName); ?></span>
                                    <span class="profile-dropdown-role"><?php echo isFullAdmin() ? 'Full Admin' : 'Document Admin'; ?></span>
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
                                        <path d="M5 20c0-3.6 3.1-6.2 7-6.2s7 2.6 7 6.2" stroke="currentColor"
                                            stroke-width="1.6" stroke-linecap="round" />
                                    </svg>
                                    Edit Profile
                                </button>
                                <a href="logout.php" class="profile-dropdown-item profile-dropdown-item-danger">
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M9 4H6A1.5 1.5 0 0 0 4.5 5.5V18.5A1.5 1.5 0 0 0 6 20H9"
                                            stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                                        <path d="M14 16L18 12L14 8" stroke="currentColor" stroke-width="1.6"
                                            stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M18 12H9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                                    </svg>
                                    Sign Out
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- STATISTICS CONTENT -->
            <main class="stats-page">

                <div class="stats-page-header">
                    <?php if (isFullAdmin()): ?>
                        <h1>Statistics</h1>
                        <p>An overview of document requests across the registrar system.</p>
                    <?php else: ?>
                        <h1>My Statistics</h1>
                        <p>An overview of document requests for<?php echo !empty($_SESSION['admin_doc_scope']) ? ' ' . htmlspecialchars(implode(', ', $_SESSION['admin_doc_scope'])) : '.'; ?></p>
                    <?php endif; ?>
                </div>

                <!-- Stat cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none">
                                <rect x="5" y="3" width="14" height="18" rx="2" stroke="currentColor" stroke-width="1.6" />
                                <path d="M8.5 8H15.5M8.5 12H15.5M8.5 16H13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                            </svg>
                        </span>
                        <div>
                            <span class="stat-card-value"><?php echo $submittedTotal; ?></span>
                            <span class="stat-card-label">Total submitted</span>
                        </div>
                    </div>

                    <div class="stat-card stat-card-pending">
                        <span class="stat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.7" />
                                <path d="M12 7V12L15 14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <div>
                            <span class="stat-card-value"><?php echo $pending; ?></span>
                            <span class="stat-card-label">Pending</span>
                        </div>
                    </div>

                    <div class="stat-card stat-card-processing">
                        <span class="stat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M12 5V12L16.5 14.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M4 12C4 7.6 7.6 4 12 4C15.2 4 18 5.9 19.3 8.6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                                <path d="M20 12C20 16.4 16.4 20 12 20C8.8 20 6 18.1 4.7 15.4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                                <path d="M19.3 5.5V8.6H16.2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M4.7 18.5V15.4H7.8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <div>
                            <span class="stat-card-value"><?php echo $processing; ?></span>
                            <span class="stat-card-label">Processing</span>
                        </div>
                    </div>

                    <div class="stat-card stat-card-ready">
                        <span class="stat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.7" />
                                <path d="M8.5 12L11 14.5L15.5 9.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <div>
                            <span class="stat-card-value"><?php echo $ready; ?></span>
                            <span class="stat-card-label">Ready for pickup</span>
                        </div>
                    </div>

                    <div class="stat-card stat-card-claimed">
                        <span class="stat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M4 12.5L9.5 18L20 6.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <div>
                            <span class="stat-card-value"><?php echo $claimedTotal; ?></span>
                            <span class="stat-card-label">Claimed (all-time)</span>
                        </div>
                    </div>
                </div>

                <div class="stats-row-2col">

                    <!-- Last 14 days chart -->
                    <div class="panel panel-chart">
                        <h2 class="panel-title">Last 14 days</h2>
                        <div class="bar-chart">
                            <?php foreach ($days as $date => $counts): ?>
                                <div class="bar-chart-col">
                                    <div class="bar-chart-bar" style="height:<?php echo max(2, round(($counts['claimed'] / $maxDayCount) * 100)); ?>%; background:#8fe0b4;"
                                        title="Claimed: <?php echo $counts['claimed']; ?>"></div>
                                    <div class="bar-chart-bar" style="height:<?php echo max(2, round(($counts['submitted'] / $maxDayCount) * 100)); ?>%;"
                                        title="Submitted: <?php echo $counts['submitted']; ?>"></div>
                                    <span class="bar-chart-label"><?php echo date('M j', strtotime($date)); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="chart-legend">
                            <span class="chart-legend-item"><span class="chart-legend-swatch" style="background:var(--admin-blue-300);"></span> Submitted</span>
                            <span class="chart-legend-item"><span class="chart-legend-swatch" style="background:#8fe0b4;"></span> Claimed</span>
                        </div>
                    </div>

                    <!-- Document type breakdown -->
                    <div class="panel panel-docs">
                        <h2 class="panel-title">By document type</h2>
                        <?php if (empty($docCounts)): ?>
                            <p class="stats-empty">No requests yet.</p>
                        <?php else: ?>
                            <?php foreach ($docCounts as $type => $count): ?>
                                <div class="doc-breakdown-row">
                                    <div class="doc-breakdown-top">
                                        <span class="doc-breakdown-name"><?php echo htmlspecialchars($type); ?></span>
                                        <span class="doc-breakdown-count"><?php echo $count; ?></span>
                                    </div>
                                    <div class="doc-breakdown-track">
                                        <div class="doc-breakdown-fill" style="width:<?php echo $maxDocCount > 0 ? round(($count / $maxDocCount) * 100) : 0; ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- Recent activity (Full Admin only) -->
                <?php if (isFullAdmin()): ?>
                    <div class="panel panel-activity">
                        <div class="panel-header-row">
                            <h2 class="panel-title">Recent activity</h2>
                            <?php if ($hasMoreActivity): ?>
                                <button type="button" class="panel-see-all" id="openAuditLogModal">
                                    See all recent activity
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M9 6L15 12L9 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($activityRows)): ?>
                            <p class="stats-empty">No activity recorded yet.</p>
                        <?php else: ?>
                            <div class="activity-list">
                                <?php foreach ($activityRows as $a): renderActivityItem($a);
                                endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- AUDIT LOG MODAL (Full Admin only) -->
    <?php if (isFullAdmin()): ?>
        <div class="modal-overlay" id="auditLogOverlay" hidden>
            <div class="audit-log-box" role="dialog" aria-modal="true" aria-labelledby="auditLogTitle">
                <div class="audit-log-header">
                    <div>
                        <h3 id="auditLogTitle">All Recent Activity</h3>
                        <p>Showing the last <?php echo count($allActivityRows); ?> logged actions.</p>
                    </div>
                    <button type="button" class="edit-profile-close" id="auditLogClose" aria-label="Close">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M6 6L18 18M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>

                <div class="audit-log-body">
                    <?php if (empty($allActivityRows)): ?>
                        <p class="stats-empty">No activity recorded yet.</p>
                    <?php else: ?>
                        <div class="activity-list">
                            <?php foreach ($allActivityRows as $a): renderActivityItem($a);
                            endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

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
                            <span class="edit-profile-avatar-role"><?php echo isFullAdmin() ? 'Full Admin' : 'Document Admin'; ?></span> <button type="button" class="edit-profile-photo-btn" id="editProfilePhotoBtn">
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

    <!-- THEME TOGGLE -->
    <script>
        (function() {
            var root = document.documentElement;
            var toggle = document.getElementById('themeToggle');
            if (!toggle) return;

            function reflect(theme) {
                var isLight = theme === 'light';
                toggle.setAttribute('aria-checked', isLight ? 'true' : 'false');
                toggle.setAttribute('aria-label', isLight ? 'Switch to dark mode' : 'Switch to light mode');
            }

            reflect(root.getAttribute('data-theme') === 'light' ? 'light' : 'dark');

            toggle.addEventListener('click', function() {
                var next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
                if (next === 'light') {
                    root.setAttribute('data-theme', 'light');
                } else {
                    root.removeAttribute('data-theme');
                }
                try {
                    localStorage.setItem('urs_admin_theme', next);
                } catch (e) {}
                reflect(next);
            });
        })();
    </script>

</body>

</html>