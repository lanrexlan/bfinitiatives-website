<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/mailer.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Please log in to upload documents.";
    header('Location: login.php');
    exit();
}

// Check if file was submitted
if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error_message'] = "Error uploading file. Please try again.";
    header('Location: documents.php');
    exit();
}

// Validate document type
if (!isset($_POST['document_type']) || empty($_POST['document_type'])) {
    $_SESSION['error_message'] = "Please select a document type.";
    header('Location: documents.php');
    exit();
}

$document_type = $_POST['document_type'];
$notes = isset($_POST['notes']) ? $_POST['notes'] : '';

// Validate file type
$allowed_extensions = ['pdf', 'doc', 'docx'];
$file_ext = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));

if (!in_array($file_ext, $allowed_extensions)) {
    $_SESSION['error_message'] = "Invalid file type. Only PDF, DOC, and DOCX are allowed.";
    header('Location: documents.php');
    exit();
}

// Check file size (10MB limit)
if ($_FILES['document']['size'] > 10 * 1024 * 1024) {
    $_SESSION['error_message'] = "File size exceeds the 10MB limit.";
    header('Location: documents.php');
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if document of this type already exists for the user
    $check_stmt = $conn->prepare("
        SELECT id, version FROM user_documents 
        WHERE user_id = :user_id AND document_type = :doc_type
        ORDER BY version DESC
        LIMIT 1
    ");
    
    $check_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':doc_type' => $document_type
    ]);
    
    $existing_doc = $check_stmt->fetch(PDO::FETCH_ASSOC);
    $version = 1;
    
    if ($existing_doc) {
        $version = ($existing_doc['version'] ?? 0) + 1;
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = 'uploads/documents/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate unique filename
    $new_filename = $_SESSION['user_id'] . '_' . $document_type . '_v' . $version . '_' . time() . '.' . $file_ext;
    $original_filename = $_FILES['document']['name'];
    
    // Move uploaded file
    if (move_uploaded_file($_FILES['document']['tmp_name'], $upload_dir . $new_filename)) {
        // Check if user_documents table exists, create if not
        $check_table = $conn->query("
            SELECT EXISTS (
                SELECT 1 
                FROM information_schema.tables 
                WHERE table_name = 'user_documents'
            )
        ");
        
        $table_exists = $check_table->fetchColumn();
        
        if (!$table_exists) {
            // Create table
            $conn->exec("
                CREATE TABLE user_documents (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER NOT NULL,
                    document_type VARCHAR(50) NOT NULL,
                    file_name VARCHAR(255) NOT NULL,
                    upload_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    status VARCHAR(20) DEFAULT 'submitted',
                    review_status VARCHAR(20) DEFAULT 'pending',
                    feedback TEXT,
                    feedback_date TIMESTAMP,
                    admin_id INTEGER,
                    version INTEGER DEFAULT 1,
                    additional_data JSONB,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            ");
        }
        
        // Prepare additional data
        $additional_data = null;
        if (!empty($notes)) {
            $additional_data = json_encode(['notes' => $notes]);
        }
        
        // Insert document record and get the document ID
        $insert_stmt = $conn->prepare("
            INSERT INTO user_documents (
                user_id,
                document_type,
                file_name,
                upload_date,
                status,
                review_status,
                version,
                additional_data
            ) VALUES (
                :user_id,
                :doc_type,
                :file_name,
                NOW(),
                'submitted',
                'pending',
                :version,
                :additional_data
            ) RETURNING id
        ");
        
        $insert_stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':doc_type' => $document_type,
            ':file_name' => $new_filename,
            ':version' => $version,
            ':additional_data' => $additional_data
        ]);
        
        // Get the inserted document ID
        $document_result = $insert_stmt->fetch(PDO::FETCH_ASSOC);
        $document_id = $document_result['id'];
        
        // Check if admin_notifications table exists, create if not
        $check_table = $conn->query("
            SELECT EXISTS (
                SELECT 1 
                FROM information_schema.tables 
                WHERE table_name = 'admin_notifications'
            )
        ");
        
        $table_exists = $check_table->fetchColumn();
        
        if (!$table_exists) {
            // Create table
            $conn->exec("
                CREATE TABLE admin_notifications (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER NOT NULL,
                    type VARCHAR(50) NOT NULL,
                    message TEXT NOT NULL,
                    link VARCHAR(255),
                    is_read BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            ");
        }
        
        // Add notification for admins
        $notification_stmt = $conn->prepare("
            INSERT INTO admin_notifications (
                user_id,
                type,
                message,
                link,
                created_at
            ) VALUES (
                :user_id,
                'document_uploaded',
                :message,
                :link,
                NOW()
            )
        ");
        
        $message = "New " . ucfirst($document_type) . " document uploaded";
        $link = "admin-document-review.php?type=" . $document_type . "&status=pending";
        
        $notification_stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':message' => $message,
            ':link' => $link
        ]);
        
        // Get user information for email notifications
        $user_stmt = $conn->prepare("
            SELECT first_name, last_name, email
            FROM users
            WHERE id = :user_id
        ");
        $user_stmt->execute([':user_id' => $_SESSION['user_id']]);
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Send email notifications if user information is available
        if ($user) {
            $scholar_name = $user['first_name'] . ' ' . $user['last_name'];
            $scholar_email = $user['email'];
            $scholar_first_name = $user['first_name'];
            
            // Send email notification to admins
            try {
                $admin_result = sendDocumentUploadNotificationToAdmin(
                    $scholar_name,
                    $scholar_email,
                    $document_type,
                    $original_filename,
                    $document_id
                );
                
                if (!$admin_result['success']) {
                    error_log("Failed to send admin notification email: " . $admin_result['message']);
                }
            } catch (Exception $e) {
                error_log("Error sending admin notification email: " . $e->getMessage());
            }
            
            // Send confirmation email to scholar
            try {
                $scholar_result = sendDocumentUploadConfirmationToScholar(
                    $scholar_email,
                    $scholar_first_name,
                    $document_type,
                    $original_filename
                );
                
                if (!$scholar_result['success']) {
                    error_log("Failed to send scholar confirmation email: " . $scholar_result['message']);
                }
            } catch (Exception $e) {
                error_log("Error sending scholar confirmation email: " . $e->getMessage());
            }
        }
        
        $_SESSION['success_message'] = "Your document has been uploaded successfully and sent for review. You will be notified via email once the review is complete.";
    } else {
        $_SESSION['error_message'] = "Failed to move uploaded file.";
    }
    
} catch (Exception $e) {
    error_log("Document upload error: " . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
}

// Redirect back to documents page
header('Location: documents.php?type=' . $document_type);
exit();
?>