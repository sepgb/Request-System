<?php
require_once __DIR__ . '/functions.php';

$currentAdminPage = basename($_SERVER['PHP_SELF'] ?? '');

$sidebarScopeIn = documentScopeInClause($conn);
$sidebarTotal = (int)$conn->query(
    'SELECT COUNT(*) AS c FROM requests' . ($sidebarScopeIn !== null ? " WHERE document_type IN ($sidebarScopeIn)" : '')
)->fetch_assoc()['c'];
$sidebarAllActive = $currentAdminPage === 'dashboard.php' && empty($isFiltering);
?>
<aside class="admin-sidebar">
    <div class="sidebar-brand">
        <span class="sidebar-brand-icon" aria-hidden="true">
            <img src="../assets/images/logo.png" alt="" class="sidebar-brand-img">
        </span>
        <div class="univ">
            <span class="sidebar-brand-text">
                University of Rizal System
            </span>
            <span class="sidebar-office">
                Registrar Office - Morong
            </span>
        </div>
    </div>

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

    <div class="sidebar-section-label">
        Requests
    </div>

    <nav class="sidebar-nav sidebar-nav-status">
        <a href="dashboard.php"
            class="sidebar-link sidebar-status-link<?php echo $sidebarAllActive ? ' active' : ''; ?>"
            data-status="">
            <span>All requests</span>
            <span class="sidebar-count sidebar-count-all" id="count-all"><?php echo $sidebarTotal; ?></span>
        </a>
        <a href="claim_schedule.php" class="sidebar-link<?php echo $currentAdminPage === 'claim_schedule.php' ? ' active' : ''; ?>">
            <span>Claim Schedule</span>
        </a>
    </nav>
</aside>