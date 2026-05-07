<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

// Check if user is logged in and is a primary scholar
if (!isset($_SESSION['user_id']) || $_SESSION['user_program'] !== 'primary') {
    header('Location: login.php');
    exit();
}

$upload_message = '';
$upload_status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['result_file'])) {
        $file = $_FILES['result_file'];
        $allowed_types = ['pdf', 'jpg', 'jpeg', 'png'];
        $max_size = 5 * 1024 * 1024; // 5MB

        // Get file extension
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Validate file
        if (!in_array($file_ext, $allowed_types)) {
            $upload_message = 'Invalid file type. Only PDF, JPG, JPEG, and PNG files are allowed.';
            $upload_status = 'error';
        } elseif ($file['size'] > $max_size) {
            $upload_message = 'File size too large. Maximum size is 5MB.';
            $upload_status = 'error';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $upload_message = 'An error occurred during upload. Please try again.';
            $upload_status = 'error';
        } else {
            // Generate unique filename
            $new_filename = uniqid('result_') . '.' . $file_ext;
            $upload_dir = 'uploads/results/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $upload_path = $upload_dir . $new_filename;

            try {
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $db = new Database();
                    $conn = $db->getConnection();

                    // Update ward_details with new result upload
                    $stmt = $conn->prepare("
                        UPDATE ward_details 
                        SET last_result_upload = CURRENT_TIMESTAMP,
                            result_file_path = :file_path
                        WHERE user_id = :user_id
                    ");

                    $stmt->execute([
                        ':file_path' => $upload_path,
                        ':user_id' => $_SESSION['user_id']
                    ]);

                    $upload_message = 'Term result uploaded successfully!';
                    $upload_status = 'success';
                } else {
                    $upload_message = 'Failed to upload file. Please try again.';
                    $upload_status = 'error';
                }
            } catch (Exception $e) {
                $upload_message = 'Database error occurred. Please try again.';
                $upload_status = 'error';
                error_log("Upload error: " . $e->getMessage());
            }
        }
    }
}

// Fetch last upload info
try {
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->prepare("
        SELECT last_result_upload, current_term 
        FROM ward_details 
        WHERE user_id = :user_id
    ");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $ward_info = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching ward info: " . $e->getMessage());
    $ward_info = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Term Result</title>
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
        
        
        .upload-container {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 20px auto;
        }

        .upload-header {
            margin-bottom: 20px;
            text-align: center;
        }

        .upload-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .upload-form {
            text-align: center;
        }

        .file-input-container {
            border: 2px dashed #ccc;
            padding: 30px;
            border-radius: 5px;
            margin: 20px 0;
            cursor: pointer;
            transition: border-color 0.3s ease;
        }

        .file-input-container:hover {
            border-color: #007bff;
        }

        .file-input {
            display: none;
        }

        .upload-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s ease;
        }

        .upload-btn:hover {
            background: #0056b3;
        }

        .message {
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .requirements {
            margin-top: 20px;
            font-size: 0.9em;
            color: #666;
        }

        #selected-file-name {
            margin-top: 10px;
            font-size: 0.9em;
            color: #666;
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
        <h2>Primary Scholar Portal</h2>
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
        <div class="upload-container">
            <div class="upload-header">
                <h1>Upload Term Result</h1>
            </div>

            <div class="upload-info">
                <p><strong>Current Term:</strong> <?php echo htmlspecialchars($ward_info['current_term'] ?? 'Not set'); ?></p>
                <p><strong>Last Upload:</strong> 
                    <?php
                    if (!empty($ward_info['last_result_upload'])) {
                        echo htmlspecialchars(date('F j, Y g:i A', strtotime($ward_info['last_result_upload'])));
                    } else {
                        echo 'No previous upload';
                    }
                    ?>
                </p>
            </div>

            <?php if ($upload_message): ?>
                <div class="message <?php echo $upload_status; ?>">
                    <?php echo htmlspecialchars($upload_message); ?>
                </div>
            <?php endif; ?>

            <form class="upload-form" method="POST" enctype="multipart/form-data">
                <div class="file-input-container" onclick="document.getElementById('result_file').click();">
                    <i class="fas fa-cloud-upload-alt fa-3x"></i>
                    <p>Click to select or drag and drop your file here</p>
                    <input type="file" id="result_file" name="result_file" class="file-input" accept=".pdf,.jpg,.jpeg,.png" onchange="updateFileName(this)">
                    <div id="selected-file-name"></div>
                </div>

                <button type="submit" class="upload-btn">
                    <i class="fas fa-upload"></i> Upload Result
                </button>

                <div class="requirements">
                    <p><strong>Requirements:</strong></p>
                    <ul>
                        <li>Accepted formats: PDF, JPG, JPEG, PNG</li>
                        <li>Maximum file size: 5MB</li>
                        <li>Must be clear and readable</li>
                        <li>Must include school's stamp and signature</li>
                    </ul>
                </div>
            </form>
        </div>
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

function updateFileName(input) {
            const fileNameDisplay = document.getElementById('selected-file-name');
            if (input.files.length > 0) {
                fileNameDisplay.textContent = 'Selected file: ' + input.files[0].name;
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
            const fileInput = document.getElementById('result_file');
            
            fileInput.files = files;
            updateFileName(fileInput);
        }
</script>
</body>
</html>