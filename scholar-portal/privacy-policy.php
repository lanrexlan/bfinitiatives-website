<?php
session_start();

$first_name = '';
$profile_picture = null;

if (isset($_SESSION['user_id'])) {
    try {
        require_once 'includes/config.php';
        require_once 'includes/db.php';
        $db   = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT first_name, profile_picture FROM users WHERE id = :user_id");
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $first_name     = $user['first_name'];
            $profile_picture = $user['profile_picture'] ?? null;
        }
    } catch (Exception $e) {
        error_log("Privacy policy page error: " . $e->getMessage());
    }
}

$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Privacy Policy | BFI Scholar Portal</title>
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
      --sidebar-width:268px;--header-height:64px;
      --r-sm:8px;--r-md:14px;--r-lg:20px;--r-xl:28px;
    }
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    html{font-size:16px;scroll-behavior:smooth;}
    body{font-family:var(--font-body);background:#F2F4F8;color:var(--text-primary);line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden;}
    a{text-decoration:none;color:inherit;}
    img{max-width:100%;display:block;}

    /* ── READING PROGRESS ── */
    #readingProgress{position:fixed;top:0;left:0;height:3px;background:linear-gradient(90deg,var(--gold),var(--gold-bright));z-index:999;transition:width 0.1s linear;width:0%;}

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
    .sidebar-bottom{padding:16px 12px;border-top:1px solid rgba(255,255,255,0.06);}

    /* ── HEADER ── */
    .header{position:fixed;top:0;left:<?php echo $isLoggedIn?'var(--sidebar-width)':'0'; ?>;right:0;height:var(--header-height);background:var(--white);border-bottom:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;padding:0 28px;z-index:100;transition:left var(--transition);}
    .header-left{display:flex;align-items:center;gap:16px;}
    .mobile-toggle{display:none;background:none;border:none;cursor:pointer;padding:6px;color:var(--text-secondary);font-size:18px;}
    .header-page-title{font-family:var(--font-display);font-size:19px;font-weight:500;color:var(--navy);}
    .header-page-title em{font-style:italic;color:var(--gold);}
    .header-right{display:flex;align-items:center;gap:12px;}
    .header-icon-btn{width:36px;height:36px;border-radius:var(--r-sm);background:var(--cream);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-secondary);font-size:15px;transition:var(--transition);}
    .header-icon-btn:hover{background:var(--cream-dark);color:var(--navy);}
    .header-avatar{width:36px;height:36px;border-radius:50%;background:var(--navy-light);overflow:hidden;border:2px solid var(--border-light);cursor:pointer;display:flex;align-items:center;justify-content:center;}
    .header-avatar img{width:100%;height:100%;object-fit:cover;}
    .header-avatar-init{font-family:var(--font-display);font-size:14px;color:var(--gold-bright);}
    .btn-login{display:inline-flex;align-items:center;gap:8px;padding:9px 18px;background:var(--gold);color:var(--midnight);font-family:var(--font-body);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-login:hover{background:var(--gold-bright);}

    /* ── MAIN ── */
    .main{margin-left:<?php echo $isLoggedIn?'var(--sidebar-width)':'0'; ?>;margin-top:var(--header-height);padding:28px;min-height:calc(100vh - var(--header-height));transition:margin-left var(--transition);}

    /* ── HERO BANNER ── */
    .hero-banner{background:var(--navy);border-radius:var(--r-xl);padding:36px 40px;margin-bottom:28px;position:relative;overflow:hidden;}
    .hero-banner::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:32px 32px;}
    .hero-banner::after{content:'';position:absolute;top:-60px;right:-60px;width:300px;height:300px;background:radial-gradient(circle,rgba(200,160,88,0.1) 0%,transparent 65%);}
    .hero-inner{position:relative;z-index:1;display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:20px;}
    .hero-eyebrow{font-size:10px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:rgba(200,160,88,0.7);margin-bottom:8px;}
    .hero-title{font-family:var(--font-display);font-size:clamp(26px,3.5vw,38px);font-weight:500;color:var(--white);line-height:1.15;}
    .hero-title em{font-style:italic;color:var(--gold-bright);}
    .hero-sub{font-size:13.5px;font-weight:300;color:rgba(255,255,255,0.5);margin-top:6px;max-width:480px;}
    .hero-meta{display:flex;gap:16px;flex-wrap:wrap;align-items:center;}
    .hero-meta-item{display:flex;align-items:center;gap:6px;font-size:12px;color:rgba(255,255,255,0.4);}
    .hero-meta-item i{color:rgba(200,160,88,0.6);}
    .hero-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px;}
    .btn-gold-sm{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:var(--gold);color:var(--midnight);font-family:var(--font-body);font-size:12.5px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-gold-sm:hover{background:var(--gold-bright);}
    .btn-ghost-sm{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.75);font-family:var(--font-body);font-size:12.5px;border:1px solid rgba(255,255,255,0.1);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-ghost-sm:hover{background:rgba(255,255,255,0.1);color:var(--white);}

    /* ── LAYOUT ── */
    .policy-layout{display:grid;grid-template-columns:220px 1fr;gap:24px;align-items:start;}

    /* ── TABLE OF CONTENTS ── */
    .toc-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:20px;position:sticky;top:calc(var(--header-height) + 20px);}
    .toc-title{font-family:var(--font-display);font-size:15px;font-weight:500;color:var(--navy);margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--border-light);}
    .toc-title em{font-style:italic;color:var(--gold);}
    .toc-list{list-style:none;}
    .toc-item{margin-bottom:2px;}
    .toc-link{display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:var(--r-sm);font-size:12.5px;color:var(--text-muted);transition:var(--transition);cursor:pointer;}
    .toc-link:hover{background:var(--cream);color:var(--text-primary);}
    .toc-link.active{background:rgba(200,160,88,0.1);color:var(--gold);font-weight:500;}
    .toc-num{width:18px;height:18px;border-radius:50%;background:var(--cream);font-size:9px;font-weight:600;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--text-muted);}
    .toc-link.active .toc-num{background:var(--gold);color:var(--midnight);}
    .toc-progress-bar{height:3px;background:var(--border-light);border-radius:2px;overflow:hidden;margin-top:14px;}
    .toc-progress-fill{height:100%;background:linear-gradient(90deg,var(--gold),var(--gold-bright));border-radius:2px;transition:width 0.2s;}

    /* ── POLICY CONTENT ── */
    .policy-container{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);overflow:hidden;}
    .policy-section{padding:32px 36px;border-bottom:1px solid var(--border-light);}
    .policy-section:last-child{border-bottom:none;}
    .ps-header{display:flex;align-items:flex-start;gap:14px;margin-bottom:18px;}
    .ps-icon{width:40px;height:40px;border-radius:var(--r-sm);background:rgba(200,160,88,0.1);display:flex;align-items:center;justify-content:center;font-size:16px;color:var(--gold);flex-shrink:0;}
    .ps-num{font-family:var(--font-display);font-size:11px;font-weight:500;color:var(--gold);letter-spacing:1px;margin-bottom:3px;}
    .ps-title{font-family:var(--font-display);font-size:22px;font-weight:500;color:var(--navy);line-height:1.15;}
    .ps-title em{font-style:italic;color:var(--gold);}
    .ps-body{font-size:14px;line-height:1.8;color:var(--text-secondary);}
    .ps-body p{margin-bottom:14px;}
    .ps-body p:last-child{margin-bottom:0;}
    .ps-sub-title{font-size:14px;font-weight:600;color:var(--navy);margin:18px 0 10px;}
    .ps-list{list-style:none;padding:0;margin:10px 0 14px;}
    .ps-list li{display:flex;align-items:flex-start;gap:10px;font-size:14px;color:var(--text-secondary);margin-bottom:9px;line-height:1.6;}
    .ps-list li::before{content:'';width:6px;height:6px;border-radius:50%;background:var(--gold);flex-shrink:0;margin-top:8px;}
    .highlight-box{background:var(--cream);border-left:3px solid var(--gold);border-radius:0 var(--r-sm) var(--r-sm) 0;padding:14px 18px;font-size:13.5px;color:var(--text-secondary);margin:14px 0;line-height:1.7;}
    .highlight-box strong{color:var(--navy);}

    /* ── CONTACT SECTION ── */
    .contact-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px;}
    .contact-card{background:var(--cream);border-radius:var(--r-md);padding:16px;display:flex;align-items:center;gap:12px;transition:var(--transition);}
    .contact-card:hover{background:var(--cream-dark);}
    .contact-card-icon{width:36px;height:36px;background:var(--navy);border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:14px;color:var(--gold-bright);flex-shrink:0;}
    .contact-card-label{font-size:10.5px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;}
    .contact-card-val{font-size:13px;font-weight:500;color:var(--navy);margin-top:2px;}
    .contact-card-val a{color:var(--navy);transition:color var(--transition);}
    .contact-card-val a:hover{color:var(--gold);}

    /* ── FOOTER ── */
    .portal-footer{margin-left:<?php echo $isLoggedIn?'var(--sidebar-width)':'0'; ?>;background:var(--white);padding:16px 28px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;transition:margin-left var(--transition);}
    .footer-copy{font-size:12px;color:var(--text-muted);}
    .footer-links{display:flex;gap:20px;}
    .footer-links a{font-size:12px;color:var(--text-muted);transition:color var(--transition);}
    .footer-links a:hover{color:var(--gold);}

    /* ── BACK TO TOP ── */
    #backToTop{position:fixed;bottom:28px;right:28px;width:40px;height:40px;background:var(--navy);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--gold-bright);font-size:14px;cursor:pointer;opacity:0;pointer-events:none;transition:var(--transition);border:none;box-shadow:var(--shadow-md);z-index:90;}
    #backToTop.visible{opacity:1;pointer-events:all;}
    #backToTop:hover{background:var(--navy-mid);transform:translateY(-2px);}

    /* ── SIDEBAR OVERLAY ── */
    .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:150;backdrop-filter:blur(4px);}
    .sidebar-overlay.active{display:block;}

    /* ── RESPONSIVE ── */
    @media(max-width:1100px){.policy-layout{grid-template-columns:1fr;}.toc-card{position:static;display:none;}}
    @media(max-width:768px){
      .sidebar{transform:translateX(-100%);}
      .sidebar.active{transform:translateX(0);}
      .header{left:0;}
      .main,.portal-footer{margin-left:0;}
      .mobile-toggle{display:flex;}
      .policy-section{padding:24px 20px;}
      .contact-grid{grid-template-columns:1fr;}
    }
    @media(max-width:480px){
      .main{padding:16px;}
      .hero-banner{padding:24px 20px;}
      .ps-header{flex-direction:column;gap:10px;}
    }
  </style>
</head>
<body>

<div id="readingProgress"></div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR (logged-in only) -->
<?php if ($isLoggedIn): ?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-top">
    <div class="sidebar-logo">
      <div class="sidebar-logomark"><svg viewBox="0 0 22 22" fill="none"><path d="M4 18L11 4L18 18" stroke="#080E1C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 13H15" stroke="#080E1C" stroke-width="1.8" stroke-linecap="round"/></svg></div>
      <div class="sidebar-logo-text">Bold Footprint<span>Scholar Portal</span></div>
    </div>
    <div class="sidebar-user">
      <div class="sidebar-avatar">
        <?php if ($profile_picture): ?>
          <img src="<?php echo htmlspecialchars('./uploads/profile_pictures/'.$profile_picture); ?>" alt="Profile" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
          <div class="sidebar-avatar-init" style="display:none;"><?php echo strtoupper(substr($first_name,0,1)); ?></div>
        <?php else: ?>
          <div class="sidebar-avatar-init"><?php echo strtoupper(substr($first_name,0,1)); ?></div>
        <?php endif; ?>
      </div>
      <div>
        <div class="sidebar-user-name"><?php echo htmlspecialchars($first_name); ?></div>
        <div class="sidebar-user-role">BFI Scholar</div>
      </div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>
    <div class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a></div>
    <div class="nav-item"><a href="profile.php" class="nav-link"><i class="fas fa-user"></i> My Profile</a></div>
    <div class="nav-item"><a href="#" class="nav-link"><i class="fas fa-route"></i> My Journey</a></div>
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
<?php endif; ?>

<!-- HEADER -->
<header class="header">
  <div class="header-left">
    <?php if ($isLoggedIn): ?>
    <button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
    <?php endif; ?>
    <div class="header-page-title">Privacy <em>Policy</em></div>
  </div>
  <div class="header-right">
    <button class="header-icon-btn" title="Print this page" onclick="window.print()"><i class="fas fa-print"></i></button>
    <?php if ($isLoggedIn): ?>
    <a href="settings.php"><button class="header-icon-btn" title="Settings"><i class="fas fa-cog"></i></button></a>
    <a href="profile.php">
      <div class="header-avatar">
        <?php if ($profile_picture): ?>
          <img src="<?php echo htmlspecialchars('./uploads/profile_pictures/'.$profile_picture); ?>" alt="Profile" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
          <div class="header-avatar-init" style="display:none;"><?php echo strtoupper(substr($first_name,0,1)); ?></div>
        <?php else: ?>
          <div class="header-avatar-init"><?php echo strtoupper(substr($first_name,0,1)); ?></div>
        <?php endif; ?>
      </div>
    </a>
    <?php else: ?>
    <a href="login.php" class="btn-login"><i class="fas fa-sign-in-alt"></i> Sign In</a>
    <?php endif; ?>
  </div>
</header>

<!-- MAIN -->
<main class="main" id="mainContent">

  <!-- HERO BANNER -->
  <div class="hero-banner">
    <div class="hero-inner">
      <div>
        <div class="hero-eyebrow">Legal &amp; Compliance</div>
        <div class="hero-title">Privacy <em>Policy</em></div>
        <div class="hero-sub">How Bold Footprint Initiatives collects, protects, and respects your personal data across the Scholar Portal.</div>
        <div class="hero-actions">
          <button class="btn-gold-sm" onclick="window.print()"><i class="fas fa-print"></i> Print Policy</button>
          <a href="settings.php#panel-privacy" class="btn-ghost-sm"><i class="fas fa-sliders-h"></i> Manage My Data</a>
        </div>
      </div>
      <div class="hero-meta">
        <div class="hero-meta-item"><i class="fas fa-calendar-alt"></i> Last updated: March 2026</div>
        <div class="hero-meta-item"><i class="fas fa-clock"></i> ~7 min read</div>
        <div class="hero-meta-item"><i class="fas fa-list-ul"></i> 11 sections</div>
      </div>
    </div>
  </div>

  <!-- POLICY LAYOUT -->
  <div class="policy-layout">

    <!-- TABLE OF CONTENTS -->
    <div class="toc-card">
      <div class="toc-title">Table of <em>Contents</em></div>
      <ul class="toc-list">
        <li class="toc-item"><a class="toc-link active" href="#s1"><span class="toc-num">1</span> Introduction</a></li>
        <li class="toc-item"><a class="toc-link" href="#s2"><span class="toc-num">2</span> Information We Collect</a></li>
        <li class="toc-item"><a class="toc-link" href="#s3"><span class="toc-num">3</span> How We Use It</a></li>
        <li class="toc-item"><a class="toc-link" href="#s4"><span class="toc-num">4</span> Sharing &amp; Disclosure</a></li>
        <li class="toc-item"><a class="toc-link" href="#s5"><span class="toc-num">5</span> Data Security</a></li>
        <li class="toc-item"><a class="toc-link" href="#s6"><span class="toc-num">6</span> Your Rights</a></li>
        <li class="toc-item"><a class="toc-link" href="#s7"><span class="toc-num">7</span> Cookies</a></li>
        <li class="toc-item"><a class="toc-link" href="#s8"><span class="toc-num">8</span> Data Retention</a></li>
        <li class="toc-item"><a class="toc-link" href="#s9"><span class="toc-num">9</span> International Transfers</a></li>
        <li class="toc-item"><a class="toc-link" href="#s10"><span class="toc-num">10</span> Children's Privacy</a></li>
        <li class="toc-item"><a class="toc-link" href="#s11"><span class="toc-num">11</span> Contact Us</a></li>
      </ul>
      <div class="toc-progress-bar"><div class="toc-progress-fill" id="tocProgress" style="width:0%;"></div></div>
      <div style="font-size:11px;color:var(--text-muted);margin-top:8px;text-align:center;" id="tocProgressLabel">0% read</div>
    </div>

    <!-- POLICY CONTENT -->
    <div class="policy-container">

      <!-- 1. Introduction -->
      <div class="policy-section" id="s1">
        <div class="ps-header">
          <div class="ps-icon"><i class="fas fa-info-circle"></i></div>
          <div>
            <div class="ps-num">Section 01</div>
            <div class="ps-title">Introduction</div>
          </div>
        </div>
        <div class="ps-body">
          <p>Bold Footprint Initiatives ("we," "our," or "us") is committed to protecting your privacy and ensuring the security of your personal information. This Privacy Policy explains how we collect, use, disclose, and safeguard your data when you use the BFI Scholar Portal and related services.</p>
          <div class="highlight-box"><strong>Our Commitment:</strong> We are dedicated to full transparency in how we handle your data and to ensuring compliance with applicable data protection legislation, including NDPR (Nigeria Data Protection Regulation) and international standards.</div>
          <p>By using the Scholar Portal, you agree to the practices described in this policy. If you do not agree, please discontinue use and contact us at the address below.</p>
        </div>
      </div>

      <!-- 2. Information We Collect -->
      <div class="policy-section" id="s2">
        <div class="ps-header">
          <div class="ps-icon"><i class="fas fa-database"></i></div>
          <div>
            <div class="ps-num">Section 02</div>
            <div class="ps-title">Information <em>We Collect</em></div>
          </div>
        </div>
        <div class="ps-body">
          <div class="ps-sub-title">Information You Provide Directly</div>
          <p>When you register for and use our services, we may collect:</p>
          <ul class="ps-list">
            <li>Full name, email address, and contact details</li>
            <li>Educational background, academic transcripts, and qualifications</li>
            <li>Profile photographs and identification documents</li>
            <li>Application documents, personal statements, and research proposals</li>
            <li>Communication preferences and notification settings</li>
          </ul>
          <div class="ps-sub-title">Information Collected Automatically</div>
          <p>We may automatically collect certain technical data when you use the portal:</p>
          <ul class="ps-list">
            <li>Device type, operating system, and browser information</li>
            <li>IP address and approximate geographic location</li>
            <li>Session data, login timestamps, and activity logs</li>
            <li>Usage patterns and feature interactions</li>
          </ul>
        </div>
      </div>

      <!-- 3. How We Use Your Information -->
      <div class="policy-section" id="s3">
        <div class="ps-header">
          <div class="ps-icon"><i class="fas fa-cogs"></i></div>
          <div>
            <div class="ps-num">Section 03</div>
            <div class="ps-title">How We <em>Use</em> Your Information</div>
          </div>
        </div>
        <div class="ps-body">
          <p>Your information enables us to deliver a meaningful scholarship support experience. Specifically, we use it to:</p>
          <ul class="ps-list">
            <li>Operate and maintain the Scholar Portal and all associated features</li>
            <li>Match you with appropriate mentors and scholarship opportunities</li>
            <li>Send timely updates about application deadlines and progress</li>
            <li>Analyse usage patterns to improve the platform</li>
            <li>Comply with legal and regulatory obligations</li>
            <li>Investigate fraud, security breaches, and policy violations</li>
          </ul>
        </div>
      </div>

      <!-- 4. Information Sharing -->
      <div class="policy-section" id="s4">
        <div class="ps-header">
          <div class="ps-icon"><i class="fas fa-share-alt"></i></div>
          <div>
            <div class="ps-num">Section 04</div>
            <div class="ps-title">Sharing &amp; <em>Disclosure</em></div>
          </div>
        </div>
        <div class="ps-body">
          <div class="ps-sub-title">With Your Explicit Consent</div>
          <p>We only share your profile and story when you explicitly agree — for example, when you opt into the Scholar Stories feature or agree to be connected with a mentor.</p>
          <div class="ps-sub-title">Service Providers</div>
          <p>We engage trusted third-party vendors to help operate the platform (e.g. hosting, email delivery). These providers are bound by strict confidentiality agreements and may only process data as we instruct.</p>
          <div class="ps-sub-title">Legal Requirements</div>
          <p>We may disclose information where required by law, court order, or to protect the rights and safety of our users and the public.</p>
          <div class="highlight-box"><strong>Important:</strong> We never sell, rent, or trade your personal data to any third party for commercial or marketing purposes. Your data is used exclusively to support your scholarship journey.</div>
        </div>
      </div>

      <!-- 5. Data Security -->
      <div class="policy-section" id="s5">
        <div class="ps-header">
          <div class="ps-icon"><i class="fas fa-shield-alt"></i></div>
          <div>
            <div class="ps-num">Section 05</div>
            <div class="ps-title">Data <em>Security</em></div>
          </div>
        </div>
        <div class="ps-body">
          <p>We employ industry-standard technical and organisational measures to protect your personal information, including:</p>
          <ul class="ps-list">
            <li>End-to-end encryption for data in transit (TLS) and at rest (AES-256)</li>
            <li>Secure password hashing using bcrypt with appropriate cost factors</li>
            <li>Role-based access controls limiting data access to authorised personnel only</li>
            <li>Regular security audits and penetration testing</li>
            <li>Documented incident response procedures in the event of a data breach</li>
          </ul>
          <p>While we take every reasonable precaution, no system is perfectly secure. We encourage you to use a strong, unique password and to report any suspicious activity to us immediately.</p>
        </div>
      </div>

      <!-- 6. Your Rights -->
      <div class="policy-section" id="s6">
        <div class="ps-header">
          <div class="ps-icon"><i class="fas fa-user-check"></i></div>
          <div>
            <div class="ps-num">Section 06</div>
            <div class="ps-title">Your <em>Rights &amp; Choices</em></div>
          </div>
        </div>
        <div class="ps-body">
          <div class="ps-sub-title">Access &amp; Portability</div>
          <p>You may download a complete copy of your personal data at any time via <a href="settings.php" style="color:var(--gold);">Account Settings → Privacy &amp; Data</a>.</p>
          <div class="ps-sub-title">Correction</div>
          <p>You can update your profile details — name, email, profile photo — directly from your <a href="profile.php" style="color:var(--gold);">My Profile</a> page.</p>
          <div class="ps-sub-title">Erasure</div>
          <p>You may request permanent deletion of your account and all associated data through Settings. Some information may be retained for legal or compliance purposes.</p>
          <div class="ps-sub-title">Communication Preferences</div>
          <p>You control whether you receive email and SMS communications, and at what frequency, from <a href="settings.php" style="color:var(--gold);">Settings → Notifications</a>.</p>
        </div>
      </div>

      <!-- 7. Cookies -->
      <div class="policy-section" id="s7">
        <div class="ps-header">
          <div class="ps-icon"><i class="fas fa-cookie-bite"></i></div>
          <div>
            <div class="ps-num">Section 07</div>
            <div class="ps-title">Cookies &amp; <em>Tracking</em></div>
          </div>
        </div>
        <div class="ps-body">
          <p>We use cookies and similar browser-storage technologies for the following purposes:</p>
          <ul class="ps-list">
            <li>Maintaining your authenticated session across pages</li>
            <li>Remembering your display and language preferences</li>
            <li>Analysing aggregate usage to improve the portal</li>
            <li>Protecting against cross-site request forgery (CSRF)</li>
          </ul>
          <p>You can clear or block cookies at any time via your browser settings. Note that disabling cookies may prevent you from remaining signed in.</p>
        </div>
      </div>

      <!-- 8. Data Retention -->
      <div class="policy-section" id="s8">
        <div class="ps-header">
          <div class="ps-icon"><i class="fas fa-clock"></i></div>
          <div>
            <div class="ps-num">Section 08</div>
            <div class="ps-title">Data <em>Retention</em></div>
          </div>
        </div>
        <div class="ps-body">
          <p>We retain your personal information only for as long as necessary to fulfil the purposes described in this policy. Retention periods are determined by:</p>
          <ul class="ps-list">
            <li>The duration of your active membership on the portal</li>
            <li>Statutory obligations under Nigerian and applicable international law</li>
            <li>Legitimate business needs such as dispute resolution and audit trails</li>
            <li>Supporting ongoing mentorship relationships where applicable</li>
          </ul>
          <p>Upon account deletion or upon request, we securely erase or anonymise your data within 30 days, except where retention is legally required.</p>
        </div>
      </div>

      <!-- 9. International Transfers -->
      <div class="policy-section" id="s9">
        <div class="ps-header">
          <div class="ps-icon"><i class="fas fa-globe-africa"></i></div>
          <div>
            <div class="ps-num">Section 09</div>
            <div class="ps-title">International <em>Transfers</em></div>
          </div>
        </div>
        <div class="ps-body">
          <p>As a platform connecting scholars with opportunities worldwide, data may be transferred to and processed in countries outside Nigeria. We ensure appropriate safeguards are in place for any cross-border transfer, including:</p>
          <ul class="ps-list">
            <li>Standard contractual clauses approved by relevant data protection authorities</li>
            <li>Adequacy decisions confirming equivalent protection levels in recipient countries</li>
            <li>Binding corporate rules and certification schemes where applicable</li>
          </ul>
        </div>
      </div>

      <!-- 10. Children's Privacy -->
      <div class="policy-section" id="s10">
        <div class="ps-header">
          <div class="ps-icon"><i class="fas fa-child"></i></div>
          <div>
            <div class="ps-num">Section 10</div>
            <div class="ps-title">Children's <em>Privacy</em></div>
          </div>
        </div>
        <div class="ps-body">
          <p>The BFI Scholar Portal is intended for users aged 16 and above. We do not knowingly collect or process personal data from children under 16. If you believe a minor has provided us with their information, please contact us immediately and we will delete it promptly.</p>
        </div>
      </div>

      <!-- 11. Contact -->
      <div class="policy-section" id="s11">
        <div class="ps-header">
          <div class="ps-icon"><i class="fas fa-envelope"></i></div>
          <div>
            <div class="ps-num">Section 11</div>
            <div class="ps-title">Contact <em>Us</em></div>
          </div>
        </div>
        <div class="ps-body">
          <p>If you have any questions, concerns, or requests regarding this Privacy Policy or how we handle your data, our team is here to help. Reach us through any of the channels below.</p>
          <div class="contact-grid">
            <div class="contact-card">
              <div class="contact-card-icon"><i class="fas fa-envelope"></i></div>
              <div>
                <div class="contact-card-label">Email</div>
                <div class="contact-card-val"><a href="mailto:info@bfinitiatives.com">info@bfinitiatives.com</a></div>
              </div>
            </div>
            <div class="contact-card">
              <div class="contact-card-icon"><i class="fas fa-phone"></i></div>
              <div>
                <div class="contact-card-label">Phone</div>
                <div class="contact-card-val">(+234) 816 501 1291</div>
              </div>
            </div>
            <div class="contact-card">
              <div class="contact-card-icon"><i class="fas fa-map-marker-alt"></i></div>
              <div>
                <div class="contact-card-label">Address</div>
                <div class="contact-card-val">Ibadan, Oyo State, Nigeria</div>
              </div>
            </div>
            <div class="contact-card">
              <div class="contact-card-icon"><i class="fas fa-globe"></i></div>
              <div>
                <div class="contact-card-label">Website</div>
                <div class="contact-card-val"><a href="https://www.bfinitiatives.com" target="_blank">www.bfinitiatives.com</a></div>
              </div>
            </div>
          </div>
          <p style="margin-top:20px;">We aim to respond to all privacy-related enquiries within <strong>5 business days</strong>.</p>
          <div class="highlight-box" style="margin-top:16px;"><strong>Policy Changes:</strong> We will notify you of any material changes to this policy via email and an in-app notice at least 14 days before changes take effect. Your continued use of the portal constitutes acceptance of the updated policy.</div>
        </div>
      </div>

    </div><!-- /policy-container -->
  </div><!-- /policy-layout -->

</main>

<!-- BACK TO TOP -->
<button id="backToTop" title="Back to top"><i class="fas fa-arrow-up"></i></button>

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
  <?php if ($isLoggedIn): ?>
  // Sidebar
  const sidebar=document.getElementById('sidebar');
  const overlay=document.getElementById('sidebarOverlay');
  const toggle=document.getElementById('mobileToggle');
  function openSidebar(){sidebar.classList.add('active');overlay.classList.add('active');document.body.style.overflow='hidden';}
  function closeSidebar(){sidebar.classList.remove('active');overlay.classList.remove('active');document.body.style.overflow='';}
  toggle.addEventListener('click',()=>sidebar.classList.contains('active')?closeSidebar():openSidebar());
  overlay.addEventListener('click',closeSidebar);
  window.addEventListener('resize',()=>{if(window.innerWidth>768)closeSidebar();});
  <?php endif; ?>

  // Reading progress bar
  const progressBar=document.getElementById('readingProgress');
  const tocFill=document.getElementById('tocProgress');
  const tocLbl=document.getElementById('tocProgressLabel');
  const backTop=document.getElementById('backToTop');

  function updateProgress(){
    const main=document.getElementById('mainContent');
    const scrollTop=window.scrollY||document.documentElement.scrollTop;
    const docH=document.documentElement.scrollHeight-window.innerHeight;
    const pct=docH>0?Math.round((scrollTop/docH)*100):0;
    progressBar.style.width=pct+'%';
    if(tocFill){tocFill.style.width=pct+'%';tocLbl.textContent=pct+'% read';}
    backTop.classList.toggle('visible',scrollTop>400);

    // Update TOC active link
    const sections=['s1','s2','s3','s4','s5','s6','s7','s8','s9','s10','s11'];
    let active='s1';
    sections.forEach(id=>{
      const el=document.getElementById(id);
      if(el&&el.getBoundingClientRect().top<120)active=id;
    });
    document.querySelectorAll('.toc-link').forEach(link=>{
      link.classList.toggle('active',link.getAttribute('href')==='#'+active);
    });
  }

  window.addEventListener('scroll',updateProgress,{passive:true});

  // Back to top
  backTop.addEventListener('click',()=>window.scrollTo({top:0,behavior:'smooth'}));

  // Smooth scroll for TOC links
  document.querySelectorAll('.toc-link').forEach(link=>{
    link.addEventListener('click',function(e){
      e.preventDefault();
      const target=document.querySelector(this.getAttribute('href'));
      if(target){const y=target.getBoundingClientRect().top+window.scrollY-80;window.scrollTo({top:y,behavior:'smooth'});}
    });
  });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>