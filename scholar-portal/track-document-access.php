<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$token = $input['token'] ?? '';

if (!$token || !$action) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Find the share record
    $stmt = $conn->prepare("
        SELECT ds.document_id, ds.created_by 
        FROM document_shares ds
        WHERE ds.share_token = $1 AND ds.expires_at > CURRENT_TIMESTAMP
    ");
    $stmt->execute([$token]);
    $share = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$share) {
        http_response_code(404);
        echo json_encode(['error' => 'Invalid or expired token']);
        exit();
    }
    
    // Log the activity
    $description = $action === 'download' ? 'Document downloaded via share link' : 'Document accessed via share link';
    
    $log_stmt = $conn->prepare("
        INSERT INTO document_activities (document_id, user_id, activity_type, description) 
        VALUES ($1, $2, $3, $4)
    ");
    $log_stmt->execute([
        $share['document_id'], 
        $share['created_by'], 
        $action === 'download' ? 'downloaded' : 'viewed', 
        $description
    ]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Track access error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>