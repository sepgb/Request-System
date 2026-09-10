<?php
require_once 'auth_check.php';
require_once '../config/db.php';

$requests = $conn->query("SELECT * FROM requests ORDER BY date_requested DESC");

// ---- Quick stats ----
$stats = $conn->query("SELECT
    SUM(request_status = 'Pending') AS pending,
    SUM(request_status = 'Ready for Pickup') AS ready,
    COUNT(*) AS total
    FROM requests")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Requests Dashboard | Registrar Admin</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="admin-body">
  <header class="site-header admin-header">
    <div class="header-inner">
      <a href="dashboard.php" class="brand">
        <span class="brand-icon" aria-hidden="true"></span>
        <span class="brand-text">Registrar Admin</span>
      </a>
      <nav class="site-nav">
        <span class="admin-who">Hi admin, <?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
        <a href="logout.php">Log out</a>
      </nav>
    </div>
  </header>

  <main class="admin-main">

    <div class="stats-row">
      <div class="stat-card">
        <span class="stat-number" id="stat-total"><?php echo (int)$stats['total']; ?></span>
        <span class="stat-label">Total requests</span>
      </div>
      <div class="stat-card stat-card-pending">
        <span class="stat-number" id="stat-pending"><?php echo (int)$stats['pending']; ?></span>
        <span class="stat-label">Pending</span>
      </div>
      <div class="stat-card stat-card-ready">
        <span class="stat-number" id="stat-ready"><?php echo (int)$stats['ready']; ?></span>
        <span class="stat-label">Ready for pickup</span>
      </div>
    </div>

    <div class="filter-bar">
      <div class="filter-search">
        <input type="text" id="filterSearch" autocomplete="off"
          placeholder="Search by name, student number or reference number">
      </div>

      <select id="filterStatus" class="filter-status">
        <option value="">All statuses</option>
        <option value="Pending">Pending</option>
        <option value="Ready for Pickup">Ready for Pickup</option>
      </select>
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
            <th><span class="visually-hidden">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <!-- Shown by admin.js when a search or status filter matches nothing -->
          <tr class="filter-empty" hidden>
            <td colspan="8" class="empty-row">No requests match that search.</td>
          </tr>

          <?php if ($requests->num_rows === 0): ?>
            <tr>
              <td colspan="8" class="empty-row">
                No requests yet. New submissions from the student site will appear here.
              </td>
            </tr>
          <?php endif; ?>

          <?php while ($r = $requests->fetch_assoc()): ?>
            <tr id="row-<?php echo $r['id']; ?>" data-reference="<?php echo htmlspecialchars($r['reference_no']); ?>">
              <td data-label="Reference no."><span class="cell-ref"><?php echo htmlspecialchars($r['reference_no']); ?></span></td>
              <td data-label="Student no."><?php echo htmlspecialchars($r['student_number']); ?></td>
              <td data-label="Name"><?php echo htmlspecialchars($r['full_name']); ?></td>
              <td data-label="Document"><?php echo htmlspecialchars($r['document_type']); ?></td>
              <td data-label="Year Level"><?php echo $r['year_level'] !== '' && $r['year_level'] !== null ? htmlspecialchars($r['year_level']) : '&mdash;'; ?></td>
              <td data-label="Semester"><?php echo $r['semester'] !== '' && $r['semester'] !== null ? htmlspecialchars($r['semester']) : '&mdash;'; ?></td>
              <td data-label="Claim date"><?php echo $r['claim_date'] ? date('M d, Y', strtotime($r['claim_date'])) : '&mdash;'; ?></td>
              <td data-label="Status" class="cell-status">
                <select class="status-select" data-id="<?php echo $r['id']; ?>"
                  aria-label="Status for <?php echo htmlspecialchars($r['reference_no']); ?>">
                  <?php foreach (['Pending', 'Ready for Pickup'] as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo $r['request_status'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="cell-action" id="action-<?php echo $r['id']; ?>">
                <?php if ($r['request_status'] === 'Ready for Pickup'): ?>
                  <button type="button" class="btn btn-small btn-danger btn-claim"
                    data-id="<?php echo $r['id']; ?>"
                    data-reference="<?php echo htmlspecialchars($r['reference_no']); ?>">
                    Mark claimed
                  </button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </main>

  <script src="../assets/js/admin.js"></script>
</body>

</html>