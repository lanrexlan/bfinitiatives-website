<?php

return [
    'app' => [
        'base_url' => 'https://your-domain.example',
        'vendor_autoload' => '/absolute/path/to/vendor/autoload.php',
    ],
    'db' => [
        'users' => [
            'driver' => 'pgsql',
            'host' => 'localhost',
            'port' => '5432',
            'dbname' => 'your_users_database',
            'user' => 'your_database_user',
            'password' => 'replace-with-real-password',
        ],
        'communication' => [
            'driver' => 'pgsql',
            'host' => 'localhost',
            'port' => '5432',
            'dbname' => 'your_communication_database',
            'user' => 'your_database_user',
            'password' => 'replace-with-real-password',
        ],
    ],
    'mail' => [
        'host' => 'mail.example.com',
        'port' => 465,
        'username' => 'info@example.com',
        'password' => 'replace-with-real-smtp-password',
        'encryption' => 'smtps',
        'from_email' => 'info@example.com',
        'from_name' => 'BFI Initiatives',
        'reply_to_email' => 'info@example.com',
        'reply_to_name' => 'BFI Initiatives',
        'admin_email' => 'info@example.com',
        'admin_name' => 'BFI Team',
        'no_reply_email' => 'noreply@example.com',
        'smtp_debug' => 0,
    ],
];
