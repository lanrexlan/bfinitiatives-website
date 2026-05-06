<?php
// check_email.php
header('Content-Type: application/json');
require_once __DIR__ . '/app_bootstrap.php';

// Database connection
try {
    $db_connection = bfi_pg_connect('users');
} catch (Exception $e) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get email from request
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

if (empty($email)) {
    echo json_encode(['error' => 'No email provided']);
    exit;
}

// Check if email exists in database
$query = "SELECT COUNT(*) FROM scholarship_applications WHERE email = $1";
$result = pg_query_params($db_connection, $query, array($email));

if (!$result) {
    echo json_encode(['error' => 'Database query failed']);
    exit;
}

$row = pg_fetch_row($result);
$exists = (int)$row[0] > 0;

echo json_encode(['exists' => $exists]);
?>
