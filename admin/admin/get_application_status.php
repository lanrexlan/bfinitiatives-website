<?php
// get_application_status.php
session_start();
header('Content-Type: application/json');

// Check if user is logged in as admin
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get application ID from query string
if (empty($_GET['application_id'])) {
    echo json_encode(['error' => 'Missing application ID']);
    exit;
}

$application_id = $_GET['application_id'];

// Get database connection
require_once 'includes/db.php';
$db = new Database();
$conn = $db->getConnection();

// Get current status
$query = "SELECT status, admin_comments FROM scholarship_applications WHERE application_id = :application_id";
$stmt = $conn->prepare($query);
$stmt->execute([':application_id' => $application_id]);
$application = $stmt->fetch(PDO::FETCH_ASSOC);

if ($application) {
    echo json_encode([
        'status' => $application['status'], 
        'admin_comments' => $application['admin_comments'] ?? ''
    ]);
} else {
    echo json_encode(['error' => 'Application not found']);
}
exit;
?>