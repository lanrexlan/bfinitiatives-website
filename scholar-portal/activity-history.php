<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Initialize variables
$user_data = [];
$activities = [];

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Fetch user data
    $stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Fetch activity history
    $stmt = $conn->prepare("
        SELECT 
            activity_type,
            description,
            created_at,
            status,
            related_entity
        FROM activity_log
        WHERE user_id = :user_id
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity History - Scholar Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
    
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
                        url('/Images/dashboard-bg.jpeg') center/cover fixed;
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
            margin-top: -20px;
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

        
        .activity-timeline {
            position: relative;
            padding: 20px;
        }

        .activity-item {
            position: relative;
            padding: 20px;
            margin-left: 30px;
            background: var(--card-bg);
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .activity-item::before {
            content: '';
            position: absolute;
            left: -30px;
            top: 0;
            bottom: -20px;
            width: 2px;
            background: var(--primary-color);
        }

        .activity-item::after {
            content: '';
            position: absolute;
            left: -36px;
            top: 20px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary-color);
            border: 2px solid white;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .activity-type {
            font-weight: 500;
            color: var(--primary-color);
        }

        .activity-date {
            font-size: 0.9rem;
            color: #666;
        }

        .activity-description {
            color: #444;
            margin-bottom: 10px;
        }

        .activity-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-error {
            background: #f8d7da;
            color: #721c24;
        }

        .no-activity {
            text-align: center;
            padding: 40px;
            background: var(--card-bg);
            border-radius: 10px;
            margin-top: 20px;
        }

        .filter-container {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 20px;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-btn.active {
            background: var(--primary-color);
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <header class="dashboard-header">
            <h1>Activity History</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($user_data['first_name'] ?? 'User'); ?></span>
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
                        Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a href="scholarship-status.php" class="nav-link">
                        <i class="fas fa-graduation-cap"></i>
                        Scholarship Status
                    </a>
                </li>
                <li class="nav-item">
                    <a href="upload-document.php" class="nav-link">
                        <i class="fas fa-file-alt"></i>
                        Documents
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
        
        <div class="content-wrapper">
            <div class="filter-container">
                <button class="filter-btn active" data-filter="all">All Activities</button>
                <button class="filter-btn" data-filter="application">Applications</button>
                <button class="filter-btn" data-filter="document">Documents</button>
                <button class="filter-btn" data-filter="profile">Profile Updates</button>
            </div>

            <?php if (empty($activities)): ?>
                <div class="no-activity">
                    <i class="fas fa-history fa-3x" style="color: var(--primary-color); margin-bottom: 15px;"></i>
                    <h2>No Activity Yet</h2>
                    <p>Your activity history will appear here once you start using the portal.</p>
                </div>
            <?php else: ?>
                <div class="activity-timeline">
                    <?php foreach ($activities as $activity): ?>
                        <div class="activity-item" data-type="<?php echo htmlspecialchars($activity['activity_type']); ?>">
                            <div class="activity-header">
                                <span class="activity-type">
                                    <?php echo htmlspecialchars(ucfirst($activity['activity_type'])); ?>
                                </span>
                                <span class="activity-date">
                                    <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                </span>
                            </div>
                            <div class="activity-description">
                                <?php echo htmlspecialchars($activity['description']); ?>
                            </div>
                            <span class="activity-status status-<?php echo strtolower($activity['status']); ?>">
                                <?php echo htmlspecialchars($activity['status']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Footer -->
        <footer class="footer">
            <div class="footer-copyright">
                © 2024 Bold Footprint Initiatives. All rights reserved.
            </div>
            <div class="footer-links">
                <a href="/"><i class="fas fa-home me-2"></i>Home</a>
                        <a href="/about-us"><i class="fas fa-info-circle me-2"></i>About Us</a>
                        <a href="/programs"><i class="fas fa-graduation-cap me-2"></i>Programs</a>
            </div>
        </footer>
    </div>

    
    <script>
        // Activity filtering
        document.querySelectorAll('.filter-btn').forEach(button => {
            button.addEventListener('click', function() {
                // Update active button
                document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');

                // Filter activities
                const filter = this.dataset.filter;
                document.querySelectorAll('.activity-item').forEach(item => {
                    if (filter === 'all' || item.dataset.type === filter) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>