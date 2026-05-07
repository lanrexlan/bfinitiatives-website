<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$first_name = '';
$last_name = '';
$profile_picture = null;
$scholarship_type = 'BFI Standard Scholarship';
$agreement_file = 'scholarship_agreements.pdf';
$notification_count = 0;

try {
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT first_name, last_name, profile_picture FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $first_name = $user['first_name'] ?? '';
        $last_name  = $user['last_name'] ?? '';
        $profile_picture = $user['profile_picture'] ?? null;
        $scholarship_type = $user['scholarship_type'] ?? 'BFI Standard Scholarship';
        if (!empty($user['agreement_file'])) { $agreement_file = $user['agreement_file']; }
    }
    $notif_stmt = $conn->prepare("SELECT id FROM notifications WHERE user_id = :user_id AND read_status = 0");
    $notif_stmt->execute([':user_id' => $_SESSION['user_id']]);
    $notification_count = $notif_stmt->rowCount();
} catch (Exception $e) { error_log("Agreement view error: " . $e->getMessage()); }

$agreement_path = './uploads/agreements/' . $agreement_file;
$agreement_exists = file_exists($agreement_path);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Scholarship Agreement | BFI Scholar Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    :root{--midnight:#080E1C;--navy:#0D1829;--navy-mid:#132038;--navy-light:#1C2F52;--gold:#C8A058;--gold-bright:#E0B96C;--cream:#FAF6EF;--cream-dark:#F2EAD8;--white:#FFFFFF;--text-primary:#0D1829;--text-secondary:#4A526A;--text-muted:#8A92A8;--border-light:#E8E4DA;--font-display:'Cormorant Garamond',Georgia,serif;--font-body:'Outfit',-apple-system,sans-serif;--ease:cubic-bezier(0.25,0.46,0.45,0.94);--transition:0.3s var(--ease);--shadow-md:0 8px 32px rgba(8,14,28,0.10);--sidebar-width:268px;--header-height:64px;--r-sm:8px;--r-md:14px;--r-lg:20px;--r-xl:28px;}
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:var(--font-body);background:#F2F4F8;color:var(--text-primary);line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden;}
    a{text-decoration:none;color:inherit;}
    .sidebar{position:fixed;left:0;top:0;width:var(--sidebar-width);height:100vh;background:var(--navy);z-index:200;display:flex;flex-direction:column;overflow:hidden;transition:transform var(--transition);}
    .sidebar-top{padding:28px 24px 20px;border-bottom:1px solid rgba(255,255,255,0.06);}
    .sidebar-logo{display:flex;align-items:center;gap:12px;margin-bottom:32px;}
    .sidebar-logomark{width:34px;height:34px;background:var(--gold);border-radius:7px;display:flex;align-items:center;justify-content:center;}
    .sidebar-logomark svg{width:20px;height:20px;}
    .sidebar-logo-text{font-family:var(--font-display);font-size:15px;font-weight:500;color:var(--white);line-height:1.2;}
    .sidebar-logo-text span{display:block;font-family:var(--font-body);font-size:9px;letter-spacing:2px;text-transform:uppercase;color:rgba(200,160,88,0.6);}
    .sidebar-user{display:flex;align-items:center;gap:12px;}
    .sidebar-avatar{width:40px;height:40px;border-radius:50%;background:var(--navy-light);border:2px solid rgba(200,160,88,0.3);overflow:hidden;display:flex;align-items:center;justify-content:center;}
    .sidebar-avatar img{width:100%;height:100%;object-fit:cover;}
    .sidebar-avatar-init{font-family:var(--font-display);font-size:16px;color:var(--gold-bright);}
    .sidebar-user-name{font-size:13.5px;font-weight:500;color:var(--white);}
    .sidebar-user-role{font-size:10.5px;color:rgba(255,255,255,0.35);}
    .sidebar-nav{flex:1;padding:20px 12px;overflow-y:auto;}
    .nav-section-label{font-size:9.5px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,0.2);padding:0 12px;margin:16px 0 8px;}
    .nav-item{margin-bottom:2px;}
    .nav-link{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:var(--r-sm);font-size:13.5px;color:rgba(255,255,255,0.6);transition:var(--transition);position:relative;}
    .nav-link:hover{background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.9);}
    .nav-link.active{background:rgba(200,160,88,0.12);color:var(--gold-bright);}
    .nav-link.active::before{content:'';position:absolute;left:0;top:6px;bottom:6px;width:2.5px;background:var(--gold);border-radius:2px;}
    .nav-link i{width:18px;text-align:center;font-size:14px;}
    .nav-badge{margin-left:auto;background:rgba(239,68,68,0.2);color:#F87171;font-size:10px;font-weight:600;padding:1px 7px;border-radius:10px;}
    .sidebar-bottom{padding:16px 12px;border-top:1px solid rgba(255,255,255,0.06);}
    .header{position:fixed;top:0;left:var(--sidebar-width);right:0;height:var(--header-height);background:var(--white);border-bottom:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;padding:0 28px;z-index:100;}
    .header-left{display:flex;align-items:center;gap:16px;}
    .mobile-toggle{display:none;background:none;border:none;cursor:pointer;padding:6px;color:var(--text-secondary);font-size:18px;}
    .breadcrumb{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-muted);}
    .breadcrumb a{color:var(--text-muted);}.breadcrumb a:hover{color:var(--gold);}
    .breadcrumb-sep{font-size:9px;}
    .breadcrumb-current{color:var(--text-primary);font-weight:500;}
    .header-right{display:flex;align-items:center;gap:14px;}
    .header-icon-btn{width:36px;height:36px;border-radius:var(--r-sm);background:var(--cream);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-secondary);font-size:14px;transition:var(--transition);position:relative;}
    .header-icon-btn:hover{background:var(--cream-dark);color:var(--navy);}
    .notif-dot{position:absolute;top:6px;right:6px;width:7px;height:7px;border-radius:50%;background:#EF4444;border:2px solid var(--white);}
    .header-avatar{width:36px;height:36px;border-radius:50%;background:var(--navy-light);overflow:hidden;border:2px solid var(--border-light);cursor:pointer;display:flex;align-items:center;justify-content:center;}
    .header-avatar img{width:100%;height:100%;object-fit:cover;}
    .header-avatar-init{font-family:var(--font-display);font-size:14px;color:var(--gold-bright);}
    .main{margin-left:var(--sidebar-width);margin-top:var(--header-height);padding:28px;min-height:calc(100vh - var(--header-height));}
    .page-header{background:var(--navy);border-radius:var(--r-xl);padding:28px 32px;margin-bottom:24px;position:relative;overflow:hidden;}
    .page-header::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:32px 32px;}
    .page-header::after{content:'';position:absolute;top:-60px;right:-60px;width:240px;height:240px;background:radial-gradient(circle,rgba(200,160,88,0.1) 0%,transparent 65%);}
    .page-header-inner{position:relative;z-index:1;}
    .page-header-eyebrow{font-size:10px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:rgba(200,160,88,0.7);margin-bottom:6px;}
    .page-header-title{font-family:var(--font-display);font-size:clamp(22px,3vw,30px);font-weight:500;color:var(--white);line-height:1.15;margin-bottom:4px;}
    .page-header-title em{font-style:italic;color:var(--gold-bright);}
    .page-header-sub{font-size:13.5px;font-weight:300;color:rgba(255,255,255,0.5);}
    /* AGREEMENT */
    .agreement-layout{display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start;}
    .agreement-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);overflow:hidden;}
    .agreement-card-header{background:var(--navy);padding:20px 24px;display:flex;align-items:center;gap:14px;}
    .agreement-card-icon{width:44px;height:44px;background:rgba(200,160,88,0.15);border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:20px;color:var(--gold-bright);flex-shrink:0;}
    .agreement-card-title{font-family:var(--font-display);font-size:20px;font-weight:500;color:var(--white);}
    .agreement-card-sub{font-size:12.5px;color:rgba(255,255,255,0.45);margin-top:2px;}
    .agreement-body{padding:28px;}
    .agreement-meta-row{display:flex;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid var(--border-light);}
    .agreement-meta-row:last-of-type{border-bottom:none;margin-bottom:20px;}
    .agreement-meta-icon{width:36px;height:36px;background:var(--cream);border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:14px;color:var(--gold);flex-shrink:0;}
    .agreement-meta-label{font-size:11.5px;color:var(--text-muted);}
    .agreement-meta-value{font-size:14px;font-weight:500;color:var(--navy);}
    .agreement-placeholder{background:var(--cream);border-radius:var(--r-md);padding:40px 24px;text-align:center;margin-top:4px;}
    .agreement-placeholder-icon{width:64px;height:64px;background:var(--white);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:26px;color:var(--text-muted);}
    .agreement-placeholder-title{font-family:var(--font-display);font-size:20px;color:var(--navy);margin-bottom:8px;}
    .agreement-placeholder-sub{font-size:13.5px;color:var(--text-muted);line-height:1.7;max-width:360px;margin:0 auto;}
    /* PDF embed */
    .pdf-embed{width:100%;height:600px;border:none;border-radius:var(--r-md);}
    /* SIDEBAR CARD */
    .side-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);overflow:hidden;margin-bottom:16px;}
    .side-card-header{padding:16px 20px;border-bottom:1px solid var(--border-light);}
    .side-card-title{font-family:var(--font-display);font-size:17px;font-weight:500;color:var(--navy);}
    .side-card-body{padding:16px 20px;}
    .btn-download{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:13px;background:var(--navy);color:var(--white);font-family:var(--font-body);font-size:13.5px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);margin-bottom:10px;}
    .btn-download:hover{background:var(--navy-light);}
    .btn-back{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:13px;background:var(--cream);color:var(--text-primary);font-family:var(--font-body);font-size:13.5px;font-weight:500;border:1px solid var(--border-light);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-back:hover{background:var(--cream-dark);}
    .info-row{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border-light);font-size:13px;}
    .info-row:last-child{border-bottom:none;}
    .info-icon{width:28px;height:28px;background:var(--cream);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12px;color:var(--gold);flex-shrink:0;}
    .info-label{font-size:11px;color:var(--text-muted);}
    .info-value{font-size:13px;font-weight:500;color:var(--navy);}
    .portal-footer{margin-left:var(--sidebar-width);background:var(--white);padding:14px 28px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;}
    .footer-copy{font-size:12px;color:var(--text-muted);}
    .footer-links{display:flex;gap:18px;}
    .footer-links a{font-size:12px;color:var(--text-muted);transition:color var(--transition);}.footer-links a:hover{color:var(--gold);}
    .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:150;}
    .sidebar-overlay.active{display:block;}
    @media(max-width:1000px){.agreement-layout{grid-template-columns:1fr}}
    @media(max-width:768px){.sidebar{transform:translateX(-100%)}.sidebar.active{transform:translateX(0)}.header{left:0}.main,.portal-footer{margin-left:0}.mobile-toggle{display:flex}}
  </style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-top">
    <div class="sidebar-logo">
      <div class="sidebar-logomark"><svg viewBox="0 0 22 22" fill="none"><path d="M4 18L11 4L18 18" stroke="#080E1C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 13H15" stroke="#080E1C" stroke-width="1.8" stroke-linecap="round"/></svg></div>
      <div class="sidebar-logo-text">Bold Footprint<span>Scholar Portal</span></div>
    </div>
    <div class="sidebar-user">
      <div class="sidebar-avatar"><?php if ($profile_picture): ?><img src="<?php echo htmlspecialchars('./uploads/profile_pictures/'.$profile_picture); ?>" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"><div class="sidebar-avatar-init" style="display:none;"><?php echo strtoupper(substr($first_name,0,1)); ?></div><?php else: ?><div class="sidebar-avatar-init"><?php echo strtoupper(substr($first_name,0,1)); ?></div><?php endif; ?></div>
      <div><div class="sidebar-user-name"><?php echo htmlspecialchars($first_name.' '.$last_name); ?></div><div class="sidebar-user-role">BFI Scholar</div></div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>
    <div class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a></div>
    <div class="nav-item"><a href="profile.php" class="nav-link"><i class="fas fa-user"></i> My Profile</a></div>
    <div class="nav-item"><a href="#" class="nav-link"><i class="fas fa-route"></i> My Journey<?php if($notification_count>0): ?><span class="nav-badge"><?php echo $notification_count; ?></span><?php endif; ?></a></div>
    <div class="nav-section-label">Resources</div>
    <div class="nav-item"><a href="documents.php" class="nav-link"><i class="fas fa-file-alt"></i> My Documents</a></div>
    <div class="nav-item"><a href="scholarships.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Scholarships</a></div>
    <div class="nav-item"><a href="mentors.php" class="nav-link"><i class="fas fa-users"></i> My Mentor</a></div>
    <div class="nav-item"><a href="application-help.php" class="nav-link"><i class="fas fa-question-circle"></i> Application Help</a></div>
    <div class="nav-item"><a href="resources.php" class="nav-link"><i class="fas fa-book"></i> Resources</a></div>
    <div class="nav-section-label">Account</div>
    <div class="nav-item"><a href="view-agreement.php" class="nav-link active"><i class="fas fa-file-contract"></i> My Agreement</a></div>
    <div class="nav-item"><a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a></div>
  </nav>
  <div class="sidebar-bottom"><a href="logout.php" class="nav-link" style="color:rgba(239,68,68,0.7);"><i class="fas fa-sign-out-alt"></i> Log Out</a></div>
</aside>
<header class="header">
  <div class="header-left">
    <button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
    <div class="breadcrumb"><a href="dashboard.php">Dashboard</a><span class="breadcrumb-sep"><i class="fas fa-chevron-right" style="font-size:9px;"></i></span><span class="breadcrumb-current">Scholarship Agreement</span></div>
  </div>
  <div class="header-right">
    <button class="header-icon-btn"><i class="fas fa-bell"></i><?php if($notification_count>0): ?><div class="notif-dot"></div><?php endif; ?></button>
    <a href="profile.php"><div class="header-avatar"><?php if($profile_picture): ?><img src="<?php echo htmlspecialchars('./uploads/profile_pictures/'.$profile_picture); ?>" alt=""><div class="header-avatar-init" style="display:none;"><?php echo strtoupper(substr($first_name,0,1)); ?></div><?php else: ?><div class="header-avatar-init"><?php echo strtoupper(substr($first_name,0,1)); ?></div><?php endif; ?></div></a>
  </div>
</header>
<main class="main">
  <div class="page-header">
    <div class="page-header-inner">
      <div class="page-header-eyebrow">Scholar Portal</div>
      <div class="page-header-title">Scholarship <em>Agreement</em></div>
      <div class="page-header-sub">View and download your official scholarship agreement document</div>
    </div>
  </div>
  <div class="agreement-layout">
    <div class="agreement-card">
      <div class="agreement-card-header">
        <div class="agreement-card-icon"><i class="fas fa-file-contract"></i></div>
        <div>
          <div class="agreement-card-title">Scholarship Agreement</div>
          <div class="agreement-card-sub">For: <?php echo htmlspecialchars($first_name.' '.$last_name); ?></div>
        </div>
      </div>
      <div class="agreement-body">
        <div class="agreement-meta-row">
          <div class="agreement-meta-icon"><i class="fas fa-graduation-cap"></i></div>
          <div><div class="agreement-meta-label">Scholarship Type</div><div class="agreement-meta-value"><?php echo htmlspecialchars($scholarship_type); ?></div></div>
        </div>
        <div class="agreement-meta-row">
          <div class="agreement-meta-icon"><i class="fas fa-user"></i></div>
          <div><div class="agreement-meta-label">Scholar Name</div><div class="agreement-meta-value"><?php echo htmlspecialchars($first_name.' '.$last_name); ?></div></div>
        </div>
        <?php if ($agreement_exists): ?>
        <iframe class="pdf-embed" src="<?php echo htmlspecialchars($agreement_path); ?>" title="Scholarship Agreement"></iframe>
        <?php else: ?>
        <div class="agreement-placeholder">
          <div class="agreement-placeholder-icon"><i class="fas fa-file-pdf"></i></div>
          <div class="agreement-placeholder-title">Agreement Pending Upload</div>
          <p class="agreement-placeholder-sub">This is a placeholder for your scholarship agreement. Your programme coordinator will upload the official document soon. You'll receive a notification when it's ready.</p>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <div>
      <div class="side-card">
        <div class="side-card-header"><div class="side-card-title">Actions</div></div>
        <div class="side-card-body">
          <?php if ($agreement_exists): ?>
          <a href="<?php echo htmlspecialchars($agreement_path); ?>" download class="btn-download"><i class="fas fa-download"></i> Download Agreement</a>
          <?php else: ?>
          <button class="btn-download" disabled style="opacity:0.5;cursor:not-allowed;"><i class="fas fa-download"></i> Not Yet Available</button>
          <?php endif; ?>
          <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
      </div>
      <div class="side-card">
        <div class="side-card-header"><div class="side-card-title">Agreement Details</div></div>
        <div class="side-card-body">
          <div class="info-row"><div class="info-icon"><i class="fas fa-tag"></i></div><div><div class="info-label">Type</div><div class="info-value"><?php echo htmlspecialchars($scholarship_type); ?></div></div></div>
          <div class="info-row"><div class="info-icon"><i class="fas fa-shield-alt"></i></div><div><div class="info-label">Status</div><div class="info-value" style="color:<?php echo $agreement_exists?'#059669':'var(--text-muted)'; ?>"><?php echo $agreement_exists?'Available':'Pending Upload'; ?></div></div></div>
          <div class="info-row"><div class="info-icon"><i class="fas fa-building"></i></div><div><div class="info-label">Issued By</div><div class="info-value">Bold Footprint Initiatives</div></div></div>
        </div>
      </div>
    </div>
  </div>
</main>
<footer class="portal-footer"><div class="footer-copy">&copy; 2026 Bold Footprint Initiatives.</div><div class="footer-links"><a href="/index.html">Main Site</a><a href="/contact.html">Contact</a></div></footer>
<script>
  const sidebar=document.getElementById('sidebar'),overlay=document.getElementById('sidebarOverlay'),toggle=document.getElementById('mobileToggle');
  function openS(){sidebar.classList.add('active');overlay.classList.add('active');document.body.style.overflow='hidden';}
  function closeS(){sidebar.classList.remove('active');overlay.classList.remove('active');document.body.style.overflow='';}
  if(toggle)toggle.addEventListener('click',()=>sidebar.classList.contains('active')?closeS():openS());
  overlay.addEventListener('click',closeS);
</script>
</body></html>