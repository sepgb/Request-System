<?php
require_once 'auth_check.php';
require_once '../config/db.php';
require_once '../includes/functions.php';

date_default_timezone_set('Asia/Manila');

$scopeIn = documentScopeInClause($conn);
$scopeAnd = $scopeIn !== null ? " AND document_type IN ($scopeIn)" : '';

$show = (($_GET['show'] ?? '') === 'not-ready') ? 'not-ready' : 'all';
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$scheduleRes = $conn->query("
    SELECT id, reference_no, student_number, full_name, document_type, year_level, semester, request_status, claim_date
    FROM requests
    WHERE claim_date IS NOT NULL
      AND request_status IN ('Pending', 'Processing', 'Ready for Pickup'){$scopeAnd}
    ORDER BY claim_date ASC, full_name ASC
");

$groups = [];
$countAll = 0;
$countNotReady = 0;
while ($r = $scheduleRes->fetch_assoc()) {
    $isReady = $r['request_status'] === 'Ready for Pickup';
    $d = $r['claim_date'];

    if (!isset($groups[$d])) {
        $groups[$d] = ['rows' => [], 'total' => 0, 'ready' => 0, 'notReady' => 0];
    }

    $countAll++;
    $groups[$d]['total']++;
    if ($isReady) {
        $groups[$d]['ready']++;
    } else {
        $groups[$d]['notReady']++;
        $countNotReady++;
    }

    if ($show === 'all' || !$isReady) {
        $groups[$d]['rows'][] = $r;
    }
}

foreach ($groups as $d => $g) {
    if (empty($g['rows'])) {
        unset($groups[$d]);
    }
}

function scheduleDayTag(string $date, string $today, string $tomorrow): ?array
{
    if ($date < $today) return ['Past date', 'schedule-tag-past'];
    if ($date === $today) return ['Today', 'schedule-tag-today'];
    if ($date === $tomorrow) return ['Tomorrow', 'schedule-tag-soon'];
    return null;
}

function schedulePlural(int $n, string $word): string
{
    return $n . ' ' . $word . ($n === 1 ? '' : 's');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Schedule | Registrar Admin</title>
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

            <main class="admin-main schedule-main">
                <div class="schedule-page">

                    <div class="schedule-toolbar">
                        <h1 class="schedule-title">Claim Schedule</h1>

                        <nav class="schedule-tabs" aria-label="Schedule view">
                            <a href="claim_schedule.php" class="schedule-tab<?php echo $show === 'all' ? ' is-active' : ''; ?>">
                                All scheduled
                                <span class="schedule-tab-count"><?php echo $countAll; ?></span>
                            </a>
                            <a href="claim_schedule.php?show=not-ready" class="schedule-tab<?php echo $show === 'not-ready' ? ' is-active' : ''; ?>">
                                Not ready
                                <span class="schedule-tab-count"><?php echo $countNotReady; ?></span>
                            </a>
                        </nav>
                    </div>

                    <?php if (empty($groups)): ?>
                        <p class="schedule-empty">
                            <?php echo $countAll === 0
                                ? 'No requests are scheduled yet'
                                : 'Everything scheduled is ready for pickup'; ?>
                        </p>
                    <?php else: ?>
                        <?php $dayIndex = 0; ?>
                        <?php foreach ($groups as $date => $g): ?>
                            <?php
                            $dayDate = new DateTime($date);
                            $tag = scheduleDayTag($date, $today, $tomorrow);
                            $pct = $g['total'] > 0 ? (int)round($g['ready'] / $g['total'] * 100) : 0;
                            ?>
                            <section class="schedule-day" style="--i: <?php echo min($dayIndex, 5); ?>">
                                <header class="schedule-day-head">
                                    <div class="schedule-leaf" aria-hidden="true">
                                        <span class="schedule-leaf-month"><?php echo $dayDate->format('M'); ?></span>
                                        <span class="schedule-leaf-day"><?php echo $dayDate->format('j'); ?></span>
                                    </div>

                                    <div class="schedule-day-title">
                                        <h2 class="schedule-day-date"><?php echo $dayDate->format('l, F j, Y'); ?></h2>
                                        <p class="schedule-day-meta">
                                            <?php if ($tag): ?>
                                                <span class="schedule-day-flag <?php echo $tag[1]; ?>"><?php echo $tag[0]; ?></span> &middot;
                                            <?php endif; ?>
                                            <?php echo schedulePlural($g['total'], 'request'); ?>
                                        </p>
                                    </div>

                                    <div class="schedule-progress">
                                        <span class="schedule-progress-text"><?php echo $g['ready']; ?> of <?php echo $g['total']; ?> ready</span>
                                        <span class="schedule-bar" aria-hidden="true">
                                            <span class="schedule-bar-fill" style="width: <?php echo $pct; ?>%"></span>
                                        </span>
                                    </div>
                                </header>

                                <table class="schedule-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Name</th>
                                            <th scope="col">Document</th>
                                            <th scope="col">Reference no.</th>
                                            <th scope="col">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($g['rows'] as $r): ?>
                                            <?php
                                            $isReady = $r['request_status'] === 'Ready for Pickup';
                                            $isLate = !$isReady && $date <= $today;
                                            $stateClass = $isReady ? 'is-ready' : ($isLate ? 'is-late' : 'is-wait');
                                            $docSubParts = array_filter(
                                                [$r['year_level'], $r['semester']],
                                                function ($v) {
                                                    return $v !== null && $v !== '';
                                                }
                                            );
                                            $docInlineParts = array_merge([$r['document_type']], $docSubParts);
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="schedule-person">
                                                        <span class="schedule-name" title="<?php echo htmlspecialchars($r['full_name'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($r['full_name']); ?></span>
                                                        <span class="schedule-sub"><?php echo htmlspecialchars($r['student_number']); ?></span>
                                                        <span class="schedule-inline">
                                                            <span class="schedule-doc-inline"><?php echo htmlspecialchars(implode(' · ', $docInlineParts)); ?></span>
                                                            <a class="schedule-ref-inline" href="dashboard.php?q=<?php echo urlencode($r['reference_no']); ?>"><?php echo htmlspecialchars($r['reference_no']); ?></a>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="schedule-person">
                                                        <span class="schedule-doc-name" title="<?php echo htmlspecialchars($r['document_type'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($r['document_type']); ?></span>
                                                        <?php if ($docSubParts): ?>
                                                            <span class="schedule-sub"><?php echo htmlspecialchars(implode(' · ', $docSubParts)); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <a class="schedule-ref" href="dashboard.php?q=<?php echo urlencode($r['reference_no']); ?>"><?php echo htmlspecialchars($r['reference_no']); ?></a>
                                                </td>
                                                <td>
                                                    <span class="schedule-state <?php echo $stateClass; ?>">
                                                        <span class="schedule-dot" aria-hidden="true"></span>
                                                        <?php echo htmlspecialchars($r['request_status']); ?>
                                                        <?php if ($isLate): ?>
                                                            <span class="schedule-late"><?php echo $date === $today ? 'Due today' : 'Overdue'; ?></span>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </section>
                            <?php $dayIndex++; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>

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