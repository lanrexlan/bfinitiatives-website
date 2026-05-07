<?php
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}



function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function is_valid_password($password) {
    // At least 8 characters long
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return false;
    }

    // Check for at least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }

    // Check for at least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }

    // Check for at least one number
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }

    // Check for at least one special character
    if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
        return false;
    }

    return true;
}

/**
 * Generate random token
 */
function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Check if token is expired
 */
function is_token_expired($expiry_timestamp) {
    return strtotime($expiry_timestamp) < time();
}

/**
 * Log admin actions
 */
function log_admin_action($admin_id, $action, $details = '') {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO admin_logs (
                admin_id,
                action,
                details,
                ip_address,
                created_at
            ) VALUES (
                :admin_id,
                :action,
                :details,
                :ip_address,
                CURRENT_TIMESTAMP
            )
        ");

        $stmt->execute([
            ':admin_id' => $admin_id,
            ':action' => $action,
            ':details' => $details,
            ':ip_address' => $_SERVER['REMOTE_ADDR']
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Error logging admin action: " . $e->getMessage());
        return false;
    }
}

/**
 * Check for suspicious activity
 */
function check_suspicious_activity($email) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM login_attempts 
            WHERE email = :email 
            AND attempt_time > CURRENT_TIMESTAMP - INTERVAL '30 minutes'
        ");
        
        $stmt->execute([':email' => $email]);
        $attempts = $stmt->fetchColumn();
        
        return $attempts >= MAX_LOGIN_ATTEMPTS;
    } catch (Exception $e) {
        error_log("Error checking suspicious activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Record login attempt
 */
function record_login_attempt($email, $success) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO login_attempts (
                email,
                success,
                ip_address,
                attempt_time
            ) VALUES (
                :email,
                :success,
                :ip_address,
                CURRENT_TIMESTAMP
            )
        ");

        $stmt->execute([
            ':email' => $email,
            ':success' => $success,
            ':ip_address' => $_SERVER['REMOTE_ADDR']
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Error recording login attempt: " . $e->getMessage());
        return false;
    }
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

// Add this to functions.php
function checkEmailExistence($conn, $email) {
    try {
        // Check in admins table
        $check_stmt = $conn->prepare("
            SELECT id, email, is_active 
            FROM admins 
            WHERE LOWER(email) = LOWER(:email)
        ");
        $check_stmt->execute(['email' => $email]);
        $existing_admin = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_admin) {
            error_log("Email exists check - Found in admins table: " . print_r($existing_admin, true));
            return [
                'exists' => true,
                'details' => 'Email is already registered as an admin.'
            ];
        }

        // Check in authorized_emails table
        $auth_check_stmt = $conn->prepare("
            SELECT id, email, is_used 
            FROM admin_authorized_emails 
            WHERE LOWER(email) = LOWER(:email)
        ");
        $auth_check_stmt->execute(['email' => $email]);
        $authorized = $auth_check_stmt->fetch(PDO::FETCH_ASSOC);

        error_log("Email exists check - Authorization status: " . print_r($authorized, true));

        if (!$authorized) {
            return [
                'exists' => false,
                'authorized' => false,
                'details' => 'Email is not authorized for registration.'
            ];
        }

        if ($authorized['is_used']) {
            return [
                'exists' => true,
                'authorized' => true,
                'details' => 'Email authorization has already been used.'
            ];
        }

        return [
            'exists' => false,
            'authorized' => true,
            'details' => 'Email is authorized and available for registration.'
        ];

    } catch (PDOException $e) {
        error_log("Error checking email existence: " . $e->getMessage());
        throw new Exception("Database error while checking email status.");
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