// Admin interface JavaScript for user_vo plugin

// Simple HTML escaping function
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', function() {
    const scanButton = document.getElementById('scan-duplicates');
    const duplicateResults = document.getElementById('duplicate-results');
    const duplicateList = document.getElementById('duplicate-list');
    
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
            scanButton.textContent = t('user_vo', 'Scan for Duplicates');
            
            if (data.success) {
                displayDuplicateSets(data.duplicateSets);
            } else {
                OC.Notification.showTemporary(t('user_vo', 'Error scanning for duplicates') + ': ' + data.error);
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            scanButton.disabled = false;
            scanButton.textContent = t('user_vo', 'Scan for Duplicates');
            OC.Notification.showTemporary(t('user_vo', 'Error scanning for duplicates') + ': ' + error);
        });
    });
    
    function displayDuplicateSets(duplicateSets) {
        if (!duplicateSets || duplicateSets.length === 0) {
            duplicateList.innerHTML = '<p>' + t('user_vo', 'No duplicate accounts found.') + '</p>';
        } else {
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
            document.querySelectorAll('.expose-checkbox').forEach(checkbox => {
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
        
        duplicateResults.style.display = 'block';
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
}); 
