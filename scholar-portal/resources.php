<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$first_name = ''; $last_name = ''; $email = ''; $profile_picture = null; $notification_count = 0;
try {
    $db = new Database(); $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT first_name, last_name, email, profile_picture FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) { $first_name=$user['first_name']; $last_name=$user['last_name']??''; $email=$user['email']??''; $profile_picture=$user['profile_picture']??null; }
    $notif_stmt = $conn->prepare("SELECT id FROM notifications WHERE user_id = :user_id AND read_status = 0");
    $notif_stmt->execute([':user_id' => $_SESSION['user_id']]);
    $notification_count = $notif_stmt->rowCount();
} catch (Exception $e) { error_log("Resource Library error: " . $e->getMessage()); }

$host='localhost'; $dbname='bfinitia_resource_library'; $user_db='bfinitia'; $password='Akande_Olanrewaju123@'; $port='5432';
$videoStats=[]; $documentStats=[]; $recentVideos=[]; $recentDocuments=[];
$total_videos=0; $total_documents=0; $total_categories=0;

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$user_db;password=$password";
    $pdo = new PDO($dsn); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $videoStats    = $pdo->query("SELECT category, COUNT(*) as count FROM videos GROUP BY category")->fetchAll(PDO::FETCH_KEY_PAIR);
    $documentStats = $pdo->query("SELECT category, COUNT(*) as count FROM documents GROUP BY category")->fetchAll(PDO::FETCH_KEY_PAIR);
    $recentVideos  = $pdo->query("SELECT * FROM videos ORDER BY id DESC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
    $recentDocuments = $pdo->query("SELECT * FROM documents ORDER BY upload_date DESC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
    $total_videos    = array_sum($videoStats);
    $total_documents = array_sum($documentStats);
    $total_categories = count(array_unique(array_merge(array_keys($videoStats), array_keys($documentStats))));
} catch(PDOException $e) { error_log("Resource Library DB error: " . $e->getMessage()); }

function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = ['pdf'=>'fas fa-file-pdf','doc'=>'fas fa-file-word','docx'=>'fas fa-file-word','xls'=>'fas fa-file-excel','xlsx'=>'fas fa-file-excel','ppt'=>'fas fa-file-powerpoint','pptx'=>'fas fa-file-powerpoint','txt'=>'fas fa-file-alt','rtf'=>'fas fa-file-alt'];
    return $icons[$ext] ?? 'fas fa-file';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resource Library | BFI Scholar Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    :root{--midnight:#080E1C;--navy:#0D1829;--navy-mid:#132038;--navy-light:#1C2F52;--gold:#C8A058;--gold-bright:#E0B96C;--cream:#FAF6EF;--cream-dark:#F2EAD8;--white:#FFFFFF;--text-primary:#0D1829;--text-secondary:#4A526A;--text-muted:#8A92A8;--border-light:#E8E4DA;--font-display:'Cormorant Garamond',Georgia,serif;--font-body:'Outfit',-apple-system,sans-serif;--ease:cubic-bezier(0.25,0.46,0.45,0.94);--transition:0.3s var(--ease);--shadow-md:0 8px 32px rgba(8,14,28,0.10);--shadow-lg:0 20px 60px rgba(8,14,28,0.14);--sidebar-width:268px;--header-height:64px;--r-sm:8px;--r-md:14px;--r-lg:20px;--r-xl:28px;}
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:var(--font-body);background:#F2F4F8;color:var(--text-primary);line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden;}
    a{text-decoration:none;color:inherit;}img{max-width:100%;display:block;}
    /* ---- SIDEBAR ---- */
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
    /* ---- HEADER ---- */
    .header{position:fixed;top:0;left:var(--sidebar-width);right:0;height:var(--header-height);background:var(--white);border-bottom:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;padding:0 28px;z-index:100;}
    .header-left{display:flex;align-items:center;gap:16px;}
    .mobile-toggle{display:none;background:none;border:none;cursor:pointer;padding:6px;color:var(--text-secondary);font-size:18px;}
    .breadcrumb{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-muted);}
    .breadcrumb a{color:var(--text-muted);transition:color var(--transition);}.breadcrumb a:hover{color:var(--gold);}
    .breadcrumb-sep{font-size:9px;}.breadcrumb-current{color:var(--text-primary);font-weight:500;}
    .header-right{display:flex;align-items:center;gap:14px;}
    .header-icon-btn{width:36px;height:36px;border-radius:var(--r-sm);background:var(--cream);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-secondary);font-size:14px;transition:var(--transition);position:relative;}
    .header-icon-btn:hover{background:var(--cream-dark);color:var(--navy);}
    .notif-dot{position:absolute;top:6px;right:6px;width:7px;height:7px;border-radius:50%;background:#EF4444;border:2px solid var(--white);}
    .header-avatar{width:36px;height:36px;border-radius:50%;background:var(--navy-light);overflow:hidden;border:2px solid var(--border-light);cursor:pointer;display:flex;align-items:center;justify-content:center;}
    .header-avatar img{width:100%;height:100%;object-fit:cover;}
    .header-avatar-init{font-family:var(--font-display);font-size:14px;color:var(--gold-bright);}
    /* ---- MAIN ---- */
    .main{margin-left:var(--sidebar-width);margin-top:var(--header-height);padding:28px;min-height:calc(100vh - var(--header-height));}
    .page-header{background:var(--navy);border-radius:var(--r-xl);padding:28px 32px;margin-bottom:24px;position:relative;overflow:hidden;}
    .page-header::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:32px 32px;}
    .page-header::after{content:'';position:absolute;top:-60px;right:-60px;width:240px;height:240px;background:radial-gradient(circle,rgba(200,160,88,0.1) 0%,transparent 65%);}
    .ph-inner{position:relative;z-index:1;}
    .ph-eyebrow{font-size:10px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:rgba(200,160,88,0.7);margin-bottom:6px;}
    .ph-title{font-family:var(--font-display);font-size:clamp(22px,3vw,30px);font-weight:500;color:var(--white);margin-bottom:4px;}
    .ph-title em{font-style:italic;color:var(--gold-bright);}
    .ph-sub{font-size:13.5px;font-weight:300;color:rgba(255,255,255,0.5);}
    /* STATS */
    .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
    .stat-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:20px 24px;display:flex;align-items:center;gap:16px;transition:var(--transition);}
    .stat-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-md);}
    .stat-icon{width:48px;height:48px;background:var(--navy);border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:18px;color:var(--gold-bright);flex-shrink:0;}
    .stat-num{font-family:var(--font-display);font-size:28px;font-weight:500;color:var(--navy);line-height:1;}
    .stat-label{font-size:12px;color:var(--text-muted);margin-top:2px;}
    /* TYPE CARDS */
    .section-label{font-size:10px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);margin-bottom:14px;}
    .type-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px;}
    .type-card{background:var(--white);border-radius:var(--r-xl);border:1px solid var(--border-light);overflow:hidden;transition:var(--transition);}
    .type-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-lg);border-color:transparent;}
    .type-card-header{background:var(--navy);padding:24px 28px;position:relative;overflow:hidden;}
    .type-card-header::after{content:'';position:absolute;top:-40px;right:-40px;width:160px;height:160px;background:radial-gradient(circle,rgba(200,160,88,0.1) 0%,transparent 65%);}
    .tc-icon{width:52px;height:52px;background:rgba(200,160,88,0.12);border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;font-size:22px;color:var(--gold-bright);margin-bottom:16px;}
    .tc-title{font-family:var(--font-display);font-size:22px;font-weight:500;color:var(--white);margin-bottom:4px;}
    .tc-title em{font-style:italic;color:var(--gold-bright);}
    .tc-sub{font-size:13px;color:rgba(255,255,255,0.45);}
    .type-card-body{padding:24px 28px;}
    .type-tag-row{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;}
    .type-tag{font-size:11px;padding:3px 10px;border-radius:20px;background:var(--cream);color:var(--text-secondary);border:1px solid var(--border-light);}
    .type-desc{font-size:13.5px;font-weight:300;color:var(--text-secondary);line-height:1.7;margin-bottom:20px;}
    .btn-explore{display:inline-flex;align-items:center;gap:8px;padding:11px 22px;background:var(--navy);color:var(--white);font-family:var(--font-body);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-explore:hover{background:var(--navy-light);}
    /* RECENT */
    .recent-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
    .recent-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);overflow:hidden;}
    .recent-card-header{padding:18px 20px;border-bottom:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;}
    .recent-card-title{font-family:var(--font-display);font-size:17px;font-weight:500;color:var(--navy);}
    .recent-card-title em{font-style:italic;color:var(--gold);}
    .recent-view-all{font-size:12px;color:var(--gold);display:flex;align-items:center;gap:5px;transition:var(--transition);}.recent-view-all:hover{gap:8px;}
    .recent-item{display:flex;align-items:center;gap:14px;padding:14px 20px;border-bottom:1px solid var(--border-light);transition:var(--transition);}
    .recent-item:last-child{border-bottom:none;}
    .recent-item:hover{background:var(--cream);}
    .ri-icon{width:40px;height:40px;background:var(--cream);border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:16px;color:var(--gold);flex-shrink:0;}
    .ri-title{font-size:13.5px;font-weight:500;color:var(--navy);margin-bottom:2px;}
    .ri-meta{font-size:11.5px;color:var(--text-muted);}
    .ri-action{margin-left:auto;font-size:11.5px;font-weight:500;color:var(--gold);background:rgba(200,160,88,0.08);border:1px solid rgba(200,160,88,0.2);padding:4px 10px;border-radius:20px;white-space:nowrap;transition:var(--transition);flex-shrink:0;}
    .ri-action:hover{background:var(--gold);color:var(--midnight);}
    .empty-mini{padding:32px 20px;text-align:center;font-size:13px;color:var(--text-muted);}
    /* FOOTER */
    .portal-footer{margin-left:var(--sidebar-width);background:var(--white);padding:14px 28px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;}
    .footer-copy{font-size:12px;color:var(--text-muted);}
    .footer-links{display:flex;gap:18px;}
    .footer-links a{font-size:12px;color:var(--text-muted);transition:color var(--transition);}.footer-links a:hover{color:var(--gold);}
    .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:150;backdrop-filter:blur(4px);}
    .sidebar-overlay.active{display:block;}
    @media(max-width:1024px){.stats-grid{grid-template-columns:repeat(2,1fr)}.type-grid,.recent-grid{grid-template-columns:1fr}}
    @media(max-width:768px){.sidebar{transform:translateX(-100%)}.sidebar.active{transform:translateX(0)}.header{left:0}.main,.portal-footer{margin-left:0}.mobile-toggle{display:flex}}
    @media(max-width:480px){.main{padding:16px}}
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
      <div class="sidebar-avatar">
        <?php if($profile_picture): ?><img src="<?php echo htmlspecialchars('./uploads/profile_pictures/'.$profile_picture); ?>" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"><div class="sidebar-avatar-init" style="display:none;"><?php echo strtoupper(substr($first_name,0,1)); ?></div><?php else: ?><div class="sidebar-avatar-init"><?php echo strtoupper(substr($first_name,0,1)); ?></div><?php endif; ?>
      </div>
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
    <div class="nav-item"><a href="resources.php" class="nav-link active"><i class="fas fa-book"></i> Resources</a></div>
    <div class="nav-section-label">Account</div>
    <div class="nav-item"><a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a></div>
  </nav>
  <div class="sidebar-bottom"><a href="logout.php" class="nav-link" style="color:rgba(239,68,68,0.7);"><i class="fas fa-sign-out-alt"></i> Log Out</a></div>
</aside>

<header class="header">
  <div class="header-left">
    <button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
    <div class="breadcrumb"><a href="dashboard.php">Dashboard</a><span class="breadcrumb-sep"><i class="fas fa-chevron-right" style="font-size:9px;"></i></span><span class="breadcrumb-current">Resource Library</span></div>
  </div>
  <div class="header-right">
    <button class="header-icon-btn"><i class="fas fa-bell"></i><?php if($notification_count>0): ?><div class="notif-dot"></div><?php endif; ?></button>
    <a href="profile.php"><div class="header-avatar"><?php if($profile_picture): ?><img src="<?php echo htmlspecialchars('./uploads/profile_pictures/'.$profile_picture); ?>" alt=""><div class="header-avatar-init" style="display:none;"><?php echo strtoupper(substr($first_name,0,1)); ?></div><?php else: ?><div class="header-avatar-init"><?php echo strtoupper(substr($first_name,0,1)); ?></div><?php endif; ?></div></a>
  </div>
</header>

<main class="main">
  <div class="page-header">
    <div class="ph-inner">
      <div class="ph-eyebrow">Scholar Portal</div>
      <div class="ph-title">Resource <em>Library Hub</em></div>
      <div class="ph-sub">Your comprehensive gateway to scholarship resources — videos, documents, templates and guides</div>
    </div>
  </div>

  <!-- STATS -->
  <div class="stats-grid">
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-video"></i></div><div><div class="stat-num"><?php echo $total_videos ?: '—'; ?></div><div class="stat-label">Total Videos</div></div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-file-alt"></i></div><div><div class="stat-num"><?php echo $total_documents ?: '—'; ?></div><div class="stat-label">Total Documents</div></div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-folder-open"></i></div><div><div class="stat-num"><?php echo ($total_categories ?: '—'); ?></div><div class="stat-label">Categories</div></div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-star"></i></div><div><div class="stat-num"><?php echo (($total_videos+$total_documents) ?: '—'); ?></div><div class="stat-label">Resources Available</div></div></div>
  </div>

  <!-- TYPE CARDS -->
  <div class="section-label">Explore Resource Types</div>
  <div class="type-grid">
    <div class="type-card">
      <div class="type-card-header">
        <div class="tc-icon"><i class="fas fa-play-circle"></i></div>
        <div class="tc-title">Video <em>Resources</em></div>
        <div class="tc-sub">Interactive learning through visual content</div>
      </div>
      <div class="type-card-body">
        <div class="type-tag-row">
          <span class="type-tag">Application Tips <?php echo isset($videoStats['application-tips']) ? '('.$videoStats['application-tips'].')' : ''; ?></span>
          <span class="type-tag">Info Sessions <?php echo isset($videoStats['info-sessions']) ? '('.$videoStats['info-sessions'].')' : ''; ?></span>
          <span class="type-tag">Interview Prep <?php echo isset($videoStats['interview-prep']) ? '('.$videoStats['interview-prep'].')' : ''; ?></span>
        </div>
        <p class="type-desc">Watch our curated collection of educational videos covering application strategies, interview preparation, and informational sessions from successful scholarship recipients and mentors. All videos are playable inline and downloadable.</p>
        <a href="video-resources.php" class="btn-explore"><i class="fas fa-play"></i> Explore Videos</a>
      </div>
    </div>
    <div class="type-card">
      <div class="type-card-header">
        <div class="tc-icon"><i class="fas fa-file-alt"></i></div>
        <div class="tc-title">Document <em>Library</em></div>
        <div class="tc-sub">Templates, samples, and comprehensive guides</div>
      </div>
      <div class="type-card-body">
        <div class="type-tag-row">
          <span class="type-tag">SOP Samples <?php echo isset($documentStats['sop-samples']) ? '('.$documentStats['sop-samples'].')' : ''; ?></span>
          <span class="type-tag">GRE Materials</span>
          <span class="type-tag">CV Templates</span>
          <span class="type-tag">Essay Samples</span>
          <span class="type-tag">Application Guides</span>
        </div>
        <p class="type-desc">Download high-quality document templates, sample essays, statement of purpose examples, CV templates, recommendation letter guides, and comprehensive application guides. All files are viewable in-browser and downloadable.</p>
        <a href="document-resources.php" class="btn-explore"><i class="fas fa-download"></i> Explore Documents</a>
      </div>
    </div>
  </div>

  <!-- RECENTLY ADDED -->
  <div class="section-label">Recently Added</div>
  <div class="recent-grid">
    <div class="recent-card">
      <div class="recent-card-header">
        <div class="recent-card-title">Recent <em>Videos</em></div>
        <a href="video-resources.php" class="recent-view-all">View all <i class="fas fa-arrow-right" style="font-size:10px;"></i></a>
      </div>
      <?php if (!empty($recentVideos)): ?>
        <?php foreach ($recentVideos as $v): ?>
        <div class="recent-item">
          <div class="ri-icon"><i class="fas fa-play-circle"></i></div>
          <div><div class="ri-title"><?php echo htmlspecialchars($v['title'] ?? 'Video Resource'); ?></div><div class="ri-meta"><?php echo htmlspecialchars($v['duration'] ?? ''); ?><?php if(!empty($v['duration']) && !empty($v['category'])): ?> · <?php endif; ?><?php echo htmlspecialchars($v['category'] ?? ''); ?></div></div>
          <a href="video-resources.php" class="ri-action">Watch</a>
        </div>
        <?php endforeach; ?>
      <?php else: ?><div class="empty-mini">No videos yet — check back soon.</div><?php endif; ?>
    </div>
    <div class="recent-card">
      <div class="recent-card-header">
        <div class="recent-card-title">Recent <em>Documents</em></div>
        <a href="document-resources.php" class="recent-view-all">View all <i class="fas fa-arrow-right" style="font-size:10px;"></i></a>
      </div>
      <?php if (!empty($recentDocuments)): ?>
        <?php foreach ($recentDocuments as $d): ?>
        <div class="recent-item">
          <div class="ri-icon"><i class="<?php echo getFileIcon($d['filename'] ?? ''); ?>"></i></div>
          <div><div class="ri-title"><?php echo htmlspecialchars($d['title'] ?? 'Document'); ?></div><div class="ri-meta"><?php echo htmlspecialchars($d['category'] ?? ''); ?></div></div>
          <a href="document-resources.php" class="ri-action">View</a>
        </div>
        <?php endforeach; ?>
      <?php else: ?><div class="empty-mini">No documents yet — check back soon.</div><?php endif; ?>
    </div>
  </div>
</main>

<footer class="portal-footer">
  <div class="footer-copy">&copy; 2026 Bold Footprint Initiatives. All rights reserved.</div>
  <div class="footer-links"><a href="/index.html">Main Site</a><a href="/about.html">About</a><a href="/programs.html">Programs</a><a href="/contact.html">Contact</a></div>
</footer>

<script>
  const sidebar=document.getElementById('sidebar'),overlay=document.getElementById('sidebarOverlay'),toggle=document.getElementById('mobileToggle');
  function openS(){sidebar.classList.add('active');overlay.classList.add('active');document.body.style.overflow='hidden';}
  function closeS(){sidebar.classList.remove('active');overlay.classList.remove('active');document.body.style.overflow='';}
  if(toggle) toggle.addEventListener('click',()=>sidebar.classList.contains('active')?closeS():openS());
  overlay.addEventListener('click',closeS);
  window.addEventListener('resize',()=>{if(window.innerWidth>768)closeS();});
</script>
</body>
</html>