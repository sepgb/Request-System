<?php
require_once 'config/db.php';
$pageTitle = 'Claim Receipt';
$basePath = '';

$reference_no = trim($_GET['reference_no'] ?? '');
$student_number = trim($_GET['student_number'] ?? '');
$result = null;
$notFound = false;

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

include 'includes/header.php';
?>

<?php if ($notFound || !$result): ?>
  <section class="form-section">
    <h1>Receipt Not Found</h1>
    <div class="alert alert-error">No matching request found. Please check your reference number and student number.</div>
    <a href="track.php" class="btn btn-secondary">Back to Track Request</a>
  </section>
<?php else: ?>
  <section class="form-section receipt-page">
    <div class="receipt-header">
      <h1>Document Claim Receipt</h1>
      <p>Present this receipt together with the Cashier's receipt at the <br> Registrar's Office to claim your document.</p>
    </div>
    <div class="receipt-box" id="receiptBox">
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
          <th>Processing Fee</th>
          <td>&#8369;<?php echo number_format($result['amount'], 2); ?></td>
        </tr>
      </table>

      <p class="receipt-note">* Please pay the processing fee at the Cashier's Office and keep the official receipt.


      <div class="receipt-actions no-print">
        <button type="button" id="print" onclick="window.print()" class="btn btn-primary">Print Receipt</button>
        <button type="button" id="downloadReceiptBtn" class="btn btn-secondary">Download PDF</button>
      </div>
      </p>
    </div>
  </section>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <script>
    (function() {
      var btn = document.getElementById('downloadReceiptBtn');
      var receiptBox = document.getElementById('receiptBox');
      if (!btn || !receiptBox) return;

      btn.addEventListener('click', function() {
        var originalLabel = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Preparing PDF…';

        receiptBox.classList.add('pdf-mode');

        var filename = <?php echo json_encode('receipt.pdf'); ?>;

        function generate() {
          html2pdf()
            .set({
              margin: 10,
              filename: filename,
              image: {
                type: 'jpeg',
                quality: 0.98
              },
              html2canvas: {
                scale: 2,
                backgroundColor: '#ffffff',
                useCORS: true,
                scrollX: 0,
                scrollY: 0,
                windowWidth: document.documentElement.scrollWidth,
                windowHeight: document.documentElement.scrollHeight
              },
              jsPDF: {
                unit: 'mm',
                format: 'a4',
                orientation: 'portrait'
              },
              pagebreak: {
                mode: ['avoid-all', 'css', 'legacy']
              }
            })
            .from(receiptBox)
            .save()
            .then(function() {
              receiptBox.classList.remove('pdf-mode');
              btn.disabled = false;
              btn.textContent = originalLabel;
            })
            .catch(function() {
              receiptBox.classList.remove('pdf-mode');
              btn.disabled = false;
              btn.textContent = originalLabel;
              alert('Sorry, the PDF could not be generated. Please try again or use Print Receipt instead.');
            });
        }

        var fontsReady = (document.fonts && document.fonts.ready) ?
          document.fonts.ready :
          Promise.resolve();

        fontsReady.then(function() {
          requestAnimationFrame(function() {
            requestAnimationFrame(generate);
          });
        });
      });
    })();
  </script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>