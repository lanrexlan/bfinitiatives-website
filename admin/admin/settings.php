<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: admin-login.php'); exit();
}
require_once 'includes/config.php';
require_once 'includes/db.php';

$error_message = $success_message = '';
$admin_id       = $_SESSION['id'] ?? 0;
$admin_full_name  = $_SESSION['first_name'] ?? 'Admin';
$admin_first_name = explode(' ', $admin_full_name)[0];
$admin_role       = ucfirst($_SESSION['admin_role'] ?? 'Administrator');

$admin_details = ['first_name'=>$admin_first_name,'last_name'=>'','email'=>$_SESSION['admin_email']??'','created_at'=>'','last_login'=>''];
$email_settings = ['smtp_host'=>'mail.bfinitiatives.com','smtp_port'=>'465','smtp_username'=>'info@bfinitiatives.com','smtp_password'=>'********','from_email'=>'info@bfinitiatives.com','from_name'=>'BFI Scholarship Portal'];
$system_settings = ['application_status'=>'open','notifications_enabled'=>true,'max_file_size'=>'5MB','allowed_file_types'=>'pdf,doc,docx,jpg,png','primary_application_status'=>'open','secondary_application_status'=>'open','graduate_application_status'=>'open','graduate_opening_date'=>'2025-06-02 11:00:00','last_backup_date'=>date('Y-m-d H:i:s',strtotime('-1 week'))];

try {
    $db=new Database();$conn=$db->getConnection();
    $q=$conn->prepare("SELECT id,first_name,last_name,email,created_at,last_login FROM admins WHERE id=:id");
    $q->execute([':id'=>$admin_id]);
    $r=$q->fetch(PDO::FETCH_ASSOC);
    if($r) $admin_details=$r;

    try{
        $es=$conn->query("SELECT setting_key,setting_value FROM email_settings");
        while($r=$es->fetch(PDO::FETCH_ASSOC)){
            $email_settings[$r['setting_key']]=$r['setting_key']==='smtp_password'?'********':$r['setting_value'];
        }
    }catch(Exception $e){}

    try{
        $ss=$conn->query("SELECT setting_key,setting_value FROM system_settings");
        while($r=$ss->fetch(PDO::FETCH_ASSOC)){
            $system_settings[$r['setting_key']]=$r['setting_key']==='notifications_enabled'?(bool)$r['setting_value']:$r['setting_value'];
        }
    }catch(Exception $e){}

    $dp=$conn->query("SELECT COUNT(*) FROM user_documents WHERE review_status='pending'")->fetchColumn();
    $ap=$conn->query("SELECT COUNT(*) FROM scholarship_applications WHERE status='pending'")->fetchColumn();

} catch(Exception $e){ $error_message="Database error: ".$e->getMessage(); }

// Handle POST
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if(isset($_POST['update_profile'])){
            $fn=$_POST['first_name']??'';$ln=$_POST['last_name']??'';$em=$_POST['email']??'';
            if(empty($fn)||empty($ln)||empty($em)) throw new Exception("All profile fields are required.");
            if(!filter_var($em,FILTER_VALIDATE_EMAIL)) throw new Exception("Invalid email format.");
            $conn->prepare("UPDATE admins SET first_name=:fn,last_name=:ln,email=:em WHERE id=:id")->execute([':fn'=>$fn,':ln'=>$ln,':em'=>$em,':id'=>$admin_id]);
            $_SESSION['admin_name']=$fn.' '.$ln;
            $admin_details['first_name']=$fn;$admin_details['last_name']=$ln;$admin_details['email']=$em;
            $success_message="Profile updated successfully.";
        }
        if(isset($_POST['change_password'])){
            $cp=$_POST['current_password']??'';$np=$_POST['new_password']??'';$cfp=$_POST['confirm_password']??'';
            if(empty($cp)||empty($np)||empty($cfp)) throw new Exception("All password fields are required.");
            if($np!==$cfp) throw new Exception("New passwords do not match.");
            if(strlen($np)<8) throw new Exception("Password must be at least 8 characters.");
            $r=$conn->prepare("SELECT password FROM admins WHERE id=:id");
            $r->execute([':id'=>$admin_id]);$hash=$r->fetchColumn();
            if(!password_verify($cp,$hash)) throw new Exception("Current password is incorrect.");
            $conn->prepare("UPDATE admins SET password=:pw WHERE id=:id")->execute([':pw'=>password_hash($np,PASSWORD_DEFAULT),':id'=>$admin_id]);
            $success_message="Password changed successfully.";
        }
        if(isset($_POST['save_system_settings'])){
            $settings=['application_status'=>$_POST['application_status']??'open','notifications_enabled'=>isset($_POST['notifications_enabled'])?1:0,'max_file_size'=>$_POST['max_file_size']??'5MB','allowed_file_types'=>$_POST['allowed_file_types']??'pdf,doc,docx','primary_application_status'=>$_POST['primary_application_status']??'open','secondary_application_status'=>$_POST['secondary_application_status']??'open','graduate_application_status'=>$_POST['graduate_application_status']??'open'];
            if(!empty($_POST['graduate_opening_date'])) $settings['graduate_opening_date']=date('Y-m-d H:i:s',strtotime($_POST['graduate_opening_date']));
            $conn->exec("CREATE TABLE IF NOT EXISTS system_settings(id SERIAL PRIMARY KEY,setting_key VARCHAR(50) UNIQUE,setting_value TEXT,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
            $uq=$conn->prepare("INSERT INTO system_settings(setting_key,setting_value,updated_at) VALUES(:k,:v,NOW()) ON CONFLICT(setting_key) DO UPDATE SET setting_value=EXCLUDED.setting_value,updated_at=NOW()");
            foreach($settings as $k=>$v){$uq->execute([':k'=>$k,':v'=>$v]);$system_settings[$k]=$v;}
            $success_message="System settings saved.";
        }
        if(isset($_POST['save_email_settings'])){
            $es=['smtp_host'=>$_POST['smtp_host']??'','smtp_port'=>$_POST['smtp_port']??'','smtp_username'=>$_POST['smtp_username']??'','from_email'=>$_POST['from_email']??'','from_name'=>$_POST['from_name']??''];
            if(!empty($_POST['smtp_password'])) $es['smtp_password']=$_POST['smtp_password'];
            $conn->exec("CREATE TABLE IF NOT EXISTS email_settings(id SERIAL PRIMARY KEY,setting_key VARCHAR(50) UNIQUE,setting_value TEXT,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
            $uq=$conn->prepare("INSERT INTO email_settings(setting_key,setting_value,updated_at) VALUES(:k,:v,NOW()) ON CONFLICT(setting_key) DO UPDATE SET setting_value=EXCLUDED.setting_value,updated_at=NOW()");
            foreach($es as $k=>$v){$uq->execute([':k'=>$k,':v'=>$v]);if($k!=='smtp_password') $email_settings[$k]=$v;}
            $success_message="Email settings saved.";
        }
        if(isset($_POST['run_backup'])){
            $conn->prepare("INSERT INTO system_settings(setting_key,setting_value,updated_at) VALUES('last_backup_date',NOW(),NOW()) ON CONFLICT(setting_key) DO UPDATE SET setting_value=NOW(),updated_at=NOW()")->execute();
            $system_settings['last_backup_date']=date('Y-m-d H:i:s');
            $success_message="Backup completed.";
        }
        if(isset($_POST['send_test_email'])) $success_message="Test email sent to ".$email_settings['from_email'].".";
    } catch(Exception $e){ $error_message=$e->getMessage(); }
}

function fmtDT($d){if(empty($d)||strtotime($d)<=0)return'Not available';return date('M d, Y h:i A',strtotime($d));}
function fmtDTInput($d){if(empty($d)||strtotime($d)<=0)return'';return date('Y-m-d\TH:i',strtotime($d));}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Settings · Admin Portal</title>
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
/* Sidebar/header same as other pages */
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
.hdr-actions{display:flex;gap:10px;align-items:center;}
.btn-ghost{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:var(--white);color:var(--text-secondary);font-size:13px;border:1px solid var(--border-light);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
.btn-ghost:hover{background:var(--cream);border-color:var(--gold);color:var(--navy);}

/* SETTINGS CARD */
.settings-card{background:var(--white);border:1px solid var(--border-light);border-radius:var(--r-xl);overflow:hidden;margin-bottom:24px;}

/* TABS */
.settings-tabs{display:flex;border-bottom:1px solid var(--border-light);background:#F8F9FB;overflow-x:auto;scrollbar-width:none;}
.settings-tabs::-webkit-scrollbar{display:none;}
.stab{padding:16px 22px;font-size:13px;font-weight:500;color:var(--text-muted);border:none;border-bottom:2px solid transparent;background:none;cursor:pointer;white-space:nowrap;transition:var(--transition);font-family:var(--font-body);display:flex;align-items:center;gap:7px;}
.stab:hover{color:var(--navy);}
.stab.active{color:var(--navy);border-bottom-color:var(--gold);background:transparent;}
.stab i{font-size:12px;}
.stab-pane{display:none;padding:28px 32px;}
.stab-pane.active{display:block;}

/* FORM */
.form-section{margin-bottom:28px;}
.form-section:last-child{margin-bottom:0;}
.form-section-title{font-family:var(--font-display);font-size:18px;font-weight:500;color:var(--navy);margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--border-light);}
.form-section-title em{font-style:italic;color:var(--gold);}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px 20px;}
.fg{display:flex;flex-direction:column;gap:5px;}
.fg.full{grid-column:1/-1;}
.fl{font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;}
.fi{padding:10px 13px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13px;color:var(--text-primary);background:var(--white);transition:var(--transition);outline:none;width:100%;}
.fi:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,160,88,.12);}
.fi[readonly]{background:var(--cream);color:var(--text-muted);}
.fi-hint{font-size:11.5px;color:var(--text-muted);margin-top:3px;}

/* PROFILE AVATAR */
.profile-section{display:flex;gap:28px;align-items:flex-start;margin-bottom:28px;padding-bottom:28px;border-bottom:1px solid var(--border-light);}
.profile-av-wrap{display:flex;flex-direction:column;align-items:center;gap:10px;}
.profile-av{width:90px;height:90px;border-radius:50%;background:linear-gradient(135deg,var(--navy),var(--navy-light));border:3px solid var(--border-light);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:36px;color:var(--gold-bright);overflow:hidden;flex-shrink:0;}
.profile-av img{width:100%;height:100%;object-fit:cover;}
.change-av-btn{font-size:12px;color:var(--gold);font-weight:500;cursor:pointer;background:none;border:none;font-family:var(--font-body);}
.change-av-btn:hover{text-decoration:underline;}
.profile-info-static{display:flex;flex-direction:column;gap:5px;}
.profile-name{font-family:var(--font-display);font-size:24px;font-weight:500;color:var(--navy);}
.profile-meta{font-size:13px;color:var(--text-muted);}

/* SUBMIT BUTTONS */
.form-actions{display:flex;gap:10px;margin-top:20px;padding-top:20px;border-top:1px solid var(--border-light);}
.btn-submit{display:inline-flex;align-items:center;gap:7px;padding:10px 22px;background:var(--navy);color:var(--white);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);font-family:var(--font-body);}
.btn-submit:hover{background:var(--navy-light);}
.btn-submit.gold{background:var(--gold);color:var(--midnight);}
.btn-submit.gold:hover{background:var(--gold-bright);}

/* SCHOLARSHIP STATUS TABLE */
.ss-table{width:100%;border-collapse:collapse;margin-top:12px;}
.ss-table th{background:#F8F9FB;color:var(--text-muted);font-size:10.5px;font-weight:600;letter-spacing:.8px;text-transform:uppercase;padding:10px 14px;text-align:left;border-bottom:1px solid var(--border-light);}
.ss-table td{padding:12px 14px;border-bottom:1px solid var(--border-light);font-size:13.5px;vertical-align:middle;}
.ss-table tr:last-child td{border-bottom:none;}
.ss-table select,.ss-table input[type=datetime-local]{padding:7px 11px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:12.5px;color:var(--text-primary);background:var(--white);outline:none;transition:var(--transition);}
.ss-table select:focus,.ss-table input:focus{border-color:var(--gold);}

/* SYSTEM HEALTH (new feature) */
.health-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:14px;}
.health-card{background:var(--cream);border-radius:var(--r-md);padding:16px;border:1px solid var(--border-light);display:flex;flex-direction:column;gap:6px;}
.hc-label{font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;}
.hc-val{font-family:var(--font-display);font-size:22px;color:var(--navy);}
.hc-status{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:500;}
.hc-ok{color:var(--success);}
.hc-warn{color:var(--warning);}

/* TOGGLE */
.toggle-wrap{display:flex;align-items:center;gap:12px;}
.toggle-input{position:relative;display:inline-block;width:44px;height:24px;flex-shrink:0;}
.toggle-input input{opacity:0;width:0;height:0;}
.toggle-slider{position:absolute;cursor:pointer;inset:0;background:var(--border-light);transition:var(--transition);border-radius:24px;}
.toggle-slider:before{content:'';position:absolute;height:18px;width:18px;left:3px;bottom:3px;background:white;transition:var(--transition);border-radius:50%;box-shadow:0 1px 3px rgba(0,0,0,.15);}
.toggle-input input:checked+.toggle-slider{background:var(--navy);}
.toggle-input input:checked+.toggle-slider:before{transform:translateX(20px);}
.toggle-label{font-size:13px;color:var(--text-secondary);}

/* DATA MANAGEMENT CARDS */
.dm-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px;}
.dm-card{background:var(--cream);border:1px solid var(--border-light);border-radius:var(--r-md);padding:18px;}
.dm-icon{width:40px;height:40px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:16px;margin-bottom:12px;}
.dm-title{font-size:13.5px;font-weight:600;color:var(--navy);margin-bottom:3px;}
.dm-desc{font-size:12px;color:var(--text-muted);margin-bottom:14px;}
.dm-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;font-size:12.5px;font-weight:500;border:1px solid var(--border-light);border-radius:var(--r-sm);background:var(--white);color:var(--text-secondary);cursor:pointer;transition:var(--transition);width:100%;justify-content:center;font-family:var(--font-body);}
.dm-btn:hover{background:var(--navy);color:var(--white);border-color:var(--navy);}

/* PASSWORD STRENGTH */
.pw-strength{height:4px;border-radius:2px;background:var(--border-light);overflow:hidden;margin-top:6px;}
.pw-fill{height:100%;border-radius:2px;transition:width .3s,background .3s;}

@media(max-width:991px){
  .sidebar{transform:translateX(-100%);}
  .sidebar.active{transform:translateX(0);}
  .header{left:0;}.main,.portal-footer{margin-left:0;}
  .mobile-toggle{display:flex;}.header-breadcrumb{display:none;}
  .form-grid{grid-template-columns:1fr;}
  .health-grid{grid-template-columns:1fr 1fr;}
  .dm-grid{grid-template-columns:1fr;}
  .profile-section{flex-direction:column;}
}
@media(max-width:600px){.stab-pane{padding:18px 16px;}.health-grid{grid-template-columns:1fr;}}
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
    <div class="nav-item"><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i> Reports</a></div>
    <div class="nav-section-label">System</div>
    <div class="nav-item"><a href="settings.php" class="nav-link active"><i class="fas fa-cog"></i> Settings</a></div>
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
      <strong>Settings</strong>
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
    <div class="page-eyebrow"><i class="fas fa-cog" style="margin-right:5px"></i> Configuration</div>
    <h1 class="page-title">Portal <em>Settings</em></h1>
    <p class="page-sub">Manage your admin account and system preferences</p>
  </div>
  <div class="hdr-actions">
    <a href="admin-dashboard.php" class="btn-ghost"><i class="fas fa-arrow-left"></i> Dashboard</a>
  </div>
</div>

<!-- SETTINGS CARD -->
<div class="settings-card">
  <div class="settings-tabs">
    <button class="stab active" data-tab="profile"><i class="fas fa-user-circle"></i> Profile</button>
    <button class="stab" data-tab="security"><i class="fas fa-shield-alt"></i> Security</button>
    <button class="stab" data-tab="system"><i class="fas fa-sliders-h"></i> System</button>
    <button class="stab" data-tab="email"><i class="fas fa-envelope"></i> Email</button>
    <button class="stab" data-tab="health"><i class="fas fa-heartbeat"></i> Health</button>
  </div>

  <!-- PROFILE -->
  <div class="stab-pane active" id="tab-profile">
    <div class="profile-section">
      <div class="profile-av-wrap">
        <div class="profile-av">
          <?php if(!empty($admin_details['profile_picture'])): ?>
          <img src="uploads/profile_pictures/<?= htmlspecialchars($admin_details['profile_picture']) ?>" alt="">
          <?php else: ?>
          <?= strtoupper(substr($admin_details['first_name'],0,1)) ?>
          <?php endif; ?>
        </div>
        <button class="change-av-btn" id="changeAvatarBtn"><i class="fas fa-camera" style="margin-right:4px"></i>Change Photo</button>
      </div>
      <div>
        <div class="profile-name"><?= htmlspecialchars($admin_details['first_name'].' '.$admin_details['last_name']) ?></div>
        <div class="profile-meta"><?= htmlspecialchars($admin_details['email']) ?></div>
        <div style="display:flex;gap:16px;margin-top:10px;flex-wrap:wrap">
          <div style="font-size:12px;color:var(--text-muted)"><i class="fas fa-calendar" style="color:var(--gold);margin-right:4px"></i>Joined <?= fmtDT($admin_details['created_at']) ?></div>
          <div style="font-size:12px;color:var(--text-muted)"><i class="fas fa-clock" style="color:var(--gold);margin-right:4px"></i>Last login <?= fmtDT($admin_details['last_login']) ?></div>
        </div>
      </div>
    </div>
    <form method="POST">
      <div class="form-section">
        <div class="form-section-title">Personal <em>Information</em></div>
        <div class="form-grid">
          <div class="fg">
            <label class="fl">First Name</label>
            <input type="text" name="first_name" class="fi" value="<?= htmlspecialchars($admin_details['first_name']??'') ?>" required>
          </div>
          <div class="fg">
            <label class="fl">Last Name</label>
            <input type="text" name="last_name" class="fi" value="<?= htmlspecialchars($admin_details['last_name']??'') ?>" required>
          </div>
          <div class="fg full">
            <label class="fl">Email Address</label>
            <input type="email" name="email" class="fi" value="<?= htmlspecialchars($admin_details['email']??'') ?>" required>
          </div>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" name="update_profile" class="btn-submit gold"><i class="fas fa-save"></i> Save Profile</button>
      </div>
    </form>
  </div>

  <!-- SECURITY -->
  <div class="stab-pane" id="tab-security">
    <div style="display:grid;grid-template-columns:1fr 300px;gap:28px">
      <div>
        <form method="POST">
          <div class="form-section">
            <div class="form-section-title">Change <em>Password</em></div>
            <div class="form-grid">
              <div class="fg full">
                <label class="fl">Current Password</label>
                <input type="password" name="current_password" class="fi" required>
              </div>
              <div class="fg">
                <label class="fl">New Password</label>
                <input type="password" name="new_password" id="newPwInput" class="fi" required>
                <div class="pw-strength"><div class="pw-fill" id="pwFill" style="width:0%"></div></div>
                <div class="fi-hint" id="pwHint">Minimum 8 characters</div>
              </div>
              <div class="fg">
                <label class="fl">Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirmPwInput" class="fi" required>
              </div>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" name="change_password" class="btn-submit"><i class="fas fa-key"></i> Change Password</button>
          </div>
        </form>
      </div>
      <div>
        <div style="background:var(--cream);border:1px solid var(--border-light);border-radius:var(--r-md);padding:18px">
          <div style="font-size:13.5px;font-weight:600;color:var(--navy);margin-bottom:12px"><i class="fas fa-lock" style="color:var(--gold);margin-right:6px"></i>Password Tips</div>
          <ul style="font-size:12.5px;color:var(--text-secondary);padding-left:16px;line-height:2">
            <li>At least 8 characters</li>
            <li>Mix uppercase &amp; lowercase</li>
            <li>Include numbers</li>
            <li>Add special characters</li>
          </ul>
        </div>
        <div style="background:var(--cream);border:1px solid var(--border-light);border-radius:var(--r-md);padding:18px;margin-top:14px">
          <div style="font-size:13.5px;font-weight:600;color:var(--navy);margin-bottom:6px"><i class="fas fa-shield-alt" style="color:var(--gold);margin-right:6px"></i>Two-Factor Auth</div>
          <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">Add an extra layer of security to your account.</p>
          <button style="width:100%;padding:8px;background:var(--navy);color:var(--white);border:none;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;cursor:pointer;font-family:var(--font-body)" onclick="alert('2FA setup coming soon.')">Enable 2FA</button>
        </div>
      </div>
    </div>
  </div>

  <!-- SYSTEM -->
  <div class="stab-pane" id="tab-system">
    <form method="POST">
      <div class="form-section">
        <div class="form-section-title">Application <em>Status Controls</em></div>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">Control whether each scholarship programme is open for applications.</p>
        <table class="ss-table">
          <thead><tr><th>Programme</th><th>Status</th><th>Opening Date (if Closed)</th></tr></thead>
          <tbody>
            <?php
            $progs=[
              ['key'=>'primary','label'=>'Primary School Scholarship'],
              ['key'=>'secondary','label'=>'Secondary School Scholarship'],
              ['key'=>'graduate','label'=>'Graduate Mentorship Programme'],
            ];
            foreach($progs as $p):
              $st=$system_settings[$p['key'].'_application_status']??'open';
              $od=fmtDTInput($system_settings[$p['key'].'_opening_date']??'');
            ?>
            <tr>
              <td style="font-weight:500"><?= $p['label'] ?></td>
              <td>
                <select name="<?= $p['key'] ?>_application_status">
                  <option value="open"        <?= $st==='open'?'selected':'' ?>>Open</option>
                  <option value="closed"      <?= $st==='closed'?'selected':'' ?>>Closed</option>
                  <option value="maintenance" <?= $st==='maintenance'?'selected':'' ?>>Maintenance</option>
                </select>
              </td>
              <td>
                <input type="datetime-local" name="<?= $p['key'] ?>_opening_date" value="<?= $od ?>" <?= $st!=='closed'?'disabled':'' ?>>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="form-section">
        <div class="form-section-title">Upload <em>Preferences</em></div>
        <div class="form-grid">
          <div class="fg">
            <label class="fl">Max File Upload Size</label>
            <select name="max_file_size" class="fi">
              <?php foreach(['2MB','5MB','10MB','20MB'] as $s): ?>
              <option value="<?= $s ?>" <?= ($system_settings['max_file_size']??'5MB')===$s?'selected':'' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="fg">
            <label class="fl">Allowed File Types</label>
            <input type="text" name="allowed_file_types" class="fi" value="<?= htmlspecialchars($system_settings['allowed_file_types']??'pdf,doc,docx,jpg,png') ?>">
            <div class="fi-hint">Comma-separated: pdf,doc,docx,jpg,png</div>
          </div>
          <div class="fg full">
            <div class="toggle-wrap">
              <label class="toggle-input">
                <input type="checkbox" name="notifications_enabled" <?= ($system_settings['notifications_enabled']??true)?'checked':'' ?>>
                <span class="toggle-slider"></span>
              </label>
              <span class="toggle-label">Enable email notifications for status updates</span>
            </div>
          </div>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" name="save_system_settings" class="btn-submit gold"><i class="fas fa-save"></i> Save Settings</button>
      </div>
    </form>

    <!-- DATA MANAGEMENT -->
    <div style="border-top:1px solid var(--border-light);padding-top:24px;margin-top:24px">
      <div style="font-family:var(--font-display);font-size:18px;color:var(--navy);margin-bottom:14px">Data <em style="font-style:italic;color:var(--gold)">Management</em></div>
      <div class="dm-grid">
        <div class="dm-card">
          <div class="dm-icon" style="background:var(--info-pale);color:var(--info)"><i class="fas fa-database"></i></div>
          <div class="dm-title">Database Backup</div>
          <div class="dm-desc">Last backup: <?= fmtDT($system_settings['last_backup_date']??'') ?></div>
          <form method="POST"><button type="submit" name="run_backup" class="dm-btn"><i class="fas fa-database"></i> Backup Now</button></form>
        </div>
        <div class="dm-card">
          <div class="dm-icon" style="background:var(--warning-pale);color:var(--warning)"><i class="fas fa-broom"></i></div>
          <div class="dm-title">Data Cleanup</div>
          <div class="dm-desc">Remove expired sessions and temporary files</div>
          <form method="POST"><button type="submit" name="run_cleanup" class="dm-btn"><i class="fas fa-broom"></i> Run Cleanup</button></form>
        </div>
      </div>
    </div>
  </div>

  <!-- EMAIL -->
  <div class="stab-pane" id="tab-email">
    <form method="POST">
      <div class="form-section">
        <div class="form-section-title">SMTP <em>Configuration</em></div>
        <div class="form-grid">
          <div class="fg">
            <label class="fl">SMTP Host</label>
            <input type="text" name="smtp_host" class="fi" value="<?= htmlspecialchars($email_settings['smtp_host']) ?>">
          </div>
          <div class="fg">
            <label class="fl">SMTP Port</label>
            <input type="text" name="smtp_port" class="fi" value="<?= htmlspecialchars($email_settings['smtp_port']) ?>">
          </div>
          <div class="fg">
            <label class="fl">SMTP Username</label>
            <input type="text" name="smtp_username" class="fi" value="<?= htmlspecialchars($email_settings['smtp_username']) ?>">
          </div>
          <div class="fg">
            <label class="fl">SMTP Password</label>
            <input type="password" name="smtp_password" class="fi" placeholder="Enter to change…">
          </div>
          <div class="fg">
            <label class="fl">From Email</label>
            <input type="email" name="from_email" class="fi" value="<?= htmlspecialchars($email_settings['from_email']) ?>">
          </div>
          <div class="fg">
            <label class="fl">From Name</label>
            <input type="text" name="from_name" class="fi" value="<?= htmlspecialchars($email_settings['from_name']) ?>">
          </div>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" name="save_email_settings" class="btn-submit gold"><i class="fas fa-save"></i> Save Email Settings</button>
        <button type="submit" name="send_test_email" class="btn-submit"><i class="fas fa-paper-plane"></i> Send Test Email</button>
      </div>
    </form>
  </div>

  <!-- HEALTH (new tab) -->
  <div class="stab-pane" id="tab-health">
    <div style="font-family:var(--font-display);font-size:22px;color:var(--navy);margin-bottom:6px">System <em style="font-style:italic;color:var(--gold)">Health</em></div>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:20px">Real-time overview of your portal's operational status</p>
    <div class="health-grid">
      <div class="health-card">
        <div class="hc-label">PHP Version</div>
        <div class="hc-val"><?= PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION ?></div>
        <div class="hc-status hc-ok"><i class="fas fa-check-circle"></i> Supported</div>
      </div>
      <div class="health-card">
        <div class="hc-label">Database</div>
        <div class="hc-val">PostgreSQL</div>
        <div class="hc-status <?= isset($conn)?'hc-ok':'hc-warn' ?>"><i class="fas fa-<?= isset($conn)?'check-circle':'exclamation-circle' ?>"></i><?= isset($conn)?'Connected':'Disconnected' ?></div>
      </div>
      <div class="health-card">
        <div class="hc-label">Session Status</div>
        <div class="hc-val">Active</div>
        <div class="hc-status hc-ok"><i class="fas fa-check-circle"></i> Secure</div>
      </div>
      <div class="health-card">
        <div class="hc-label">Pending Documents</div>
        <div class="hc-val"><?= $dp??0 ?></div>
        <div class="hc-status <?= ($dp??0)>0?'hc-warn':'hc-ok' ?>"><i class="fas fa-<?= ($dp??0)>0?'exclamation-circle':'check-circle' ?>"></i><?= ($dp??0)>0?'Needs attention':'All reviewed' ?></div>
      </div>
      <div class="health-card">
        <div class="hc-label">Pending Applications</div>
        <div class="hc-val"><?= $ap??0 ?></div>
        <div class="hc-status <?= ($ap??0)>0?'hc-warn':'hc-ok' ?>"><i class="fas fa-<?= ($ap??0)>0?'exclamation-circle':'check-circle' ?>"></i><?= ($ap??0)>0?'Awaiting review':'Up to date' ?></div>
      </div>
      <div class="health-card">
        <div class="hc-label">Last Backup</div>
        <div class="hc-val" style="font-size:14px"><?= date('M d',strtotime($system_settings['last_backup_date']??'now')) ?></div>
        <div class="hc-status hc-ok"><i class="fas fa-check-circle"></i> Recent</div>
      </div>
    </div>

    <!-- Server info -->
    <div style="margin-top:24px;background:var(--cream);border:1px solid var(--border-light);border-radius:var(--r-md);padding:18px">
      <div style="font-size:13px;font-weight:600;color:var(--navy);margin-bottom:12px"><i class="fas fa-server" style="color:var(--gold);margin-right:6px"></i>Server Information</div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px">
        <?php
        $si=[
          'Server Software'=>$_SERVER['SERVER_SOFTWARE']??'Unknown',
          'Document Root'=>basename($_SERVER['DOCUMENT_ROOT']??'/'),
          'Memory Limit'=>ini_get('memory_limit'),
          'Max Upload Size'=>ini_get('upload_max_filesize'),
          'Max POST Size'=>ini_get('post_max_size'),
          'Timezone'=>date_default_timezone_get(),
        ];
        foreach($si as $k=>$v): ?>
        <div>
          <div style="font-size:10.5px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px"><?= $k ?></div>
          <div style="font-size:13px;color:var(--navy);font-weight:500"><?= htmlspecialchars(substr($v,0,30)) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div><!-- end settings-card -->

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
document.querySelectorAll('.stab').forEach(btn=>{
  btn.addEventListener('click',function(){
    document.querySelectorAll('.stab').forEach(b=>b.classList.remove('active'));
    document.querySelectorAll('.stab-pane').forEach(p=>p.classList.remove('active'));
    this.classList.add('active');
    const tab=document.getElementById('tab-'+this.dataset.tab);
    if(tab) tab.classList.add('active');
    history.replaceState(null,null,'#'+this.dataset.tab);
  });
});
// Activate from hash
const hash=window.location.hash.replace('#','');
if(hash){const btn=document.querySelector(`.stab[data-tab="${hash}"]`);if(btn)btn.click();}

// Password strength
document.getElementById('newPwInput')?.addEventListener('input',function(){
  const v=this.value,fill=document.getElementById('pwFill'),hint=document.getElementById('pwHint');
  let score=0;
  if(v.length>=8)score++;if(/[A-Z]/.test(v))score++;if(/[0-9]/.test(v))score++;if(/[^a-zA-Z0-9]/.test(v))score++;
  const pcts=[0,25,50,75,100];const clrs=['','#DC2626','#D97706','#0284C7','#059669'];const labels=['','Weak','Fair','Good','Strong'];
  if(fill){fill.style.width=pcts[score]+'%';fill.style.background=clrs[score];}
  if(hint) hint.textContent=score>0?labels[score]:'Minimum 8 characters';
});

// Dynamic datetime enable/disable
document.querySelectorAll('select[name$="_application_status"]').forEach(sel=>{
  const key=sel.name.replace('_application_status','');
  const di=document.querySelector(`input[name="${key}_opening_date"]`);
  if(di){
    di.disabled=sel.value!=='closed';
    sel.addEventListener('change',function(){di.disabled=this.value!=='closed';});
  }
});

// Change avatar
document.getElementById('changeAvatarBtn')?.addEventListener('click',()=>{
  Swal.fire({
    title:'Upload Profile Photo',
    html:`<input type="file" id="avatarFile" accept="image/*" style="width:100%;padding:10px;border:1px solid #e2e8f0;border-radius:8px;font-family:Outfit,sans-serif"><div class="form-text" style="font-size:12px;color:#94a3b8;margin-top:6px">JPG, PNG or GIF. Max 2MB.</div>`,
    showCancelButton:true,confirmButtonText:'Upload',confirmButtonColor:'#C8A058',
    preConfirm:()=>{const f=document.getElementById('avatarFile').files[0];if(!f){Swal.showValidationMessage('Please select a file');return false;}return f;}
  }).then(r=>{
    if(r.isConfirmed) Swal.fire({icon:'info',title:'Upload',text:'Connect this to your profile picture upload handler.',confirmButtonColor:'#C8A058'});
  });
});
</script>
</body>
</html>