<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_GET['document_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Verify document belongs to user
    $stmt = $conn->prepare("
        SELECT id FROM user_documents 
        WHERE id = $1 AND user_id = $2
    ");
    $stmt->execute([$_GET['document_id'], $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Document not found']);
        exit();
    }
    
    // Get document history
    $stmt = $conn->prepare("
        SELECT 
            da.*,
            u.first_name as user_first_name,
            u.last_name as user_last_name,
            a.first_name as admin_first_name,
            a.last_name as admin_last_name
        FROM document_activities da
        LEFT JOIN users u ON da.user_id = u.id
        LEFT JOIN admins a ON da.admin_id = a.id
        WHERE da.document_id = $1
        ORDER BY da.created_at DESC
    ");
    $stmt->execute([$_GET['document_id']]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format activities for display
    $formatted_activities = array_map(function($activity) {
        $actor = $activity['admin_first_name'] ? 
            $activity['admin_first_name'] . ' ' . $activity['admin_last_name'] . ' (Admin)' :
            $activity['user_first_name'] . ' ' . $activity['user_last_name'];
            
        $icon = getActivityIcon($activity['activity_type']);
        $description = getActivityDescription($activity['activity_type'], $activity['description']);
        
        return [
            'id' => $activity['id'],
            'actor' => $actor,
            'description' => $description,
            'icon' => $icon,
            'created_at' => $activity['created_at'],
            'formatted_time' => timeAgo($activity['created_at'])
        ];
    }, $activities);
    
    echo json_encode(['success' => true, 'activities' => $formatted_activities]);
    
} catch (Exception $e) {
    error_log("Document history error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function getActivityIcon($type) {
    $icons = [
        'uploaded' => 'fa-upload',
        'viewed' => 'fa-eye',
        'downloaded' => 'fa-download',
        'reviewed' => 'fa-search',
        'feedback_given' => 'fa-comment',
        'revision_uploaded' => 'fa-edit',
        'approved' => 'fa-check-circle',
        'shared' => 'fa-share'
    ];
    return $icons[$type] ?? 'fa-file';
}

function getActivityDescription($type, $description) {
    $descriptions = [
        'uploaded' => 'Document uploaded',
        'viewed' => 'Document viewed',
        'downloaded' => 'Document downloaded',
        'reviewed' => 'Document reviewed',
        'feedback_given' => 'Feedback provided',
        'revision_uploaded' => 'Revision uploaded',
        'approved' => 'Document approved',
        'shared' => 'Document shared'
    ];
    
    $base = $descriptions[$type] ?? 'Activity recorded';
    return $description ? $base . ': ' . $description : $base;
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return date('M j, Y', strtotime($datetime));
}
?>