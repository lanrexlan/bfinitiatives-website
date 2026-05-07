<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Initialize response array
$response = [
    'success' => false,
    'message' => ''
];

try {
    // Check if document ID is provided
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Invalid document ID');
    }

    $documentId = (int)$_GET['id'];
    $userId = $_SESSION['user_id'];

    // Get database connection
    $db = new Database();
    $conn = $db->getConnection();

    // First, get the document details to check ownership and get file path
    $stmt = $conn->prepare("
        SELECT file_path, document_type 
        FROM documents 
        WHERE id = :id AND user_id = :user_id
    ");
    
    $stmt->execute([
        ':id' => $documentId,
        ':user_id' => $userId
    ]);

    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        throw new Exception('Document not found or you do not have permission to delete it');
    }

    // Begin transaction
    $conn->beginTransaction();

    // Delete the record from database
    $stmt = $conn->prepare("
        DELETE FROM documents 
        WHERE id = :id AND user_id = :user_id
    ");

    $stmt->execute([
        ':id' => $documentId,
        ':user_id' => $userId
    ]);

    // Check if database deletion was successful
    if ($stmt->rowCount() === 0) {
        throw new Exception('Failed to delete document record');
    }

    // Delete the actual file
    $filePath = $document['file_path'];
    if (file_exists($filePath)) {
        if (!unlink($filePath)) {
            // If file deletion fails, rollback database deletion
            $conn->rollBack();
            throw new Exception('Failed to delete document file');
        }
    }

    // Commit the transaction
    $conn->commit();

    // Log the successful deletion
    error_log("Document deleted successfully - ID: $documentId, Type: {$document['document_type']}, User: $userId");

    // Set success message
    $response['success'] = true;
    $response['message'] = 'Document deleted successfully';

    // Redirect back to upload page with success message
    $_SESSION['success_message'] = 'Document deleted successfully';
    header('Location: upload_document.php?success=deleted');
    exit();

} catch (Exception $e) {
    // Rollback transaction if active
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    // Log the error
    error_log("Error deleting document: " . $e->getMessage());

    // Set error message
    $response['message'] = 'Error deleting document: ' . $e->getMessage();

    // Redirect back to upload page with error message
    $_SESSION['error_message'] = 'Error deleting document: ' . $e->getMessage();
    header('Location: upload_document.php?error=delete_failed');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deleting Document - BFI Scholar Portal</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: #f4f6f8;
        }

        .message-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }

        .success {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .error {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .redirect-message {
            margin-top: 15px;
            color: #666;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            margin: 10px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="message-container">
        <?php if ($response['success']): ?>
            <div class="success">
                <?php echo htmlspecialchars($response['message']); ?>
            </div>
        <?php else: ?>
            <div class="error">
                <?php echo htmlspecialchars($response['message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="spinner"></div>
        <div class="redirect-message">
            Redirecting back to documents page...
        </div>
    </div>

    <script>
        // Redirect after 2 seconds if not already redirected by PHP
        setTimeout(function() {
            window.location.href = 'upload_document.php';
        }, 2000);
    </script>
</body>
</html>