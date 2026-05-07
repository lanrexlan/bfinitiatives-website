<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    $_SESSION['error'] = "Please log in";
    header('Location: admin-login.php'); exit();
}
require_once 'includes/config.php';
require_once 'includes/db.php';

$messages = $category_counts = [];
$error_message = $success_message = '';
$unread_count = 0;
$selected_category = $_GET['category'] ?? 'all';
$search_query      = $_GET['search'] ?? '';
$filter_read       = $_GET['read'] ?? 'all'; // new: filter by read/unread
$page     = max(1,(int)($_GET['page']??1));
$per_page = 12;
$total_messages = 0;

// Handle actions
if (isset($_GET['action'])) {
    try {
        $db=new Database();$conn=$db->getConnection();
        switch($_GET['action']){
            case 'mark_read':   $conn->prepare("UPDATE contact_messages SET read_status=TRUE  WHERE id=:id")->execute([':id'=>$_GET['id']]);$success_message="Marked as read.";break;
            case 'mark_unread': $conn->prepare("UPDATE contact_messages SET read_status=FALSE WHERE id=:id")->execute([':id'=>$_GET['id']]);$success_message="Marked as unread.";break;
            case 'delete':      $conn->prepare("DELETE FROM contact_messages WHERE id=:id")->execute([':id'=>$_GET['id']]);$success_message="Message deleted.";break;
            case 'mark_all_read': $conn->prepare("UPDATE contact_messages SET read_status=TRUE WHERE read_status=FALSE")->execute();$success_message="All messages marked as read.";break;
            case 'star':   $conn->prepare("UPDATE contact_messages SET is_starred=TRUE  WHERE id=:id")->execute([':id'=>$_GET['id']]);break;
            case 'unstar': $conn->prepare("UPDATE contact_messages SET is_starred=FALSE WHERE id=:id")->execute([':id'=>$_GET['id']]);break;
        }
    } catch(Exception $e){ $error_message="Action failed: ".$e->getMessage(); }
}

// Handle reply
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['send_reply'])) {
    $to_email  = $_POST['to_email']  ?? '';
    $to_name   = $_POST['to_name']   ?? '';
    $subject   = $_POST['subject']   ?? '';
    $message   = $_POST['message']   ?? '';
    $msg_id    = $_POST['message_id']?? '';
    if (empty($to_email)||empty($subject)||empty($message)) {
        $error_message="Please fill all required fields.";
    } else {
        try {
            $db=new Database();$conn=$db->getConnection();
            $conn->exec("CREATE TABLE IF NOT EXISTS message_replies(id SERIAL PRIMARY KEY,message_id INTEGER,sent_by INTEGER,to_email VARCHAR(255),to_name VARCHAR(255),subject VARCHAR(255),reply_text TEXT,sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
            $conn->prepare("INSERT INTO message_replies(message_id,sent_by,to_email,to_name,subject,reply_text) VALUES(:mid,:sb,:te,:tn,:s,:rt)")
                 ->execute([':mid'=>$msg_id,':sb'=>$_SESSION['admin_id']??1,':te'=>$to_email,':tn'=>$to_name,':s'=>$subject,':rt'=>$message]);
            $conn->prepare("UPDATE contact_messages SET read_status=TRUE WHERE id=:id")->execute([':id'=>$msg_id]);
            $success_message="Reply sent to {$to_name}.";
        } catch(Exception $e){ $error_message="Reply failed: ".$e->getMessage(); }
    }
}

// Fetch messages
try {
    $db=new Database();$conn=$db->getConnection();
    $conn->exec("ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS is_starred BOOLEAN DEFAULT FALSE");

    $uc=$conn->query("SELECT COUNT(*) FROM contact_messages WHERE read_status=FALSE");
    $unread_count=$uc->fetchColumn();

    $cq=$conn->query("SELECT category,COUNT(*) cnt FROM contact_messages GROUP BY category");
    while($r=$cq->fetch(PDO::FETCH_ASSOC)) $category_counts[$r['category']]=$r['cnt'];
    $total_ct=$conn->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();
    $category_counts['all']=$total_ct;

    $where=[]; $params=[];
    if ($selected_category!=='all'){ $where[]="category=:cat"; $params[':cat']=$selected_category; }
    if (!empty($search_query)){       $where[]="(name ILIKE :s OR email ILIKE :s OR subject ILIKE :s OR message ILIKE :s)"; $params[':s']="%$search_query%"; }
    if ($filter_read==='unread'){     $where[]="read_status=FALSE"; }
    if ($filter_read==='read'){       $where[]="read_status=TRUE"; }
    if ($filter_read==='starred'){    $where[]="is_starred=TRUE"; }
    $wc=$where?" WHERE ".implode(" AND ",$where):"";

    $tc=$conn->prepare("SELECT COUNT(*) FROM contact_messages $wc");
    $tc->execute($params); $total_messages=(int)$tc->fetchColumn();
    $total_pages=max(1,ceil($total_messages/$per_page));
    $offset=($page-1)*$per_page;

    $q="SELECT id,name,email,subject,message,category,read_status,is_starred,created_at FROM contact_messages $wc ORDER BY created_at DESC LIMIT :lim OFFSET :off";
    $stmt=$conn->prepare($q);
    foreach($params as $k=>$v) $stmt->bindValue($k,$v);
    $stmt->bindValue(':lim',$per_page,PDO::PARAM_INT);
    $stmt->bindValue(':off',$offset,PDO::PARAM_INT);
    $stmt->execute();
    $messages=$stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e){ $error_message="Database error: ".$e->getMessage(); }

function relTime($d){$diff=time()-strtotime($d);if($diff<60)return"Just now";if($diff<3600)return floor($diff/60)."m ago";if($diff<86400)return floor($diff/3600)."h ago";if($diff<172800)return"Yesterday";return date('M d, Y',strtotime($d));}
function catColor($c){return['scholarship'=>'cat-green','technical'=>'cat-red','feedback'=>'cat-amber'][$c]??'cat-blue';}

$admin_full_name  = $_SESSION['admin_name'] ?? 'Admin';
$admin_first_name = explode(' ', $admin_full_name)[0];
$admin_role       = ucfirst($_SESSION['admin_role'] ?? 'Administrator');

// Sidebar notification counts
try {
    $dp=$conn->query("SELECT COUNT(*) FROM user_documents WHERE review_status='pending'")->fetchColumn();
    $ap=$conn->query("SELECT COUNT(*) FROM scholarship_applications WHERE status='pending'")->fetchColumn();
} catch(Exception $e){ $dp=$ap=0; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Messages · Admin Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<style>
:root{
  --midnight:#080E1C;--navy:#0D1829;--navy-mid:#132038;--navy-light:#1C2F52;
  --gold:#C8A058;--gold-bright:#E0B96C;--cream:#FAF6EF;--cream-dark:#F2EAD8;--white:#FFFFFF;
  --text-primary:#0D1829;--text-secondary:#4A526A;--text-muted:#8A92A8;--border-light:#E8E4DA;
  --admin-crimson:#9F1239;--admin-crimson-pale:rgba(159,18,57,.10);
  --success:#059669;--success-pale:rgba(5,150,105,.10);
  --warning:#D97706;--warning-pale:rgba(217,119,6,.10);
  --danger:#DC2626;--danger-pale:rgba(220,38,38,.10);
  --info:#0284C7;--info-pale:rgba(2,132,199,.10);
  --font-display:'Cormorant Garamond',Georgia,serif;
  --font-body:'Outfit',-apple-system,sans-serif;
  --ease:cubic-bezier(.25,.46,.45,.94);--transition:.3s var(--ease);
  --sidebar-width:268px;--header-height:64px;
  --r-sm:8px;--r-md:14px;--r-lg:20px;--r-xl:28px;
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
body{font-family:var(--font-body);background:#F0F2F7;color:var(--text-primary);line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden;}
a{text-decoration:none;color:inherit;}
/* Reuse sidebar/header styles */
.sidebar{position:fixed;left:0;top:0;width:var(--sidebar-width);height:100vh;background:var(--navy);z-index:200;display:flex;flex-direction:column;overflow:hidden;transition:transform var(--transition);}
.sidebar-top{padding:24px 20px 18px;border-bottom:1px solid rgba(255,255,255,.06);}
.sidebar-logo{display:flex;align-items:center;gap:11px;margin-bottom:28px;}
.sidebar-logomark{width:32px;height:32px;background:var(--gold);border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.sidebar-logo-text{font-family:var(--font-display);font-size:14.5px;font-weight:500;color:var(--white);line-height:1.2;}
.sidebar-logo-text span{display:block;font-family:var(--font-body);font-size:8.5px;letter-spacing:2px;text-transform:uppercase;color:rgba(200,160,88,.55);}
.admin-chip{display:inline-flex;align-items:center;gap:5px;background:var(--admin-crimson);color:white;font-size:8.5px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:3px 9px;border-radius:20px;margin-left:6px;flex-shrink:0;}
.sidebar-user{display:flex;align-items:center;gap:11px;}
.sidebar-avatar{width:38px;height:38px;border-radius:50%;background:var(--navy-light);border:2px solid rgba(200,160,88,.25);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.sidebar-avatar-init{font-family:var(--font-display);font-size:15px;font-weight:500;color:var(--gold-bright);}
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
.notif-dot{position:absolute;top:6px;right:6px;width:7px;height:7px;border-radius:50%;background:var(--admin-crimson);border:1.5px solid var(--white);}
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

/* STATS ROW */
.msg-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:22px;}
.ms-card{background:var(--white);border:1px solid var(--border-light);border-radius:var(--r-lg);padding:16px 20px;display:flex;align-items:center;gap:14px;transition:var(--transition);}
.ms-card:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(8,14,28,.07);}
.ms-icon{width:40px;height:40px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;}
.ms-val{font-family:var(--font-display);font-size:28px;font-weight:500;color:var(--navy);line-height:1;}
.ms-lbl{font-size:11.5px;color:var(--text-muted);margin-top:2px;}

/* MESSAGES LAYOUT */
.messages-layout{display:grid;grid-template-columns:220px 1fr;gap:20px;align-items:start;}

/* SIDEBAR PANEL */
.msg-sidebar{background:var(--white);border:1px solid var(--border-light);border-radius:var(--r-lg);overflow:hidden;position:sticky;top:calc(var(--header-height)+20px);}
.msg-sb-header{padding:16px 18px;border-bottom:1px solid var(--border-light);background:#F8F9FB;}
.msg-sb-title{font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;}
.msg-sb-link{display:flex;align-items:center;justify-content:space-between;padding:11px 18px;font-size:13px;color:var(--text-secondary);transition:var(--transition);border-left:2.5px solid transparent;}
.msg-sb-link:hover{background:var(--cream);color:var(--navy);}
.msg-sb-link.active{background:rgba(200,160,88,.08);color:var(--navy);border-left-color:var(--gold);font-weight:500;}
.msg-sb-link i{width:16px;text-align:center;margin-right:8px;font-size:12.5px;}
.msg-sb-cnt{font-size:11px;font-weight:600;padding:2px 7px;border-radius:10px;background:var(--cream);}
.msg-sb-link.active .msg-sb-cnt{background:rgba(200,160,88,.15);color:var(--gold);}
.cat-section{padding:8px 0;}
.cat-section-label{font-size:9.5px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);padding:6px 18px 4px;}

/* MAIN MESSAGES PANEL */
.msg-panel{background:var(--white);border:1px solid var(--border-light);border-radius:var(--r-lg);overflow:hidden;}
.msg-panel-header{padding:16px 22px;border-bottom:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;background:#F8F9FB;}
.msg-panel-title{font-family:var(--font-display);font-size:19px;color:var(--navy);}
.msg-panel-title em{font-style:italic;color:var(--gold);}
.msg-search{position:relative;}
.msg-search input{padding:8px 12px 8px 34px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-size:12.5px;font-family:var(--font-body);color:var(--text-primary);background:var(--white);width:220px;outline:none;transition:var(--transition);}
.msg-search input:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,160,88,.12);width:260px;}
.msg-search i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:11.5px;}
.panel-actions{display:flex;gap:6px;}
.pa-btn{width:32px;height:32px;border-radius:var(--r-sm);background:var(--cream);border:1px solid var(--border-light);cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:12.5px;transition:var(--transition);}
.pa-btn:hover{background:var(--navy);color:var(--gold-bright);border-color:transparent;}

/* MESSAGE ITEM */
.msg-item{display:flex;align-items:flex-start;gap:14px;padding:16px 22px;border-bottom:1px solid var(--border-light);transition:var(--transition);cursor:pointer;position:relative;}
.msg-item:last-child{border-bottom:none;}
.msg-item:hover{background:#FAFBFD;}
.msg-item.unread{background:linear-gradient(to right,rgba(200,160,88,.03),transparent);}
.msg-item.unread::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--gold);}
.msg-avatar{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--navy),var(--navy-light));display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:17px;color:var(--gold-bright);flex-shrink:0;}
.msg-body{flex:1;min-width:0;}
.msg-row1{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:3px;}
.msg-sender{font-size:13.5px;font-weight:600;color:var(--text-primary);}
.msg-sender.unread-name{color:var(--navy);}
.msg-time{font-size:11px;color:var(--text-muted);white-space:nowrap;flex-shrink:0;margin-left:10px;}
.msg-subject{font-size:13px;font-weight:500;color:var(--text-secondary);margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.msg-preview{font-size:12px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:500px;}
.msg-meta{display:flex;align-items:center;gap:6px;margin-top:6px;}
.cat-tag{padding:2px 8px;border-radius:20px;font-size:10.5px;font-weight:500;}
.cat-blue{background:var(--info-pale);color:var(--info);}
.cat-green{background:var(--success-pale);color:var(--success);}
.cat-red{background:var(--danger-pale);color:var(--danger);}
.cat-amber{background:var(--warning-pale);color:var(--warning);}
.unread-dot{width:7px;height:7px;border-radius:50%;background:var(--gold);flex-shrink:0;}
.msg-actions-right{display:flex;flex-direction:column;align-items:flex-end;gap:8px;flex-shrink:0;}
.star-btn{background:none;border:none;cursor:pointer;font-size:14px;color:var(--border-light);transition:var(--transition);padding:2px;}
.star-btn.starred{color:#EAB308;}
.star-btn:hover{color:#EAB308;}
.msg-btns{display:flex;gap:5px;}
.mb-btn{display:inline-flex;align-items:center;gap:4px;padding:5px 10px;font-size:11.5px;font-weight:500;border-radius:var(--r-sm);border:none;cursor:pointer;transition:var(--transition);font-family:var(--font-body);}
.mb-btn:hover{transform:translateY(-1px);}
.mb-primary{background:var(--navy);color:var(--white);}
.mb-primary:hover{background:var(--navy-light);}
.mb-ghost{background:var(--cream);color:var(--text-secondary);border:1px solid var(--border-light);}
.mb-reply{background:rgba(200,160,88,.1);color:var(--gold);}
.mb-reply:hover{background:var(--gold);color:var(--midnight);}
.mb-del{background:var(--danger-pale);color:var(--danger);}
.mb-del:hover{background:var(--danger);color:var(--white);}

/* EMPTY */
.msg-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 24px;text-align:center;}
.me-icon{font-size:44px;color:var(--border-light);margin-bottom:16px;}
.me-title{font-family:var(--font-display);font-size:22px;color:var(--navy);margin-bottom:6px;}
.me-sub{font-size:13px;color:var(--text-muted);margin-bottom:20px;}

/* PAGINATION */
.msg-footer{padding:14px 22px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;background:#FAFBFC;flex-wrap:wrap;gap:10px;}
.mf-text{font-size:12.5px;color:var(--text-muted);}
.pagination{display:flex;gap:4px;list-style:none;}
.page-item .page-link{display:flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 9px;border-radius:var(--r-sm);border:1px solid var(--border-light);background:var(--white);color:var(--text-secondary);font-size:12px;font-weight:500;transition:var(--transition);cursor:pointer;}
.page-item .page-link:hover{background:var(--cream);border-color:var(--gold);}
.page-item.active .page-link{background:var(--navy);border-color:var(--navy);color:var(--white);}
.page-item.disabled .page-link{opacity:.4;pointer-events:none;}

/* MODALS */
.modal-content{border:none;border-radius:var(--r-xl);overflow:hidden;box-shadow:0 20px 60px rgba(8,14,28,.2);}
.modal-header{background:var(--navy);color:var(--white);border-bottom:none;padding:20px 24px;}
.modal-title{font-family:var(--font-display);font-size:20px;}
.modal-title i{color:var(--gold);margin-right:8px;}
.btn-close{filter:invert(1);}
.modal-body{padding:24px;}
.modal-footer{border-top:1px solid var(--border-light);padding:16px 24px;}
.form-label{display:block;font-size:11.5px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;}
.form-control{width:100%;padding:10px 13px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13px;color:var(--text-primary);background:var(--white);transition:var(--transition);outline:none;}
.form-control:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,160,88,.12);}
.form-group{margin-bottom:16px;}
.canned-chips{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;}
.canned-chip{padding:4px 10px;background:var(--cream);border:1px solid var(--border-light);border-radius:20px;font-size:11.5px;color:var(--text-secondary);cursor:pointer;transition:var(--transition);}
.canned-chip:hover{background:var(--gold);color:var(--midnight);border-color:var(--gold);}
.btn-modal-gold{background:var(--gold);color:var(--midnight);border:none;padding:10px 22px;border-radius:var(--r-sm);font-size:13px;font-weight:500;cursor:pointer;}
.btn-modal-gold:hover{background:var(--gold-bright);}
.btn-modal-ghost{background:var(--cream);color:var(--text-secondary);border:1px solid var(--border-light);padding:10px 22px;border-radius:var(--r-sm);font-size:13px;cursor:pointer;}

@media(max-width:1100px){.messages-layout{grid-template-columns:1fr;}.msg-sidebar{position:static;}}
@media(max-width:991px){
  .sidebar{transform:translateX(-100%);}
  .sidebar.active{transform:translateX(0);}
  .header{left:0;}.main,.portal-footer{margin-left:0;}
  .mobile-toggle{display:flex;}.header-breadcrumb{display:none;}
  .msg-stats{grid-template-columns:repeat(2,1fr);}
}
@media(max-width:600px){.msg-stats{grid-template-columns:1fr 1fr};}
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
    <div class="nav-item"><a href="applications.php" class="nav-link"><i class="fas fa-tasks"></i> Applications<?php if($ap>0): ?><span class="nav-badge"><?= $ap ?></span><?php endif; ?></a></div>
    <div class="nav-section-label">Management</div>
    <div class="nav-item"><a href="admin-document-review.php" class="nav-link"><i class="fas fa-file-alt"></i> Review Documents<?php if($dp>0): ?><span class="nav-badge"><?= $dp ?></span><?php endif; ?></a></div>
    <div class="nav-item"><a href="scholarships.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Scholarships</a></div>
    <div class="nav-item"><a href="admin-messages.php" class="nav-link active"><i class="fas fa-envelope"></i> Messages<?php if($unread_count>0): ?><span class="nav-badge"><?= $unread_count ?></span><?php endif; ?></a></div>
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
      <strong>Messages</strong>
    </div>
  </div>
  <div class="header-right">
    <div class="header-time" id="headerTime"></div>
    <button class="header-icon-btn" style="position:relative" onclick="window.location.href='admin-dashboard.php'">
      <i class="fas fa-home"></i><?php if($unread_count>0): ?><div class="notif-dot"></div><?php endif; ?>
    </button>
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
<?php if ($error_message): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error_message) ?></div><?php endif; ?>
<?php if ($success_message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success_message) ?></div><?php endif; ?>

<!-- PAGE HEADER -->
<div class="page-header">
  <div>
    <div class="page-eyebrow"><i class="fas fa-inbox" style="margin-right:5px"></i> Communications Hub</div>
    <h1 class="page-title">Scholar <em>Messages</em></h1>
    <p class="page-sub">Respond to contact form enquiries from scholars and applicants</p>
  </div>
  <div class="hdr-actions">
    <?php if($unread_count>0): ?>
    <a href="?action=mark_all_read" class="btn-gold"><i class="fas fa-check-double"></i> Mark All Read</a>
    <?php endif; ?>
    <a href="admin-dashboard.php" class="btn-ghost"><i class="fas fa-arrow-left"></i> Dashboard</a>
  </div>
</div>

<!-- STATS -->
<div class="msg-stats">
  <div class="ms-card">
    <div class="ms-icon" style="background:var(--info-pale);color:var(--info)"><i class="fas fa-inbox"></i></div>
    <div><div class="ms-val"><?= $category_counts['all']??0 ?></div><div class="ms-lbl">Total Messages</div></div>
  </div>
  <div class="ms-card">
    <div class="ms-icon" style="background:var(--warning-pale);color:var(--warning)"><i class="fas fa-envelope"></i></div>
    <div><div class="ms-val" style="color:var(--warning)"><?= $unread_count ?></div><div class="ms-lbl">Unread</div></div>
  </div>
  <div class="ms-card">
    <div class="ms-icon" style="background:var(--success-pale);color:var(--success)"><i class="fas fa-check-circle"></i></div>
    <div><div class="ms-val" style="color:var(--success)"><?= ($category_counts['all']??0) - $unread_count ?></div><div class="ms-lbl">Read</div></div>
  </div>
  <div class="ms-card">
    <div class="ms-icon" style="background:rgba(234,179,8,.1);color:#EAB308"><i class="fas fa-star"></i></div>
    <div><div class="ms-val" style="color:#EAB308"><?php try{echo $conn->query("SELECT COUNT(*) FROM contact_messages WHERE is_starred=TRUE")->fetchColumn();}catch(Exception $e){echo 0;} ?></div><div class="ms-lbl">Starred</div></div>
  </div>
</div>

<!-- MESSAGES LAYOUT -->
<div class="messages-layout">

  <!-- LEFT SIDEBAR -->
  <div class="msg-sidebar">
    <div class="msg-sb-header"><div class="msg-sb-title">Inbox</div></div>

    <div class="cat-section">
      <div class="cat-section-label">View</div>
      <?php
      $views=[
        ['filter_read'=>'all',      'label'=>'All Messages', 'icon'=>'inbox',   'cnt'=>$category_counts['all']??0],
        ['filter_read'=>'unread',   'label'=>'Unread',       'icon'=>'envelope','cnt'=>$unread_count],
        ['filter_read'=>'starred',  'label'=>'Starred',      'icon'=>'star',    'cnt'=>0],
      ];
      foreach($views as $v):
        $url="?filter_read={$v['filter_read']}".($selected_category!=='all'?"&category=$selected_category":"").(!empty($search_query)?"&search=".urlencode($search_query):"");
        $isA=$filter_read===$v['filter_read']&&$selected_category==='all';
      ?>
      <a href="<?= $url ?>" class="msg-sb-link <?= $isA?'active':'' ?>">
        <i class="fas fa-<?= $v['icon'] ?>"></i><?= $v['label'] ?>
        <span class="msg-sb-cnt"><?= $v['cnt'] ?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <div class="cat-section" style="border-top:1px solid var(--border-light)">
      <div class="cat-section-label">Categories</div>
      <?php
      $cats=[
        ['key'=>'all',        'label'=>'All',        'icon'=>'list'],
        ['key'=>'general',    'label'=>'General',    'icon'=>'comment'],
        ['key'=>'scholarship','label'=>'Scholarship','icon'=>'graduation-cap'],
        ['key'=>'technical',  'label'=>'Technical',  'icon'=>'wrench'],
        ['key'=>'feedback',   'label'=>'Feedback',   'icon'=>'thumbs-up'],
      ];
      foreach($cats as $c):
        $url="?category={$c['key']}".($filter_read!=='all'?"&filter_read=$filter_read":"").(!empty($search_query)?"&search=".urlencode($search_query):"");
        $isA=$selected_category===$c['key'];
      ?>
      <a href="<?= $url ?>" class="msg-sb-link <?= $isA?'active':'' ?>">
        <i class="fas fa-<?= $c['icon'] ?>"></i><?= $c['label'] ?>
        <span class="msg-sb-cnt"><?= $c['key']==='all'?($category_counts['all']??0):($category_counts[$c['key']]??0) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- MAIN PANEL -->
  <div class="msg-panel">
    <div class="msg-panel-header">
      <div class="msg-panel-title"><?= ucfirst(str_replace('_',' ',$selected_category)) ?> <em>Messages</em></div>
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <form method="GET" style="display:inline">
          <?php if($selected_category!=='all'): ?><input type="hidden" name="category" value="<?= htmlspecialchars($selected_category) ?>"><?php endif; ?>
          <?php if($filter_read!=='all'): ?><input type="hidden" name="filter_read" value="<?= htmlspecialchars($filter_read) ?>"><?php endif; ?>
          <div class="msg-search">
            <i class="fas fa-search"></i>
            <input type="text" name="search" placeholder="Search messages…" value="<?= htmlspecialchars($search_query) ?>" onkeydown="if(event.key==='Enter'){this.form.submit()}">
          </div>
        </form>
        <div class="panel-actions">
          <button class="pa-btn" title="Refresh" onclick="location.reload()"><i class="fas fa-sync-alt"></i></button>
        </div>
      </div>
    </div>

    <?php if(empty($messages)): ?>
    <div class="msg-empty">
      <div class="me-icon"><i class="fas fa-inbox"></i></div>
      <div class="me-title">No Messages</div>
      <p class="me-sub"><?= !empty($search_query)?"No messages match your search.":($selected_category!=='all'?"No {$selected_category} messages found.":"Your inbox is empty.") ?></p>
      <a href="admin-messages.php" class="btn-gold"><i class="fas fa-sync-alt"></i> Reset</a>
    </div>
    <?php else: ?>

    <?php foreach($messages as $msg): ?>
    <div class="msg-item <?= !$msg['read_status']?'unread':'' ?>">
      <div class="msg-avatar"><?= strtoupper(substr($msg['name'],0,1)) ?></div>
      <div class="msg-body">
        <div class="msg-row1">
          <span class="msg-sender <?= !$msg['read_status']?'unread-name':'' ?>"><?= htmlspecialchars($msg['name']) ?></span>
          <span class="msg-time"><?= relTime($msg['created_at']) ?></span>
        </div>
        <div class="msg-subject"><?= htmlspecialchars($msg['subject']) ?></div>
        <div class="msg-preview"><?= htmlspecialchars($msg['message']) ?></div>
        <div class="msg-meta">
          <?php if(!$msg['read_status']): ?><span class="unread-dot"></span><?php endif; ?>
          <span class="cat-tag <?= catColor($msg['category']??'general') ?>"><?= ucfirst($msg['category']??'general') ?></span>
          <span style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($msg['email']) ?></span>
        </div>
      </div>
      <div class="msg-actions-right">
        <a href="?action=<?= $msg['is_starred']?'unstar':'star' ?>&id=<?= $msg['id'] ?>&category=<?= urlencode($selected_category) ?>&page=<?= $page ?>">
          <button class="star-btn <?= $msg['is_starred']?'starred':'' ?>"><i class="fas fa-star"></i></button>
        </a>
        <div class="msg-btns">
          <button class="mb-btn mb-primary view-msg-btn"
            data-id="<?= $msg['id'] ?>"
            data-name="<?= htmlspecialchars($msg['name']) ?>"
            data-email="<?= htmlspecialchars($msg['email']) ?>"
            data-subject="<?= htmlspecialchars($msg['subject']) ?>"
            data-message="<?= htmlspecialchars($msg['message']) ?>"
            data-category="<?= htmlspecialchars($msg['category']??'general') ?>"
            data-date="<?= relTime($msg['created_at']) ?>"
            data-read="<?= $msg['read_status']?1:0 ?>">
            <i class="fas fa-eye"></i> View
          </button>
          <button class="mb-btn mb-reply reply-btn"
            data-id="<?= $msg['id'] ?>"
            data-name="<?= htmlspecialchars($msg['name']) ?>"
            data-email="<?= htmlspecialchars($msg['email']) ?>"
            data-subject="<?= htmlspecialchars($msg['subject']) ?>">
            <i class="fas fa-reply"></i>
          </button>
          <a href="?action=delete&id=<?= $msg['id'] ?>&category=<?= urlencode($selected_category) ?>&page=<?= $page ?>"
             onclick="return confirm('Delete this message?')"
             class="mb-btn mb-del"><i class="fas fa-trash"></i></a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if($total_pages>1): ?>
    <div class="msg-footer">
      <div class="mf-text">Showing <?= ($offset+1) ?>–<?= min($offset+$per_page,$total_messages) ?> of <?= $total_messages ?></div>
      <ul class="pagination">
        <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="?category=<?= urlencode($selected_category) ?>&filter_read=<?= $filter_read ?>&page=<?= $page-1 ?>&search=<?= urlencode($search_query) ?>"><i class="fas fa-chevron-left"></i></a></li>
        <?php for($i=max(1,$page-2);$i<=min($total_pages,max(1,$page-2)+4);$i++): ?>
        <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?category=<?= urlencode($selected_category) ?>&filter_read=<?= $filter_read ?>&page=<?= $i ?>&search=<?= urlencode($search_query) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
        <li class="page-item <?= $page>=$total_pages?'disabled':'' ?>"><a class="page-link" href="?category=<?= urlencode($selected_category) ?>&filter_read=<?= $filter_read ?>&page=<?= $page+1 ?>&search=<?= urlencode($search_query) ?>"><i class="fas fa-chevron-right"></i></a></li>
      </ul>
    </div>
    <?php endif; ?>

    <?php endif; ?>
  </div>
</div>

</main>

<!-- VIEW MESSAGE MODAL -->
<div class="modal fade" id="viewMsgModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-envelope-open"></i> Message Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div style="background:var(--cream);border-radius:var(--r-md);padding:16px;margin-bottom:16px;border:1px solid var(--border-light)">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <div style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px">From</div>
              <div style="font-size:14px;font-weight:500;color:var(--navy)" id="vm-name"></div>
              <div style="font-size:12px;color:var(--text-muted)" id="vm-email"></div>
            </div>
            <div>
              <div style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px">Category / Date</div>
              <div style="font-size:13px" id="vm-cat"></div>
              <div style="font-size:12px;color:var(--text-muted)" id="vm-date"></div>
            </div>
          </div>
          <div style="margin-top:12px">
            <div style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px">Subject</div>
            <div style="font-size:14px;font-weight:500;color:var(--navy)" id="vm-subject"></div>
          </div>
        </div>
        <div style="background:var(--white);border:1px solid var(--border-light);border-radius:var(--r-md);padding:20px;font-size:13.5px;color:var(--text-secondary);line-height:1.8;white-space:pre-line" id="vm-message"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-modal-ghost" data-bs-dismiss="modal">Close</button>
        <a href="#" id="vm-read-btn" class="btn-modal-ghost" style="font-size:13px;padding:10px 18px"><i class="fas fa-check" style="margin-right:6px"></i>Mark Read</a>
        <button type="button" class="btn-modal-gold" id="vm-reply-btn"><i class="fas fa-reply" style="margin-right:6px"></i>Reply</button>
      </div>
    </div>
  </div>
</div>

<!-- REPLY MODAL -->
<div class="modal fade" id="replyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-reply"></i> Reply to Message</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <!-- Canned responses -->
          <div style="margin-bottom:16px">
            <div style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px">Quick Responses</div>
            <div class="canned-chips">
              <span class="canned-chip" onclick="insertCanned('received')">✓ Received</span>
              <span class="canned-chip" onclick="insertCanned('review')">⟳ Under Review</span>
              <span class="canned-chip" onclick="insertCanned('info')">ℹ Need More Info</span>
              <span class="canned-chip" onclick="insertCanned('thanks')">★ Thank You</span>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">To</label>
            <input type="text" class="form-control" id="r-to-name" name="to_name" readonly>
          </div>
          <div class="form-group">
            <input type="email" class="form-control" id="r-to-email" name="to_email" readonly style="display:none">
          </div>
          <div class="form-group">
            <label class="form-label">Subject</label>
            <input type="text" class="form-control" id="r-subject" name="subject" required>
          </div>
          <div class="form-group">
            <label class="form-label">Message</label>
            <textarea class="form-control" id="r-message" name="message" rows="7" required style="resize:vertical"></textarea>
          </div>
          <input type="hidden" id="r-msg-id" name="message_id">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-modal-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="send_reply" class="btn-modal-gold"><i class="fas fa-paper-plane" style="margin-right:6px"></i>Send Reply</button>
        </div>
      </form>
    </div>
  </div>
</div>

<footer class="portal-footer">
  <div class="footer-copy">© 2025 Bold Footprint Initiatives. Admin Portal.</div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar
const sidebar=document.getElementById('sidebar'),overlay=document.getElementById('sidebarOverlay'),toggle=document.getElementById('mobileToggle');
const openSB=()=>{sidebar.classList.add('active');overlay.classList.add('active');document.body.style.overflow='hidden';};
const closeSB=()=>{sidebar.classList.remove('active');overlay.classList.remove('active');document.body.style.overflow='';};
toggle?.addEventListener('click',()=>sidebar.classList.contains('active')?closeSB():openSB());
overlay?.addEventListener('click',closeSB);
function updateClock(){const el=document.getElementById('headerTime');if(!el)return;const n=new Date();el.textContent=n.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'})+' · '+n.toLocaleDateString('en-GB',{day:'numeric',month:'short'});}
updateClock();setInterval(updateClock,30000);

// View message modal
const viewModal=new bootstrap.Modal(document.getElementById('viewMsgModal'));
const replyModal=new bootstrap.Modal(document.getElementById('replyModal'));

document.querySelectorAll('.view-msg-btn').forEach(btn=>{
  btn.addEventListener('click',function(){
    const d=this.dataset;
    document.getElementById('vm-name').textContent=d.name;
    document.getElementById('vm-email').textContent=d.email;
    document.getElementById('vm-subject').textContent=d.subject;
    document.getElementById('vm-cat').textContent=d.category.charAt(0).toUpperCase()+d.category.slice(1);
    document.getElementById('vm-date').textContent=d.date;
    document.getElementById('vm-message').textContent=d.message;
    const rb=document.getElementById('vm-read-btn');
    rb.href=`?action=mark_read&id=${d.id}&category=<?= urlencode($selected_category) ?>&page=<?= $page ?>`;
    rb.style.display=d.read==='0'?'inline-flex':'none';
    const reb=document.getElementById('vm-reply-btn');
    reb.onclick=()=>{viewModal.hide();openReply(d.id,d.name,d.email,d.subject);};
    viewModal.show();
  });
});

document.querySelectorAll('.reply-btn').forEach(btn=>{
  btn.addEventListener('click',function(){
    const d=this.dataset;
    openReply(d.id,d.name,d.email,d.subject);
  });
});

function openReply(id,name,email,subject){
  document.getElementById('r-to-name').value=name+' <'+email+'>';
  document.getElementById('r-to-email').value=email;
  document.getElementById('r-subject').value='RE: '+subject;
  document.getElementById('r-msg-id').value=id;
  document.getElementById('r-message').value=`Dear ${name.split(' ')[0]},\n\nThank you for reaching out to us.\n\n[Your message here]\n\nKind regards,\n<?= htmlspecialchars($admin_full_name) ?>\nBFI Scholarship Team`;
  replyModal.show();
}

// Canned responses
const canned={
  received:`Thank you for your message. We have received your enquiry and will respond within 2–3 business days.`,
  review:`Thank you for contacting us. Your message is currently under review by our team. We will get back to you shortly.`,
  info:`Thank you for reaching out. To assist you better, could you please provide some additional information? Specifically, [detail what is needed].`,
  thanks:`Thank you for your kind message. We truly appreciate your support and feedback regarding BFI's scholarship programmes.`
};
function insertCanned(key){const ta=document.getElementById('r-message');if(ta&&canned[key]){const name=document.getElementById('r-to-name').value.split('<')[0].trim().split(' ')[0];ta.value=`Dear ${name},\n\n${canned[key]}\n\nKind regards,\n<?= htmlspecialchars($admin_full_name) ?>\nBFI Scholarship Team`;ta.focus();}}
</script>
</body>
</html>