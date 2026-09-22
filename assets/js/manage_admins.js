document.addEventListener('DOMContentLoaded', function () {
    const overlay = document.getElementById('editScopeOverlay');
    const closeBtn = document.getElementById('editScopeClose');
    const cancelBtn = document.getElementById('editScopeCancel');
    const saveBtn = document.getElementById('editScopeSave');
    const alertEl = document.getElementById('editScopeAlert');
    const subtitleEl = document.getElementById('editScopeSubtitle');
    const checkboxContainer = document.getElementById('editScopeCheckboxes');

    let activeAdminId = null;
    let activeCard = null;

    function showAlert(type, message) {
        if (!alertEl) return;
        alertEl.innerHTML = '<div class="edit-profile-alert edit-profile-alert-' + type + '">' + message + '</div>';
    }

    function openModal(btn) {
        activeAdminId = btn.dataset.adminId;
        activeCard = document.querySelector('.admin-manage-card[data-admin-id="' + activeAdminId + '"]');
        if (!activeCard) return;

        if (subtitleEl) subtitleEl.textContent = 'Choose which document types ' + btn.dataset.adminName + ' can see and manage';
        if (alertEl) alertEl.innerHTML = '';

        const assignedText = activeCard.querySelector('.admin-manage-assigned-value').textContent.trim();
        const currentlyAssigned = assignedText === 'None yet' ? [] : assignedText.split(',').map(function (s) { return s.trim(); });

        checkboxContainer.querySelectorAll('.scope-checkbox').forEach(function (cb) {
            cb.checked = currentlyAssigned.indexOf(cb.value) !== -1;
        });

        if (overlay) overlay.hidden = false;
    }

    function closeModal() {
        if (overlay) overlay.hidden = true;
        activeAdminId = null;
        activeCard = null;
    }

    document.querySelectorAll('.admin-manage-edit-btn').forEach(function (btn) {
        btn.addEventListener('click', function () { openModal(btn); });
    });

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeModal();
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay && !overlay.hidden) closeModal();
    });

    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            if (!activeAdminId || !activeCard) return;

            const checked = Array.from(checkboxContainer.querySelectorAll('.scope-checkbox:checked')).map(function (cb) {
                return cb.value;
            });

            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving…';
            if (alertEl) alertEl.innerHTML = '';

            const body = new URLSearchParams();
            body.append('admin_id', activeAdminId);
            body.append('csrf_token', window.CSRF_TOKEN);
            checked.forEach(function (v) { body.append('doc_types[]', v); });

            fetch('update_admin_scope.php', { method: 'POST', body: body })
                .then(function (res) { return res.json(); })
                .then(function (json) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Changes';

                    if (json.success) {
                        const valueEl = activeCard.querySelector('.admin-manage-assigned-value');
                        valueEl.textContent = checked.length ? checked.join(', ') : 'None yet';
                        closeModal();
                    } else {
                        showAlert('error', json.message || 'Could not save.');
                    }
                })
                .catch(function () {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Changes';
                    showAlert('error', 'Network error. Please try again.');
                });
        });
    }
});