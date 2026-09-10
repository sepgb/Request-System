<?php
$pageTitle = 'New Request';
$basePath = '';
include 'includes/header.php';
?>

<section class="form-section">
  <h1>Document Request Form</h1>
  <p class="subtitle">Please fill up all required fields. Your details
    must match your official records.</p>

  <form id="requestForm" action="submit_request.php" method="POST" novalidate>
    <div class="form-grid">
      <div class="form-group">
        <label for="student_number">Student Number *</label>
        <input type="text" id="student_number" name="student_number" required
          placeholder="e.g. M2026-01106" autocomplete="off">
      </div>

      <div class="form-group">
        <label for="full_name">Full Name *</label>
        <input type="text" id="full_name" name="full_name" required
          placeholder="e.g. Juan Dela Cruz" autocomplete="off">
      </div>

      <div class="form-group">
        <label for="course">Course / Program *</label>
        <input type="text" id="course" name="course" required
          placeholder="e.g. BS Computer Science" autocomplete="off">
      </div>

      <div class="form-group">
        <label for="document_type">Document Needed *</label>
        <select id="document_type" name="document_type" required>
          <option value="">Select Document</option>
          <option>Certificate of Registration</option>
          <option>Certificate of Grades</option>
          <option>Diploma (Copy / Authentication)</option>
        </select>
      </div>

      <div class="form-group" id="year_level_group" hidden>
        <label for="year_level">Year Level *</label>
        <select id="year_level" name="year_level">
          <option value="">Select Year Level</option>
          <option>1st Year</option>
          <option>2nd Year</option>
          <option>3rd Year</option>
          <option>4th Year</option>
          <option>5th Year</option>
          <option>Graduate / Alumni</option>
        </select>
      </div>

      <div class="form-group" id="semester_group" hidden>
        <label for="semester">Semester *</label>
        <select id="semester" name="semester">
          <option value="">Select Semester</option>
          <option>1st Semester</option>
          <option>2nd Semester</option>
          <option>Summer</option>
        </select>
      </div>

    </div>

    <div class="form-group">
      <label for="purpose">Purpose / Reason for Request *</label>
      <textarea id="purpose" name="purpose" rows="4" required
        placeholder="e.g. For scholarship"></textarea>
    </div>

    <div class="gcash-box">
      <h3>Payment</h3>
      <p class="gcash-amount">Processing Fee: <strong>&#8369;20.00</strong></p>
      <p class="form-note">Pay at the Cashier's Office and keep the official receipt.
      </p>
    </div>

    <p class="form-note">* Required fields. After submitting, you will receive a
      <strong>Reference Number</strong> — save it to track your request.
    </p>

    <button type="submit" class="btn btn-primary">Submit Request</button>
  </form>
</section>

<?php include 'includes/footer.php'; ?>