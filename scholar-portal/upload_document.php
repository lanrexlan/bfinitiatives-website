<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

// Define upload constants
define('UPLOAD_DIR', 'uploads/documents/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_TYPES', ['application/pdf', 'image/jpeg', 'image/png']);

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// Update the file upload handling section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    try {
        // Debug logging
        error_log("Starting file upload process");
        error_log("File details: " . print_r($_FILES['document'], true));
        
        $file = $_FILES['document'];
        $documentType = $_POST['document_type'];
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Upload failed with error code: " . $file['error']);
        }
        
        // Check file size
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception("File is too large. Maximum size is 5MB.");
        }
        
        // Check file type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $fileType = $finfo->file($file['tmp_name']);
        
        if (!in_array($fileType, ALLOWED_TYPES)) {
            throw new Exception("Invalid file type. Allowed types: PDF, JPEG, PNG");
        }
        
        // Generate safe filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $uniqueName = uniqid($_SESSION['user_id'] . '_') . '.' . $extension;
        $uploadPath = UPLOAD_DIR . $uniqueName;
        
        // Debug log
        error_log("Attempting to move file to: " . $uploadPath);
        
        // Move file
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception("Failed to move uploaded file.");
        }
        
        // Save to database
        $db = new Database();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            INSERT INTO documents (
                user_id,
                document_type,
                file_name,
                file_path,
                file_size,
                file_type,
                uploaded_at
            ) VALUES (
                :user_id,
                :document_type,
                :file_name,
                :file_path,
                :file_size,
                :file_type,
                CURRENT_TIMESTAMP
            )
        ");
        
        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':document_type' => $documentType,
            ':file_name' => $file['name'],
            ':file_path' => $uploadPath,
            ':file_size' => $file['size'],
            ':file_type' => $fileType
        ]);
        
        // Update the required_documents table using PostgreSQL's UPSERT
$updateStmt = $conn->prepare("
    INSERT INTO required_documents (user_id, document_type, status, updated_at)
    VALUES (:user_id, :document_type, 'approved', CURRENT_TIMESTAMP)
    ON CONFLICT (user_id, document_type) 
    DO UPDATE SET 
        status = 'approved',
        updated_at = CURRENT_TIMESTAMP
");

try {
    $updateStmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':document_type' => $documentType
    ]);
    
    // Log success
    error_log("Required documents table updated successfully for user {$_SESSION['user_id']}, document type: {$documentType}");
} catch (Exception $e) {
    error_log("Error updating required_documents table: " . $e->getMessage());
}
        
        // Log success
        error_log("File uploaded successfully: " . $uploadPath);
        $success_message = "Document uploaded successfully!";
        
        
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
        exit();
        
    } catch (Exception $e) {
        error_log("Upload error: " . $e->getMessage());
        $error_message = $e->getMessage();
    }
}

// Add this at the top of your file to handle the success redirect
if (isset($_GET['success'])) {
    $success_message = "Document uploaded successfully!";
}

// Create the documents table if it doesn't exist
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $conn->exec("
        CREATE TABLE IF NOT EXISTS documents (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL,
            document_type VARCHAR(50) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_size INTEGER NOT NULL,
            file_type VARCHAR(50) NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
} catch (Exception $e) {
    error_log("Database setup error: " . $e->getMessage());
}

// Rest of your existing code...

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$success_message = '';
$error_message = '';
$first_name = 'User'; // Default value

// Get user data
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Debug log
    error_log("Fetching user data for ID: " . $_SESSION['user_id']);
    
    $stmt = $conn->prepare("
        SELECT first_name, last_name 
        FROM users 
        WHERE id = :user_id
    ");
    
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && isset($user['first_name'])) {
        $first_name = $user['first_name'];
        error_log("Found first_name: " . $first_name);
    } else {
        error_log("No user data found or first_name is null");
    }
    
} catch (Exception $e) {
    error_log("Error fetching user data: " . $e->getMessage());
}

// Get existing documents
try {
    $stmt = $conn->prepare("
        SELECT * FROM documents 
        WHERE user_id = :user_id 
        ORDER BY uploaded_at DESC
    ");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching documents: " . $e->getMessage());
    $documents = [];
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    // Your existing upload handling code here
}

// Debug output
error_log("Final first_name value before display: " . $first_name);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Documents - BFI Scholar Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Include your existing styles */
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #48cae4;
            --text-color: #333;
            --bg-color: #f4f6f8;
            --sidebar-width: 250px;
            --header-height: 70px;
            --footer-height: 60px;
        }

        body {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)),
                        url('/Images/programss-bg.jpeg') center/cover fixed;
            color: var(--text-color);
            min-height: 100vh;
        }

        /* Header Styles */
        .header {
            position: fixed;
            top: 0;
            right: 0;
            left: var(--sidebar-width);
            height: var(--header-height);
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            z-index: 100;
            backdrop-filter: blur(10px);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notification-bell {
            position: relative;
            cursor: pointer;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .progress-circle {
            position: relative;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #f0f0f0;
            margin: 0 auto;
        }

        .progress-circle::after {
            content: attr(data-progress) '%';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .quick-action-btn {
            background: white;
            border: none;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .quick-action-btn i {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 300px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            padding: 15px;
            display: none;
            z-index: 1000;
        }

        .notification-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        /* New animated background */
        .gradient-background {
            background: linear-gradient(-45deg, #4361ee, #3f37c9, #48cae4, #4895ef);
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
        }

        @keyframes gradient {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            position: fixed;
            left: 0; /* Align to extreme left */
            top: 0; /* Align to top */
            margin: 0; /* Remove any margin */
            height: 100vh;
            transition: 0.3s;
            backdrop-filter: blur(10px);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 20px 0;
            text-align: center;
            border-bottom: 1px solid rgba(238, 238, 238, 0.5);
            margin-bottom: 20px;
        }

        .sidebar-header h2 {
            color: var(--primary-color);
            font-size: 1.5rem;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 10px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: var(--text-color);
            text-decoration: none;
            border-radius: 8px;
            transition: 0.3s;
        }

        .nav-link:hover {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            transform: translateX(5px);
        }

        .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Main Content Styles */
        .container {
            display: flex;
            min-height: 100vh;
            padding-bottom: var(--footer-height);
        }

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            margin-top: var(--header-height);
            padding: 20px;
        }

        .dashboard-header {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }

        .welcome-message {
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            color: var(--text-color);
            font-size: 1.1rem;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 2rem;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: bold;
        }

        .recent-activity {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid rgba(238, 238, 238, 0.5);
            transition: background-color 0.3s;
        }

        .activity-item:hover {
            background-color: rgba(67, 97, 238, 0.05);
            padding-left: 10px;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-item .time {
            color: #666;
            font-size: 0.9rem;
        }

        /* Footer Styles */
        .footer {
            position: fixed;
            bottom: 0;
            right: 0;
            left: var(--sidebar-width);
            height: var(--footer-height);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            box-shadow: 0 -2px 5px rgba(0,0,0,0.1);
        }

        .footer-links {
            display: flex;
            gap: 20px;
        }

        .footer-links a {
            color: var(--text-color);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .footer-links a:hover {
            color: var(--primary-color);
        }
        
        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .header {
                left: 0;
            }

            .main-content {
                margin-left: 0;
            }

            .footer {
                left: 0;
            }

            .mobile-toggle {
                display: block;
                position: fixed;
                top: 20px;
                left: 20px;
                z-index: 1001;
                background: var(--primary-color);
                color: white;
                padding: 10px;
                border-radius: 5px;
                cursor: pointer;
            }

            .header-right {
                gap: 10px;
            }

            .user-profile span {
                display: none;
            }
        }
        
        .upload-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .document-list {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .upload-form {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .form-group select,
        .form-group input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .submit-btn {
            background: var(--primary-color);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .submit-btn:hover {
            background: var(--secondary-color);
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .document-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .document-item:last-child {
            border-bottom: none;
        }
        
        .document-info {
            flex: 1;
        }
        
        .document-actions {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            border: none;
            transition: 0.3s;
        }
        
        .view-btn {
            background: #4361ee;
            color: white;
        }
        
        .delete-btn {
            background: #dc3545;
            color: white;
        }
        
        .document-list {
    background: rgba(255, 255, 255, 0.95);
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    margin-top: 20px;
}

.document-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #eee;
}

.document-item:last-child {
    border-bottom: none;
}

.document-info {
    flex: 1;
}

.document-actions {
    display: flex;
    gap: 10px;
}

.action-btn {
    padding: 5px 10px;
    border-radius: 3px;
    cursor: pointer;
    border: none;
    transition: 0.3s;
}

.view-btn {
    background: #4361ee;
    color: white;
}

.delete-btn {
    background: #dc3545;
    color: white;
}
    </style>
</head>
<body>
    <div class="container">
        <!-- Include your sidebar and header -->
         <!-- Header -->
        <!-- Header -->
<header class="header">
    <div class="header-left">
        <div class="search-bar">
            <input type="search" placeholder="Search..." style="padding: 8px; border-radius: 20px; border: 1px solid #ddd;">
        </div>
    </div>
    <div class="header-right">
        <div class="notification-bell">
            <i class="fas fa-bell"></i>
            <span class="notification-badge">3</span>
        </div>
        <div class="user-profile">
    <div class="user-avatar">
        <?php echo strtoupper(substr($first_name ?? 'U', 0, 1)); ?>
    </div>
    <span><?php echo htmlspecialchars($first_name ?? 'User'); ?></span>
</div>
    </div>
</header>
        
         <!-- Sidebar -->
        <div class="sidebar">
    <div class="sidebar-header">
        <h2>Primary Scholar</h2>
    </div>
    <ul class="nav-menu">
        <li class="nav-item">
            <a href="dashboard_primary.php" class="nav-link">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="ward-profile.php" class="nav-link">
                <i class="fas fa-child"></i>
                Ward's Profile
            </a>
        </li>
        <li class="nav-item">
            <a href="upload-result.php" class="nav-link">
                <i class="fas fa-file-upload"></i>
                Upload Results
            </a>
        </li>
        <li class="nav-item">
            <a href="support.php" class="nav-link">
                <i class="fas fa-life-ring"></i>
                Support
            </a>
        </li>
        <li class="nav-item">
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </li>
    </ul>
</div>

    
        
        <div class="main-content">
            <div class="dashboard-header">
    <h1 class="welcome-message">Upload Documents</h1>
    <p>Welcome, <?php echo htmlspecialchars($first_name ?? 'User'); ?>! Please upload the required documents for your ward's primary school scholarship.</p>
</div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            


<div class="requirements-info" style="margin-bottom: 20px; padding: 15px; background: rgba(255, 255, 255, 0.95); border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
    <h3 style="margin-bottom: 10px;">Required Documents</h3>
    <ul style="list-style: none; padding: 0;">
        <li style="margin-bottom: 10px;">
            <i class="fas fa-check-circle" style="color: var(--primary-color);"></i>
            Birth Certificate (Scanned copy of original document)
        </li>
        <li style="margin-bottom: 10px;">
            <i class="fas fa-check-circle" style="color: var(--primary-color);"></i>
            School Admission Letter (Current academic year)
        </li>
        <li style="margin-bottom: 10px;">
            <i class="fas fa-check-circle" style="color: var(--primary-color);"></i>
            Guardian's ID (Valid government-issued ID)
        </li>
    </ul>
</div>

            
            <div class="upload-container">
                <form class="upload-form" method="POST" enctype="multipart/form-data" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo MAX_FILE_SIZE; ?>" />
    
<div class="form-group">
    <label for="document_type">Document Type</label>
    <select name="document_type" id="document_type" required class="form-control">
        <option value="">Select Document Type</option>
        <option value="birth_certificate">Birth Certificate</option>
        <option value="admission_letter">School Admission Letter</option>
        <option value="guardian_id">Guardian's ID</option>
    </select>
</div>

<div class="form-group">
    <label for="document">Select File</label>
    <input type="file" name="document" id="document" required class="form-control" 
           accept=".pdf,.jpg,.jpeg,.png">
    <small class="form-text text-muted">
        Maximum file size: 5MB. Allowed formats: PDF, JPEG, PNG
    </small>
</div>

<button type="submit" class="submit-btn">
    <i class="fas fa-upload"></i> Upload Document
</button>


<div class="document-list">
    <h2>Uploaded Documents</h2>
    <?php if (!empty($documents)): ?>
        <?php foreach ($documents as $document): ?>
            <div class="document-item">
                <div class="document-info">
                    <h3><?php echo htmlspecialchars($document['document_type']); ?></h3>
                    <p>Uploaded: <?php echo date('M j, Y g:i A', strtotime($document['uploaded_at'])); ?></p>
                </div>
                <div class="document-actions">
                    <a href="<?php echo htmlspecialchars($document['file_path']); ?>" 
                       class="action-btn view-btn" target="_blank">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <button class="action-btn delete-btn" 
                            onclick="deleteDocument(<?php echo $document['id']; ?>)">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No documents uploaded yet.</p>
    <?php endif; ?>
</div>

<div class="sidebar">
    <div class="sidebar-header">
        <h2>Scholar Portal</h2>
    </div>
    <ul class="nav-menu">
        <li class="nav-item">
            <a href="dashboard_primary.php" class="nav-link">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="ward-profile.php" class="nav-link">
                <i class="fas fa-child"></i>
                Ward's Profile
            </a>
        </li>
        <li class="nav-item">
            <a href="upload-result.php" class="nav-link">
                <i class="fas fa-file-upload"></i>
                Upload Results
            </a>
        </li>
    
        <li class="nav-item">
            <a href="support.php" class="nav-link">
                <i class="fas fa-life-ring"></i>
                Support
            </a>
        </li>
        <li class="nav-item">
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </li>
    </ul>
</div>



    <!-- Footer -->
        <footer class="footer">
            <div class="footer-copyright">
                © 2024 Bright Future Initiatives. All rights reserved.
            </div>
            <div class="footer-links">
                <a href="/"><i class="fas fa-home me-2"></i>Home</a>
                        <a href="/about-us"><i class="fas fa-info-circle me-2"></i>About Us</a>
                        <a href="/programs"><i class="fas fa-graduation-cap me-2"></i>Programs</a>
            </div>
        </footer>
    <script>
        function deleteDocument(documentId) {
            if (confirm('Are you sure you want to delete this document?')) {
                // Add delete functionality here
                window.location.href = `delete-document.php?id=${documentId}`;
            }
        }
    </script>
    
    <script>
// Add this to your existing JavaScript
document.querySelector('.upload-form').addEventListener('submit', function(e) {
    const fileInput = document.querySelector('#document');
    const submitBtn = document.querySelector('.submit-btn');
    
    if (fileInput.files.length > 0) {
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
        submitBtn.disabled = true;
    }
});
</script>

<!-- JavaScript for file upload -->
<script>
function updateFileName(input) {
    const fileNameDisplay = document.getElementById('selected-file-name');
    if (input.files.length > 0) {
        const file = input.files[0];
        const fileSize = (file.size / 1024 / 1024).toFixed(2); // Convert to MB
        fileNameDisplay.innerHTML = `
            <div style="margin-top: 10px;">
                <p><strong>Selected file:</strong> ${file.name}</p>
                <p><strong>Size:</strong> ${fileSize} MB</p>
            </div>
        `;
    } else {
        fileNameDisplay.textContent = '';
    }
}

// Drag and drop functionality
const dropZone = document.querySelector('.file-input-container');

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, unhighlight, false);
});

function highlight(e) {
    dropZone.style.borderColor = '#007bff';
}

function unhighlight(e) {
    dropZone.style.borderColor = '#ccc';
}

dropZone.addEventListener('drop', handleDrop, false);

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    const fileInput = document.getElementById('document');
    
    fileInput.files = files;
    updateFileName(fileInput);
}

// Add loading state to form submission
document.querySelector('.upload-form').addEventListener('submit', function(e) {
    const fileInput = document.querySelector('#document');
    const submitBtn = document.querySelector('.submit-btn');
    
    if (fileInput.files.length > 0) {
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
        submitBtn.disabled = true;
    }
});
</script>

<script>
    function deleteDocument(documentId) {
        if (confirm('Are you sure you want to delete this document?')) {
            // Add delete functionality here
            window.location.href = `delete-document.php?id=${documentId}`;
        }
    }
</script>
</body>
</html>