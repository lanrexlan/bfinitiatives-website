<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

// Check if user is logged in and is a primary scholar
if (!isset($_SESSION['user_id']) || $_SESSION['user_program'] !== 'primary') {
    header('Location: login.php');
    exit();
}

// Initialize default values
$first_name = 'User';
$last_name = '';
$email = '';
$program = '';
$ward_name = '';
$ward_class = '';
$last_result_upload = 'Not uploaded yet';
$current_term = '';
$documents_status = array(
    'birth_certificate' => false,
    'school_admission' => false,
    'current_result' => false,
    'guardian_id' => false
);
$profile_picture = null;

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get user and ward details
    $stmt = $conn->prepare("
        SELECT 
            u.first_name, 
            u.last_name,
            u.email, 
            u.program,
            u.created_at,
            u.profile_picture,
            w.ward_name,
            w.current_class,
            w.last_result_upload,
            w.current_term
        FROM users u 
        LEFT JOIN ward_details w ON u.id = w.user_id 
        WHERE u.id = :user_id
    ");
    
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $first_name = $user['first_name'] ?? 'User';
        $last_name = $user['last_name'] ?? '';
        $email = $user['email'] ?? '';
        $program = $user['program'] ?? '';
        $profile_picture = $user['profile_picture'] ?? null;
        $ward_name = $user['ward_name'] ?? 'Not provided';
        $ward_class = $user['current_class'] ?? 'Not provided';
        $last_result_upload = $user['last_result_upload'] ?? 'Not uploaded yet';
        $current_term = $user['current_term'] ?? 'Not set';
    }
    
    // Get documents status
    $doc_stmt = $conn->prepare("
        SELECT document_type, status 
        FROM required_documents 
        WHERE user_id = :user_id
    ");
    $doc_stmt->execute([':user_id' => $_SESSION['user_id']]);
    while ($doc = $doc_stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($documents_status[$doc['document_type']])) {
            $documents_status[$doc['document_type']] = $doc['status'] == 'approved';
        }
    }
    
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

// Calculate completion percentage based on required documents
$completed_docs = array_filter($documents_status);
$completion_percentage = (count($completed_docs) / count($documents_status)) * 100;
?>


<!-- Rest of your HTML code remains the same -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scholar Portal - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        
        /* Replace/Add these CSS rules in your existing <style> tag */

/* Mobile-First Base Styles */
:root {
    --sidebar-width: 250px;
    --header-height: 70px;
    --footer-height: 60px;
}

/* Mobile Navigation Toggle Button */
.mobile-toggle {
    display: none;
    position: fixed;
    top: 15px;
    left: 15px;
    z-index: 1001;
    background: var(--primary-color);
    color: white;
    padding: 10px;
    border-radius: 5px;
    cursor: pointer;
    border: none;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

/* Responsive Breakpoints */
@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .resources-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    /* Show mobile toggle */
    .mobile-toggle {
        display: block;
    }

    /* Sidebar modifications */
    .sidebar {
        transform: translateX(-100%);
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: 80%;
        max-width: 300px;
        z-index: 1000;
        transition: transform 0.3s ease-in-out;
    }

    .sidebar.active {
        transform: translateX(0);
    }

    /* Header modifications */
    .header {
        left: 0;
        padding: 0 15px;
    }

    .header-left {
        display: none;
    }

    .header-right {
        width: 100%;
        justify-content: flex-end;
    }

    .user-profile span {
        display: none;
    }

    /* Main content modifications */
    .main-content {
        margin-left: 0;
        padding: 15px;
    }

    /* Stats grid modifications */
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    /* Quick actions modifications */
    .quick-actions {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }

    /* Resources grid modifications */
    .resources-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    /* Footer modifications */
    .footer {
        left: 0;
        padding: 0 15px;
        flex-direction: column;
        height: auto;
        padding: 10px 0;
    }

    .footer-links {
        flex-wrap: wrap;
        justify-content: center;
        gap: 10px;
    }

    .footer-copyright {
        font-size: 0.8rem;
        text-align: center;
    }
}

@media (max-width: 480px) {
    /* Further refinements for smaller screens */
    .header {
        height: 60px;
    }

    .welcome-message {
        font-size: 1.2rem;
    }

    .stat-card {
        padding: 15px;
    }

    .stat-card .value {
        font-size: 1.5rem;
    }

    .quick-actions {
        grid-template-columns: 1fr;
    }

    .mobile-toggle {
        top: 10px;
        left: 10px;
    }

    .notification-badge {
        width: 16px;
        height: 16px;
        font-size: 10px;
    }

    .user-avatar {
        width: 35px;
        height: 35px;
    }
}
    
    /* User Avatar Styles */
.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    overflow: hidden;
    background: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.profile-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.avatar-initials {
    color: white;
    font-weight: bold;
    font-size: 1.2em;
    text-transform: uppercase;
}

.fallback-avatar {
    background-color: var(--primary-color);
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.user-name {
    margin-left: 10px;
    font-weight: 500;
}

/* Hover effect for profile picture */
.user-avatar:hover .profile-image {
    transform: scale(1.1);
    transition: transform 0.3s ease;
}

/* Profile picture dropdown (optional) */
.user-profile {
    position: relative;
    cursor: pointer;
}

.profile-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 10px 0;
    min-width: 200px;
    display: none;
    z-index: 1000;
}

.profile-dropdown.active {
    display: block;
}

.profile-dropdown-item {
    padding: 10px 20px;
    color: var(--text-color);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: background-color 0.3s;
}

.profile-dropdown-item:hover {
    background-color: rgba(67, 97, 238, 0.05);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .user-name {
        display: none;
    }
    
    .user-avatar {
        width: 35px;
        height: 35px;
    }
    
    .avatar-initials {
        font-size: 1em;
    }
}
    
/* Overlay for mobile sidebar */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
}

.sidebar-overlay.active {
    display: block;
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
    font-family: 'Poppins', system-ui, -apple-system, sans-serif;
    background-image: url('/Images/programss-bg.jpeg'); /* Replace with your image path */
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    background-repeat: no-repeat;
    min-height: 100vh;
    padding: 40px 20px;
    position: relative;
    color: var(--text-color);
    line-height: 1.6;
    margin: 0;
}
       body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(245, 247, 250, 0.95) 0%, rgba(227, 238, 255, 0.92) 100%);
    z-index: -1;
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
        
        .academic-resources {
    background: rgba(255, 255, 255, 0.95);
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    margin-top: 20px;
}

.section-title {
    color: var(--primary-color);
    margin-bottom: 20px;
    font-size: 1.5rem;
}

.resources-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.resource-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    transition: transform 0.3s ease;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.resource-card:hover {
    transform: translateY(-5px);
}

.resource-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 15px;
    background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.resource-icon i {
    font-size: 24px;
    color: white;
}

.resource-card h3 {
    margin: 10px 0;
    color: var(--text-color);
}

.resource-card p {
    color: #666;
    margin-bottom: 15px;
    font-size: 0.9rem;
}

.resource-link {
    display: inline-block;
    padding: 8px 20px;
    background: var(--primary-color);
    color: white;
    border-radius: 20px;
    text-decoration: none;
    font-size: 0.9rem;
    transition: background 0.3s;
}

.resource-link:hover {
    background: var(--secondary-color);
}
        
        /* New styles for Primary School Dashboard */
.documents-section {
    background: rgba(255, 255, 255, 0.95);
    padding: 25px;
    border-radius: 10px;
    margin-top: 20px;
}

.documents-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.document-card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.document-card:hover {
    transform: translateY(-5px);
}

.document-card i {
    font-size: 2rem;
    color: var(--primary-color);
    margin-bottom: 15px;
}

.document-card.submitted {
    background: #f0fff4;
}

.document-card .status {
    display: block;
    margin: 10px 0;
    color: #666;
}

.upload-btn {
    background: var(--primary-color);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 5px;
    cursor: pointer;
    transition: background 0.3s ease;
}

.upload-btn:hover {
    background: var(--secondary-color);
}

.payment-status-section {
    background: rgba(255, 255, 255, 0.95);
    padding: 25px;
    border-radius: 10px;
    margin-top: 20px;
}

.payment-card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.term-status {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.status-indicator {
    display: flex;
    align-items: center;
    gap: 10px;
}

.status-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.status-dot.processed {
    background: #48bb78;
}

.payment-details {
    border-top: 1px solid #eee;
    padding-top: 15px;
    margin-top: 15px;
}

.primary-action {
    background: var(--primary-color);
    color: white;
}

.primary-action:hover {
    background: var(--secondary-color);
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
        
        /* Add to your existing CSS */
.scholarship-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 15px;
    justify-content: center;
}

.tag {
    background: rgba(67, 97, 238, 0.1);
    color: var(--primary-color);
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 0.8rem;
    transition: all 0.3s ease;
}

.tag:hover {
    background: var(--primary-color);
    color: white;
    transform: translateY(-2px);
}

/* Add hover effect for resource cards */
.resource-card {
    position: relative;
    overflow: hidden;
}

.resource-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
    transform: scaleX(0);
    transition: transform 0.3s ease;
    transform-origin: left;
}

.resource-card:hover::before {
    transform: scaleX(1);
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
    </style>
</head>
<body>
    <!-- Sidebar Overlay -->
<div class="sidebar-overlay"></div>
    <div class="container">
        <!-- Mobile Menu Toggle -->
        <div class="mobile-toggle">
            <i class="fas fa-bars"></i>
        </div>

        <!-- Header -->
        <!-- Header -->
<header class="header">
        
        <div class="header-right">
    <div class="notification-bell">
        <i class="fas fa-bell"></i>
        <span class="notification-badge">3</span>
    </div>
    <div class="user-profile">
        <div class="user-avatar">
            <?php if ($profile_picture): ?>
                <img src="<?php echo htmlspecialchars('/uploads/profile_pictures/' . $profile_picture); ?>" 
                     alt="Profile Picture"
                     class="profile-image"
                     onerror="this.onerror=null; this.src='/images/default-avatar.png'; this.classList.add('fallback-avatar');">
            <?php else: ?>
                <div class="avatar-initials">
                    <?php echo strtoupper(substr($first_name, 0, 1)); ?>
                </div>
            <?php endif; ?>
        </div>
        <span class="user-name"><?php echo htmlspecialchars($first_name); ?></span>
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

        <!-- Main Content -->
        <div class="main-content">
    <div class="dashboard-header">
        <h1 class="welcome-message">Welcome, Parent/Guardian of <?php echo htmlspecialchars($ward_name); ?>!</h1>
        <p>Track your ward's scholarship progress and academic requirements</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div>
                <h3>Current Class</h3>
                <div class="value"><?php echo htmlspecialchars($ward_class); ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div>
                <h3>Current Term</h3>
                <div class="value"><?php echo htmlspecialchars($current_term); ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <div>
                <h3>Last Result Upload</h3>
                <div class="value" style="font-size: 1.2rem;"><?php echo htmlspecialchars($last_result_upload); ?></div>
            </div>
        </div>
    </div>

    <!-- Quick Actions for Primary School -->
    <div class="quick-actions">
        <button class="quick-action-btn" onclick="window.location.href='upload-result.php'">
            <i class="fas fa-upload"></i>
            <div>Upload Term Result</div>
        </button>
        <button class="quick-action-btn" onclick="window.location.href='ward-profile.php'">
            <i class="fas fa-child"></i>
            <div>Update Ward's Info</div>
        </button>
        <button class="quick-action-btn" onclick="window.location.href='payment-history.php'">
            <i class="fas fa-receipt"></i>
            <div>Payment History</div>
        </button>
        <button class="quick-action-btn" onclick="window.location.href='support.php'">
            <i class="fas fa-headset"></i>
            <div>Get Support</div>
        </button>
    </div>

    <!-- Required Documents Section -->
    <div class="documents-section">
        <h2 class="section-title">Required Documents</h2>
        <div class="documents-grid">
            <div class="document-card <?php echo $documents_status['birth_certificate'] ? 'submitted' : ''; ?>">
                <i class="fas fa-certificate"></i>
                <h3>Birth Certificate</h3>
                <span class="status"><?php echo $documents_status['birth_certificate'] ? 'Submitted' : 'Pending'; ?></span>
                <?php if (!$documents_status['birth_certificate']): ?>
                    <button onclick="window.location.href='upload_document.php'" class="upload-btn">Upload</button>
                <?php endif; ?>
            </div>
            <div class="document-card <?php echo $documents_status['school_admission'] ? 'submitted' : ''; ?>">
                <i class="fas fa-school"></i>
                <h3>School Admission Letter</h3>
                <span class="status"><?php echo $documents_status['school_admission'] ? 'Submitted' : 'Pending'; ?></span>
                <?php if (!$documents_status['school_admission']): ?>
                    <button onclick="window.location.href='upload_document.php?type=school_admission'" class="upload-btn">Upload</button>
                <?php endif; ?>
            </div>

            <div class="document-card <?php echo $documents_status['guardian_id'] ? 'submitted' : ''; ?>">
                <i class="fas fa-id-card"></i>
                <h3>Guardian's ID</h3>
                <span class="status"><?php echo $documents_status['guardian_id'] ? 'Submitted' : 'Pending'; ?></span>
                <?php if (!$documents_status['guardian_id']): ?>
                    <button onclick="window.location.href='upload_document.php?type=guardian_id'" class="upload-btn">Upload</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Payment Status Section -->
    <div class="payment-status-section">
        <h2 class="section-title">School Fees Payment Status</h2>
        <div class="payment-card">
            <div class="term-status">
                <h3>Current Term: <?php echo htmlspecialchars($current_term); ?></h3>
                <div class="status-indicator">
                    <span class="status-dot processed"></span>
                    <span>Payment Processed</span>
                </div>
            </div>
            <div class="payment-details">
                <p>Next Payment Due: Beginning of Next Term</p>
                <p>Note: Please ensure to upload current term results for continuous scholarship support</p>
            </div>
        </div>
    </div>
</div>

           

        <!-- Footer -->
        <footer class="footer">
            <div class="footer-copyright">
                © 2024 Bright Future Initiatives. All rights reserved.
            </div>
            <div class="footer-links">
                <a href="/"><i class="fas fa-home me-2"></i>Home</a>
                        <a href="/about.html"><i class="fas fa-info-circle me-2"></i>About Us</a>
                        <a href="/our_programs.html"><i class="fas fa-graduation-cap me-2"></i>Programs</a>
            </div>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
    const mobileToggle = document.querySelector('.mobile-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    // Toggle sidebar
    function toggleSidebar() {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
    }

    // Event listeners
    mobileToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleSidebar();
    });

    // Close sidebar when clicking overlay
    overlay.addEventListener('click', toggleSidebar);

    // Close sidebar when clicking outside
    document.addEventListener('click', function(e) {
        if (!sidebar.contains(e.target) && 
            !mobileToggle.contains(e.target) && 
            sidebar.classList.contains('active')) {
            toggleSidebar();
        }
    });

    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 768 && sidebar.classList.contains('active')) {
                toggleSidebar();
            }
        }, 250);
    });

    // Prevent clicks within sidebar from closing it
    sidebar.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    // Handle notifications
    const notificationBell = document.querySelector('.notification-bell');
    if (notificationBell) {
        notificationBell.addEventListener('click', function(e) {
            e.stopPropagation();
            // Add your notification logic here
        });
    }
});
    </script>
</body>
</html>