<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$first_name = '';
$last_name = '';
$email = '';
$profile_picture = null;
$notification_count = 0;

try {
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->prepare("
        SELECT 
            first_name, 
            last_name,
            email, 
            profile_picture
        FROM users 
        WHERE id = :user_id
    ");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $first_name = $user['first_name'];
        $last_name = $user['last_name'] ?? '';
        $email = $user['email'] ?? '';
        $profile_picture = $user['profile_picture'] ?? null;
    }
    $notification_stmt = $conn->prepare("
        SELECT id FROM notifications 
        WHERE user_id = :user_id AND read_status = 0
    ");
    $notification_stmt->execute([':user_id' => $_SESSION['user_id']]);
    $notification_count = $notification_stmt->rowCount();
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Dashboard | BFI Scholar Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{
      --midnight:#080E1C;--navy:#0D1829;--navy-mid:#132038;--navy-light:#1C2F52;
      --gold:#C8A058;--gold-bright:#E0B96C;--gold-pale:#F0D9A8;
      --cream:#FAF6EF;--cream-dark:#F2EAD8;--white:#FFFFFF;
      --text-primary:#0D1829;--text-secondary:#4A526A;--text-muted:#8A92A8;
      --border-light:#E8E4DA;
      --font-display:'Cormorant Garamond',Georgia,serif;
      --font-body:'Outfit',-apple-system,sans-serif;
      --ease:cubic-bezier(0.25,0.46,0.45,0.94);
      --transition:0.3s var(--ease);
      --shadow-sm:0 2px 8px rgba(8,14,28,0.06);
      --shadow-md:0 8px 32px rgba(8,14,28,0.10);
      --shadow-lg:0 20px 60px rgba(8,14,28,0.14);
      --sidebar-width:268px;--header-height:64px;
      --r-sm:8px;--r-md:14px;--r-lg:20px;--r-xl:28px;
    }
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    html{font-size:16px;}
    body{font-family:var(--font-body);background:#F2F4F8;color:var(--text-primary);line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden;}
    a{text-decoration:none;color:inherit;}
    img{max-width:100%;display:block;}

    /* SIDEBAR */
    .sidebar{position:fixed;left:0;top:0;width:var(--sidebar-width);height:100vh;background:var(--navy);z-index:200;display:flex;flex-direction:column;overflow:hidden;transition:transform var(--transition);}
    .sidebar-top{padding:28px 24px 20px;border-bottom:1px solid rgba(255,255,255,0.06);}
    .sidebar-logo{display:flex;align-items:center;gap:12px;margin-bottom:32px;}
    .sidebar-logomark{width:34px;height:34px;background:var(--gold);border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .sidebar-logomark svg{width:20px;height:20px;}
    .sidebar-logo-text{font-family:var(--font-display);font-size:15px;font-weight:500;color:var(--white);line-height:1.2;}
    .sidebar-logo-text span{display:block;font-family:var(--font-body);font-size:9px;letter-spacing:2px;text-transform:uppercase;color:rgba(200,160,88,0.6);}
    .sidebar-user{display:flex;align-items:center;gap:12px;}
    .sidebar-avatar{width:40px;height:40px;border-radius:50%;background:var(--navy-light);border:2px solid rgba(200,160,88,0.3);overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .sidebar-avatar img{width:100%;height:100%;object-fit:cover;}
    .sidebar-avatar-init{font-family:var(--font-display);font-size:16px;font-weight:500;color:var(--gold-bright);}
    .sidebar-user-name{font-size:13.5px;font-weight:500;color:var(--white);line-height:1.3;}
    .sidebar-user-role{font-size:10.5px;color:rgba(255,255,255,0.35);letter-spacing:0.5px;}
    .sidebar-nav{flex:1;padding:20px 12px;overflow-y:auto;}
    .nav-section-label{font-size:9.5px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,0.2);padding:0 12px;margin:16px 0 8px;}
    .nav-item{margin-bottom:2px;}
    .nav-link{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:var(--r-sm);font-size:13.5px;font-weight:400;color:rgba(255,255,255,0.6);transition:var(--transition);position:relative;}
    .nav-link:hover{background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.9);}
    .nav-link.active{background:rgba(200,160,88,0.12);color:var(--gold-bright);}
    .nav-link.active::before{content:'';position:absolute;left:0;top:6px;bottom:6px;width:2.5px;background:var(--gold);border-radius:2px;}
    .nav-link i{width:18px;text-align:center;font-size:14px;flex-shrink:0;}
    .nav-badge{margin-left:auto;background:rgba(239,68,68,0.2);color:#F87171;font-size:10px;font-weight:600;padding:1px 7px;border-radius:10px;}
    .sidebar-bottom{padding:16px 12px;border-top:1px solid rgba(255,255,255,0.06);}

    /* HEADER */
    .header{position:fixed;top:0;left:var(--sidebar-width);right:0;height:var(--header-height);background:var(--white);border-bottom:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;padding:0 28px;z-index:100;}
    .header-left{display:flex;align-items:center;gap:16px;}
    .mobile-toggle{display:none;background:none;border:none;cursor:pointer;padding:6px;color:var(--text-secondary);}
    .header-greeting{font-size:13.5px;color:var(--text-muted);}
    .header-greeting strong{color:var(--text-primary);font-weight:600;}
    .header-right{display:flex;align-items:center;gap:16px;}
    .header-icon-btn{width:36px;height:36px;border-radius:var(--r-sm);background:var(--cream);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-secondary);font-size:15px;transition:var(--transition);position:relative;}
    .header-icon-btn:hover{background:var(--cream-dark);color:var(--navy);}
    .notif-dot{position:absolute;top:6px;right:6px;width:8px;height:8px;border-radius:50%;background:#EF4444;border:2px solid var(--white);}
    .header-avatar{width:36px;height:36px;border-radius:50%;background:var(--navy-light);overflow:hidden;border:2px solid var(--border-light);cursor:pointer;display:flex;align-items:center;justify-content:center;}
    .header-avatar img{width:100%;height:100%;object-fit:cover;}
    .header-avatar-init{font-family:var(--font-display);font-size:14px;color:var(--gold-bright);}

    /* MAIN */
    .main{margin-left:var(--sidebar-width);margin-top:var(--header-height);padding:28px;min-height:calc(100vh - var(--header-height));}

    /* WELCOME BANNER */
    .welcome-banner{background:var(--navy);border-radius:var(--r-xl);padding:32px 36px;margin-bottom:24px;position:relative;overflow:hidden;}
    .welcome-banner::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:32px 32px;}
    .welcome-banner::after{content:'';position:absolute;top:-60px;right:-60px;width:280px;height:280px;background:radial-gradient(circle,rgba(200,160,88,0.1) 0%,transparent 65%);}
    .welcome-inner{position:relative;z-index:1;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;}
    .welcome-text-eyebrow{font-size:10px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:rgba(200,160,88,0.7);margin-bottom:8px;}
    .welcome-title{font-family:var(--font-display);font-size:clamp(24px,3vw,32px);font-weight:500;color:var(--white);line-height:1.15;margin-bottom:6px;}
    .welcome-title em{font-style:italic;color:var(--gold-bright);}
    .welcome-sub{font-size:13.5px;font-weight:300;color:rgba(255,255,255,0.5);}
    .welcome-cta{display:flex;gap:10px;flex-wrap:wrap;}
    .btn-gold{display:inline-flex;align-items:center;gap:8px;padding:11px 22px;background:var(--gold);color:var(--midnight);font-family:var(--font-body);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-gold:hover{background:var(--gold-bright);transform:translateY(-1px);}
    .btn-ghost-sm{display:inline-flex;align-items:center;gap:8px;padding:11px 22px;background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.75);font-family:var(--font-body);font-size:13px;font-weight:400;border:1px solid rgba(255,255,255,0.1);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-ghost-sm:hover{background:rgba(255,255,255,0.1);color:var(--white);}

    /* JOURNEY TRACKER — DISTINCTIVE FEATURE */
    .section-label{font-size:10px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);margin-bottom:14px;}
    .journey-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:24px;margin-bottom:24px;}
    .journey-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;}
    .journey-title{font-family:var(--font-display);font-size:20px;font-weight:500;color:var(--navy);}
    .journey-title em{font-style:italic;color:var(--gold);}
    .journey-progress-label{font-size:12px;color:var(--text-muted);}
    .journey-progress-label strong{color:var(--gold);font-weight:600;}
    .journey-track{display:flex;align-items:center;gap:0;position:relative;padding:8px 0 24px;}
    .jt-step{display:flex;flex-direction:column;align-items:center;flex:1;position:relative;}
    .jt-step:not(:last-child)::after{content:'';position:absolute;top:18px;left:50%;width:100%;height:2px;background:var(--border-light);z-index:0;}
    .jt-step.done:not(:last-child)::after,.jt-step.active:not(:last-child)::after{background:linear-gradient(90deg,var(--gold),var(--border-light));}
    .jt-step.done:not(:last-child)::after{background:var(--gold);}
    .jt-dot{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;z-index:1;transition:var(--transition);}
    .jt-dot.done{background:var(--gold);color:var(--midnight);}
    .jt-dot.active{background:var(--navy);color:var(--white);box-shadow:0 0 0 4px rgba(200,160,88,0.2);}
    .jt-dot.future{background:var(--cream);color:var(--text-muted);border:1px solid var(--border-light);}
    .jt-label{font-size:11px;font-weight:500;color:var(--text-secondary);text-align:center;margin-top:10px;max-width:80px;}
    .jt-step.done .jt-label{color:var(--gold);}
    .jt-step.active .jt-label{color:var(--navy);font-weight:600;}
    .jt-step.future .jt-label{color:var(--text-muted);}
    .journey-current{background:var(--cream);border-radius:var(--r-md);padding:16px 20px;display:flex;align-items:center;gap:16px;}
    .jc-icon{width:40px;height:40px;background:var(--navy);border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:16px;color:var(--gold-bright);flex-shrink:0;}
    .jc-title{font-size:14px;font-weight:500;color:var(--navy);margin-bottom:4px;}
    .jc-sub{font-size:12.5px;color:var(--text-muted);}
    .jc-progress{flex:1;max-width:160px;margin-left:auto;}
    .jc-prog-label{font-size:11px;color:var(--text-muted);display:flex;justify-content:space-between;margin-bottom:6px;}
    .jc-prog-bar{height:4px;background:var(--border-light);border-radius:2px;overflow:hidden;}
    .jc-prog-fill{height:100%;background:linear-gradient(90deg,var(--gold),var(--gold-bright));border-radius:2px;}

    /* QUICK ACTIONS */
    .quick-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
    .quick-card{background:var(--white);border-radius:var(--r-lg);padding:20px;border:1px solid var(--border-light);text-align:center;cursor:pointer;transition:var(--transition);}
    .quick-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-md);border-color:transparent;}
    .quick-icon{width:48px;height:48px;border-radius:var(--r-sm);background:var(--cream);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:18px;color:var(--navy);transition:var(--transition);}
    .quick-card:hover .quick-icon{background:var(--navy);color:var(--gold-bright);}
    .quick-title{font-size:13px;font-weight:500;color:var(--navy);margin-bottom:3px;}
    .quick-desc{font-size:11.5px;color:var(--text-muted);}

    /* TWO-COL GRID */
    .two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;}
    .card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);overflow:hidden;}
    .card-header{padding:20px 24px 0;display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;}
    .card-title{font-family:var(--font-display);font-size:18px;font-weight:500;color:var(--navy);}
    .card-title em{font-style:italic;color:var(--gold);}
    .card-link{font-size:12px;color:var(--gold);font-weight:500;display:flex;align-items:center;gap:5px;transition:var(--transition);}
    .card-link:hover{gap:8px;}
    .card-body{padding:0 24px 24px;}

    /* CHECKLIST */
    .checklist-item{display:flex;align-items:center;gap:14px;padding:13px 0;border-bottom:1px solid var(--border-light);}
    .checklist-item:last-child{border-bottom:none;}
    .check-dot{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;}
    .check-done{background:rgba(52,211,153,0.1);color:#10B981;}
    .check-pending{background:rgba(245,158,11,0.1);color:#F59E0B;}
    .check-todo{background:var(--cream);color:var(--text-muted);border:1px solid var(--border-light);}
    .check-text{flex:1;}
    .check-title{font-size:13.5px;font-weight:500;color:var(--navy);}
    .check-sub{font-size:11.5px;color:var(--text-muted);margin-top:1px;}
    .check-btn{font-size:11.5px;font-weight:500;color:var(--gold);background:rgba(200,160,88,0.08);border:1px solid rgba(200,160,88,0.2);padding:4px 12px;border-radius:20px;white-space:nowrap;transition:var(--transition);}
    .check-btn:hover{background:var(--gold);color:var(--midnight);}

    /* STORY CARDS */
    .story-item{padding:14px 0;border-bottom:1px solid var(--border-light);}
    .story-item:last-child{border-bottom:none;}
    .story-item-header{display:flex;align-items:center;gap:12px;margin-bottom:8px;}
    .story-thumb{width:40px;height:40px;border-radius:50%;overflow:hidden;flex-shrink:0;background:var(--cream);}
    .story-thumb img{width:100%;height:100%;object-fit:cover;}
    .story-name{font-size:13.5px;font-weight:500;color:var(--navy);}
    .story-school{font-size:11.5px;color:var(--text-muted);}
    .story-quote{font-family:var(--font-display);font-size:15px;font-style:italic;color:var(--text-secondary);line-height:1.55;padding-left:12px;border-left:2px solid var(--gold);margin-bottom:10px;}
    .story-read{font-size:12px;color:var(--gold);display:flex;align-items:center;gap:5px;transition:var(--transition);}
    .story-read:hover{gap:8px;}

    /* SCHOLARSHIP CARDS */
    .scholarship-item{padding:14px 0;border-bottom:1px solid var(--border-light);}
    .scholarship-item:last-child{border-bottom:none;}
    .schol-top{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:6px;}
    .schol-title{font-size:14px;font-weight:500;color:var(--navy);line-height:1.35;}
    .schol-badge{font-size:10px;font-weight:600;padding:2px 9px;border-radius:20px;white-space:nowrap;flex-shrink:0;}
    .badge-full{background:rgba(52,211,153,0.1);color:#059669;}
    .schol-uni{font-size:12px;color:var(--text-muted);margin-bottom:8px;}
    .schol-tags{display:flex;gap:5px;flex-wrap:wrap;}
    .schol-tag{font-size:10.5px;padding:2px 9px;border-radius:20px;background:var(--cream);color:var(--text-secondary);border:1px solid var(--border-light);}

    /* FOOTER */
    .portal-footer{margin-left:var(--sidebar-width);background:var(--white);padding:16px 28px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;}
    .footer-copy{font-size:12px;color:var(--text-muted);}
    .footer-links{display:flex;gap:20px;}
    .footer-links a{font-size:12px;color:var(--text-muted);transition:color var(--transition);}.footer-links a:hover{color:var(--gold);}

    /* SIDEBAR OVERLAY */
    .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:150;backdrop-filter:blur(4px);}
    .sidebar-overlay.active{display:block;}

    @media(max-width:1100px){.quick-grid{grid-template-columns:repeat(2,1fr)}.two-col{grid-template-columns:1fr}}
    @media(max-width:768px){
      .sidebar{transform:translateX(-100%);}
      .sidebar.active{transform:translateX(0);}
      .header{left:0;}
      .main,.portal-footer{margin-left:0;}
      .mobile-toggle{display:flex;}
      .header-greeting{display:none;}
      .quick-grid{grid-template-columns:repeat(2,1fr);}
      .journey-track{overflow-x:auto;padding-bottom:8px;}
    }
    @media(max-width:480px){.quick-grid{grid-template-columns:1fr}.welcome-cta{flex-direction:column}}
  </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-top">
    <div class="sidebar-logo">
      <div class="sidebar-logomark"><svg viewBox="0 0 22 22" fill="none"><path d="M4 18L11 4L18 18" stroke="#080E1C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 13H15" stroke="#080E1C" stroke-width="1.8" stroke-linecap="round"/></svg></div>
      <div class="sidebar-logo-text">Bold Footprint<span>Scholar Portal</span></div>
    </div>
    <div class="sidebar-user">
      <div class="sidebar-avatar">
        <?php if ($profile_picture): ?>
          <img src="<?php echo htmlspecialchars('./uploads/profile_pictures/' . $profile_picture); ?>" alt="Profile" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
          <div class="sidebar-avatar-init" style="display:none;"><?php echo strtoupper(substr($first_name,0,1)); ?></div>
        <?php else: ?>
          <div class="sidebar-avatar-init"><?php echo strtoupper(substr($first_name,0,1)); ?></div>
        <?php endif; ?>
      </div>
      <div>
        <div class="sidebar-user-name"><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></div>
        <div class="sidebar-user-role">BFI Scholar</div>
      </div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>
    <div class="nav-item"><a href="dashboard.php" class="nav-link active"><i class="fas fa-home"></i> Dashboard</a></div>
    <div class="nav-item"><a href="profile.php" class="nav-link"><i class="fas fa-user"></i> My Profile</a></div>
    <div class="nav-item">
      <a href="#" class="nav-link"><i class="fas fa-route"></i> My Journey
        <?php if ($notification_count > 0): ?><span class="nav-badge"><?php echo $notification_count; ?></span><?php endif; ?>
      </a>
    </div>
    <div class="nav-section-label">Resources</div>
    <div class="nav-item"><a href="documents.php" class="nav-link"><i class="fas fa-file-alt"></i> My Documents</a></div>
    <div class="nav-item"><a href="scholarships.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Scholarships</a></div>
    <div class="nav-item"><a href="mentors.php" class="nav-link"><i class="fas fa-users"></i> My Mentor</a></div>
    <div class="nav-item"><a href="application-help.php" class="nav-link"><i class="fas fa-question-circle"></i> Application Help</a></div>
    <div class="nav-item"><a href="resources.php" class="nav-link"><i class="fas fa-book"></i> Resources</a></div>
    <div class="nav-section-label">Account</div>
    <div class="nav-item"><a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a></div>
  </nav>

  <div class="sidebar-bottom">
    <a href="logout.php" class="nav-link" style="color:rgba(239,68,68,0.7);">
      <i class="fas fa-sign-out-alt"></i> Log Out
    </a>
  </div>
</aside>

<!-- HEADER -->
<header class="header">
  <div class="header-left">
    <button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
    <div class="header-greeting">Good day, <strong><?php echo htmlspecialchars($first_name); ?></strong> 👋</div>
  </div>
  <div class="header-right">
    <a href="view-agreement.php">
      <button class="header-icon-btn" title="Scholarship Agreement"><i class="fas fa-file-contract"></i></button>
    </a>
    <button class="header-icon-btn" title="Notifications">
      <i class="fas fa-bell"></i>
      <?php if ($notification_count > 0): ?><div class="notif-dot"></div><?php endif; ?>
    </button>
    <a href="profile.php">
      <div class="header-avatar">
        <?php if ($profile_picture): ?>
          <img src="<?php echo htmlspecialchars('./uploads/profile_pictures/' . $profile_picture); ?>" alt="Profile" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
          <div class="header-avatar-init" style="display:none;"><?php echo strtoupper(substr($first_name,0,1)); ?></div>
        <?php else: ?>
          <div class="header-avatar-init"><?php echo strtoupper(substr($first_name,0,1)); ?></div>
        <?php endif; ?>
      </div>
    </a>
  </div>
</header>

<!-- MAIN -->
<main class="main">

  <!-- WELCOME BANNER -->
  <div class="welcome-banner">
    <div class="welcome-inner">
      <div>
        <div class="welcome-text-eyebrow">Scholar Dashboard</div>
        <div class="welcome-title">Welcome back, <em><?php echo htmlspecialchars($first_name); ?>.</em></div>
        <div class="welcome-sub">Your journey to international education continues. Let's keep moving forward.</div>
      </div>
      <div class="welcome-cta">
        <a href="view-agreement.php" class="btn-gold"><i class="fas fa-file-contract"></i> View Agreement</a>
        <a href="scholarships.php" class="btn-ghost-sm"><i class="fas fa-graduation-cap"></i> Find Scholarships</a>
      </div>
    </div>
  </div>

  <!-- JOURNEY TRACKER — DISTINCTIVE FEATURE -->
  <div class="journey-card">
    <div class="journey-header">
      <div class="card-title">My <em>Journey</em> Tracker</div>
      <div class="journey-progress-label"><strong>Step 4</strong> of 5 — Application Preparation</div>
    </div>
    <div class="journey-track">
      <div class="jt-step done">
        <div class="jt-dot done"><i class="fas fa-check"></i></div>
        <div class="jt-label">Scholarship Awarded</div>
      </div>
      <div class="jt-step done">
        <div class="jt-dot done"><i class="fas fa-check"></i></div>
        <div class="jt-label">Documents Submitted</div>
      </div>
      <div class="jt-step done">
        <div class="jt-dot done"><i class="fas fa-check"></i></div>
        <div class="jt-label">Mentor Assigned</div>
      </div>
      <div class="jt-step active">
        <div class="jt-dot active"><i class="fas fa-pen"></i></div>
        <div class="jt-label">App. Preparation</div>
      </div>
      <div class="jt-step future">
        <div class="jt-dot future"><i class="fas fa-flag"></i></div>
        <div class="jt-label">Placement</div>
      </div>
    </div>
    <div class="journey-current">
      <div class="jc-icon"><i class="fas fa-pen-nib"></i></div>
      <div>
        <div class="jc-title">Application Preparation — In Progress</div>
        <div class="jc-sub">Statement of Purpose · 2 of 3 drafts reviewed by mentor</div>
      </div>
      <div class="jc-progress">
        <div class="jc-prog-label"><span>Progress</span><span>65%</span></div>
        <div class="jc-prog-bar"><div class="jc-prog-fill" style="width:65%;"></div></div>
      </div>
    </div>
  </div>

  <!-- QUICK ACTIONS -->
  <div class="section-label">Quick Actions</div>
  <div class="quick-grid">
    <div class="quick-card" onclick="window.location.href='view-agreement.php'">
      <div class="quick-icon"><i class="fas fa-file-contract"></i></div>
      <div class="quick-title">Scholarship Agreement</div>
      <div class="quick-desc">View your terms & conditions</div>
    </div>
    <div class="quick-card" onclick="window.location.href='scholarships.php'">
      <div class="quick-icon"><i class="fas fa-graduation-cap"></i></div>
      <div class="quick-title">Find Scholarships</div>
      <div class="quick-desc">Browse opportunities</div>
    </div>
    <div class="quick-card" onclick="window.location.href='application-help.php'">
      <div class="quick-icon"><i class="fas fa-file-alt"></i></div>
      <div class="quick-title">Application Materials</div>
      <div class="quick-desc">Prepare your documents</div>
    </div>
    <div class="quick-card" onclick="window.location.href='resources.php'">
      <div class="quick-icon"><i class="fas fa-book"></i></div>
      <div class="quick-title">Resource Library</div>
      <div class="quick-desc">Guides & templates</div>
    </div>
  </div>

  <!-- TWO-COL: CHECKLIST + STORIES -->
  <div class="two-col">

    <!-- APPLICATION CHECKLIST -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Application <em>Checklist</em></div>
        <a href="application-help.php" class="card-link">View all <i class="fas fa-arrow-right" style="font-size:10px;"></i></a>
      </div>
      <div class="card-body">
        <div class="checklist-item">
          <div class="check-dot <?php echo (isset($user['has_cv']) && $user['has_cv']) ? 'check-done' : 'check-pending'; ?>">
            <i class="fas <?php echo (isset($user['has_cv']) && $user['has_cv']) ? 'fa-check' : 'fa-hourglass-half'; ?>"></i>
          </div>
          <div class="check-text"><div class="check-title">CV / Resume</div><div class="check-sub">Highlight academic achievements</div></div>
          <a href="application-help.php?doc=cv" class="check-btn">Start</a>
        </div>
        <div class="checklist-item">
          <div class="check-dot <?php echo (isset($user['has_statement']) && $user['has_statement']) ? 'check-done' : 'check-todo'; ?>">
            <i class="fas <?php echo (isset($user['has_statement']) && $user['has_statement']) ? 'fa-check' : 'fa-circle'; ?>"></i>
          </div>
          <div class="check-text"><div class="check-title">Personal Statement</div><div class="check-sub">Your story and goals</div></div>
          <a href="application-help.php?doc=statement" class="check-btn">Guide</a>
        </div>
        <div class="checklist-item">
          <div class="check-dot <?php echo (isset($user['has_research']) && $user['has_research']) ? 'check-done' : 'check-todo'; ?>">
            <i class="fas <?php echo (isset($user['has_research']) && $user['has_research']) ? 'fa-check' : 'fa-circle'; ?>"></i>
          </div>
          <div class="check-text"><div class="check-title">Research Proposal</div><div class="check-sub">Outline research interests</div></div>
          <a href="application-help.php?doc=research" class="check-btn">Template</a>
        </div>
        <div class="checklist-item">
          <div class="check-dot <?php echo (isset($user['recommendation_count']) && $user['recommendation_count'] > 0) ? 'check-done' : 'check-todo'; ?>">
            <i class="fas <?php echo (isset($user['recommendation_count']) && $user['recommendation_count'] > 0) ? 'fa-check' : 'fa-circle'; ?>"></i>
          </div>
          <div class="check-text"><div class="check-title">Recommendation Letters</div><div class="check-sub">Secure academic references</div></div>
          <a href="application-help.php?doc=recommendations" class="check-btn">Tips</a>
        </div>
        <div class="checklist-item">
          <div class="check-dot check-todo"><i class="fas fa-circle"></i></div>
          <div class="check-text"><div class="check-title">English Proficiency</div><div class="check-sub">IELTS / TOEFL preparation</div></div>
          <a href="application-help.php?doc=language" class="check-btn">Resources</a>
        </div>
      </div>
    </div>

    <!-- SUCCESS STORIES -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Scholar <em>Stories</em></div>
        <a href="success-stories.php" class="card-link">View all <i class="fas fa-arrow-right" style="font-size:10px;"></i></a>
      </div>
      <div class="card-body">
        <div class="story-item">
          <div class="story-item-header">
            <div class="story-thumb"><img src="/Images/opeyemi.jpg" alt="Opeyemi" onerror="this.parentElement.style.background='var(--navy-light)';this.style.display='none';"></div>
            <div><div class="story-name">Opeyemi Owolabi</div><div class="story-school">University of Florida · PhD Aerospace Engineering</div></div>
          </div>
          <div class="story-quote">BFI's guidance on personal statements and interview prep was crucial to my PhD success.</div>
          <a href="success-stories.php?story=opeyemi-owolabi" class="story-read">Read full story <i class="fas fa-arrow-right" style="font-size:10px;"></i></a>
        </div>
        <div class="story-item">
          <div class="story-item-header">
            <div class="story-thumb"><img src="/Images/babatunde.jpg" alt="Babatunde" onerror="this.parentElement.style.background='var(--navy-light)';this.style.display='none';"></div>
            <div><div class="story-name">Babatunde Adedeji</div><div class="story-school">Tulane University · PhD Chemical Engineering</div></div>
          </div>
          <div class="story-quote">The mentors poured themselves out with compassion to help and see the mentees' breakthrough!</div>
          <a href="success-stories.php?story=babatunde-adedeji" class="story-read">Read full story <i class="fas fa-arrow-right" style="font-size:10px;"></i></a>
        </div>
      </div>
    </div>
  </div>

  <!-- FEATURED SCHOLARSHIPS -->
  <div class="section-label">Featured Scholarships</div>
  <div class="two-col" style="margin-bottom:0;">
    <div class="card">
      <div class="card-body" style="padding-top:20px;">
        <div class="scholarship-item">
          <div class="schol-top"><div class="schol-title">Fulbright Foreign Student Program</div><span class="schol-badge badge-full">Full Funding</span></div>
          <div class="schol-uni">United States — Various Universities</div>
          <div class="schol-tags"><span class="schol-tag">All Fields</span><span class="schol-tag">Masters</span><span class="schol-tag">PhD</span><span class="schol-tag">USA</span></div>
        </div>
        <div class="scholarship-item">
          <div class="schol-top"><div class="schol-title">Commonwealth Scholarship</div><span class="schol-badge badge-full">Full Funding</span></div>
          <div class="schol-uni">United Kingdom — Various Universities</div>
          <div class="schol-tags"><span class="schol-tag">Development</span><span class="schol-tag">Masters</span><span class="schol-tag">PhD</span><span class="schol-tag">UK</span></div>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card-body" style="padding-top:20px;">
        <div class="scholarship-item">
          <div class="schol-top"><div class="schol-title">DAAD Scholarships</div><span class="schol-badge" style="background:rgba(245,158,11,0.1);color:#D97706;">Partial–Full</span></div>
          <div class="schol-uni">Germany — Various Universities</div>
          <div class="schol-tags"><span class="schol-tag">All Fields</span><span class="schol-tag">Masters</span><span class="schol-tag">PhD</span><span class="schol-tag">Germany</span></div>
        </div>
        <div class="scholarship-item">
          <div class="schol-top"><div class="schol-title">Australia Awards Scholarships</div><span class="schol-badge badge-full">Full Funding</span></div>
          <div class="schol-uni">Australia — Various Universities</div>
          <div class="schol-tags"><span class="schol-tag">Development</span><span class="schol-tag">Undergraduate</span><span class="schol-tag">Masters</span></div>
        </div>
      </div>
    </div>
  </div>

</main>

<!-- FOOTER -->
<footer class="portal-footer">
  <div class="footer-copy">&copy; 2026 Bold Footprint Initiatives. All rights reserved.</div>
  <div class="footer-links">
    <a href="/index.html"><i class="fas fa-home" style="font-size:10px;margin-right:4px;"></i>Main Site</a>
    <a href="/about.html">About Us</a>
    <a href="/programs.html">Programs</a>
    <a href="/contact.html">Contact</a>
  </div>
</footer>

<script>
  const sidebar=document.getElementById('sidebar');
  const overlay=document.getElementById('sidebarOverlay');
  const toggle=document.getElementById('mobileToggle');
  function openSidebar(){sidebar.classList.add('active');overlay.classList.add('active');document.body.style.overflow='hidden';}
  function closeSidebar(){sidebar.classList.remove('active');overlay.classList.remove('active');document.body.style.overflow='';}
  toggle.addEventListener('click',()=>sidebar.classList.contains('active')?closeSidebar():openSidebar());
  overlay.addEventListener('click',closeSidebar);
  window.addEventListener('resize',()=>{if(window.innerWidth>768)closeSidebar();});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>