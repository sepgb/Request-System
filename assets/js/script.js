// Custom dropdown + validation for the request form.
// Server-side validation in submit_request.php is the real safety net.
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('requestForm');
  if (!form) return;

  function initCustomSelect(wrapperId) {
    const root = document.getElementById(wrapperId);
    if (!root) return null;

    const trigger = root.querySelector('.custom-select-trigger');
    const valueEl = root.querySelector('.custom-select-value');
    const list = root.querySelector('.custom-select-list');
    const select = root.querySelector('select');
    const options = Array.from(list.querySelectorAll('.custom-select-option'));

    function close() {
      list.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
    }

    function open() {
      document.querySelectorAll('.custom-select-list').forEach(function (l) { l.hidden = true; });
      document.querySelectorAll('.custom-select-trigger').forEach(function (t) { t.setAttribute('aria-expanded', 'false'); });
      list.hidden = false;
      trigger.setAttribute('aria-expanded', 'true');
    }

    function setValue(value, label) {
      select.value = value;
      valueEl.textContent = label;
      valueEl.classList.toggle('is-placeholder', value === '');
      options.forEach(function (opt) {
        opt.classList.toggle('is-selected', opt.dataset.value === value);
      });
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      if (list.hidden) open(); else close();
    });

    options.forEach(function (opt) {
      opt.addEventListener('click', function () {
        setValue(opt.dataset.value, opt.textContent);
        trigger.classList.remove('is-invalid');
        close();
      });
    });

    return {
      select: select,
      trigger: trigger,
      reset: function () {
        setValue('', options[0].textContent);
      }
    };
  }

  document.addEventListener('click', function (e) {
    if (!e.target.closest('.custom-select-wrapper')) {
      document.querySelectorAll('.custom-select-list').forEach(function (l) { l.hidden = true; });
      document.querySelectorAll('.custom-select-trigger').forEach(function (t) { t.setAttribute('aria-expanded', 'false'); });
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.custom-select-list').forEach(function (l) { l.hidden = true; });
      document.querySelectorAll('.custom-select-trigger').forEach(function (t) { t.setAttribute('aria-expanded', 'false'); });
    }
  });

  const documentTypeCustom = initCustomSelect('document_type_wrapper');
  const yearLevelCustom = initCustomSelect('year_level_wrapper');
  const semesterCustom = initCustomSelect('semester_wrapper');

  if (!documentTypeCustom) return;

  const documentType = documentTypeCustom.select;
  const yearGroup = document.getElementById('year_level_group');
  const semesterGroup = document.getElementById('semester_group');

  const academicDocs = ['Certificate of Registration', 'Certificate of Grades'];

  function toggleAcademicFields() {
    const needed = academicDocs.includes(documentType.value);

    yearGroup.hidden = !needed;
    if (!needed && yearLevelCustom) yearLevelCustom.reset();

    semesterGroup.hidden = !needed;
    if (!needed && semesterCustom) semesterCustom.reset();
  }

  documentType.addEventListener('change', toggleAcademicFields);
  toggleAcademicFields();

  form.addEventListener('submit', function (e) {
    const copies = document.getElementById('copies');

    let valid = true;
    let message = '';

    function checkRequired(customSelect, label) {
      if (!customSelect || customSelect.select.value !== '') return;
      valid = false;
      if (!message) message = label + ' is required.';
      customSelect.trigger.classList.add('is-invalid');
    }

    checkRequired(documentTypeCustom, 'Document Needed');
    if (!yearGroup.hidden) checkRequired(yearLevelCustom, 'Year Level');
    if (!semesterGroup.hidden) checkRequired(semesterCustom, 'Semester');

    if (copies && (parseInt(copies.value, 10) < 1 || parseInt(copies.value, 10) > 10)) {
      valid = false;
      if (!message) message = 'Number of copies must be between 1 and 10.';
    }

    if (!valid) {
      e.preventDefault();
      alert(message);
    }
  });
});

// Cancel-request 
document.addEventListener('DOMContentLoaded', function () {
  const openBtn = document.getElementById('openCancelModal');
  const overlay = document.getElementById('cancelRequestOverlay');
  const closeBtn = document.getElementById('closeCancelModal');
  if (!openBtn || !overlay) return;

  openBtn.addEventListener('click', function () { overlay.hidden = false; });
  if (closeBtn) closeBtn.addEventListener('click', function () { overlay.hidden = true; });

  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) overlay.hidden = true;
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') overlay.hidden = true;
  });
});