document.addEventListener('DOMContentLoaded', function () {
  const statTotal = document.getElementById('stat-total');
  const statPending = document.getElementById('stat-pending');
  const statReady = document.getElementById('stat-ready');
  const sidebarCountAll = document.getElementById('count-all');
  const sidebarCountPending = document.getElementById('count-pending');
  const sidebarCountReady = document.getElementById('count-ready');

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
  function setActionCell(id, status, reference, fullName) {
    const cell = document.getElementById('action-' + id);
    if (!cell) return;

    if (status === 'Ready for Pickup') {
      cell.innerHTML =
        '<button type="button" class="btn-claim" ' +
        'data-id="' + id + '" ' +
        'data-reference="' + escapeHtml(reference) + '" ' +
        'data-full-name="' + escapeHtml(fullName) + '" ' +
        'title="Mark as claimed" ' +
        'aria-label="Mark ' + escapeHtml(reference) + ' (' + escapeHtml(fullName) + ') as claimed">' +

        '<svg viewBox="0 0 24 24" fill="none">' +
        '<path d="M5 13L9.5 17.5L19 7" ' +
        'stroke="currentColor" stroke-width="2.2" ' +
        'stroke-linecap="round" stroke-linejoin="round"/>' +
        '</svg>' +

        '</button>';
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
      const fullName = row ? row.dataset.fullName : '';

      select.disabled = true;

      fetch('quick_status.php', {
        method: 'POST',
        body: new URLSearchParams({ id: id, request_status: newStatus, csrf_token: window.CSRF_TOKEN })
      })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          if (json.success) {
            paintStatusSelect(select);
            select.dataset.prevStatus = newStatus;
            setActionCell(id, newStatus, reference, fullName);
            applyFilter();

            if (prevStatus !== newStatus) {
              if (prevStatus === 'Pending') {
                bumpStat(statPending, -1);
                bumpStat(sidebarCountPending, -1);
              }
              if (prevStatus === 'Ready for Pickup') {
                bumpStat(statReady, -1);
                bumpStat(sidebarCountReady, -1);
              }
              if (newStatus === 'Pending') {
                bumpStat(statPending, 1);
                bumpStat(sidebarCountPending, 1);
              }
              if (newStatus === 'Ready for Pickup') {
                bumpStat(statReady, 1);
                bumpStat(sidebarCountReady, 1);
              }
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
    btn.disabled = true;

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
          btn.disabled = false;
        }
      })
      .catch(function () {
        alert('Network error. Please try again.');
        btn.disabled = false;
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
  if (tableBody) {
    tableBody.addEventListener('click', function (e) {
      const btn = e.target.closest('.btn-claim');
      if (!btn) return;

      const id = btn.dataset.id;
      const reference = btn.dataset.reference;
      const fullName = btn.dataset.fullName;

      openClaimModal(id, reference, fullName, btn);
    });
  }
  // ---- Live filtering: type to search, click a status in the sidebar ----
  const filterSearch = document.getElementById('filterSearch');
  const statusLinks = document.querySelectorAll('.sidebar-status-link');
  const emptyRow = document.querySelector('.filter-empty');
  let currentStatus = '';

  function applyFilter() {
    if (!tableBody) return;

    const term = (filterSearch ? filterSearch.value : '').trim().toLowerCase();
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
      const matchesStatus = currentStatus === '' || rowStatus === currentStatus;
      const visible = matchesTerm && matchesStatus;

      row.hidden = !visible;
      if (visible) shown++;
    });

    if (emptyRow) {
      const filtering = term !== '' || currentStatus !== '';
      emptyRow.hidden = !(filtering && shown === 0);
    }
  }

  statusLinks.forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      currentStatus = link.dataset.status;

      statusLinks.forEach(function (l) { l.classList.remove('active'); });
      link.classList.add('active');

      applyFilter();
    });
  });

  if (filterSearch) filterSearch.addEventListener('input', applyFilter);
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
  const editProfilePhotoBtn = document.getElementById('editProfilePhotoBtn');
  const editProfilePhotoInput = document.getElementById('editProfilePhotoInput');
  const editProfileAvatarEl = document.getElementById('editProfileAvatar');

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
    toast.style.gap = '10px';
    toast.style.minWidth = '260px';
    toast.style.maxWidth = '360px';
    toast.style.padding = '14px 16px';
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
  });

  if (editProfileForm) {
    const currentPasswordInput = document.getElementById('edit_current_password');
    const newPasswordInput = document.getElementById('edit_new_password');
    const confirmPasswordInput = document.getElementById('edit_confirm_password');

    function markInvalid(input, message) {
      if (!input) return;
      input.classList.add('is-invalid');
      const field = input.closest('.edit-profile-field');
      if (field && message) {
        const err = document.createElement('p');
        err.className = 'edit-profile-field-error';
        err.textContent = message;
        field.appendChild(err);
      }
    }

    function clearInvalid() {
      [currentPasswordInput, newPasswordInput, confirmPasswordInput].forEach(function (input) {
        if (!input) return;
        input.classList.remove('is-invalid');
        const field = input.closest('.edit-profile-field');
        if (field) {
          const err = field.querySelector('.edit-profile-field-error');
          if (err) err.remove();
        }
      });
    }

    [currentPasswordInput, newPasswordInput, confirmPasswordInput].forEach(function (input) {
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

              currentPasswordInput.value = '';
              newPasswordInput.value = '';
              confirmPasswordInput.value = '';
              editProfilePhotoInput.value = '';

              closeEditProfileModal();
              showToast('success', json.message || 'Profile updated.');
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
});
