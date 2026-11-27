<?php
require_once __DIR__ . '/functions.php';

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminPhoto = $_SESSION['admin_photo'] ?? null;
$nameParts = explode(' ', trim($adminName));
$firstInitial = strtoupper(substr($nameParts[0], 0, 1));
$secondInitial = strtoupper(substr(end($nameParts), 0, 1));
$adminInitial = $firstInitial . $secondInitial;

$scopeIn = documentScopeInClause($conn);

$fullActivityLimit = 200;

$activityWhere = $scopeIn !== null ? "WHERE is_dismissed = 0 AND document_type IN ($scopeIn) " : 'WHERE is_dismissed = 0 ';
$activityAnd = $scopeIn !== null ? "AND document_type IN ($scopeIn) " : '';

if (!empty($_GET['read_notif'])) {
    $readId = (int)$_GET['read_notif'];
    if ($readId > 0) {
        $rstmt = $conn->prepare("UPDATE audit_log SET is_read = 1 WHERE id = ? {$activityAnd}");
        $rstmt->bind_param('i', $readId);
        $rstmt->execute();
        $rstmt->close();
    }
}

$fullActivity = $conn->query("SELECT * FROM audit_log {$activityWhere}ORDER BY created_at DESC LIMIT {$fullActivityLimit}");
$allActivityRows = [];
while ($row = $fullActivity->fetch_assoc()) {
    $allActivityRows[] = $row;
}

$activityRows = $allActivityRows;
$notifToday = date('Y-m-d');
$notifGroups = ['Today' => [], 'Earlier' => []];
foreach ($allActivityRows as $act) {
    $notifGroups[date('Y-m-d', strtotime($act['created_at'])) === $notifToday ? 'Today' : 'Earlier'][] = $act;
}

$unreadWhere = $activityWhere . 'AND is_read = 0';
$unreadCount = (int)($conn->query("SELECT COUNT(*) AS c FROM audit_log {$unreadWhere}")->fetch_assoc()['c'] ?? 0);

function renderActivityItem(array $a): void
{
    $isClaimed = $a['action'] === 'claimed';
    $isDeleted = $a['action'] === 'deleted';
    $isUnread = empty($a['is_read']);
    $canOpen = !in_array($a['action'], ['claimed', 'deleted'], true);

    $classes = 'activity-item';
    if ($isUnread) {
        $classes .= ' is-unread';
    }

    echo '<div class="' . $classes . '"'
        . ' data-notif-id="' . (int)$a['id'] . '"'
        . ' data-read="' . ($isUnread ? '0' : '1') . '"';
    if ($canOpen) {
        echo ' data-reference="' . htmlspecialchars($a['reference_no'], ENT_QUOTES, 'UTF-8') . '"';
    }
    echo ' title="Double-click to ' . ($canOpen ? 'open this request' : 'mark as read') . '">';

    echo '<input type="checkbox" class="notif-check" aria-label="Select notification">';
    echo '<span class="activity-dot' . ($isClaimed ? ' is-claimed' : ($isDeleted ? ' is-deleted' : '')) . '"></span>';
    echo '<div class="activity-text">';
    if ($isClaimed) {
        echo '<p><strong>' . htmlspecialchars($a['performed_by_username'] ?? 'Admin') . '</strong>'
            . ' marked <strong>' . htmlspecialchars($a['reference_no']) . '</strong>'
            . ' (' . htmlspecialchars($a['full_name']) . ') as claimed</p>';
    } elseif ($isDeleted) {
        echo '<p><strong>' . htmlspecialchars($a['performed_by_username'] ?? 'Admin') . '</strong>'
            . ' deleted <strong>' . htmlspecialchars($a['reference_no']) . '</strong>'
            . ' (' . htmlspecialchars($a['full_name']) . ')</p>';
    } else {
        echo '<p><strong>' . htmlspecialchars($a['performed_by_username'] ?? 'Admin') . '</strong>'
            . ' changed <strong>' . htmlspecialchars($a['reference_no']) . '</strong>'
            . ' from ' . htmlspecialchars($a['old_status'] ?? '—')
            . ' to ' . htmlspecialchars($a['new_status'] ?? '—') . '</p>';
    }
    echo '<span class="activity-meta">' . date('M j, Y \\a\\t g:i A', strtotime($a['created_at'])) . '</span>';
    echo '</div></div>';
}
?>
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

        <button type="button" class="theme-toggle" id="themeToggle" role="switch"
            aria-checked="false" aria-label="Switch to light mode" title="Toggle light / dark mode">
            <svg class="theme-toggle-icon theme-toggle-icon-sun" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="4.2" stroke="currentColor" stroke-width="1.8" />
                <path d="M12 2.5V5M12 19V21.5M4.5 12H2M22 12H19.5M5.6 5.6L7.3 7.3M18.4 5.6L16.7 7.3M5.6 18.4L7.3 16.7M18.4 18.4L16.7 16.7"
                    stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
            <svg class="theme-toggle-icon theme-toggle-icon-moon" viewBox="0 0 24 24" fill="none">
                <path d="M20.5 14.5A8.5 8.5 0 1 1 9.5 3.5a7 7 0 0 0 11 11Z" stroke="currentColor"
                    stroke-width="1.8" stroke-linejoin="round" />
            </svg>
        </button>

        <?php if (isFullAdmin()): ?>
            <div class="notif-menu" id="notifMenu">
                <button type="button" class="notif-bell" id="notifTrigger"
                    aria-haspopup="true" aria-expanded="false" aria-label="Notifications">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M6 9.5a6 6 0 1 1 12 0v3.7c0 .5.16 1 .46 1.4L19.5 16.5a1 1 0 0 1-.8 1.6H5.3a1 1 0 0 1-.8-1.6l1.04-1.9c.3-.4.46-.9.46-1.4V9.5Z"
                            stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                        <path d="M9.5 19.5a2.5 2.5 0 0 0 5 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                    </svg>
                    <?php if ($unreadCount > 0): ?>
                        <span class="notif-badge" id="notifBadge"><?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?></span>
                    <?php endif; ?>
                </button>

                <div class="notif-dropdown" id="notifDropdown" hidden>
                    <div class="notif-dropdown-header">
                        <span>Notifications</span>
                        <div class="notif-more-wrap">
                            <button type="button" class="notif-more" id="notifMoreBtn"
                                aria-haspopup="true" aria-expanded="false" aria-label="More options">
                                <svg viewBox="0 0 24 24" fill="currentColor">
                                    <circle cx="5" cy="12" r="1.8" />
                                    <circle cx="12" cy="12" r="1.8" />
                                    <circle cx="19" cy="12" r="1.8" />
                                </svg>
                            </button>
                            <div class="notif-more-menu" id="notifMoreMenu" hidden>
                                <button type="button" class="notif-menu-item" id="notifMarkAllRead" <?php echo $unreadCount > 0 ? '' : ' disabled'; ?>>
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M5 12.5L9.5 17L19 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    Mark all as read
                                </button>
                                <button type="button" class="notif-menu-item is-danger" id="notifDeleteMode" <?php echo empty($activityRows) ? ' disabled' : ''; ?>>
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 002 2h6a2 2 0 002-2l1-12M9 7V4h6v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    Delete notifications
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="notif-tabs" role="tablist">
                        <button type="button" class="notif-tab is-active" data-filter="all" role="tab" aria-selected="true">All</button>
                        <button type="button" class="notif-tab" data-filter="unread" role="tab" aria-selected="false">Unread</button>
                        <label class="notif-select-all" id="notifSelectAllWrap" hidden>
                            <input type="checkbox" id="notifSelectAll">
                            <span>All</span>
                        </label>
                    </div>
                    <div class="notif-list" id="notifList">
                        <?php if (empty($activityRows)): ?>
                            <p class="stats-empty" id="notifEmptyAll">No notifications.</p>
                        <?php else: ?>
                            <?php foreach ($notifGroups as $groupLabel => $groupRows): ?>
                                <?php if (empty($groupRows)) continue; ?>
                                <div class="notif-group">
                                    <h4 class="notif-group-title"><?php echo $groupLabel; ?></h4>
                                    <div class="activity-list">
                                        <?php foreach ($groupRows as $a): renderActivityItem($a);
                                        endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <p class="stats-empty" id="notifEmptyUnread" hidden>You're all caught up.</p>
                        <?php endif; ?>
                    </div>
                    <div class="notif-delete-bar" id="notifDeleteBar" hidden>
                        <button type="button" class="notif-cancel-btn" id="notifCancelDelete">Cancel</button>
                        <button type="button" class="notif-delete-btn" id="notifDeleteConfirm" disabled>Delete</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

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
    <?php echo $topbarTools ?? ''; ?>
</header>