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
$program = '';
$profile_picture = null;
$upcoming_events = [];
$past_events = [];
$registered_events = [];

// Get user details and events
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
    
    // Get upcoming events
    $upcoming_stmt = $conn->prepare("
        SELECT e.id, e.title, e.description, e.event_date, e.location, e.type, 
               (SELECT COUNT(*) FROM event_registration WHERE event_id = e.id) as registration_count,
               (SELECT COUNT(*) FROM event_registration WHERE event_id = e.id AND user_id = :user_id) as is_registered
        FROM events e
        WHERE e.event_date > NOW()
        AND (e.program = :program OR e.program = 'All Programs')
        ORDER BY e.event_date ASC
        LIMIT 10
    ");
    
    $upcoming_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':program' => $program
    ]);
    $upcoming_events = $upcoming_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get past events
    $past_stmt = $conn->prepare("
        SELECT e.id, e.title, e.description, e.event_date, e.location, e.type,
               (SELECT COUNT(*) FROM event_attendance WHERE event_id = e.id AND user_id = :user_id) as attended
        FROM events e
        WHERE e.event_date < NOW()
        AND (e.program = :program OR e.program = 'All Programs')
        ORDER BY e.event_date DESC
        LIMIT 5
    ");
    
    $past_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':program' => $program
    ]);
    $past_events = $past_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get events user is registered for
    $registered_stmt = $conn->prepare("
        SELECT e.id, e.title, e.event_date, e.location, e.type
        FROM events e
        JOIN event_registration r ON e.id = r.event_id
        WHERE r.user_id = :user_id
        AND e.event_date > NOW()
        ORDER BY e.event_date ASC
    ");
    
    $registered_stmt->execute([':user_id' => $_SESSION['user_id']]);
    $registered_events = $registered_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Events page error: " . $e->getMessage());
}

// Handle event registration if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_event'])) {
    $event_id = $_POST['event_id'] ?? 0;
    
    if ($event_id) {
        try {
            // Check if already registered
            $check_stmt = $conn->prepare("
                SELECT id FROM event_registration 
                WHERE user_id = :user_id AND event_id = :event_id
            ");
            $check_stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':event_id' => $event_id
            ]);
            
            if (!$check_stmt->fetch()) {
                // Not registered yet, so register
                $reg_stmt = $conn->prepare("
                    INSERT INTO event_registration (user_id, event_id, registration_date)
                    VALUES (:user_id, :event_id, NOW())
                ");
                $reg_stmt->execute([
                    ':user_id' => $_SESSION['user_id'],
                    ':event_id' => $event_id
                ]);
                
                // Redirect to refresh the page and avoid form resubmission
                header('Location: events-workshops.php?registered=1');
                exit();
            }
        } catch (Exception $e) {
            error_log("Event registration error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events & Workshops - BFI Scholar Portal</title>
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
        
        /* Additional styles for events page */
        .events-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }
        
        @media (max-width: 992px) {
            .events-container {
                grid-template-columns: 1fr;
            }
        }
        
        .upcoming-events {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            padding: 25px;
        }
        
        .sidebar-content {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            padding: 25px;
        }
        
        .events-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .events-title {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin: 0;
        }
        
        .filter-dropdown {
            background: white;
            border: 1px solid #eee;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        .event-card {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 4px solid var(--primary-color);
        }
        
        .event-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .event-card.workshop {
            border-left-color: #4caf50;
        }
        
        .event-card.seminar {
            border-left-color: #ff9800;
        }
        
        .event-card.networking {
            border-left-color: #2196f3;
        }
        
        .event-date {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
        }
        
        .event-title {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: var(--text-color);
        }
        
        .event-details {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .event-location, .event-time {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: #555;
        }
        
        .event-description {
            color: #666;
            margin-bottom: 15px;
        }
        
        .event-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .event-type {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .event-type.workshop {
            background: rgba(76, 175, 80, 0.1);
            color: #4caf50;
        }
        
        .event-type.seminar {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
        }
        
        .event-type.networking {
            background: rgba(33, 150, 243, 0.1);
            color: #2196f3;
        }
        
        .btn-register {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .btn-register:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .btn-registered {
            background: #e0e0e0;
            color: #333;
            cursor: default;
        }
        
        .btn-registered:hover {
            background: #e0e0e0;
            transform: none;
        }
        
        .registered-events {
            margin-bottom: 30px;
        }
        
        .registered-event {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .event-date-badge {
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            color: var(--primary-color);
            font-weight: 500;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .date-day {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .registered-event-details {
            flex: 1;
        }
        
        .registered-event-title {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .past-events {
            margin-top: 30px;
        }
        
        .past-event {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .past-event:last-child {
            border-bottom: none;
        }
        
        .past-event-title {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .past-event-date {
            font-size: 0.8rem;
            color: #666;
        }
        
        .attended-badge {
            background: #4caf50;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            margin-left: 10px;
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
    
        <!-- Sidebar -->
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
                    <a href="scholarship-details.php" class="nav-link">
                        <i class="fas fa-graduation-cap"></i>
                        Scholarship Details
                    </a>
                </li>
                <li class="nav-item">
                    <a href="notifications.php" class="nav-link">
                        <i class="fas fa-bell"></i>
                        Notifications
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
                <h1 class="welcome-message">Events & Workshops</h1>
                <p>Discover upcoming opportunities and engage with the BFI community</p>
            </div>
            
            <?php if (isset($_GET['registered']) && $_GET['registered'] == 1): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    You have successfully registered for the event!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="events-container">
                <div class="upcoming-events">
                    <div class="events-header">
                        <h2 class="events-title">Upcoming Events</h2>
                        <select class="filter-dropdown" id="event-filter">
                            <option value="all">All Events</option>
                            <option value="workshop">Workshops</option>
                            <option value="seminar">Seminars</option>
                            <option value="networking">Networking</option>
                        </select>
                    </div>
                    
                    <?php if (count($upcoming_events) > 0): ?>
                        <?php foreach ($upcoming_events as $event): ?>
                            <?php 
                                $event_type_class = strtolower($event['type'] ?? 'default');
                                $event_date = new DateTime($event['event_date']);
                                $is_registered = ($event['is_registered'] > 0);
                            ?>
                            <div class="event-card <?php echo $event_type_class; ?>" data-type="<?php echo $event_type_class; ?>">
                                <div class="event-date">
                                    <?php echo $event_date->format('l, F j, Y - g:i A'); ?>
                                </div>
                                <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                                <div class="event-details">
                                    <div class="event-location">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($event['location']); ?>
                                    </div>
                                    <div class="event-time">
                                        <i class="far fa-clock"></i>
                                        <?php echo $event_date->format('g:i A'); ?>
                                    </div>
                                </div>
                                <div class="event-description">
                                    <?php echo htmlspecialchars($event['description']); ?>
                                </div>
                                <div class="event-meta">
                                    <span class="event-type <?php echo $event_type_class; ?>">
                                        <?php echo htmlspecialchars(ucfirst($event['type'] ?? 'Event')); ?>
                                    </span>
                                    
                                    <?php if ($is_registered): ?>
                                        <button class="btn btn-register btn-registered" disabled>Registered</button>
                                    <?php else: ?>
                                        <form method="post" action="">
                                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                            <button type="submit" name="register_event" class="btn btn-register">Register Now</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No upcoming events are currently scheduled. Check back soon for new events!
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="sidebar-content">
                    <!-- Your Registered Events Section -->
                    <div class="registered-events">
                        <h3 class="events-title">Your Registered Events</h3>
                        
                        <?php if (count($registered_events) > 0): ?>
                            <?php foreach ($registered_events as $event): ?>
                                <?php $event_date = new DateTime($event['event_date']); ?>
                                <div class="registered-event">
                                    <div class="event-date-badge">
                                        <span class="date-month"><?php echo $event_date->format('M'); ?></span>
                                        <span class="date-day"><?php echo $event_date->format('d'); ?></span>
                                    </div>
                                    <div class="registered-event-details">
                                        <div class="registered-event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                                        <div class="registered-event-info">
                                            <small>
                                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>You haven't registered for any upcoming events yet.</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Past Events Section -->
                    <div class="past-events">
                        <h3 class="events-title">Past Events</h3>
                        
                        <?php if (count($past_events) > 0): ?>
                            <?php foreach ($past_events as $event): ?>
                                <?php 
                                    $event_date = new DateTime($event['event_date']);
                                    $attended = ($event['attended'] > 0);
                                ?>
                                <div class="past-event">
                                    <div class="past-event-title">
                                        <?php echo htmlspecialchars($event['title']); ?>
                                        <?php if ($attended): ?>
                                            <span class="attended-badge">Attended</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="past-event-date">
                                        <i class="far fa-calendar-alt"></i>
                                        <?php echo $event_date->format('M d, Y'); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No past events to display.</p>
                        <?php endif; ?>
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

            overlay.addEventListener('click', toggleSidebar);

            document.addEventListener('click', function(e) {
                if (!sidebar.contains(e.target) && 
                    !mobileToggle.contains(e.target) && 
                    sidebar.classList.contains('active')) {
                    toggleSidebar();
                }
            });
            
            // Handle event filtering
            const eventFilter = document.getElementById('event-filter');
            const eventCards = document.querySelectorAll('.event-card');
            
            eventFilter.addEventListener('change', function() {
                const selectedType = this.value;
                
                eventCards.forEach(card => {
                    if (selectedType === 'all' || card.dataset.type === selectedType) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
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