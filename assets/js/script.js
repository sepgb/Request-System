// Basic client-side validation for the request form.
// Server-side validation in submit_request.php is the real safety net.
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('requestForm');
  if (!form) return;

  const documentType = document.getElementById('document_type');
  const yearGroup = document.getElementById('year_level_group');
  const yearLevel = document.getElementById('year_level');
  const semesterGroup = document.getElementById('semester_group');
  const semester = document.getElementById('semester');

  // Diploma is a one-off record, so year level and semester don't apply.
  const academicDocs = ['Certificate of Registration', 'Certificate of Grades'];

  function toggleAcademicFields() {
    const needed = academicDocs.includes(documentType.value);

    yearGroup.hidden = !needed;
    yearLevel.required = needed;
    if (!needed) yearLevel.value = '';

    semesterGroup.hidden = !needed;
    semester.required = needed;
    if (!needed) semester.value = '';
  }

  if (documentType && yearGroup && yearLevel && semesterGroup && semester) {
    documentType.addEventListener('change', toggleAcademicFields);
    toggleAcademicFields();
  }

  form.addEventListener('submit', function (e) {
    const copies = document.getElementById('copies');

    let valid = true;
    let message = '';

    if (copies && (parseInt(copies.value, 10) < 1 || parseInt(copies.value, 10) > 10)) {
      valid = false;
      message = 'Number of copies must be between 1 and 10.';
    }

    if (!valid) {
      e.preventDefault();
      alert(message);
    }
  });
});
