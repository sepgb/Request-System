<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';
$pageTitle = 'Track Request';
$basePath = '';
include 'includes/header.php';

$result = null;
$notFound = false;
$rateLimited = false;
$reference_no = trim($_GET['reference_no'] ?? '');
$student_number = trim($_GET['student_number'] ?? '');

if ($reference_no !== '' && $student_number !== '') {
  $clientIp = getClientIp();

  if (!checkRateLimit($conn, $clientIp)) {
    $rateLimited = true;
  } else {
    recordLookupAttempt($conn, $clientIp);

    $stmt = $conn->prepare("SELECT * FROM requests WHERE reference_no = ? AND student_number = ?");
    $stmt->bind_param('ss', $reference_no, $student_number);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 1) {
      $result = $res->fetch_assoc();
    } else {
      $notFound = true;
    }
    $stmt->close();
  }
}

function statusClass($status)
{
  return 'badge badge-' . strtolower(str_replace(' ', '-', $status));
}
?>

<section class="form-section">
  <h1>Track Your Request</h1>
  <p class="subtitle">Enter your reference number and student number to check your request status.</p>

  <form method="GET" class="track-form">
    <div class="form-grid">
      <div class="form-group">
        <label for="reference_no">Reference Number *</label>
        <input type="text" id="reference_no" name="reference_no" required
          value="<?php echo htmlspecialchars($reference_no); ?>" placeholder="e.g. REQ-2026-000001" autocomplete="off">
      </div>
      <div class="form-group">
        <label for="student_number">Student Number *</label>
        <input type="text" id="student_number" name="student_number" required
          value="<?php echo htmlspecialchars($student_number); ?>" placeholder="e.g. 2023-00123" autocomplete="off">
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Check Status</button>
  </form>

  <?php if ($rateLimited): ?>
    <div class="alert alert-error">Too many lookup attempts from this connection. Please wait a few minutes and try again.</div>
  <?php elseif ($notFound): ?>
    <div class="alert alert-error">No matching request found. Please check your reference number and student number.</div>
  <?php elseif (isset($_GET['updated'])): ?>
    <div class="alert alert-success">
      <p>Your request has been updated.</p>
    </div>
  <?php elseif (isset($_GET['cancelled'])): ?>
    <div class="alert alert-success">
      <p>Your request has been cancelled.</p>
    </div>
  <?php endif; ?>

  <?php if ($result): ?>
    <div class="tracking-result">
      <h2>Request Details</h2>
      <table class="detail-table">
        <tr>
          <th>Reference No.</th>
          <td><?php echo htmlspecialchars($result['reference_no']); ?></td>
        </tr>
        <tr>
          <th>Student Number</th>
          <td><?php echo htmlspecialchars($result['student_number']); ?></td>
        </tr>
        <tr>
          <th>Name</th>
          <td><?php echo htmlspecialchars($result['full_name']); ?></td>
        </tr>
        <tr>
          <th>Document</th>
          <td><?php echo htmlspecialchars($result['document_type']); ?></td>
        </tr>
        <?php if (!empty($result['semester'])): ?>
          <tr>
            <th>Semester</th>
            <td><?php echo htmlspecialchars($result['semester']); ?></td>
          </tr>
        <?php endif; ?>
        <tr>
          <th>Purpose</th>
          <td><?php echo htmlspecialchars($result['purpose']); ?></td>
        </tr>
        <tr>
          <th>Date Requested</th>
          <td><?php echo date('M d, Y', strtotime($result['date_requested'])); ?></td>
        </tr>
        <tr class="receipt-claim-row">
          <th>Claim Date</th>
          <td><strong><?php echo $result['claim_date'] ? (new DateTime($result['claim_date']))->format('l, F j, Y') : 'To be announced'; ?></strong></td>
        </tr>
        <tr>
          <th>Request Status</th>
          <td><span class="<?php echo statusClass($result['request_status']); ?>"><?php echo htmlspecialchars($result['request_status']); ?></span></td>
        </tr>
        <tr>
          <th>Processing Fee</th>
          <td>&#8369;<?php echo number_format($result['amount'], 2); ?></td>
        </tr>
        <?php if (!empty($result['admin_remarks'])): ?>
          <tr>
            <th>Remarks</th>
            <td><?php echo nl2br(htmlspecialchars($result['admin_remarks'])); ?></td>
          </tr>
        <?php endif; ?>
      </table>

      <div class="tracking-actions">
        <a href="receipt.php?reference_no=<?php echo urlencode($result['reference_no']); ?>&student_number=<?php echo urlencode($result['student_number']); ?>"
          class="btn btn-primary">View / Print Claim Receipt</a>

        <?php if ($result['request_status'] === 'Pending'): ?>
          <a href="edit_request.php?reference_no=<?php echo urlencode($result['reference_no']); ?>&student_number=<?php echo urlencode($result['student_number']); ?>"
            class="btn btn-edit" aria-label="Edit Request">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M14.5 4.5L19.5 9.5L8 21H3V16L14.5 4.5Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <span class="btn-action-label">Edit</span>
          </a>
          <button type="button" id="openCancelModal" class="btn btn-cancel" aria-label="Cancel Request">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M6 6L18 18M18 6L6 18" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
            </svg>
            <span class="btn-action-label">Cancel</span>
          </button>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</section>

<?php if ($result && $result['request_status'] === 'Pending'): ?>
  <div class="modal-overlay" id="cancelRequestOverlay" hidden>
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="cancelModalTitle">
      <h3 id="cancelModalTitle">Cancel this request?</h3>
      <p>This can't be undone. You'll need to submit a new request if you change your mind.</p>
      <form method="POST" action="cancel_request.php">
        <input type="hidden" name="reference_no" value="<?php echo htmlspecialchars($result['reference_no']); ?>">
        <input type="hidden" name="student_number" value="<?php echo htmlspecialchars($result['student_number']); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
        <div class="modal-actions">
          <button type="button" class="btn-modal btn-modal-cancel" id="closeCancelModal">Go back</button>
          <button type="submit" class="btn-modal btn-modal-confirm" style="background:#d65454;color:#fff;">Yes, cancel it</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>