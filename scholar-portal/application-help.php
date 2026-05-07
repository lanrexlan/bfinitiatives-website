<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$first_name=''; $profile_picture=null; $notification_count=0;
$selected_doc = isset($_GET['doc']) ? $_GET['doc'] : 'overview';

try {
    $db=new Database(); $conn=$db->getConnection();
    $stmt=$conn->prepare("SELECT first_name, profile_picture FROM users WHERE id = :user_id");
    $stmt->execute([':user_id'=>$_SESSION['user_id']]);
    $user=$stmt->fetch(PDO::FETCH_ASSOC);
    if($user){$first_name=$user['first_name'];$profile_picture=$user['profile_picture']??null;}
    $notif_stmt=$conn->prepare("SELECT id FROM notifications WHERE user_id = :user_id AND read_status = 0");
    $notif_stmt->execute([':user_id'=>$_SESSION['user_id']]);
    $notification_count=$notif_stmt->rowCount();
} catch(Exception $e){error_log("Application help page error: ".$e->getMessage());}

$document_statuses=['cv'=>'not_started','statement'=>'not_started','research'=>'not_started','recommendation'=>'not_started','language'=>'not_started','additional'=>'not_started'];
$document_counts=['recommendation_total'=>3,'recommendation_completed'=>0];
$completion_percentage=0;

try {
    $doc_stmt=$conn->prepare("SELECT document_type, status, review_status, COUNT(id) as count FROM user_documents WHERE user_id = :user_id GROUP BY document_type, status, review_status");
    $doc_stmt->execute([':user_id'=>$_SESSION['user_id']]);
    $documents=$doc_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($documents as $doc){
        $type=$doc['document_type']; $status=$doc['status']; $review=$doc['review_status']; $count=$doc['count'];
        if($status=='submitted'){
            if($review=='approved') $document_statuses[$type]='complete';
            elseif($review=='in_review') $document_statuses[$type]='in_progress';
            else $document_statuses[$type]='in_progress';
        }
        if($type=='recommendation') $document_counts['recommendation_completed']=intval($count);
    }
    $total_documents=count($document_statuses); $completed_count=0; $in_progress_count=0;
    foreach($document_statuses as $status){
        if($status=='complete') $completed_count++;
        elseif($status=='in_progress') $in_progress_count++;
    }
    $completion_percentage=round((($completed_count+($in_progress_count*0.5))/$total_documents)*100);
} catch(Exception $e){error_log("Error fetching document status: ".$e->getMessage()); $completion_percentage=0;}

function isActive($tab){ global $selected_doc; return $selected_doc===$tab?'active':''; }

function statusBadge($status){
    if($status==='complete') return '<span style="font-size:10.5px;font-weight:600;padding:2px 9px;border-radius:20px;background:rgba(52,211,153,0.1);color:#059669;">Complete</span>';
    if($status==='in_progress') return '<span style="font-size:10.5px;font-weight:600;padding:2px 9px;border-radius:20px;background:rgba(245,158,11,0.1);color:#D97706;">In Progress</span>';
    return '<span style="font-size:10.5px;font-weight:600;padding:2px 9px;border-radius:20px;background:rgba(139,146,168,0.1);color:#8A92A8;">Not Started</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Application Materials | BFI Scholar Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    :root{--midnight:#080E1C;--navy:#0D1829;--navy-light:#1C2F52;--gold:#C8A058;--gold-bright:#E0B96C;--cream:#FAF6EF;--cream-dark:#F2EAD8;--white:#FFFFFF;--text-primary:#0D1829;--text-secondary:#4A526A;--text-muted:#8A92A8;--border-light:#E8E4DA;--font-display:'Cormorant Garamond',Georgia,serif;--font-body:'Outfit',-apple-system,sans-serif;--ease:cubic-bezier(0.25,0.46,0.45,0.94);--transition:0.3s var(--ease);--shadow-md:0 8px 32px rgba(8,14,28,0.10);--sidebar-width:268px;--header-height:64px;--r-sm:8px;--r-md:14px;--r-lg:20px;--r-xl:28px;}
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
    .page-header-inner{position:relative;z-index:1;}
    .page-header-eyebrow{font-size:10px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:rgba(200,160,88,0.7);margin-bottom:6px;}
    .page-header-title{font-family:var(--font-display);font-size:clamp(22px,3vw,30px);font-weight:500;color:var(--white);margin-bottom:4px;}
    .page-header-title em{font-style:italic;color:var(--gold-bright);}
    .page-header-sub{font-size:13.5px;font-weight:300;color:rgba(255,255,255,0.5);}
    /* TAB NAV */
    .tab-nav{display:flex;gap:4px;background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:6px;margin-bottom:24px;overflow-x:auto;}
    .tab-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:var(--r-sm);font-family:var(--font-body);font-size:13px;font-weight:400;color:var(--text-secondary);border:none;background:transparent;cursor:pointer;transition:var(--transition);white-space:nowrap;}
    .tab-btn:hover{background:var(--cream);color:var(--navy);}
    .tab-btn.active{background:var(--navy);color:var(--white);}
    .tab-btn i{font-size:12px;}
    /* CONTENT LAYOUT */
    .content-layout{display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start;}
    /* READINESS CARD */
    .readiness-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:24px;margin-bottom:20px;}
    .readiness-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;}
    .readiness-title{font-family:var(--font-display);font-size:18px;font-weight:500;color:var(--navy);}
    .readiness-pct{font-family:var(--font-display);font-size:28px;font-weight:500;color:var(--gold);}
    .progress-bar{height:6px;background:var(--border-light);border-radius:3px;overflow:hidden;margin-bottom:20px;}
    .progress-fill{height:100%;background:linear-gradient(90deg,var(--gold),var(--gold-bright));border-radius:3px;transition:width 1s var(--ease);}
    .readiness-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
    .readiness-item{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--cream);border-radius:var(--r-sm);}
    .readiness-item-label{font-size:13px;color:var(--text-secondary);}
    /* CONTENT CARD */
    .content-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);overflow:hidden;}
    .content-card-header{padding:20px 24px;border-bottom:1px solid var(--border-light);background:var(--cream);}
    .content-card-title{font-family:var(--font-display);font-size:20px;font-weight:500;color:var(--navy);}
    .content-card-title em{font-style:italic;color:var(--gold);}
    .content-card-body{padding:24px;}
    .tip-block{background:rgba(200,160,88,0.05);border:1px solid rgba(200,160,88,0.15);border-radius:var(--r-md);padding:16px 20px;margin-bottom:16px;}
    .tip-block-label{font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--gold);margin-bottom:8px;}
    .tip-block-text{font-size:13.5px;font-weight:300;color:var(--text-secondary);line-height:1.72;}
    .guide-section{margin-bottom:24px;}
    .guide-section h3{font-family:var(--font-display);font-size:18px;font-weight:500;color:var(--navy);margin-bottom:10px;}
    .guide-section h3 em{font-style:italic;color:var(--gold);}
    .guide-section p{font-size:13.5px;font-weight:300;color:var(--text-secondary);line-height:1.75;margin-bottom:12px;}
    .guide-list{list-style:none;display:flex;flex-direction:column;gap:8px;}
    .guide-list li{font-size:13.5px;color:var(--text-secondary);display:flex;align-items:flex-start;gap:10px;line-height:1.55;}
    .guide-list li::before{content:'';width:4px;height:4px;border-radius:50%;background:var(--gold);flex-shrink:0;margin-top:7px;}
    .mistake-item{display:flex;align-items:flex-start;gap:12px;padding:12px 0;border-bottom:1px solid var(--border-light);}
    .mistake-item:last-child{border-bottom:none;}
    .mistake-icon{width:28px;height:28px;border-radius:50%;background:rgba(239,68,68,0.08);display:flex;align-items:center;justify-content:center;font-size:12px;color:#EF4444;flex-shrink:0;margin-top:1px;}
    .mistake-text{font-size:13.5px;color:var(--text-secondary);line-height:1.6;}
    .mistake-text strong{color:var(--navy);}
    .timeline-item{display:flex;gap:16px;padding:12px 0;border-bottom:1px solid var(--border-light);}
    .timeline-item:last-child{border-bottom:none;}
    .timeline-dot{width:10px;height:10px;border-radius:50%;background:var(--gold);flex-shrink:0;margin-top:5px;}
    .timeline-content{}
    .timeline-label{font-size:12px;font-weight:600;color:var(--gold);margin-bottom:2px;}
    .timeline-text{font-size:13.5px;color:var(--text-secondary);line-height:1.6;}
    /* SIDEBAR QUICK NAV */
    .side-nav-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);overflow:hidden;margin-bottom:16px;}
    .side-nav-header{padding:14px 18px;border-bottom:1px solid var(--border-light);font-size:11px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:var(--text-muted);}
    .side-nav-link{display:flex;align-items:center;gap:10px;padding:11px 18px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-light);transition:var(--transition);}
    .side-nav-link:last-child{border-bottom:none;}
    .side-nav-link:hover{background:var(--cream);color:var(--navy);}
    .side-nav-link.active{background:rgba(200,160,88,0.07);color:var(--gold);}
    .side-nav-link i{width:16px;text-align:center;font-size:12px;color:var(--text-muted);}
    .side-nav-link.active i{color:var(--gold);}
    .portal-footer{margin-left:var(--sidebar-width);background:var(--white);padding:14px 28px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;}
    .footer-copy{font-size:12px;color:var(--text-muted);}
    .footer-links{display:flex;gap:18px;}.footer-links a{font-size:12px;color:var(--text-muted);transition:color var(--transition);}.footer-links a:hover{color:var(--gold);}
    .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:150;}.sidebar-overlay.active{display:block;}
    @media(max-width:1024px){.content-layout{grid-template-columns:1fr}.readiness-grid{grid-template-columns:1fr}}
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
      <div class="sidebar-avatar"><?php if($profile_picture): ?><img src="<?php echo htmlspecialchars('./uploads/profile_pictures/'.$profile_picture); ?>" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"><div class="sidebar-avatar-init" style="display:none;"><?php echo strtoupper(substr($first_name,0,1)); ?></div><?php else: ?><div class="sidebar-avatar-init"><?php echo strtoupper(substr($first_name,0,1)); ?></div><?php endif; ?></div>
      <div><div class="sidebar-user-name"><?php echo htmlspecialchars($first_name); ?></div><div class="sidebar-user-role">BFI Scholar</div></div>
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
    <div class="nav-item"><a href="application-help.php" class="nav-link active"><i class="fas fa-question-circle"></i> Application Help</a></div>
    <div class="nav-item"><a href="resources.php" class="nav-link"><i class="fas fa-book"></i> Resources</a></div>
    <div class="nav-section-label">Account</div>
    <div class="nav-item"><a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a></div>
  </nav>
  <div class="sidebar-bottom"><a href="logout.php" class="nav-link" style="color:rgba(239,68,68,0.7);"><i class="fas fa-sign-out-alt"></i> Log Out</a></div>
</aside>
<header class="header">
  <div class="header-left">
    <button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
    <div class="breadcrumb"><a href="dashboard.php">Dashboard</a><span class="breadcrumb-sep"><i class="fas fa-chevron-right" style="font-size:9px;"></i></span><span class="breadcrumb-current">Application Materials</span></div>
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
      <div class="page-header-title">Application <em>Materials</em></div>
      <div class="page-header-sub">Guidance and resources to help you create compelling scholarship application documents</div>
    </div>
  </div>

  <!-- TAB NAV -->
  <div class="tab-nav">
    <a href="?doc=overview"><button class="tab-btn <?php echo isActive('overview'); ?>"><i class="fas fa-home"></i> Overview</button></a>
    <a href="?doc=cv"><button class="tab-btn <?php echo isActive('cv'); ?>"><i class="fas fa-id-card"></i> CV / Resume</button></a>
    <a href="?doc=statement"><button class="tab-btn <?php echo isActive('statement'); ?>"><i class="fas fa-pen-nib"></i> Personal Statement</button></a>
    <a href="?doc=research"><button class="tab-btn <?php echo isActive('research'); ?>"><i class="fas fa-flask"></i> Research Proposal</button></a>
    <a href="?doc=recommendations"><button class="tab-btn <?php echo isActive('recommendations'); ?>"><i class="fas fa-envelope-open-text"></i> Recommendation Letters</button></a>
    <a href="?doc=language"><button class="tab-btn <?php echo isActive('language'); ?>"><i class="fas fa-language"></i> Language Tests</button></a>
    <a href="?doc=templates"><button class="tab-btn <?php echo isActive('templates'); ?>"><i class="fas fa-file-alt"></i> Templates</button></a>
  </div>

  <div class="content-layout">
    <div>

      <?php if ($selected_doc === 'overview'): ?>
      <!-- READINESS -->
      <div class="readiness-card">
        <div class="readiness-header">
          <div class="readiness-title">Your Application Readiness</div>
          <div class="readiness-pct"><?php echo $completion_percentage; ?>%</div>
        </div>
        <div class="progress-bar"><div class="progress-fill" style="width:<?php echo $completion_percentage; ?>%;"></div></div>
        <div class="readiness-grid">
          <div class="readiness-item"><span class="readiness-item-label">CV / Resume</span><?php echo statusBadge($document_statuses['cv']); ?></div>
          <div class="readiness-item"><span class="readiness-item-label">Personal Statement</span><?php echo statusBadge($document_statuses['statement']); ?></div>
          <div class="readiness-item"><span class="readiness-item-label">Research Proposal</span><?php echo statusBadge($document_statuses['research']); ?></div>
          <div class="readiness-item"><span class="readiness-item-label">Recommendation Letters</span>
            <?php if($document_counts['recommendation_completed']>=$document_counts['recommendation_total']): echo statusBadge('complete');
            elseif($document_counts['recommendation_completed']>0): echo '<span style="font-size:10.5px;font-weight:600;padding:2px 9px;border-radius:20px;background:rgba(245,158,11,0.1);color:#D97706;">'.$document_counts['recommendation_completed'].' / '.$document_counts['recommendation_total'].'</span>';
            else: echo statusBadge('not_started'); endif; ?>
          </div>
          <div class="readiness-item"><span class="readiness-item-label">Language Test</span><?php echo statusBadge($document_statuses['language']); ?></div>
          <div class="readiness-item"><span class="readiness-item-label">Additional Documents</span><?php echo statusBadge($document_statuses['additional']); ?></div>
        </div>
      </div>

      <div class="content-card">
        <div class="content-card-header"><div class="content-card-title">Application <em>Process Overview</em></div></div>
        <div class="content-card-body">
          <div class="tip-block">
            <div class="tip-block-label">Key Insight</div>
            <div class="tip-block-text">Creating strong application materials is crucial for securing international scholarships. Use the resources in this section to develop compelling documents that showcase your qualifications and potential.</div>
          </div>
          <div class="guide-section">
            <h3>What you <em>need</em></h3>
            <ul class="guide-list">
              <li>CV/Resume — a comprehensive summary of your academic and professional achievements</li>
              <li>Personal Statement — a compelling narrative about your background, motivation, and future goals</li>
              <li>Research Proposal (for research-based programmes) — a detailed plan of your intended research</li>
              <li>Recommendation Letters — usually 2–3 letters from academic or professional references</li>
              <li>English Language Test Scores — IELTS, TOEFL, or other recognised tests</li>
              <li>Academic Transcripts — official records of your academic performance</li>
            </ul>
          </div>
          <div class="guide-section">
            <h3>Common <em>mistakes</em> to avoid</h3>
            <div class="mistake-item"><div class="mistake-icon"><i class="fas fa-times"></i></div><div class="mistake-text"><strong>Generic Applications</strong> — tailoring your application to each specific scholarship is essential.</div></div>
            <div class="mistake-item"><div class="mistake-icon"><i class="fas fa-times"></i></div><div class="mistake-text"><strong>Grammar and Spelling Errors</strong> — always proofread carefully or get professional help.</div></div>
            <div class="mistake-item"><div class="mistake-icon"><i class="fas fa-times"></i></div><div class="mistake-text"><strong>Missing Deadlines</strong> — track application timelines and submit well before deadlines.</div></div>
            <div class="mistake-item"><div class="mistake-icon"><i class="fas fa-times"></i></div><div class="mistake-text"><strong>Weak Personal Statement</strong> — failing to clearly connect your background to your future goals.</div></div>
            <div class="mistake-item"><div class="mistake-icon"><i class="fas fa-times"></i></div><div class="mistake-text"><strong>Inadequate References</strong> — choose referees who know you well and can speak to your abilities.</div></div>
          </div>
          <div class="guide-section">
            <h3>Recommended <em>Timeline</em></h3>
            <div class="timeline-item"><div class="timeline-dot"></div><div class="timeline-content"><div class="timeline-label">12–18 months before start date</div><div class="timeline-text">Research programmes, identify scholarships, connect with potential supervisors.</div></div></div>
            <div class="timeline-item"><div class="timeline-dot"></div><div class="timeline-content"><div class="timeline-label">9–12 months before start date</div><div class="timeline-text">Request transcripts, approach referees, begin drafting personal statement.</div></div></div>
            <div class="timeline-item"><div class="timeline-dot"></div><div class="timeline-content"><div class="timeline-label">6–9 months before start date</div><div class="timeline-text">Complete language tests, refine application documents with mentor feedback.</div></div></div>
            <div class="timeline-item"><div class="timeline-dot"></div><div class="timeline-content"><div class="timeline-label">3–6 months before start date</div><div class="timeline-text">Submit applications, prepare for potential interviews.</div></div></div>
          </div>
        </div>
      </div>

      <?php elseif ($selected_doc === 'cv'): ?>
      <div class="content-card">
        <div class="content-card-header"><div class="content-card-title"><em>CV</em> / Resume Guide</div></div>
        <div class="content-card-body">
          <div class="tip-block"><div class="tip-block-label">Goal</div><div class="tip-block-text">Your academic CV should comprehensively present your qualifications, experience, and achievements in a clear, professional format tailored to the scholarship programme.</div></div>
          <div class="guide-section"><h3>Essential <em>sections</em></h3><ul class="guide-list"><li>Personal Information — name, contact details, LinkedIn/ResearchGate (no photo required for most international applications)</li><li>Education — list in reverse chronological order, include GPA, relevant coursework, thesis titles</li><li>Research Experience — describe projects, your role, key findings, and any publications</li><li>Publications & Presentations — even conference posters or preprints count</li><li>Awards & Scholarships — include all academic awards, scholarships, and recognition</li><li>Technical Skills — programming languages, lab techniques, software relevant to your field</li><li>References — typically 2–3; note "available upon request" if not submitting yet</li></ul></div>
          <div class="guide-section"><h3>Common <em>mistakes</em></h3><ul class="guide-list"><li>Including irrelevant work experience without connecting it to your academic goals</li><li>Using overly designed templates — academic CVs should be clean and readable</li><li>Exceeding 2–3 pages for early-career academics</li><li>Using the same CV for every application without tailoring</li></ul></div>
        </div>
      </div>

      <?php elseif ($selected_doc === 'statement'): ?>
      <div class="content-card">
        <div class="content-card-header"><div class="content-card-title">Personal <em>Statement</em> Guide</div></div>
        <div class="content-card-body">
          <div class="tip-block"><div class="tip-block-label">Key Principle</div><div class="tip-block-text">A great personal statement tells a compelling story that connects your past, present, and future — showing the scholarship committee exactly why you are the right person, for this programme, at this time.</div></div>
          <div class="guide-section"><h3>Structure</h3><ul class="guide-list"><li>Opening hook — a specific moment, problem, or question that ignited your passion for the field</li><li>Background — your academic journey and how it has shaped your research interests</li><li>Current position — your most recent work, projects, and what you have learned</li><li>Research goals — specific, feasible, and clearly connected to the programme or supervisor</li><li>Why this programme — name specific faculty, resources, or research groups at the institution</li><li>Closing — your long-term vision and how this scholarship is a crucial step toward it</li></ul></div>
          <div class="guide-section"><h3>Revision process</h3><ul class="guide-list"><li>Write a rough draft without editing — get ideas on paper first</li><li>Share with your BFI mentor for substantive feedback</li><li>Revise for clarity, specificity, and narrative flow</li><li>Proofread for grammar and spelling — read aloud to catch errors</li><li>Have someone unfamiliar with your field read it for clarity</li></ul></div>
        </div>
      </div>

      <?php elseif ($selected_doc === 'research'): ?>
      <div class="content-card">
        <div class="content-card-header"><div class="content-card-title">Research <em>Proposal</em> Guide</div></div>
        <div class="content-card-body">
          <div class="tip-block"><div class="tip-block-label">Purpose</div><div class="tip-block-text">Your research proposal demonstrates that you have a clear, feasible, and significant research plan. It shows intellectual maturity and preparation for doctoral-level work.</div></div>
          <div class="guide-section"><h3>Standard structure</h3><ul class="guide-list"><li>Title — concise and descriptive</li><li>Abstract — 200–300 word summary of the entire proposal</li><li>Background & Literature Review — situate your research within existing knowledge</li><li>Research Questions / Objectives — clear, specific, achievable</li><li>Methodology — how you will conduct the research, what data/materials you need</li><li>Timeline — realistic milestone chart for the proposed study period</li><li>Significance — why this research matters and who benefits</li><li>References — all cited works in the required citation style</li></ul></div>
        </div>
      </div>

      <?php elseif ($selected_doc === 'recommendations'): ?>
      <div class="content-card">
        <div class="content-card-header"><div class="content-card-title">Recommendation <em>Letters</em> Guide</div></div>
        <div class="content-card-body">
          <div class="tip-block"><div class="tip-block-label">Important</div><div class="tip-block-text">Strong recommendation letters can make or break an application. Choose referees who know your work well and can speak specifically to your abilities — not just your character.</div></div>
          <div class="guide-section"><h3>Choosing your <em>referees</em></h3><ul class="guide-list"><li>At least 2 of 3 letters should come from academic supervisors or faculty who know your research</li><li>Choose people who have seen you at your best — a project, thesis, or research collaboration</li><li>Avoid generic character references unless specifically requested</li><li>Ask referees well in advance — at least 6–8 weeks before the deadline</li></ul></div>
          <div class="guide-section"><h3>Supporting your <em>referees</em></h3><ul class="guide-list"><li>Provide your CV, personal statement draft, and programme details</li><li>Remind them of specific projects or achievements they can reference</li><li>Give them a clear deadline — 2 weeks before the actual deadline</li><li>Send a polite follow-up if you haven't received confirmation</li></ul></div>
        </div>
      </div>

      <?php elseif ($selected_doc === 'language'): ?>
      <div class="content-card">
        <div class="content-card-header"><div class="content-card-title">Language <em>Tests</em> Guide</div></div>
        <div class="content-card-body">
          <div class="tip-block"><div class="tip-block-label">Requirements vary</div><div class="tip-block-text">Most international scholarships require proof of English proficiency. Check each programme's specific requirements — minimum scores vary widely.</div></div>
          <div class="guide-section"><h3>Common tests</h3><ul class="guide-list"><li>IELTS Academic — required by most UK, Australia, and some European universities. Minimum typically 6.5–7.0 overall</li><li>TOEFL iBT — more common for US universities. Minimum typically 90–100</li><li>Duolingo English Test — increasingly accepted, especially for US institutions</li><li>GRE — required by many US PhD programmes, particularly in STEM</li></ul></div>
          <div class="guide-section"><h3>Preparation tips</h3><ul class="guide-list"><li>Register for your test at least 3 months before your application deadline</li><li>Use official preparation materials — Cambridge IELTS books, ETS TOEFL practice tests</li><li>Practice all four sections: reading, writing, listening, and speaking</li><li>Consider a preparation course if your first practice scores are below target</li></ul></div>
        </div>
      </div>

      <?php elseif ($selected_doc === 'templates'): ?>
      <div class="content-card">
        <div class="content-card-header"><div class="content-card-title">Download <em>Templates</em></div></div>
        <div class="content-card-body">
          <div class="tip-block"><div class="tip-block-label">How to use these</div><div class="tip-block-text">These templates are starting points — not final documents. Use them as structure guides and customise every section to reflect your own experience and voice.</div></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <?php
            $templates=[
              ['name'=>'Academic CV Template','icon'=>'fas fa-id-card','cat'=>'cv-samples'],
              ['name'=>'Personal Statement Template','icon'=>'fas fa-pen-nib','cat'=>'sop-samples'],
              ['name'=>'Research Proposal Template','icon'=>'fas fa-flask','cat'=>'sop-samples'],
              ['name'=>'Recommendation Request Email','icon'=>'fas fa-envelope','cat'=>'recommendation'],
            ];
            foreach($templates as $t):
            ?>
            <a href="document-resources.php" style="display:flex;align-items:center;gap:12px;padding:14px 18px;background:var(--cream);border-radius:var(--r-md);border:1px solid var(--border-light);transition:var(--transition);" onmouseover="this.style.background='var(--white)';this.style.borderColor='var(--gold)'" onmouseout="this.style.background='var(--cream)';this.style.borderColor='var(--border-light)'">
              <div style="width:36px;height:36px;background:var(--navy);border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:15px;color:var(--gold-bright);flex-shrink:0;"><i class="<?php echo $t['icon']; ?>"></i></div>
              <div><div style="font-size:13.5px;font-weight:500;color:var(--navy);"><?php echo $t['name']; ?></div><div style="font-size:11px;color:var(--text-muted);margin-top:1px;">View in Document Library</div></div>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div>

    <!-- SIDEBAR -->
    <div>
      <div class="side-nav-card">
        <div class="side-nav-header">Quick Navigation</div>
        <a href="?doc=overview" class="side-nav-link <?php echo isActive('overview'); ?>"><i class="fas fa-home"></i> Overview</a>
        <a href="?doc=cv" class="side-nav-link <?php echo isActive('cv'); ?>"><i class="fas fa-id-card"></i> CV / Resume</a>
        <a href="?doc=statement" class="side-nav-link <?php echo isActive('statement'); ?>"><i class="fas fa-pen-nib"></i> Personal Statement</a>
        <a href="?doc=research" class="side-nav-link <?php echo isActive('research'); ?>"><i class="fas fa-flask"></i> Research Proposal</a>
        <a href="?doc=recommendations" class="side-nav-link <?php echo isActive('recommendations'); ?>"><i class="fas fa-envelope-open-text"></i> Recommendation Letters</a>
        <a href="?doc=language" class="side-nav-link <?php echo isActive('language'); ?>"><i class="fas fa-language"></i> Language Tests</a>
        <a href="?doc=templates" class="side-nav-link <?php echo isActive('templates'); ?>"><i class="fas fa-file-alt"></i> Templates</a>
      </div>
      <div class="side-nav-card">
        <div class="side-nav-header">Resources</div>
        <a href="document-resources.php" class="side-nav-link"><i class="fas fa-file-alt"></i> Document Library</a>
        <a href="video-resources.php" class="side-nav-link"><i class="fas fa-video"></i> Video Resources</a>
        <a href="scholarships.php" class="side-nav-link"><i class="fas fa-graduation-cap"></i> Find Scholarships</a>
        <a href="mentors.php" class="side-nav-link"><i class="fas fa-users"></i> Contact Mentor</a>
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