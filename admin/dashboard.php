<?php
require_once 'auth_check.php';
require_once '../config/db.php';
require_once '../includes/functions.php';

$scopeIn = documentScopeInClause($conn);
$scopeWhere = $scopeIn !== null ? " WHERE document_type IN ($scopeIn)" : '';

$docTypeRes = $conn->query("SELECT DISTINCT document_type FROM requests{$scopeWhere} ORDER BY document_type ASC");
$docTypeOptions = [];
while ($dt = $docTypeRes->fetch_assoc()) {
  $docTypeOptions[] = $dt['document_type'];
}

$perPage = 50;

$filterQ        = trim($_GET['q'] ?? '');
$filterStatus   = trim($_GET['status'] ?? '');
$filterDocument = trim($_GET['document'] ?? '');
$filterPeriod   = trim($_GET['period'] ?? '');
$page           = max(1, (int)($_GET['page'] ?? 1));

$validStatuses = ['Pending', 'Processing', 'Ready for Pickup', 'Rejected'];
if (!in_array($filterStatus, $validStatuses, true)) {
  $filterStatus = '';
}
if (!in_array($filterDocument, $docTypeOptions, true)) {
  $filterDocument = '';
}
if (!in_array($filterPeriod, ['today', 'week', 'month'], true)) {
  $filterPeriod = '';
}

$where  = [];
$params = [];
$types  = '';

if ($scopeIn !== null) {
  $where[] = "document_type IN ($scopeIn)";
}
if ($filterQ !== '') {
  $where[] = '(reference_no LIKE ? OR student_number LIKE ? OR full_name LIKE ?)';
  $like = '%' . $filterQ . '%';
  $params = array_merge($params, [$like, $like, $like]);
  $types .= 'sss';
}
if ($filterStatus !== '') {
  $where[] = 'request_status = ?';
  $params[] = $filterStatus;
  $types .= 's';
}
if ($filterDocument !== '') {
  $where[] = 'document_type = ?';
  $params[] = $filterDocument;
  $types .= 's';
}
if ($filterPeriod === 'today') {
  $where[] = 'date_requested >= CURDATE() AND date_requested < CURDATE() + INTERVAL 1 DAY';
} elseif ($filterPeriod === 'week') {
  $where[] = 'YEARWEEK(date_requested, 1) = YEARWEEK(CURDATE(), 1)';
} elseif ($filterPeriod === 'month') {
  $where[] = 'YEAR(date_requested) = YEAR(CURDATE()) AND MONTH(date_requested) = MONTH(CURDATE())';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

if ($types !== '') {
  $countStmt = $conn->prepare("SELECT COUNT(*) AS c FROM requests {$whereSql}");
  $countStmt->bind_param($types, ...$params);
  $countStmt->execute();
  $totalRows = (int)$countStmt->get_result()->fetch_assoc()['c'];
  $countStmt->close();
} else {
  $totalRows = (int)$conn->query("SELECT COUNT(*) AS c FROM requests {$whereSql}")->fetch_assoc()['c'];
}

$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$pageSql   = "SELECT * FROM requests {$whereSql} ORDER BY date_requested DESC LIMIT ? OFFSET ?";
$pageTypes = $types . 'ii';
$pageStmt  = $conn->prepare($pageSql);
$pageStmt->bind_param($pageTypes, ...array_merge($params, [$perPage, $offset]));
$pageStmt->execute();
$requests = $pageStmt->get_result();

$isFiltering = ($filterQ !== '' || $filterStatus !== '' || $filterDocument !== '' || $filterPeriod !== '');

function filterUrl(array $overrides = []): string
{
  global $filterQ, $filterStatus, $filterDocument, $filterPeriod;
  $params = array_merge([
    'q'        => $filterQ,
    'status'   => $filterStatus,
    'document' => $filterDocument,
    'period'   => $filterPeriod,
    'page'     => 1,
  ], $overrides);
  $params = array_filter($params, function ($v) {
    return $v !== '' && $v !== null;
  });
  return 'dashboard.php' . ($params ? ('?' . http_build_query($params)) : '');
}

$stats = $conn->query("
    SELECT
        SUM(request_status = 'Pending') AS pending,
        SUM(request_status = 'Processing') AS processing,
        SUM(request_status = 'Ready for Pickup') AS ready,
        SUM(request_status = 'Rejected') AS rejected,
        COUNT(*) AS total
    FROM requests{$scopeWhere}
")->fetch_assoc();

$initialStatusFilter = $filterStatus;
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

  <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/style.css'); ?>">

</head>

<body class="admin-body">

  <div class="admin-shell">

    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="admin-content">

      <?php ob_start(); ?>
      <div class="topbar-tools">
        <form method="GET" action="dashboard.php" id="filterForm">
          <label class="topbar-search">
            <button type="submit" aria-label="Search">
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
            </button>
            <input
              type="text"
              name="q"
              id="filterSearch"
              autocomplete="off"
              value="<?php echo htmlspecialchars($filterQ, ENT_QUOTES, 'UTF-8'); ?>"
              placeholder="Search name, student no. or reference no.">
          </label>

          <div class="table-filters">
            <select name="status" id="filterStatus" aria-label="Filter by status">
              <option value="">All statuses</option>
              <?php foreach ($validStatuses as $st): ?>
                <option value="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filterStatus === $st ? ' selected' : ''; ?>>
                  <?php echo htmlspecialchars($st); ?>
                </option>
              <?php endforeach; ?>
            </select>

            <select name="document" id="filterDocument" aria-label="Filter by document type">
              <option value="">All documents</option>
              <?php foreach ($docTypeOptions as $dt): ?>
                <option value="<?php echo htmlspecialchars($dt, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filterDocument === $dt ? ' selected' : ''; ?>>
                  <?php echo htmlspecialchars($dt); ?>
                </option>
              <?php endforeach; ?>
            </select>

            <select name="period" id="filterDatePeriod" aria-label="Filter by date">
              <option value="">Any time</option>
              <option value="today" <?php echo $filterPeriod === 'today' ? ' selected' : ''; ?>>Today</option>
              <option value="week" <?php echo $filterPeriod === 'week' ? ' selected' : ''; ?>>This week</option>
              <option value="month" <?php echo $filterPeriod === 'month' ? ' selected' : ''; ?>>This month</option>
            </select>

          </div>
        </form>
      </div>
      <?php $topbarTools = ob_get_clean(); ?>
      <?php include __DIR__ . '/../includes/admin_topbar.php'; ?>

      <main class="admin-main">

        <div class="stats-row">

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
              <?php if ($totalRows === 0): ?>
                <tr>
                  <td colspan="8" class="empty-row">
                    <div class="empty-state">
                      <svg viewBox="0 0 24 24" fill="none">
                        <rect x="5" y="3" width="14" height="18" rx="2" stroke="currentColor" stroke-width="1.5" />
                        <path d="M9 8H15M9 12H15M9 16H13" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                      </svg>
                      <span>
                        <?php echo $isFiltering ? 'No requests match your filters.' : 'No requests yet. New submissions from the student site will appear here.'; ?>
                      </span>
                    </div>
                  </td>
                </tr>
              <?php endif; ?>

              <?php while ($r = $requests->fetch_assoc()): ?>
                <tr
                  id="row-<?php echo $r['id']; ?>"
                  data-reference="<?php echo htmlspecialchars($r['reference_no'], ENT_QUOTES, 'UTF-8'); ?>"
                  data-full-name="<?php echo htmlspecialchars($r['full_name'], ENT_QUOTES, 'UTF-8'); ?>"
                  data-date-requested="<?php echo $r['date_requested'] ? date('M d, Y g:i A', strtotime($r['date_requested'])) : ''; ?>"
                  data-date="<?php echo $r['date_requested'] ? date('Y-m-d', strtotime($r['date_requested'])) : ''; ?>"
                  data-document="<?php echo htmlspecialchars($r['document_type'], ENT_QUOTES, 'UTF-8'); ?>"
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

          <?php if ($totalRows > 0): ?>
            <div class="table-pagination">
              <span class="table-pagination-count">
                Showing <?php echo $offset + 1; ?>&ndash;<?php echo min($offset + $perPage, $totalRows); ?>
                of <?php echo $totalRows; ?> request<?php echo $totalRows === 1 ? '' : 's'; ?>
              </span>

              <?php if ($totalPages > 1): ?>
                <nav class="table-pagination-nav" aria-label="Table pages">
                  <?php if ($page > 1): ?>
                    <a href="<?php echo filterUrl(['page' => $page - 1]); ?>" class="table-pagination-btn">Prev</a>
                  <?php else: ?>
                    <span class="table-pagination-btn is-disabled">Prev</span>
                  <?php endif; ?>

                  <span class="table-pagination-page">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>

                  <?php if ($page < $totalPages): ?>
                    <a href="<?php echo filterUrl(['page' => $page + 1]); ?>" class="table-pagination-btn">Next</a>
                  <?php else: ?>
                    <span class="table-pagination-btn is-disabled">Next</span>
                  <?php endif; ?>
                </nav>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </main>
    </div>
  </div>

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

  <div class="modal-overlay" id="deleteModalOverlay" hidden>
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
      <h3 id="deleteModalTitle">Delete request?</h3>
      <p id="deleteModalText">This will permanently delete the rejected request from the system.</p>
      <div class="modal-actions">
        <button type="button" class="btn-modal btn-modal-cancel" id="deleteModalCancel">Cancel</button>
        <button type="button" class="btn-modal btn-modal-danger" id="deleteModalConfirm">Yes, delete</button>
      </div>
    </div>
  </div>

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
        <button type="button" class="btn-modal btn-modal-danger" id="rdDelete" hidden>Delete Request</button>
      </div>
    </div>
  </div>

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