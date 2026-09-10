<?php
require_once 'config/db.php';
$pageTitle = 'Track Request';
$basePath = '';
include 'includes/header.php';

$result = null;
$notFound = false;
$reference_no = trim($_GET['reference_no'] ?? '');
$student_number = trim($_GET['student_number'] ?? '');

if ($reference_no !== '' && $student_number !== '') {
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

  <?php if ($notFound): ?>
    <div class="alert alert-error">No matching request found. Please check your reference number and student number.</div>
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

      <a href="receipt.php?reference_no=<?php echo urlencode($result['reference_no']); ?>&student_number=<?php echo urlencode($result['student_number']); ?>"
        class="btn btn-primary" style="margin-top:16px;">View / Print Claim Receipt</a>
    </div>
  <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>