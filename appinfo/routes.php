<?php
return [
    'routes' => [
        ['name' => 'admin#index', 'url' => '/admin', 'verb' => 'GET'],
        ['name' => 'admin#scanDuplicates', 'url' => '/admin/scan-duplicates', 'verb' => 'GET'],
        ['name' => 'admin#exposeUser', 'url' => '/admin/expose-user', 'verb' => 'POST'],
        ['name' => 'admin#hideUser', 'url' => '/admin/hide-user', 'verb' => 'POST'],
        ['name' => 'admin#getConfigurationStatus', 'url' => '/admin/config-status', 'verb' => 'GET'],
        ['name' => 'admin#saveConfiguration', 'url' => '/admin/save-config', 'verb' => 'POST'],
        ['name' => 'admin#testConfiguration', 'url' => '/admin/test-config', 'verb' => 'POST'],
        ['name' => 'admin#clearConfiguration', 'url' => '/admin/clear-config', 'verb' => 'POST'],
        ['name' => 'admin#saveUserSyncSettings', 'url' => '/admin/save-user-sync-settings', 'verb' => 'POST'],
        ['name' => 'admin#saveNightlySyncSetting', 'url' => '/admin/save-nightly-sync', 'verb' => 'POST'],
        ['name' => 'admin#getNightlySyncStatus', 'url' => '/admin/nightly-sync-status', 'verb' => 'GET'],
        ['name' => 'admin#previewLocalUsers', 'url' => '/admin/preview-local-users', 'verb' => 'GET'],
        ['name' => 'admin#previewVOUsers', 'url' => '/admin/preview-vo-users', 'verb' => 'GET'],
        ['name' => 'admin#syncFromVO', 'url' => '/admin/sync-from-vo', 'verb' => 'POST'],
        ['name' => 'admin#searchVOUsers', 'url' => '/admin/search-vo-users', 'verb' => 'GET'],
        ['name' => 'admin#createAccountFromVO', 'url' => '/admin/create-account-from-vo', 'verb' => 'POST'],
        ['name' => 'admin#bulkCreateAccountsFromVO', 'url' => '/admin/bulk-create-accounts-from-vo', 'verb' => 'POST'],
    ]
]; 
