document.addEventListener('DOMContentLoaded', function () {
  const statTotal = document.getElementById('stat-total');
  const statPending = document.getElementById('stat-pending');
  const statReady = document.getElementById('stat-ready');

  function bumpStat(el, delta) {
    if (el) el.textContent = String(parseInt(el.textContent, 10) + delta);
  }

  function paintStatusSelect(select) {
    select.className = 'status-select status-select-' + select.value.toLowerCase().replace(/ /g, '-');
  }

  function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  // Show/hide the "Claimed" button for a row based on its current status
  function setActionCell(id, status, reference) {
    const cell = document.getElementById('action-' + id);
    if (!cell) return;
    if (status === 'Ready for Pickup') {
      cell.innerHTML = '<button type="button" class="btn btn-small btn-danger btn-claim" data-id="' +
        id + '" data-reference="' + escapeHtml(reference) + '">Mark claimed</button>';
    } else {
      cell.innerHTML = '';
    }
  }

  // Inline status dropdown -> quick status update, no page reload
  document.querySelectorAll('.status-select').forEach(function (select) {
    paintStatusSelect(select);
    select.dataset.prevStatus = select.value;

    select.addEventListener('change', function () {
      const id = select.dataset.id;
      const newStatus = select.value;
      const prevStatus = select.dataset.prevStatus;
      const row = document.getElementById('row-' + id);
      const reference = row ? row.dataset.reference : '';
      select.disabled = true;

      fetch('quick_status.php', {
        method: 'POST',
        body: new URLSearchParams({ id: id, request_status: newStatus })
      })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          if (json.success) {
            paintStatusSelect(select);
            select.dataset.prevStatus = newStatus;
            setActionCell(id, newStatus, reference);
            applyFilter();

            if (prevStatus !== newStatus) {
              if (prevStatus === 'Pending') bumpStat(statPending, -1);
              if (prevStatus === 'Ready for Pickup') bumpStat(statReady, -1);
              if (newStatus === 'Pending') bumpStat(statPending, 1);
              if (newStatus === 'Ready for Pickup') bumpStat(statReady, 1);
            }
          } else {
            alert(json.message || 'Could not update status.');
            select.value = prevStatus;
          }
          select.disabled = false;
        })
        .catch(function () {
          alert('Network error. Please try again.');
          select.value = prevStatus;
          select.disabled = false;
        });
    });
  });

  // Claimed -> deletes the request. Delegated so it also works on buttons
  // that appear later when a row's status is switched to Ready for Pickup.
  const tableBody = document.querySelector('.admin-table tbody');
  if (tableBody) {
    tableBody.addEventListener('click', function (e) {
      const btn = e.target.closest('.btn-claim');
      if (!btn) return;

      const id = btn.dataset.id;
      const reference = btn.dataset.reference;

      if (!confirm('Mark ' + reference + ' as claimed?\n\nThis will permanently delete the request from the system.')) {
        return;
      }

      btn.disabled = true;

      fetch('claim_request.php', {
        method: 'POST',
        body: new URLSearchParams({ id: id })
      })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          if (json.success) {
            const row = document.getElementById('row-' + id);
            if (row) row.remove();
            bumpStat(statTotal, -1);
            bumpStat(statReady, -1);
          } else {
            alert(json.message || 'Could not mark as claimed.');
            btn.disabled = false;
          }
        })
        .catch(function () {
          alert('Network error. Please try again.');
          btn.disabled = false;
        });
    });
  }
  // ---- Live filtering: type to search, pick a status to narrow ----
  const filterSearch = document.getElementById('filterSearch');
  const filterStatus = document.getElementById('filterStatus');
  const emptyRow = document.querySelector('.filter-empty');

  function applyFilter() {
    if (!tableBody) return;

    const term = (filterSearch ? filterSearch.value : '').trim().toLowerCase();
    const status = filterStatus ? filterStatus.value : '';
    let shown = 0;

    tableBody.querySelectorAll('tr[id^="row-"]').forEach(function (row) {
      const cells = row.querySelectorAll('td');
      const haystack = [
        cells[0] ? cells[0].textContent : '',
        cells[1] ? cells[1].textContent : '',
        cells[2] ? cells[2].textContent : ''
      ].join(' ').toLowerCase();

      const select = row.querySelector('.status-select');
      const rowStatus = select ? select.value : '';

      const matchesTerm = term === '' || haystack.indexOf(term) !== -1;
      const matchesStatus = status === '' || rowStatus === status;
      const visible = matchesTerm && matchesStatus;

      row.hidden = !visible;
      if (visible) shown++;
    });

    if (emptyRow) {
      const filtering = term !== '' || status !== '';
      emptyRow.hidden = !(filtering && shown === 0);
    }
  }

  if (filterSearch) filterSearch.addEventListener('input', applyFilter);
  if (filterStatus) filterStatus.addEventListener('change', applyFilter);
});
