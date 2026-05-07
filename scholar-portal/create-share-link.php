<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$document_id = $input['document_id'] ?? null;
$expires_days = $input['expires_days'] ?? 7;
$max_access = $input['max_access'] ?? null;

if (!$document_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Document ID required']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Verify document belongs to user
    $stmt = $conn->prepare("
        SELECT id, file_name FROM user_documents 
        WHERE id = $1 AND user_id = $2
    ");
    $stmt->execute([$document_id, $_SESSION['user_id']]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        http_response_code(404);
        echo json_encode(['error' => 'Document not found']);
        exit();
    }
    
    // Generate unique token
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime("+{$expires_days} days"));
    
    // Create share record
    $stmt = $conn->prepare("
        INSERT INTO document_shares (document_id, share_token, created_by, expires_at, max_access) 
        VALUES ($1, $2, $3, $4, $5)
    ");
    $stmt->execute([$document_id, $token, $_SESSION['user_id'], $expires_at, $max_access]);
    
    // Log the sharing activity
    try {
        $log_stmt = $conn->prepare("
            INSERT INTO document_activities (document_id, user_id, activity_type, description) 
            VALUES ($1, $2, $3, $4)
        ");
        $log_stmt->execute([
            $document_id, 
            $_SESSION['user_id'], 
            'shared', 
            'Share link created (expires: ' . date('Y-m-d', strtotime($expires_at)) . ')'
        ]);
    } catch (Exception $e) {
        // Log error but don't fail the request
        error_log("Error logging share activity: " . $e->getMessage());
    }
    
    $share_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . 
                 $_SERVER['HTTP_HOST'] . 
                 dirname($_SERVER['REQUEST_URI']) . 
                 '/shared-document.php?token=' . $token;
    
    echo json_encode([
        'success' => true,
        'share_url' => $share_url,
        'expires_at' => $expires_at,
        'token' => $token
    ]);
    
} catch (Exception $e) {
    error_log("Share link creation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>