<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$first_name=''; $last_name=''; $email=''; $profile_picture=null; $notification_count=0;
try {
    $db=new Database(); $conn=$db->getConnection();
    $stmt=$conn->prepare("SELECT first_name, last_name, email, profile_picture FROM users WHERE id = :user_id");
    $stmt->execute([':user_id'=>$_SESSION['user_id']]);
    $user=$stmt->fetch(PDO::FETCH_ASSOC);
    if($user){$first_name=$user['first_name'];$last_name=$user['last_name']??'';$email=$user['email']??'';$profile_picture=$user['profile_picture']??null;}
    $notif_stmt=$conn->prepare("SELECT id FROM notifications WHERE user_id = :user_id AND read_status = 0");
    $notif_stmt->execute([':user_id'=>$_SESSION['user_id']]);
    $notification_count=$notif_stmt->rowCount();
} catch(Exception $e){error_log("Resources error: ".$e->getMessage());}

$host='localhost'; $dbname='bfinitia_resource_library'; $user_db='bfinitia'; $password='Akande_Olanrewaju123@'; $port='5432';

function getMimeType($filename){
    $ext=strtolower(pathinfo($filename,PATHINFO_EXTENSION));
    $types=['mp4'=>'video/mp4','mov'=>'video/quicktime','avi'=>'video/x-msvideo','wmv'=>'video/x-ms-wmv','webm'=>'video/webm','mkv'=>'video/x-matroska'];
    return $types[$ext]??'video/mp4';
}
function formatDuration($dur){
    if(empty($dur)) return null;
    return $dur;
}

$videos=[];
try {
    $dsn="pgsql:host=$host;port=$port;dbname=$dbname;user=$user_db;password=$password";
    $pdo=new PDO($dsn); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $videos=$pdo->query("SELECT * FROM videos ORDER BY category, title")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e){error_log("Resources error: ".$e->getMessage()); $videos=[];}

$categories=[
    'application-tips' =>['label'=>'Application Tips',       'icon'=>'fas fa-lightbulb',    'color'=>'rgba(200,160,88,0.12)',  'color_icon'=>'var(--gold-bright)'],
    'info-sessions'    =>['label'=>'Information Sessions',    'icon'=>'fas fa-info-circle',  'color'=>'rgba(52,211,153,0.10)', 'color_icon'=>'#34D399'],
    'interview-prep'   =>['label'=>'Interview Preparation',   'icon'=>'fas fa-microphone',   'color'=>'rgba(96,165,250,0.10)', 'color_icon'=>'#60A5FA'],
];
$total_videos = count($videos);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Video Resources | BFI Scholar Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    :root{--midnight:#080E1C;--navy:#0D1829;--navy-light:#1C2F52;--gold:#C8A058;--gold-bright:#E0B96C;--cream:#FAF6EF;--cream-dark:#F2EAD8;--white:#FFFFFF;--text-primary:#0D1829;--text-secondary:#4A526A;--text-muted:#8A92A8;--border-light:#E8E4DA;--font-display:'Cormorant Garamond',Georgia,serif;--font-body:'Outfit',-apple-system,sans-serif;--ease:cubic-bezier(0.25,0.46,0.45,0.94);--transition:0.3s var(--ease);--shadow-md:0 8px 32px rgba(8,14,28,0.10);--shadow-lg:0 20px 60px rgba(8,14,28,0.14);--sidebar-width:268px;--header-height:64px;--r-sm:8px;--r-md:14px;--r-lg:20px;--r-xl:28px;}
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:var(--font-body);background:#F2F4F8;color:var(--text-primary);line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden;}
    a{text-decoration:none;color:inherit;}
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
    .sidebar-user-role{font-size:10.5px;color:rgba(255,255,255,0.35);}
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
    .main{margin-left:var(--sidebar-width);margin-top:var(--header-height);padding:28px;min-height:calc(100vh - var(--header-height));}
    .page-header{background:var(--navy);border-radius:var(--r-xl);padding:28px 32px;margin-bottom:24px;position:relative;overflow:hidden;}
    .page-header::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:32px 32px;}
    .page-header::after{content:'';position:absolute;top:-60px;right:-60px;width:240px;height:240px;background:radial-gradient(circle,rgba(200,160,88,0.1) 0%,transparent 65%);}
    .ph-inner{position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;}
    .ph-eyebrow{font-size:10px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:rgba(200,160,88,0.7);margin-bottom:6px;}
    .ph-title{font-family:var(--font-display);font-size:clamp(22px,3vw,30px);font-weight:500;color:var(--white);margin-bottom:4px;}
    .ph-title em{font-style:italic;color:var(--gold-bright);}
    .ph-sub{font-size:13.5px;font-weight:300;color:rgba(255,255,255,0.5);}
    .ph-stats{display:flex;gap:24px;position:relative;z-index:1;}
    .ph-stat{text-align:center;}
    .ph-stat-num{font-family:var(--font-display);font-size:24px;font-weight:500;color:var(--gold-bright);line-height:1;}
    .ph-stat-label{font-size:10px;color:rgba(255,255,255,0.35);text-transform:uppercase;letter-spacing:1px;margin-top:2px;}
    .btn-back-sm{display:inline-flex;align-items:center;gap:8px;padding:9px 18px;background:rgba(255,255,255,0.07);color:rgba(255,255,255,0.7);font-size:13px;border:1px solid rgba(255,255,255,0.1);border-radius:var(--r-sm);transition:var(--transition);}
    .btn-back-sm:hover{background:rgba(255,255,255,0.12);color:var(--white);}
    .search-bar{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:12px;}
    .search-wrap{flex:1;position:relative;}
    .search-icon-inner{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;}
    .search-input{width:100%;padding:9px 12px 9px 36px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13.5px;color:var(--text-primary);background:var(--cream);transition:border-color var(--transition);}
    .search-input:focus{outline:none;border-color:var(--gold);background:var(--white);box-shadow:0 0 0 3px rgba(200,160,88,0.1);}
    .search-info{font-size:12px;color:var(--text-muted);white-space:nowrap;}
    .cat-filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px;}
    .cat-pill{padding:7px 16px;border-radius:20px;background:var(--white);color:var(--text-secondary);border:1px solid var(--border-light);font-family:var(--font-body);font-size:13px;cursor:pointer;transition:var(--transition);}
    .cat-pill:hover{border-color:var(--gold);color:var(--gold);}
    .cat-pill.active{background:var(--navy);color:var(--white);border-color:var(--navy);}
    .section-label{font-size:10px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);margin-bottom:14px;}
    .cat-section{margin-bottom:28px;}
    .cat-section-header{display:flex;align-items:center;gap:14px;margin-bottom:16px;}
    .cat-section-icon{width:40px;height:40px;border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
    .cat-section-title{font-family:var(--font-display);font-size:22px;font-weight:500;color:var(--navy);}
    .cat-section-title em{font-style:italic;color:var(--gold);}
    .cat-section-count{font-size:12px;color:var(--text-muted);background:var(--white);border:1px solid var(--border-light);padding:2px 9px;border-radius:20px;margin-left:auto;}
    .video-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;}
    .video-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);overflow:hidden;transition:var(--transition);display:flex;flex-direction:column;}
    .video-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-lg);border-color:transparent;}
    .video-card.hidden-card{display:none;}
    .video-thumb{position:relative;aspect-ratio:16/9;background:var(--navy);overflow:hidden;cursor:pointer;}
    .video-thumb-bg{position:absolute;inset:0;background:linear-gradient(135deg,var(--navy) 0%,var(--navy-light) 100%);display:flex;align-items:center;justify-content:center;}
    .play-btn{width:56px;height:56px;border-radius:50%;background:rgba(200,160,88,0.2);border:2px solid var(--gold);display:flex;align-items:center;justify-content:center;font-size:20px;color:var(--gold-bright);transition:var(--transition);position:relative;z-index:1;}
    .video-thumb:hover .play-btn{background:var(--gold);color:var(--midnight);transform:scale(1.1);}
    .video-duration{position:absolute;bottom:10px;right:10px;background:rgba(8,14,28,0.8);color:var(--white);font-size:11px;font-weight:600;padding:2px 8px;border-radius:4px;backdrop-filter:blur(4px);}
    .video-cat-badge{position:absolute;top:10px;left:10px;font-size:9.5px;font-weight:700;letter-spacing:0.5px;text-transform:uppercase;padding:3px 8px;border-radius:4px;backdrop-filter:blur(4px);background:rgba(8,14,28,0.7);color:rgba(255,255,255,0.8);}
    .video-body{padding:16px 18px 18px;flex:1;display:flex;flex-direction:column;}
    .video-title{font-size:14px;font-weight:600;color:var(--navy);margin-bottom:6px;line-height:1.4;}
    .video-desc{font-size:13px;font-weight:300;color:var(--text-secondary);line-height:1.65;margin-bottom:14px;flex:1;}
    .video-actions{display:flex;gap:8px;margin-top:auto;}
    .btn-play-card{display:inline-flex;align-items:center;gap:6px;flex:1;justify-content:center;padding:9px 14px;background:var(--navy);color:var(--white);font-family:var(--font-body);font-size:12.5px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-play-card:hover{background:var(--navy-light);}
    .btn-dl-card{display:inline-flex;align-items:center;gap:6px;padding:9px 14px;background:rgba(200,160,88,0.08);color:var(--gold);font-family:var(--font-body);font-size:12.5px;font-weight:500;border:1px solid rgba(200,160,88,0.25);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-dl-card:hover{background:var(--gold);color:var(--midnight);}
    .empty-cat{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:40px;text-align:center;}
    .empty-cat-icon{font-size:36px;color:var(--border-light);margin-bottom:12px;}
    .empty-cat-text{font-size:13.5px;color:var(--text-muted);}
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(4,8,16,0.96);z-index:600;flex-direction:column;align-items:center;justify-content:center;padding:0;}
    .modal-overlay.active{display:flex;}
    .video-modal{display:flex;flex-direction:column;width:100%;max-width:1000px;max-height:100vh;}
    .vm-header{display:flex;align-items:center;gap:14px;padding:16px 24px;flex-shrink:0;}
    .vm-title{font-family:var(--font-display);font-size:20px;font-weight:500;color:var(--white);flex:1;}
    .vm-title em{font-style:italic;color:var(--gold-bright);}
    .vm-close{width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,0.08);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:15px;color:rgba(255,255,255,0.7);transition:var(--transition);}
    .vm-close:hover{background:rgba(255,255,255,0.15);color:var(--white);}
    .vm-player{background:#000;position:relative;width:100%;}
    .vm-player video{width:100%;display:block;max-height:calc(100vh - 140px);}
    .vm-unavailable{display:none;flex-direction:column;align-items:center;justify-content:center;min-height:300px;padding:40px;text-align:center;}
    .vm-unavailable.show{display:flex;}
    .vm-unavailable-icon{font-size:48px;color:rgba(255,255,255,0.1);margin-bottom:16px;}
    .vm-unavailable-title{font-family:var(--font-display);font-size:24px;color:var(--white);margin-bottom:8px;}
    .vm-unavailable-sub{font-size:14px;color:rgba(255,255,255,0.4);max-width:340px;line-height:1.7;}
    .vm-footer{padding:14px 24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;flex-shrink:0;}
    .vm-footer-meta{font-size:12.5px;color:rgba(255,255,255,0.4);}
    .vm-footer-actions{display:flex;gap:10px;}
    .btn-vm-close{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:rgba(255,255,255,0.08);color:rgba(255,255,255,0.7);font-family:var(--font-body);font-size:13px;font-weight:500;border:1px solid rgba(255,255,255,0.1);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-vm-close:hover{background:rgba(255,255,255,0.14);color:var(--white);}
    .btn-vm-dl{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:var(--gold);color:var(--midnight);font-family:var(--font-body);font-size:13px;font-weight:600;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-vm-dl:hover{background:var(--gold-bright);}
    .portal-footer{margin-left:var(--sidebar-width);background:var(--white);padding:14px 28px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;}
    .footer-copy{font-size:12px;color:var(--text-muted);}
    .footer-links{display:flex;gap:18px;}
    .footer-links a{font-size:12px;color:var(--text-muted);transition:color var(--transition);}.footer-links a:hover{color:var(--gold);}
    .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:150;backdrop-filter:blur(4px);}
    .sidebar-overlay.active{display:block;}
    @media(max-width:1024px){.video-grid{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:768px){.sidebar{transform:translateX(-100%)}.sidebar.active{transform:translateX(0)}.header{left:0}.main,.portal-footer{margin-left:0}.mobile-toggle{display:flex}.video-grid{grid-template-columns:1fr}.search-bar{flex-direction:column;align-items:stretch}}
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
      <div class="sidebar-avatar"><?php if($profile_picture): ?><img src="<?php echo htmlspecialchars('./uploads/profile_pictures/'.$profile_picture); ?>" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"><div class="sidebar-avatar-init" style="display:none;"><?php echo strtoupper(substr($first_name,0,1)); ?></div><?php else: ?><div class="sidebar-avatar-init"><?php echo strtoupper(substr($first_name,0,1)); ?></div><?php endif; ?></div>
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
    <div class="breadcrumb">
      <a href="dashboard.php">Dashboard</a><span class="breadcrumb-sep"><i class="fas fa-chevron-right" style="font-size:9px;"></i></span>
      <a href="resources.php">Resources</a><span class="breadcrumb-sep"><i class="fas fa-chevron-right" style="font-size:9px;"></i></span>
      <span class="breadcrumb-current">Video Library</span>
    </div>
  </div>
  <div class="header-right">
    <button class="header-icon-btn"><i class="fas fa-bell"></i><?php if($notification_count>0): ?><div class="notif-dot"></div><?php endif; ?></button>
    <a href="profile.php"><div class="header-avatar"><?php if($profile_picture): ?><img src="<?php echo htmlspecialchars('./uploads/profile_pictures/'.$profile_picture); ?>" alt=""><div class="header-avatar-init" style="display:none;"><?php echo strtoupper(substr($first_name,0,1)); ?></div><?php else: ?><div class="header-avatar-init"><?php echo strtoupper(substr($first_name,0,1)); ?></div><?php endif; ?></div></a>
  </div>
</header>

<main class="main">
  <div class="page-header">
    <div class="ph-inner">
      <div>
        <div class="ph-eyebrow">Resource Library</div>
        <div class="ph-title">Video <em>Resources</em></div>
        <div class="ph-sub">All videos playable inline and downloadable — application tips, info sessions, interview prep</div>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:12px;">
        <div class="ph-stats">
          <div class="ph-stat"><div class="ph-stat-num"><?php echo $total_videos ?: '—'; ?></div><div class="ph-stat-label">Total Videos</div></div>
          <div class="ph-stat"><div class="ph-stat-num"><?php echo count($categories); ?></div><div class="ph-stat-label">Categories</div></div>
        </div>
        <a href="resources.php" class="btn-back-sm"><i class="fas fa-arrow-left"></i> Back to Library</a>
      </div>
    </div>
  </div>

  <!-- SEARCH + FILTER -->
  <div class="search-bar">
    <div class="search-wrap">
      <i class="fas fa-search search-icon-inner"></i>
      <input type="text" class="search-input" id="videoSearch" placeholder="Search videos by title or description…" oninput="filterVideos(this.value)">
    </div>
    <div class="search-info" id="searchInfo"><?php echo $total_videos; ?> video<?php echo $total_videos!==1?'s':''; ?> available</div>
  </div>
  <div class="cat-filters" id="catFilters">
    <button class="cat-pill active" data-cat="all" onclick="filterByCat('all',this)"><i class="fas fa-th"></i> All Videos</button>
    <?php foreach($categories as $key=>$cat): ?>
    <button class="cat-pill" data-cat="<?php echo $key; ?>" onclick="filterByCat('<?php echo $key; ?>',this)"><i class="<?php echo $cat['icon']; ?>"></i> <?php echo htmlspecialchars($cat['label']); ?></button>
    <?php endforeach; ?>
  </div>

  <!-- VIDEO SECTIONS -->
  <?php foreach($categories as $cat_key=>$cat): ?>
  <?php $cat_videos=array_values(array_filter($videos,fn($v)=>($v['category']??'')===$cat_key)); $cat_count=count($cat_videos); ?>
  <div class="cat-section" id="catsec-<?php echo $cat_key; ?>" data-cat="<?php echo $cat_key; ?>">
    <div class="cat-section-header">
      <div class="cat-section-icon" style="background:<?php echo $cat['color']; ?>;"><i class="<?php echo $cat['icon']; ?>" style="color:<?php echo $cat['color_icon']; ?>;"></i></div>
      <div class="cat-section-title"><?php echo htmlspecialchars($cat['label']); ?></div>
      <span class="cat-section-count"><?php echo $cat_count; ?> video<?php echo $cat_count!==1?'s':''; ?></span>
    </div>
    <?php if($cat_count>0): ?>
    <div class="video-grid" id="grid-<?php echo $cat_key; ?>">
      <?php foreach($cat_videos as $v): ?>
      <?php
        /* ── FIX: use the same /media/videos/ path the old working file uses ── */
        $vid_web_path = '/media/videos/' . ($v['filename'] ?? '');
        $vid_fs_path  = $_SERVER['DOCUMENT_ROOT'] . '/media/videos/' . ($v['filename'] ?? '');
        $vid_exists   = !empty($v['filename']) && file_exists($vid_fs_path);
        $mime         = getMimeType($v['filename'] ?? '');
        $dur          = formatDuration($v['duration'] ?? null);
      ?>
      <div class="video-card" data-cat="<?php echo $cat_key; ?>" data-search="<?php echo strtolower(htmlspecialchars(($v['title']??'').' '.($v['description']??'').' '.$cat_key)); ?>">
        <div class="video-thumb" onclick="<?php echo $vid_exists ? "playVideo('".htmlspecialchars($vid_web_path)."','".htmlspecialchars(addslashes($v['title']??'Video'))."','".$mime."','".htmlspecialchars($dur??'')."')" : "openUnavailable('".htmlspecialchars(addslashes($v['title']??'Video'))."')"; ?>">
          <div class="video-thumb-bg">
            <div class="play-btn"><i class="fas fa-play" style="margin-left:3px;"></i></div>
          </div>
          <?php if($dur): ?><div class="video-duration"><?php echo htmlspecialchars($dur); ?></div><?php endif; ?>
          <div class="video-cat-badge"><?php echo htmlspecialchars($cat['label']); ?></div>
        </div>
        <div class="video-body">
          <div class="video-title"><?php echo htmlspecialchars($v['title']??'Untitled'); ?></div>
          <?php if(!empty($v['description'])): ?><div class="video-desc"><?php echo htmlspecialchars($v['description']); ?></div><?php endif; ?>
          <div class="video-actions">
            <button class="btn-play-card" onclick="<?php echo $vid_exists ? "playVideo('".htmlspecialchars($vid_web_path)."','".htmlspecialchars(addslashes($v['title']??'Video'))."','".$mime."','".htmlspecialchars($dur??'')."')" : "openUnavailable('".htmlspecialchars(addslashes($v['title']??'Video'))."')"; ?>">
              <i class="fas fa-play"></i> <?php echo $vid_exists ? 'Play Video' : 'Preview Unavailable'; ?>
            </button>
            <?php if($vid_exists): ?>
            <a href="<?php echo htmlspecialchars($vid_web_path); ?>" download="<?php echo htmlspecialchars($v['filename']??'video'); ?>" class="btn-dl-card" onclick="event.stopPropagation()"><i class="fas fa-download"></i> Download</a>
            <?php else: ?>
            <button class="btn-dl-card" style="opacity:0.4;cursor:not-allowed;" disabled><i class="fas fa-download"></i> Download</button>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-cat">
      <div class="empty-cat-icon"><i class="fas fa-video"></i></div>
      <div class="empty-cat-text">No <?php echo htmlspecialchars($cat['label']); ?> videos uploaded yet — check back soon.</div>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <div id="noResults" style="display:none;text-align:center;padding:60px 24px;">
    <div style="font-size:40px;color:var(--border-light);margin-bottom:16px;"><i class="fas fa-search"></i></div>
    <div style="font-family:var(--font-display);font-size:22px;color:var(--navy);margin-bottom:8px;">No videos found</div>
    <div style="font-size:13.5px;color:var(--text-muted);">Try a different search term or browse all categories.</div>
  </div>
</main>

<!-- VIDEO PLAYER MODAL -->
<div class="modal-overlay" id="videoModal">
  <div class="video-modal">
    <div class="vm-header">
      <div class="vm-title" id="vmTitle">Video <em>Player</em></div>
      <button class="vm-close" onclick="closeVideo()"><i class="fas fa-times"></i></button>
    </div>
    <div class="vm-player" id="vmPlayer">
      <video id="videoEl" controls preload="metadata" style="width:100%;display:block;max-height:calc(100vh - 140px);">
        <source id="videoSrc" src="" type="video/mp4">
        Your browser does not support the video tag.
      </video>
      <div class="vm-unavailable" id="vmUnavailable">
        <div class="vm-unavailable-icon"><i class="fas fa-video-slash"></i></div>
        <div class="vm-unavailable-title">Video Not Yet Available</div>
        <div class="vm-unavailable-sub">This video hasn't been uploaded yet. Your programme coordinator will add it soon.</div>
      </div>
    </div>
    <div class="vm-footer">
      <div class="vm-footer-meta" id="vmMeta"></div>
      <div class="vm-footer-actions">
        <button class="btn-vm-close" onclick="closeVideo()"><i class="fas fa-times"></i> Close</button>
        <a id="vmDlBtn" href="#" download class="btn-vm-dl"><i class="fas fa-download"></i> Download</a>
      </div>
    </div>
  </div>
</div>

<footer class="portal-footer">
  <div class="footer-copy">&copy; 2026 Bold Footprint Initiatives. All rights reserved.</div>
  <div class="footer-links"><a href="/index.html">Main Site</a><a href="resources.php">Resources</a><a href="document-resources.php">Documents</a></div>
</footer>

<script>
  // Sidebar
  const sidebar=document.getElementById('sidebar'),overlay=document.getElementById('sidebarOverlay'),toggle=document.getElementById('mobileToggle');
  function openS(){sidebar.classList.add('active');overlay.classList.add('active');document.body.style.overflow='hidden';}
  function closeS(){sidebar.classList.remove('active');overlay.classList.remove('active');document.body.style.overflow='';}
  if(toggle) toggle.addEventListener('click',()=>sidebar.classList.contains('active')?closeS():openS());
  overlay.addEventListener('click',closeS);
  window.addEventListener('resize',()=>{if(window.innerWidth>768)closeS();});

  // ── FIX: robust playVideo with onerror fallback to unavailable state ──
  function playVideo(src, title, mime, duration) {
    const el       = document.getElementById('videoEl');
    const srcEl    = document.getElementById('videoSrc');
    const unavail  = document.getElementById('vmUnavailable');
    const dlBtn    = document.getElementById('vmDlBtn');

    // Reset state fully
    el.onerror = null;
    el.style.display = 'block';
    unavail.classList.remove('show');
    dlBtn.style.display = '';

    // Wire up error handler BEFORE loading so a 404 is caught
    el.onerror = function () {
      el.style.display = 'none';
      unavail.classList.add('show');
      dlBtn.style.display = 'none';
    };

    // Set source and load
    srcEl.src  = src;
    srcEl.type = mime || 'video/mp4';
    el.load();
    el.play().catch(function(){});

    // UI
    document.getElementById('vmTitle').innerHTML = htmlEscape(title) + ' <em style="color:var(--gold-bright);">— Video</em>';
    document.getElementById('vmMeta').textContent = duration ? 'Duration: ' + duration : '';
    dlBtn.href     = src;
    dlBtn.download = src.split('/').pop();

    document.getElementById('videoModal').classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function openUnavailable(title) {
    const el = document.getElementById('videoEl');
    el.onerror = null;
    el.style.display = 'none';
    document.getElementById('vmUnavailable').classList.add('show');
    document.getElementById('vmTitle').innerHTML = htmlEscape(title) + ' <em style="color:rgba(255,255,255,0.4);">— Unavailable</em>';
    document.getElementById('vmMeta').textContent = 'Not yet uploaded';
    document.getElementById('vmDlBtn').style.display = 'none';
    document.getElementById('videoModal').classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeVideo() {
    const el = document.getElementById('videoEl');
    el.onerror = null;
    el.pause();
    el.currentTime = 0;
    document.getElementById('videoSrc').src = '';
    el.load();
    el.style.display = 'block';
    document.getElementById('vmUnavailable').classList.remove('show');
    document.getElementById('vmDlBtn').style.display = '';
    document.getElementById('videoModal').classList.remove('active');
    document.body.style.overflow = '';
  }

  document.getElementById('videoModal').addEventListener('click', function(e){ if(e.target===this) closeVideo(); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeVideo(); });

  // Category filter pills
  function filterByCat(cat,btn){
    document.querySelectorAll('.cat-pill').forEach(p=>p.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.cat-section').forEach(sec=>{
      sec.style.display=(cat==='all'||sec.dataset.cat===cat)?'':'none';
    });
    document.getElementById('videoSearch').value='';
    updateCount();
  }

  // Search filter
  function filterVideos(q){
    q=q.toLowerCase().trim();
    document.querySelectorAll('.cat-pill').forEach(p=>p.classList.remove('active'));
    document.querySelector('[data-cat="all"]').classList.add('active');
    document.querySelectorAll('.cat-section').forEach(sec=>sec.style.display='');
    let visible=0;
    document.querySelectorAll('.video-card').forEach(card=>{
      const match=!q||card.dataset.search.includes(q);
      card.classList.toggle('hidden-card',!match);
      if(match)visible++;
    });
    document.querySelectorAll('.cat-section').forEach(sec=>{
      const hasVisible=[...sec.querySelectorAll('.video-card:not(.hidden-card)')].length>0;
      if(q&&!hasVisible) sec.style.display='none';
    });
    document.getElementById('noResults').style.display=(q&&visible===0)?'block':'none';
    document.getElementById('searchInfo').textContent=q?(visible+' result'+(visible!==1?'s':'')):'<?php echo $total_videos; ?> video<?php echo $total_videos!==1?'s':''; ?> available';
  }

  function updateCount(){
    const visible=[...document.querySelectorAll('.video-card:not(.hidden-card)')].length;
    document.getElementById('searchInfo').textContent=visible+' video'+(visible!==1?'s':'');
  }

  function htmlEscape(str){
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
</script>
</body>
</html>