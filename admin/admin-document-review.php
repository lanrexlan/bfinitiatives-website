<?php
session_start();
error_log("Document Review accessed - Session: " . print_r($_SESSION, true));

$is_logged_in = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$is_logged_in) {
    $_SESSION['error'] = "Please log in to access the document review";
    header('Location: admin-login.php'); exit();
}

require_once 'includes/config.php';
require_once 'includes/db.php';

$filter_type   = $_GET['type']   ?? 'all';
$filter_status = $_GET['status'] ?? 'pending';
$search_term   = $_GET['search'] ?? '';
$documents     = [];
$error_message = $success_message = '';
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 10;
$total_documents = 0;
$status_counts   = [];

if (isset($_SESSION['success_message'])) { $success_message = $_SESSION['success_message']; unset($_SESSION['success_message']); }
if (isset($_SESSION['error_message']))   { $error_message   = $_SESSION['error_message'];   unset($_SESSION['error_message']); }

// Handle quick inline status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_status_update'])) {
    try {
        $db = new Database(); $conn = $db->getConnection();
        $stmt = $conn->prepare("UPDATE user_documents SET review_status=:s, feedback_date=NOW() WHERE id=:id");
        $stmt->execute([':s' => $_POST['new_status'], ':id' => (int)$_POST['doc_id']]);
        $_SESSION['success_message'] = "Document status updated.";
        header('Location: ' . $_SERVER['REQUEST_URI']); exit();
    } catch (Exception $e) { $error_message = "Update failed: " . $e->getMessage(); }
}

try {
    $db   = new Database();
    $conn = $db->getConnection();

    $where = []; $params = [];
    if ($filter_type   !== 'all') { $where[] = "d.document_type=:dt";  $params[':dt']  = $filter_type; }
    if ($filter_status !== 'all') { $where[] = "d.review_status=:rs";  $params[':rs']  = $filter_status; }
    if (!empty($search_term))     { $where[] = "(u.first_name ILIKE :s OR u.last_name ILIKE :s OR u.email ILIKE :s)"; $params[':s'] = "%$search_term%"; }
    $wc = $where ? " AND " . implode(" AND ", $where) : "";

    $cnt = $conn->prepare("SELECT COUNT(*) FROM user_documents d JOIN users u ON d.user_id=u.id WHERE 1=1 $wc");
    $cnt->execute($params); $total_documents = (int)$cnt->fetchColumn();
    $total_pages = max(1, ceil($total_documents / $per_page));
    $offset = ($page - 1) * $per_page;

    $q = "SELECT d.id,d.user_id,d.document_type,d.file_name,d.upload_date,d.review_status,d.feedback,d.version,
                 u.first_name,u.last_name,u.email,u.profile_picture
          FROM user_documents d JOIN users u ON d.user_id=u.id WHERE 1=1 $wc
          ORDER BY d.upload_date DESC LIMIT :lim OFFSET :off";
    $stmt = $conn->prepare($q);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset,   PDO::PARAM_INT);
    $stmt->execute();
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sc = $conn->query("SELECT review_status, COUNT(*) cnt FROM user_documents GROUP BY review_status");
    while ($r = $sc->fetch(PDO::FETCH_ASSOC)) $status_counts[$r['review_status']] = $r['cnt'];

} catch (Exception $e) { $error_message = "Error retrieving documents: " . $e->getMessage(); }

function docTypeLabel($t){ return ['cv'=>'CV / Résumé','statement'=>'Personal Statement','research'=>'Research Proposal','recommendation'=>'Recommendation','language'=>'Language Test','cold email'=>'Cold Email'][$t] ?? 'Document'; }
function docTypeIcon($t){ return ['cv'=>'fa-file-alt','statement'=>'fa-pen-fancy','research'=>'fa-flask','recommendation'=>'fa-envelope','language'=>'fa-language','cold email'=>'fa-at'][$t] ?? 'fa-file'; }
function statusBadge($s){ return ['pending'=>'badge-pending','in_review'=>'badge-review','needs_revision'=>'badge-rejected','approved'=>'badge-approved'][$s] ?? 'badge-secondary'; }
function statusLabel($s){ return ['pending'=>'Pending','in_review'=>'In Review','needs_revision'=>'Needs Revision','approved'=>'Approved'][$s] ?? ucfirst($s); }
function statusIcon($s){ return ['pending'=>'clock','in_review'=>'search','needs_revision'=>'times-circle','approved'=>'check-circle'][$s] ?? 'circle'; }
function fileIcon($f){ $e=strtolower(pathinfo($f,PATHINFO_EXTENSION)); return ['pdf'=>'fa-file-pdf','doc'=>'fa-file-word','docx'=>'fa-file-word','jpg'=>'fa-file-image','jpeg'=>'fa-file-image','png'=>'fa-file-image'][$e]??'fa-file'; }

$admin_full_name  = $_SESSION['admin_name'] ?? 'Admin';
$admin_first_name = explode(' ', $admin_full_name)[0];
$admin_role       = ucfirst($_SESSION['admin_role'] ?? 'Administrator');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Document Review · Admin Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<style>
:root{
  --midnight:#080E1C;--navy:#0D1829;--navy-mid:#132038;--navy-light:#1C2F52;
  --gold:#C8A058;--gold-bright:#E0B96C;--gold-pale:#F0D9A8;
  --cream:#FAF6EF;--cream-dark:#F2EAD8;--white:#FFFFFF;
  --text-primary:#0D1829;--text-secondary:#4A526A;--text-muted:#8A92A8;
  --border-light:#E8E4DA;
  --admin-crimson:#9F1239;--admin-crimson-light:#BE123C;--admin-crimson-pale:rgba(159,18,57,.10);
  --success:#059669;--success-pale:rgba(5,150,105,.10);
  --warning:#D97706;--warning-pale:rgba(217,119,6,.10);
  --danger:#DC2626;--danger-pale:rgba(220,38,38,.10);
  --info:#0284C7;--info-pale:rgba(2,132,199,.10);
  --font-display:'Cormorant Garamond',Georgia,serif;
  --font-body:'Outfit',-apple-system,sans-serif;
  --ease:cubic-bezier(.25,.46,.45,.94);--transition:.3s var(--ease);
  --shadow-sm:0 2px 8px rgba(8,14,28,.06);--shadow-md:0 8px 32px rgba(8,14,28,.10);
  --sidebar-width:268px;--header-height:64px;
  --r-sm:8px;--r-md:14px;--r-lg:20px;--r-xl:28px;
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
body{font-family:var(--font-body);background:#F0F2F7;color:var(--text-primary);line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden;}
a{text-decoration:none;color:inherit;}

/* SIDEBAR */
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
.nav-link{display:flex;align-items:center;gap:11px;padding:10px 13px;border-radius:var(--r-sm);font-size:13px;font-weight:400;color:rgba(255,255,255,.55);transition:var(--transition);position:relative;}
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

/* HEADER */
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

/* MAIN */
.main{margin-left:var(--sidebar-width);margin-top:var(--header-height);padding:26px;min-height:calc(100vh - var(--header-height));}

/* PAGE HEADER */
.page-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;flex-wrap:wrap;gap:16px;}
.page-eyebrow{font-size:9.5px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:var(--gold);margin-bottom:4px;}
.page-title{font-family:var(--font-display);font-size:clamp(24px,2.5vw,32px);font-weight:500;color:var(--navy);line-height:1.2;}
.page-title em{font-style:italic;color:var(--gold);}
.page-sub{font-size:13px;color:var(--text-muted);margin-top:4px;}
.hdr-actions{display:flex;gap:10px;align-items:center;}

/* BUTTONS */
.btn-gold{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:var(--gold);color:var(--midnight);font-family:var(--font-body);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
.btn-gold:hover{background:var(--gold-bright);transform:translateY(-1px);}
.btn-navy{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:var(--navy);color:var(--white);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
.btn-navy:hover{background:var(--navy-light);transform:translateY(-1px);}
.btn-ghost{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:var(--white);color:var(--text-secondary);font-size:13px;font-weight:400;border:1px solid var(--border-light);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
.btn-ghost:hover{background:var(--cream);border-color:var(--gold);color:var(--navy);}
.btn-sm{padding:6px 14px;font-size:12px;border-radius:var(--r-sm);}

/* STAT CARDS */
.stat-row{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:24px;}
.stat-card{background:var(--white);border-radius:var(--r-lg);padding:18px 20px;border:1px solid var(--border-light);cursor:pointer;transition:var(--transition);position:relative;overflow:hidden;}
.stat-card:hover,.stat-card.active{transform:translateY(-3px);box-shadow:0 8px 24px rgba(8,14,28,.09);}
.stat-card.active{border-color:var(--gold);background:linear-gradient(135deg,var(--cream) 0%,var(--white) 100%);}
.stat-card-icon{position:absolute;top:16px;right:16px;width:36px;height:36px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:14px;}
.si-warn{background:var(--warning-pale);color:var(--warning);}
.si-info{background:var(--info-pale);color:var(--info);}
.si-danger{background:var(--danger-pale);color:var(--danger);}
.si-green{background:var(--success-pale);color:var(--success);}
.si-navy{background:rgba(13,24,41,.08);color:var(--navy);}
.sc-label{font-size:11px;color:var(--text-muted);margin-bottom:6px;font-weight:500;text-transform:uppercase;letter-spacing:.5px;}
.sc-value{font-family:var(--font-display);font-size:30px;font-weight:500;color:var(--navy);line-height:1;}
.sc-sub{font-size:11.5px;color:var(--text-muted);margin-top:4px;}

/* FILTER PANEL */
.filter-panel{background:var(--white);border:1px solid var(--border-light);border-radius:var(--r-lg);padding:20px 24px;margin-bottom:20px;}
.filter-row{display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;}
.filter-group{display:flex;flex-direction:column;gap:5px;flex:1;min-width:160px;}
.filter-label{font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;}
.filter-input,.filter-select{padding:9px 13px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13px;color:var(--text-primary);background:var(--white);transition:var(--transition);outline:none;}
.filter-input:focus,.filter-select:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,160,88,.12);}
.search-wrap{position:relative;flex:2;}
.search-wrap .filter-input{padding-left:36px;}
.search-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:12px;}

/* TABLE CARD */
.table-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);overflow:hidden;margin-bottom:24px;}
.tc-header{padding:20px 24px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border-light);flex-wrap:wrap;gap:12px;}
.tc-title{font-family:var(--font-display);font-size:20px;font-weight:500;color:var(--navy);}
.tc-title em{font-style:italic;color:var(--gold);}
.tc-sub{font-size:12px;color:var(--text-muted);margin-top:2px;}
.tc-actions{display:flex;gap:8px;align-items:center;}
.tbl-btn{width:34px;height:34px;border-radius:var(--r-sm);background:var(--cream);border:1px solid var(--border-light);cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:13px;transition:var(--transition);}
.tbl-btn:hover{background:var(--navy);color:var(--gold-bright);border-color:transparent;}
.data-table{width:100%;border-collapse:separate;border-spacing:0;}
.data-table th{background:#F8F9FB;color:var(--text-muted);font-weight:600;font-size:10.5px;letter-spacing:.8px;text-transform:uppercase;padding:12px 16px;text-align:left;border-bottom:1px solid var(--border-light);}
.data-table td{padding:14px 16px;vertical-align:middle;border-bottom:1px solid var(--border-light);font-size:13.5px;color:var(--text-primary);}
.data-table tbody tr:last-child td{border-bottom:none;}
.data-table tbody tr{transition:background var(--transition);}
.data-table tbody tr:hover{background:#FAFBFD;}

/* SCHOLAR CELL */
.scholar-cell{display:flex;align-items:center;gap:10px;}
.sc-av{width:36px;height:36px;border-radius:var(--r-sm);background:linear-gradient(135deg,var(--navy),var(--navy-light));display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:15px;font-weight:500;color:var(--gold-bright);flex-shrink:0;overflow:hidden;}
.sc-av img{width:100%;height:100%;object-fit:cover;}
.sc-name{font-weight:500;font-size:13.5px;color:var(--text-primary);}
.sc-email{font-size:11.5px;color:var(--text-muted);}

/* DOC CELL */
.doc-cell{display:flex;align-items:center;gap:10px;}
.doc-icon-wrap{width:36px;height:36px;border-radius:var(--r-sm);background:var(--cream);display:flex;align-items:center;justify-content:center;color:var(--gold);font-size:14px;flex-shrink:0;}
.doc-name{font-weight:500;font-size:13px;}
.doc-meta{font-size:11px;color:var(--text-muted);}
.doc-ext{display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:4px;font-size:10.5px;font-weight:500;margin-top:3px;}
.ext-pdf{background:rgba(220,38,38,.08);color:#DC2626;}
.ext-word{background:rgba(2,132,199,.08);color:#0284C7;}
.ext-img{background:var(--success-pale);color:var(--success);}

/* BADGES */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:500;}
.badge-pending{background:var(--warning-pale);color:var(--warning);}
.badge-approved{background:var(--success-pale);color:var(--success);}
.badge-rejected{background:var(--danger-pale);color:var(--danger);}
.badge-review{background:rgba(99,102,241,.1);color:#6366F1;}
.badge-secondary{background:var(--cream);color:var(--text-muted);}

/* ACTION BUTTONS */
.act-btns{display:flex;gap:6px;justify-content:flex-end;}
.act-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 13px;font-family:var(--font-body);font-size:12px;font-weight:500;border-radius:var(--r-sm);border:none;cursor:pointer;transition:var(--transition);}
.act-btn:hover{transform:translateY(-1px);}
.ab-primary{background:var(--navy);color:var(--white);}
.ab-primary:hover{background:var(--navy-light);}
.ab-ghost{background:var(--cream);color:var(--text-secondary);border:1px solid var(--border-light);}
.ab-ghost:hover{background:var(--cream-dark);}
.ab-gold{background:rgba(200,160,88,.12);color:var(--gold);}
.ab-gold:hover{background:var(--gold);color:var(--midnight);}

/* QUICK STATUS FORM */
.quick-status-form select{padding:4px 8px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-size:11.5px;font-family:var(--font-body);color:var(--text-primary);background:var(--white);cursor:pointer;}

/* PAGINATION */
.tc-footer{padding:14px 24px;display:flex;justify-content:space-between;align-items:center;border-top:1px solid var(--border-light);background:#FAFBFC;flex-wrap:wrap;gap:10px;}
.tf-text{font-size:12.5px;color:var(--text-muted);}
.pagination{display:flex;gap:4px;list-style:none;}
.page-item .page-link{display:flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border-radius:var(--r-sm);border:1px solid var(--border-light);background:var(--white);color:var(--text-secondary);font-size:12.5px;font-weight:500;transition:var(--transition);cursor:pointer;}
.page-item .page-link:hover{background:var(--cream);border-color:var(--gold);color:var(--navy);}
.page-item.active .page-link{background:var(--navy);border-color:var(--navy);color:var(--white);}
.page-item.disabled .page-link{opacity:.4;pointer-events:none;}

/* EMPTY STATE */
.empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 24px;text-align:center;}
.es-icon{font-size:40px;color:var(--border-light);margin-bottom:16px;}
.es-title{font-family:var(--font-display);font-size:22px;font-weight:500;color:var(--navy);margin-bottom:6px;}
.es-sub{font-size:13px;color:var(--text-muted);max-width:400px;margin:0 auto 20px;}

/* PORTAL FOOTER */
.portal-footer{margin-left:var(--sidebar-width);background:var(--white);padding:14px 26px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;}
.footer-copy{font-size:11.5px;color:var(--text-muted);}
.footer-links{display:flex;gap:18px;}
.footer-links a{font-size:11.5px;color:var(--text-muted);transition:color var(--transition);}
.footer-links a:hover{color:var(--gold);}

/* ALERTS */
.alert{padding:12px 18px;border-radius:var(--r-md);font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:10px;}
.alert-success{background:var(--success-pale);color:var(--success);border:1px solid rgba(5,150,105,.2);}
.alert-danger{background:var(--danger-pale);color:var(--danger);border:1px solid rgba(220,38,38,.2);}

/* DATE CHIP */
.date-chip{font-size:12.5px;color:var(--text-secondary);}
.time-chip{font-size:11px;color:var(--text-muted);}

@media(max-width:991px){
  .sidebar{transform:translateX(-100%);}
  .sidebar.active{transform:translateX(0);}
  .header{left:0;}
  .main,.portal-footer{margin-left:0;}
  .mobile-toggle{display:flex;}
  .header-breadcrumb{display:none;}
  .stat-row{grid-template-columns:repeat(2,1fr);}
}
@media(max-width:600px){.stat-row{grid-template-columns:1fr 1fr;}.filter-row{flex-direction:column;}}
</style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR -->
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
    <div class="nav-item"><a href="applications.php" class="nav-link"><i class="fas fa-tasks"></i> Applications<?php if(!empty($status_counts['pending'])): ?><span class="nav-badge"><?= $status_counts['pending'] ?></span><?php endif; ?></a></div>
    <div class="nav-section-label">Management</div>
    <div class="nav-item"><a href="admin-document-review.php" class="nav-link active"><i class="fas fa-file-alt"></i> Review Documents<?php if(!empty($status_counts['pending'])): ?><span class="nav-badge"><?= $status_counts['pending'] ?></span><?php endif; ?></a></div>
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

<!-- HEADER -->
<header class="header">
  <div class="header-left">
    <button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
    <div class="header-breadcrumb">
      <span>Admin</span><span class="sep">/</span>
      <strong>Document Review</strong>
    </div>
  </div>
  <div class="header-right">
    <div class="header-time" id="headerTime"></div>
    <button class="header-icon-btn" onclick="window.location.href='admin-dashboard.php'" title="Dashboard"><i class="fas fa-home"></i></button>
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
    <div class="page-eyebrow"><i class="fas fa-file-search" style="margin-right:5px"></i> Document Management</div>
    <h1 class="page-title">Review <em>Documents</em></h1>
    <p class="page-sub">Assess and provide feedback on scholar submissions</p>
  </div>
  <div class="hdr-actions">
    <a href="admin-document-review.php" class="btn-ghost"><i class="fas fa-sync-alt"></i> Refresh</a>
    <a href="admin-dashboard.php" class="btn-navy"><i class="fas fa-arrow-left"></i> Dashboard</a>
  </div>
</div>

<!-- STATUS STAT CARDS -->
<div class="stat-row">
  <?php
  $tabs=[
    ['status'=>'pending',        'label'=>'Pending',      'icon'=>'clock',        'cls'=>'si-warn'],
    ['status'=>'in_review',      'label'=>'In Review',    'icon'=>'search',       'cls'=>'si-info'],
    ['status'=>'needs_revision', 'label'=>'Needs Revision','icon'=>'edit',        'cls'=>'si-danger'],
    ['status'=>'approved',       'label'=>'Approved',     'icon'=>'check-circle', 'cls'=>'si-green'],
    ['status'=>'all',            'label'=>'All Docs',     'icon'=>'layer-group',  'cls'=>'si-navy'],
  ];
  foreach($tabs as $t):
    $cnt = $t['status']==='all' ? array_sum($status_counts) : ($status_counts[$t['status']] ?? 0);
    $isActive = $filter_status===$t['status'];
    $url="?status={$t['status']}".($filter_type!=='all'?"&type=$filter_type":"").(!empty($search_term)?"&search=".urlencode($search_term):"");
  ?>
  <a href="<?= $url ?>" style="text-decoration:none">
    <div class="stat-card <?= $isActive?'active':'' ?>">
      <div class="stat-card-icon <?= $t['cls'] ?>"><i class="fas fa-<?= $t['icon'] ?>"></i></div>
      <div class="sc-label"><?= $t['label'] ?></div>
      <div class="sc-value"><?= $cnt ?></div>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<!-- FILTER PANEL -->
<div class="filter-panel">
  <form method="GET" class="filter-row">
    <?php if($filter_status!=='all'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>"><?php endif; ?>
    <div class="filter-group" style="max-width:200px">
      <label class="filter-label">Document Type</label>
      <select class="filter-select" name="type">
        <option value="all" <?= $filter_type==='all'?'selected':'' ?>>All Types</option>
        <?php foreach(['cv'=>'CV / Résumé','statement'=>'Personal Statement','research'=>'Research Proposal','recommendation'=>'Recommendation','language'=>'Language Test','cold email'=>'Cold Email'] as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $filter_type===$k?'selected':'' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filter-group search-wrap">
      <label class="filter-label">Search Scholars</label>
      <i class="fas fa-search search-icon"></i>
      <input type="text" class="filter-input" name="search" placeholder="Name or email…" value="<?= htmlspecialchars($search_term) ?>">
    </div>
    <button type="submit" class="btn-gold" style="margin-top:auto"><i class="fas fa-filter"></i> Apply</button>
    <?php if(!empty($search_term)||$filter_type!=='all'): ?>
    <a href="?status=<?= $filter_status ?>" class="btn-ghost" style="margin-top:auto"><i class="fas fa-times"></i> Clear</a>
    <?php endif; ?>
  </form>
</div>

<!-- TABLE -->
<div class="table-card">
  <div class="tc-header">
    <div>
      <div class="tc-title"><?= ucfirst(str_replace('_',' ',$filter_status)) ?> <em>Documents</em></div>
      <div class="tc-sub">
        <?= !empty($documents) ? "Showing ".count($documents)." of $total_documents document".($total_documents!=1?'s':'') : "No documents match your criteria" ?>
      </div>
    </div>
    <div class="tc-actions">
      <button class="tbl-btn" title="Refresh" onclick="location.reload()"><i class="fas fa-sync-alt"></i></button>
      <button class="tbl-btn" title="Export CSV" id="exportBtn"><i class="fas fa-download"></i></button>
    </div>
  </div>

  <?php if(!empty($documents)): ?>
  <div style="overflow-x:auto">
    <table class="data-table">
      <thead>
        <tr>
          <th>Scholar</th>
          <th>Document</th>
          <th>Status</th>
          <th>Submitted</th>
          <th style="text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($documents as $doc):
          $ext = strtolower(pathinfo($doc['file_name'],PATHINFO_EXTENSION));
          $extCls = $ext==='pdf'?'ext-pdf':($ext==='doc'||$ext==='docx'?'ext-word':'ext-img');
        ?>
        <tr>
          <td>
            <div class="scholar-cell">
              <div class="sc-av">
                <?php if(!empty($doc['profile_picture'])): ?>
                <img src="/scholar-portal/uploads/profile_pictures/<?= htmlspecialchars($doc['profile_picture']) ?>" alt="">
                <?php else: ?>
                <?= strtoupper(substr($doc['first_name'],0,1)) ?>
                <?php endif; ?>
              </div>
              <div>
                <div class="sc-name"><?= htmlspecialchars($doc['first_name'].' '.$doc['last_name']) ?></div>
                <div class="sc-email"><?= htmlspecialchars($doc['email']) ?></div>
              </div>
            </div>
          </td>
          <td>
            <div class="doc-cell">
              <div class="doc-icon-wrap"><i class="fas <?= docTypeIcon($doc['document_type']) ?>"></i></div>
              <div>
                <div class="doc-name"><?= docTypeLabel($doc['document_type']) ?></div>
                <div class="doc-meta">Version <?= $doc['version']?:'1' ?></div>
                <span class="doc-ext <?= $extCls ?>"><i class="fas <?= fileIcon($doc['file_name']) ?>"></i> <?= strtoupper($ext) ?></span>
              </div>
            </div>
          </td>
          <td>
            <!-- Quick inline status update -->
            <form method="POST" class="quick-status-form" style="display:flex;align-items:center;gap:6px">
              <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
              <input type="hidden" name="quick_status_update" value="1">
              <span class="badge <?= statusBadge($doc['review_status']) ?>">
                <i class="fas fa-<?= statusIcon($doc['review_status']) ?>"></i>
                <?= statusLabel($doc['review_status']) ?>
              </span>
              <select name="new_status" onchange="this.form.submit()" title="Quick status update">
                <option value="">Change…</option>
                <option value="pending">Pending</option>
                <option value="in_review">In Review</option>
                <option value="needs_revision">Needs Revision</option>
                <option value="approved">Approved</option>
              </select>
            </form>
          </td>
          <td>
            <div class="date-chip"><?= date('M d, Y',strtotime($doc['upload_date'])) ?></div>
            <div class="time-chip"><?= date('h:i A',strtotime($doc['upload_date'])) ?></div>
          </td>
          <td>
            <div class="act-btns">
              <a href="admin-review-document.php?id=<?= $doc['id'] ?>" class="act-btn ab-primary"><i class="fas fa-edit"></i> Review</a>
              <a href="download-document.php?id=<?= $doc['id'] ?>" class="act-btn ab-ghost"><i class="fas fa-download"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if($total_pages>1): ?>
  <div class="tc-footer">
    <div class="tf-text">Showing <?= ($offset+1) ?>–<?= min($offset+$per_page,$total_documents) ?> of <?= $total_documents ?></div>
    <ul class="pagination">
      <li class="page-item <?= $page<=1?'disabled':'' ?>">
        <a class="page-link" href="?page=<?= $page-1 ?>&status=<?= $filter_status ?>&type=<?= $filter_type ?>&search=<?= urlencode($search_term) ?>"><i class="fas fa-chevron-left"></i></a>
      </li>
      <?php
      $sp=max(1,$page-2); $ep=min($total_pages,$sp+4); $sp=max(1,$ep-4);
      for($i=$sp;$i<=$ep;$i++):
      ?>
      <li class="page-item <?= $i===$page?'active':'' ?>">
        <a class="page-link" href="?page=<?= $i ?>&status=<?= $filter_status ?>&type=<?= $filter_type ?>&search=<?= urlencode($search_term) ?>"><?= $i ?></a>
      </li>
      <?php endfor; ?>
      <li class="page-item <?= $page>=$total_pages?'disabled':'' ?>">
        <a class="page-link" href="?page=<?= $page+1 ?>&status=<?= $filter_status ?>&type=<?= $filter_type ?>&search=<?= urlencode($search_term) ?>"><i class="fas fa-chevron-right"></i></a>
      </li>
    </ul>
  </div>
  <?php endif; ?>

  <?php else: ?>
  <div class="empty-state">
    <div class="es-icon"><i class="fas fa-folder-open"></i></div>
    <div class="es-title">No Documents Found</div>
    <p class="es-sub">No documents match your current filters. Try adjusting your search criteria.</p>
    <a href="admin-document-review.php" class="btn-gold"><i class="fas fa-sync-alt"></i> Reset Filters</a>
  </div>
  <?php endif; ?>
</div>

</main>

<footer class="portal-footer">
  <div class="footer-copy">© 2025 Bold Footprint Initiatives. Admin Portal.</div>
  <div class="footer-links">
    <a href="admin-dashboard.php">Dashboard</a>
    <a href="reports.php">Reports</a>
    <a href="settings.php">Settings</a>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Sidebar
const sidebar=document.getElementById('sidebar'),overlay=document.getElementById('sidebarOverlay'),toggle=document.getElementById('mobileToggle');
const openSB=()=>{sidebar.classList.add('active');overlay.classList.add('active');document.body.style.overflow='hidden';};
const closeSB=()=>{sidebar.classList.remove('active');overlay.classList.remove('active');document.body.style.overflow='';};
toggle?.addEventListener('click',()=>sidebar.classList.contains('active')?closeSB():openSB());
overlay?.addEventListener('click',closeSB);

// Clock
function updateClock(){const el=document.getElementById('headerTime');if(!el)return;const n=new Date();el.textContent=n.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'})+' · '+n.toLocaleDateString('en-GB',{day:'numeric',month:'short'});}
updateClock();setInterval(updateClock,30000);

// Export
document.getElementById('exportBtn')?.addEventListener('click',()=>{
  Swal.fire({title:'Export Documents',html:`<p style="font-size:13px;color:#64748b;margin-bottom:20px">Choose your export format:</p>
    <div style="display:grid;gap:10px">
      <button onclick="doExport('csv')" class="swal-export-btn" style="padding:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;font-family:Outfit,sans-serif;font-size:13px;display:flex;align-items:center;gap:10px">
        <i class="fas fa-file-csv" style="color:#059669;font-size:18px"></i><div style="text-align:left"><strong>CSV</strong><br><small style="color:#94a3b8">Spreadsheet compatible</small></div>
      </button>
    </div>`,showConfirmButton:false,showCancelButton:true,cancelButtonText:'Close'});
});
function doExport(fmt){Swal.close();window.location.href=`export_documents.php?format=${fmt}&status=<?= urlencode($filter_status) ?>&type=<?= urlencode($filter_type) ?>`;}
</script>
</body>
</html>