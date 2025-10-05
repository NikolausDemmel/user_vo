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
        ['name' => 'admin#syncAllUsers', 'url' => '/admin/sync-all-users', 'verb' => 'POST'],
    ]
]; 
