<?php
return [
    'routes' => [
        ['name' => 'admin#index', 'url' => '/admin', 'verb' => 'GET'],
        ['name' => 'admin#scanDuplicates', 'url' => '/admin/scan-duplicates', 'verb' => 'GET'],
        ['name' => 'admin#exposeUser', 'url' => '/admin/expose-user', 'verb' => 'POST'],
        ['name' => 'admin#hideUser', 'url' => '/admin/hide-user', 'verb' => 'POST'],
    ]
]; 
