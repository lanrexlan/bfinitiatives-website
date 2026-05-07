<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: admin-login.php'); exit();
}
require_once 'includes/config.php';
require_once 'includes/db.php';

$error_message = '';
$filter_year    = $_GET['year']    ?? date('Y');
$filter_program = $_GET['program'] ?? 'all';

$monthly_applications = $status_distribution = $program_distribution = $institution_distribution = [];
$available_years = $available_programs = [];
$total_applications = $approved_count = $pending_count = 0;

$admin_full_name  = $_SESSION['admin_name'] ?? 'Admin';
$admin_first_name = explode(' ', $admin_full_name)[0];
$admin_role       = ucfirst($_SESSION['admin_role'] ?? 'Administrator');

try {
    $db=new Database();$conn=$db->getConnection();
    $available_years=$conn->query("SELECT DISTINCT EXTRACT(YEAR FROM created_at) y FROM scholarship_applications ORDER BY y DESC")->fetchAll(PDO::FETCH_COLUMN);
    if(empty($available_years)) $available_years=[date('Y')];
    $available_programs=$conn->query("SELECT DISTINCT program_type FROM scholarship_applications WHERE program_type IS NOT NULL ORDER BY program_type")->fetchAll(PDO::FETCH_COLUMN);

    $wc="WHERE EXTRACT(YEAR FROM created_at)=:y"; $pp=[':y'=>$filter_year];
    if($filter_program!=='all'){$wc.=" AND program_type=:p";$pp[':p']=$filter_program;}

    // Monthly
    $mq=$conn->prepare("SELECT EXTRACT(MONTH FROM created_at) m,COUNT(*) cnt FROM scholarship_applications $wc GROUP BY m ORDER BY m");
    $mq->execute($pp); $mc=array_fill(0,12,0);
    while($r=$mq->fetch(PDO::FETCH_ASSOC)) $mc[(int)$r['m']-1]=(int)$r['cnt'];
    $monthly_applications=['labels'=>["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],'data'=>$mc];
    $total_applications=array_sum($mc);

    // Status
    $sq=$conn->prepare("SELECT status,COUNT(*) cnt FROM scholarship_applications $wc GROUP BY status ORDER BY cnt DESC");
    $sq->execute($pp); $sl=[];$sd=[];$sc=[];
    $colorMap=['pending'=>'#D97706','under_review'=>'#0284C7','shortlisted'=>'#8B5CF6','approved'=>'#059669','rejected'=>'#DC2626'];
    while($r=$sq->fetch(PDO::FETCH_ASSOC)){
      $sl[]=ucwords(str_replace('_',' ',$r['status']));
      $sd[]=(int)$r['cnt'];$sc[]=$colorMap[$r['status']]??'#6c757d';
      if($r['status']==='approved') $approved_count=(int)$r['cnt'];
      if($r['status']==='pending')  $pending_count=(int)$r['cnt'];
    }
    $status_distribution=['labels'=>$sl,'data'=>$sd,'colors'=>$sc];

    // Programs
    $pq=$conn->prepare("SELECT program_type,COUNT(*) cnt FROM scholarship_applications $wc GROUP BY program_type ORDER BY cnt DESC");
    $pq->execute($pp); $pl=[];$pd=[];
    while($r=$pq->fetch(PDO::FETCH_ASSOC)){$pl[]=$r['program_type'];$pd[]=(int)$r['cnt'];}
    $program_distribution=['labels'=>$pl,'data'=>$pd];

    // Institutions
    $iq=$conn->prepare("SELECT undergraduate_institution,COUNT(*) cnt FROM scholarship_applications $wc GROUP BY undergraduate_institution ORDER BY cnt DESC LIMIT 10");
    $iq->execute($pp); $il=[];$id=[];
    while($r=$iq->fetch(PDO::FETCH_ASSOC)){$il[]=$r['undergraduate_institution'];$id[]=(int)$r['cnt'];}
    $institution_distribution=['labels'=>$il,'data'=>$id];

    // Sidebar badges
    $dp=$conn->query("SELECT COUNT(*) FROM user_documents WHERE review_status='pending'")->fetchColumn();
    $ap=$conn->query("SELECT COUNT(*) FROM scholarship_applications WHERE status='pending'")->fetchColumn();

} catch(Exception $e){ $error_message="Database error: ".$e->getMessage(); }

$approval_rate = $total_applications>0?round(($approved_count/$total_applications)*100):0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reports & Analytics · Admin Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
.alert-danger{background:var(--danger-pale);color:var(--danger);border:1px solid rgba(220,38,38,.2);}
.portal-footer{margin-left:var(--sidebar-width);background:var(--white);padding:14px 26px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;}
.footer-copy{font-size:11.5px;color:var(--text-muted);}

/* PAGE */
.page-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;flex-wrap:wrap;gap:16px;}
.page-eyebrow{font-size:9.5px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:var(--gold);margin-bottom:4px;}
.page-title{font-family:var(--font-display);font-size:clamp(24px,2.5vw,32px);font-weight:500;color:var(--navy);}
.page-title em{font-style:italic;color:var(--gold);}
.page-sub{font-size:13px;color:var(--text-muted);margin-top:4px;}
.hdr-actions{display:flex;gap:10px;align-items:center;}
.btn-gold{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:var(--gold);color:var(--midnight);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
.btn-gold:hover{background:var(--gold-bright);}
.btn-ghost{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:var(--white);color:var(--text-secondary);font-size:13px;border:1px solid var(--border-light);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
.btn-ghost:hover{background:var(--cream);border-color:var(--gold);color:var(--navy);}

/* STATS */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:22px;}
.stat-card{background:var(--white);border-radius:var(--r-lg);padding:22px 24px;border:1px solid var(--border-light);transition:var(--transition);position:relative;overflow:hidden;}
.stat-card:hover{transform:translateY(-4px);box-shadow:0 10px 30px rgba(8,14,28,.1);}
.stat-icon{position:absolute;top:18px;right:18px;width:44px;height:44px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:17px;}
.stat-label{font-size:12px;color:var(--text-muted);margin-bottom:8px;font-weight:400;}
.stat-value{font-family:var(--font-display);font-size:38px;font-weight:500;color:var(--navy);line-height:1;margin-bottom:6px;}
.stat-meta{font-size:11.5px;color:var(--text-muted);}

/* FILTER */
.filter-panel{background:var(--white);border:1px solid var(--border-light);border-radius:var(--r-lg);padding:18px 22px;margin-bottom:22px;}
.filter-row{display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;}
.fg{display:flex;flex-direction:column;gap:5px;flex:1;min-width:140px;}
.fg label{font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;}
.fg select{padding:9px 13px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13px;color:var(--text-primary);background:var(--white);outline:none;transition:var(--transition);}
.fg select:focus{border-color:var(--gold);}

/* CHART CARD */
.chart-card{background:var(--white);border-radius:var(--r-lg);padding:22px 24px;border:1px solid var(--border-light);margin-bottom:20px;}
.chart-title{font-family:var(--font-display);font-size:20px;font-weight:500;color:var(--navy);margin-bottom:4px;}
.chart-title em{font-style:italic;color:var(--gold);}
.chart-sub{font-size:12px;color:var(--text-muted);margin-bottom:18px;}
.chart-wrap{position:relative;height:280px;}

/* TABLE */
.data-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);overflow:hidden;margin-bottom:20px;}
.dc-header{padding:18px 22px;border-bottom:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;background:#F8F9FB;}
.dc-title{font-family:var(--font-display);font-size:19px;color:var(--navy);}
.dc-title em{font-style:italic;color:var(--gold);}
.data-table{width:100%;border-collapse:separate;border-spacing:0;}
.data-table th{background:#F8F9FB;color:var(--text-muted);font-weight:600;font-size:10.5px;letter-spacing:.8px;text-transform:uppercase;padding:12px 18px;text-align:left;border-bottom:1px solid var(--border-light);}
.data-table td{padding:13px 18px;border-bottom:1px solid var(--border-light);font-size:13.5px;vertical-align:middle;}
.data-table tbody tr:last-child td{border-bottom:none;}
.data-table tbody tr:hover{background:#FAFBFD;}
.progress-bar-wrap{display:flex;align-items:center;gap:10px;}
.prog-bar{flex:1;height:6px;background:var(--cream);border-radius:3px;overflow:hidden;}
.prog-fill{height:100%;background:linear-gradient(to right,var(--navy),var(--gold));border-radius:3px;transition:width .6s var(--ease);}
.prog-pct{font-size:12px;font-weight:600;color:var(--navy);min-width:36px;text-align:right;}
.rank-num{width:28px;height:28px;background:var(--cream);border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:var(--navy);}
.rank-num.top{background:linear-gradient(135deg,var(--gold),var(--gold-bright));color:var(--midnight);}

@media(max-width:1200px){.stats-row{grid-template-columns:repeat(2,1fr);}}
@media(max-width:991px){
  .sidebar{transform:translateX(-100%);}
  .sidebar.active{transform:translateX(0);}
  .header{left:0;}.main,.portal-footer{margin-left:0;}
  .mobile-toggle{display:flex;}.header-breadcrumb{display:none;}
}
@media(max-width:768px){.stats-row{grid-template-columns:1fr 1fr;}}
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
    <div class="nav-item"><a href="applications.php" class="nav-link"><i class="fas fa-tasks"></i> Applications<?php if(($ap??0)>0): ?><span class="nav-badge"><?= $ap ?></span><?php endif; ?></a></div>
    <div class="nav-section-label">Management</div>
    <div class="nav-item"><a href="admin-document-review.php" class="nav-link"><i class="fas fa-file-alt"></i> Review Documents<?php if(($dp??0)>0): ?><span class="nav-badge"><?= $dp ?></span><?php endif; ?></a></div>
    <div class="nav-item"><a href="scholarships.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Scholarships</a></div>
    <div class="nav-item"><a href="admin-messages.php" class="nav-link"><i class="fas fa-envelope"></i> Messages</a></div>
    <div class="nav-section-label">Analytics</div>
    <div class="nav-item"><a href="reports.php" class="nav-link active"><i class="fas fa-chart-bar"></i> Reports</a></div>
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
      <strong>Reports & Analytics</strong>
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

<!-- PAGE HEADER -->
<div class="page-header">
  <div>
    <div class="page-eyebrow"><i class="fas fa-chart-bar" style="margin-right:5px"></i> Data Intelligence</div>
    <h1 class="page-title">Reports &amp; <em>Analytics</em></h1>
    <p class="page-sub">Insights into scholarship applications and scholar performance</p>
  </div>
  <div class="hdr-actions">
    <button class="btn-ghost" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
    <button class="btn-gold" id="exportBtn"><i class="fas fa-download"></i> Export</button>
  </div>
</div>

<!-- FILTERS -->
<div class="filter-panel">
  <form method="GET" class="filter-row">
    <div class="fg" style="max-width:160px">
      <label>Year</label>
      <select name="year">
        <?php foreach($available_years as $y): ?>
        <option value="<?= $y ?>" <?= $filter_year==$y?'selected':'' ?>><?= $y ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="fg" style="max-width:220px">
      <label>Programme</label>
      <select name="program">
        <option value="all" <?= $filter_program==='all'?'selected':'' ?>>All Programmes</option>
        <?php foreach($available_programs as $p): ?>
        <option value="<?= htmlspecialchars($p) ?>" <?= $filter_program===$p?'selected':'' ?>><?= htmlspecialchars($p) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn-gold" style="margin-top:auto"><i class="fas fa-filter"></i> Apply</button>
    <?php if($filter_program!=='all'): ?><a href="reports.php?year=<?= $filter_year ?>" class="btn-ghost" style="margin-top:auto"><i class="fas fa-times"></i></a><?php endif; ?>
  </form>
</div>

<!-- STATS -->
<div class="stats-row">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(13,24,41,.07);color:var(--navy)"><i class="fas fa-file-alt"></i></div>
    <div class="stat-label">Total Applications</div>
    <div class="stat-value"><?= number_format($total_applications) ?></div>
    <div class="stat-meta">In <?= $filter_year ?><?= $filter_program!=='all'?' · '.$filter_program:'' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:var(--success-pale);color:var(--success)"><i class="fas fa-check-circle"></i></div>
    <div class="stat-label">Approved</div>
    <div class="stat-value" style="color:var(--success)"><?= number_format($approved_count) ?></div>
    <div class="stat-meta">Scholarship recipients</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:var(--warning-pale);color:var(--warning)"><i class="fas fa-clock"></i></div>
    <div class="stat-label">Pending</div>
    <div class="stat-value" style="color:var(--warning)"><?= number_format($pending_count) ?></div>
    <div class="stat-meta">Awaiting decision</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:var(--info-pale);color:var(--info)"><i class="fas fa-percentage"></i></div>
    <div class="stat-label">Approval Rate</div>
    <div class="stat-value" style="color:var(--info)"><?= $approval_rate ?>%</div>
    <div class="stat-meta">
      <div style="height:4px;background:var(--cream);border-radius:2px;margin-top:6px;overflow:hidden">
        <div style="height:100%;width:<?= $approval_rate ?>%;background:var(--info);border-radius:2px;transition:width .6s"></div>
      </div>
    </div>
  </div>
</div>

<!-- MONTHLY CHART -->
<div class="chart-card">
  <div class="chart-title">Monthly Application <em>Trends</em></div>
  <div class="chart-sub"><?= $filter_year ?> — Total applications by month</div>
  <div class="chart-wrap"><canvas id="monthlyChart"></canvas></div>
</div>

<!-- STATUS + PROGRAMS -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
  <div class="chart-card" style="margin-bottom:0">
    <div class="chart-title">Status <em>Distribution</em></div>
    <div class="chart-sub">Breakdown by review status</div>
    <div class="chart-wrap"><canvas id="statusChart"></canvas></div>
  </div>
  <div class="chart-card" style="margin-bottom:0">
    <div class="chart-title">Applications by <em>Programme</em></div>
    <div class="chart-sub">Volume per programme type</div>
    <div class="chart-wrap"><canvas id="programChart"></canvas></div>
  </div>
</div>

<!-- TOP INSTITUTIONS -->
<?php if(!empty($institution_distribution['labels'])): ?>
<div class="data-card">
  <div class="dc-header">
    <div class="dc-title">Top <em>Institutions</em></div>
    <span style="font-size:12px;color:var(--text-muted)">Top <?= count($institution_distribution['labels']) ?> by application volume</span>
  </div>
  <div style="overflow-x:auto">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width:50px">#</th>
          <th>Institution</th>
          <th style="width:100px;text-align:center">Applications</th>
          <th style="width:250px">Share</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $totalInst=array_sum($institution_distribution['data']);
        foreach($institution_distribution['labels'] as $i=>$inst):
          $cnt=$institution_distribution['data'][$i];
          $pct=$totalInst>0?round(($cnt/$totalInst)*100,1):0;
        ?>
        <tr>
          <td><div class="rank-num <?= $i<3?'top':'' ?>"><?= $i+1 ?></div></td>
          <td style="font-weight:500;color:var(--navy)"><?= htmlspecialchars($inst) ?></td>
          <td style="text-align:center;font-weight:600;color:var(--navy)"><?= number_format($cnt) ?></td>
          <td>
            <div class="progress-bar-wrap">
              <div class="prog-bar"><div class="prog-fill" style="width:<?= $pct ?>%"></div></div>
              <span class="prog-pct"><?= $pct ?>%</span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

</main>

<footer class="portal-footer">
  <div class="footer-copy">© 2025 Bold Footprint Initiatives. Admin Portal.</div>
</footer>

<script>
const sidebar=document.getElementById('sidebar'),overlay=document.getElementById('sidebarOverlay'),toggle=document.getElementById('mobileToggle');
const openSB=()=>{sidebar.classList.add('active');overlay.classList.add('active');document.body.style.overflow='hidden';};
const closeSB=()=>{sidebar.classList.remove('active');overlay.classList.remove('active');document.body.style.overflow='';};
toggle?.addEventListener('click',()=>sidebar.classList.contains('active')?closeSB():openSB());
overlay?.addEventListener('click',closeSB);
function updateClock(){const el=document.getElementById('headerTime');if(!el)return;const n=new Date();el.textContent=n.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'})+' · '+n.toLocaleDateString('en-GB',{day:'numeric',month:'short'});}
updateClock();setInterval(updateClock,30000);

Chart.defaults.font.family="'Outfit', -apple-system, sans-serif";
Chart.defaults.font.size=12;

const commonTooltip={backgroundColor:'#fff',titleColor:'#0D1829',bodyColor:'#4A526A',borderColor:'#E8E4DA',borderWidth:1,padding:12,boxPadding:6,cornerRadius:8};

// Monthly
new Chart(document.getElementById('monthlyChart'),{
  type:'line',
  data:{
    labels:<?= json_encode($monthly_applications['labels']) ?>,
    datasets:[{
      label:'Applications',
      data:<?= json_encode($monthly_applications['data']) ?>,
      borderColor:'#C8A058',
      backgroundColor:'rgba(200,160,88,.08)',
      borderWidth:2.5,
      pointBackgroundColor:'#fff',
      pointBorderColor:'#C8A058',
      pointBorderWidth:2,
      pointRadius:5,
      pointHoverRadius:7,
      tension:.4,fill:true
    }]
  },
  options:{
    responsive:true,maintainAspectRatio:false,
    plugins:{legend:{display:false},tooltip:{...commonTooltip,callbacks:{title:c=>`${c[0].label} <?= $filter_year ?>`,label:c=>`Applications: ${c.raw}`}}},
    scales:{
      x:{grid:{display:false},ticks:{font:{size:11}}},
      y:{beginAtZero:true,grid:{borderDash:[3,3],drawBorder:false},ticks:{precision:0,font:{size:11}}}
    }
  }
});

// Status
new Chart(document.getElementById('statusChart'),{
  type:'doughnut',
  data:{
    labels:<?= json_encode($status_distribution['labels']) ?>,
    datasets:[{data:<?= json_encode($status_distribution['data']) ?>,backgroundColor:<?= json_encode($status_distribution['colors']) ?>,borderWidth:0,hoverOffset:12}]
  },
  options:{
    responsive:true,maintainAspectRatio:false,cutout:'65%',
    plugins:{
      legend:{position:'right',labels:{boxWidth:10,padding:14,usePointStyle:true,pointStyle:'circle',font:{size:11.5}}},
      tooltip:{...commonTooltip,callbacks:{label:c=>{const t=c.dataset.data.reduce((a,b)=>a+b,0);return`${c.label}: ${c.raw} (${Math.round(c.raw/t*100)}%)`;} }}
    }
  }
});

// Programs
new Chart(document.getElementById('programChart'),{
  type:'bar',
  data:{
    labels:<?= json_encode($program_distribution['labels']) ?>,
    datasets:[{
      label:'Applications',
      data:<?= json_encode($program_distribution['data']) ?>,
      backgroundColor:['#0D1829','#1C2F52','#C8A058','#E0B96C','#8A92A8'],
      borderRadius:8,borderSkipped:false
    }]
  },
  options:{
    responsive:true,maintainAspectRatio:false,
    plugins:{legend:{display:false},tooltip:{...commonTooltip}},
    scales:{
      x:{grid:{display:false,drawBorder:false},ticks:{font:{size:11}}},
      y:{beginAtZero:true,grid:{borderDash:[3,3],drawBorder:false},ticks:{precision:0,font:{size:11}}}
    },
    barPercentage:.7
  }
});

// Export
document.getElementById('exportBtn')?.addEventListener('click',()=>{
  const opts=document.createElement('div');
  opts.style.cssText='position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;display:flex;align-items:center;justify-content:center';
  opts.innerHTML=`<div style="background:#fff;border-radius:20px;padding:28px;width:360px;max-width:90vw">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
      <h5 style="font-family:Cormorant Garamond,serif;font-size:20px;color:#0D1829">Export Report</h5>
      <button onclick="this.closest('div').parentNode.remove()" style="background:none;border:none;font-size:18px;cursor:pointer;color:#94a3b8">×</button>
    </div>
    <div style="display:grid;gap:10px">
      <button onclick="window.location.href='export_reports.php?format=csv&year=<?= $filter_year ?>';this.closest('div').parentNode.remove()"
        style="padding:14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;cursor:pointer;font-family:Outfit,sans-serif;font-size:13px;display:flex;align-items:center;gap:12px;text-align:left">
        <i class="fas fa-file-csv" style="font-size:20px;color:#059669"></i><div><strong>CSV Format</strong><br><small style="color:#94a3b8">Spreadsheet compatible</small></div>
      </button>
      <button onclick="window.print();this.closest('div').parentNode.remove()"
        style="padding:14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;cursor:pointer;font-family:Outfit,sans-serif;font-size:13px;display:flex;align-items:center;gap:12px;text-align:left">
        <i class="fas fa-file-pdf" style="font-size:20px;color:#DC2626"></i><div><strong>PDF Report</strong><br><small style="color:#94a3b8">Print-ready format</small></div>
      </button>
    </div>
  </div>`;
  document.body.appendChild(opts);
  opts.addEventListener('click',e=>{if(e.target===opts)opts.remove();});
});
</script>
</body>
</html>