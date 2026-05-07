<?php
function bfi_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function bfi_csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(bfi_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function bfi_validate_csrf_post(): bool {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
