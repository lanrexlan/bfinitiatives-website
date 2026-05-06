<?php

return [
    'app' => [
        'base_url' => 'https://bfinitiatives.com',
        'vendor_autoload' => '/home/bfinitia/public_html/scholar-portal/vendor/autoload.php',
    ],
    'db' => [
        'users' => [
            'driver' => 'pgsql',
            'host' => 'localhost',
            'port' => '5432',
            'dbname' => 'bfinitia_users',
            'user' => 'bfinitia',
            'password' => 'Akande_Olanrewaju123@',
        ],
        'communication' => [
            'driver' => 'pgsql',
            'host' => 'localhost',
            'port' => '5432',
            'dbname' => 'bfinitia_communication',
            'user' => 'bfinitia',
            'password' => 'Akande_Olanrewaju123@',
        ],
    ],
    'mail' => [
        'host' => 'mail.bfinitiatives.com',
        'port' => 465,
        'username' => 'info@bfinitiatives.com',
        'password' => 'K5Y)T{gvZ-NS',
        'encryption' => 'smtps',
        'from_email' => 'info@bfinitiatives.com',
        'from_name' => 'BFI Initiatives',
        'reply_to_email' => 'info@bfinitiatives.com',
        'reply_to_name' => 'BFI Initiatives',
        'admin_email' => 'info@bfinitiatives.com',
        'admin_name' => 'BFI Team',
        'no_reply_email' => 'noreply@bfinitiatives.com',
        'smtp_debug' => 0,
    ],
];
