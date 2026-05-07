<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    $_SESSION['error'] = "Please log in";
    header('Location: admin-login.php'); exit();
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid document specified";
    header('Location: admin-document-review.php'); exit();
}

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/mailer.php';

$document_id    = (int)$_GET['id'];
$success_message = $error_message = '';
$document = $versions = $corrections = [];
$additional_data = [];
$stats = ['pending_count' => 0];

if (isset($_SESSION['success_message'])) { $success_message = $_SESSION['success_message']; unset($_SESSION['success_message']); }
if (isset($_SESSION['error_message']))   { $error_message   = $_SESSION['error_message'];   unset($_SESSION['error_message']); }

try {
    $db = new Database(); $conn = $db->getConnection();

    $stmt = $conn->prepare("
        SELECT d.*,u.first_name,u.last_name,u.email,u.profile_picture
        FROM user_documents d JOIN users u ON d.user_id=u.id WHERE d.id=:id");
    $stmt->execute([':id'=>$document_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$document) { $_SESSION['error_message']="Document not found"; header('Location: admin-document-review.php'); exit(); }

    $ver=$conn->prepare("SELECT id,version,upload_date,review_status,feedback,feedback_date,file_name FROM user_documents WHERE user_id=:uid AND document_type=:dt ORDER BY version DESC,upload_date DESC");
    $ver->execute([':uid'=>$document['user_id'],':dt'=>$document['document_type']]);
    $versions=$ver->fetchAll(PDO::FETCH_ASSOC);

    try {
        $cor=$conn->prepare("SELECT c.*,a.first_name admin_fn,a.last_name admin_ln FROM document_corrections c JOIN admins a ON c.admin_id=a.id WHERE c.document_id=:id ORDER BY c.created_at DESC");
        $cor->execute([':id'=>$document_id]);
        $corrections=$cor->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e){ $corrections=[]; }

    $pc=$conn->query("SELECT COUNT(*) FROM user_documents WHERE review_status='pending'");
    $stats['pending_count']=$pc->fetchColumn()??0;

    // Submit review
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['submit_review'])) {
        $new_status = $_POST['review_status'] ?? '';
        $feedback   = $_POST['feedback'] ?? '';
        if (empty($new_status)) throw new Exception("Please select a review status");

        $upd=$conn->prepare("UPDATE user_documents SET review_status=:s,feedback=:f,feedback_date=NOW(),admin_id=:aid WHERE id=:id");
        $upd->execute([':s'=>$new_status,':f'=>$feedback,':aid'=>$_SESSION['admin_id']??1,':id'=>$document_id]);

        $notif=$conn->prepare("INSERT INTO notifications(user_id,type,message,link,created_at) VALUES(:uid,'document_feedback',:msg,:lnk,NOW())");
        $notif->execute([':uid'=>$document['user_id'],':msg'=>"Your ".ucfirst($document['document_type'])." has been reviewed",':lnk'=>"documents.php?type=".$document['document_type']]);

        sendDocumentFeedbackEmail($document['email'],$document['first_name'],getDocTypeLabel($document['document_type']),$new_status,$feedback);
        $success_message="Review saved and scholar notified!";
        $stmt->execute([':id'=>$document_id]); $document=$stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Upload correction
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['upload_corrected'])) {
        if (!isset($_FILES['corrected_document'])||$_FILES['corrected_document']['error']!==UPLOAD_ERR_OK)
            throw new RuntimeException("No file uploaded or upload error.");
        $file=$_FILES['corrected_document'];
        $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
        if(!in_array($ext,['pdf','doc','docx'],true)) throw new RuntimeException("Unsupported file type.");
        $docRoot=rtrim($_SERVER['DOCUMENT_ROOT']??'','/\\');
        $upDir=$docRoot.'/scholar-portal/uploads/documents/';
        if(!is_dir($upDir)) mkdir($upDir,0775,true);
        $newName='admin_correction_'.time().'_'.preg_replace('/[^a-z0-9_-]+/i','',$document['document_type']).'.'.$ext;
        if(!move_uploaded_file($file['tmp_name'],$upDir.$newName)) throw new RuntimeException("Failed to save file.");
        @chmod($upDir.$newName,0644);

        // Ensure table
        $conn->exec("CREATE TABLE IF NOT EXISTS document_corrections(id SERIAL PRIMARY KEY,document_id INTEGER,corrected_file VARCHAR(255),notes TEXT,admin_id INTEGER,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $ins=$conn->prepare("INSERT INTO document_corrections(document_id,corrected_file,notes,admin_id,created_at) VALUES(:di,:cf,:n,:ai,NOW())");
        $ins->execute([':di'=>$document_id,':cf'=>$newName,':n'=>$_POST['correction_notes']??null,':ai'=>$_SESSION['admin_id']??1]);
        $notif=$conn->prepare("INSERT INTO notifications(user_id,type,message,link,created_at) VALUES(:uid,'document_correction',:msg,:lnk,NOW())");
        $notif->execute([':uid'=>$document['user_id'],':msg'=>"A corrected ".ucfirst($document['document_type'])." is available",':lnk'=>"documents.php?type=".$document['document_type']]);
        $success_message="Corrected document uploaded!";
        $cor->execute([':id'=>$document_id]); $corrections=$cor->fetchAll(PDO::FETCH_ASSOC);
    }

    if (!empty($document['additional_data'])) $additional_data=is_string($document['additional_data'])?json_decode($document['additional_data'],true)??[]:$document['additional_data'];

} catch(Exception $e){ error_log("Review error: ".$e->getMessage()); $error_message="Error: ".$e->getMessage(); }

function getDocTypeLabel($t){return['cv'=>'CV/Résumé','statement'=>'Personal Statement','research'=>'Research Proposal','recommendation'=>'Recommendation Letter','language'=>'Language Test','cold email'=>'Cold Email'][$t]??'Document';}
function getDocTypeIcon($t){return['cv'=>'fa-file-alt','statement'=>'fa-pen-fancy','research'=>'fa-flask','recommendation'=>'fa-envelope','language'=>'fa-language','cold email'=>'fa-at'][$t]??'fa-file';}
function statusBadge($s){return['pending'=>'badge-pending','in_review'=>'badge-review','needs_revision'=>'badge-rejected','approved'=>'badge-approved'][$s]??'badge-secondary';}
function statusLabel($s){return['pending'=>'Pending','in_review'=>'In Review','needs_revision'=>'Needs Revision','approved'=>'Approved'][$s]??ucfirst($s);}
function statusIcon($s){return['pending'=>'clock','in_review'=>'search','needs_revision'=>'times-circle','approved'=>'check-circle'][$s]??'circle';}

$admin_full_name  = $_SESSION['admin_name'] ?? 'Admin';
$admin_first_name = explode(' ', $admin_full_name)[0];
$admin_role       = ucfirst($_SESSION['admin_role'] ?? 'Administrator');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Review Document · Admin Portal</title>
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

/* DOC HERO */
.doc-hero{background:var(--navy);border-radius:var(--r-xl);padding:28px 32px;margin-bottom:24px;position:relative;overflow:hidden;}
.doc-hero::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,.025) 1px,transparent 1px);background-size:28px 28px;}
.doc-hero::after{content:'';position:absolute;top:-60px;right:-60px;width:300px;height:300px;background:radial-gradient(circle,rgba(200,160,88,.08),transparent 65%);}
.doc-hero-inner{position:relative;z-index:1;display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap;}
.doc-hero-icon{width:72px;height:72px;border-radius:var(--r-md);background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;font-size:28px;color:var(--gold-bright);flex-shrink:0;border:1px solid rgba(255,255,255,.1);}
.doc-hero-info{flex:1;}
.doc-hero-type{font-size:10px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:rgba(200,160,88,.65);margin-bottom:5px;}
.doc-hero-title{font-family:var(--font-display);font-size:26px;font-weight:500;color:var(--white);margin-bottom:8px;}
.doc-hero-meta{display:flex;flex-wrap:wrap;gap:16px;margin-top:10px;}
.doc-hero-meta-item{display:flex;align-items:center;gap:6px;font-size:12.5px;color:rgba(255,255,255,.55);}
.doc-hero-meta-item i{color:rgba(255,255,255,.35);font-size:11px;}
.doc-status-pill{display:inline-flex;align-items:center;gap:6px;padding:5px 14px;border-radius:20px;background:rgba(255,255,255,.12);font-size:12px;font-weight:500;color:var(--white);}
.doc-hero-actions{display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap;}
.btn-hero{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;font-size:12.5px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);font-family:var(--font-body);}
.btn-hero-gold{background:var(--gold);color:var(--midnight);}
.btn-hero-gold:hover{background:var(--gold-bright);}
.btn-hero-ghost{background:rgba(255,255,255,.08);color:rgba(255,255,255,.8);border:1px solid rgba(255,255,255,.12);}
.btn-hero-ghost:hover{background:rgba(255,255,255,.14);}

/* SCHOLAR STRIP */
.scholar-strip{background:var(--white);border:1px solid var(--border-light);border-radius:var(--r-lg);padding:18px 22px;margin-bottom:20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;}
.scholar-av{width:52px;height:52px;border-radius:var(--r-md);background:linear-gradient(135deg,var(--navy),var(--navy-light));display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:22px;color:var(--gold-bright);overflow:hidden;flex-shrink:0;}
.scholar-av img{width:100%;height:100%;object-fit:cover;}
.scholar-name{font-size:16px;font-weight:500;color:var(--navy);}
.scholar-email{font-size:12.5px;color:var(--text-muted);display:flex;align-items:center;gap:5px;margin-top:2px;}

/* LAYOUT */
.review-grid{display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;}

/* PREVIEW CARD */
.preview-card{background:var(--white);border:1px solid var(--border-light);border-radius:var(--r-lg);overflow:hidden;}
.pc-header{padding:16px 20px;border-bottom:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;background:#F8F9FB;}
.pc-title{font-size:13.5px;font-weight:600;color:var(--navy);}
.doc-frame{width:100%;height:680px;border:none;display:block;}
.preview-fallback{height:400px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:32px;background:var(--cream);}
.pf-icon{font-size:48px;color:var(--border-light);margin-bottom:16px;}
.pf-title{font-family:var(--font-display);font-size:20px;color:var(--navy);margin-bottom:6px;}
.pf-sub{font-size:13px;color:var(--text-muted);margin-bottom:20px;}

/* RIGHT PANEL */
.right-panel{display:flex;flex-direction:column;gap:16px;}

/* CARD */
.panel-card{background:var(--white);border:1px solid var(--border-light);border-radius:var(--r-lg);overflow:hidden;}
.panel-card-header{padding:15px 18px;border-bottom:1px solid var(--border-light);display:flex;align-items:center;gap:8px;background:#F8F9FB;}
.panel-card-title{font-size:13.5px;font-weight:600;color:var(--navy);}
.panel-card-title i{color:var(--gold);}
.panel-card-body{padding:18px;}

/* FORM */
.form-group{margin-bottom:16px;}
.form-label{display:block;font-size:11.5px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;}
.form-control{width:100%;padding:10px 13px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13px;color:var(--text-primary);background:var(--white);transition:var(--transition);outline:none;resize:vertical;}
.form-control:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,160,88,.12);}
select.form-control{cursor:pointer;}
.btn-submit{display:flex;align-items:center;justify-content:center;gap:7px;width:100%;padding:12px;background:var(--navy);color:var(--white);font-family:var(--font-body);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
.btn-submit:hover{background:var(--navy-light);}
.btn-submit.btn-gold-submit{background:var(--gold);color:var(--midnight);}
.btn-submit.btn-gold-submit:hover{background:var(--gold-bright);}

/* REVIEW CHECKLIST */
.checklist{display:flex;flex-direction:column;gap:8px;}
.check-item{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:var(--r-sm);background:var(--cream);cursor:pointer;transition:var(--transition);}
.check-item:hover{background:var(--cream-dark);}
.check-item input[type=checkbox]{accent-color:var(--navy);width:14px;height:14px;}
.check-item label{font-size:12.5px;color:var(--text-secondary);cursor:pointer;flex:1;}

/* FEEDBACK TEMPLATES */
.template-chips{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;}
.tmpl-chip{padding:4px 10px;background:var(--cream);border:1px solid var(--border-light);border-radius:20px;font-size:11.5px;color:var(--text-secondary);cursor:pointer;transition:var(--transition);}
.tmpl-chip:hover{background:var(--gold);color:var(--midnight);border-color:var(--gold);}

/* TIMELINE */
.timeline{position:relative;padding-left:20px;}
.timeline::before{content:'';position:absolute;left:7px;top:0;bottom:0;width:1.5px;background:var(--border-light);}
.tl-item{position:relative;padding-bottom:16px;padding-left:18px;}
.tl-item:last-child{padding-bottom:0;}
.tl-dot{position:absolute;left:-13px;top:5px;width:14px;height:14px;border-radius:50%;background:var(--white);border:2px solid var(--border-light);}
.tl-item.current .tl-dot{background:var(--gold);border-color:var(--gold);}
.tl-content{background:var(--cream);border-radius:var(--r-sm);padding:10px 13px;border:1px solid var(--border-light);}
.tl-title{font-size:12.5px;font-weight:600;color:var(--navy);margin-bottom:3px;display:flex;justify-content:space-between;align-items:center;}
.tl-date{font-size:11px;color:var(--text-muted);}

/* CORRECTION ITEM */
.correction-item{background:var(--cream);border-radius:var(--r-sm);padding:12px;border-left:3px solid var(--gold);margin-bottom:10px;}
.ci-header{display:flex;justify-content:space-between;margin-bottom:6px;}
.ci-author{font-size:12.5px;font-weight:600;color:var(--navy);}
.ci-date{font-size:11px;color:var(--text-muted);}
.ci-notes{font-size:12px;color:var(--text-secondary);background:var(--white);padding:8px 10px;border-radius:var(--r-sm);margin-bottom:10px;border:1px solid var(--border-light);}
.ci-dl{display:flex;align-items:center;gap:6px;padding:6px 12px;background:var(--navy);color:var(--white);font-size:11.5px;font-weight:500;border-radius:var(--r-sm);width:100%;justify-content:center;border:none;cursor:pointer;transition:var(--transition);}
.ci-dl:hover{background:var(--navy-light);}

/* ADDITIONAL INFO */
.info-table{width:100%;border-collapse:collapse;}
.info-table th,.info-table td{padding:8px 10px;font-size:12.5px;border-bottom:1px solid var(--border-light);text-align:left;}
.info-table th{color:var(--text-muted);font-weight:600;width:40%;}
.info-table td{color:var(--text-primary);}
.info-table tr:last-child th,.info-table tr:last-child td{border-bottom:none;}

/* MODAL */
.modal-content{border:none;border-radius:var(--r-xl);overflow:hidden;box-shadow:0 20px 60px rgba(8,14,28,.2);}
.modal-header{background:var(--navy);color:var(--white);border-bottom:none;padding:20px 24px;}
.modal-title{font-family:var(--font-display);font-size:20px;font-weight:500;}
.modal-title i{color:var(--gold);margin-right:8px;}
.btn-close{filter:invert(1);}
.modal-body{padding:24px;}
.modal-footer{border-top:1px solid var(--border-light);padding:16px 24px;}
.btn-modal-gold{background:var(--gold);color:var(--midnight);border:none;padding:10px 22px;border-radius:var(--r-sm);font-size:13px;font-weight:500;cursor:pointer;transition:var(--transition);}
.btn-modal-gold:hover{background:var(--gold-bright);}
.btn-modal-ghost{background:var(--cream);color:var(--text-secondary);border:1px solid var(--border-light);padding:10px 22px;border-radius:var(--r-sm);font-size:13px;font-weight:500;cursor:pointer;transition:var(--transition);}

.portal-footer{margin-left:var(--sidebar-width);background:var(--white);padding:14px 26px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;}
.footer-copy{font-size:11.5px;color:var(--text-muted);}

.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:500;}
.badge-pending{background:var(--warning-pale);color:var(--warning);}
.badge-approved{background:var(--success-pale);color:var(--success);}
.badge-rejected{background:var(--danger-pale);color:var(--danger);}
.badge-review{background:rgba(99,102,241,.1);color:#6366F1;}
.badge-secondary{background:var(--cream);color:var(--text-muted);}

@media(max-width:1100px){.review-grid{grid-template-columns:1fr;}}
@media(max-width:991px){
  .sidebar{transform:translateX(-100%);}
  .sidebar.active{transform:translateX(0);}
  .header{left:0;}.main,.portal-footer{margin-left:0;}
  .mobile-toggle{display:flex;}.header-breadcrumb{display:none;}
}
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
    <div class="nav-item"><a href="applications.php" class="nav-link"><i class="fas fa-tasks"></i> Applications</a></div>
    <div class="nav-section-label">Management</div>
    <div class="nav-item"><a href="admin-document-review.php" class="nav-link active"><i class="fas fa-file-alt"></i> Review Documents<?php if(!empty($stats['pending_count'])): ?><span class="nav-badge"><?= $stats['pending_count'] ?></span><?php endif; ?></a></div>
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
      <a href="admin-document-review.php" style="color:var(--text-muted)">Documents</a><span class="sep">/</span>
      <strong>Review</strong>
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
<?php if ($error_message): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error_message) ?></div><?php endif; ?>
<?php if ($success_message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success_message) ?></div><?php endif; ?>

<?php if (!empty($document)): ?>

<!-- DOCUMENT HERO -->
<div class="doc-hero">
  <div class="doc-hero-inner">
    <div class="doc-hero-icon"><i class="fas <?= getDocTypeIcon($document['document_type']) ?>"></i></div>
    <div class="doc-hero-info">
      <div class="doc-hero-type"><?= strtoupper(str_replace('_',' ',$document['document_type'])) ?></div>
      <div class="doc-hero-title"><?= getDocTypeLabel($document['document_type']) ?></div>
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:4px">
        <span class="doc-status-pill">
          <i class="fas fa-<?= statusIcon($document['review_status']) ?>"></i>
          <?= statusLabel($document['review_status']) ?>
        </span>
        <span style="font-size:11.5px;color:rgba(255,255,255,.45)"><i class="fas fa-code-branch" style="margin-right:4px"></i>Version <?= $document['version']?:'1' ?></span>
      </div>
      <div class="doc-hero-meta">
        <div class="doc-hero-meta-item"><i class="fas fa-calendar"></i><?= date('F j, Y',strtotime($document['upload_date'])) ?></div>
        <div class="doc-hero-meta-item"><i class="fas fa-clock"></i><?= date('h:i A',strtotime($document['upload_date'])) ?></div>
        <div class="doc-hero-meta-item"><i class="fas fa-file"></i><?= strtoupper(pathinfo($document['file_name'],PATHINFO_EXTENSION)) ?> File</div>
      </div>
    </div>
    <div class="doc-hero-actions">
      <a href="download-document.php?id=<?= $document['id'] ?>" class="btn-hero btn-hero-ghost"><i class="fas fa-download"></i> Download</a>
      <button type="button" class="btn-hero btn-hero-gold" data-bs-toggle="modal" data-bs-target="#uploadModal"><i class="fas fa-upload"></i> Upload Correction</button>
    </div>
  </div>
</div>

<!-- SCHOLAR STRIP -->
<div class="scholar-strip">
  <div class="scholar-av">
    <?php if(!empty($document['profile_picture'])): ?>
    <img src="/scholar-portal/uploads/profile_pictures/<?= htmlspecialchars($document['profile_picture']) ?>" alt="">
    <?php else: ?>
    <?= strtoupper(substr($document['first_name'],0,1)) ?>
    <?php endif; ?>
  </div>
  <div style="flex:1">
    <div class="scholar-name"><?= htmlspecialchars($document['first_name'].' '.$document['last_name']) ?></div>
    <div class="scholar-email"><i class="fas fa-envelope" style="font-size:10px"></i><?= htmlspecialchars($document['email']) ?></div>
  </div>
  <a href="admin-document-review.php" class="btn-hero btn-hero-ghost" style="font-size:12px;padding:8px 14px">
    <i class="fas fa-arrow-left"></i> Back to List
  </a>
</div>

<!-- REVIEW GRID -->
<div class="review-grid">

  <!-- LEFT: PREVIEW + INFO -->
  <div>
    <div class="preview-card">
      <div class="pc-header">
        <span class="pc-title"><i class="fas fa-eye" style="color:var(--gold);margin-right:6px"></i>Document Preview</span>
        <div style="display:flex;gap:6px">
          <a href="download-document.php?id=<?= $document['id'] ?>" style="font-size:12px;color:var(--gold);font-weight:500;display:flex;align-items:center;gap:4px"><i class="fas fa-download"></i> Download</a>
        </div>
      </div>
      <?php
      $ext = strtolower(pathinfo($document['file_name'],PATHINFO_EXTENSION));
      $proto = (!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!='off')?'https':'http';
      $fileUrl = $proto.'://'.$_SERVER['HTTP_HOST'].'/scholar-portal/uploads/documents/'.rawurlencode($document['file_name']);
      $fsPath  = rtrim($_SERVER['DOCUMENT_ROOT'],'/\\').'/scholar-portal/uploads/documents/'.$document['file_name'];
      $exists  = file_exists($fsPath);
      if($exists && $ext==='pdf'): ?>
      <iframe src="https://docs.google.com/viewer?url=<?= urlencode($fileUrl) ?>&embedded=true" class="doc-frame"></iframe>
      <?php elseif($exists && in_array($ext,['doc','docx'])): ?>
      <iframe src="https://view.officeapps.live.com/op/embed.aspx?src=<?= urlencode($fileUrl) ?>" class="doc-frame"></iframe>
      <?php elseif($exists && in_array($ext,['jpg','jpeg','png','gif'])): ?>
      <div style="text-align:center;padding:20px;background:#f8f9fb"><img src="<?= htmlspecialchars($fileUrl) ?>" style="max-height:650px;max-width:100%;border-radius:var(--r-md)" alt="Document"></div>
      <?php elseif($exists): ?>
      <div class="preview-fallback">
        <div class="pf-icon"><i class="fas fa-file"></i></div>
        <div class="pf-title">Preview Unavailable</div>
        <p class="pf-sub">This file type cannot be previewed in the browser.</p>
        <a href="download-document.php?id=<?= $document['id'] ?>" class="btn-hero btn-hero-gold"><i class="fas fa-download"></i> Download to View</a>
      </div>
      <?php else: ?>
      <div class="preview-fallback">
        <div class="pf-icon" style="color:var(--warning)"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="pf-title">File Not Found</div>
        <p class="pf-sub">The document file could not be found on the server.</p>
      </div>
      <?php endif; ?>
    </div>

    <?php if(!empty($additional_data)): ?>
    <div class="panel-card" style="margin-top:16px">
      <div class="panel-card-header">
        <span class="panel-card-title"><i class="fas fa-info-circle"></i> Additional Information</span>
      </div>
      <div class="panel-card-body" style="padding:0">
        <table class="info-table">
          <tbody>
            <?php foreach($additional_data as $k=>$v): ?>
            <tr>
              <th><?= ucwords(str_replace('_',' ',$k)) ?></th>
              <td><?= is_string($v)?nl2br(htmlspecialchars($v)):htmlspecialchars(json_encode($v)) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- RIGHT PANEL -->
  <div class="right-panel">

    <!-- REVIEW CHECKLIST (new feature) -->
    <div class="panel-card">
      <div class="panel-card-header">
        <span class="panel-card-title"><i class="fas fa-clipboard-check"></i> Review Checklist</span>
      </div>
      <div class="panel-card-body">
        <div class="checklist" id="reviewChecklist">
          <div class="check-item"><input type="checkbox" id="c1"><label for="c1">Document is complete and legible</label></div>
          <div class="check-item"><input type="checkbox" id="c2"><label for="c2">Content matches stated document type</label></div>
          <div class="check-item"><input type="checkbox" id="c3"><label for="c3">All required sections are present</label></div>
          <div class="check-item"><input type="checkbox" id="c4"><label for="c4">No inconsistencies or discrepancies</label></div>
          <div class="check-item"><input type="checkbox" id="c5"><label for="c5">Formatting meets requirements</label></div>
          <div class="check-item"><input type="checkbox" id="c6"><label for="c6">Signatures / certifications present (if applicable)</label></div>
        </div>
        <div style="margin-top:12px;padding:10px;background:var(--cream);border-radius:var(--r-sm);text-align:center">
          <span style="font-size:12px;color:var(--text-muted)">Checklist progress: </span>
          <span id="checkProgress" style="font-size:13px;font-weight:600;color:var(--navy)">0 / 6</span>
        </div>
      </div>
    </div>

    <!-- FEEDBACK FORM -->
    <div class="panel-card">
      <div class="panel-card-header">
        <span class="panel-card-title"><i class="fas fa-comment-dots"></i> Provide Feedback</span>
      </div>
      <div class="panel-card-body">
        <!-- Feedback templates (new feature) -->
        <div style="margin-bottom:14px">
          <div style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px">Quick Templates</div>
          <div class="template-chips">
            <span class="tmpl-chip" onclick="insertTemplate('approve')">✓ Approve</span>
            <span class="tmpl-chip" onclick="insertTemplate('revise')">✎ Request Revision</span>
            <span class="tmpl-chip" onclick="insertTemplate('missing')">⚠ Missing Info</span>
            <span class="tmpl-chip" onclick="insertTemplate('excellent')">★ Excellent Work</span>
          </div>
        </div>
        <form method="POST">
          <div class="form-group">
            <label class="form-label">Review Status</label>
            <select name="review_status" class="form-control" required id="reviewStatusSel">
              <option value="">Select status…</option>
              <option value="in_review" <?= $document['review_status']==='in_review'?'selected':'' ?>>In Review</option>
              <option value="needs_revision" <?= $document['review_status']==='needs_revision'?'selected':'' ?>>Needs Revision</option>
              <option value="approved" <?= $document['review_status']==='approved'?'selected':'' ?>>Approved</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Feedback Comments</label>
            <textarea name="feedback" id="feedbackArea" class="form-control" rows="7" placeholder="Provide detailed feedback…"><?= htmlspecialchars($document['feedback']??'') ?></textarea>
          </div>
          <button type="submit" name="submit_review" class="btn-submit btn-gold-submit">
            <i class="fas fa-paper-plane"></i> Submit Feedback &amp; Notify Scholar
          </button>
        </form>
      </div>
    </div>

    <!-- CORRECTIONS HISTORY -->
    <?php if(!empty($corrections)): ?>
    <div class="panel-card">
      <div class="panel-card-header">
        <span class="panel-card-title"><i class="fas fa-history"></i> Corrected Versions</span>
      </div>
      <div class="panel-card-body">
        <?php foreach($corrections as $c): ?>
        <div class="correction-item">
          <div class="ci-header">
            <span class="ci-author"><?= htmlspecialchars($c['admin_fn'].' '.$c['admin_ln']) ?></span>
            <span class="ci-date"><?= date('M d, Y',strtotime($c['created_at'])) ?></span>
          </div>
          <?php if(!empty($c['notes'])): ?><div class="ci-notes"><?= nl2br(htmlspecialchars($c['notes'])) ?></div><?php endif; ?>
          <a href="download-correction.php?id=<?= $c['id'] ?>" class="ci-dl"><i class="fas fa-download"></i> Download Correction</a>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- VERSION HISTORY -->
    <div class="panel-card">
      <div class="panel-card-header">
        <span class="panel-card-title"><i class="fas fa-code-branch"></i> Version History</span>
      </div>
      <div class="panel-card-body">
        <?php if(!empty($versions)): ?>
        <div class="timeline">
          <?php foreach($versions as $v): ?>
          <div class="tl-item <?= $v['id']==$document_id?'current':'' ?>">
            <div class="tl-dot"></div>
            <div class="tl-content">
              <div class="tl-title">
                <span>Version <?= $v['version']?:'1' ?></span>
                <span class="badge <?= statusBadge($v['review_status']) ?>"><i class="fas fa-<?= statusIcon($v['review_status']) ?>"></i><?= statusLabel($v['review_status']) ?></span>
              </div>
              <div class="tl-date"><?= date('M j, Y · h:i A',strtotime($v['upload_date'])) ?></div>
              <?php if($v['id']!=$document_id): ?>
              <div style="display:flex;gap:6px;margin-top:8px">
                <a href="admin-review-document.php?id=<?= $v['id'] ?>" style="flex:1;display:flex;align-items:center;justify-content:center;gap:4px;padding:5px 10px;background:var(--navy);color:var(--white);font-size:11.5px;border-radius:var(--r-sm)"><i class="fas fa-eye"></i> View</a>
                <a href="download-document.php?id=<?= $v['id'] ?>" style="flex:1;display:flex;align-items:center;justify-content:center;gap:4px;padding:5px 10px;background:var(--cream);color:var(--text-secondary);font-size:11.5px;border-radius:var(--r-sm);border:1px solid var(--border-light)"><i class="fas fa-download"></i></a>
              </div>
              <?php else: ?>
              <div style="font-size:11px;color:var(--gold);margin-top:5px;font-weight:500">← Currently Viewing</div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="font-size:13px;color:var(--text-muted);text-align:center;padding:20px 0">No version history</p>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<?php else: ?>
<div style="background:var(--white);border-radius:var(--r-xl);border:1px solid var(--border-light);padding:60px 24px;text-align:center">
  <div style="font-size:48px;color:var(--border-light);margin-bottom:16px"><i class="fas fa-file-exclamation"></i></div>
  <div style="font-family:var(--font-display);font-size:22px;color:var(--navy);margin-bottom:8px">Document Not Found</div>
  <p style="font-size:13px;color:var(--text-muted);margin-bottom:20px">The document you requested could not be found.</p>
  <a href="admin-document-review.php" class="btn-hero btn-hero-gold"><i class="fas fa-arrow-left"></i> Back to Documents</a>
</div>
<?php endif; ?>

</main>

<!-- UPLOAD CORRECTION MODAL -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-upload"></i> Upload Corrected Document</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Corrected Document</label>
            <input type="file" name="corrected_document" class="form-control" accept=".pdf,.doc,.docx" required>
            <div style="font-size:11.5px;color:var(--text-muted);margin-top:5px">Accepted: PDF, DOC, DOCX</div>
          </div>
          <div class="form-group">
            <label class="form-label">Correction Notes</label>
            <textarea name="correction_notes" class="form-control" rows="4" placeholder="Explain what was corrected…"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-modal-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="upload_corrected" class="btn-modal-gold"><i class="fas fa-upload" style="margin-right:6px"></i>Upload</button>
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

// Clock
function updateClock(){const el=document.getElementById('headerTime');if(!el)return;const n=new Date();el.textContent=n.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'})+' · '+n.toLocaleDateString('en-GB',{day:'numeric',month:'short'});}
updateClock();setInterval(updateClock,30000);

// Checklist progress
const checkboxes=document.querySelectorAll('#reviewChecklist input[type=checkbox]');
const progressEl=document.getElementById('checkProgress');
function updateProgress(){const done=[...checkboxes].filter(c=>c.checked).length;if(progressEl)progressEl.textContent=`${done} / ${checkboxes.length}`;}
checkboxes.forEach(cb=>cb.addEventListener('change',updateProgress));

// Feedback templates
const templates={
  approve:`Thank you for submitting your ${document.querySelector('.doc-hero-title')?.textContent||'document'}. After thorough review, we are pleased to inform you that your submission has been approved. The content is clear, well-structured, and meets our requirements. Well done!`,
  revise:`Thank you for your submission. After reviewing your document, we have identified areas that require revision before we can proceed. Please address the following points and resubmit at your earliest convenience.`,
  missing:`Your submission has been reviewed. However, we noticed that some required information appears to be missing or incomplete. Please ensure all sections are fully completed and resubmit.`,
  excellent:`Excellent work on your submission! Your document demonstrates a high level of preparation, clarity, and attention to detail. We are very impressed with the quality of your work.`
};
function insertTemplate(key){
  const ta=document.getElementById('feedbackArea');
  if(ta&&templates[key]){ta.value=templates[key];ta.focus();}
  if(key==='approve'){const sel=document.getElementById('reviewStatusSel');if(sel)sel.value='approved';}
  if(key==='revise'||key==='missing'){const sel=document.getElementById('reviewStatusSel');if(sel)sel.value='needs_revision';}
}
</script>
</body>
</html>