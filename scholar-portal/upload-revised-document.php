<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if necessary data is provided
if (!isset($_POST['original_id']) || !isset($_POST['document_type']) || !isset($_FILES['document'])) {
    $_SESSION['error_message'] = "Missing required information.";
    header('Location: documents.php');
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get original document details
    $stmt = $conn->prepare("
        SELECT
            id,
            document_type,
            version
        FROM
            user_documents
        WHERE
            id = :doc_id AND
            user_id = :user_id
    ");
    
    $stmt->execute([
        ':doc_id' => $_POST['original_id'],
        ':user_id' => $_SESSION['user_id']
    ]);
    
    $original_doc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$original_doc) {
        $_SESSION['error_message'] = "Original document not found.";
        header('Location: documents.php');
        exit();
    }
    
    // Handle file upload
    $upload_dir = 'uploads/documents/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_info = pathinfo($_FILES['document']['name']);
    $file_ext = strtolower($file_info['extension']);
    
    // Check file extension
    $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
    if (!in_array($file_ext, $allowed_extensions)) {
        $_SESSION['error_message'] = "Invalid file type. Allowed types: PDF, DOC, DOCX, JPG, JPEG, PNG.";
        header('Location: documents.php');
        exit();
    }
    
    // Generate new filename
    $new_filename = $_SESSION['user_id'] . '_' . $original_doc['document_type'] . '_v' . ($original_doc['version'] + 1) . '_' . time() . '.' . $file_ext;
    
    // Move uploaded file
    if (move_uploaded_file($_FILES['document']['tmp_name'], $upload_dir . $new_filename)) {
        // Prepare additional data
        $additional_data = [];
        if (isset($_POST['notes']) && !empty($_POST['notes'])) {
            $additional_data['revision_notes'] = $_POST['notes'];
        }
        
        // Insert new document version
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
            )
        ");
        
        $insert_stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':doc_type' => $original_doc['document_type'],
            ':file_name' => $new_filename,
            ':version' => $original_doc['version'] + 1,
            ':additional_data' => !empty($additional_data) ? json_encode($additional_data) : null
        ]);
        
        // Check if admin_notifications table exists
        $check_table = $conn->query("
            SELECT EXISTS (
                SELECT 1 
                FROM information_schema.tables 
                WHERE table_name = 'admin_notifications'
            )
        ");
        
        $table_exists = $check_table->fetchColumn();
        
        if ($table_exists) {
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
                    'document_revised',
                    :message,
                    :link,
                    NOW()
                )
            ");
            
            $message = "A revised " . ucfirst($original_doc['document_type']) . " has been submitted";
            $link = "admin-document-review.php?type=" . $original_doc['document_type'] . "&status=pending";
            
            $notification_stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':message' => $message,
                ':link' => $link
            ]);
        }
        
        $_SESSION['success_message'] = "Your revised document has been submitted successfully.";
    } else {
        $_SESSION['error_message'] = "Failed to upload file. Please try again.";
    }
    
} catch (Exception $e) {
    error_log("Upload revised document error: " . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
}

header('Location: documents.php?type=' . $_POST['document_type']);
exit();
?>