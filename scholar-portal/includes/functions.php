<?php
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function redirect_if_not_logged_in() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit();
    }
}

function redirect_if_logged_in() {
    if (is_logged_in()) {
        header('Location: dashboard.php');
        exit();
    }
    
    function createRememberMeToken($user_id) {
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            INSERT INTO remember_tokens (user_id, token, expires_at) 
            VALUES ($1, $2, $3)
            ON CONFLICT (token) DO UPDATE 
            SET expires_at = $3
        ");
        $stmt->execute([$user_id, $token, $expires]);
        
        return $token;
    } catch (PDOException $e) {
        error_log("Remember token creation failed: " . $e->getMessage());
        return false;
    }
}
    
    function cleanupExpiredTokens() {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            DELETE FROM remember_tokens 
            WHERE expires_at < CURRENT_TIMESTAMP
        ");
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("Token cleanup failed: " . $e->getMessage());
    }
}
    
    function timeAgo($timestamp) {
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return "Just now";
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . " minute" . ($mins > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } else {
        $days = floor($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    }
}
}
?>