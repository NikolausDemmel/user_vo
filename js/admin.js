// Admin interface JavaScript for user_vo plugin

// Simple HTML escaping function
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', function() {
    // Configuration form handling
    const configForm = document.getElementById('user-vo-config-form');
    const testConfigButtons = document.querySelectorAll('.test-config-btn');
    const clearConfigButton = document.getElementById('clear-config');
    const configMessageConfigPhp = document.getElementById('config-message-configphp');
    const configMessageAdmin = document.getElementById('config-message-admin');

    if (configForm) {
        configForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveConfiguration();
        });
    }

    // Attach event listener to all test config buttons (config.php and admin interface)
    testConfigButtons.forEach(button => {
        button.addEventListener('click', function() {
            testConfiguration(this);
        });
    });

    if (clearConfigButton) {
        clearConfigButton.addEventListener('click', function() {
            if (confirm(t('user_vo', 'Are you sure you want to clear the configuration? This will remove all saved settings.'))) {
                clearConfiguration();
            }
        });
    }

    function saveConfiguration() {
        const formData = new FormData(configForm);
        const data = {
            api_url: formData.get('api_url'),
            api_username: formData.get('api_username'),
            api_password: formData.get('api_password')
        };

        const submitButton = configForm.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;
        submitButton.disabled = true;
        submitButton.textContent = t('user_vo', 'Saving...');

        fetch(OC.generateUrl('/apps/user_vo/admin/save-config'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'requesttoken': OC.requestToken
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            submitButton.disabled = false;
            submitButton.textContent = originalText;

            if (data.success) {
                showConfigMessage(data.message, 'success', submitButton);
                // Reload the page to update the configuration status
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showConfigMessage(data.message, 'error', submitButton);
            }
        })
        .catch(error => {
            submitButton.disabled = false;
            submitButton.textContent = originalText;
            showConfigMessage(t('user_vo', 'Error saving configuration') + ': ' + error, 'error', submitButton);
        });
    }

    function testConfiguration(button) {
        let data;

        // Check if button has data attributes (config.php mode) or use form (admin interface mode)
        const mode = button.getAttribute('data-mode');
        if (mode === 'config-php') {
            // Send empty values - server will load everything from config.php
            data = {
                api_url: '',
                api_username: '',
                api_password: '' // Password will be retrieved server-side from config
            };
        } else if (configForm) {
            // Get configuration from form (admin interface mode)
            const formData = new FormData(configForm);
            data = {
                api_url: formData.get('api_url'),
                api_username: formData.get('api_username'),
                api_password: formData.get('api_password')
            };
        } else {
            showConfigMessage(t('user_vo', 'Unable to test configuration: no form found'), 'error', button);
            return;
        }

        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = t('user_vo', 'Testing...');

        fetch(OC.generateUrl('/apps/user_vo/admin/test-config'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'requesttoken': OC.requestToken
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            button.disabled = false;
            button.textContent = originalText;

            if (data.success) {
                showConfigMessage(data.message, 'success', button);
            } else {
                showConfigMessage(data.message, 'error', button);
            }
        })
        .catch(error => {
            button.disabled = false;
            button.textContent = originalText;
            showConfigMessage(t('user_vo', 'Error testing configuration') + ': ' + error, 'error', button);
        });
    }

    function clearConfiguration() {
        const originalText = clearConfigButton.textContent;
        clearConfigButton.disabled = true;
        clearConfigButton.textContent = t('user_vo', 'Clearing...');

        fetch(OC.generateUrl('/apps/user_vo/admin/clear-config'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'requesttoken': OC.requestToken
            }
        })
        .then(response => response.json())
        .then(data => {
            clearConfigButton.disabled = false;
            clearConfigButton.textContent = originalText;

            if (data.success) {
                showConfigMessage(data.message, 'success');
                // Clear the form fields
                document.getElementById('api-url').value = '';
                document.getElementById('api-username').value = '';
                document.getElementById('api-password').value = '';
                // Refresh the page to update the configuration status
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showConfigMessage(data.message, 'error');
            }
        })
        .catch(error => {
            clearConfigButton.disabled = false;
            clearConfigButton.textContent = originalText;
            showConfigMessage(t('user_vo', 'Error clearing configuration') + ': ' + error, 'error');
        });
    }

    function showConfigMessage(message, type, button) {
        // Determine which message div to use based on button mode
        let messageDiv = null;
        if (button) {
            const mode = button.getAttribute('data-mode');
            if (mode === 'config-php') {
                messageDiv = configMessageConfigPhp;
            } else {
                messageDiv = configMessageAdmin;
            }
        }

        // Fallback to admin message div if not found
        if (!messageDiv) {
            messageDiv = configMessageAdmin;
        }

        if (messageDiv) {
            messageDiv.textContent = message;
            messageDiv.className = 'config-message ' + type;
            messageDiv.style.display = 'block';

            setTimeout(() => {
                messageDiv.style.display = 'none';
            }, 5000);
        } else {
            // Fallback to browser alert if no message div found
            alert(message);
        }
    }

    // Duplicate user management functionality
    const scanButton = document.getElementById('scan-duplicates');
    const scanResults = document.getElementById('scan-results');
    const summaryInfo = document.getElementById('summary-info');
    const duplicateResults = document.getElementById('duplicate-results');
    const duplicateList = document.getElementById('duplicate-list');
    const allUsersResults = document.getElementById('all-users-results');
    const allUsersList = document.getElementById('all-users-list');

    if (!scanButton) {
        console.error('Scan button not found');
        return;
    }

    scanButton.addEventListener('click', function() {
        scanButton.disabled = true;
        scanButton.textContent = t('user_vo', 'Scanning...');

        fetch(OC.generateUrl('/apps/user_vo/admin/scan-duplicates'), {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'requesttoken': OC.requestToken
            }
        })
        .then(response => {
            return response.json();
        })
        .then(data => {
            scanButton.disabled = false;
            scanButton.textContent = t('user_vo', 'Scan for Users');

            if (data.success) {
                displayResults(data);
            } else {
                OC.Notification.showTemporary(t('user_vo', 'Error scanning for users') + ': ' + data.error);
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            scanButton.disabled = false;
            scanButton.textContent = t('user_vo', 'Scan for Users');
            OC.Notification.showTemporary(t('user_vo', 'Error scanning for users') + ': ' + error);
        });
    });

    function displayResults(data) {
        // Display summary
        let summaryHtml = '<div class="user-summary">';
        summaryHtml += '<h4>' + t('user_vo', 'Summary') + '</h4>';
        summaryHtml += '<p><strong>' + t('user_vo', 'Duplicate Users:') + '</strong> ' + data.summary.duplicateSets + '</p>';
        summaryHtml += '<p><strong>' + t('user_vo', 'Total Managed Users:') + '</strong> ' + data.summary.totalManagedUsers + '</p>';
        summaryHtml += '</div>';
        summaryInfo.innerHTML = summaryHtml;

        // Display duplicate users
        if (data.duplicateSets && data.duplicateSets.length > 0) {
            displayDuplicateSets(data.duplicateSets);
            duplicateResults.style.display = 'block';
        } else {
            duplicateList.innerHTML = '<p>' + t('user_vo', 'No duplicate users found.') + '</p>';
            duplicateResults.style.display = 'block';
        }

        // Display all plugin users
        if (data.allPluginUsers && data.allPluginUsers.length > 0) {
            displayAllPluginUsers(data.allPluginUsers);
            allUsersResults.style.display = 'block';
        } else {
            allUsersList.innerHTML = '<p>' + t('user_vo', 'No plugin users found.') + '</p>';
            allUsersResults.style.display = 'block';
        }

        scanResults.style.display = 'block';
    }

    function displayDuplicateSets(duplicateSets) {
        let html = '<div class="duplicate-sets">';

        duplicateSets.forEach((set, index) => {
            // Find the canonical user for the title
            let canonicalUser = set.variants.find(variant => variant.is_canonical);
            let title = canonicalUser ? canonicalUser.displayname : set.normalized_uid;

            html += '<div class="duplicate-set">';
            html += '<h5>' + escapeHtml(title) + '</h5>';
            html += '<table class="duplicate-variants">';
            html += '<thead><tr><th>' + t('user_vo', 'Username') + '</th><th>' + t('user_vo', 'Canonical') + '</th><th>' + t('user_vo', 'Exposed') + '</th><th>' + t('user_vo', 'Files') + '</th><th>' + t('user_vo', 'Groups') + '</th><th>' + t('user_vo', 'Created') + '</th><th>' + t('user_vo', 'Display Name') + '</th></tr></thead>';
            html += '<tbody>';

            set.variants.forEach(variant => {
                html += '<tr>';
                html += '<td>' + escapeHtml(variant.display_uid || variant.uid) + '</td>';
                html += '<td>' + (variant.is_canonical ? '✔️' : '') + '</td>';
                html += '<td>' + renderExposeCheckbox(variant) + '</td>';
                html += '<td>' + variant.file_count + '</td>';
                html += '<td>' + renderGroups(variant.groups) + '</td>';
                html += '<td>' + renderCreationDate(variant.creation_date) + '</td>';
                html += '<td>' + escapeHtml(variant.displayname) + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table></div>';
        });

        html += '</div>';
        duplicateList.innerHTML = html;

        // Add event listeners for checkboxes
        duplicateList.querySelectorAll('.expose-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function(e) {
                const uid = this.getAttribute('data-uid');
                if (this.checked) {
                    exposeUser(uid);
                } else {
                    hideUser(uid);
                }
            });
        });
    }

    function displayAllPluginUsers(allPluginUsers) {
        let html = '<div class="all-plugin-users">';
        html += '<table class="all-users-table">';
        html += '<thead><tr><th>' + t('user_vo', 'Username') + '</th><th>' + t('user_vo', 'Canonical') + '</th><th>' + t('user_vo', 'Exposed') + '</th><th>' + t('user_vo', 'Normalized') + '</th><th>' + t('user_vo', 'Files') + '</th><th>' + t('user_vo', 'Groups') + '</th><th>' + t('user_vo', 'Created') + '</th><th>' + t('user_vo', 'Display Name') + '</th></tr></thead>';
        html += '<tbody>';

        allPluginUsers.forEach(user => {
            html += '<tr>';
            html += '<td>' + escapeHtml(user.display_uid || user.uid) + '</td>';
            html += '<td>' + (user.is_canonical ? '✔️' : '') + '</td>';
            html += '<td>' + (user.is_exposed ? '✔️' : '') + '</td>';
            html += '<td>' + (user.is_normalized ? '✔️' : '') + '</td>';
            html += '<td>' + user.file_count + '</td>';
            html += '<td>' + renderGroups(user.groups) + '</td>';
            html += '<td>' + renderCreationDate(user.creation_date) + '</td>';
            html += '<td>' + escapeHtml(user.displayname) + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        allUsersList.innerHTML = html;
    }

    function renderExposeCheckbox(variant) {
        if (variant.is_canonical) {
            return '<input type="checkbox" checked disabled title="' + t('user_vo', 'Canonical user always exposed') + '">';
        }
        return '<input type="checkbox" class="expose-checkbox" data-uid="' + escapeHtml(variant.uid) + '"' + (variant.is_exposed ? ' checked' : '') + '>';
    }

    function renderGroups(groups) {
        if (!groups || groups.length === 0) {
            return '<span class="no-groups">—</span>';
        }
        return '<span class="groups-list">' + escapeHtml(groups.join(', ')) + '</span>';
    }

    function renderCreationDate(creationDate) {
        if (!creationDate) {
            return '<span class="no-date">—</span>';
        }
        return '<span class="creation-date">' + escapeHtml(creationDate) + '</span>';
    }

    function exposeUser(uid) {
        fetch(OC.generateUrl('/apps/user_vo/admin/expose-user'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'requesttoken': OC.requestToken
            },
            body: JSON.stringify({ uid })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                OC.Notification.showTemporary(t('user_vo', 'User exposed successfully'));
                scanButton.click(); // Refresh the list
            } else {
                OC.Notification.showTemporary(t('user_vo', 'Error exposing user') + ': ' + data.error);
            }
        })
        .catch(error => {
            OC.Notification.showTemporary(t('user_vo', 'Error exposing user') + ': ' + error);
        });
    }

    function hideUser(uid) {
        fetch(OC.generateUrl('/apps/user_vo/admin/hide-user'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'requesttoken': OC.requestToken
            },
            body: JSON.stringify({ uid })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                OC.Notification.showTemporary(t('user_vo', 'User hidden successfully'));
                scanButton.click(); // Refresh the list
            } else {
                OC.Notification.showTemporary(t('user_vo', 'Error hiding user') + ': ' + data.error);
            }
        })
        .catch(error => {
            OC.Notification.showTemporary(t('user_vo', 'Error hiding user') + ': ' + error);
        });
    }

    // ========================================
    // User Data Synchronization
    // ========================================

    const saveUserSyncSettingsButton = document.getElementById('save-user-sync-settings');
    const syncEmailCheckbox = document.getElementById('sync-email');
    const syncPhotoCheckbox = document.getElementById('sync-photo');
    const userSyncMessage = document.getElementById('user-sync-message');
    const syncAllUsersButton = document.getElementById('sync-all-users');
    const syncAllUsersStatus = document.getElementById('sync-all-users-status');
    const userSyncResults = document.getElementById('user-sync-results');
    const userSyncSummary = document.getElementById('user-sync-summary');
    const userSyncList = document.getElementById('user-sync-list');

    // Save user sync settings
    if (saveUserSyncSettingsButton) {
        saveUserSyncSettingsButton.addEventListener('click', function() {
            const syncEmail = syncEmailCheckbox ? syncEmailCheckbox.checked : false;
            const syncPhoto = syncPhotoCheckbox ? syncPhotoCheckbox.checked : false;

            fetch(OC.generateUrl('/apps/user_vo/admin/save-user-sync-settings'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'requesttoken': OC.requestToken
                },
                body: JSON.stringify({
                    sync_email: syncEmail,
                    sync_photo: syncPhoto
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    userSyncMessage.textContent = data.message;
                    userSyncMessage.className = 'config-message success';
                    userSyncMessage.style.display = 'inline';

                    setTimeout(() => {
                        userSyncMessage.style.display = 'none';
                    }, 3000);
                } else {
                    userSyncMessage.textContent = data.message || 'Error saving settings';
                    userSyncMessage.className = 'config-message error';
                    userSyncMessage.style.display = 'inline';
                }
            })
            .catch(error => {
                userSyncMessage.textContent = 'Error: ' + error;
                userSyncMessage.className = 'config-message error';
                userSyncMessage.style.display = 'inline';
            });
        });
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Nightly sync checkbox handler
    const nightlySyncCheckbox = document.getElementById('enable-nightly-sync');
    if (nightlySyncCheckbox) {
        nightlySyncCheckbox.addEventListener('change', function() {
            const enabled = this.checked;

            fetch(OC.generateUrl('/apps/user_vo/admin/save-nightly-sync'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'requesttoken': OC.requestToken
                },
                body: JSON.stringify({ enabled: enabled })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    OC.Notification.showTemporary(t('user_vo', 'Nightly sync setting saved'));
                    // Refresh status display
                    loadNightlySyncStatus();
                } else {
                    OC.Notification.showTemporary(t('user_vo', 'Error saving nightly sync setting'), { type: 'error' });
                }
            })
            .catch(error => {
                OC.Notification.showTemporary(t('user_vo', 'Error:') + ' ' + error, { type: 'error' });
            });
        });

        // Load status on page load
        loadNightlySyncStatus();
    }

    // Function to load and display nightly sync status
    function loadNightlySyncStatus() {
        fetch(OC.generateUrl('/apps/user_vo/admin/nightly-sync-status'), {
            method: 'GET',
            headers: {
                'requesttoken': OC.requestToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNightlySyncStatusDisplay(data);
            }
        })
        .catch(error => {
            console.error('Error loading nightly sync status:', error);
        });
    }

    // Function to update the status display
    function updateNightlySyncStatusDisplay(data) {
        const statusBadge = document.getElementById('nightly-sync-status-badge');
        const lastRunElement = document.getElementById('nightly-sync-last-run');
        const summaryElement = document.getElementById('nightly-sync-summary');
        const errorContainer = document.getElementById('nightly-sync-error-container');
        const errorElement = document.getElementById('nightly-sync-error');

        // Update status badge
        statusBadge.className = 'status-badge ' + data.last_status;
        if (data.last_status === 'success') {
            statusBadge.textContent = t('user_vo', 'Success');
        } else if (data.last_status === 'failed') {
            statusBadge.textContent = t('user_vo', 'Failed');
        } else {
            statusBadge.textContent = t('user_vo', 'Never run');
        }

        // Update last run time
        if (data.last_run) {
            const date = new Date(data.last_run * 1000);
            lastRunElement.textContent = date.toLocaleString();
        } else {
            lastRunElement.textContent = t('user_vo', 'Never');
        }

        // Update summary
        if (data.last_summary && data.last_summary.total !== undefined) {
            const summary = data.last_summary;
            const parts = [];
            if (summary.synced > 0) parts.push(t('user_vo', '{synced} synced', {synced: summary.synced}));
            if (summary.failed > 0) parts.push(t('user_vo', '{failed} failed', {failed: summary.failed}));
            if (summary.skipped > 0) parts.push(t('user_vo', '{skipped} skipped', {skipped: summary.skipped}));
            summaryElement.textContent = parts.length > 0 ? parts.join(', ') : t('user_vo', 'No users to sync');
        } else {
            summaryElement.textContent = t('user_vo', 'No sync data available');
        }

        // Update error display
        if (data.last_error && data.last_error.trim() !== '') {
            errorElement.textContent = data.last_error;
            errorContainer.style.display = 'flex';
        } else {
            errorContainer.style.display = 'none';
        }
    }

    // View local data (fast, no API calls)
    const viewLocalDataButton = document.getElementById('view-local-data');
    if (viewLocalDataButton) {
        viewLocalDataButton.addEventListener('click', function() {
            viewLocalDataButton.disabled = true;
            syncAllUsersStatus.textContent = t('user_vo', 'Loading users...');
            syncAllUsersStatus.className = 'sync-status syncing';
            userSyncResults.style.display = 'none';

            fetch(OC.generateUrl('/apps/user_vo/admin/preview-local-users'), {
                method: 'GET',
                headers: {
                    'requesttoken': OC.requestToken
                }
            })
            .then(response => response.json())
            .then(data => {
                viewLocalDataButton.disabled = false;

                if (data.success) {
                    syncAllUsersStatus.textContent = '';

                    // Show summary
                    userSyncSummary.innerHTML = `
                        <p><strong>${t('user_vo', 'Local user data (database only):')}</strong></p>
                        <ul>
                            <li>${t('user_vo', 'Total users:')} ${data.total}</li>
                        </ul>
                    `;

                    // Show results table
                    userSyncList.innerHTML = '';
                    data.results.forEach(result => {
                        const row = document.createElement('tr');
                        row.className = result.status;

                        const statusIcon = result.status === 'info' ? 'ℹ' :
                                         result.status === 'skipped' ? '○' : '○';

                        row.innerHTML = `
                            <td>${escapeHtml(result.uid)}</td>
                            <td>${escapeHtml(result.vo_username || '-')}</td>
                            <td>${escapeHtml(result.vo_user_id || '-')}</td>
                            <td>${escapeHtml(result.display_name || '-')}</td>
                            <td>${escapeHtml(result.email || '-')}</td>
                            <td>${escapeHtml(result.photo_status || '-')}</td>
                            <td>${escapeHtml(result.last_synced || '-')}</td>
                            <td><span class="status-${result.status}">${statusIcon} ${escapeHtml(result.message)}</span></td>
                        `;
                        userSyncList.appendChild(row);
                    });

                    userSyncResults.style.display = 'block';
                } else {
                    syncAllUsersStatus.textContent = t('user_vo', 'Failed to load data:') + ' ' + (data.error || 'Unknown error');
                    syncAllUsersStatus.className = 'sync-status error';
                    OC.Notification.showTemporary(t('user_vo', 'Error loading data') + ': ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                viewLocalDataButton.disabled = false;
                syncAllUsersStatus.textContent = t('user_vo', 'Error:') + ' ' + error;
                syncAllUsersStatus.className = 'sync-status error';
                OC.Notification.showTemporary(t('user_vo', 'Error loading data') + ': ' + error);
            });
        });
    }

    // View user metadata (with VO API calls)
    const viewUserMetadataButton = document.getElementById('view-user-metadata');
    if (viewUserMetadataButton) {
        viewUserMetadataButton.addEventListener('click', function() {
            viewUserMetadataButton.disabled = true;
            syncAllUsersStatus.textContent = t('user_vo', 'Previewing from VO... (this may take a moment)');
            syncAllUsersStatus.className = 'sync-status syncing';
            userSyncResults.style.display = 'none';

            fetch(OC.generateUrl('/apps/user_vo/admin/preview-vo-users'), {
                method: 'GET',
                headers: {
                    'requesttoken': OC.requestToken
                }
            })
            .then(response => response.json())
            .then(data => {
                viewUserMetadataButton.disabled = false;

                if (data.success) {
                    syncAllUsersStatus.textContent = '';

                    // Show summary
                    userSyncSummary.innerHTML = `
                        <p><strong>${t('user_vo', 'User metadata (not synced):')}</strong></p>
                        <ul>
                            <li>${t('user_vo', 'Total users:')} ${data.total}</li>
                        </ul>
                    `;

                    // Show results table
                    userSyncList.innerHTML = '';
                    data.results.forEach(result => {
                        const row = document.createElement('tr');
                        row.className = result.status;

                        const statusIcon = result.status === 'info' ? 'ℹ' :
                                         result.status === 'deleted' ? '⚠' :
                                         result.status === 'failed' ? '✗' :
                                         result.status === 'skipped' ? '○' : '○';

                        row.innerHTML = `
                            <td>${escapeHtml(result.uid)}</td>
                            <td>${escapeHtml(result.vo_username || '-')}</td>
                            <td>${escapeHtml(result.vo_user_id || '-')}</td>
                            <td>${escapeHtml(result.display_name || '-')}</td>
                            <td>${escapeHtml(result.email || '-')}</td>
                            <td>${escapeHtml(result.photo_status || '-')}</td>
                            <td>${escapeHtml(result.last_synced || '-')}</td>
                            <td><span class="status-${result.status}">${statusIcon} ${escapeHtml(result.message)}</span></td>
                        `;
                        userSyncList.appendChild(row);
                    });

                    userSyncResults.style.display = 'block';
                } else {
                    syncAllUsersStatus.textContent = t('user_vo', 'Failed to load metadata:') + ' ' + (data.error || 'Unknown error');
                    syncAllUsersStatus.className = 'sync-status error';
                }
            })
            .catch(error => {
                viewUserMetadataButton.disabled = false;
                syncAllUsersStatus.textContent = t('user_vo', 'Error:') + ' ' + error;
                syncAllUsersStatus.className = 'sync-status error';
            });
        });
    }

    // Sync all users
    if (syncAllUsersButton) {
        syncAllUsersButton.addEventListener('click', function() {
            syncAllUsersButton.disabled = true;
            syncAllUsersStatus.textContent = t('user_vo', 'Syncing from VO... (this may take a moment)');
            syncAllUsersStatus.className = 'sync-status syncing';
            userSyncResults.style.display = 'none';

            fetch(OC.generateUrl('/apps/user_vo/admin/sync-from-vo'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'requesttoken': OC.requestToken
                }
            })
            .then(response => response.json())
            .then(data => {
                syncAllUsersButton.disabled = false;

                if (data.success) {
                    const summary = data.summary;
                    syncAllUsersStatus.textContent = '';

                    // Show summary
                    userSyncSummary.innerHTML = `
                        <p><strong>${t('user_vo', 'Sync completed:')}</strong></p>
                        <ul>
                            <li>${t('user_vo', 'Total users:')} ${summary.total}</li>
                            <li class="success">${t('user_vo', 'Successfully synced:')} ${summary.success}</li>
                            <li class="error">${t('user_vo', 'Failed:')} ${summary.failed}</li>
                            <li>${t('user_vo', 'Skipped:')} ${summary.skipped}</li>
                        </ul>
                    `;

                    // Show results table
                    userSyncList.innerHTML = '';
                    data.results.forEach(result => {
                        const row = document.createElement('tr');
                        row.className = result.status;

                        const statusIcon = result.status === 'success' ? '✓' :
                                         result.status === 'deleted' ? '⚠' :
                                         result.status === 'failed' ? '✗' :
                                         result.status === 'skipped' ? '○' : '○';

                        row.innerHTML = `
                            <td>${escapeHtml(result.uid)}</td>
                            <td>${escapeHtml(result.vo_username || '-')}</td>
                            <td>${escapeHtml(result.vo_user_id || '-')}</td>
                            <td>${escapeHtml(result.display_name || '-')}</td>
                            <td>${escapeHtml(result.email || '-')}</td>
                            <td>${escapeHtml(result.photo_status || '-')}</td>
                            <td>${escapeHtml(result.last_synced || '-')}</td>
                            <td><span class="status-${result.status}">${statusIcon} ${escapeHtml(result.message)}</span></td>
                        `;
                        userSyncList.appendChild(row);
                    });

                    userSyncResults.style.display = 'block';

                    OC.Notification.showTemporary(
                        t('user_vo', 'User sync completed: {success} succeeded, {failed} failed', {
                            success: summary.success,
                            failed: summary.failed
                        })
                    );
                } else {
                    syncAllUsersStatus.textContent = t('user_vo', 'Sync failed:') + ' ' + (data.error || 'Unknown error');
                    syncAllUsersStatus.className = 'sync-status error';
                }
            })
            .catch(error => {
                syncAllUsersButton.disabled = false;
                syncAllUsersStatus.textContent = t('user_vo', 'Error:') + ' ' + error;
                syncAllUsersStatus.className = 'sync-status error';
            });
        });
    }
});
