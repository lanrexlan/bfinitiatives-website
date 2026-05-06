<?php

if (defined('BFI_BOOTSTRAP_LOADED')) {
    return;
}

define('BFI_BOOTSTRAP_LOADED', true);

function bfi_env($key, $default = null) {
    $value = getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return $value;
}

function bfi_merge_config(array $base, array $overrides) {
    foreach ($overrides as $key => $value) {
        if (isset($base[$key]) && is_array($base[$key]) && is_array($value)) {
            $base[$key] = bfi_merge_config($base[$key], $value);
            continue;
        }

        $base[$key] = $value;
    }

    return $base;
}

function bfi_config() {
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $config = [
        'app' => [
            'base_url' => bfi_env('BFI_BASE_URL', 'https://bfinitiatives.com'),
            'vendor_autoload' => bfi_env('BFI_VENDOR_AUTOLOAD', ''),
        ],
        'db' => [
            'users' => [
                'driver' => 'pgsql',
                'host' => bfi_env('BFI_DB_HOST', 'localhost'),
                'port' => bfi_env('BFI_DB_PORT', '5432'),
                'dbname' => bfi_env('BFI_DB_NAME', ''),
                'user' => bfi_env('BFI_DB_USER', ''),
                'password' => bfi_env('BFI_DB_PASSWORD', ''),
            ],
            'communication' => [
                'driver' => 'pgsql',
                'host' => bfi_env('BFI_COMM_DB_HOST', bfi_env('BFI_DB_HOST', 'localhost')),
                'port' => bfi_env('BFI_COMM_DB_PORT', bfi_env('BFI_DB_PORT', '5432')),
                'dbname' => bfi_env('BFI_COMM_DB_NAME', ''),
                'user' => bfi_env('BFI_COMM_DB_USER', bfi_env('BFI_DB_USER', '')),
                'password' => bfi_env('BFI_COMM_DB_PASSWORD', bfi_env('BFI_DB_PASSWORD', '')),
            ],
        ],
        'mail' => [
            'host' => bfi_env('BFI_SMTP_HOST', ''),
            'port' => (int) bfi_env('BFI_SMTP_PORT', '465'),
            'username' => bfi_env('BFI_SMTP_USERNAME', ''),
            'password' => bfi_env('BFI_SMTP_PASSWORD', ''),
            'encryption' => bfi_env('BFI_SMTP_ENCRYPTION', 'smtps'),
            'from_email' => bfi_env('BFI_FROM_EMAIL', 'info@bfinitiatives.com'),
            'from_name' => bfi_env('BFI_FROM_NAME', 'BFI Initiatives'),
            'reply_to_email' => bfi_env('BFI_REPLY_TO_EMAIL', 'info@bfinitiatives.com'),
            'reply_to_name' => bfi_env('BFI_REPLY_TO_NAME', 'BFI Initiatives'),
            'admin_email' => bfi_env('BFI_ADMIN_EMAIL', 'info@bfinitiatives.com'),
            'admin_name' => bfi_env('BFI_ADMIN_NAME', 'BFI Team'),
            'no_reply_email' => bfi_env('BFI_NO_REPLY_EMAIL', 'noreply@bfinitiatives.com'),
            'smtp_debug' => (int) bfi_env('BFI_SMTP_DEBUG', '0'),
        ],
    ];

    $localConfigPath = __DIR__ . '/app_secrets.php';
    if (is_file($localConfigPath)) {
        $localConfig = require $localConfigPath;
        if (is_array($localConfig)) {
            $config = bfi_merge_config($config, $localConfig);
        }
    }

    return $config;
}

function bfi_config_get($path, $default = null) {
    $segments = explode('.', $path);
    $current = bfi_config();

    foreach ($segments as $segment) {
        if (!is_array($current) || !array_key_exists($segment, $current)) {
            return $default;
        }

        $current = $current[$segment];
    }

    return $current;
}

function bfi_database_config($name = 'users') {
    $config = bfi_config_get('db.' . $name);
    if (!is_array($config)) {
        throw new RuntimeException("Database configuration for {$name} is missing.");
    }

    return $config;
}

function bfi_pg_connect($name = 'users') {
    $db = bfi_database_config($name);

    $connectionString = sprintf(
        'host=%s port=%s dbname=%s user=%s password=%s',
        $db['host'] ?? 'localhost',
        $db['port'] ?? '5432',
        $db['dbname'] ?? '',
        $db['user'] ?? '',
        $db['password'] ?? ''
    );

    $connection = pg_connect($connectionString);
    if (!$connection) {
        throw new RuntimeException('Database connection failed');
    }

    return $connection;
}

function bfi_pdo_connect($name = 'users') {
    $db = bfi_database_config($name);

    if (($db['driver'] ?? 'pgsql') !== 'pgsql') {
        throw new RuntimeException('Unsupported PDO driver configured.');
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $db['host'] ?? 'localhost',
        $db['port'] ?? '5432',
        $db['dbname'] ?? ''
    );

    return new PDO(
        $dsn,
        $db['user'] ?? '',
        $db['password'] ?? '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function bfi_admin_email() {
    return (string) bfi_config_get('mail.admin_email', 'info@bfinitiatives.com');
}

function bfi_admin_name() {
    return (string) bfi_config_get('mail.admin_name', 'BFI Team');
}

function bfi_from_email() {
    return (string) bfi_config_get('mail.from_email', 'info@bfinitiatives.com');
}

function bfi_from_name() {
    return (string) bfi_config_get('mail.from_name', 'BFI Initiatives');
}

function bfi_no_reply_email() {
    return (string) bfi_config_get('mail.no_reply_email', 'noreply@bfinitiatives.com');
}

function bfi_public_url($path = '') {
    $baseUrl = rtrim((string) bfi_config_get('app.base_url', ''), '/');
    if ($path === '') {
        return $baseUrl;
    }

    if ($baseUrl === '') {
        return ltrim($path, '/');
    }

    return $baseUrl . '/' . ltrim($path, '/');
}

function bfi_require_phpmailer() {
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $candidates = array_filter([
        bfi_config_get('app.vendor_autoload', ''),
        __DIR__ . '/vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
        '/home/bfinitia/public_html/scholar-portal/vendor/autoload.php',
    ]);

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            require_once $candidate;
            $loaded = true;
            return;
        }
    }

    throw new RuntimeException(
        'PHPMailer autoload file not found. Set app.vendor_autoload in app_secrets.php or BFI_VENDOR_AUTOLOAD.'
    );
}

function bfi_configure_mailer($mail, array $overrides = []) {
    $debug = array_key_exists('debug', $overrides)
        ? (int) $overrides['debug']
        : (int) bfi_config_get('mail.smtp_debug', 0);

    $mail->SMTPDebug = $debug;
    if ($debug > 0) {
        $mail->Debugoutput = function ($message, $level) {
            error_log('PHPMailer debug: ' . $message);
        };
    }

    $mail->isSMTP();
    $mail->Host = (string) bfi_config_get('mail.host', '');
    $mail->SMTPAuth = true;
    $mail->Username = (string) bfi_config_get('mail.username', '');
    $mail->Password = (string) bfi_config_get('mail.password', '');

    $encryption = (string) bfi_config_get('mail.encryption', 'smtps');
    if ($encryption === 'smtps') {
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($encryption === 'tls') {
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $mail->SMTPSecure = $encryption;
    }

    $mail->Port = (int) bfi_config_get('mail.port', 465);
    $mail->Timeout = (int) ($overrides['timeout'] ?? 30);
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];

    $fromEmail = (string) ($overrides['from_email'] ?? bfi_from_email());
    $fromName = (string) ($overrides['from_name'] ?? bfi_from_name());
    $replyToEmail = (string) ($overrides['reply_to_email'] ?? bfi_config_get('mail.reply_to_email', $fromEmail));
    $replyToName = (string) ($overrides['reply_to_name'] ?? bfi_config_get('mail.reply_to_name', $fromName));

    $mail->setFrom($fromEmail, $fromName);
    if ($replyToEmail !== '') {
        $mail->addReplyTo($replyToEmail, $replyToName);
    }
}

function bfi_mail_headers(array $options = []) {
    $fromEmail = (string) ($options['from_email'] ?? bfi_from_email());
    $fromName = (string) ($options['from_name'] ?? bfi_from_name());
    $replyToEmail = $options['reply_to_email'] ?? null;
    $isHtml = !empty($options['html']);

    $headers = [];
    if ($isHtml) {
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type:text/html;charset=UTF-8';
    }

    if ($fromName !== '') {
        $headers[] = "From: {$fromName} <{$fromEmail}>";
    } else {
        $headers[] = "From: {$fromEmail}";
    }

    if ($replyToEmail) {
        $headers[] = "Reply-To: {$replyToEmail}";
    }

    return implode("\r\n", $headers);
}
