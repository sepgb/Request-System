document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.flip-card').forEach(function (card) {
        const adminId = card.dataset.adminId;
        const openBtn = card.querySelector('.flip-open-btn');
        const cancelBtn = card.querySelector('.flip-cancel-btn');
        const saveBtn = card.querySelector('.flip-save-btn');
        const alertEl = card.querySelector('.admin-manage-back-alert');
        const checkboxes = card.querySelectorAll('.scope-checkbox');
        const assignedListEl = card.querySelector('.admin-manage-assigned-list');

        let originalChecked = [];

        function snapshotState() {
            originalChecked = Array.from(checkboxes).map(function (cb) { return cb.checked; });
        }

        function restoreState() {
            checkboxes.forEach(function (cb, i) { cb.checked = originalChecked[i]; });
        }

        function open() {
            snapshotState();
            if (alertEl) alertEl.innerHTML = '';
            card.classList.add('is-flipped');
        }

        function close() {
            card.classList.remove('is-flipped');
        }

        function showAlert(message) {
            if (!alertEl) return;
            alertEl.innerHTML = '<div class="edit-profile-alert edit-profile-alert-error">' + message + '</div>';
        }

        if (openBtn) openBtn.addEventListener('click', open);
        if (cancelBtn) cancelBtn.addEventListener('click', function () { restoreState(); close(); });

        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                const checked = Array.from(checkboxes).filter(function (cb) { return cb.checked; }).map(function (cb) { return cb.value; });

                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving…';
                if (alertEl) alertEl.innerHTML = '';

                const body = new URLSearchParams();
                body.append('admin_id', adminId);
                body.append('csrf_token', window.CSRF_TOKEN);
                checked.forEach(function (v) { body.append('doc_types[]', v); });

                fetch('update_admin_scope.php', { method: 'POST', body: body })
                    .then(function (res) { return res.json(); })
                    .then(function (json) {
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Save Changes';

                        if (json.success) {
                            if (assignedListEl) {
                                assignedListEl.innerHTML = checked.length
                                    ? checked.map(function (v) { return '<span class="admin-manage-doc-row">' + v.replace(/</g, '&lt;') + '</span>'; }).join('')
                                    : '<span class="admin-manage-assigned-empty">None yet</span>';
                            }
                            close();
                        } else {
                            showAlert(json.message || 'Could not save.');
                        }
                    })
                    .catch(function () {
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Save Changes';
                        showAlert('Network error. Please try again.');
                    });
            });
        }
    });
});
