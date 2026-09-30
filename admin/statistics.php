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

function weekTrend($current, $previous)
{
    $baseline = $previous == 0 ? 1 : $previous;
    $pct = round((($current - $previous) / $baseline) * 100, 1);
    $label = abs($pct) . '%';
    return ['pct' => abs($pct), 'label' => $label, 'up' => $current >= $previous, 'flat' => $current == $previous];
}

function trendClass(array $t): string
{
    return $t['flat'] ? 'is-flat' : ($t['up'] ? 'is-up' : 'is-down');
}

date_default_timezone_set('Asia/Manila');
$todayDow = (int)date('N'); // 1 = Monday ... 7 = Sunday
$mondayThisWeek = date('Y-m-d', strtotime('-' . ($todayDow - 1) . ' days'));
$mondayLastWeek = date('Y-m-d', strtotime($mondayThisWeek . ' -7 days'));

$submittedThisWeek = (int)($conn->query("
    SELECT COUNT(*) AS c FROM requests
    WHERE date_requested >= '{$mondayThisWeek}'{$scopeAnd}
")->fetch_assoc()['c'] ?? 0);

$claimedThisWeek = (int)($conn->query("
    SELECT COUNT(*) AS c FROM audit_log
    WHERE action = 'claimed' AND created_at >= '{$mondayThisWeek}'{$scopeAnd}
")->fetch_assoc()['c'] ?? 0);

$submittedLastWeek = (int)($conn->query("
    SELECT COUNT(*) AS c FROM requests
    WHERE date_requested >= '{$mondayLastWeek}' AND date_requested < '{$mondayThisWeek}'{$scopeAnd}
")->fetch_assoc()['c'] ?? 0);

$claimedLastWeek = (int)($conn->query("
    SELECT COUNT(*) AS c FROM audit_log
    WHERE action = 'claimed' AND created_at >= '{$mondayLastWeek}' AND created_at < '{$mondayThisWeek}'{$scopeAnd}
")->fetch_assoc()['c'] ?? 0);

$rejectedThisWeek = 0;
$rejectedLastWeek = 0;
$rejRes = $conn->query("
    SELECT COALESCE(
        (SELECT MAX(a.created_at) FROM audit_log a
         WHERE a.reference_no = r.reference_no AND a.new_status = 'Rejected'),
        r.date_requested
    ) AS rejected_at
    FROM requests r
    WHERE r.request_status = 'Rejected'{$scopeAnd}
");
while ($rj = $rejRes->fetch_assoc()) {
    $rejectedDay = substr((string)$rj['rejected_at'], 0, 10);
    if ($rejectedDay >= $mondayThisWeek) {
        $rejectedThisWeek++;
    } elseif ($rejectedDay >= $mondayLastWeek) {
        $rejectedLastWeek++;
    }
}

$submittedTrend = weekTrend($submittedThisWeek, $submittedLastWeek);
$claimedTrend = weekTrend($claimedThisWeek, $claimedLastWeek);
$rejectedTrend = weekTrend($rejectedThisWeek, $rejectedLastWeek);

/* ---------------------------------------------------------------
   Top requested documents (daily / weekly)
   --------------------------------------------------------------- */
$topDocLimit = 3;
function docCountsSince($conn, $scopeAnd, $since = null)
{
    $counts = [];
    $reqWhere = "WHERE 1=1" . ($since !== null ? " AND date_requested >= '" . $conn->real_escape_string($since) . "'" : '') . $scopeAnd;
    $logWhere = "WHERE action = 'claimed'" . ($since !== null ? " AND created_at >= '" . $conn->real_escape_string($since) . "'" : '') . $scopeAnd;

    $res = $conn->query("SELECT document_type, COUNT(*) AS c FROM requests {$reqWhere} GROUP BY document_type");
    while ($row = $res->fetch_assoc()) {
        $counts[$row['document_type']] = ($counts[$row['document_type']] ?? 0) + (int)$row['c'];
    }
    $res = $conn->query("SELECT document_type, COUNT(*) AS c FROM audit_log {$logWhere} GROUP BY document_type");
    while ($row = $res->fetch_assoc()) {
        $counts[$row['document_type']] = ($counts[$row['document_type']] ?? 0) + (int)$row['c'];
    }
    arsort($counts);
    return $counts;
}
function topDocs(array $periodCounts, array $allTime, int $limit): array
{
    $top = array_slice($periodCounts, 0, $limit, true);
    foreach ($allTime as $type => $c) {
        if (count($top) >= $limit) break;
        if (!isset($top[$type])) $top[$type] = 0;
    }
    return $top;
}

$allTimeDocs = docCountsSince($conn, $scopeAnd, null);
$docPeriods = [
    'daily'  => topDocs(docCountsSince($conn, $scopeAnd, date('Y-m-d')), $allTimeDocs, $topDocLimit),                        // today
    'weekly' => topDocs(docCountsSince($conn, $scopeAnd, date('Y-m-d', strtotime('-6 days'))), $allTimeDocs, $topDocLimit),  // trailing 7 days
];
$docPeriodEmpty = [
    'daily'  => 'No requests today.',
    'weekly' => 'No requests in the last 7 days.',
];
$docAxisSteps = 5;

function renderDocChart(array $counts, int $steps): void
{
    $max = !empty($counts) ? max($counts) : 0;
    $raw = max(1, $max) / $steps;
    $mag = pow(10, floor(log10($raw)));
    $step = $mag;
    foreach ([1, 2, 2.5, 5, 10] as $m) {
        $step = $m * $mag;
        if ($step >= $raw) break;
    }
    $step = max(1, (int)ceil($step));
    $axisMax = $step * $steps;
?>
    <div class="dchart-scroll">
        <div class="dchart" style="min-width:<?php echo 44 + count($counts) * 76; ?>px;">
            <div class="dchart-plot">
                <?php for ($i = 0; $i <= $steps; $i++): ?>
                    <div class="dchart-tick" style="bottom:<?php echo ($i / $steps) * 100; ?>%;">
                        <span><?php echo $i * $step; ?></span>
                        <i></i>
                    </div>
                <?php endfor; ?>
                <div class="dchart-bars">
                    <?php foreach ($counts as $type => $count): ?>
                        <div class="dchart-col">
                            <div class="dchart-bar" style="height:<?php echo round(($count / $axisMax) * 100, 2); ?>%;">
                                <span><?php echo $count; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="dchart-labels">
                <?php foreach ($counts as $type => $count): ?>
                    <span title="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php
}

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
    <link rel="stylesheet" href="../assets/css/style.css?v=20260928">
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

                    <!-- Notifications (Full Admin only) -->
                    <?php if (isFullAdmin()): ?>
                        <div class="notif-menu" id="notifMenu">
                            <button type="button" class="notif-bell" id="notifTrigger"
                                aria-haspopup="true" aria-expanded="false" aria-label="Notifications">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M6 9.5a6 6 0 1 1 12 0v3.7c0 .5.16 1 .46 1.4L19.5 16.5a1 1 0 0 1-.8 1.6H5.3a1 1 0 0 1-.8-1.6l1.04-1.9c.3-.4.46-.9.46-1.4V9.5Z"
                                        stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                                    <path d="M9.5 19.5a2.5 2.5 0 0 0 5 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                                </svg>
                                <?php if (!empty($activityRows)): ?>
                                    <span class="notif-badge"><?php echo count($activityRows) > 9 ? '9+' : count($activityRows); ?></span>
                                <?php endif; ?>
                            </button>

                            <div class="notif-dropdown" id="notifDropdown" hidden>
                                <div class="notif-dropdown-header">
                                    <span>Notifications</span>
                                </div>
                                <div class="notif-list">
                                    <?php if (empty($activityRows)): ?>
                                        <p class="stats-empty">No activity recorded yet.</p>
                                    <?php else: ?>
                                        <div class="activity-list">
                                            <?php foreach ($activityRows as $a): renderActivityItem($a);
                                            endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($hasMoreActivity): ?>
                                    <button type="button" class="notif-see-all" id="openAuditLogModal">
                                        See all recent activity
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M9 6L15 12L9 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

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
                    <div class="stat-card-hero">
                        <div class="stat-card-hero-top">
                            <span class="stat-card-hero-title">Total Request</span>
                            <span class="stat-card-hero-icon">
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M14 3H7.5A2.5 2.5 0 0 0 5 5.5v13A2.5 2.5 0 0 0 7.5 21h9a2.5 2.5 0 0 0 2.5-2.5V8zM14 3v5h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M9 13h6M9 16.5h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </div>
                        <div class="stat-card-hero-value-row">
                            <span class="stat-card-hero-value"><?php echo $submittedThisWeek; ?></span>
                            <span class="stat-card-hero-trend <?php echo trendClass($submittedTrend); ?>">
                                <svg class="stat-card-hero-trend-arrow" viewBox="0 0 12 12" fill="none">
                                    <path d="M6 2V10M6 2L2.5 5.5M6 2L9.5 5.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <?php echo $submittedTrend['label']; ?>
                            </span>
                        </div>
                        <span class="stat-card-hero-caption">vs. <?php echo number_format($submittedLastWeek); ?> last week</span>
                    </div>

                    <div class="stat-card-hero">
                        <div class="stat-card-hero-top">
                            <span class="stat-card-hero-title">Request Claimed</span>
                            <span class="stat-card-hero-icon">
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M14 3H7.5A2.5 2.5 0 0 0 5 5.5v13A2.5 2.5 0 0 0 7.5 21h9a2.5 2.5 0 0 0 2.5-2.5V8zM14 3v5h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M9 15l2.2 2.2L15.5 12.8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </div>
                        <div class="stat-card-hero-value-row">
                            <span class="stat-card-hero-value"><?php echo $claimedThisWeek; ?></span>
                            <span class="stat-card-hero-trend <?php echo trendClass($claimedTrend); ?>">
                                <svg class="stat-card-hero-trend-arrow" viewBox="0 0 12 12" fill="none">
                                    <path d="M6 2V10M6 2L2.5 5.5M6 2L9.5 5.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <?php echo $claimedTrend['label']; ?>
                            </span>
                        </div>
                        <span class="stat-card-hero-caption">vs. <?php echo number_format($claimedLastWeek); ?> last week</span>
                    </div>

                    <div class="stat-card-hero">
                        <div class="stat-card-hero-top">
                            <span class="stat-card-hero-title">Request Rejected</span>
                            <span class="stat-card-hero-icon">
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M14 3H7.5A2.5 2.5 0 0 0 5 5.5v13A2.5 2.5 0 0 0 7.5 21h9a2.5 2.5 0 0 0 2.5-2.5V8zM14 3v5h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M9.5 12.5l5 5M14.5 12.5l-5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </div>
                        <div class="stat-card-hero-value-row">
                            <span class="stat-card-hero-value"><?php echo $rejectedThisWeek; ?></span>
                            <span class="stat-card-hero-trend <?php echo trendClass($rejectedTrend); ?>">
                                <svg class="stat-card-hero-trend-arrow" viewBox="0 0 12 12" fill="none">
                                    <path d="M6 2V10M6 2L2.5 5.5M6 2L9.5 5.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <?php echo $rejectedTrend['label']; ?>
                            </span>
                        </div>
                        <span class="stat-card-hero-caption">vs. <?php echo number_format($rejectedLastWeek); ?> last week</span>
                    </div>

                    <?php
                    $statusTotal = $pending + $processing + $ready;
                    $donutR = 42;
                    $donutC = 2 * M_PI * $donutR;
                    $donutSegs = [
                        ['label' => 'Pending', 'short' => 'Pending', 'value' => $pending, 'color' => '#e3c589'],
                        ['label' => 'Processing', 'short' => 'Processing', 'value' => $processing, 'color' => '#9dc4ff'],
                        ['label' => 'Ready for pickup', 'short' => 'Ready', 'value' => $ready, 'color' => '#8fe0b4'],
                    ];
                    ?>
                    <div class="stat-card-status">
                        <div class="stat-card-status-summary">
                            <span class="stat-card-status-total"><?php echo $statusTotal; ?></span>
                            <span class="stat-card-status-name">Open Requests</span>
                        </div>
                        <span class="stat-card-status-divider"></span>
                        <div class="stat-card-status-chart">
                            <svg class="stat-card-status-donut" viewBox="0 0 100 100" role="img" aria-label="Open requests by status">
                                <circle class="stat-card-status-donut-bg" cx="50" cy="50" r="<?php echo $donutR; ?>" fill="none" stroke-width="14" />
                                <?php if ($statusTotal > 0): $offset = 0;
                                    foreach ($donutSegs as $seg):
                                        if ($seg['value'] <= 0) continue;
                                        $len = ($seg['value'] / $statusTotal) * $donutC; ?>
                                        <circle cx="50" cy="50" r="<?php echo $donutR; ?>" fill="none" stroke="<?php echo $seg['color']; ?>" stroke-width="14"
                                            stroke-dasharray="<?php echo round($len, 2); ?> <?php echo round($donutC - $len, 2); ?>"
                                            stroke-dashoffset="<?php echo round(-$offset, 2); ?>"
                                            transform="rotate(-90 50 50)">
                                            <title><?php echo $seg['label'] . ': ' . $seg['value']; ?></title>
                                        </circle>
                                <?php $offset += $len;
                                    endforeach;
                                endif; ?>
                            </svg>
                            <div class="stat-card-status-legend">
                                <?php foreach ($donutSegs as $seg): ?>
                                    <span class="stat-card-status-legend-item">
                                        <span class="stat-card-status-swatch" style="background:<?php echo $seg['color']; ?>;"></span>
                                        <?php echo $seg['value'] . ' ' . $seg['label']; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stats-row-2col">

                    <!-- Last 14 days chart -->
                    <div class="panel panel-chart">
                        <h2 class="panel-title">Last 14 days</h2>
                        <?php
                        $chartData = [
                            'labels'    => array_map(function ($d) {
                                return date('M j', strtotime($d));
                            }, array_keys($days)),
                            'submitted' => array_values(array_map(function ($c) {
                                return $c['submitted'];
                            }, $days)),
                            'claimed'   => array_values(array_map(function ($c) {
                                return $c['claimed'];
                            }, $days)),
                        ];
                        ?>
                        <div class="line-chart" id="dayChart" data-chart="<?php echo htmlspecialchars(json_encode($chartData), ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="lc-tooltip" role="status"></div>
                        </div>
                        <div class="chart-legend">
                            <span class="chart-legend-item"><span class="chart-legend-swatch" style="background:var(--admin-blue-300);"></span> Submitted</span>
                            <span class="chart-legend-item"><span class="chart-legend-swatch" style="background:#3fb984;"></span> Claimed</span>
                        </div>
                    </div>

                    <!-- Top requested documents -->
                    <div class="panel panel-docs" id="topDocsPanel">
                        <div class="docs-header">
                            <h2 class="panel-title">Top requested document</h2>
                            <div class="docs-filter">
                                <button type="button" class="docs-filter-btn" id="docsFilterBtn" aria-haspopup="listbox" aria-expanded="false">
                                    <span id="docsFilterLabel">Daily</span>
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M6 9L12 15L18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>
                                <ul class="docs-filter-menu" id="docsFilterMenu" role="listbox" hidden>
                                    <li role="option" class="is-active" aria-selected="true" data-period="daily">Daily</li>
                                    <li role="option" aria-selected="false" data-period="weekly">Weekly</li>
                                </ul>
                            </div>
                        </div>
                        <?php foreach ($docPeriods as $period => $counts): ?>
                            <div class="docs-period" data-period="<?php echo $period; ?>" <?php echo $period !== 'daily' ? 'hidden' : ''; ?>>
                                <?php if (empty($counts)): ?>
                                    <p class="stats-empty"><?php echo $docPeriodEmpty[$period]; ?></p>
                                <?php else: ?>
                                    <?php renderDocChart($counts, $docAxisSteps); ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                </div>

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

    <!-- LAST 14 DAYS LINE CHART -->
    <script>
        (function() {
            var el = document.getElementById('dayChart');
            if (!el) return;
            var data;
            try {
                data = JSON.parse(el.getAttribute('data-chart'));
            } catch (e) {
                return;
            }

            var NS = 'http://www.w3.org/2000/svg';
            var COLORS = {
                submitted: '#5c7fd6',
                claimed: '#3fb984'
            };
            var tip = el.querySelector('.lc-tooltip');
            var svg = null,
                geo = null;

            function mk(name, attrs) {
                var n = document.createElementNS(NS, name);
                for (var k in attrs) n.setAttribute(k, attrs[k]);
                return n;
            }

            function niceTicks(max) {
                var step = 1,
                    cands = [1, 2, 5, 10, 20, 50, 100, 200, 500, 1000];
                for (var i = 0; i < cands.length; i++) {
                    step = cands[i];
                    if (max / step <= 4) break;
                }
                var top = Math.max(step, Math.ceil(max / step) * step),
                    t = [];
                for (var v = 0; v <= top; v += step) t.push(v);
                return t;
            }

            // Smooth, overshoot-free curve (monotone cubic) through the points
            function smoothPath(p) {
                var n = p.length,
                    dx = [],
                    m = [],
                    t = [],
                    i;
                for (i = 0; i < n - 1; i++) {
                    dx[i] = p[i + 1].x - p[i].x;
                    m[i] = (p[i + 1].y - p[i].y) / dx[i];
                }
                t[0] = m[0];
                t[n - 1] = m[n - 2];
                for (i = 1; i < n - 1; i++) {
                    t[i] = (m[i - 1] * m[i] <= 0) ? 0 : (m[i - 1] + m[i]) / 2;
                }
                for (i = 0; i < n - 1; i++) {
                    if (m[i] === 0) {
                        t[i] = 0;
                        t[i + 1] = 0;
                        continue;
                    }
                    var a = t[i] / m[i],
                        b = t[i + 1] / m[i],
                        q = a * a + b * b;
                    if (q > 9) {
                        var tau = 3 / Math.sqrt(q);
                        t[i] = tau * a * m[i];
                        t[i + 1] = tau * b * m[i];
                    }
                }
                var d = 'M' + p[0].x + ' ' + p[0].y;
                for (i = 0; i < n - 1; i++) {
                    d += ' C' + (p[i].x + dx[i] / 3) + ' ' + (p[i].y + t[i] * dx[i] / 3) +
                        ' ' + (p[i + 1].x - dx[i] / 3) + ' ' + (p[i + 1].y - t[i + 1] * dx[i] / 3) +
                        ' ' + p[i + 1].x + ' ' + p[i + 1].y;
                }
                return d;
            }

            function draw() {
                var W = el.clientWidth,
                    H = 230;
                if (!W) return;
                var pad = {
                    l: 30,
                    r: 18,
                    t: 16,
                    b: 30
                };
                var n = data.labels.length;
                var maxV = Math.max(1, Math.max.apply(null, data.submitted.concat(data.claimed)));
                var ticks = niceTicks(maxV);
                var yTop = ticks[ticks.length - 1];
                var iw = W - pad.l - pad.r,
                    ih = H - pad.t - pad.b;

                function X(i) {
                    return pad.l + (n === 1 ? iw / 2 : i * iw / (n - 1));
                }

                function Y(v) {
                    return pad.t + ih - (v / yTop) * ih;
                }

                if (svg) el.removeChild(svg);
                svg = mk('svg', {
                    width: W,
                    height: H,
                    viewBox: '0 0 ' + W + ' ' + H,
                    'class': 'lc-svg'
                });

                var defs = mk('defs', {});
                ['submitted', 'claimed'].forEach(function(k) {
                    var g = mk('linearGradient', {
                        id: 'lcFill-' + k,
                        x1: 0,
                        y1: 0,
                        x2: 0,
                        y2: 1
                    });
                    g.appendChild(mk('stop', {
                        offset: '0%',
                        'stop-color': COLORS[k],
                        'stop-opacity': 0.32
                    }));
                    g.appendChild(mk('stop', {
                        offset: '100%',
                        'stop-color': COLORS[k],
                        'stop-opacity': 0
                    }));
                    defs.appendChild(g);
                });
                svg.appendChild(defs);

                // horizontal grid + y labels
                ticks.forEach(function(v) {
                    svg.appendChild(mk('line', {
                        x1: pad.l,
                        x2: W - pad.r,
                        y1: Y(v),
                        y2: Y(v),
                        'class': 'lc-hgrid'
                    }));
                    var tx = mk('text', {
                        x: pad.l - 8,
                        y: Y(v) + 3.5,
                        'text-anchor': 'end',
                        'class': 'lc-axis'
                    });
                    tx.textContent = v;
                    svg.appendChild(tx);
                });

                // vertical grid + x labels (every day; every 2nd on narrow screens)
                var skip = W < 560 ? 2 : 1;
                for (var i = 0; i < n; i++) {
                    svg.appendChild(mk('line', {
                        x1: X(i),
                        x2: X(i),
                        y1: pad.t,
                        y2: pad.t + ih,
                        'class': 'lc-vgrid'
                    }));
                    if (i % skip === 0) {
                        var lb = mk('text', {
                            x: X(i),
                            y: H - 8,
                            'text-anchor': 'middle',
                            'class': 'lc-axis'
                        });
                        lb.textContent = data.labels[i];
                        svg.appendChild(lb);
                    }
                }

                // series: claimed behind, submitted in front
                var pts = {};
                ['claimed', 'submitted'].forEach(function(k) {
                    pts[k] = data[k].map(function(v, i) {
                        return {
                            x: X(i),
                            y: Y(v)
                        };
                    });
                    var line = smoothPath(pts[k]);
                    var area = line + ' L' + X(n - 1) + ' ' + Y(0) + ' L' + X(0) + ' ' + Y(0) + ' Z';
                    svg.appendChild(mk('path', {
                        d: area,
                        fill: 'url(#lcFill-' + k + ')',
                        stroke: 'none'
                    }));
                    svg.appendChild(mk('path', {
                        d: line,
                        'class': 'lc-line',
                        stroke: COLORS[k]
                    }));
                });

                // hover visuals
                var guide = mk('line', {
                    x1: 0,
                    x2: 0,
                    y1: pad.t,
                    y2: pad.t + ih,
                    'class': 'lc-guide'
                });
                var dotS = mk('circle', {
                    r: 4.5,
                    'class': 'lc-dot',
                    stroke: COLORS.submitted
                });
                var dotC = mk('circle', {
                    r: 4.5,
                    'class': 'lc-dot',
                    stroke: COLORS.claimed
                });
                svg.appendChild(guide);
                svg.appendChild(dotC);
                svg.appendChild(dotS);

                var hit = mk('rect', {
                    x: pad.l - 8,
                    y: 0,
                    width: iw + 16,
                    height: H,
                    fill: 'transparent',
                    'class': 'lc-hit'
                });
                svg.appendChild(hit);
                el.insertBefore(svg, tip);

                geo = {
                    W: W,
                    X: X,
                    pts: pts,
                    guide: guide,
                    dotS: dotS,
                    dotC: dotC,
                    n: n,
                    pad: pad,
                    iw: iw
                };

                function idxFromEvent(e) {
                    var r = svg.getBoundingClientRect();
                    var cx = (e.touches ? e.touches[0].clientX : e.clientX) - r.left;
                    var i = Math.round((cx - pad.l) / (n === 1 ? 1 : iw / (n - 1)));
                    return Math.max(0, Math.min(n - 1, i));
                }

                function onMove(e) {
                    show(idxFromEvent(e));
                }
                hit.addEventListener('mousemove', onMove);
                hit.addEventListener('touchstart', onMove, {
                    passive: true
                });
                hit.addEventListener('touchmove', onMove, {
                    passive: true
                });
                hit.addEventListener('mouseleave', hide);
                hit.addEventListener('touchend', hide);
            }

            function show(i) {
                if (!geo) return;
                var x = geo.X(i),
                    ps = geo.pts.submitted[i],
                    pc = geo.pts.claimed[i];
                geo.guide.setAttribute('x1', x);
                geo.guide.setAttribute('x2', x);
                geo.dotS.setAttribute('cx', x);
                geo.dotS.setAttribute('cy', ps.y);
                geo.dotC.setAttribute('cx', x);
                geo.dotC.setAttribute('cy', pc.y);
                geo.guide.style.opacity = 1;
                geo.dotS.style.opacity = 1;
                geo.dotC.style.opacity = 1;

                tip.innerHTML =
                    '<div class="lc-tip-date">' + data.labels[i] + '</div>' +
                    '<div class="lc-tip-row"><span class="lc-tip-dot" style="background:' + COLORS.submitted + '"></span>Submitted<b>' + data.submitted[i] + '</b></div>' +
                    '<div class="lc-tip-row"><span class="lc-tip-dot" style="background:' + COLORS.claimed + '"></span>Claimed<b>' + data.claimed[i] + '</b></div>';

                var tw = tip.offsetWidth,
                    th = tip.offsetHeight;
                var left = Math.max(tw / 2 + 4, Math.min(geo.W - tw / 2 - 4, x));
                var top = Math.max(th + 16, Math.min(ps.y, pc.y));
                tip.style.left = left + 'px';
                tip.style.top = top + 'px';
                tip.style.opacity = 1;
            }

            function hide() {
                if (!geo) return;
                geo.guide.style.opacity = 0;
                geo.dotS.style.opacity = 0;
                geo.dotC.style.opacity = 0;
                tip.style.opacity = 0;
            }

            draw();
            var raf = null;
            window.addEventListener('resize', function() {
                if (raf) cancelAnimationFrame(raf);
                raf = requestAnimationFrame(draw);
            });
        })();
    </script>

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

    <script>
        (function() {
            var btn = document.getElementById('docsFilterBtn');
            var menu = document.getElementById('docsFilterMenu');
            var label = document.getElementById('docsFilterLabel');
            var panel = document.getElementById('topDocsPanel');
            if (!btn || !menu || !panel) return;

            function setOpen(open) {
                if (open) {
                    menu.removeAttribute('hidden');
                } else {
                    menu.setAttribute('hidden', '');
                }
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

            document.addEventListener('click', function(e) {
                var li = e.target.closest ? e.target.closest('#docsFilterMenu li[data-period]') : null;
                if (li) {
                    var period = li.getAttribute('data-period');
                    menu.querySelectorAll('li').forEach(function(x) {
                        var on = x === li;
                        x.classList.toggle('is-active', on);
                        x.setAttribute('aria-selected', on ? 'true' : 'false');
                    });
                    panel.querySelectorAll('.docs-period').forEach(function(el) {
                        if (el.getAttribute('data-period') === period) {
                            el.removeAttribute('hidden');
                        } else {
                            el.setAttribute('hidden', '');
                        }
                    });
                    label.textContent = li.textContent.trim();
                    setOpen(false);
                    return;
                }
                if (e.target.closest && e.target.closest('#docsFilterBtn')) {
                    setOpen(menu.hasAttribute('hidden'));
                    return;
                }
                setOpen(false);
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') setOpen(false);
            });
        })();
    </script>
</body>

</html>