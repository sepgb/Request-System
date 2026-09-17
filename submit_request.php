<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

$pageTitle = 'Request Submitted';
$basePath = '';

/* =====================================================================
   POST — validate, insert, then redirect.
   The redirect is what stops a refresh from creating a second request.
   ===================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $errors = [];

  if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    $errors[] = 'Your form session expired. Please go back and submit again.';
  }

  $student_number = trim($_POST['student_number'] ?? '');
  $full_name      = trim($_POST['full_name'] ?? '');
  $course         = trim($_POST['course'] ?? '');
  $year_level     = trim($_POST['year_level'] ?? '');
  $semester       = trim($_POST['semester'] ?? '');
  $document_type  = trim($_POST['document_type'] ?? '');
  $purpose        = trim($_POST['purpose'] ?? '');

  $academicDocs = ['Certificate of Registration', 'Certificate of Grades'];
  $allowedDocs  = ['Certificate of Registration', 'Certificate of Grades', 'Diploma (Copy / Authentication)'];

  if ($student_number === '') $errors[] = 'Student number is required.';
  if ($full_name === '') $errors[] = 'Full name is required.';
  if ($course === '') $errors[] = 'Course/Program is required.';

  if ($document_type === '') {
    $errors[] = 'Document type is required.';
  } elseif (!in_array($document_type, $allowedDocs, true)) {
    $errors[] = 'That document type is not currently offered.';
  }

  if (in_array($document_type, $academicDocs, true)) {
    if ($year_level === '') $errors[] = 'Year level is required for this document type.';
    if ($semester === '')   $errors[] = 'Semester is required for this document type.';
  } else {
    $year_level = '';
    $semester   = '';
  }

  if ($purpose === '') $errors[] = 'Purpose is required.';
  if (!empty($errors)) {
    include 'includes/header.php';
?>
    <section class="form-section confirmation confirmation-error">
      <h1>Check these details</h1>
      <p class="subtitle">Your request was not submitted. Fix the items below and send it again.</p>

      <div class="alert alert-error">
        <ul>
          <?php foreach ($errors as $e): ?>
            <li><?php echo htmlspecialchars($e); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="confirmation-actions">
        <a href="javascript:history.back()" class="btn btn-primary">Back to the form</a>
      </div>
    </section>
<?php
    include 'includes/footer.php';
    exit;
  }

  // Unique placeholder — reference_no is a UNIQUE column, so two students
  // submitting at the same moment must not both insert 'PENDING'.
  $placeholder = 'TMP-' . uniqid();

  $stmt = $conn->prepare(
    "INSERT INTO requests
         (reference_no, student_number, full_name, course, year_level, semester,
          document_type, purpose)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
  );
  $stmt->bind_param(
    'ssssssss',
    $placeholder,
    $student_number,
    $full_name,
    $course,
    $year_level,
    $semester,
    $document_type,
    $purpose
  );
  $stmt->execute();
  $newId = $stmt->insert_id;
  $stmt->close();

  $referenceNo = 'REQ-' . date('Y') . '-' . str_pad($newId, 6, '0', STR_PAD_LEFT);
  $claimDate   = calculateClaimDate(new DateTime())->format('Y-m-d');

  $update = $conn->prepare("UPDATE requests SET reference_no = ?, claim_date = ? WHERE id = ?");
  $update->bind_param('ssi', $referenceNo, $claimDate, $newId);
  $update->execute();
  $update->close();

  header('Location: submit_request.php?ref=' . urlencode($referenceNo)
    . '&sn=' . urlencode($student_number));
  exit;
}

/* =====================================================================
   GET — read the saved request back and show the confirmation.
   Refreshing this URL just re-reads the same row.
   ===================================================================== */
$ref = trim($_GET['ref'] ?? '');
$sn  = trim($_GET['sn'] ?? '');

if ($ref === '' || $sn === '') {
  header('Location: request.php');
  exit;
}

$stmt = $conn->prepare(
  "SELECT reference_no, student_number, document_type, claim_date
     FROM requests WHERE reference_no = ? AND student_number = ?"
);
$stmt->bind_param('ss', $ref, $sn);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows !== 1) {
  $stmt->close();
  header('Location: request.php');
  exit;
}

$request = $res->fetch_assoc();
$stmt->close();

$referenceNo    = $request['reference_no'];
$student_number = $request['student_number'];
$document_type  = $request['document_type'];
$claimDate      = $request['claim_date'];

include 'includes/header.php';
?>

<section class="form-section confirmation" style="margin-top: -20px;">
  <span class="confirmation-mark" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
      stroke-linecap="round" stroke-linejoin="round">
      <path d="M4 12.5 L9.5 18 L20 6.5" />
    </svg>
  </span>

  <h1>Request received</h1>
  <p class="subtitle">The registrar's office has your request for
    <strong><?php echo htmlspecialchars($document_type); ?></strong>.
    Please Pay the processing fee at the Cashier's Office and keep your official receipt
  </p>

  <div class="reference-box">
    <span class="reference-label">Your reference number</span>
    <span class="reference-number" id="referenceNo"><?php echo htmlspecialchars($referenceNo); ?></span>
    <button type="button" class="ref-copy" id="copyRef"
      data-reference="<?php echo htmlspecialchars($referenceNo); ?>">Copy number</button>
    <p class="reference-hint">Screenshot or copy this. You will need this to track your
      requested document.</p>
  </div>

  <dl class="next-steps">
    <div class="next-step">
      <dt>Claim date</dt>
      <dd class="next-step-strong">
        <?php echo $claimDate
          ? (new DateTime($claimDate))->format('l, F j, Y')
          : 'To be announced'; ?>
      </dd>
    </div>
    <div class="next-step">
      <dt>Bring with you</dt>
      <dd>Printed claim receipt and Cashier's receipt</dd>
    </div>
    <div class="next-step">
      <dt>Processing fee</dt>
      <dd>&#8369;20.00</dd>
    </div>
  </dl>

  <div class="confirmation-actions">
    <a href="receipt.php?reference_no=<?php echo urlencode($referenceNo); ?>&student_number=<?php echo urlencode($student_number); ?>"
      class="btn btn-primary">View and print receipt</a>
  </div>

</section>

<script>
  (function() {
    var btn = document.getElementById('copyRef');
    if (!btn || !navigator.clipboard) return;

    btn.addEventListener('click', function() {
      navigator.clipboard.writeText(btn.dataset.reference).then(function() {
        btn.textContent = 'Copied';
        btn.classList.add('is-copied');
        setTimeout(function() {
          btn.textContent = 'Copy number';
          btn.classList.remove('is-copied');
        }, 2000);
      });
    });
  })();
</script>

<?php include 'includes/footer.php'; ?>