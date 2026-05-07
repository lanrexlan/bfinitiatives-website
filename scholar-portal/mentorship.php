<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Initialize default values
$first_name = '';
$profile_picture = null;
$program = '';
$mentors = [];
$upcoming_sessions = [];
$past_sessions = [];

// Get user details and mentorship data
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get user details
    $stmt = $conn->prepare("
        SELECT first_name, profile_picture, program
        FROM users
        WHERE id = :user_id
    ");
    
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $first_name = $user['first_name'] ?? '';
        $profile_picture = $user['profile_picture'] ?? null;
        $program = $user['program'] ?? '';
    }
    
    // Get available mentors for the user's program
    $mentors_stmt = $conn->prepare("
        SELECT 
            m.id, 
            m.name, 
            m.title, 
            m.bio, 
            m.expertise, 
            m.profile_picture,
            m.availability,
            (SELECT AVG(rating) FROM mentor_sessions WHERE mentor_id = m.id AND rating IS NOT NULL) as avg_rating
        FROM mentors m
        WHERE m.program = :program OR m.program = 'All Programs'
        ORDER BY avg_rating DESC, m.name ASC
    ");
    
    $mentors_stmt->execute([':program' => $program]);
    $mentors = $mentors_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get upcoming mentorship sessions
    $upcoming_stmt = $conn->prepare("
        SELECT 
            s.id, 
            s.session_date, 
            s.topic, 
            s.status,
            m.name as mentor_name,
            m.profile_picture as mentor_picture
        FROM mentor_sessions s
        JOIN mentors m ON s.mentor_id = m.id
        WHERE s.user_id = :user_id
        AND s.session_date > NOW()
        ORDER BY s.session_date ASC
    ");
    
    $upcoming_stmt->execute([':user_id' => $_SESSION['user_id']]);
    $upcoming_sessions = $upcoming_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get past mentorship sessions
    $past_stmt = $conn->prepare("
        SELECT 
            s.id, 
            s.session_date, 
            s.topic, 
            s.notes,
            s.rating,
            s.feedback,
            m.name as mentor_name
        FROM mentor_sessions s
        JOIN mentors m ON s.mentor_id = m.id
        WHERE s.user_id = :user_id
        AND s.session_date < NOW()
        ORDER BY s.session_date DESC
        LIMIT 5
    ");
    
    $past_stmt->execute([':user_id' => $_SESSION['user_id']]);
    $past_sessions = $past_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Mentorship page error: " . $e->getMessage());
}

// Handle mentorship session booking if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_session'])) {
    $mentor_id = $_POST['mentor_id'] ?? 0;
    $session_date = $_POST['session_date'] ?? '';
    $topic = $_POST['topic'] ?? '';
    
    if ($mentor_id && $session_date && $topic) {
        try {
            // Insert new session
            $session_stmt = $conn->prepare("
                INSERT INTO mentor_sessions (user_id, mentor_id, session_date, topic, status, created_at)
                VALUES (:user_id, :mentor_id, :session_date, :topic, 'Scheduled', NOW())
            ");
            
            $session_stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':mentor_id' => $mentor_id,
                ':session_date' => $session_date,
                ':topic' => $topic
            ]);
            
            // Redirect to refresh the page and avoid form resubmission
            header('Location: mentorship.php?scheduled=1');
            exit();
        } catch (Exception $e) {
            error_log("Mentorship scheduling error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentorship - BFI Scholar Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Include the same base styles as dashboard.php */
        /* ... Copy base styles from dashboard.php ... */
        
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
            font-family: 'Poppins', system-ui, -apple-system, sans-serif;
            background-image: url('/Images/apply-scholar.jpeg');
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

        /* Sidebar Overlay */
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

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            position: fixed;
            left: 0;
            top: 0;
            margin: 0;
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
        
        /* Footer */
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
        
        /* Additional styles for mentorship page */
        .mentorship-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }
        
        @media (max-width: 992px) {
            .mentorship-container {
                grid-template-columns: 1fr;
            }
        }
        
        .mentors-list {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            padding: 25px;
        }
        
        .sessions-sidebar {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            padding: 25px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin: 0;
        }
        
        .mentor-card {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            gap: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .mentor-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .mentor-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .mentor-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .mentor-info {
            flex: 1;
        }
        
        .mentor-name {
            font-size: 1.3rem;
            margin-bottom: 5px;
            color: var(--text-color);
        }
        
        .mentor-title {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .mentor-bio {
            color: #555;
            margin-bottom: 15px;
            font-size: 0.95rem;
        }
        
        .mentor-expertise {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .expertise-tag {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .mentor-rating {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #f39c12;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .mentor-cta {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn-schedule {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-schedule:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .session-card {
            background: #f5f5f5;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 3px solid var(--primary-color);
        }
        
        .session-card.past {
            border-left-color: #999;
        }
        
        .session-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }
        
        .session-mentor-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
        }
        
        .session-mentor-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .session-info {
            flex: 1;
        }
        
        .session-mentor-name {
            font-weight: 500;
            margin-bottom: 0;
        }
        
        .session-date {
            font-size: 0.85rem;
            color: #666;
        }
        
        .session-topic {
            margin: 10px 0;
            font-size: 0.95rem;
        }
        
        .session-status {
            font-size: 0.8rem;
            padding: 3px 10px;
            border-radius: 12px;
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
            display: inline-block;
        }
        
        .session-status.confirmed {
            background: rgba(76, 175, 80, 0.1);
            color: #4caf50;
        }
        
        .session-status.completed {
            background: rgba(158, 158, 158, 0.1);
            color: #616161;
        }
        
        .session-status.cancelled {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
        }
        
        .past-session-details {
            border-top: 1px solid #eee;
            margin-top: 10px;
            padding-top: 10px;
        }
        
        .session-notes {
            font-size: 0.9rem;
            color: #555;
            margin-bottom: 10px;
        }
        
        .session-rating {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #f39c12;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }
        
        .session-feedback {
            font-size: 0.85rem;
            color: #666;
            font-style: italic;
        }
        
        .modal-body .form-group {
            margin-bottom: 20px;
        }
        
        .availability-list {
            list-style: none;
            padding: 0;
            margin: 0 0 20px 0;
            font-size: 0.9rem;
        }
        
        .availability-list li {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        
        .availability-list li:last-child {
            border-bottom: none;
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
    
                <!-- Sidebar - Scholar Portal -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Scholar Portal</h2>
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link">
                        <i class="fas fa-user"></i>
                        My Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a href="documents.php" class="nav-link">
                        <i class="fas fa-file-alt"></i>
                        My Documents
                    </a>
                </li>
                <li class="nav-item">
                    <a href="scholarships.php" class="nav-link">
                        <i class="fas fa-graduation-cap"></i>
                        Scholarship Opportunities
                    </a>
                </li>
                <li class="nav-item">
                    <a href="mentors.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        Connect with Mentors
                    </a>
                </li>
                <li class="nav-item">
                    <a href="settings.php" class="nav-link">
                        <i class="fas fa-cog"></i>
                        Settings
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
                <h1 class="welcome-message">Mentorship Program</h1>
                <p>Connect with experienced mentors who can guide you through your academic journey</p>
            </div>
            
            <?php if (isset($_GET['scheduled']) && $_GET['scheduled'] == 1): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Your mentorship session has been scheduled successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="mentorship-container">
                <div class="mentors-list">
                    <div class="section-header">
                        <h2 class="section-title">Available Mentors</h2>
                    </div>
                    
                    <?php if (count($mentors) > 0): ?>
                        <?php foreach ($mentors as $mentor): ?>
                            <div class="mentor-card">
                                <div class="mentor-avatar">
                                    <?php if (!empty($mentor['profile_picture'])): ?>
                                        <img src="<?php echo htmlspecialchars('/uploads/mentor_pictures/' . $mentor['profile_picture']); ?>" 
                                             alt="<?php echo htmlspecialchars($mentor['name']); ?>"
                                             onerror="this.src='/images/default-mentor.png';">
                                    <?php else: ?>
                                        <img src="/images/default-mentor.png" alt="Default mentor picture">
                                    <?php endif; ?>
                                </div>
                                <div class="mentor-info">
                                    <h3 class="mentor-name"><?php echo htmlspecialchars($mentor['name']); ?></h3>
                                    <div class="mentor-title"><?php echo htmlspecialchars($mentor['title']); ?></div>
                                    
                                    <?php if (!empty($mentor['avg_rating'])): ?>
                                        <div class="mentor-rating">
                                            <?php 
                                                $rating = round($mentor['avg_rating'] * 2) / 2; // Round to nearest 0.5
                                                for ($i = 1; $i <= 5; $i++): 
                                                    if ($i <= $rating):
                                                        echo '<i class="fas fa-star"></i>';
                                                    elseif ($i - 0.5 == $rating):
                                                        echo '<i class="fas fa-star-half-alt"></i>';
                                                    else:
                                                        echo '<i class="far fa-star"></i>';
                                                    endif;
                                                endfor;
                                            ?>
                                            <span><?php echo number_format($mentor['avg_rating'], 1); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mentor-bio"><?php echo htmlspecialchars($mentor['bio']); ?></div>
                                    
                                    <?php if (!empty($mentor['expertise'])): ?>
                                        <div class="mentor-expertise">
                                            <?php foreach (explode(',', $mentor['expertise']) as $expertise): ?>
                                                <span class="expertise-tag"><?php echo htmlspecialchars(trim($expertise)); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mentor-cta">
                                        <button class="btn btn-schedule" data-bs-toggle="modal" data-bs-target="#scheduleModal<?php echo $mentor['id']; ?>">
                                            <i class="fas fa-calendar-plus"></i> Schedule Session
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Schedule Modal for each mentor -->
                            <div class="modal fade" id="scheduleModal<?php echo $mentor['id']; ?>" tabindex="-1" aria-labelledby="scheduleModalLabel<?php echo $mentor['id']; ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="scheduleModalLabel<?php echo $mentor['id']; ?>">Schedule Session with <?php echo htmlspecialchars($mentor['name']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form method="post" action="">
                                                <input type="hidden" name="mentor_id" value="<?php echo $mentor['id']; ?>">
                                                
                                                <?php if (!empty($mentor['availability'])): ?>
                                                    <div class="form-group">
                                                        <label>Available Times:</label>
                                                        <ul class="availability-list">
                                                            <?php foreach (explode(',', $mentor['availability']) as $time): ?>
                                                                <li><?php echo htmlspecialchars(trim($time)); ?></li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="form-group">
                                                    <label for="session_date">Session Date and Time:</label>
                                                    <input type="datetime-local" class="form-control" id="session_date" name="session_date" required>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="topic">What would you like to discuss?</label>
                                                    <textarea class="form-control" id="topic" name="topic" rows="3" placeholder="Briefly describe what you'd like to discuss in this session" required></textarea>
                                                </div>
                                                
                                                <div class="d-grid gap-2">
                                                    <button type="submit" name="schedule_session" class="btn btn-primary">Request Session</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No mentors are currently available for your program. Please check back later.
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="sessions-sidebar">
                    <!-- Upcoming Sessions Section -->
                    <div class="upcoming-sessions">
                        <h3 class="section-title">Your Upcoming Sessions</h3>
                        
                        <?php if (count($upcoming_sessions) > 0): ?>
                            <?php foreach ($upcoming_sessions as $session): ?>
                                <?php 
                                    $session_date = new DateTime($session['session_date']);
                                    $status_class = strtolower($session['status']);
                                ?>
                                <div class="session-card">
                                    <div class="session-header">
                                        <div class="session-mentor-avatar">
                                            <?php if (!empty($session['mentor_picture'])): ?>
                                                <img src="<?php echo htmlspecialchars('/uploads/mentor_pictures/' . $session['mentor_picture']); ?>" 
                                                     alt="<?php echo htmlspecialchars($session['mentor_name']); ?>"
                                                     onerror="this.src='/images/default-mentor.png';">
                                            <?php else: ?>
                                                <img src="/images/default-mentor.png" alt="Default mentor picture">
                                            <?php endif; ?>
                                        </div>
                                        <div class="session-info">
                                            <h4 class="session-mentor-name"><?php echo htmlspecialchars($session['mentor_name']); ?></h4>
                                            <div class="session-date">
                                                <i class="far fa-calendar-alt"></i>
                                                <?php echo $session_date->format('F j, Y'); ?> at <?php echo $session_date->format('g:i A'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="session-topic">
                                        <strong>Topic:</strong> <?php echo htmlspecialchars($session['topic']); ?>
                                    </div>
                                    <span class="session-status <?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars($session['status']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>You don't have any upcoming mentorship sessions.</p>
                            <p>Schedule a session with one of our mentors to get started!</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Past Sessions Section -->
                    <?php if (count($past_sessions) > 0): ?>
                        <div class="past-sessions mt-4">
                            <h3 class="section-title">Past Sessions</h3>
                            
                            <?php foreach ($past_sessions as $session): ?>
                                <?php $session_date = new DateTime($session['session_date']); ?>
                                <div class="session-card past">
                                    <div class="session-header">
                                        <div class="session-info">
                                            <h4 class="session-mentor-name"><?php echo htmlspecialchars($session['mentor_name']); ?></h4>
                                            <div class="session-date">
                                                <i class="far fa-calendar-alt"></i>
                                                <?php echo $session_date->format('F j, Y'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="session-topic">
                                        <strong>Topic:</strong> <?php echo htmlspecialchars($session['topic']); ?>
                                    </div>
                                    
                                    <?php if (!empty($session['notes']) || !empty($session['rating'])): ?>
                                        <div class="past-session-details">
                                            <?php if (!empty($session['notes'])): ?>
                                                <div class="session-notes">
                                                    <strong>Notes:</strong> <?php echo htmlspecialchars($session['notes']); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($session['rating'])): ?>
                                                <div class="session-rating">
                                                    <?php 
                                                        for ($i = 1; $i <= 5; $i++): 
                                                            if ($i <= $session['rating']):
                                                                echo '<i class="fas fa-star"></i>';
                                                            else:
                                                                echo '<i class="far fa-star"></i>';
                                                            endif;
                                                        endfor;
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($session['feedback'])): ?>
                                                <div class="session-feedback">
                                                    "<?php echo htmlspecialchars($session['feedback']); ?>"
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer">
            <div class="footer-copyright">
                © 2024 Bold Footprint Initiatives. All rights reserved.
            </div>
            <div class="footer-links">
                <a href="/"><i class="fas fa-home me-2"></i>Home</a>
                <a href="/about.html"><i class="fas fa-info-circle me-2"></i>About Us</a>
                <a href="/programs.html"><i class="fas fa-graduation-cap me-2"></i>Programs</a>
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

            overlay.addEventListener('click', toggleSidebar);

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

            sidebar.addEventListener('click', function(e) {
                e.stopPropagation();
            });
            
            // Auto-dismiss alerts
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>