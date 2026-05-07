<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user = [];
$error_msg = '';
$success_msg = '';
$notification_count = 0;

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("
        SELECT id, first_name, last_name, email, phone, date_of_birth, gender,
               education_level, university, major, graduation_year, country, city,
               bio, interests, profile_picture, created_at
        FROM users WHERE id = :user_id
    ");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) throw new Exception("User not found. Please log in again.");

    // Notification count
    try {
        $n_stmt = $conn->prepare("SELECT id FROM notifications WHERE user_id = :uid AND read_status = 0");
        $n_stmt->execute([':uid' => $_SESSION['user_id']]);
        $notification_count = $n_stmt->rowCount();
    } catch (Exception $e) {}

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        $first_name      = trim($_POST['first_name']);
        $last_name       = trim($_POST['last_name']);
        $phone           = trim($_POST['phone']);
        $date_of_birth   = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
        $gender          = isset($_POST['gender']) ? $_POST['gender'] : null;
        $education_level = trim($_POST['education_level']);
        $university      = trim($_POST['university']);
        $major           = trim($_POST['major']);
        $graduation_year = !empty($_POST['graduation_year']) ? (int)$_POST['graduation_year'] : null;
        $country         = trim($_POST['country']);
        $city            = trim($_POST['city']);
        $bio             = trim($_POST['bio']);
        $interests       = trim($_POST['interests']);
        $profile_picture = $user['profile_picture'];

        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['size'] > 0) {
            $upload_dir = './uploads/profile_pictures/';
            $temp_name  = $_FILES['profile_picture']['tmp_name'];
            $file_type  = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
            $allowed    = ['jpg','jpeg','png','gif'];
            if (in_array($file_type, $allowed)) {
                $new_filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_type;
                if (!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);
                if (move_uploaded_file($temp_name, $upload_dir . $new_filename)) {
                    if ($profile_picture && file_exists($upload_dir . $profile_picture)) unlink($upload_dir . $profile_picture);
                    $profile_picture = $new_filename;
                } else { $error_msg = "Failed to upload profile picture."; }
            } else { $error_msg = "Invalid file type. Use JPG, PNG, or GIF."; }
        }

        if (empty($error_msg)) {
            $upd = $conn->prepare("
                UPDATE users SET first_name=:fn, last_name=:ln, phone=:ph, date_of_birth=:dob,
                  gender=:g, education_level=:el, university=:uni, major=:maj,
                  graduation_year=:gy, country=:co, city=:ci, bio=:bio,
                  interests=:int, profile_picture=:pp, updated_at=CURRENT_TIMESTAMP
                WHERE id=:uid
            ");
            if ($upd->execute([':fn'=>$first_name,':ln'=>$last_name,':ph'=>$phone,':dob'=>$date_of_birth,':g'=>$gender,':el'=>$education_level,':uni'=>$university,':maj'=>$major,':gy'=>$graduation_year,':co'=>$country,':ci'=>$city,':bio'=>$bio,':int'=>$interests,':pp'=>$profile_picture,':uid'=>$_SESSION['user_id']])) {
                $success_msg = "Profile updated successfully!";
                $stmt->execute([':user_id' => $_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else { $error_msg = "Failed to update profile."; }
        }
    }
} catch (Exception $e) {
    error_log("Profile error: " . $e->getMessage());
    $error_msg = "An error occurred: " . $e->getMessage();
}

// Profile completion meter
$completion_fields = ['first_name','last_name','phone','date_of_birth','gender','education_level','university','major','graduation_year','country','city','bio','interests','profile_picture'];
$filled = 0;
foreach ($completion_fields as $f) { if (!empty($user[$f])) $filled++; }
$profile_completion = $user ? round(($filled / count($completion_fields)) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>My Profile | BFI Scholar Portal</title>
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

    /* ── SIDEBAR ── */
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

    /* ── HEADER ── */
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

    /* ── MAIN ── */
    .main{margin-left:var(--sidebar-width);margin-top:var(--header-height);padding:28px;min-height:calc(100vh - var(--header-height));}

    /* ── PROFILE HERO ── */
    .profile-hero{background:var(--navy);border-radius:var(--r-xl);padding:32px 36px;margin-bottom:24px;position:relative;overflow:hidden;}
    .profile-hero::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:32px 32px;}
    .profile-hero::after{content:'';position:absolute;top:-60px;right:-60px;width:260px;height:260px;background:radial-gradient(circle,rgba(200,160,88,0.1) 0%,transparent 65%);}
    .profile-hero-inner{position:relative;z-index:1;display:flex;align-items:center;gap:28px;flex-wrap:wrap;}
    .profile-hero-avatar{width:80px;height:80px;border-radius:50%;border:3px solid rgba(200,160,88,0.4);overflow:hidden;background:var(--navy-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .profile-hero-avatar img{width:100%;height:100%;object-fit:cover;}
    .profile-hero-avatar-init{font-family:var(--font-display);font-size:32px;font-weight:500;color:var(--gold-bright);}
    .profile-hero-info{flex:1;}
    .profile-eyebrow{font-size:10px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:rgba(200,160,88,0.7);margin-bottom:6px;}
    .profile-name{font-family:var(--font-display);font-size:clamp(22px,3vw,30px);font-weight:500;color:var(--white);margin-bottom:4px;}
    .profile-email{font-size:13px;color:rgba(255,255,255,0.4);}
    .completion-wrap{flex:1;min-width:220px;}
    .completion-label{display:flex;justify-content:space-between;font-size:11px;color:rgba(255,255,255,0.5);margin-bottom:8px;}
    .completion-label strong{color:var(--gold-bright);}
    .completion-bar{height:6px;background:rgba(255,255,255,0.1);border-radius:3px;overflow:hidden;}
    .completion-fill{height:100%;background:linear-gradient(90deg,var(--gold),var(--gold-bright));border-radius:3px;transition:width 0.8s var(--ease);}
    .completion-hint{font-size:11px;color:rgba(255,255,255,0.35);margin-top:8px;}

    /* ── FORM SECTIONS ── */
    .form-section{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:28px;margin-bottom:20px;}
    .fs-header{display:flex;align-items:center;gap:12px;padding-bottom:18px;border-bottom:1px solid var(--border-light);margin-bottom:24px;}
    .fs-icon{width:38px;height:38px;background:var(--navy);border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:15px;color:var(--gold-bright);flex-shrink:0;}
    .fs-title{font-family:var(--font-display);font-size:19px;font-weight:500;color:var(--navy);}
    .fs-title em{font-style:italic;color:var(--gold);}

    /* Avatar upload */
    .avatar-upload-section{display:flex;flex-direction:column;align-items:center;gap:14px;}
    .avatar-large{width:110px;height:110px;border-radius:50%;border:3px solid var(--border-light);overflow:hidden;background:var(--navy-light);display:flex;align-items:center;justify-content:center;transition:var(--transition);}
    .avatar-large:hover{border-color:var(--gold);}
    .avatar-large img{width:100%;height:100%;object-fit:cover;}
    .avatar-large-init{font-family:var(--font-display);font-size:36px;font-weight:500;color:var(--gold-bright);}
    .avatar-upload-btn{display:inline-flex;align-items:center;gap:7px;font-size:12px;font-weight:500;color:var(--gold);background:rgba(200,160,88,0.08);border:1px solid rgba(200,160,88,0.2);padding:7px 16px;border-radius:20px;cursor:pointer;transition:var(--transition);}
    .avatar-upload-btn:hover{background:var(--gold);color:var(--midnight);}
    .avatar-hint{font-size:11px;color:var(--text-muted);text-align:center;}
    .avatar-change-note{font-size:12px;color:var(--gold);background:rgba(200,160,88,0.08);border:1px solid rgba(200,160,88,0.2);padding:8px 14px;border-radius:var(--r-sm);text-align:center;}

    /* Form fields */
    .form-label{font-size:12px;font-weight:600;letter-spacing:0.5px;color:var(--text-secondary);margin-bottom:7px;display:block;}
    .form-control,.form-select{width:100%;padding:11px 14px;border:1.5px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:14px;color:var(--text-primary);background:var(--cream);transition:var(--transition);}
    .form-control:focus,.form-select:focus{outline:none;border-color:var(--gold);background:var(--white);box-shadow:0 0 0 3px rgba(200,160,88,0.1);}
    .form-control:disabled,.form-control[readonly]{background:rgba(8,14,28,0.03);color:var(--text-muted);cursor:not-allowed;}
    textarea.form-control{resize:vertical;min-height:110px;line-height:1.6;}
    .field-note{font-size:11.5px;color:var(--text-muted);margin-top:5px;}
    .char-count{font-size:11px;color:var(--text-muted);text-align:right;margin-top:4px;}

    /* Submit */
    .btn-gold{display:inline-flex;align-items:center;gap:8px;padding:13px 28px;background:var(--gold);color:var(--midnight);font-family:var(--font-body);font-size:13.5px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-gold:hover{background:var(--gold-bright);transform:translateY(-2px);box-shadow:var(--shadow-md);}
    .btn-outline{display:inline-flex;align-items:center;gap:8px;padding:13px 28px;background:transparent;color:var(--text-secondary);font-family:var(--font-body);font-size:13.5px;font-weight:400;border:1.5px solid var(--border-light);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-outline:hover{border-color:var(--navy);color:var(--navy);}

    /* Alerts */
    .portal-alert{padding:14px 18px;border-radius:var(--r-md);margin-bottom:20px;font-size:13.5px;display:flex;align-items:center;gap:10px;}
    .portal-alert-success{background:rgba(16,185,129,0.08);color:#059669;border:1px solid rgba(16,185,129,0.2);}
    .portal-alert-danger{background:rgba(239,68,68,0.08);color:#DC2626;border:1px solid rgba(239,68,68,0.2);}

    /* Footer */
    .portal-footer{margin-left:var(--sidebar-width);background:var(--white);padding:16px 28px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;}
    .footer-copy{font-size:12px;color:var(--text-muted);}
    .footer-links{display:flex;gap:20px;}
    .footer-links a{font-size:12px;color:var(--text-muted);transition:color var(--transition);}
    .footer-links a:hover{color:var(--gold);}

    /* Overlay */
    .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:150;backdrop-filter:blur(4px);}
    .sidebar-overlay.active{display:block;}

    /* Responsive */
    @media(max-width:768px){
      .sidebar{transform:translateX(-100%);}
      .sidebar.active{transform:translateX(0);}
      .header{left:0;}
      .main,.portal-footer{margin-left:0;}
      .mobile-toggle{display:flex;}
      .header-greeting{display:none;}
      .profile-hero-inner{flex-direction:column;align-items:flex-start;gap:20px;}
      .completion-wrap{width:100%;}
      .form-section{padding:20px;}
    }
    @media(max-width:480px){
      .main{padding:16px;}
      .profile-hero{padding:24px 20px;}
    }
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
        <?php if (!empty($user['profile_picture'])): ?>
          <img src="<?php echo htmlspecialchars('./uploads/profile_pictures/' . $user['profile_picture']); ?>" alt="Profile" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
          <div class="sidebar-avatar-init" style="display:none;"><?php echo strtoupper(substr($user['first_name'] ?? '',0,1)); ?></div>
        <?php else: ?>
          <div class="sidebar-avatar-init"><?php echo strtoupper(substr($user['first_name'] ?? '',0,1)); ?></div>
        <?php endif; ?>
      </div>
      <div>
        <div class="sidebar-user-name"><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></div>
        <div class="sidebar-user-role">BFI Scholar</div>
      </div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>
    <div class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a></div>
    <div class="nav-item"><a href="profile.php" class="nav-link active"><i class="fas fa-user"></i> My Profile</a></div>
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
    <a href="logout.php" class="nav-link" style="color:rgba(239,68,68,0.7);"><i class="fas fa-sign-out-alt"></i> Log Out</a>
  </div>
</aside>

<!-- HEADER -->
<header class="header">
  <div class="header-left">
    <button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
    <div class="header-greeting">My <strong>Profile</strong></div>
  </div>
  <div class="header-right">
    <button class="header-icon-btn" title="Notifications">
      <i class="fas fa-bell"></i>
      <?php if ($notification_count > 0): ?><div class="notif-dot"></div><?php endif; ?>
    </button>
    <div class="header-avatar">
      <?php if (!empty($user['profile_picture'])): ?>
        <img src="<?php echo htmlspecialchars('./uploads/profile_pictures/' . $user['profile_picture']); ?>" alt="Profile" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
        <div class="header-avatar-init" style="display:none;"><?php echo strtoupper(substr($user['first_name'] ?? '',0,1)); ?></div>
      <?php else: ?>
        <div class="header-avatar-init"><?php echo strtoupper(substr($user['first_name'] ?? '',0,1)); ?></div>
      <?php endif; ?>
    </div>
  </div>
</header>

<!-- MAIN -->
<main class="main">

  <!-- PROFILE HERO -->
  <div class="profile-hero">
    <div class="profile-hero-inner">
      <div class="profile-hero-avatar">
        <?php if (!empty($user['profile_picture'])): ?>
          <img src="<?php echo htmlspecialchars('./uploads/profile_pictures/' . $user['profile_picture']); ?>" alt="Profile" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
          <div class="profile-hero-avatar-init" style="display:none;"><?php echo strtoupper(substr($user['first_name'] ?? '',0,1)); ?></div>
        <?php else: ?>
          <div class="profile-hero-avatar-init"><?php echo strtoupper(substr($user['first_name'] ?? '',0,1)); ?></div>
        <?php endif; ?>
      </div>
      <div class="profile-hero-info">
        <div class="profile-eyebrow">Scholar Profile</div>
        <div class="profile-name"><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></div>
        <div class="profile-email"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
      </div>
      <div class="completion-wrap">
        <div class="completion-label">
          <span>Profile Completion</span>
          <strong><?php echo $profile_completion; ?>%</strong>
        </div>
        <div class="completion-bar">
          <div class="completion-fill" style="width:<?php echo $profile_completion; ?>%;"></div>
        </div>
        <div class="completion-hint">
          <?php if ($profile_completion < 100): ?>
            <?php echo count($completion_fields) - $filled; ?> field(s) remaining — a complete profile improves your scholarship matches.
          <?php else: ?>
            ✓ Your profile is complete!
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <?php if (!empty($success_msg)): ?>
    <div class="portal-alert portal-alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?></div>
  <?php endif; ?>
  <?php if (!empty($error_msg)): ?>
    <div class="portal-alert portal-alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?></div>
  <?php endif; ?>

  <form method="POST" action="profile.php" enctype="multipart/form-data">

    <!-- SECTION 1: Profile Picture + Personal Info -->
    <div class="form-section">
      <div class="fs-header">
        <div class="fs-icon"><i class="fas fa-id-card"></i></div>
        <div class="fs-title">Personal <em>Information</em></div>
      </div>
      <div class="row g-4">
        <div class="col-md-3">
          <div class="avatar-upload-section">
            <div class="avatar-large" id="avatarPreview">
              <?php if (!empty($user['profile_picture'])): ?>
                <img src="<?php echo htmlspecialchars('./uploads/profile_pictures/' . $user['profile_picture']); ?>"
                     alt="Profile" id="avatarImg"
                     onerror="this.style.display='none';document.getElementById('avatarInitFallback').style.display='flex';">
                <div class="avatar-large-init" id="avatarInitFallback" style="display:none;"><?php echo strtoupper(substr($user['first_name'] ?? '',0,1)); ?></div>
              <?php else: ?>
                <div class="avatar-large-init" id="avatarInitFallback"><?php echo strtoupper(substr($user['first_name'] ?? '',0,1)); ?></div>
              <?php endif; ?>
            </div>
            <label for="profile_picture_upload" class="avatar-upload-btn">
              <i class="fas fa-camera"></i> Change Photo
            </label>
            <input type="file" id="profile_picture_upload" name="profile_picture" accept="image/*" style="display:none;">
            <div class="avatar-hint">JPG, PNG or GIF · Max 5MB</div>
            <div class="avatar-change-note" id="photoChangeNote" style="display:none;">
              <i class="fas fa-info-circle"></i> New photo selected — save to apply.
            </div>
          </div>
        </div>
        <div class="col-md-9">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">First Name</label>
              <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Last Name</label>
              <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email Address</label>
              <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly disabled>
              <div class="field-note">Email cannot be changed. Contact support if needed.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone Number</label>
              <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="+234 800 000 0000">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- SECTION 2: Demographics -->
    <div class="form-section">
      <div class="fs-header">
        <div class="fs-icon"><i class="fas fa-user-circle"></i></div>
        <div class="fs-title">Demographic <em>Details</em></div>
      </div>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Date of Birth</label>
          <input type="date" class="form-control" name="date_of_birth" value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Gender</label>
          <select class="form-select" name="gender">
            <option value="">Select Gender</option>
            <option value="male"              <?php echo (($user['gender'] ?? '') === 'male')              ? 'selected' : ''; ?>>Male</option>
            <option value="female"            <?php echo (($user['gender'] ?? '') === 'female')            ? 'selected' : ''; ?>>Female</option>
            <option value="non-binary"        <?php echo (($user['gender'] ?? '') === 'non-binary')        ? 'selected' : ''; ?>>Non-binary</option>
            <option value="prefer-not-to-say" <?php echo (($user['gender'] ?? '') === 'prefer-not-to-say') ? 'selected' : ''; ?>>Prefer not to say</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Education Level</label>
          <select class="form-select" name="education_level">
            <option value="">Select Level</option>
            <option value="high-school" <?php echo (($user['education_level'] ?? '') === 'high-school') ? 'selected' : ''; ?>>High School</option>
            <option value="bachelors"   <?php echo (($user['education_level'] ?? '') === 'bachelors')   ? 'selected' : ''; ?>>Bachelor's Degree</option>
            <option value="masters"     <?php echo (($user['education_level'] ?? '') === 'masters')     ? 'selected' : ''; ?>>Master's Degree</option>
            <option value="phd"         <?php echo (($user['education_level'] ?? '') === 'phd')         ? 'selected' : ''; ?>>PhD / Doctoral</option>
            <option value="other"       <?php echo (($user['education_level'] ?? '') === 'other')       ? 'selected' : ''; ?>>Other</option>
          </select>
        </div>
      </div>
    </div>

    <!-- SECTION 3: Academic Info -->
    <div class="form-section">
      <div class="fs-header">
        <div class="fs-icon"><i class="fas fa-graduation-cap"></i></div>
        <div class="fs-title">Academic <em>Background</em></div>
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">University / Institution</label>
          <input type="text" class="form-control" name="university" value="<?php echo htmlspecialchars($user['university'] ?? ''); ?>" placeholder="e.g. University of Lagos">
        </div>
        <div class="col-md-6">
          <label class="form-label">Field of Study / Major</label>
          <input type="text" class="form-control" name="major" value="<?php echo htmlspecialchars($user['major'] ?? ''); ?>" placeholder="e.g. Electrical Engineering">
        </div>
        <div class="col-md-4">
          <label class="form-label">Graduation Year</label>
          <input type="number" class="form-control" name="graduation_year" min="1980" max="2030" value="<?php echo htmlspecialchars($user['graduation_year'] ?? ''); ?>" placeholder="e.g. 2024">
        </div>
        <div class="col-md-4">
          <label class="form-label">Country</label>
          <input type="text" class="form-control" name="country" value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>" placeholder="e.g. Nigeria">
        </div>
        <div class="col-md-4">
          <label class="form-label">City</label>
          <input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" placeholder="e.g. Lagos">
        </div>
      </div>
    </div>

    <!-- SECTION 4: Bio & Interests -->
    <div class="form-section">
      <div class="fs-header">
        <div class="fs-icon"><i class="fas fa-pen-nib"></i></div>
        <div class="fs-title">About <em>Me</em></div>
      </div>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Bio</label>
          <textarea class="form-control" name="bio" id="bioField" rows="4" maxlength="600" placeholder="Share your background, achievements, and what drives you…"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
          <div class="char-count"><span id="bioCount"><?php echo strlen($user['bio'] ?? ''); ?></span> / 600</div>
        </div>
        <div class="col-12">
          <label class="form-label">Research Interests</label>
          <textarea class="form-control" name="interests" id="interestsField" rows="3" maxlength="400" placeholder="List your academic and research interests — these help match you to scholarships…"><?php echo htmlspecialchars($user['interests'] ?? ''); ?></textarea>
          <div class="char-count"><span id="interestsCount"><?php echo strlen($user['interests'] ?? ''); ?></span> / 400</div>
        </div>
      </div>
    </div>

    <!-- SUBMIT -->
    <div style="display:flex;justify-content:flex-end;gap:12px;margin-bottom:32px;">
      <a href="dashboard.php" class="btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
      <button type="submit" name="update_profile" class="btn-gold"><i class="fas fa-save"></i> Save Profile</button>
    </div>

  </form>
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
  // Sidebar toggle
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  const toggle  = document.getElementById('mobileToggle');
  function openSidebar() { sidebar.classList.add('active'); overlay.classList.add('active'); document.body.style.overflow = 'hidden'; }
  function closeSidebar(){ sidebar.classList.remove('active'); overlay.classList.remove('active'); document.body.style.overflow = ''; }
  if (toggle) toggle.addEventListener('click', () => sidebar.classList.contains('active') ? closeSidebar() : openSidebar());
  overlay.addEventListener('click', closeSidebar);
  window.addEventListener('resize', () => { if (window.innerWidth > 768) closeSidebar(); });

  // Profile picture preview
  const fileInput   = document.getElementById('profile_picture_upload');
  const avatarWrap  = document.getElementById('avatarPreview');
  const changeNote  = document.getElementById('photoChangeNote');

  if (fileInput) {
    fileInput.addEventListener('change', function () {
      if (!this.files || !this.files[0]) return;
      const file = this.files[0];
      if (file.size > 5 * 1024 * 1024) { alert('File exceeds 5 MB.'); this.value = ''; return; }
      if (!file.type.match(/^image\/(jpeg|jpg|png|gif)$/)) { alert('Please choose a JPG, PNG, or GIF.'); this.value = ''; return; }
      const reader = new FileReader();
      reader.onload = e => {
        avatarWrap.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;" alt="Preview">`;
        if (changeNote) changeNote.style.display = 'block';
      };
      reader.readAsDataURL(file);
    });
  }

  // Character counters
  function bindCounter(fieldId, countId) {
    const field = document.getElementById(fieldId);
    const count = document.getElementById(countId);
    if (!field || !count) return;
    field.addEventListener('input', () => { count.textContent = field.value.length; });
  }
  bindCounter('bioField', 'bioCount');
  bindCounter('interestsField', 'interestsCount');
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>