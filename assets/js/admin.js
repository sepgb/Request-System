document.addEventListener('DOMContentLoaded', function () {
  const statTotal = document.getElementById('stat-total');
  const statPending = document.getElementById('stat-pending');
  const statReady = document.getElementById('stat-ready');
  const sidebarCountAll = document.getElementById('count-all');
  const sidebarCountPending = document.getElementById('count-pending');
  const sidebarCountProcessing = document.getElementById('count-processing');
  const sidebarCountReady = document.getElementById('count-ready');
  const sidebarCountRejected = document.getElementById('count-rejected');

  function bumpStat(el, delta) {
    if (el) el.textContent = String(parseInt(el.textContent, 10) + delta);
  }

  function paintStatusSelect(select) {
    var cls = 'status-select-' + select.value.toLowerCase().replace(/ /g, '-');

    Array.prototype.slice.call(select.classList).forEach(function (c) {
      if (c.indexOf('status-select-') === 0 && c !== 'status-select-native') {
        select.classList.remove(c);
      }
    });
    select.classList.add('status-select');
    select.classList.add(cls);

    if (select._trigger) {
      select._trigger.className = 'status-select-trigger ' + cls;
      select._trigger.querySelector('.status-select-trigger-label').textContent = select.value;
    }

    if (select._list) {
      select._list.querySelectorAll('.status-select-option').forEach(function (opt) {
        var selected = opt.dataset.value === select.value;
        opt.classList.toggle('is-selected', selected);
        opt.setAttribute('aria-selected', selected ? 'true' : 'false');
      });
    }
  }

  // Build a fully custom dropdown UI for a status <select>, so the popup
  // list can be styled precisely (native <select> popups can't be). The
  // original <select> stays in the DOM (visually hidden) as the source of
  // truth for .value / dataset / the 'change' event, so every bit of
  // existing update/filter logic below keeps working untouched.
  function enhanceStatusSelect(select) {
    var wrapper = document.createElement('div');
    wrapper.className = 'status-select-wrapper';
    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);
    select.classList.add('status-select-native');

    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'status-select-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    if (select.getAttribute('aria-label')) {
      trigger.setAttribute('aria-label', select.getAttribute('aria-label'));
    }
    trigger.innerHTML =
      '<span class="status-select-trigger-label"></span>' +
      '<svg class="status-select-trigger-caret" viewBox="0 0 20 20" fill="none">' +
      '<path d="M5.5 8L10 12.5L14.5 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>' +
      '</svg>';
    wrapper.appendChild(trigger);

    var list = document.createElement('div');
    list.className = 'status-select-list';
    list.setAttribute('role', 'listbox');
    list.hidden = true;

    Array.prototype.forEach.call(select.options, function (opt) {
      var item = document.createElement('div');
      item.className = 'status-select-option';
      item.setAttribute('role', 'option');
      item.dataset.value = opt.value;
      item.textContent = opt.textContent.trim();
      list.appendChild(item);
    });

    // Appended to <body> so the popup can never be clipped by the
    // table's horizontal-scroll container, regardless of which row it's in.
    document.body.appendChild(list);

    select._trigger = trigger;
    select._list = list;

    function positionList() {
      var rect = trigger.getBoundingClientRect();
      list.style.position = 'fixed';
      list.style.top = (rect.bottom + 6) + 'px';
      list.style.left = rect.left + 'px';
      list.style.minWidth = rect.width + 'px';
    }

    function closeList() {
      list.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
    }

    function openList() {
      document.querySelectorAll('.status-select-list').forEach(function (l) {
        if (l !== list) l.hidden = true;
      });
      positionList();
      list.hidden = false;
      trigger.setAttribute('aria-expanded', 'true');
    }

    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      if (trigger.disabled) return;
      if (list.hidden) openList(); else closeList();
    });

    list.addEventListener('click', function (e) {
      var item = e.target.closest('.status-select-option');
      if (!item) return;
      closeList();
      if (item.dataset.value === select.value) return;
      select.value = item.dataset.value;
      select.dispatchEvent(new Event('change'));
    });

    document.addEventListener('click', function (e) {
      if (!list.hidden && !e.target.closest('.status-select-wrapper') && !e.target.closest('.status-select-list')) {
        closeList();
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !list.hidden) closeList();
    });

    window.addEventListener('scroll', function () {
      if (!list.hidden) closeList();
    }, true);

    window.addEventListener('resize', function () {
      if (!list.hidden) closeList();
    });

    paintStatusSelect(select);
  }

  // Same idea as enhanceStatusSelect above (hide the real <select>, build a
  // fully custom trigger+list so the popup can actually be themed — native
  // <select> option lists can't be styled), but plain/unstyled-by-value,
  // for ordinary filter dropdowns like "Document" rather than status pills.
  function enhanceFilterSelect(select) {
    var wrapper = document.createElement('div');
    wrapper.className = 'table-filter-select-wrapper';
    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);
    select.classList.add('status-select-native'); // reuses the same visually-hidden recipe

    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'table-filter-select-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    if (select.getAttribute('aria-label')) {
      trigger.setAttribute('aria-label', select.getAttribute('aria-label'));
    }
    trigger.innerHTML =
      '<span class="table-filter-select-label"></span>' +
      '<svg class="table-filter-select-caret" viewBox="0 0 20 20" fill="none">' +
      '<path d="M5.5 8L10 12.5L14.5 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>' +
      '</svg>';
    wrapper.appendChild(trigger);

    var list = document.createElement('div');
    list.className = 'table-filter-select-list';
    list.setAttribute('role', 'listbox');
    list.hidden = true;
    wrapper.appendChild(list);

    Array.prototype.forEach.call(select.options, function (opt) {
      var item = document.createElement('div');
      item.className = 'table-filter-select-option';
      item.setAttribute('role', 'option');
      item.dataset.value = opt.value;
      item.textContent = opt.textContent.trim();
      list.appendChild(item);
    });

    function paint() {
      var selectedText = select.options[select.selectedIndex]
        ? select.options[select.selectedIndex].textContent.trim()
        : '';
      trigger.querySelector('.table-filter-select-label').textContent = selectedText;
      list.querySelectorAll('.table-filter-select-option').forEach(function (opt) {
        var selected = opt.dataset.value === select.value;
        opt.classList.toggle('is-selected', selected);
        opt.setAttribute('aria-selected', selected ? 'true' : 'false');
      });
    }

    function closeList() {
      list.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
    }

    function openList() {
      document.querySelectorAll('.table-filter-select-list, .status-select-list').forEach(function (l) {
        if (l !== list) l.hidden = true;
      });
      list.hidden = false;
      trigger.setAttribute('aria-expanded', 'true');
    }

    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      if (list.hidden) openList(); else closeList();
    });

    list.addEventListener('click', function (e) {
      var item = e.target.closest('.table-filter-select-option');
      if (!item) return;
      closeList();
      if (item.dataset.value === select.value) return;
      select.value = item.dataset.value;
      paint();
      select.dispatchEvent(new Event('change'));
    });

    document.addEventListener('click', function (e) {
      if (!list.hidden && !e.target.closest('.table-filter-select-wrapper')) {
        closeList();
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !list.hidden) closeList();
    });

    select.addEventListener('change', paint);
    paint();
  }

  function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  // Inline status dropdown -> quick status update, no page reload
  document.querySelectorAll('.status-select').forEach(function (select) {
    enhanceStatusSelect(select);
    select.dataset.prevStatus = select.value;

    select.addEventListener('change', function () {
      const id = select.dataset.id;
      const newStatus = select.value;
      const prevStatus = select.dataset.prevStatus;

      select.disabled = true;
      if (select._trigger) select._trigger.disabled = true;

      fetch('quick_status.php', {
        method: 'POST',
        body: new URLSearchParams({ id: id, request_status: newStatus, csrf_token: window.CSRF_TOKEN })
      })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          if (json.success) {
            paintStatusSelect(select);
            select.dataset.prevStatus = newStatus;
            applyFilter();

            if (prevStatus !== newStatus) {
              if (prevStatus === 'Pending') {
                bumpStat(statPending, -1);
                bumpStat(sidebarCountPending, -1);
              }
              if (prevStatus === 'Processing') {
                bumpStat(sidebarCountProcessing, -1);
              }
              if (prevStatus === 'Ready for Pickup') {
                bumpStat(statReady, -1);
                bumpStat(sidebarCountReady, -1);
              }
              if (prevStatus === 'Rejected') {
                bumpStat(sidebarCountRejected, -1);
              }
              if (newStatus === 'Pending') {
                bumpStat(statPending, 1);
                bumpStat(sidebarCountPending, 1);
              }
              if (newStatus === 'Processing') {
                bumpStat(sidebarCountProcessing, 1);
              }
              if (newStatus === 'Ready for Pickup') {
                bumpStat(statReady, 1);
                bumpStat(sidebarCountReady, 1);
              }
              if (newStatus === 'Rejected') {
                bumpStat(sidebarCountRejected, 1);
              }
            }
          } else {
            alert(json.message || 'Could not update status.');
            select.value = prevStatus;
            paintStatusSelect(select);
          }
          select.disabled = false;
          if (select._trigger) select._trigger.disabled = false;
        })
        .catch(function () {
          alert('Network error. Please try again.');
          select.value = prevStatus;
          paintStatusSelect(select);
          select.disabled = false;
          if (select._trigger) select._trigger.disabled = false;
        });
    });
  });

  // ---- Custom "mark as claimed" confirmation modal ----
  const claimModalOverlay = document.getElementById('claimModalOverlay');
  const claimModalText = document.getElementById('claimModalText');
  const claimModalConfirm = document.getElementById('claimModalConfirm');
  const claimModalCancel = document.getElementById('claimModalCancel');
  let pendingClaim = null; // { id, btn }

  function openClaimModal(id, reference, fullName, btn) {
    pendingClaim = { id: id, btn: btn };

    if (claimModalText) {
      claimModalText.textContent =
        'Mark ' + reference + ' (' + fullName + ') as claimed? This will permanently delete the request from the system.';
    }

    if (claimModalOverlay) {
      claimModalOverlay.hidden = false;
    }
  }

  function closeClaimModal() {
    pendingClaim = null;
    if (claimModalOverlay) claimModalOverlay.hidden = true;
  }

  function doClaim(id, btn) {
    if (btn) btn.disabled = true;

    fetch('claim_request.php', {
      method: 'POST',
      body: new URLSearchParams({ id: id, csrf_token: window.CSRF_TOKEN })
    })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        if (json.success) {
          const row = document.getElementById('row-' + id);
          if (row) row.remove();
          bumpStat(statTotal, -1);
          bumpStat(statReady, -1);
          bumpStat(sidebarCountAll, -1);
          bumpStat(sidebarCountReady, -1);
        } else {
          alert(json.message || 'Could not mark as claimed.');
          if (btn) btn.disabled = false;
        }
      })
      .catch(function () {
        alert('Network error. Please try again.');
        if (btn) btn.disabled = false;
      });
  }

  if (claimModalConfirm) {
    claimModalConfirm.addEventListener('click', function () {
      if (!pendingClaim) return;
      const claim = pendingClaim;
      closeClaimModal();
      doClaim(claim.id, claim.btn);
    });
  }

  if (claimModalCancel) {
    claimModalCancel.addEventListener('click', closeClaimModal);
  }

  if (claimModalOverlay) {
    claimModalOverlay.addEventListener('click', function (e) {
      if (e.target === claimModalOverlay) closeClaimModal();
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && claimModalOverlay && !claimModalOverlay.hidden) {
      closeClaimModal();
    }
  });

  const tableBody = document.querySelector('.admin-table tbody');

  // ---- Request details modal: double-click a row to see it like a receipt ----
  const requestDetailsOverlay = document.getElementById('requestDetailsOverlay');
  const requestDetailsClose = document.getElementById('requestDetailsClose');
  const rdMarkClaimed = document.getElementById('rdMarkClaimed');
  const rdDelete = document.getElementById('rdDelete');
  const rdTitle = document.getElementById('requestDetailsTitle');
  const rdStudentNo = document.getElementById('rdStudentNo');
  const rdName = document.getElementById('rdName');
  const rdDocument = document.getElementById('rdDocument');
  const rdYearLevel = document.getElementById('rdYearLevel');
  const rdSemester = document.getElementById('rdSemester');
  const rdClaimDate = document.getElementById('rdClaimDate');
  const rdStatus = document.getElementById('rdStatus');
  const rdDateRequested = document.getElementById('rdDateRequested');
  const rdPurpose = document.getElementById('rdPurpose');
  const rdPurposeRow = document.getElementById('rdPurposeRow');

  let currentDetailsRequest = null;

  function openRequestDetails(row) {
    const cells = row.querySelectorAll('td');
    const select = row.querySelector('.status-select');
    const status = select ? select.value : (cells[7] ? cells[7].textContent.trim() : '');
    const statusClass = 'status-select-' + status.toLowerCase().replace(/ /g, '-');
    const id = row.id.replace('row-', '');
    const reference = row.dataset.reference || '';
    const fullName = row.dataset.fullName || (cells[2] ? cells[2].textContent.trim() : '');

    currentDetailsRequest = { id: id, reference: reference, fullName: fullName };

    if (rdTitle) rdTitle.textContent = reference;
    if (rdDateRequested) rdDateRequested.textContent = row.dataset.dateRequested || '\u2014';
    if (rdStudentNo) rdStudentNo.textContent = cells[1] ? cells[1].textContent.trim() : '';
    if (rdName) rdName.textContent = fullName;
    if (rdDocument) rdDocument.textContent = cells[3] ? cells[3].textContent.trim() : '';
    if (rdYearLevel) rdYearLevel.textContent = cells[4] ? cells[4].textContent.trim() : '';
    if (rdSemester) rdSemester.textContent = cells[5] ? cells[5].textContent.trim() : '';
    if (rdClaimDate) rdClaimDate.textContent = cells[6] ? cells[6].textContent.trim() : '';
    if (rdStatus) {
      rdStatus.textContent = status;
      rdStatus.className = 'request-details-value ' + statusClass;
    }
    if (rdPurpose && rdPurposeRow) {
      const purpose = row.dataset.purpose || '';
      rdPurpose.textContent = purpose;
      rdPurposeRow.hidden = purpose === '';
    }
    if (rdMarkClaimed) {
      rdMarkClaimed.hidden = status !== 'Ready for Pickup';
    }
    if (rdDelete) {
      rdDelete.hidden = status !== 'Rejected';
    }

    if (requestDetailsOverlay) requestDetailsOverlay.hidden = false;
  }

  function closeRequestDetails() {
    currentDetailsRequest = null;
    if (requestDetailsOverlay) requestDetailsOverlay.hidden = true;
  }

  if (tableBody) {
    tableBody.addEventListener('dblclick', function (e) {
      if (e.target.closest('a, button, .status-select-wrapper')) return;
      const row = e.target.closest('tr[id^="row-"]');
      if (row) openRequestDetails(row);
    });
  }

  if (requestDetailsClose) {
    requestDetailsClose.addEventListener('click', closeRequestDetails);
  }

  if (rdMarkClaimed) {
    rdMarkClaimed.addEventListener('click', function () {
      if (!currentDetailsRequest) return;
      const req = currentDetailsRequest;
      closeRequestDetails();
      openClaimModal(req.id, req.reference, req.fullName, null);
    });
  }

  // ---- Delete a rejected request (confirmation modal) ----
  // Only Rejected requests can be deleted; delete_request.php enforces that too.
  const deleteModalOverlay = document.getElementById('deleteModalOverlay');
  const deleteModalText = document.getElementById('deleteModalText');
  const deleteModalConfirm = document.getElementById('deleteModalConfirm');
  const deleteModalCancel = document.getElementById('deleteModalCancel');
  let pendingDelete = null; // { id }

  function openDeleteModal(id, reference, fullName) {
    pendingDelete = { id: id };

    if (deleteModalText) {
      deleteModalText.textContent =
        'Delete ' + reference + ' (' + fullName + ')? This will permanently delete the rejected request from the system.';
    }

    if (deleteModalOverlay) {
      deleteModalOverlay.hidden = false;
    }
  }

  function closeDeleteModal() {
    pendingDelete = null;
    if (deleteModalOverlay) deleteModalOverlay.hidden = true;
  }

  function doDelete(id) {
    fetch('delete_request.php', {
      method: 'POST',
      body: new URLSearchParams({ id: id, csrf_token: window.CSRF_TOKEN })
    })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        if (json.success) {
          const row = document.getElementById('row-' + id);
          if (row) row.remove();
          bumpStat(statTotal, -1);
          bumpStat(sidebarCountAll, -1);
          bumpStat(sidebarCountRejected, -1);
        } else {
          alert(json.message || 'Could not delete the request.');
        }
      })
      .catch(function () {
        alert('Network error. Please try again.');
      });
  }

  if (rdDelete) {
    rdDelete.addEventListener('click', function () {
      if (!currentDetailsRequest) return;
      const req = currentDetailsRequest;
      closeRequestDetails();
      openDeleteModal(req.id, req.reference, req.fullName);
    });
  }

  if (deleteModalConfirm) {
    deleteModalConfirm.addEventListener('click', function () {
      if (!pendingDelete) return;
      const del = pendingDelete;
      closeDeleteModal();
      doDelete(del.id);
    });
  }

  if (deleteModalCancel) {
    deleteModalCancel.addEventListener('click', closeDeleteModal);
  }

  if (deleteModalOverlay) {
    deleteModalOverlay.addEventListener('click', function (e) {
      if (e.target === deleteModalOverlay) closeDeleteModal();
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && deleteModalOverlay && !deleteModalOverlay.hidden) {
      closeDeleteModal();
    }
  });

  if (requestDetailsOverlay) {
    requestDetailsOverlay.addEventListener('click', function (e) {
      if (e.target === requestDetailsOverlay) closeRequestDetails();
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && requestDetailsOverlay && !requestDetailsOverlay.hidden) {
      closeRequestDetails();
    }
  });

  // ---- Search / Document / Date filters ----
  // Filtering now runs as real SQL on the server (needed for pagination to
  // work correctly), so these just submit the form — the sidebar status
  // links and Prev/Next are plain <a> links doing the same thing.
  const filterForm = document.getElementById('filterForm');
  const filterDocument = document.getElementById('filterDocument');
  if (filterDocument) enhanceFilterSelect(filterDocument);
  const filterDatePeriod = document.getElementById('filterDatePeriod');
  if (filterDatePeriod) enhanceFilterSelect(filterDatePeriod);

  if (filterDocument) filterDocument.addEventListener('change', function () {
    if (filterForm) filterForm.submit();
  });
  if (filterDatePeriod) filterDatePeriod.addEventListener('change', function () {
    if (filterForm) filterForm.submit();
  });
});
// ---- Profile dropdown ----
const profileTrigger = document.getElementById('profileTrigger');
const profileDropdown = document.getElementById('profileDropdown');

function closeProfileDropdown() {
  if (!profileDropdown) return;
  profileDropdown.hidden = true;
  if (profileTrigger) profileTrigger.setAttribute('aria-expanded', 'false');
}

if (profileTrigger && profileDropdown) {
  profileTrigger.addEventListener('click', function (e) {
    e.stopPropagation();
    const isOpen = !profileDropdown.hidden;
    if (typeof closeNotifDropdown === 'function') closeNotifDropdown();
    if (isOpen) {
      closeProfileDropdown();
    } else {
      profileDropdown.hidden = false;
      profileTrigger.setAttribute('aria-expanded', 'true');
    }
  });

  document.addEventListener('click', function (e) {
    if (!profileDropdown.hidden && !e.target.closest('#profileMenu')) {
      closeProfileDropdown();
    }
  });
}

// ---- Edit profile modal ----
const editProfileOverlay = document.getElementById('editProfileOverlay');
const editProfileForm = document.getElementById('editProfileForm');
const editProfileAlert = document.getElementById('editProfileAlert');
const editProfileSave = document.getElementById('editProfileSave');
const openEditProfileBtn = document.getElementById('openEditProfile');
const editProfileClose = document.getElementById('editProfileClose');
const editProfileCancel = document.getElementById('editProfileCancel');

// ---- Notification bell dropdown ----
const notifTrigger = document.getElementById('notifTrigger');
const notifDropdown = document.getElementById('notifDropdown');

function closeNotifDropdown() {
  if (!notifDropdown) return;
  notifDropdown.hidden = true;
  exitNotifSelectMode();
  closeNotifMenu();
  if (notifTrigger) notifTrigger.setAttribute('aria-expanded', 'false');
}

if (notifTrigger && notifDropdown) {
  notifTrigger.addEventListener('click', function (e) {
    e.stopPropagation();
    const isOpen = !notifDropdown.hidden;
    closeProfileDropdown();
    if (isOpen) {
      closeNotifDropdown();
    } else {
      notifDropdown.hidden = false;
      notifTrigger.setAttribute('aria-expanded', 'true');
    }
  });

  document.addEventListener('click', function (e) {
    if (!notifDropdown.hidden && !e.target.closest('#notifMenu')) {
      closeNotifDropdown();
    }
  });
}

// ---- Notification badge ----
function updateNotifBadge(count) {
  const badge = document.getElementById('notifBadge');
  if (count > 0) {
    const label = count > 9 ? '9+' : String(count);
    if (badge) {
      badge.textContent = label;
    } else if (notifTrigger) {
      const span = document.createElement('span');
      span.className = 'notif-badge';
      span.id = 'notifBadge';
      span.textContent = label;
      notifTrigger.appendChild(span);
    }
  } else if (badge) {
    badge.remove();
  }

  // "Mark all as read" only makes sense while something is unread
  const markAll = document.getElementById('notifMarkAllRead');
  if (markAll) markAll.disabled = !(count > 0);
}

// ---- Notification tabs (All / Unread) ----
let notifFilter = 'all';

function applyNotifFilter() {
  const list = document.getElementById('notifList');
  if (!list) return;

  let anyShown = false;
  list.querySelectorAll('.notif-group').forEach(function (group) {
    let shown = 0;
    group.querySelectorAll('.activity-item').forEach(function (item) {
      const show = notifFilter === 'all' || item.dataset.read === '0';
      item.hidden = !show;
      if (show) shown++;
    });
    group.hidden = shown === 0;          // hide "Today" / "Earlier" when empty
    if (shown > 0) anyShown = true;
  });

  const hasItems = !!list.querySelector('.activity-item');
  const empty = document.getElementById('notifEmptyUnread');
  if (empty) empty.hidden = !(notifFilter === 'unread' && !anyShown && hasItems);

  // Anything the filter just hid can't stay selected for deletion
  list.querySelectorAll('.activity-item[hidden] .notif-check:checked').forEach(function (cb) {
    cb.checked = false;
    cb.closest('.activity-item').classList.remove('is-selected');
  });
  updateNotifSelection();
}

document.querySelectorAll('.notif-tab').forEach(function (tab) {
  tab.addEventListener('click', function () {
    notifFilter = tab.dataset.filter;
    document.querySelectorAll('.notif-tab').forEach(function (t) {
      const active = t === tab;
      t.classList.toggle('is-active', active);
      t.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    applyNotifFilter();
  });
});

// ---- Three-dot menu (Mark all as read / Delete notifications) ----
const notifMoreBtn = document.getElementById('notifMoreBtn');
const notifMoreMenu = document.getElementById('notifMoreMenu');

function closeNotifMenu() {
  if (!notifMoreMenu) return;
  notifMoreMenu.hidden = true;
  if (notifMoreBtn) notifMoreBtn.setAttribute('aria-expanded', 'false');
}

if (notifMoreBtn && notifMoreMenu) {
  notifMoreBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    const willOpen = notifMoreMenu.hidden;
    notifMoreMenu.hidden = !willOpen;
    notifMoreBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
  });

  document.addEventListener('click', function (e) {
    if (!notifMoreMenu.hidden && !e.target.closest('.notif-more-wrap')) {
      closeNotifMenu();
    }
  });
}

// ---- Notification items: double-click to open the request or mark read ----
document.addEventListener('dblclick', function (e) {
  if (notifSelecting) return;            // selecting for delete — don't open anything
  const item = e.target.closest('.activity-item');
  if (!item) return;

  const notifId = item.dataset.notifId;
  const reference = item.dataset.reference;

  if (reference) {
    // Still-existing request — jump to the dashboard and highlight it.
    // The server marks this notification read when it sees read_notif.
    const url = 'dashboard.php?highlight=' + encodeURIComponent(reference) +
      (notifId ? '&read_notif=' + encodeURIComponent(notifId) : '');
    window.location.href = url;
    return;
  }

  // No linked request (claimed / deleted) — just mark it read in place.
  if (!notifId || item.dataset.read === '1') return;
  fetch('mark_notifications_read.php', {
    method: 'POST',
    body: new URLSearchParams({ id: notifId, csrf_token: window.CSRF_TOKEN })
  })
    .then(function (res) { return res.json(); })
    .then(function (json) {
      if (json.success) {
        item.classList.remove('is-unread');
        item.dataset.read = '1';
        updateNotifBadge(json.unread);
        applyNotifFilter();
      }
    });
});

// ---- Mark all as read ----
const notifMarkAllBtn = document.getElementById('notifMarkAllRead');
if (notifMarkAllBtn) {
  notifMarkAllBtn.addEventListener('click', function () {
    fetch('mark_notifications_read.php', {
      method: 'POST',
      body: new URLSearchParams({ csrf_token: window.CSRF_TOKEN })
    })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        if (json.success) {
          document.querySelectorAll('.activity-item.is-unread').forEach(function (el) {
            el.classList.remove('is-unread');
            el.dataset.read = '1';
          });
          updateNotifBadge(json.unread);
          notifMarkAllBtn.disabled = true;
          closeNotifMenu();
          applyNotifFilter();
        }
      });
  });
}

// ---- Delete notifications (select mode) ----
// Deleting only hides a notification from the bell (is_dismissed); the audit
// record stays, so Statistics is unaffected.
const notifDeleteModeBtn = document.getElementById('notifDeleteMode');
const notifSelectAllWrap = document.getElementById('notifSelectAllWrap');
const notifSelectAll = document.getElementById('notifSelectAll');
const notifDeleteBar = document.getElementById('notifDeleteBar');
const notifDeleteConfirm = document.getElementById('notifDeleteConfirm');
const notifCancelDelete = document.getElementById('notifCancelDelete');
let notifSelecting = false;

function visibleNotifChecks() {
  return Array.from(document.querySelectorAll('#notifList .activity-item:not([hidden]) .notif-check'));
}

function updateNotifSelection() {
  const checks = visibleNotifChecks();
  const selected = checks.filter(function (c) { return c.checked; }).length;

  if (notifSelectAll) {
    notifSelectAll.checked = checks.length > 0 && selected === checks.length;
    notifSelectAll.indeterminate = selected > 0 && selected < checks.length;
    notifSelectAll.disabled = checks.length === 0;
  }
  if (notifDeleteConfirm) {
    notifDeleteConfirm.disabled = selected === 0;
    notifDeleteConfirm.textContent = selected > 0 ? 'Delete (' + selected + ')' : 'Delete';
  }
}

function enterNotifSelectMode() {
  if (!notifDropdown) return;
  notifSelecting = true;
  notifDropdown.classList.add('is-selecting');
  if (notifSelectAllWrap) notifSelectAllWrap.hidden = false;
  if (notifDeleteBar) notifDeleteBar.hidden = false;
  closeNotifMenu();
  updateNotifSelection();
}

function exitNotifSelectMode() {
  notifSelecting = false;
  if (notifDropdown) notifDropdown.classList.remove('is-selecting');
  if (notifSelectAllWrap) notifSelectAllWrap.hidden = true;
  if (notifDeleteBar) notifDeleteBar.hidden = true;
  document.querySelectorAll('#notifList .notif-check').forEach(function (cb) {
    cb.checked = false;
    const item = cb.closest('.activity-item');
    if (item) item.classList.remove('is-selected');
  });
  updateNotifSelection();
}

// Shows "No notifications." once nothing is left, and keeps the menu item in sync
function refreshNotifEmpty() {
  const list = document.getElementById('notifList');
  if (!list) return;
  const hasItems = !!list.querySelector('.activity-item');
  let emptyAll = document.getElementById('notifEmptyAll');
  if (!hasItems && !emptyAll) {
    emptyAll = document.createElement('p');
    emptyAll.className = 'stats-empty';
    emptyAll.id = 'notifEmptyAll';
    emptyAll.textContent = 'No notifications.';
    list.appendChild(emptyAll);
  } else if (hasItems && emptyAll) {
    emptyAll.remove();
  }
  if (notifDeleteModeBtn) notifDeleteModeBtn.disabled = !hasItems;
}

if (notifDeleteModeBtn) {
  notifDeleteModeBtn.addEventListener('click', function () {
    if (!notifDeleteModeBtn.disabled) enterNotifSelectMode();
  });
}

if (notifCancelDelete) {
  notifCancelDelete.addEventListener('click', exitNotifSelectMode);
}

// Click anywhere on a notification to tick / untick it while selecting
document.addEventListener('click', function (e) {
  if (!notifSelecting) return;
  const item = e.target.closest('#notifList .activity-item');
  if (!item) return;
  const cb = item.querySelector('.notif-check');
  if (!cb) return;
  if (e.target !== cb) cb.checked = !cb.checked;   // clicking the box itself already toggled it
  item.classList.toggle('is-selected', cb.checked);
  updateNotifSelection();
});

// "All" — selects every notification currently shown in the active tab
if (notifSelectAll) {
  notifSelectAll.addEventListener('change', function () {
    visibleNotifChecks().forEach(function (cb) {
      cb.checked = notifSelectAll.checked;
      cb.closest('.activity-item').classList.toggle('is-selected', cb.checked);
    });
    updateNotifSelection();
  });
}

if (notifDeleteConfirm) {
  notifDeleteConfirm.addEventListener('click', function () {
    const items = Array.from(document.querySelectorAll('#notifList .activity-item'))
      .filter(function (item) {
        const cb = item.querySelector('.notif-check');
        return cb && cb.checked && !item.hidden;
      });
    const ids = items.map(function (item) { return item.dataset.notifId; }).filter(Boolean);
    if (ids.length === 0) return;

    notifDeleteConfirm.disabled = true;
    fetch('mark_notifications_read.php', {
      method: 'POST',
      body: new URLSearchParams({ action: 'delete', ids: ids.join(','), csrf_token: window.CSRF_TOKEN })
    })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        if (!json.success) {
          alert(json.message || 'Could not delete notifications.');
          updateNotifSelection();
          return;
        }
        items.forEach(function (item) { item.remove(); });
        document.querySelectorAll('#notifList .notif-group').forEach(function (group) {
          if (!group.querySelector('.activity-item')) group.remove();   // drop empty Today / Earlier
        });
        updateNotifBadge(json.unread);
        exitNotifSelectMode();
        refreshNotifEmpty();
        applyNotifFilter();
      })
      .catch(function () {
        alert('Could not delete notifications. Please try again.');
        updateNotifSelection();
      });
  });
}

// ---- Highlight a request row linked from a notification (dashboard only) ----
(function () {
  const params = new URLSearchParams(window.location.search);
  const ref = params.get('highlight');
  if (!ref) return;

  const table = document.querySelector('.admin-table');
  const row = table ? table.querySelector('tr[data-reference="' + CSS.escape(ref) + '"]') : null;
  if (row) {
    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    row.classList.add('row-highlight');
    setTimeout(function () {
      row.classList.remove('row-highlight');
    }, 4000);
  }

  // Clean the URL so refreshing doesn't re-highlight / re-mark-read
  params.delete('highlight');
  params.delete('read_notif');
  const qs = params.toString();
  window.history.replaceState({}, '', window.location.pathname + (qs ? '?' + qs : ''));
})();

// ---- Audit log popup ----
const auditLogOverlay = document.getElementById('auditLogOverlay');
const openAuditLogBtn = document.getElementById('openAuditLogModal');
const auditLogClose = document.getElementById('auditLogClose');

function openAuditLogModal() {
  closeNotifDropdown();
  if (auditLogOverlay) auditLogOverlay.hidden = false;
}

function closeAuditLogModal() {
  if (auditLogOverlay) auditLogOverlay.hidden = true;
}

if (openAuditLogBtn) openAuditLogBtn.addEventListener('click', openAuditLogModal);
if (auditLogClose) auditLogClose.addEventListener('click', closeAuditLogModal);

if (auditLogOverlay) {
  auditLogOverlay.addEventListener('click', function (e) {
    if (e.target === auditLogOverlay) closeAuditLogModal();
  });
}
const editProfilePhotoBtn = document.getElementById('editProfilePhotoBtn');
const editProfilePhotoInput = document.getElementById('editProfilePhotoInput');
const editProfileAvatarEl = document.getElementById('editProfileAvatar');
let originalAvatarHtml = editProfileAvatarEl ? editProfileAvatarEl.innerHTML : '';

// showToast / showEditProfileAlert live outside the DOMContentLoaded block, so they can't
// see the escapeHtml defined inside it — without this copy they threw a ReferenceError.
function escapeHtml(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function showToast(type, message) {
  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    document.body.appendChild(container);
  }

  container.style.position = 'fixed';
  container.style.top = '24px';
  container.style.left = '50%';
  container.style.right = 'auto';
  container.style.transform = 'translateX(-50%)';
  container.style.zIndex = '9999';
  container.style.display = 'flex';
  container.style.flexDirection = 'column';
  container.style.alignItems = 'center';
  container.style.gap = '10px';
  container.style.pointerEvents = 'none';
  container.style.margin = '0';
  container.style.padding = '0';

  const toast = document.createElement('div');
  toast.style.display = 'flex';
  toast.style.alignItems = 'center';
  toast.style.justifyContent = 'center';
  toast.style.textAlign = 'center';
  toast.style.gap = '10px';
  toast.style.maxWidth = '360px';
  toast.style.padding = '10px 20px';
  toast.style.borderRadius = '12px';
  toast.style.fontFamily = "'Inter', sans-serif";
  toast.style.fontSize = '0.86rem';
  toast.style.fontWeight = '500';
  toast.style.lineHeight = '1.4';
  toast.style.boxShadow = '0 16px 40px rgba(0, 0, 0, 0.45)';
  toast.style.pointerEvents = 'auto';
  toast.style.opacity = '0';
  toast.style.transform = 'translateY(-10px)';
  toast.style.transition = 'opacity 0.25s ease, transform 0.25s ease';

  if (type === 'success') {
    toast.style.background = '#12241b';
    toast.style.border = '1px solid rgba(90, 199, 140, 0.4)';
    toast.style.color = '#8fe0b4';
  } else {
    toast.style.background = 'rgba(214, 84, 84, 0.14)';
    toast.style.border = '1px solid rgba(214, 84, 84, 0.35)';
    toast.style.color = '#f4b6b6';
  }

  const iconMarkup = type === 'success'
    ? '<svg viewBox="0 0 24 24" fill="none" style="width:19px;height:19px;flex:0 0 auto;"><path d="M4 12.5 L9.5 18 L20 6.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    : '<svg viewBox="0 0 24 24" fill="none" style="width:19px;height:19px;flex:0 0 auto;"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 8V13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="16.2" r="1" fill="currentColor"/></svg>';

  toast.innerHTML = iconMarkup + '<span>' + escapeHtml(message) + '</span>';
  container.appendChild(toast);

  requestAnimationFrame(function () {
    toast.style.opacity = '1';
    toast.style.transform = 'translateY(0)';
  });

  setTimeout(function () {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(-10px)';
    setTimeout(function () { toast.remove(); }, 200);
  }, 3000);
}
function showEditProfileAlert(type, message) {
  if (!editProfileAlert) return;
  editProfileAlert.innerHTML = '<div class="edit-profile-alert edit-profile-alert-' + type + '">' +
    escapeHtml(message) + '</div>';
}

function openEditProfileModal() {
  closeProfileDropdown();
  if (editProfileAlert) editProfileAlert.innerHTML = '';
  if (editProfileOverlay) editProfileOverlay.hidden = false;
}
function openEditProfileModal() {
  closeProfileDropdown();
  if (editProfileAlert) editProfileAlert.innerHTML = '';
  if (editProfileOverlay) editProfileOverlay.hidden = false;
}

function closeEditProfileModal() {
  if (editProfileOverlay) editProfileOverlay.hidden = true;

  // Discard anything typed/selected but not saved, so reopening (or the
  // next failed-save state) doesn't still show it.
  if (editProfileForm) {
    editProfileForm.reset();
    editProfileForm.querySelectorAll('.is-invalid').forEach(function (el) {
      el.classList.remove('is-invalid');
    });
    editProfileForm.querySelectorAll('.edit-profile-field-error').forEach(function (el) {
      el.remove();
    });
  }
  if (editProfileAlert) editProfileAlert.innerHTML = '';
  if (editProfileAvatarEl) editProfileAvatarEl.innerHTML = originalAvatarHtml;
}

if (openEditProfileBtn) openEditProfileBtn.addEventListener('click', openEditProfileModal);
if (editProfileClose) editProfileClose.addEventListener('click', closeEditProfileModal);
if (editProfileCancel) editProfileCancel.addEventListener('click', closeEditProfileModal);

if (editProfilePhotoBtn && editProfilePhotoInput) {
  editProfilePhotoBtn.addEventListener('click', function () {
    editProfilePhotoInput.click();
  });

  editProfilePhotoInput.addEventListener('change', function () {
    const file = editProfilePhotoInput.files[0];
    if (!file) return;

    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (allowedTypes.indexOf(file.type) === -1) {
      showToast('error', 'Photo must be a JPG, PNG, or WEBP image.');
      editProfilePhotoInput.value = '';
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      showToast('error', 'Photo must be smaller than 2MB.');
      editProfilePhotoInput.value = '';
      return;
    }

    const reader = new FileReader();
    reader.onload = function (e) {
      if (editProfileAvatarEl) {
        editProfileAvatarEl.innerHTML = '<img src="' + e.target.result + '" alt="" class="avatar-img">';
      }
    };
    reader.readAsDataURL(file);
  });
}

if (editProfileOverlay) {
  editProfileOverlay.addEventListener('click', function (e) {
    if (e.target === editProfileOverlay) closeEditProfileModal();
  });
}

document.addEventListener('keydown', function (e) {
  if (e.key !== 'Escape') return;
  closeProfileDropdown();
  if (editProfileOverlay && !editProfileOverlay.hidden) closeEditProfileModal();
  if (auditLogOverlay && !auditLogOverlay.hidden) closeAuditLogModal();
});

if (editProfileForm) {
  const currentPasswordInput = document.getElementById('edit_current_password');
  const newPasswordInput = document.getElementById('edit_new_password');
  const confirmPasswordInput = document.getElementById('edit_confirm_password');
  const usernameInput = document.getElementById('edit_username');
  const trackedInputs = [currentPasswordInput, newPasswordInput, confirmPasswordInput, usernameInput];

  function markInvalid(input, message) {
    if (!input) return;
    input.classList.add('is-invalid');
    const field = input.closest('.edit-profile-field');
    if (field) {
      const existing = field.querySelector('.edit-profile-field-error');
      if (existing) existing.remove();
      if (message) {
        const err = document.createElement('p');
        err.className = 'edit-profile-field-error';
        err.textContent = message;
        field.appendChild(err);
      }
    }
  }

  function clearInvalid() {
    trackedInputs.forEach(function (input) {
      if (!input) return;
      input.classList.remove('is-invalid');
      const field = input.closest('.edit-profile-field');
      if (field) {
        const err = field.querySelector('.edit-profile-field-error');
        if (err) err.remove();
      }
    });
  }

  trackedInputs.forEach(function (input) {
    if (!input) return;
    input.addEventListener('input', function () {
      input.classList.remove('is-invalid');
      const field = input.closest('.edit-profile-field');
      if (field) {
        const err = field.querySelector('.edit-profile-field-error');
        if (err) err.remove();
      }
    });
  });

  // ---- Live username format check (server still re-checks uniqueness on submit) ----
  if (usernameInput) {
    let usernameCheckTimer = null;

    usernameInput.addEventListener('input', function () {
      clearTimeout(usernameCheckTimer);
      const value = usernameInput.value.trim();

      usernameCheckTimer = setTimeout(function () {
        if (value === '') return;

        if (!/^[A-Za-z0-9._-]{4,50}$/.test(value)) {
          markInvalid(usernameInput, 'Username must be 4-50 characters, using only letters, numbers, dot, underscore or dash.');
        }
      }, 400);
    });
  }

  if (editProfileForm) {
    editProfileForm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (editProfileAlert) editProfileAlert.innerHTML = '';
      clearInvalid();

      const currentPassword = currentPasswordInput.value;
      const newPassword = newPasswordInput.value;
      const confirmPassword = confirmPasswordInput.value;

      if ((newPassword || confirmPassword) && !currentPassword) {
        markInvalid(currentPasswordInput, 'Enter your current password to set a new one.');
        return;
      }
      if (newPassword && newPassword.length < 8) {
        markInvalid(newPasswordInput, 'New password must be at least 8 characters.');
        return;
      }
      if (newPassword !== confirmPassword) {
        markInvalid(confirmPasswordInput, 'New password and confirmation do not match.');
        return;
      }

      editProfileSave.disabled = true;
      editProfileSave.textContent = 'Saving…';

      fetch('update_profile.php', {
        method: 'POST',
        body: new FormData(editProfileForm)
      })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          editProfileSave.disabled = false;
          editProfileSave.textContent = 'Save Changes';

          if (json.success) {
            document.querySelectorAll('.sidebar-admin-name, .profile-dropdown-name, #editProfileAvatarName')
              .forEach(function (el) { el.textContent = json.data.full_name; });

            const avatarEls = document.querySelectorAll('#topbarAvatar, #dropdownAvatar, #editProfileAvatar');
            if (json.data.photo) {
              const src = '../' + json.data.photo + '?v=' + Date.now();
              avatarEls.forEach(function (el) {
                el.innerHTML = '<img src="' + src + '" alt="" class="avatar-img">';
              });
            } else {
              avatarEls.forEach(function (el) { el.textContent = json.data.initials; });
            }
            if (editProfileAvatarEl) originalAvatarHtml = editProfileAvatarEl.innerHTML;

            currentPasswordInput.value = '';
            newPasswordInput.value = '';
            confirmPasswordInput.value = '';
            editProfilePhotoInput.value = '';

            closeEditProfileModal();
            showToast('success', 'Changes saved');
          } else {
            const msg = json.message || 'Could not update profile.';

            if (msg.toLowerCase().indexOf('current password is incorrect') !== -1) {
              markInvalid(currentPasswordInput, msg);
              currentPasswordInput.focus();
            } else if (msg.toLowerCase().indexOf('username is already taken') !== -1) {
              markInvalid(document.getElementById('edit_username'), msg);
            } else {
              showEditProfileAlert('error', msg);
            }
          }
        })
        .catch(function () {
          editProfileSave.disabled = false;
          editProfileSave.textContent = 'Save Changes';
          showEditProfileAlert('error', 'Network error. Please try again.');
        });
    });
  }
}