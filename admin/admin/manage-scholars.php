<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    $_SESSION['error'] = "Please log in"; header('Location: admin-login.php'); exit();
}
require_once 'includes/config.php';
require_once 'includes/db.php';

$scholars = []; $error_message = $success_message = '';
$filter_status      = $_GET['status']      ?? 'all';
$filter_scholarship = $_GET['scholarship'] ?? 'all';
$search_term        = $_GET['search']      ?? '';
$current_page       = max(1,(int)($_GET['page']??1));
$items_per_page     = 10;
$sort_by            = in_array($_GET['sort']??'',['created_at','first_name','last_name','email'])?$_GET['sort']:'created_at';
$sort_order         = strtolower($_GET['order']??'')==='asc'?'ASC':'DESC';
$total_items = $total_pages = 0;

$filter_stats = ['total'=>0,'pending'=>0,'verified'=>0,'active'=>0,'inactive'=>0];

try {
    $db=new Database();$conn=$db->getConnection();
    $sc=$conn->query("SELECT status,COUNT(*) cnt FROM users WHERE role_id=2 GROUP BY status");
    while($r=$sc->fetch(PDO::FETCH_ASSOC)){$filter_stats[strtolower($r['status']??'')]=$r['cnt'];$filter_stats['total']+=$r['cnt'];}

    $where=[]; $params=[];
    if ($filter_status!=='all'){     $where[]="u.status=:st";    $params[':st']=$filter_status; }
    if ($filter_scholarship!=='all'){$where[]="u.scholarship_status=:ss"; $params[':ss']=$filter_scholarship; }
    if (!empty($search_term)){       $where[]="(u.first_name ILIKE :s OR u.last_name ILIKE :s OR u.email ILIKE :s OR u.program ILIKE :s)"; $params[':s']="%$search_term%"; }
    $wc=$where?" AND ".implode(" AND ",$where):"";

    $tc=$conn->prepare("SELECT COUNT(*) FROM users u WHERE u.role_id=2 $wc");
    $tc->execute($params); $total_items=(int)$tc->fetchColumn();
    $total_pages=max(1,ceil($total_items/$items_per_page));
    $offset=($current_page-1)*$items_per_page;

    $q="SELECT u.id,u.first_name,u.last_name,u.email,u.program,u.status,u.scholarship_status,u.is_verified,u.is_active,u.created_at,
              (SELECT COUNT(*) FROM user_documents WHERE user_id=u.id) doc_count
       FROM users u WHERE u.role_id=2 $wc ORDER BY u.$sort_by $sort_order LIMIT :lim OFFSET :off";
    $stmt=$conn->prepare($q);
    foreach($params as $k=>$v) $stmt->bindValue($k,$v);
    $stmt->bindValue(':lim',$items_per_page,PDO::PARAM_INT);
    $stmt->bindValue(':off',$offset,PDO::PARAM_INT);
    $stmt->execute();
    $scholars=$stmt->fetchAll(PDO::FETCH_ASSOC);

    $dp=$conn->query("SELECT COUNT(*) FROM user_documents WHERE review_status='pending'")->fetchColumn();
    $ap=$conn->query("SELECT COUNT(*) FROM scholarship_applications WHERE status='pending'")->fetchColumn();
    $uc=$conn->query("SELECT COUNT(*) FROM contact_messages WHERE read_status=FALSE")->fetchColumn();

} catch(Exception $e){ $error_message="Database error: ".$e->getMessage(); }

// POST handlers
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_status'])) {
    try {
        $parts=[]; $pp=[':uid'=>$_POST['user_id']];
        if(!empty($_POST['new_status'])){$parts[]="status=:st";$pp[':st']=$_POST['new_status'];}
        if(!empty($_POST['scholarship_status'])){$parts[]="scholarship_status=:ss";$pp[':ss']=$_POST['scholarship_status'];}
        if($parts){
            $parts[]="updated_at=CURRENT_TIMESTAMP";
            $conn->prepare("UPDATE users SET ".implode(",",$parts)." WHERE id=:uid")->execute($pp);
            $success_message="Scholar status updated.";
        }
    } catch(Exception $e){ $error_message="Update failed: ".$e->getMessage(); }
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_scholar'])) {
    try {
        $fn=$_POST['first_name']??'';$ln=$_POST['last_name']??'';$em=$_POST['email']??'';
        if(empty($fn)||empty($ln)||empty($em)) throw new Exception("Name and email are required.");
        $chk=$conn->prepare("SELECT COUNT(*) FROM users WHERE email=:e");
        $chk->execute([':e'=>$em]);
        if($chk->fetchColumn()>0) throw new Exception("Email already exists.");
        $conn->prepare("INSERT INTO users(first_name,last_name,email,password,program,status,role_id,is_verified,is_active,created_at) VALUES(:fn,:ln,:em,:pw,:pr,:st,2,:iv,:ia,CURRENT_TIMESTAMP)")
             ->execute([':fn'=>$fn,':ln'=>$ln,':em'=>$em,':pw'=>password_hash($_POST['password']??'changeme123',PASSWORD_DEFAULT),':pr'=>$_POST['program']??'',':st'=>$_POST['status']??'pending',':iv'=>($_POST['status']??'')==='verified'?1:0,':ia'=>($_POST['status']??'')==='active'?1:0]);
        $success_message="Scholar added successfully.";
    } catch(Exception $e){ $error_message=$e->getMessage(); }
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['bulk_update_status'])) {
    try {
        $ids=$_POST['selected_scholars']??[];
        if(empty($ids)) throw new Exception("No scholars selected.");
        $phs=[];$pp=[];
        foreach($ids as $i=>$id){$k=":id$i";$phs[]=$k;$pp[$k]=$id;}
        $parts=[];$bpp=[];
        if(!empty($_POST['bulk_status'])){$parts[]="status=:bst";$bpp[':bst']=$_POST['bulk_status'];}
        if(!empty($_POST['bulk_scholarship_status'])){$parts[]="scholarship_status=:bss";$bpp[':bss']=$_POST['bulk_scholarship_status'];}
        if($parts){
            $parts[]="updated_at=CURRENT_TIMESTAMP";
            $conn->prepare("UPDATE users SET ".implode(",",$parts)." WHERE id IN (".implode(",",$phs).")")->execute(array_merge($bpp,$pp));
            $success_message="Updated ".count($ids)." scholars.";
        }
    } catch(Exception $e){ $error_message=$e->getMessage(); }
}

function relTime($d){$diff=time()-strtotime($d);if($diff<86400)return"Today";if($diff<172800)return"Yesterday";if($diff<2592000)return floor($diff/86400)."d ago";return date('M d, Y',strtotime($d));}
function statusBadge($s){return['pending'=>'sb-warn','verified'=>'sb-green','active'=>'sb-blue','inactive'=>'sb-red'][$s??'']??'sb-grey';}
function scholarBadge($s){return['pending'=>'sb-warn','approved'=>'sb-green','rejected'=>'sb-red','graduated'=>'sb-blue'][$s??'']??'sb-grey';}

$admin_full_name  = $_SESSION['admin_name'] ?? 'Admin';
$admin_first_name = explode(' ', $admin_full_name)[0];
$admin_role       = ucfirst($_SESSION['admin_role'] ?? 'Administrator');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manage Scholars · Admin Portal</title>
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
.page-eyebrow{font-size:9.5px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:var(--gold);margin-bottom:4px;}
.page-title{font-family:var(--font-display);font-size:clamp(24px,2.5vw,32px);font-weight:500;color:var(--navy);}
.page-title em{font-style:italic;color:var(--gold);}
.page-sub{font-size:13px;color:var(--text-muted);margin-top:4px;}
.hdr-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
.btn-gold{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:var(--gold);color:var(--midnight);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
.btn-gold:hover{background:var(--gold-bright);transform:translateY(-1px);}
.btn-navy{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:var(--navy);color:var(--white);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
.btn-navy:hover{background:var(--navy-light);}
.btn-ghost{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:var(--white);color:var(--text-secondary);font-size:13px;border:1px solid var(--border-light);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
.btn-ghost:hover{background:var(--cream);border-color:var(--gold);color:var(--navy);}

/* STATS */
.stats-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:22px;}
.sc-card{background:var(--white);border-radius:var(--r-lg);padding:16px 20px;border:1px solid var(--border-light);cursor:pointer;transition:var(--transition);position:relative;text-decoration:none;display:block;}
.sc-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(8,14,28,.08);}
.sc-card.active{border-color:var(--gold);}
.sc-card-ico{position:absolute;top:14px;right:14px;width:34px;height:34px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:13px;}
.sc-lbl{font-size:11px;color:var(--text-muted);margin-bottom:5px;font-weight:500;text-transform:uppercase;letter-spacing:.5px;}
.sc-val{font-family:var(--font-display);font-size:28px;font-weight:500;color:var(--navy);line-height:1;}

/* FILTER */
.filter-panel{background:var(--white);border:1px solid var(--border-light);border-radius:var(--r-lg);padding:18px 22px;margin-bottom:20px;}
.filter-row{display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;}
.fg{display:flex;flex-direction:column;gap:5px;flex:1;min-width:140px;}
.fg label{font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;}
.fg select,.fg input{padding:9px 13px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13px;color:var(--text-primary);background:var(--white);transition:var(--transition);outline:none;}
.fg select:focus,.fg input:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,160,88,.12);}
.search-wrap{position:relative;flex:2;}
.search-wrap input{padding-left:36px;}
.search-wrap i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:12px;}

/* TABLE */
.table-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);overflow:hidden;margin-bottom:20px;}
.tc-header{padding:18px 22px;border-bottom:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;background:#F8F9FB;}
.tc-title{font-family:var(--font-display);font-size:19px;color:var(--navy);}
.tc-title em{font-style:italic;color:var(--gold);}
.tc-sub{font-size:12px;color:var(--text-muted);margin-top:2px;}
.tc-right{display:flex;gap:8px;align-items:center;}
.tbl-act{width:32px;height:32px;border-radius:var(--r-sm);background:var(--cream);border:1px solid var(--border-light);cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:12px;transition:var(--transition);}
.tbl-act:hover{background:var(--navy);color:var(--gold-bright);border-color:transparent;}
.data-table{width:100%;border-collapse:separate;border-spacing:0;}
.data-table th{background:#F8F9FB;color:var(--text-muted);font-weight:600;font-size:10.5px;letter-spacing:.8px;text-transform:uppercase;padding:12px 16px;text-align:left;border-bottom:1px solid var(--border-light);cursor:pointer;user-select:none;}
.data-table th:hover{color:var(--navy);}
.data-table td{padding:14px 16px;vertical-align:middle;border-bottom:1px solid var(--border-light);font-size:13.5px;}
.data-table tbody tr:last-child td{border-bottom:none;}
.data-table tbody tr{transition:background var(--transition);}
.data-table tbody tr:hover{background:#FAFBFD;}
.data-table tbody tr.row-selected{background:rgba(200,160,88,.04);}
.cb-cell{width:44px;text-align:center;}
.cb-cell input[type=checkbox]{accent-color:var(--navy);width:14px;height:14px;cursor:pointer;}
.scholar-cell{display:flex;align-items:center;gap:10px;}
.sch-av{width:36px;height:36px;border-radius:var(--r-sm);background:linear-gradient(135deg,var(--navy),var(--navy-light));display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:15px;color:var(--gold-bright);flex-shrink:0;}
.sch-name{font-weight:500;font-size:13.5px;color:var(--text-primary);}
.sch-prog{font-size:11.5px;color:var(--text-muted);}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:500;}
.sb-warn{background:var(--warning-pale);color:var(--warning);}
.sb-green{background:var(--success-pale);color:var(--success);}
.sb-blue{background:var(--info-pale);color:var(--info);}
.sb-red{background:var(--danger-pale);color:var(--danger);}
.sb-grey{background:var(--cream);color:var(--text-muted);}
.doc-count{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;background:rgba(13,24,41,.06);color:var(--navy);font-size:11.5px;font-weight:600;}
.act-row{display:flex;gap:5px;}
.ab{display:inline-flex;align-items:center;gap:4px;padding:5px 11px;font-size:11.5px;font-weight:500;border-radius:var(--r-sm);border:none;cursor:pointer;transition:var(--transition);font-family:var(--font-body);}
.ab:hover{transform:translateY(-1px);}
.ab-navy{background:var(--navy);color:var(--white);}
.ab-navy:hover{background:var(--navy-light);}
.ab-info{background:var(--info-pale);color:var(--info);}
.ab-info:hover{background:var(--info);color:var(--white);}
.ab-ghost{background:var(--cream);color:var(--text-secondary);border:1px solid var(--border-light);}
.ab-danger{background:var(--danger-pale);color:var(--danger);}
.ab-danger:hover{background:var(--danger);color:var(--white);}
.tc-footer{padding:14px 22px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;background:#FAFBFC;flex-wrap:wrap;gap:10px;}
.tf-text{font-size:12.5px;color:var(--text-muted);}
.pagination{display:flex;gap:4px;list-style:none;}
.page-item .page-link{display:flex;align-items:center;justify-content:center;min-width:32px;height:32px;border-radius:var(--r-sm);border:1px solid var(--border-light);background:var(--white);color:var(--text-secondary);font-size:12px;font-weight:500;transition:var(--transition);cursor:pointer;padding:0 9px;}
.page-item .page-link:hover{background:var(--cream);border-color:var(--gold);}
.page-item.active .page-link{background:var(--navy);border-color:var(--navy);color:var(--white);}
.page-item.disabled .page-link{opacity:.4;pointer-events:none;}
.empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 24px;text-align:center;}
.es-icon{font-size:44px;color:var(--border-light);margin-bottom:16px;}
.es-title{font-family:var(--font-display);font-size:22px;color:var(--navy);margin-bottom:6px;}
.bulk-bar{background:var(--navy);color:var(--white);padding:10px 22px;display:none;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;}
.bulk-bar.visible{display:flex;}
.bulk-label{font-size:13px;font-weight:500;}
.bulk-actions{display:flex;gap:8px;flex-wrap:wrap;}
.ba-btn{padding:6px 14px;font-size:12px;font-weight:500;border-radius:var(--r-sm);border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.08);color:var(--white);cursor:pointer;transition:var(--transition);font-family:var(--font-body);}
.ba-btn:hover{background:rgba(255,255,255,.16);}
@media(max-width:1200px){.stats-grid{grid-template-columns:repeat(3,1fr);}}
@media(max-width:991px){
  .sidebar{transform:translateX(-100%);}
  .sidebar.active{transform:translateX(0);}
  .header{left:0;}.main,.portal-footer{margin-left:0;}
  .mobile-toggle{display:flex;}.header-breadcrumb{display:none;}
  .stats-grid{grid-template-columns:repeat(2,1fr);}
}
@media(max-width:600px){.stats-grid{grid-template-columns:1fr 1fr;}}
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
    <div class="nav-item"><a href="manage-scholars.php" class="nav-link active"><i class="fas fa-user-graduate"></i> Scholars</a></div>
    <div class="nav-item"><a href="applications.php" class="nav-link"><i class="fas fa-tasks"></i> Applications<?php if(($ap??0)>0): ?><span class="nav-badge"><?= $ap ?></span><?php endif; ?></a></div>
    <div class="nav-section-label">Management</div>
    <div class="nav-item"><a href="admin-document-review.php" class="nav-link"><i class="fas fa-file-alt"></i> Review Documents<?php if(($dp??0)>0): ?><span class="nav-badge"><?= $dp ?></span><?php endif; ?></a></div>
    <div class="nav-item"><a href="scholarships.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Scholarships</a></div>
    <div class="nav-item"><a href="admin-messages.php" class="nav-link"><i class="fas fa-envelope"></i> Messages<?php if(($uc??0)>0): ?><span class="nav-badge"><?= $uc ?></span><?php endif; ?></a></div>
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
      <strong>Scholars</strong>
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
  <div>
    <div class="page-eyebrow"><i class="fas fa-user-graduate" style="margin-right:5px"></i> Scholar Management</div>
    <h1 class="page-title">Manage <em>Scholars</em></h1>
    <p class="page-sub">View, update and manage all programme scholars</p>
  </div>
  <div class="hdr-actions">
    <button class="btn-ghost" id="exportBtn"><i class="fas fa-download"></i> Export</button>
    <button class="btn-gold" id="addScholarBtn"><i class="fas fa-user-plus"></i> Add Scholar</button>
  </div>
</div>

<!-- STATS -->
<div class="stats-grid">
  <?php
  $sCards=[
    ['status'=>'all',      'label'=>'Total',    'ico'=>'users',      'ics'=>'rgba(13,24,41,.08)',  'icc'=>'var(--navy)',    'val'=>$filter_stats['total']],
    ['status'=>'pending',  'label'=>'Pending',  'ico'=>'clock',      'ics'=>'var(--warning-pale)', 'icc'=>'var(--warning)', 'val'=>$filter_stats['pending']??0],
    ['status'=>'verified', 'label'=>'Verified', 'ico'=>'check-circle','ics'=>'var(--success-pale)','icc'=>'var(--success)', 'val'=>$filter_stats['verified']??0],
    ['status'=>'active',   'label'=>'Active',   'ico'=>'user-check', 'ics'=>'var(--info-pale)',    'icc'=>'var(--info)',    'val'=>$filter_stats['active']??0],
    ['status'=>'inactive', 'label'=>'Inactive', 'ico'=>'user-times', 'ics'=>'var(--danger-pale)',  'icc'=>'var(--danger)',  'val'=>$filter_stats['inactive']??0],
  ];
  foreach($sCards as $c): ?>
  <a href="?status=<?= $c['status'] ?>&scholarship=<?= $filter_scholarship ?>&search=<?= urlencode($search_term) ?>" class="sc-card <?= $filter_status===$c['status']?'active':'' ?>">
    <div class="sc-card-ico" style="background:<?= $c['ics'] ?>;color:<?= $c['icc'] ?>"><i class="fas fa-<?= $c['ico'] ?>"></i></div>
    <div class="sc-lbl"><?= $c['label'] ?></div>
    <div class="sc-val"><?= $c['val'] ?></div>
  </a>
  <?php endforeach; ?>
</div>

<!-- FILTERS -->
<div class="filter-panel">
  <form method="GET" class="filter-row">
    <div class="fg" style="max-width:160px">
      <label>Account Status</label>
      <select name="status">
        <option value="all" <?= $filter_status==='all'?'selected':'' ?>>All Statuses</option>
        <option value="pending"  <?= $filter_status==='pending'?'selected':''  ?>>Pending</option>
        <option value="verified" <?= $filter_status==='verified'?'selected':'' ?>>Verified</option>
        <option value="active"   <?= $filter_status==='active'?'selected':''   ?>>Active</option>
        <option value="inactive" <?= $filter_status==='inactive'?'selected':'' ?>>Inactive</option>
      </select>
    </div>
    <div class="fg" style="max-width:180px">
      <label>Scholarship Status</label>
      <select name="scholarship">
        <option value="all"       <?= $filter_scholarship==='all'?'selected':''      ?>>All</option>
        <option value="pending"   <?= $filter_scholarship==='pending'?'selected':''  ?>>Pending</option>
        <option value="approved"  <?= $filter_scholarship==='approved'?'selected':'' ?>>Approved</option>
        <option value="rejected"  <?= $filter_scholarship==='rejected'?'selected':'' ?>>Rejected</option>
        <option value="graduated" <?= $filter_scholarship==='graduated'?'selected':'' ?>>Graduated</option>
      </select>
    </div>
    <div class="fg search-wrap">
      <label>Search</label>
      <i class="fas fa-search"></i>
      <input type="text" name="search" placeholder="Name, email or programme…" value="<?= htmlspecialchars($search_term) ?>">
    </div>
    <button type="submit" class="btn-gold" style="margin-top:auto"><i class="fas fa-filter"></i> Filter</button>
    <?php if($filter_status!=='all'||$filter_scholarship!=='all'||!empty($search_term)): ?>
    <a href="manage-scholars.php" class="btn-ghost" style="margin-top:auto"><i class="fas fa-times"></i></a>
    <?php endif; ?>
  </form>
</div>

<!-- BULK BAR -->
<div class="bulk-bar" id="bulkBar">
  <div class="bulk-label"><span id="bulkCount">0</span> scholars selected</div>
  <div class="bulk-actions">
    <button class="ba-btn" onclick="bulkAction('status','verified')"><i class="fas fa-check"></i> Mark Verified</button>
    <button class="ba-btn" onclick="bulkAction('status','active')"><i class="fas fa-user-check"></i> Mark Active</button>
    <button class="ba-btn" onclick="bulkAction('status','inactive')"><i class="fas fa-user-times"></i> Mark Inactive</button>
    <button class="ba-btn" onclick="bulkAction('scholarship','approved')"><i class="fas fa-graduation-cap"></i> Approve Scholarship</button>
    <button class="ba-btn" style="background:rgba(220,38,38,.2);border-color:rgba(220,38,38,.3)" onclick="bulkAction('status','pending')">Reset to Pending</button>
  </div>
</div>

<!-- TABLE -->
<form id="scholarsForm" method="POST">
<input type="hidden" name="bulk_status" id="bulkStatusInput">
<input type="hidden" name="bulk_scholarship_status" id="bulkScholarshipInput">
<input type="hidden" name="bulk_update_status" value="1">
<div class="table-card">
  <div class="tc-header">
    <div>
      <div class="tc-title">Scholar <em>Directory</em></div>
      <div class="tc-sub"><?= !empty($scholars)?"Showing ".count($scholars)." of $total_items scholars":"No scholars found" ?></div>
    </div>
    <div class="tc-right">
      <button type="button" class="tbl-act" onclick="location.reload()" title="Refresh"><i class="fas fa-sync-alt"></i></button>
      <button type="button" class="tbl-act" id="exportCsv" title="Export CSV"><i class="fas fa-download"></i></button>
    </div>
  </div>

  <?php if(!empty($scholars)): ?>
  <div style="overflow-x:auto">
    <table class="data-table">
      <thead>
        <tr>
          <th class="cb-cell"><input type="checkbox" id="selectAll" title="Select all"></th>
          <th><a href="?status=<?= $filter_status ?>&sort=first_name&order=<?= $sort_by==='first_name'&&$sort_order==='ASC'?'desc':'asc' ?>&search=<?= urlencode($search_term) ?>" style="color:inherit;display:flex;align-items:center;gap:5px">Scholar<?= $sort_by==='first_name'?" <i class='fas fa-sort-".strtolower($sort_order)."' style='font-size:10px'></i>":'' ?></a></th>
          <th>Account Status</th>
          <th>Scholarship</th>
          <th>Documents</th>
          <th><a href="?status=<?= $filter_status ?>&sort=created_at&order=<?= $sort_by==='created_at'&&$sort_order==='ASC'?'desc':'asc' ?>&search=<?= urlencode($search_term) ?>" style="color:inherit">Joined</a></th>
          <th style="text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($scholars as $s): ?>
        <tr>
          <td class="cb-cell"><input type="checkbox" class="scholar-cb" name="selected_scholars[]" value="<?= $s['id'] ?>"></td>
          <td>
            <div class="scholar-cell">
              <div class="sch-av"><?= strtoupper(substr($s['first_name'],0,1)) ?></div>
              <div>
                <div class="sch-name"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></div>
                <div class="sch-prog"><?= htmlspecialchars($s['email']) ?></div>
                <?php if(!empty($s['program'])): ?><div class="sch-prog" style="margin-top:2px;color:var(--gold)"><?= htmlspecialchars($s['program']) ?></div><?php endif; ?>
              </div>
            </div>
          </td>
          <td>
            <div style="display:flex;flex-direction:column;gap:4px">
              <span class="badge <?= statusBadge($s['status']) ?>"><?= ucfirst($s['status']??'unknown') ?></span>
              <?php if($s['is_verified']): ?><span class="badge sb-green" style="font-size:10px;padding:2px 8px"><i class="fas fa-shield-alt"></i> Verified</span><?php endif; ?>
            </div>
          </td>
          <td>
            <?php if(!empty($s['scholarship_status'])): ?>
            <span class="badge <?= scholarBadge($s['scholarship_status']) ?>"><?= ucfirst($s['scholarship_status']) ?></span>
            <?php else: ?>
            <span style="font-size:12px;color:var(--text-muted)">—</span>
            <?php endif; ?>
          </td>
          <td><span class="doc-count"><i class="fas fa-file-alt"></i><?= $s['doc_count'] ?></span></td>
          <td style="font-size:12.5px;color:var(--text-muted)"><?= relTime($s['created_at']) ?></td>
          <td>
            <div class="act-row" style="justify-content:flex-end">
              <button type="button" class="ab ab-navy status-btn"
                data-id="<?= $s['id'] ?>"
                data-status="<?= htmlspecialchars($s['status']??'') ?>"
                data-scholarship="<?= htmlspecialchars($s['scholarship_status']??'') ?>">
                <i class="fas fa-edit"></i> Update
              </button>
              <a href="scholar-details.php?id=<?= $s['id'] ?>" class="ab ab-info"><i class="fas fa-eye"></i></a>
              <button type="button" class="ab ab-ghost send-email-btn"
                data-email="<?= htmlspecialchars($s['email']) ?>"
                data-name="<?= htmlspecialchars($s['first_name']) ?>">
                <i class="fas fa-envelope"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if($total_pages>1): ?>
  <div class="tc-footer">
    <div class="tf-text">Showing <?= ($offset+1) ?>–<?= min($offset+$items_per_page,$total_items) ?> of <?= $total_items ?></div>
    <ul class="pagination">
      <li class="page-item <?= $current_page<=1?'disabled':'' ?>"><a class="page-link" href="?page=<?= $current_page-1 ?>&status=<?= $filter_status ?>&scholarship=<?= $filter_scholarship ?>&search=<?= urlencode($search_term) ?>"><i class="fas fa-chevron-left"></i></a></li>
      <?php for($i=max(1,$current_page-2);$i<=min($total_pages,max(1,$current_page-2)+4);$i++): ?>
      <li class="page-item <?= $i==$current_page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&status=<?= $filter_status ?>&scholarship=<?= $filter_scholarship ?>&search=<?= urlencode($search_term) ?>"><?= $i ?></a></li>
      <?php endfor; ?>
      <li class="page-item <?= $current_page>=$total_pages?'disabled':'' ?>"><a class="page-link" href="?page=<?= $current_page+1 ?>&status=<?= $filter_status ?>&scholarship=<?= $filter_scholarship ?>&search=<?= urlencode($search_term) ?>"><i class="fas fa-chevron-right"></i></a></li>
    </ul>
  </div>
  <?php endif; ?>

  <?php else: ?>
  <div class="empty-state">
    <div class="es-icon"><i class="fas fa-user-graduate"></i></div>
    <div class="es-title">No Scholars Found</div>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:20px"><?= !empty($search_term)?"No results for \"".htmlspecialchars($search_term)."\".":"No scholars match your filters." ?></p>
    <button class="btn-gold" id="addScholarBtn2"><i class="fas fa-user-plus"></i> Add New Scholar</button>
  </div>
  <?php endif; ?>
</div>
</form>

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

// Select all
const selectAll=document.getElementById('selectAll');
const cbs=document.querySelectorAll('.scholar-cb');
const bulkBar=document.getElementById('bulkBar');
const bulkCount=document.getElementById('bulkCount');

function updateBulk(){
  const checked=[...cbs].filter(c=>c.checked);
  if(checked.length>0){bulkBar.classList.add('visible');bulkCount.textContent=checked.length;}
  else{bulkBar.classList.remove('visible');}
  if(selectAll){selectAll.checked=checked.length===cbs.length&&cbs.length>0;selectAll.indeterminate=checked.length>0&&checked.length<cbs.length;}
  document.querySelectorAll('.data-table tbody tr').forEach(tr=>{
    const cb=tr.querySelector('.scholar-cb');
    if(cb) tr.classList.toggle('row-selected',cb.checked);
  });
}
selectAll?.addEventListener('change',function(){cbs.forEach(c=>c.checked=this.checked);updateBulk();});
cbs.forEach(c=>c.addEventListener('change',updateBulk));

function bulkAction(type,val){
  const cnt=[...cbs].filter(c=>c.checked).length;
  Swal.fire({
    title:'Confirm Bulk Update',
    html:`<p style="font-size:13px;color:#64748b">Update <strong>${cnt} scholar${cnt>1?'s':''}</strong> ${type==='status'?'account':'scholarship'} status to <strong style="text-transform:capitalize">${val}</strong>?</p>`,
    icon:'warning',showCancelButton:true,confirmButtonText:'Yes, Update',confirmButtonColor:'#0D1829',cancelButtonText:'Cancel'
  }).then(r=>{
    if(r.isConfirmed){
      if(type==='status'){document.getElementById('bulkStatusInput').value=val;document.getElementById('bulkScholarshipInput').value='';}
      else{document.getElementById('bulkStatusInput').value='';document.getElementById('bulkScholarshipInput').value=val;}
      document.getElementById('scholarsForm').submit();
    }
  });
}

// Status update modal
document.querySelectorAll('.status-btn').forEach(btn=>{
  btn.addEventListener('click',function(){
    const id=this.dataset.id,st=this.dataset.status,sc=this.dataset.scholarship;
    Swal.fire({
      title:'Update Scholar Status',
      html:`<div style="text-align:left">
        <div style="margin-bottom:14px">
          <label style="display:block;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">Account Status</label>
          <select id="sw-st" style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px">
            <option value="">No change</option>
            <option value="pending" ${st==='pending'?'selected':''}>Pending</option>
            <option value="verified" ${st==='verified'?'selected':''}>Verified</option>
            <option value="active" ${st==='active'?'selected':''}>Active</option>
            <option value="inactive" ${st==='inactive'?'selected':''}>Inactive</option>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">Scholarship Status</label>
          <select id="sw-sc" style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px">
            <option value="">No change</option>
            <option value="pending" ${sc==='pending'?'selected':''}>Pending</option>
            <option value="approved" ${sc==='approved'?'selected':''}>Approved</option>
            <option value="rejected" ${sc==='rejected'?'selected':''}>Rejected</option>
            <option value="graduated" ${sc==='graduated'?'selected':''}>Graduated</option>
          </select>
        </div>
      </div>`,
      showCancelButton:true,confirmButtonText:'Update',confirmButtonColor:'#0D1829',
      preConfirm:()=>{return{st:document.getElementById('sw-st').value,sc:document.getElementById('sw-sc').value};}
    }).then(r=>{
      if(r.isConfirmed&&r.value){
        const fd=new FormData();
        fd.append('user_id',id);
        if(r.value.st) fd.append('new_status',r.value.st);
        if(r.value.sc) fd.append('scholarship_status',r.value.sc);
        fd.append('update_status','1');
        Swal.fire({title:'Updating…',allowOutsideClick:false,showConfirmButton:false,willOpen:()=>Swal.showLoading()});
        fetch(window.location.href,{method:'POST',body:fd,credentials:'same-origin'})
          .then(()=>Swal.fire({icon:'success',title:'Updated',confirmButtonColor:'#C8A058'}).then(()=>location.reload()))
          .catch(e=>Swal.fire({icon:'error',title:'Error',text:e.message}));
      }
    });
  });
});

// Add scholar
function openAddScholar(){
  Swal.fire({
    title:'Add New Scholar',
    html:`<div style="text-align:left">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
        <div>
          <label style="display:block;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">First Name*</label>
          <input id="ns-fn" type="text" style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px" placeholder="First name">
        </div>
        <div>
          <label style="display:block;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">Last Name*</label>
          <input id="ns-ln" type="text" style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px" placeholder="Last name">
        </div>
      </div>
      <div style="margin-bottom:12px">
        <label style="display:block;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">Email*</label>
        <input id="ns-em" type="email" style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px" placeholder="email@example.com">
      </div>
      <div style="margin-bottom:12px">
        <label style="display:block;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">Programme</label>
        <input id="ns-pr" type="text" style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px" placeholder="e.g. PhD Chemistry">
      </div>
      <div>
        <label style="display:block;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">Initial Status</label>
        <select id="ns-st" style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px">
          <option value="pending">Pending</option>
          <option value="verified">Verified</option>
          <option value="active">Active</option>
        </select>
      </div>
    </div>`,
    showCancelButton:true,confirmButtonText:'Add Scholar',confirmButtonColor:'#C8A058',
    preConfirm:()=>{
      const fn=document.getElementById('ns-fn').value,ln=document.getElementById('ns-ln').value,em=document.getElementById('ns-em').value;
      if(!fn||!ln||!em){Swal.showValidationMessage('Name and email are required');return false;}
      if(!/^\S+@\S+\.\S+$/.test(em)){Swal.showValidationMessage('Please enter a valid email');return false;}
      return{fn,ln,em,pr:document.getElementById('ns-pr').value,st:document.getElementById('ns-st').value};
    }
  }).then(r=>{
    if(r.isConfirmed&&r.value){
      const fd=new FormData();
      fd.append('first_name',r.value.fn);fd.append('last_name',r.value.ln);
      fd.append('email',r.value.em);fd.append('program',r.value.pr);
      fd.append('status',r.value.st);fd.append('add_scholar','1');
      Swal.fire({title:'Adding…',allowOutsideClick:false,showConfirmButton:false,willOpen:()=>Swal.showLoading()});
      fetch(window.location.href,{method:'POST',body:fd,credentials:'same-origin'})
        .then(()=>Swal.fire({icon:'success',title:'Scholar Added',confirmButtonColor:'#C8A058'}).then(()=>location.reload()))
        .catch(e=>Swal.fire({icon:'error',title:'Error',text:e.message}));
    }
  });
}
document.getElementById('addScholarBtn')?.addEventListener('click',openAddScholar);
document.getElementById('addScholarBtn2')?.addEventListener('click',openAddScholar);

// Send email
document.querySelectorAll('.send-email-btn').forEach(btn=>{
  btn.addEventListener('click',function(){
    const email=this.dataset.email,name=this.dataset.name;
    Swal.fire({
      title:`Email ${name}`,
      html:`<div style="text-align:left">
        <div style="margin-bottom:12px"><label style="display:block;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">Subject</label>
        <input id="em-sub" type="text" style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px" value="BFI Scholarship - Update"></div>
        <div><label style="display:block;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">Message</label>
        <textarea id="em-msg" rows="5" style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;resize:vertical">Dear ${name},\n\n</textarea></div>
      </div>`,
      showCancelButton:true,confirmButtonText:'Send',confirmButtonColor:'#C8A058',
      preConfirm:()=>({sub:document.getElementById('em-sub').value,msg:document.getElementById('em-msg').value})
    }).then(r=>{
      if(r.isConfirmed) Swal.fire({icon:'info',title:'Email Feature',text:'Connect this to your mailer.php to send emails.',confirmButtonColor:'#C8A058'});
    });
  });
});

// Export
document.getElementById('exportCsv')?.addEventListener('click',()=>{
  Swal.fire({icon:'info',title:'Export',text:'Redirecting to export_scholars.php',confirmButtonColor:'#C8A058',timer:2000});
});
document.getElementById('exportBtn')?.addEventListener('click',()=>{
  Swal.fire({icon:'info',title:'Export Scholars',html:`<div style="display:grid;gap:8px;margin-top:10px">
    <button onclick="Swal.close();window.location.href='export_scholars.php?format=csv'" style="padding:10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;font-family:Outfit,sans-serif;font-size:13px">📊 Export as CSV</button>
  </div>`,showConfirmButton:false,showCancelButton:true});
});
</script>
</body>
</html>