<?php
require_once 'auth_check.php';
require_once '../config/db.php';
require_once '../includes/functions.php';

$initialStatusFilter = trim($_GET['status'] ?? '');

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
$todayDow = (int)date('N');
$mondayThisWeek = date('Y-m-d', strtotime('-' . ($todayDow - 1) . ' days'));
$mondayLastWeek = date('Y-m-d', strtotime($mondayThisWeek . ' -7 days'));

$submittedThisWeekLive = (int)($conn->query("
    SELECT COUNT(*) AS c FROM requests
    WHERE date_requested >= '{$mondayThisWeek}'{$scopeAnd}
")->fetch_assoc()['c'] ?? 0);

$submittedLastWeekLive = (int)($conn->query("
    SELECT COUNT(*) AS c FROM requests
    WHERE date_requested >= '{$mondayLastWeek}' AND date_requested < '{$mondayThisWeek}'{$scopeAnd}
")->fetch_assoc()['c'] ?? 0);

$submittedThisWeekGone = (int)($conn->query("
    SELECT COUNT(*) AS c FROM audit_log
    WHERE action IN ('claimed', 'deleted') AND date_requested >= '{$mondayThisWeek}'{$scopeAnd}
")->fetch_assoc()['c'] ?? 0);

$submittedLastWeekGone = (int)($conn->query("
    SELECT COUNT(*) AS c FROM audit_log
    WHERE action IN ('claimed', 'deleted') AND date_requested >= '{$mondayLastWeek}' AND date_requested < '{$mondayThisWeek}'{$scopeAnd}
")->fetch_assoc()['c'] ?? 0);

$submittedThisWeek = $submittedThisWeekLive + $submittedThisWeekGone;
$submittedLastWeek = $submittedLastWeekLive + $submittedLastWeekGone;

$claimedThisWeek = (int)($conn->query("
    SELECT COUNT(*) AS c FROM audit_log
    WHERE action = 'claimed' AND created_at >= '{$mondayThisWeek}'{$scopeAnd}
")->fetch_assoc()['c'] ?? 0);

$claimedLastWeek = (int)($conn->query("
    SELECT COUNT(*) AS c FROM audit_log
    WHERE action = 'claimed' AND created_at >= '{$mondayLastWeek}' AND created_at < '{$mondayThisWeek}'{$scopeAnd}
")->fetch_assoc()['c'] ?? 0);

$rejectedThisWeek = 0;
$rejectedLastWeek = 0;

$rejResLive = $conn->query("
    SELECT COALESCE(
        (SELECT MAX(a.created_at) FROM audit_log a
         WHERE a.reference_no = r.reference_no AND a.new_status = 'Rejected'),
        r.date_requested
    ) AS rejected_at
    FROM requests r
    WHERE r.request_status = 'Rejected'{$scopeAnd}
");
while ($rj = $rejResLive->fetch_assoc()) {
    $rejectedDay = substr((string)$rj['rejected_at'], 0, 10);
    if ($rejectedDay >= $mondayThisWeek) {
        $rejectedThisWeek++;
    } elseif ($rejectedDay >= $mondayLastWeek) {
        $rejectedLastWeek++;
    }
}

$rejResGone = $conn->query("
    SELECT COALESCE(
        (SELECT MAX(a2.created_at) FROM audit_log a2
         WHERE a2.reference_no = a.reference_no AND a2.new_status = 'Rejected'),
        a.date_requested
    ) AS rejected_at
    FROM audit_log a
    WHERE a.action = 'deleted' AND a.old_status = 'Rejected'{$scopeAnd}
");
while ($rj = $rejResGone->fetch_assoc()) {
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

$topDocLimit = 3;

function docCountsSince($conn, $scopeAnd, $since = null)
{
    $counts = [];
    $reqWhere = "WHERE 1=1" . ($since !== null ? " AND date_requested >= '" . $conn->real_escape_string($since) . "'" : '') . $scopeAnd;
    $logWhere = "WHERE action IN ('claimed', 'deleted')" . ($since !== null ? " AND COALESCE(date_requested, created_at) >= '" . $conn->real_escape_string($since) . "'" : '') . $scopeAnd;

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
    'daily'  => topDocs(docCountsSince($conn, $scopeAnd, date('Y-m-d')), $allTimeDocs, $topDocLimit),
    'weekly' => topDocs(docCountsSince($conn, $scopeAnd, date('Y-m-d', strtotime('-6 days'))), $allTimeDocs, $topDocLimit),
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
    if (isset($days[$row['d']])) $days[$row['d']]['submitted'] += (int)$row['c'];
}

$res = $conn->query("
    SELECT DATE(date_requested) AS d, COUNT(*) AS c
    FROM audit_log
    WHERE action IN ('claimed', 'deleted') AND date_requested >= (CURDATE() - INTERVAL 13 DAY){$scopeAnd}
    GROUP BY DATE(date_requested)
");
while ($row = $res->fetch_assoc()) {
    if (isset($days[$row['d']])) $days[$row['d']]['submitted'] += (int)$row['c'];
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
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
</head>

<body class="admin-body">

    <div class="admin-shell">

        <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

        <div class="admin-content">

            <?php include __DIR__ . '/../includes/admin_topbar.php'; ?>

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

                    <div class="panel panel-docs" id="topDocsPanel">
                        <div class="docs-header">
                            <h2 class="panel-title">Top requested document</h2>
                            <div class="docs-filter">
                                <button type="button" class="docs-filter-btn" id="docsFilterBtn" aria-haspopup="true" aria-expanded="false">
                                    <span id="docsFilterLabel">Daily</span>
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>
                                <ul class="docs-filter-menu" id="docsFilterMenu" role="listbox" hidden>
                                    <li data-period="daily" class="is-active" role="option" aria-selected="true">Daily</li>
                                    <li data-period="weekly" role="option" aria-selected="false">Weekly</li>
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

    <div class="toast-container" id="toastContainer"></div>

    <script>
        window.CSRF_TOKEN = <?php echo json_encode(csrf_token()); ?>;
    </script>
    <script src="../assets/js/admin.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>

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
            var panel = document.getElementById('topDocsPanel');
            if (!panel) return;
            var btn = document.getElementById('docsFilterBtn');
            var label = document.getElementById('docsFilterLabel');
            var menu = document.getElementById('docsFilterMenu');
            var items = menu.querySelectorAll('li[data-period]');

            function closeMenu() {
                menu.hidden = true;
                btn.setAttribute('aria-expanded', 'false');
            }

            function show(period, text) {
                label.textContent = text;
                items.forEach(function(li) {
                    var active = li.getAttribute('data-period') === period;
                    li.classList.toggle('is-active', active);
                    li.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                panel.querySelectorAll('.docs-period').forEach(function(el) {
                    if (el.getAttribute('data-period') === period) {
                        el.removeAttribute('hidden');
                    } else {
                        el.setAttribute('hidden', '');
                    }
                });
            }

            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var isOpen = !menu.hidden;
                if (isOpen) {
                    closeMenu();
                } else {
                    menu.hidden = false;
                    btn.setAttribute('aria-expanded', 'true');
                }
            });

            items.forEach(function(li) {
                li.addEventListener('click', function() {
                    show(li.getAttribute('data-period'), li.textContent.trim());
                    closeMenu();
                });
            });

            document.addEventListener('click', function(e) {
                if (!menu.hidden && !e.target.closest('.docs-filter')) {
                    closeMenu();
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !menu.hidden) closeMenu();
            });
        })();
    </script>
</body>

</html>