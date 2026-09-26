<?php
require_once 'auth_check.php';
require_once '../config/db.php';
require_once '../includes/functions.php';

$scopeIn = documentScopeInClause($conn);
$scopeWhere = $scopeIn !== null ? " WHERE document_type IN ($scopeIn)" : '';

$requests = $conn->query("SELECT * FROM requests{$scopeWhere} ORDER BY date_requested DESC");

/* Quick stats — scoped to this admin's document types, if restricted */
$stats = $conn->query("
    SELECT
        SUM(request_status = 'Pending') AS pending,
        SUM(request_status = 'Processing') AS processing,
        SUM(request_status = 'Ready for Pickup') AS ready,
        SUM(request_status = 'Rejected') AS rejected,
        COUNT(*) AS total
    FROM requests{$scopeWhere}
")->fetch_assoc();

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminPhoto = $_SESSION['admin_photo'] ?? null;
$nameParts = explode(' ', trim($adminName));
$firstInitial = strtoupper(substr($nameParts[0], 0, 1));
$secondInitial = strtoupper(substr(end($nameParts), 0, 1));
$adminInitial = $firstInitial . $secondInitial;
$currentAdminPage = basename($_SERVER['PHP_SELF'] ?? '');
$initialStatusFilter = trim($_GET['status'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Registrar Admin</title>

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
          <span class="sidebar-brand-text">
            University of Rizal System
          </span>
          <span class="sidebar-office">
            Registrar Office - Morong
          </span>
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
      <div class="sidebar-section-label">
        Status
      </div>

      <nav class="sidebar-nav sidebar-nav-status">

        <!-- All -->
        <a href="dashboard.php"
          class="sidebar-link sidebar-status-link<?php echo $initialStatusFilter === '' ? ' active' : ''; ?>"
          data-status="">
          <span>All requests</span>
          <span class="sidebar-count sidebar-count-all"
            id="count-all">
            <?php echo (int)$stats['total']; ?>
          </span>
        </a>

        <a href="dashboard.php?status=Pending"
          class="sidebar-link sidebar-status-link<?php echo $initialStatusFilter === 'Pending' ? ' active' : ''; ?>"
          data-status="Pending">
          <span>Pending</span>
          <span class="sidebar-count sidebar-count-pending"
            id="count-pending">
            <?php echo (int)$stats['pending']; ?>
          </span>
        </a>

        <a href="dashboard.php?status=Processing"
          class="sidebar-link sidebar-status-link<?php echo $initialStatusFilter === 'Processing' ? ' active' : ''; ?>"
          data-status="Processing">
          <span>Processing</span>
          <span class="sidebar-count sidebar-count-processing"
            id="count-processing">
            <?php echo (int)$stats['processing']; ?>
          </span>
        </a>

        <a href="dashboard.php?status=Ready+for+Pickup"
          class="sidebar-link sidebar-status-link<?php echo $initialStatusFilter === 'Ready for Pickup' ? ' active' : ''; ?>"
          data-status="Ready for Pickup">
          <span>Ready for pickup</span>
          <span class="sidebar-count sidebar-count-ready"
            id="count-ready">
            <?php echo (int)$stats['ready']; ?>
          </span>
        </a>

        <a href="dashboard.php?status=Rejected"
          class="sidebar-link sidebar-status-link<?php echo $initialStatusFilter === 'Rejected' ? ' active' : ''; ?>"
          data-status="Rejected">
          <span>Rejected</span>
          <span class="sidebar-count sidebar-count-rejected"
            id="count-rejected">
            <?php echo (int)$stats['rejected']; ?>
          </span>
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

        <!-- SEARCH -->
        <div class="topbar-tools">
          <label class="topbar-search">
            <svg viewBox="0 0 24 24" fill="none">
              <circle
                cx="11"
                cy="11"
                r="6.5"
                stroke="currentColor"
                stroke-width="1.7" />
              <path
                d="M16 16L20 20"
                stroke="currentColor"
                stroke-width="1.7"
                stroke-linecap="round" />
            </svg>
            <input
              type="text"
              id="filterSearch"
              autocomplete="off"
              placeholder="Search name, student no. or reference no.">
          </label>
        </div>
      </header>

      <!-- DASHBOARD CONTENT -->
      <main class="admin-main">

        <!-- Statistics -->
        <div class="stats-row">

          <!-- Total -->
          <div class="stat-card">
            <div class="stat-icon stat-icon-total">
              <svg viewBox="0 0 24 24" fill="none">
                <rect
                  x="5"
                  y="3"
                  width="14"
                  height="18"
                  rx="2"
                  stroke="currentColor"
                  stroke-width="1.6" />

                <path
                  d="M8.5 8H15.5M8.5 12H15.5M8.5 16H13"
                  stroke="currentColor"
                  stroke-width="1.5"
                  stroke-linecap="round" />
              </svg>
            </div>

            <div>
              <span class="stat-number"
                id="stat-total">
                <?php echo (int)$stats['total']; ?>
              </span>

              <span class="stat-label">
                Total requests
              </span>
            </div>
          </div>


          <!-- Pending -->
          <div class="stat-card stat-card-pending">
            <div class="stat-icon stat-icon-pending">
              <svg viewBox="0 0 24 24" fill="none">
                <circle
                  cx="12"
                  cy="12"
                  r="8"
                  stroke="currentColor"
                  stroke-width="1.7" />

                <path
                  d="M12 7V12L15 14"
                  stroke="currentColor"
                  stroke-width="1.7"
                  stroke-linecap="round"
                  stroke-linejoin="round" />
              </svg>
            </div>

            <div>
              <span class="stat-number"
                id="stat-pending">
                <?php echo (int)$stats['pending']; ?>
              </span>

              <span class="stat-label">
                Pending
              </span>
            </div>
          </div>

          <!-- Ready -->
          <div class="stat-card stat-card-ready">
            <div class="stat-icon stat-icon-ready">
              <svg viewBox="0 0 24 24" fill="none">
                <circle
                  cx="12"
                  cy="12"
                  r="8"
                  stroke="currentColor"
                  stroke-width="1.7" />

                <path
                  d="M8.5 12L11 14.5L15.5 9.5"
                  stroke="currentColor"
                  stroke-width="1.7"
                  stroke-linecap="round"
                  stroke-linejoin="round" />
              </svg>
            </div>

            <div>
              <span class="stat-number"
                id="stat-ready">
                <?php echo (int)$stats['ready']; ?>
              </span>

              <span class="stat-label">
                Ready for pickup
              </span>
            </div>
          </div>
        </div>


        <!-- REQUEST TABLE -->
        <div class="table-wrap">

          <table class="admin-table">

            <thead>
              <tr>
                <th>Reference no.</th>
                <th>Student no.</th>
                <th>Name</th>
                <th>Document</th>
                <th>Year Level</th>
                <th>Semester</th>
                <th>Claim date</th>
                <th>Status</th>
              </tr>
            </thead>

            <tbody>
              <tr class="filter-empty"
                hidden>
                <td colspan="8"
                  class="empty-row">
                  <div class="empty-state">
                    <svg viewBox="0 0 24 24"
                      fill="none">

                      <rect
                        x="5"
                        y="3"
                        width="14"
                        height="18"
                        rx="2"
                        stroke="currentColor"
                        stroke-width="1.5" />

                      <path
                        d="M9 8H15M9 12H15M9 16H13"
                        stroke="currentColor"
                        stroke-width="1.4"
                        stroke-linecap="round" />
                    </svg>
                    <span>
                      No requests match that search.
                    </span>
                  </div>
                </td>
              </tr>

              <?php if ($requests->num_rows === 0): ?>
                <tr>
                  <td colspan="8"
                    class="empty-row">
                    No requests yet. New submissions from the student site will appear here.
                  </td>
                </tr>
              <?php endif; ?>

              <?php while ($r = $requests->fetch_assoc()): ?>
                <tr
                  id="row-<?php echo $r['id']; ?>"
                  data-reference="<?php echo htmlspecialchars($r['reference_no'], ENT_QUOTES, 'UTF-8'); ?>"
                  data-full-name="<?php echo htmlspecialchars($r['full_name'], ENT_QUOTES, 'UTF-8'); ?>"
                  data-date-requested="<?php echo $r['date_requested'] ? date('M d, Y g:i A', strtotime($r['date_requested'])) : ''; ?>"
                  data-purpose="<?php echo htmlspecialchars($r['purpose'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                  <td data-label="Reference no.">
                    <span class="cell-ref">
                      <?php echo htmlspecialchars($r['reference_no']); ?>
                    </span>
                  </td>

                  <td data-label="Student no.">
                    <?php echo htmlspecialchars($r['student_number']); ?>
                  </td>

                  <td data-label="Name">
                    <?php echo htmlspecialchars($r['full_name']); ?>
                  </td>

                  <td data-label="Document">
                    <?php echo htmlspecialchars($r['document_type']); ?>
                  </td>

                  <td data-label="Year Level">
                    <?php
                    echo (
                      $r['year_level'] !== ''
                      && $r['year_level'] !== null
                    )
                      ? htmlspecialchars($r['year_level'])
                      : '&mdash;';
                    ?>
                  </td>

                  <td data-label="Semester">
                    <?php
                    echo (
                      $r['semester'] !== ''
                      && $r['semester'] !== null
                    )
                      ? htmlspecialchars($r['semester'])
                      : '&mdash;';
                    ?>
                  </td>

                  <td data-label="Claim date">
                    <?php
                    echo $r['claim_date']
                      ? date(
                        'M d, Y',
                        strtotime($r['claim_date'])
                      )
                      : '&mdash;';
                    ?>
                  </td>

                  <td data-label="Status"
                    class="cell-status">
                    <select
                      class="status-select"
                      data-id="<?php echo $r['id']; ?>"
                      aria-label="Status for <?php echo htmlspecialchars($r['reference_no']); ?>">
                      <?php foreach (
                        ['Pending', 'Processing', 'Ready for Pickup', 'Rejected']
                        as $s
                      ): ?>
                        <option
                          value="<?php echo $s; ?>"
                          <?php
                          echo $r['request_status'] === $s
                            ? 'selected'
                            : '';
                          ?>>
                          <?php echo $s; ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </main>
    </div>
  </div>

  <!-- CLAIM CONFIRMATION MODAL -->
  <div class="modal-overlay" id="claimModalOverlay" hidden>
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="claimModalTitle">
      <h3 id="claimModalTitle">Mark as claimed?</h3>
      <p id="claimModalText">This will permanently delete the request from the system.</p>
      <div class="modal-actions">
        <button type="button" class="btn-modal btn-modal-cancel" id="claimModalCancel">Cancel</button>
        <button type="button" class="btn-modal btn-modal-confirm" id="claimModalConfirm">Yes, mark claimed</button>
      </div>
    </div>
  </div>

  <!-- REQUEST DETAILS MODAL -->
  <div class="modal-overlay" id="requestDetailsOverlay" hidden>
    <div class="request-details-box" role="dialog" aria-modal="true" aria-labelledby="requestDetailsTitle">
      <div class="request-details-header">
        <span class="request-details-eyebrow">Request Details</span>
        <h3 id="requestDetailsTitle"></h3>
      </div>

      <div class="request-details-list">
        <div class="request-details-row">
          <span class="request-details-label">Date Requested</span>
          <span class="request-details-value" id="rdDateRequested"></span>
        </div>
        <div class="request-details-row">
          <span class="request-details-label">Student No.</span>
          <span class="request-details-value" id="rdStudentNo"></span>
        </div>
        <div class="request-details-row">
          <span class="request-details-label">Name</span>
          <span class="request-details-value" id="rdName"></span>
        </div>
        <div class="request-details-row">
          <span class="request-details-label">Document</span>
          <span class="request-details-value" id="rdDocument"></span>
        </div>
        <div class="request-details-row">
          <span class="request-details-label">Year Level</span>
          <span class="request-details-value" id="rdYearLevel"></span>
        </div>
        <div class="request-details-row">
          <span class="request-details-label">Semester</span>
          <span class="request-details-value" id="rdSemester"></span>
        </div>
        <div class="request-details-row" id="rdPurposeRow">
          <span class="request-details-label">Purpose</span>
          <span class="request-details-value" id="rdPurpose"></span>
        </div>
        <div class="request-details-row">
          <span class="request-details-label">Claim Date</span>
          <span class="request-details-value" id="rdClaimDate"></span>
        </div>
        <div class="request-details-row">
          <span class="request-details-label">Status</span>
          <span class="request-details-value" id="rdStatus"></span>
        </div>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn-modal btn-modal-cancel" id="requestDetailsClose">Cancel</button>
        <button type="button" class="btn-modal btn-modal-confirm" id="rdMarkClaimed" hidden>Mark as Claimed</button>
      </div>
    </div>
  </div>

  <!-- EDIT PROFILE MODAL -->
  <div class="modal-overlay" id="editProfileOverlay" hidden>
    <div class="edit-profile-box" role="dialog" aria-modal="true" aria-labelledby="editProfileTitle">
      <div class="edit-profile-header">
        <span class="edit-profile-header-icon">
          <svg viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="8" r="3.4" stroke="currentColor" stroke-width="1.6" />
            <path d="M5 20c0-3.6 3.1-6.2 7-6.2s7 2.6 7 6.2" stroke="currentColor"
              stroke-width="1.6" stroke-linecap="round" />
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
                  <path d="M12 16V4M12 4L7 9M12 4L17 9" stroke="currentColor" stroke-width="1.6"
                    stroke-linecap="round" stroke-linejoin="round" />
                  <path d="M4 16V18.5A1.5 1.5 0 0 0 5.5 20H18.5A1.5 1.5 0 0 0 20 18.5V16"
                    stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                </svg>
                Change Photo
              </button>
              <input type="file" id="editProfilePhotoInput" name="photo"
                accept="image/png, image/jpeg, image/webp" hidden>
            </div>
          </div>

          <div class="edit-profile-section">
            <div class="edit-profile-section-label">
              Account Information
            </div>

            <div class="edit-profile-field">
              <label for="edit_full_name">Full Name</label>
              <input type="text" id="edit_full_name" name="full_name"
                value="<?php echo htmlspecialchars($adminName); ?>" required>
            </div>

            <div class="edit-profile-field">
              <label for="edit_username">Username</label>
              <input type="text" id="edit_username" name="username"
                value="<?php echo htmlspecialchars($_SESSION['admin_username'] ?? ''); ?>" required>
              <p class="edit-profile-hint">Your login username must be unique across the system.</p>
            </div>
          </div>

          <div class="edit-profile-section">
            <div class="edit-profile-section-label">
              Change Password
            </div>

            <div class="edit-profile-field">
              <label for="edit_current_password">Current Password</label>
              <input type="password" id="edit_current_password" name="current_password" autocomplete="off">
            </div>

            <div class="edit-profile-field">
              <label for="edit_new_password">New Password</label>
              <input type="password" id="edit_new_password" name="new_password"
                autocomplete="new-password" minlength="8">
            </div>

            <div class="edit-profile-field">
              <label for="edit_confirm_password">Confirm New Password</label>
              <input type="password" id="edit_confirm_password" name="confirm_password"
                autocomplete="new-password" minlength="8">
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
    window.INITIAL_STATUS_FILTER = <?php echo json_encode($initialStatusFilter); ?>;
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