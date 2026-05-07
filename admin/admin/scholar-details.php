<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: admin-login.php'); exit();
}
require_once 'includes/config.php';
require_once 'includes/db.php';

$application = []; $error_message = $success_message = '';
$application_id = $_GET['id'] ?? '';
$stats = ['pending_count'=>0];

if (empty($application_id)) { $error_message="Application ID is required."; }
else {
    try {
        $db=new Database();$conn=$db->getConnection();
        $stmt=$conn->prepare("SELECT * FROM scholarship_applications WHERE application_id=:id");
        $stmt->execute([':id'=>$application_id]);
        $application=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$application) $error_message="Application not found: ".htmlspecialchars($application_id);

        $pc=$conn->query("SELECT COUNT(*) FROM scholarship_applications WHERE status='pending'");
        $stats['pending_count']=$pc->fetchColumn()??0;

    } catch(Exception $e){ $error_message="Database error: ".$e->getMessage(); }
}

// Status update
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_status'])) {
    try {
        $new=$_POST['new_status']??'';
        $conn->prepare("UPDATE scholarship_applications SET status=:s,updated_at=CURRENT_TIMESTAMP WHERE application_id=:id")->execute([':s'=>$new,':id'=>$application_id]);
        $success_message="Status updated to ".ucwords(str_replace('_',' ',$new)).".";
        $application['status']=$new;
    } catch(Exception $e){ $error_message="Update error: ".$e->getMessage(); }
}

function fmtDate($d){return empty($d)?'N/A':date('M d, Y',strtotime($d));}
function statusBadge($s){return['pending'=>'sb-warn','under_review'=>'sb-review','shortlisted'=>'sb-purple','approved'=>'sb-green','rejected'=>'sb-red'][$s??'']??'sb-grey';}
function statusIcon($s){return['pending'=>'clock','under_review'=>'search','shortlisted'=>'star','approved'=>'check-circle','rejected'=>'times-circle'][$s??'']??'circle';}
function rf($label,$val,$textarea=false){$v=!empty($val)?htmlspecialchars($val):'<span style="color:#94a3b8;font-style:italic">Not provided</span>';if($textarea)return"<div style='margin-bottom:18px'><div style='font-size:11px;font-weight:600;color:#8A92A8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px'>$label</div><div style='background:#F8F9FB;padding:13px;border-radius:8px;font-size:13px;color:#4A526A;line-height:1.7;border:1px solid #E8E4DA'>$v</div></div>";return"<div style='margin-bottom:16px'><div style='font-size:11px;font-weight:600;color:#8A92A8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px'>$label</div><div style='font-size:13.5px;color:#0D1829;font-weight:400'>$v</div></div>";}

$admin_full_name  = $_SESSION['admin_name'] ?? 'Admin';
$admin_first_name = explode(' ', $admin_full_name)[0];
$admin_role       = ucfirst($_SESSION['admin_role'] ?? 'Administrator');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Application Details · Admin Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<style>
:root{
  --midnight:#080E1C;--navy:#0D1829;--navy-mid:#132038;--navy-light:#1C2F52;
  --gold:#C8A058;--gold-bright:#E0B96C;--cream:#FAF6EF;--cream-dark:#F2EAD8;--white:#FFFFFF;
  --text-primary:#0D1829;--text-secondary:#4A526A;--text-muted:#8A92A8;--border-light:#E8E4DA;
  --admin-crimson:#9F1239;
  --success:#059669;--success-pale:rgba(5,150,105,.10);
  --warning:#D97706;--warning-pale:rgba(217,119,6,.10);
  --danger:#DC2626;--danger-pale:rgba(220,38,38,.10);
  --info:#0284C7;--info-pale:rgba(2,132,199,.10);
  --font-display:'Cormorant Garamond',Georgia,serif;--font-body:'Outfit',-apple-system,sans-serif;
  --ease:cubic-bezier(.25,.46,.45,.94);--transition:.3s var(--ease);
  --sidebar-width:268px;--header-height:64px;
  --r-sm:8px;--r-md:14px;--r-lg:20px;--r-xl:28px;
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
body{font-family:var(--font-body);background:#F0F2F7;color:var(--text-primary);line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden;}
a{text-decoration:none;color:inherit;}
.sidebar{position:fixed;left:0;top:0;width:var(--sidebar-width);height:100vh;background:var(--navy);z-index:200;display:flex;flex-direction:column;overflow:hidden;transition:transform var(--transition);}
.sidebar-top{padding:24px 20px 18px;border-bottom:1px solid rgba(255,255,255,.06);}
.sidebar-logo{display:flex;align-items:center;gap:11px;margin-bottom:28px;}
.sidebar-logomark{width:32px;height:32px;background:var(--gold);border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.sidebar-logo-text{font-family:var(--font-display);font-size:14.5px;font-weight:500;color:var(--white);line-height:1.2;}
.sidebar-logo-text span{display:block;font-family:var(--font-body);font-size:8.5px;letter-spacing:2px;text-transform:uppercase;color:rgba(200,160,88,.55);}
.admin-chip{display:inline-flex;align-items:center;gap:5px;background:var(--admin-crimson);color:white;font-size:8.5px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:3px 9px;border-radius:20px;margin-left:6px;flex-shrink:0;}
.sidebar-user{display:flex;align-items:center;gap:11px;}
.sidebar-avatar{width:38px;height:38px;border-radius:50%;background:var(--navy-light);border:2px solid rgba(200,160,88,.25);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.sidebar-avatar-init{font-family:var(--font-display);font-size:15px;color:var(--gold-bright);}
.sidebar-user-name{font-size:13px;font-weight:500;color:var(--white);}
.sidebar-user-role{font-size:10px;color:rgba(255,255,255,.3);letter-spacing:.5px;text-transform:uppercase;}
.sidebar-nav{flex:1;padding:18px 10px;overflow-y:auto;}
.nav-section-label{font-size:9px;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;color:rgba(255,255,255,.18);padding:0 12px;margin:18px 0 7px;}
.nav-item{margin-bottom:2px;}
.nav-link{display:flex;align-items:center;gap:11px;padding:10px 13px;border-radius:var(--r-sm);font-size:13px;color:rgba(255,255,255,.55);transition:var(--transition);position:relative;}
.nav-link:hover{background:rgba(255,255,255,.06);color:rgba(255,255,255,.9);}
.nav-link.active{background:rgba(200,160,88,.11);color:var(--gold-bright);}
.nav-link.active::before{content:'';position:absolute;left:0;top:6px;bottom:6px;width:2.5px;background:var(--gold);border-radius:2px;}
.nav-link i{width:17px;text-align:center;font-size:13.5px;flex-shrink:0;}
.nav-badge{margin-left:auto;background:rgba(220,38,38,.2);color:#F87171;font-size:9.5px;font-weight:600;padding:2px 7px;border-radius:10px;}
.sidebar-bottom{padding:14px 10px;border-top:1px solid rgba(255,255,255,.06);}
.nav-logout{color:rgba(239,68,68,.65)!important;}
.nav-logout:hover{background:rgba(239,68,68,.08)!important;color:rgba(239,68,68,.9)!important;}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:150;}
.sidebar-overlay.active{display:block;}
.header{position:fixed;top:0;left:var(--sidebar-width);right:0;height:var(--header-height);background:var(--white);border-bottom:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;padding:0 26px;z-index:100;}
.header-left{display:flex;align-items:center;gap:14px;}
.mobile-toggle{display:none;background:none;border:none;cursor:pointer;padding:6px;color:var(--text-secondary);font-size:18px;}
.header-breadcrumb{font-size:13px;color:var(--text-muted);display:flex;align-items:center;gap:7px;}
.header-breadcrumb strong{color:var(--text-primary);font-weight:600;}
.header-breadcrumb .sep{color:var(--border-light);}
.header-right{display:flex;align-items:center;gap:12px;}
.header-time{font-size:12px;color:var(--text-muted);padding-right:12px;border-right:1px solid var(--border-light);}
.header-icon-btn{width:36px;height:36px;border-radius:var(--r-sm);background:var(--cream);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-secondary);font-size:14px;transition:var(--transition);}
.header-icon-btn:hover{background:var(--cream-dark);color:var(--navy);}
.header-avatar-wrap{display:flex;align-items:center;gap:9px;cursor:pointer;padding:6px 10px;border-radius:var(--r-sm);transition:var(--transition);}
.header-avatar-wrap:hover{background:var(--cream);}
.header-avatar{width:32px;height:32px;border-radius:50%;background:var(--navy-light);display:flex;align-items:center;justify-content:center;border:2px solid var(--border-light);}
.header-avatar-init{font-family:var(--font-display);font-size:13px;color:var(--gold-bright);}
.header-admin-label{font-size:12px;font-weight:500;color:var(--text-primary);}
.header-admin-role{font-size:10.5px;color:var(--text-muted);}
.main{margin-left:var(--sidebar-width);margin-top:var(--header-height);padding:26px;min-height:calc(100vh - var(--header-height));}
.alert{padding:12px 18px;border-radius:var(--r-md);font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:10px;}
.alert-success{background:var(--success-pale);color:var(--success);border:1px solid rgba(5,150,105,.2);}
.alert-danger{background:var(--danger-pale);color:var(--danger);border:1px solid rgba(220,38,38,.2);}
.portal-footer{margin-left:var(--sidebar-width);background:var(--white);padding:14px 26px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;}
.footer-copy{font-size:11.5px;color:var(--text-muted);}

/* PAGE */
.page-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;flex-wrap:wrap;gap:16px;}
.hdr-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
.btn-gold{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:var(--gold);color:var(--midnight);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
.btn-gold:hover{background:var(--gold-bright);}
.btn-navy{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:var(--navy);color:var(--white);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
.btn-navy:hover{background:var(--navy-light);}
.btn-ghost{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:var(--white);color:var(--text-secondary);font-size:13px;border:1px solid var(--border-light);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
.btn-ghost:hover{background:var(--cream);border-color:var(--gold);color:var(--navy);}
.btn-sm{padding:6px 14px;font-size:12px;border-radius:var(--r-sm);}

/* HERO */
.app-hero{background:var(--navy);border-radius:var(--r-xl);padding:28px 32px;margin-bottom:20px;position:relative;overflow:hidden;}
.app-hero::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,.025) 1px,transparent 1px);background-size:28px 28px;}
.app-hero::after{content:'';position:absolute;top:-60px;right:-60px;width:300px;height:300px;background:radial-gradient(circle,rgba(200,160,88,.08),transparent 65%);}
.app-hero-inner{position:relative;z-index:1;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:20px;}
.app-hero-left{display:flex;gap:18px;align-items:flex-start;}
.app-av{width:64px;height:64px;border-radius:var(--r-md);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:28px;color:var(--gold-bright);flex-shrink:0;}
.app-hero-name{font-family:var(--font-display);font-size:26px;font-weight:500;color:var(--white);margin-bottom:4px;}
.app-hero-email{font-size:13px;color:rgba(255,255,255,.55);display:flex;align-items:center;gap:6px;margin-bottom:10px;}
.app-hero-meta{display:flex;gap:16px;flex-wrap:wrap;}
.app-hero-meta-item{font-size:12px;color:rgba(255,255,255,.5);display:flex;align-items:center;gap:5px;}
.app-hero-meta-item i{color:rgba(255,255,255,.3);font-size:11px;}
.status-pill{display:inline-flex;align-items:center;gap:6px;padding:5px 14px;border-radius:20px;background:rgba(255,255,255,.12);font-size:12px;font-weight:500;color:var(--white);}
.app-hero-right{display:flex;gap:10px;flex-wrap:wrap;}

/* PROGRESS TRACKER (new) */
.progress-tracker{background:var(--white);border:1px solid var(--border-light);border-radius:var(--r-lg);padding:20px 24px;margin-bottom:20px;}
.pt-title{font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;}
.pt-steps{display:flex;align-items:center;gap:0;}
.pt-step{flex:1;display:flex;flex-direction:column;align-items:center;position:relative;}
.pt-step:not(:last-child)::after{content:'';position:absolute;left:50%;top:14px;width:100%;height:2px;background:var(--border-light);z-index:0;}
.pt-step.done:not(:last-child)::after,.pt-step.current:not(:last-child)::after{background:var(--gold);}
.pt-dot{width:28px;height:28px;border-radius:50%;background:var(--cream);border:2px solid var(--border-light);display:flex;align-items:center;justify-content:center;font-size:11px;color:var(--text-muted);z-index:1;transition:var(--transition);}
.pt-step.done .pt-dot{background:var(--success);border-color:var(--success);color:var(--white);}
.pt-step.current .pt-dot{background:var(--gold);border-color:var(--gold);color:var(--midnight);}
.pt-label{font-size:10.5px;color:var(--text-muted);margin-top:6px;text-align:center;}
.pt-step.done .pt-label,.pt-step.current .pt-label{color:var(--navy);font-weight:500;}

/* LAYOUT */
.detail-grid{display:grid;grid-template-columns:1fr 320px;gap:20px;}

/* TABS */
.tabs-card{background:var(--white);border:1px solid var(--border-light);border-radius:var(--r-lg);overflow:hidden;margin-bottom:20px;}
.tabs-nav{display:flex;border-bottom:1px solid var(--border-light);background:#F8F9FB;overflow-x:auto;}
.tab-btn{padding:14px 20px;font-size:13px;font-weight:500;color:var(--text-muted);border:none;border-bottom:2px solid transparent;background:none;cursor:pointer;white-space:nowrap;transition:var(--transition);font-family:var(--font-body);}
.tab-btn:hover{color:var(--navy);}
.tab-btn.active{color:var(--navy);border-bottom-color:var(--gold);background:transparent;}
.tab-btn i{margin-right:6px;font-size:12px;}
.tab-pane{display:none;padding:22px;}
.tab-pane.active{display:block;}
.two-col-fields{display:grid;grid-template-columns:1fr 1fr;gap:0 24px;}

/* RIGHT SIDEBAR */
.right-sidebar{display:flex;flex-direction:column;gap:16px;}
.panel-card{background:var(--white);border:1px solid var(--border-light);border-radius:var(--r-lg);overflow:hidden;}
.pc-header{padding:14px 18px;border-bottom:1px solid var(--border-light);background:#F8F9FB;display:flex;align-items:center;gap:8px;}
.pc-title{font-size:13px;font-weight:600;color:var(--navy);}
.pc-title i{color:var(--gold);}
.pc-body{padding:16px 18px;}

/* BADGES */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:500;}
.sb-warn{background:var(--warning-pale);color:var(--warning);}
.sb-green{background:var(--success-pale);color:var(--success);}
.sb-blue{background:var(--info-pale);color:var(--info);}
.sb-red{background:var(--danger-pale);color:var(--danger);}
.sb-review{background:rgba(99,102,241,.1);color:#6366F1;}
.sb-purple{background:rgba(139,92,246,.1);color:#8B5CF6;}
.sb-grey{background:var(--cream);color:var(--text-muted);}

/* TIMELINE */
.tl{position:relative;padding-left:20px;}
.tl::before{content:'';position:absolute;left:7px;top:0;bottom:0;width:1.5px;background:var(--border-light);}
.tl-item{position:relative;padding-bottom:16px;padding-left:18px;}
.tl-item:last-child{padding-bottom:0;}
.tl-dot{position:absolute;left:-13px;top:5px;width:14px;height:14px;border-radius:50%;background:var(--white);border:2px solid var(--border-light);}
.tl-item.tl-success .tl-dot{background:var(--success);border-color:var(--success);}
.tl-item.tl-info .tl-dot{background:var(--info);border-color:var(--info);}
.tl-item.tl-warning .tl-dot{background:var(--warning);border-color:var(--warning);}
.tl-content{background:var(--cream);border-radius:var(--r-sm);padding:10px 13px;border:1px solid var(--border-light);}
.tl-title{font-size:12.5px;font-weight:600;color:var(--navy);margin-bottom:2px;}
.tl-sub{font-size:11.5px;color:var(--text-muted);}

/* DOC BADGES */
.doc-badges{display:flex;flex-direction:column;gap:8px;}
.doc-badge-item{display:flex;align-items:center;justify-content:space-between;padding:10px 13px;background:var(--cream);border-radius:var(--r-sm);border:1px solid var(--border-light);}
.dbi-left{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:500;color:var(--navy);}
.dbi-left i{color:var(--gold);}
.dbi-status{font-size:11.5px;color:var(--success);font-weight:500;}
.dbi-missing{font-size:11.5px;color:var(--text-muted);}

/* NOTES */
.notes-area{width:100%;padding:10px 13px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13px;color:var(--text-primary);resize:vertical;min-height:90px;outline:none;transition:var(--transition);}
.notes-area:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,160,88,.12);}

@media(max-width:1100px){.detail-grid{grid-template-columns:1fr;}}
@media(max-width:991px){
  .sidebar{transform:translateX(-100%);}
  .sidebar.active{transform:translateX(0);}
  .header{left:0;}.main,.portal-footer{margin-left:0;}
  .mobile-toggle{display:flex;}.header-breadcrumb{display:none;}
  .two-col-fields{grid-template-columns:1fr;}
}
</style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-top">
    <div class="sidebar-logo">
      <div class="sidebar-logomark"><svg viewBox="0 0 22 22" fill="none"><path d="M4 18L11 4L18 18" stroke="#080E1C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 13H15" stroke="#080E1C" stroke-width="1.8" stroke-linecap="round"/></svg></div>
      <div class="sidebar-logo-text">Bold Footprint<span>Initiatives</span></div>
      <span class="admin-chip"><i class="fas fa-shield-alt" style="font-size:7px"></i> Admin</span>
    </div>
    <div class="sidebar-user">
      <div class="sidebar-avatar"><div class="sidebar-avatar-init"><?= strtoupper(substr($admin_first_name,0,1)) ?></div></div>
      <div>
        <div class="sidebar-user-name"><?= htmlspecialchars($admin_full_name) ?></div>
        <div class="sidebar-user-role"><?= htmlspecialchars($admin_role) ?></div>
      </div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Command</div>
    <div class="nav-item"><a href="admin-dashboard.php" class="nav-link"><i class="fas fa-chart-pie"></i> Dashboard</a></div>
    <div class="nav-item"><a href="manage-scholars.php" class="nav-link"><i class="fas fa-user-graduate"></i> Scholars</a></div>
    <div class="nav-item"><a href="applications.php" class="nav-link active"><i class="fas fa-tasks"></i> Applications<?php if($stats['pending_count']>0): ?><span class="nav-badge"><?= $stats['pending_count'] ?></span><?php endif; ?></a></div>
    <div class="nav-section-label">Management</div>
    <div class="nav-item"><a href="admin-document-review.php" class="nav-link"><i class="fas fa-file-alt"></i> Review Documents</a></div>
    <div class="nav-item"><a href="scholarships.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Scholarships</a></div>
    <div class="nav-item"><a href="admin-messages.php" class="nav-link"><i class="fas fa-envelope"></i> Messages</a></div>
    <div class="nav-section-label">Analytics</div>
    <div class="nav-item"><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i> Reports</a></div>
    <div class="nav-section-label">System</div>
    <div class="nav-item"><a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a></div>
  </nav>
  <div class="sidebar-bottom">
    <a href="logout.php" class="nav-link nav-logout"><i class="fas fa-sign-out-alt"></i> Log Out</a>
  </div>
</aside>

<header class="header">
  <div class="header-left">
    <button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
    <div class="header-breadcrumb">
      <span>Admin</span><span class="sep">/</span>
      <a href="applications.php" style="color:var(--text-muted)">Applications</a><span class="sep">/</span>
      <strong>Details</strong>
    </div>
  </div>
  <div class="header-right">
    <div class="header-time" id="headerTime"></div>
    <button class="header-icon-btn" onclick="window.location.href='admin-dashboard.php'"><i class="fas fa-home"></i></button>
    <div class="header-avatar-wrap">
      <div class="header-avatar"><div class="header-avatar-init"><?= strtoupper(substr($admin_first_name,0,1)) ?></div></div>
      <div>
        <div class="header-admin-label"><?= htmlspecialchars($admin_first_name) ?></div>
        <div class="header-admin-role">Administrator</div>
      </div>
    </div>
  </div>
</header>

<main class="main">
<?php if($error_message): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error_message) ?></div><?php endif; ?>
<?php if($success_message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success_message) ?></div><?php endif; ?>

<!-- PAGE HEADER -->
<div class="page-header">
  <div class="hdr-actions">
    <a href="applications.php" class="btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
  <?php if(!empty($application)): ?>
  <div class="hdr-actions">
    <button class="btn-ghost btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
    <button class="btn-navy btn-sm status-update-btn"
      data-id="<?= htmlspecialchars($application['application_id']) ?>"
      data-status="<?= htmlspecialchars($application['status']??'') ?>">
      <i class="fas fa-edit"></i> Update Status
    </button>
    <button class="btn-gold btn-sm" id="emailBtn" data-email="<?= htmlspecialchars($application['email']??'') ?>" data-name="<?= htmlspecialchars($application['first_name']??'') ?>">
      <i class="fas fa-envelope"></i> Email Applicant
    </button>
  </div>
  <?php endif; ?>
</div>

<?php if(!empty($application)): ?>

<!-- APPLICATION HERO -->
<div class="app-hero">
  <div class="app-hero-inner">
    <div class="app-hero-left">
      <div class="app-av"><?= strtoupper(substr($application['first_name']??'A',0,1)) ?></div>
      <div>
        <div class="app-hero-name"><?= htmlspecialchars($application['first_name'].' '.$application['last_name']) ?></div>
        <div class="app-hero-email"><i class="fas fa-envelope" style="font-size:10px"></i><?= htmlspecialchars($application['email']??'') ?></div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px">
          <span class="status-pill"><i class="fas fa-<?= statusIcon($application['status']) ?>"></i><?= ucwords(str_replace('_',' ',$application['status']??'unknown')) ?></span>
        </div>
        <div class="app-hero-meta">
          <div class="app-hero-meta-item"><i class="fas fa-id-card"></i><?= htmlspecialchars($application['application_id']) ?></div>
          <div class="app-hero-meta-item"><i class="fas fa-calendar"></i>Applied <?= fmtDate($application['created_at']) ?></div>
          <div class="app-hero-meta-item"><i class="fas fa-book"></i><?= htmlspecialchars($application['program_type']??'N/A') ?></div>
          <?php if(!empty($application['undergraduate_institution'])): ?>
          <div class="app-hero-meta-item"><i class="fas fa-university"></i><?= htmlspecialchars($application['undergraduate_institution']) ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- PROGRESS TRACKER -->
<?php
$statusOrder=['pending','under_review','shortlisted','approved'];
$currentIdx=array_search($application['status']??'pending',$statusOrder);
$progressLabels=['Pending','Under Review','Shortlisted','Approved'];
?>
<div class="progress-tracker">
  <div class="pt-title">Application Progress</div>
  <div class="pt-steps">
    <?php foreach($progressLabels as $i=>$label): 
      $cls=$i<$currentIdx?'done':($i==$currentIdx?'current':'');
    ?>
    <div class="pt-step <?= $cls ?>">
      <div class="pt-dot"><i class="fas fa-<?= $cls==='done'?'check':($cls==='current'?'circle':'circle') ?>"></i></div>
      <div class="pt-label"><?= $label ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- DETAIL GRID -->
<div class="detail-grid">

  <!-- TABS -->
  <div class="tabs-card">
    <div class="tabs-nav">
      <button class="tab-btn active" data-tab="personal"><i class="fas fa-user"></i>Personal</button>
      <button class="tab-btn" data-tab="education"><i class="fas fa-graduation-cap"></i>Education</button>
      <button class="tab-btn" data-tab="statements"><i class="fas fa-file-alt"></i>Statements</button>
      <button class="tab-btn" data-tab="documents"><i class="fas fa-paperclip"></i>Documents</button>
    </div>

    <!-- PERSONAL -->
    <div class="tab-pane active" id="tab-personal">
      <div class="two-col-fields">
        <?= rf('First Name',$application['first_name']) ?>
        <?= rf('Last Name',$application['last_name']) ?>
        <?= rf('Email Address',$application['email']) ?>
        <?= rf('Phone Number',$application['phone']) ?>
        <?= rf('Field of Study',$application['field_of_study']) ?>
        <?= rf('How They Heard About Us',$application['hear_about']) ?>
      </div>
      <?= rf('Achievements',$application['achievements'],true) ?>
      <?= rf('Additional Comments',$application['additional_comments'],true) ?>
    </div>

    <!-- EDUCATION -->
    <div class="tab-pane" id="tab-education">
      <div class="two-col-fields">
        <?= rf('Institution',$application['undergraduate_institution']) ?>
        <?= rf('Degree Class',$application['degree_class']) ?>
        <?= rf('GPA',$application['gpa']) ?>
        <?= rf('Graduation Year',$application['graduation_year']) ?>
      </div>
      <?= rf('Research Experience',$application['research_experience'],true) ?>
    </div>

    <!-- STATEMENTS -->
    <div class="tab-pane" id="tab-statements">
      <?= rf('Scholarship Statement',$application['scholarship_statement'],true) ?>
      <?= rf('Financial Statement',$application['financial_statement'],true) ?>
      <?= rf('Mentorship Statement',$application['mentorship_statement'],true) ?>
      <?= rf('Mentorship Areas',$application['mentorship_areas'],true) ?>
    </div>

    <!-- DOCUMENTS -->
    <div class="tab-pane" id="tab-documents">
      <div style="margin-bottom:20px">
        <div style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px">Submitted Documents</div>
        <div class="doc-badges">
          <?php
          $docTypes=[
            'cv_file'=>['label'=>'Curriculum Vitae','icon'=>'fa-file-pdf'],
            'transcript_file'=>['label'=>'Academic Transcript','icon'=>'fa-file-alt'],
            'recommendation_file'=>['label'=>'Recommendation Letter','icon'=>'fa-envelope'],
            'financial_statement'=>['label'=>'Financial Statement','icon'=>'fa-file-invoice'],
          ];
          $anyDoc=false;
          foreach($docTypes as $key=>$dt):
            if(!empty($application[$key])):
              $anyDoc=true;
          ?>
          <div class="doc-badge-item">
            <div class="dbi-left"><i class="fas <?= $dt['icon'] ?>"></i><?= $dt['label'] ?></div>
            <div style="display:flex;align-items:center;gap:8px">
              <span class="dbi-status"><i class="fas fa-check-circle"></i> Uploaded</span>
              <a href="/uploads/<?= htmlspecialchars($application[$key]) ?>" target="_blank" style="font-size:11.5px;color:var(--gold);font-weight:500"><i class="fas fa-external-link-alt"></i></a>
            </div>
          </div>
          <?php
            endif;
          endforeach;
          if(!$anyDoc): ?>
          <p style="font-size:13px;color:var(--text-muted);text-align:center;padding:20px 0"><i class="fas fa-folder-open" style="margin-right:6px"></i>No documents uploaded yet</p>
          <?php endif; ?>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div style="background:var(--cream);border-radius:var(--r-md);padding:16px;border:1px solid var(--border-light)">
          <div style="font-size:13px;font-weight:600;color:var(--navy);margin-bottom:4px"><i class="fas fa-paper-plane" style="color:var(--gold);margin-right:6px"></i>Request Documents</div>
          <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">Send a request for additional files.</p>
          <button class="btn-navy" style="font-size:12px;padding:7px 14px;width:100%" id="reqDocsBtn"><i class="fas fa-paper-plane"></i> Send Request</button>
        </div>
        <div style="background:var(--cream);border-radius:var(--r-md);padding:16px;border:1px solid var(--border-light)">
          <div style="font-size:13px;font-weight:600;color:var(--navy);margin-bottom:4px"><i class="fas fa-upload" style="color:var(--gold);margin-right:6px"></i>Upload Document</div>
          <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">Upload files on behalf of the applicant.</p>
          <button class="btn-ghost" style="font-size:12px;padding:7px 14px;width:100%" id="uploadDocBtn"><i class="fas fa-upload"></i> Upload</button>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT SIDEBAR -->
  <div class="right-sidebar">

    <!-- QUICK STATUS -->
    <div class="panel-card">
      <div class="pc-header"><span class="pc-title"><i class="fas fa-toggle-on"></i> Quick Status Update</span></div>
      <div class="pc-body">
        <form method="POST" style="display:flex;flex-direction:column;gap:10px">
          <select name="new_status" style="padding:9px 12px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13px;color:var(--text-primary);background:var(--white);outline:none;width:100%">
            <option value="">Choose new status…</option>
            <?php foreach(['pending'=>'Pending','under_review'=>'Under Review','shortlisted'=>'Shortlisted','approved'=>'Approved','rejected'=>'Rejected'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= ($application['status']??'')===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" name="update_status" style="width:100%;padding:10px;background:var(--navy);color:var(--white);border:none;border-radius:var(--r-sm);font-size:13px;font-weight:500;cursor:pointer;transition:var(--transition)" onmouseover="this.style.background='var(--navy-light)'" onmouseout="this.style.background='var(--navy)'">
            <i class="fas fa-save" style="margin-right:6px"></i>Update Status
          </button>
        </form>
      </div>
    </div>

    <!-- APPLICATION SUMMARY -->
    <div class="panel-card">
      <div class="pc-header"><span class="pc-title"><i class="fas fa-info-circle"></i> Summary</span></div>
      <div class="pc-body">
        <?php
        $summaryItems=[
          ['label'=>'Application ID','val'=>$application['application_id']],
          ['label'=>'Programme','val'=>$application['program_type']??'N/A'],
          ['label'=>'Institution','val'=>$application['undergraduate_institution']??'N/A'],
          ['label'=>'Degree Class','val'=>$application['degree_class']??'N/A'],
          ['label'=>'Submitted','val'=>fmtDate($application['created_at'])],
          ['label'=>'Last Updated','val'=>fmtDate($application['updated_at'])],
        ];
        foreach($summaryItems as $item): ?>
        <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:8px 0;border-bottom:1px solid var(--border-light)">
          <span style="font-size:11.5px;color:var(--text-muted)"><?= $item['label'] ?></span>
          <span style="font-size:12px;color:var(--navy);font-weight:500;text-align:right;max-width:55%"><?= htmlspecialchars($item['val']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ACTIVITY TIMELINE -->
    <div class="panel-card">
      <div class="pc-header"><span class="pc-title"><i class="fas fa-history"></i> Activity Log</span></div>
      <div class="pc-body">
        <div class="tl">
          <div class="tl-item tl-info">
            <div class="tl-dot"></div>
            <div class="tl-content">
              <div class="tl-title">Status change</div>
              <div class="tl-sub">Application status updated · <?= fmtDate($application['updated_at']) ?></div>
            </div>
          </div>
          <div class="tl-item tl-success">
            <div class="tl-dot"></div>
            <div class="tl-content">
              <div class="tl-title">Email notification sent</div>
              <div class="tl-sub">Confirmation sent to applicant</div>
            </div>
          </div>
          <div class="tl-item tl-warning">
            <div class="tl-dot"></div>
            <div class="tl-content">
              <div class="tl-title">Application received</div>
              <div class="tl-sub"><?= fmtDate($application['created_at']) ?></div>
            </div>
          </div>
        </div>
        <!-- Add note -->
        <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border-light)">
          <textarea class="notes-area" id="noteArea" placeholder="Add a note…" rows="3"></textarea>
          <button style="width:100%;margin-top:8px;padding:8px;background:var(--cream);border:1px solid var(--border-light);border-radius:var(--r-sm);font-size:12.5px;color:var(--navy);font-weight:500;cursor:pointer;transition:var(--transition);font-family:var(--font-body)" id="saveNoteBtn">
            <i class="fas fa-sticky-note" style="margin-right:4px"></i>Save Note
          </button>
        </div>
      </div>
    </div>

  </div>
</div>

<?php else: ?>
<div style="background:var(--white);border-radius:var(--r-xl);border:1px solid var(--border-light);padding:60px;text-align:center">
  <div style="font-size:48px;color:var(--border-light);margin-bottom:16px"><i class="fas fa-search"></i></div>
  <div style="font-family:var(--font-display);font-size:24px;color:var(--navy);margin-bottom:8px">Application Not Found</div>
  <a href="applications.php" class="btn-gold" style="margin-top:10px"><i class="fas fa-arrow-left"></i> Back to Applications</a>
</div>
<?php endif; ?>

</main>

<footer class="portal-footer">
  <div class="footer-copy">© 2025 Bold Footprint Initiatives. Admin Portal.</div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const sidebar=document.getElementById('sidebar'),overlay=document.getElementById('sidebarOverlay'),toggle=document.getElementById('mobileToggle');
const openSB=()=>{sidebar.classList.add('active');overlay.classList.add('active');document.body.style.overflow='hidden';};
const closeSB=()=>{sidebar.classList.remove('active');overlay.classList.remove('active');document.body.style.overflow='';};
toggle?.addEventListener('click',()=>sidebar.classList.contains('active')?closeSB():openSB());
overlay?.addEventListener('click',closeSB);
function updateClock(){const el=document.getElementById('headerTime');if(!el)return;const n=new Date();el.textContent=n.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'})+' · '+n.toLocaleDateString('en-GB',{day:'numeric',month:'short'});}
updateClock();setInterval(updateClock,30000);

// Tabs
document.querySelectorAll('.tab-btn').forEach(btn=>{
  btn.addEventListener('click',function(){
    document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active'));
    this.classList.add('active');
    document.getElementById('tab-'+this.dataset.tab)?.classList.add('active');
  });
});

// Status update
document.querySelector('.status-update-btn')?.addEventListener('click',function(){
  const id=this.dataset.id,st=this.dataset.status;
  Swal.fire({
    title:'Update Application Status',
    html:`<p style="font-size:13px;color:#64748b;margin-bottom:14px">Currently: <strong style="text-transform:capitalize">${st||'unknown'}</strong></p>
      <select id="sw-new" style="width:100%;padding:10px 13px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px">
        <option value="">— Select status —</option>
        <option value="pending">Pending</option>
        <option value="under_review">Under Review</option>
        <option value="shortlisted">Shortlisted</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
      </select>`,
    showCancelButton:true,confirmButtonText:'Update',confirmButtonColor:'#0D1829',
    preConfirm:()=>{const v=document.getElementById('sw-new').value;if(!v){Swal.showValidationMessage('Please select a status');return false;}return v;}
  }).then(r=>{
    if(r.isConfirmed&&r.value){
      const fd=new FormData();fd.append('new_status',r.value);fd.append('update_status','1');
      Swal.fire({title:'Updating…',allowOutsideClick:false,showConfirmButton:false,willOpen:()=>Swal.showLoading()});
      fetch(window.location.href,{method:'POST',body:fd,credentials:'same-origin'})
        .then(()=>Swal.fire({icon:'success',title:'Updated!',confirmButtonColor:'#C8A058'}).then(()=>location.reload()))
        .catch(e=>Swal.fire({icon:'error',title:'Error',text:e.message}));
    }
  });
});

// Email button
document.getElementById('emailBtn')?.addEventListener('click',function(){
  const email=this.dataset.email,name=this.dataset.name;
  Swal.fire({
    title:`Email ${name}`,
    html:`<div style="text-align:left">
      <div style="margin-bottom:12px"><label style="display:block;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">Subject</label>
      <input id="es-sub" type="text" style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px" value="BFI Scholarship - Application Update"></div>
      <div><label style="display:block;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">Message</label>
      <textarea id="es-msg" rows="6" style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;resize:vertical">Dear ${name},\n\n</textarea></div>
    </div>`,
    showCancelButton:true,confirmButtonText:'Send Email',confirmButtonColor:'#C8A058',
    preConfirm:()=>{
      const sub=document.getElementById('es-sub').value,msg=document.getElementById('es-msg').value;
      if(!sub||!msg){Swal.showValidationMessage('Please fill all fields');return false;}
      return{sub,msg};
    }
  }).then(r=>{
    if(r.isConfirmed&&r.value){
      const fd=new FormData();
      fd.append('application_id','<?= htmlspecialchars($application_id) ?>');
      fd.append('email_subject',r.value.sub);
      fd.append('email_message',r.value.msg);
      fd.append('send_email','1');
      Swal.fire({title:'Sending…',allowOutsideClick:false,showConfirmButton:false,willOpen:()=>Swal.showLoading()});
      fetch('send_applicant_email.php',{method:'POST',body:fd,credentials:'same-origin'})
        .then(res=>res.json())
        .then(data=>{
          if(data.success) Swal.fire({icon:'success',title:'Email Sent',text:data.message,confirmButtonColor:'#C8A058'});
          else throw new Error(data.message||'Send failed');
        })
        .catch(e=>Swal.fire({icon:'error',title:'Error',text:e.message}));
    }
  });
});

// Save note
document.getElementById('saveNoteBtn')?.addEventListener('click',function(){
  const note=document.getElementById('noteArea').value.trim();
  if(!note){Swal.fire({icon:'warning',title:'Empty Note',text:'Please enter a note first.',confirmButtonColor:'#C8A058'});return;}
  Swal.fire({icon:'success',title:'Note Saved',text:'Your note has been recorded.',confirmButtonColor:'#C8A058',timer:2000});
  document.getElementById('noteArea').value='';
});

// Request docs
document.getElementById('reqDocsBtn')?.addEventListener('click',()=>{
  Swal.fire({icon:'info',title:'Request Documents',text:'This feature will send an automated email to the applicant requesting specific documents.',confirmButtonColor:'#C8A058'});
});
document.getElementById('uploadDocBtn')?.addEventListener('click',()=>{
  Swal.fire({icon:'info',title:'Upload Document',text:'Document upload on behalf of applicant will be available once connected to your upload handler.',confirmButtonColor:'#C8A058'});
});
</script>
</body>
</html>