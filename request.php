<?php
session_start();
require_once 'includes/functions.php';

$pageTitle = 'New Request';
$basePath = '';
include 'includes/header.php';
?>

<section class="form-section">
  <h1>Document Request Form</h1>
  <p class="subtitle">Please fill up all required fields. Your details
    must match your official records. *Required fields.</p>

  <form id="requestForm" action="submit_request.php" method="POST" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
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
        <label for="document_type_trigger">Document Needed *</label>
        <div class="custom-select-wrapper" id="document_type_wrapper">
          <button type="button" class="custom-select-trigger" id="document_type_trigger" aria-haspopup="listbox" aria-expanded="false">
            <span class="custom-select-value is-placeholder">Select Document</span>
            <svg class="custom-select-caret" viewBox="0 0 20 20" fill="none">
              <path d="M5.5 8L10 12.5L14.5 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </button>
          <ul class="custom-select-list" role="listbox" hidden>
            <li class="custom-select-option is-selected" role="option" data-value="">Select Document</li>
            <li class="custom-select-option" role="option" data-value="Certificate of Registration">Certificate of Registration</li>
            <li class="custom-select-option" role="option" data-value="Certificate of Grades">Certificate of Grades</li>
            <li class="custom-select-option" role="option" data-value="Diploma (Copy / Authentication)">Diploma (Copy / Authentication)</li>
          </ul>
          <select id="document_type" name="document_type" class="native-select-hidden" tabindex="-1" aria-hidden="true">
            <option value="">Select Document</option>
            <option>Certificate of Registration</option>
            <option>Certificate of Grades</option>
            <option>Diploma (Copy / Authentication)</option>
          </select>
        </div>
      </div>

      <div class="form-group" id="year_level_group" hidden>
        <label for="year_level_trigger">Year Level *</label>
        <div class="custom-select-wrapper" id="year_level_wrapper">
          <button type="button" class="custom-select-trigger" id="year_level_trigger" aria-haspopup="listbox" aria-expanded="false">
            <span class="custom-select-value is-placeholder">Select Year Level</span>
            <svg class="custom-select-caret" viewBox="0 0 20 20" fill="none">
              <path d="M5.5 8L10 12.5L14.5 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </button>
          <ul class="custom-select-list" role="listbox" hidden>
            <li class="custom-select-option is-selected" role="option" data-value="">Select Year Level</li>
            <li class="custom-select-option" role="option" data-value="1st Year">1st Year</li>
            <li class="custom-select-option" role="option" data-value="2nd Year">2nd Year</li>
            <li class="custom-select-option" role="option" data-value="3rd Year">3rd Year</li>
            <li class="custom-select-option" role="option" data-value="4th Year">4th Year</li>
            <li class="custom-select-option" role="option" data-value="5th Year">5th Year</li>
            <li class="custom-select-option" role="option" data-value="Graduate / Alumni">Graduate / Alumni</li>
          </ul>
          <select id="year_level" name="year_level" class="native-select-hidden" tabindex="-1" aria-hidden="true">
            <option value="">Select Year Level</option>
            <option>1st Year</option>
            <option>2nd Year</option>
            <option>3rd Year</option>
            <option>4th Year</option>
            <option>5th Year</option>
            <option>Graduate / Alumni</option>
          </select>
        </div>
      </div>

      <div class="form-group" id="semester_group" hidden>
        <label for="semester_trigger">Semester *</label>
        <div class="custom-select-wrapper" id="semester_wrapper">
          <button type="button" class="custom-select-trigger" id="semester_trigger" aria-haspopup="listbox" aria-expanded="false">
            <span class="custom-select-value is-placeholder">Select Semester</span>
            <svg class="custom-select-caret" viewBox="0 0 20 20" fill="none">
              <path d="M5.5 8L10 12.5L14.5 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </button>
          <ul class="custom-select-list" role="listbox" hidden>
            <li class="custom-select-option is-selected" role="option" data-value="">Select Semester</li>
            <li class="custom-select-option" role="option" data-value="1st Semester">1st Semester</li>
            <li class="custom-select-option" role="option" data-value="2nd Semester">2nd Semester</li>
            <li class="custom-select-option" role="option" data-value="Summer">Summer</li>
          </ul>
          <select id="semester" name="semester" class="native-select-hidden" tabindex="-1" aria-hidden="true">
            <option value="">Select Semester</option>
            <option>1st Semester</option>
            <option>2nd Semester</option>
            <option>Summer</option>
          </select>
        </div>
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

    <p class="form-note">After submitting, you will receive a
      <strong>Reference Number</strong> — save it to track your request.
    </p>

    <button type="submit" class="btn btn-primary">Submit Request</button>
  </form>
</section>

<?php include 'includes/footer.php'; ?>