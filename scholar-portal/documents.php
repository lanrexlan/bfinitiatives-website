<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$first_name = $last_name = '';
$profile_picture = null;
$notification_count = 0;
$selected_doc  = isset($_GET['type'])   ? $_GET['type']   : 'all';
$search_query  = isset($_GET['search']) ? $_GET['search'] : '';
$sort_by       = isset($_GET['sort'])   ? $_GET['sort']   : 'upload_date';
$sort_order    = isset($_GET['order'])  ? $_GET['order']  : 'desc';
$documents     = [];
$error_message = $success_message = '';
$stats = ['total_documents'=>0,'approved_count'=>0,'pending_count'=>0,'revision_count'=>0,'review_count'=>0];
$type_status   = [];   // checklist: type => review_status
$all_uploaded_types = [];

define('UPLOAD_DIR', 'uploads/documents/');

if (isset($_SESSION['success_message'])) { $success_message = $_SESSION['success_message']; unset($_SESSION['success_message']); }
if (isset($_SESSION['error_message']))   { $error_message   = $_SESSION['error_message'];   unset($_SESSION['error_message']); }

try {
    $db   = new Database();
    $conn = $db->getConnection();

    /* ── user ── */
    $u = $conn->prepare("SELECT first_name, last_name, profile_picture FROM users WHERE id = :uid");
    $u->execute([':uid' => $_SESSION['user_id']]);
    $ud = $u->fetch(PDO::FETCH_ASSOC);
    if ($ud) { $first_name = $ud['first_name']; $last_name = $ud['last_name'] ?? ''; $profile_picture = $ud['profile_picture'] ?? null; }

    /* ── notifications ── */
    try {
        $ns = $conn->prepare("SELECT id FROM notifications WHERE user_id = :uid AND read_status = 0");
        $ns->execute([':uid' => $_SESSION['user_id']]);
        $notification_count = $ns->rowCount();
    } catch (Exception $e) { /* table may not exist yet */ }

    /* ── stats ── */
    $ss = $conn->prepare("
        SELECT COUNT(*) as total_documents,
               SUM(CASE WHEN review_status='approved'       THEN 1 ELSE 0 END) as approved_count,
               SUM(CASE WHEN review_status='pending'        THEN 1 ELSE 0 END) as pending_count,
               SUM(CASE WHEN review_status='needs_revision' THEN 1 ELSE 0 END) as revision_count,
               SUM(CASE WHEN review_status='in_review'      THEN 1 ELSE 0 END) as review_count
        FROM user_documents WHERE user_id = :uid");
    $ss->execute([':uid' => $_SESSION['user_id']]);
    $sr = $ss->fetch(PDO::FETCH_ASSOC);
    if ($sr) $stats = $sr;

    /* ── checklist: best status per type (all uploads, ignoring filter) ── */
    $ck = $conn->prepare("
        SELECT DISTINCT ON (document_type) document_type, review_status
        FROM user_documents WHERE user_id = :uid
        ORDER BY document_type, version DESC NULLS LAST");
    $ck->execute([':uid' => $_SESSION['user_id']]);
    foreach ($ck->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $type_status[$row['document_type']] = $row['review_status'];
        $all_uploaded_types[] = $row['document_type'];
    }

    /* ── document list (filtered) ── */
    $dq = "SELECT id, document_type, file_name, upload_date, status, review_status,
                  feedback, feedback_date, version
           FROM user_documents WHERE user_id = :uid";
    $params = [':uid' => $_SESSION['user_id']];
    if (!empty($search_query)) { $dq .= " AND (file_name ILIKE :s OR document_type ILIKE :s)"; $params[':s'] = '%'.$search_query.'%'; }
    if ($selected_doc !== 'all') { $dq .= " AND document_type = :dt"; $params[':dt'] = $selected_doc; }
    $vsorts = ['upload_date','file_name','review_status','document_type'];
    $dq .= in_array($sort_by,$vsorts)&&in_array($sort_order,['asc','desc'])?" ORDER BY {$sort_by} {$sort_order}":" ORDER BY upload_date DESC";
    $ds = $conn->prepare($dq);
    $ds->execute($params);
    $raw = $ds->fetchAll(PDO::FETCH_ASSOC);

    $lv=[]; $dm=[];
    foreach ($raw as $doc) {
        $t = $doc['document_type'];
        if ($selected_doc === 'all') {
            if (!isset($lv[$t]) || $doc['version'] > $lv[$t]) { $lv[$t]=$doc['version']; $dm[$t]=$doc; }
        } else { $dm[] = $doc; }
    }
    $documents = $selected_doc==='all' ? array_values($dm) : $dm;

    /* ── corrections ── */
    if (!empty($documents)) {
        try {
            $tc = $conn->query("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='document_corrections')");
            if ($tc->fetchColumn()) {
                foreach ($documents as &$doc) {
                    $cs = $conn->prepare("
                        SELECT c.id,c.corrected_file,c.notes,c.created_at,
                               a.first_name as afn, a.last_name as aln
                        FROM document_corrections c
                        JOIN admins a ON c.admin_id=a.id
                        WHERE c.document_id=:did ORDER BY c.created_at DESC");
                    $cs->execute([':did'=>$doc['id']]);
                    $doc['corrections']=$cs->fetchAll(PDO::FETCH_ASSOC);
                }
                unset($doc);
            }
        } catch (Exception $e) { /* silent */ }
    }

} catch (Exception $e) {
    error_log("Documents error: ".$e->getMessage());
    $error_message = "An error occurred while retrieving your documents.";
}

/* ── helpers ── */
function getDocTypeLabel($t){return['cv'=>'CV / Resume','statement'=>'Personal Statement','research'=>'Research Proposal','recommendation'=>'Recommendation Letter','language'=>'Language Test','cold email'=>'Cold Email'][$t]??ucfirst($t);}
function getDocTypeIcon($t){return['cv'=>'fa-file-alt','statement'=>'fa-pen-fancy','research'=>'fa-flask','recommendation'=>'fa-envelope-open-text','language'=>'fa-language','cold email'=>'fa-envelope'][$t]??'fa-file';}
function getDocTypeColor($t){return['cv'=>'#C8A058','statement'=>'#7C9EBF','research'=>'#6BAF8A','recommendation'=>'#B07CC6','language'=>'#E07C5A','cold email'=>'#5A8AE0'][$t]??'#C8A058';}
function getStatusInfo($s){$m=['pending'=>['label'=>'Pending','color'=>'#F59E0B','icon'=>'fa-clock','bg'=>'rgba(245,158,11,0.1)'],'in_review'=>['label'=>'In Review','color'=>'#3B82F6','icon'=>'fa-spinner','bg'=>'rgba(59,130,246,0.1)'],'needs_revision'=>['label'=>'Needs Revision','color'=>'#EF4444','icon'=>'fa-exclamation-circle','bg'=>'rgba(239,68,68,0.1)'],'approved'=>['label'=>'Approved','color'=>'#10B981','icon'=>'fa-check-circle','bg'=>'rgba(16,185,129,0.1)']];return$m[$s]??['label'=>ucfirst($s),'color'=>'#8A92A8','icon'=>'fa-circle','bg'=>'rgba(138,146,168,0.1)'];}
function isActive($t){global$selected_doc;return$selected_doc===$t?'active':'';}
function docExists($f){return file_exists('uploads/documents/'.$f);}
function canPreview($f){return in_array(strtolower(pathinfo($f,PATHINFO_EXTENSION)),['pdf','txt','jpg','jpeg','png','gif']);}

$all_types  = ['cv','statement','research','recommendation','language','cold email'];
$vault_total = count($all_types);
$vault_done  = count(array_intersect($all_types, $all_uploaded_types));
$vault_pct   = $vault_total > 0 ? round(($vault_done/$vault_total)*100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
  <title>My Documents | BFI Scholar Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{
      --midnight:#080E1C;--navy:#0D1829;--navy-mid:#132038;--navy-light:#1C2F52;
      --gold:#C8A058;--gold-bright:#E0B96C;--gold-pale:#F0D9A8;
      --cream:#FAF6EF;--cream-dark:#F2EAD8;--white:#FFFFFF;
      --text-primary:#0D1829;--text-secondary:#4A526A;--text-muted:#8A92A8;
      --border-light:#E8E4DA;
      --font-display:'Cormorant Garamond',Georgia,serif;
      --font-body:'Outfit',-apple-system,sans-serif;
      --ease:cubic-bezier(0.25,0.46,0.45,0.94);
      --transition:0.3s var(--ease);
      --shadow-sm:0 2px 8px rgba(8,14,28,0.06);
      --shadow-md:0 8px 32px rgba(8,14,28,0.10);
      --shadow-lg:0 20px 60px rgba(8,14,28,0.14);
      --sidebar-width:268px;--header-height:64px;
      --r-sm:8px;--r-md:14px;--r-lg:20px;--r-xl:28px;
    }
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    html{font-size:16px;}
    body{font-family:var(--font-body);background:#F2F4F8;color:var(--text-primary);line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden;}
    a{text-decoration:none;color:inherit;}
    img{max-width:100%;display:block;}

    /* SIDEBAR */
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

    /* HEADER */
    .header{position:fixed;top:0;left:var(--sidebar-width);right:0;height:var(--header-height);background:var(--white);border-bottom:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;padding:0 28px;z-index:100;transition:left var(--transition);}
    .header-left{display:flex;align-items:center;gap:16px;}
    .mobile-toggle{display:none;background:none;border:none;cursor:pointer;padding:6px;color:var(--text-secondary);}
    .header-page-title{font-family:var(--font-display);font-size:18px;font-weight:500;color:var(--navy);}
    .header-page-title em{font-style:italic;color:var(--gold);}
    .header-right{display:flex;align-items:center;gap:16px;}
    .header-icon-btn{width:36px;height:36px;border-radius:var(--r-sm);background:var(--cream);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-secondary);font-size:15px;transition:var(--transition);position:relative;}
    .header-icon-btn:hover{background:var(--cream-dark);color:var(--navy);}
    .notif-dot{position:absolute;top:6px;right:6px;width:8px;height:8px;border-radius:50%;background:#EF4444;border:2px solid var(--white);}
    .header-avatar{width:36px;height:36px;border-radius:50%;background:var(--navy-light);overflow:hidden;border:2px solid var(--border-light);cursor:pointer;display:flex;align-items:center;justify-content:center;}
    .header-avatar img{width:100%;height:100%;object-fit:cover;}
    .header-avatar-init{font-family:var(--font-display);font-size:14px;color:var(--gold-bright);}

    /* MAIN */
    .main{margin-left:var(--sidebar-width);margin-top:var(--header-height);padding:28px;min-height:calc(100vh - var(--header-height));transition:margin-left var(--transition);}

    /* ALERTS */
    .alert-banner{border-radius:var(--r-md);padding:14px 18px;margin-bottom:20px;font-size:13.5px;display:flex;align-items:center;gap:10px;}
    .alert-success-banner{background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);color:#065F46;}
    .alert-error-banner{background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);color:#991B1B;}

    /* VAULT BANNER */
    .vault-banner{background:var(--navy);border-radius:var(--r-xl);padding:28px 32px;margin-bottom:24px;position:relative;overflow:hidden;}
    .vault-banner::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:28px 28px;}
    .vault-banner::after{content:'';position:absolute;top:-40px;right:-40px;width:220px;height:220px;background:radial-gradient(circle,rgba(200,160,88,0.12) 0%,transparent 65%);}
    .vault-inner{position:relative;z-index:1;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;}
    .vault-text-eyebrow{font-size:10px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:rgba(200,160,88,0.7);margin-bottom:6px;}
    .vault-title{font-family:var(--font-display);font-size:clamp(20px,2.5vw,28px);font-weight:500;color:var(--white);line-height:1.15;margin-bottom:4px;}
    .vault-title em{font-style:italic;color:var(--gold-bright);}
    .vault-sub{font-size:13px;font-weight:300;color:rgba(255,255,255,0.5);}
    .vault-score-ring{display:flex;align-items:center;gap:20px;flex-wrap:wrap;}
    .score-ring{position:relative;width:88px;height:88px;flex-shrink:0;}
    .score-ring svg{transform:rotate(-90deg);}
    .score-ring-text{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;}
    .score-num{font-family:var(--font-display);font-size:22px;font-weight:500;color:var(--white);}
    .score-denom{font-size:10px;color:rgba(255,255,255,0.4);}
    .score-details{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
    .score-detail-item{background:rgba(255,255,255,0.05);border-radius:var(--r-sm);padding:8px 12px;}
    .score-detail-val{font-size:16px;font-weight:600;color:var(--gold-bright);}
    .score-detail-lbl{font-size:10px;color:rgba(255,255,255,0.4);margin-top:1px;}
    .btn-upload-new{display:inline-flex;align-items:center;gap:8px;padding:11px 22px;background:var(--gold);color:var(--midnight);font-family:var(--font-body);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-upload-new:hover{background:var(--gold-bright);transform:translateY(-1px);}

    /* PORTFOLIO CHECKLIST */
    .checklist-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:20px 24px;margin-bottom:24px;}
    .checklist-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
    .checklist-title{font-family:var(--font-display);font-size:17px;font-weight:500;color:var(--navy);}
    .checklist-progress-text{font-size:12px;color:var(--text-muted);}
    .checklist-bar{height:5px;background:var(--border-light);border-radius:3px;margin-bottom:18px;overflow:hidden;}
    .checklist-bar-fill{height:100%;background:linear-gradient(90deg,var(--gold),var(--gold-bright));border-radius:3px;transition:width 0.6s var(--ease);}
    .checklist-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;}
    .checklist-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:var(--r-sm);border:1px solid var(--border-light);background:var(--cream);transition:var(--transition);cursor:pointer;}
    .checklist-item:hover{border-color:var(--gold);background:#fff;}
    .checklist-item.done{background:rgba(16,185,129,0.05);border-color:rgba(16,185,129,0.25);}
    .checklist-item.revision{background:rgba(239,68,68,0.05);border-color:rgba(239,68,68,0.2);}
    .checklist-item.review{background:rgba(59,130,246,0.05);border-color:rgba(59,130,246,0.2);}
    .checklist-icon{width:30px;height:30px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;}
    .checklist-item-name{font-size:12px;font-weight:500;color:var(--text-primary);line-height:1.3;}
    .checklist-item-status{font-size:10px;margin-top:1px;}
    .checklist-badge{margin-left:auto;font-size:10px;font-weight:600;padding:2px 7px;border-radius:8px;white-space:nowrap;}

    /* TYPE CHIPS */
    .type-chips{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;}
    .type-chip{display:inline-flex;align-items:center;gap:7px;padding:7px 14px;border-radius:20px;font-size:12px;font-weight:500;border:1px solid var(--border-light);background:var(--white);color:var(--text-secondary);cursor:pointer;transition:var(--transition);}
    .type-chip:hover,.type-chip.active{background:var(--navy);color:var(--gold-bright);border-color:var(--navy);}
    .type-chip i{font-size:11px;}
    .chip-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;}

    /* SECTION LABEL */
    .section-label{font-size:10px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);margin-bottom:14px;}

    /* SEARCH ROW */
    .search-row{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:16px 20px;margin-bottom:20px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;}
    .search-field{flex:1;min-width:180px;}
    .search-field label{font-size:11px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted);margin-bottom:6px;display:block;}
    .search-input-wrap{position:relative;}
    .search-input-wrap input,.search-input-wrap select{width:100%;padding:9px 14px 9px 36px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13px;color:var(--text-primary);background:var(--cream);transition:var(--transition);}
    .search-input-wrap select{padding-left:14px;}
    .search-input-wrap input:focus,.search-input-wrap select:focus{outline:none;border-color:var(--gold);background:var(--white);}
    .search-input-wrap i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:12px;pointer-events:none;}
    .search-btn{padding:9px 20px;background:var(--navy);color:var(--white);border:none;border-radius:var(--r-sm);font-family:var(--font-body);font-size:13px;font-weight:500;cursor:pointer;transition:var(--transition);white-space:nowrap;align-self:flex-end;}
    .search-btn:hover{background:var(--navy-light);}
    .clear-btn{padding:9px 14px;background:var(--cream);color:var(--text-secondary);border:1px solid var(--border-light);border-radius:var(--r-sm);font-size:13px;cursor:pointer;transition:var(--transition);align-self:flex-end;}
    .clear-btn:hover{background:var(--cream-dark);}

    /* DOCUMENT CARDS */
    .doc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;margin-bottom:24px;}
    .doc-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);overflow:hidden;transition:var(--transition);display:flex;flex-direction:column;}
    .doc-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-md);border-color:transparent;}
    .doc-card-head{padding:18px 20px 14px;display:flex;align-items:flex-start;gap:14px;border-bottom:1px solid var(--border-light);}
    .doc-type-icon{width:42px;height:42px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
    .doc-type-name{font-size:14px;font-weight:600;color:var(--navy);margin-bottom:3px;}
    .doc-filename{font-size:11.5px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;}
    .doc-status-pill{margin-left:auto;display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;white-space:nowrap;flex-shrink:0;}
    .doc-card-body{padding:16px 20px;flex:1;}
    .doc-meta-row{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text-muted);margin-bottom:8px;}
    .doc-meta-row i{width:14px;color:var(--gold);font-size:11px;}
    .feedback-box{background:var(--cream);border-radius:var(--r-sm);padding:12px 14px;margin-top:12px;border-left:3px solid var(--gold);}
    .feedback-box-label{font-size:10px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:var(--gold);margin-bottom:6px;}
    .feedback-box-text{font-size:12.5px;color:var(--text-secondary);line-height:1.5;}
    .correction-box{background:rgba(59,130,246,0.05);border-radius:var(--r-sm);padding:12px 14px;margin-top:10px;border:1px solid rgba(59,130,246,0.15);}
    .correction-box-label{font-size:10px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:#3B82F6;margin-bottom:6px;}
    .doc-card-foot{padding:14px 20px;border-top:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;}
    .doc-version-tag{font-size:11px;color:var(--text-muted);background:var(--cream);padding:3px 9px;border-radius:10px;}
    .doc-actions{display:flex;gap:8px;flex-wrap:wrap;}
    .doc-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:var(--r-sm);font-size:12px;font-weight:500;border:none;cursor:pointer;transition:var(--transition);font-family:var(--font-body);}
    .doc-btn-view{background:var(--navy);color:var(--white);}
    .doc-btn-view:hover{background:var(--navy-light);}
    .doc-btn-dl{background:var(--cream);color:var(--text-secondary);border:1px solid var(--border-light);}
    .doc-btn-dl:hover{background:var(--cream-dark);color:var(--navy);}
    .doc-btn-revise{background:rgba(239,68,68,0.08);color:#EF4444;border:1px solid rgba(239,68,68,0.2);}
    .doc-btn-revise:hover{background:rgba(239,68,68,0.15);}

    /* UPLOAD CARD */
    .upload-card{background:var(--white);border-radius:var(--r-lg);border:2px dashed var(--border-light);padding:32px 24px;text-align:center;cursor:pointer;transition:var(--transition);}
    .upload-card:hover{border-color:var(--gold);background:var(--cream);}
    .upload-card-icon{width:52px;height:52px;background:var(--cream);border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;font-size:20px;color:var(--gold);margin:0 auto 14px;transition:var(--transition);}
    .upload-card:hover .upload-card-icon{background:var(--navy);color:var(--gold-bright);}
    .upload-card-title{font-family:var(--font-display);font-size:18px;font-weight:500;color:var(--navy);margin-bottom:6px;}
    .upload-card-sub{font-size:12.5px;color:var(--text-muted);}

    /* EMPTY STATE */
    .empty-state{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:48px 32px;text-align:center;grid-column:1/-1;}
    .empty-icon{font-size:36px;color:var(--border-light);margin-bottom:16px;}
    .empty-title{font-family:var(--font-display);font-size:22px;font-weight:500;color:var(--navy);margin-bottom:6px;}
    .empty-sub{font-size:13px;color:var(--text-muted);}

    /* MODAL */
    .modal-content{border:none;border-radius:var(--r-xl);overflow:hidden;}
    .modal-header{background:var(--navy);padding:20px 24px;border:none;}
    .modal-title{font-family:var(--font-display);font-size:20px;font-weight:500;color:var(--white);}
    .modal-title em{font-style:italic;color:var(--gold-bright);}
    .btn-close-white{filter:invert(1);}
    .modal-body{padding:24px;background:var(--cream);}
    .modal-footer{background:var(--white);border-top:1px solid var(--border-light);padding:16px 24px;}
    .form-group{margin-bottom:18px;}
    .form-group label{font-size:12px;font-weight:600;letter-spacing:0.5px;color:var(--text-secondary);margin-bottom:6px;display:block;}
    .form-control,.form-select{width:100%;padding:10px 14px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13.5px;color:var(--text-primary);background:var(--white);transition:var(--transition);}
    .form-control:focus,.form-select:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,160,88,0.1);}
    .form-text{font-size:11.5px;color:var(--text-muted);margin-top:4px;}
    .btn-modal-primary{padding:10px 22px;background:var(--gold);color:var(--midnight);font-family:var(--font-body);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-modal-primary:hover{background:var(--gold-bright);}
    .btn-modal-secondary{padding:10px 18px;background:var(--cream);color:var(--text-secondary);font-family:var(--font-body);font-size:13px;border:1px solid var(--border-light);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-modal-secondary:hover{background:var(--cream-dark);}
    .document-viewer{width:100%;height:70vh;border:none;border-radius:var(--r-sm);}
    .file-preview-fallback{padding:48px;text-align:center;color:var(--text-muted);}
    .file-preview-fallback i{font-size:40px;color:var(--border-light);display:block;margin-bottom:16px;}

    /* SIDEBAR OVERLAY */
    .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:150;backdrop-filter:blur(4px);}
    .sidebar-overlay.active{display:block;}

    /* FOOTER */
    .portal-footer{margin-left:var(--sidebar-width);background:var(--white);padding:16px 28px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;transition:margin-left var(--transition);}
    .footer-copy{font-size:12px;color:var(--text-muted);}
    .footer-links{display:flex;gap:20px;}
    .footer-links a{font-size:12px;color:var(--text-muted);transition:color var(--transition);}
    .footer-links a:hover{color:var(--gold);}

    @media(max-width:1100px){.doc-grid{grid-template-columns:1fr 1fr;}.checklist-grid{grid-template-columns:repeat(3,1fr);}}
    @media(max-width:768px){
      .sidebar{transform:translateX(-100%);}
      .sidebar.active{transform:translateX(0);}
      .header{left:0;}
      .main,.portal-footer{margin-left:0;}
      .mobile-toggle{display:flex;}
      .doc-grid{grid-template-columns:1fr;}
      .vault-score-ring{justify-content:center;}
      .checklist-grid{grid-template-columns:1fr 1fr;}
      .search-row{flex-direction:column;}
    }
    @media(max-width:480px){
      .vault-inner{flex-direction:column;}
      .checklist-grid{grid-template-columns:1fr;}
      .doc-card-foot{flex-direction:column;align-items:flex-start;}
    }
  </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-top">
    <div class="sidebar-logo">
      <div class="sidebar-logomark">
        <svg viewBox="0 0 22 22" fill="none"><path d="M4 18L11 4L18 18" stroke="#080E1C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 13H15" stroke="#080E1C" stroke-width="1.8" stroke-linecap="round"/></svg>
      </div>
      <div class="sidebar-logo-text">BFI<span>Scholar Portal</span></div>
    </div>
    <div class="sidebar-user">
      <div class="sidebar-avatar">
        <?php if ($profile_picture): ?>
          <img src="<?= htmlspecialchars('./uploads/profile_pictures/'.$profile_picture) ?>" alt="Profile"
               onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
          <div class="sidebar-avatar-init" style="display:none;"><?= strtoupper(substr($first_name,0,1)) ?></div>
        <?php else: ?>
          <div class="sidebar-avatar-init"><?= strtoupper(substr($first_name,0,1)) ?></div>
        <?php endif; ?>
      </div>
      <div>
        <div class="sidebar-user-name"><?= htmlspecialchars($first_name.' '.$last_name) ?></div>
        <div class="sidebar-user-role">BFI Scholar</div>
      </div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>
    <div class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a></div>
    <div class="nav-item"><a href="profile.php" class="nav-link"><i class="fas fa-user"></i> My Profile</a></div>
    <div class="nav-item"><a href="my-journey.php" class="nav-link"><i class="fas fa-route"></i> My Journey
      <?php if ($notification_count > 0): ?><span class="nav-badge"><?= $notification_count ?></span><?php endif; ?>
    </a></div>
    <div class="nav-section-label">Resources</div>
    <div class="nav-item"><a href="documents.php" class="nav-link active"><i class="fas fa-file-alt"></i> My Documents</a></div>
    <div class="nav-item"><a href="scholarships.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Scholarships</a></div>
    <div class="nav-item"><a href="mentors.php" class="nav-link"><i class="fas fa-users"></i> My Mentor</a></div>
    <div class="nav-item"><a href="application-help.php" class="nav-link"><i class="fas fa-question-circle"></i> Application Help</a></div>
    <div class="nav-item"><a href="resources.php" class="nav-link"><i class="fas fa-book"></i> Resources</a></div>
    <div class="nav-section-label">Account</div>
    <div class="nav-item"><a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a></div>
  </nav>
  <div class="sidebar-bottom">
    <a href="logout.php" class="nav-link" style="color:rgba(239,68,68,0.7);"><i class="fas fa-sign-out-alt"></i> Log Out</a>
  </div>
</aside>

<!-- HEADER -->
<header class="header">
  <div class="header-left">
    <button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
    <div class="header-page-title">My <em>Document</em> Vault</div>
  </div>
  <div class="header-right">
    <button class="header-icon-btn" title="Upload Document" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
      <i class="fas fa-plus"></i>
    </button>
    <button class="header-icon-btn" title="Notifications">
      <i class="fas fa-bell"></i>
      <?php if ($notification_count > 0): ?><div class="notif-dot"></div><?php endif; ?>
    </button>
    <a href="profile.php">
      <div class="header-avatar">
        <?php if ($profile_picture): ?>
          <img src="<?= htmlspecialchars('./uploads/profile_pictures/'.$profile_picture) ?>" alt="Profile"
               onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
          <div class="header-avatar-init" style="display:none;"><?= strtoupper(substr($first_name,0,1)) ?></div>
        <?php else: ?>
          <div class="header-avatar-init"><?= strtoupper(substr($first_name,0,1)) ?></div>
        <?php endif; ?>
      </div>
    </a>
  </div>
</header>

<!-- MAIN -->
<main class="main">

  <?php if ($success_message): ?>
    <div class="alert-banner alert-success-banner"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success_message) ?></div>
  <?php endif; ?>
  <?php if ($error_message): ?>
    <div class="alert-banner alert-error-banner"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error_message) ?></div>
  <?php endif; ?>

  <!-- VAULT BANNER -->
  <div class="vault-banner">
    <div class="vault-inner">
      <div>
        <div class="vault-text-eyebrow">Document Vault</div>
        <div class="vault-title">Your <em>Application</em> Portfolio</div>
        <div class="vault-sub">Upload, track and manage all your application materials in one place.</div>
        <div style="margin-top:16px;">
          <button class="btn-upload-new" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
            <i class="fas fa-plus"></i> Upload Document
          </button>
        </div>
      </div>
      <div class="vault-score-ring">
        <div class="score-ring">
          <svg width="88" height="88" viewBox="0 0 88 88">
            <circle cx="44" cy="44" r="36" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="7"/>
            <circle cx="44" cy="44" r="36" fill="none" stroke="#C8A058" stroke-width="7" stroke-linecap="round"
              stroke-dasharray="<?= round(226.2 * $vault_pct / 100) ?> 226.2"/>
          </svg>
          <div class="score-ring-text">
            <div class="score-num"><?= $vault_done ?></div>
            <div class="score-denom">of <?= $vault_total ?></div>
          </div>
        </div>
        <div class="score-details">
          <div class="score-detail-item"><div class="score-detail-val"><?= $stats['approved_count'] ?></div><div class="score-detail-lbl">Approved</div></div>
          <div class="score-detail-item"><div class="score-detail-val"><?= $stats['pending_count'] ?></div><div class="score-detail-lbl">Pending</div></div>
          <div class="score-detail-item"><div class="score-detail-val"><?= $stats['revision_count'] ?></div><div class="score-detail-lbl">Revision</div></div>
          <div class="score-detail-item"><div class="score-detail-val"><?= $stats['review_count'] ?></div><div class="score-detail-lbl">In Review</div></div>
        </div>
      </div>
    </div>
  </div>

  <!-- PORTFOLIO CHECKLIST -->
  <div class="checklist-card">
    <div class="checklist-header">
      <div class="checklist-title">Portfolio Checklist</div>
      <div class="checklist-progress-text"><?= $vault_done ?> of <?= $vault_total ?> document types uploaded — <?= $vault_pct ?>% complete</div>
    </div>
    <div class="checklist-bar">
      <div class="checklist-bar-fill" style="width:<?= $vault_pct ?>%;"></div>
    </div>
    <div class="checklist-grid">
      <?php foreach ($all_types as $t):
        $uploaded = isset($type_status[$t]);
        $status   = $type_status[$t] ?? null;
        $color    = getDocTypeColor($t);
        $si       = $status ? getStatusInfo($status) : null;
        $item_class = '';
        if ($status === 'approved')       $item_class = 'done';
        elseif ($status === 'needs_revision') $item_class = 'revision';
        elseif ($status === 'in_review')      $item_class = 'review';
      ?>
      <div class="checklist-item <?= $item_class ?>"
           onclick="setTypeAndSubmit('<?= urlencode($t) ?>')"
           title="<?= $uploaded ? 'View '.$t.' documents' : 'Upload '.$t ?>" >
        <div class="checklist-icon" style="background:<?= $color ?>22;color:<?= $color ?>;">
          <i class="fas <?= getDocTypeIcon($t) ?>"></i>
        </div>
        <div style="flex:1;min-width:0;">
          <div class="checklist-item-name"><?= getDocTypeLabel($t) ?></div>
          <div class="checklist-item-status" style="color:<?= $si ? $si['color'] : 'var(--text-muted)' ?>;">
            <?php if ($si): ?>
              <i class="fas <?= $si['icon'] ?>" style="font-size:9px;"></i> <?= $si['label'] ?>
            <?php else: ?>
              <i class="fas fa-circle-plus" style="font-size:9px;color:var(--text-muted);"></i> Not uploaded
            <?php endif; ?>
          </div>
        </div>
        <?php if ($uploaded): ?>
          <div class="checklist-badge" style="background:<?= $si['bg'] ?>;color:<?= $si['color'] ?>;">
            <i class="fas <?= $si['icon'] ?>"></i>
          </div>
        <?php else: ?>
          <div class="checklist-badge" style="background:var(--cream-dark);color:var(--text-muted);">
            <i class="fas fa-plus"></i>
          </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- UNIFIED FILTER FORM -->
  <form method="GET" id="filterForm">
    <input type="hidden" name="type" id="docTypeHidden" value="<?= htmlspecialchars($selected_doc) ?>">

    <!-- Type chips -->
    <div class="type-chips">
      <div class="type-chip <?= isActive('all') ?>" data-type="all">
        <i class="fas fa-th-large"></i> All Documents
      </div>
      <?php foreach ($all_types as $t): ?>
        <div class="type-chip <?= isActive($t) ?>" data-type="<?= htmlspecialchars($t) ?>">
          <span class="chip-dot" style="background:<?= getDocTypeColor($t) ?>;"></span>
          <?= getDocTypeLabel($t) ?>
          <?php if (in_array($t, $all_uploaded_types)): ?>
            <i class="fas fa-check" style="color:#10B981;font-size:9px;"></i>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Search + sort row -->
    <div class="search-row">
      <div class="search-field">
        <label>Search</label>
        <div class="search-input-wrap">
          <i class="fas fa-search"></i>
          <input type="text" name="search" placeholder="Search by filename or document type…"
                 value="<?= htmlspecialchars($search_query) ?>">
        </div>
      </div>
      <div class="search-field" style="max-width:160px;">
        <label>Sort By</label>
        <div class="search-input-wrap">
          <select name="sort">
            <option value="upload_date"   <?= $sort_by==='upload_date'   ?'selected':'' ?>>Upload Date</option>
            <option value="file_name"     <?= $sort_by==='file_name'     ?'selected':'' ?>>File Name</option>
            <option value="review_status" <?= $sort_by==='review_status' ?'selected':'' ?>>Status</option>
            <option value="document_type" <?= $sort_by==='document_type' ?'selected':'' ?>>Doc Type</option>
          </select>
        </div>
      </div>
      <div class="search-field" style="max-width:120px;">
        <label>Order</label>
        <div class="search-input-wrap">
          <select name="order">
            <option value="desc" <?= $sort_order==='desc'?'selected':'' ?>>Newest</option>
            <option value="asc"  <?= $sort_order==='asc' ?'selected':'' ?>>Oldest</option>
          </select>
        </div>
      </div>
      <button type="submit" class="search-btn"><i class="fas fa-filter"></i> Filter</button>
      <?php if (!empty($search_query) || $sort_by !== 'upload_date' || $sort_order !== 'desc'): ?>
        <a href="?type=<?= urlencode($selected_doc) ?>" class="clear-btn" title="Clear filters">
          <i class="fas fa-times"></i>
        </a>
      <?php endif; ?>
    </div>
  </form>

  <!-- DOCUMENT GRID -->
  <div class="section-label">
    <?= $selected_doc === 'all' ? 'All Documents' : getDocTypeLabel($selected_doc) ?>
    <?php if (!empty($search_query)): ?>
      &mdash; results for "<?= htmlspecialchars($search_query) ?>"
    <?php endif; ?>
  </div>

  <div class="doc-grid">
    <?php if (!empty($documents)): ?>
      <?php foreach ($documents as $doc):
        $si     = getStatusInfo($doc['review_status']);
        $color  = getDocTypeColor($doc['document_type']);
        $exists = docExists($doc['file_name']);
      ?>
        <div class="doc-card">
          <div class="doc-card-head">
            <div class="doc-type-icon" style="background:<?= $color ?>22;color:<?= $color ?>;">
              <i class="fas <?= getDocTypeIcon($doc['document_type']) ?>"></i>
            </div>
            <div style="flex:1;min-width:0;">
              <div class="doc-type-name"><?= getDocTypeLabel($doc['document_type']) ?></div>
              <div class="doc-filename" title="<?= htmlspecialchars($doc['file_name']) ?>">
                <?= htmlspecialchars($doc['file_name']) ?>
              </div>
            </div>
            <div class="doc-status-pill" style="background:<?= $si['bg'] ?>;color:<?= $si['color'] ?>;">
              <i class="fas <?= $si['icon'] ?>"></i> <?= $si['label'] ?>
            </div>
          </div>

          <div class="doc-card-body">
            <div class="doc-meta-row">
              <i class="fas fa-calendar-alt"></i>
              Uploaded <?= date('F j, Y', strtotime($doc['upload_date'])) ?>
            </div>
            <?php if (!empty($doc['version']) && $doc['version'] > 1): ?>
              <div class="doc-meta-row"><i class="fas fa-code-branch"></i> Version <?= $doc['version'] ?></div>
            <?php endif; ?>

            <?php if (!empty($doc['feedback'])): ?>
              <div class="feedback-box">
                <div class="feedback-box-label"><i class="fas fa-comment-alt" style="margin-right:4px;"></i>Reviewer Feedback</div>
                <div class="feedback-box-text"><?= nl2br(htmlspecialchars($doc['feedback'])) ?></div>
                <?php if (!empty($doc['feedback_date'])): ?>
                  <div style="font-size:10.5px;color:var(--text-muted);margin-top:6px;"><?= date('M j, Y', strtotime($doc['feedback_date'])) ?></div>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($doc['corrections'])): ?>
              <?php foreach ($doc['corrections'] as $c): ?>
                <div class="correction-box">
                  <div class="correction-box-label">
                    <i class="fas fa-edit" style="margin-right:4px;"></i>
                    Corrected by <?= htmlspecialchars($c['afn'].' '.$c['aln']) ?>
                    <span style="font-weight:400;color:var(--text-muted);margin-left:6px;"><?= date('M j, Y', strtotime($c['created_at'])) ?></span>
                  </div>
                  <?php if (!empty($c['notes'])): ?>
                    <div style="font-size:12.5px;color:var(--text-secondary);margin-bottom:8px;"><?= nl2br(htmlspecialchars($c['notes'])) ?></div>
                  <?php endif; ?>
                  <?php if (docExists($c['corrected_file'])): ?>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                      <?php if (canPreview($c['corrected_file'])): ?>
                        <button class="doc-btn doc-btn-view view-btn"
                                data-file="uploads/documents/<?= htmlspecialchars($c['corrected_file']) ?>"
                                data-name="<?= htmlspecialchars($c['corrected_file']) ?>">
                          <i class="fas fa-eye"></i> View
                        </button>
                      <?php endif; ?>
                      <a href="uploads/documents/<?= htmlspecialchars($c['corrected_file']) ?>"
                         class="doc-btn doc-btn-dl" download>
                        <i class="fas fa-download"></i> Download
                      </a>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="doc-card-foot">
            <span class="doc-version-tag">v<?= $doc['version'] ?: '1' ?></span>
            <div class="doc-actions">
              <?php if ($exists): ?>
                <?php if (canPreview($doc['file_name'])): ?>
                  <button class="doc-btn doc-btn-view view-btn"
                          data-file="uploads/documents/<?= htmlspecialchars($doc['file_name']) ?>"
                          data-name="<?= htmlspecialchars($doc['file_name']) ?>">
                    <i class="fas fa-eye"></i> View
                  </button>
                <?php endif; ?>
                <a href="uploads/documents/<?= htmlspecialchars($doc['file_name']) ?>"
                   class="doc-btn doc-btn-dl" download>
                  <i class="fas fa-download"></i>
                </a>
                <?php if ($doc['review_status'] === 'needs_revision'): ?>
                  <button class="doc-btn doc-btn-revise revise-btn"
                          data-id="<?= $doc['id'] ?>"
                          data-type="<?= htmlspecialchars($doc['document_type']) ?>"
                          data-bs-toggle="modal" data-bs-target="#uploadRevisionModal">
                    <i class="fas fa-upload"></i> Revise
                  </button>
                <?php endif; ?>
              <?php else: ?>
                <span style="font-size:11.5px;color:#F59E0B;">
                  <i class="fas fa-exclamation-triangle"></i> File missing — please re-upload
                </span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-icon"><i class="fas fa-inbox"></i></div>
        <div class="empty-title">
          <?= !empty($search_query) ? 'No results found' : ($selected_doc !== 'all' ? 'No '.getDocTypeLabel($selected_doc).' uploaded yet' : 'Your vault is empty') ?>
        </div>
        <div class="empty-sub">
          <?= !empty($search_query) ? 'Try different search terms or clear the filter.' : 'Upload your first document to get started.' ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- UPLOAD CARD -->
    <div class="upload-card" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
      <div class="upload-card-icon"><i class="fas fa-plus"></i></div>
      <div class="upload-card-title">Add a Document</div>
      <div class="upload-card-sub">Click to upload a new file to your vault</div>
    </div>
  </div>

</main>

<!-- FOOTER -->
<footer class="portal-footer">
  <div class="footer-copy">&copy; <?= date('Y') ?> Bold Footprint Initiatives. All rights reserved.</div>
  <div class="footer-links">
    <a href="/"><i class="fas fa-home" style="font-size:10px;margin-right:4px;"></i>Main Site</a>
    <a href="/about.html">About Us</a>
    <a href="/programs.html">Programs</a>
    <a href="/contact.html">Contact</a>
  </div>
</footer>

<!-- UPLOAD MODAL -->
<div class="modal fade" id="uploadDocumentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Upload <em>New Document</em></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form action="upload-handler.php" method="post" enctype="multipart/form-data">
        <div class="modal-body">
          <div class="form-group">
            <label>Document Type</label>
            <select class="form-select" name="document_type" required>
              <option value="">Select a document type…</option>
              <option value="cv">CV / Resume</option>
              <option value="statement">Personal Statement</option>
              <option value="research">Research Proposal</option>
              <option value="recommendation">Recommendation Letter</option>
              <option value="language">Language Test Score</option>
              <option value="cold email">Cold Email</option>
            </select>
          </div>
          <div class="form-group">
            <label>File</label>
            <input type="file" class="form-control" name="document" required>
            <div class="form-text">Accepted formats: PDF, DOC, DOCX (max 10 MB)</div>
          </div>
          <div class="form-group">
            <label>Notes <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
            <textarea class="form-control" name="notes" rows="3"
                      placeholder="Any context or notes for the reviewer…"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-modal-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-modal-primary"><i class="fas fa-upload" style="margin-right:6px;"></i>Upload</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- REVISION MODAL -->
<div class="modal fade" id="uploadRevisionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Upload <em>Revised Document</em></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form action="upload-revised-document.php" method="post" enctype="multipart/form-data">
        <div class="modal-body">
          <div class="form-group">
            <label>Revised File</label>
            <input type="file" class="form-control" name="document" required>
            <div class="form-text">Accepted formats: PDF, DOC, DOCX (max 10 MB)</div>
          </div>
          <div class="form-group">
            <label>What did you change?</label>
            <textarea class="form-control" name="notes" rows="3"
                      placeholder="Briefly describe the revisions you made based on the feedback…"></textarea>
          </div>
          <input type="hidden" id="revise_original_id" name="original_id">
          <input type="hidden" id="revise_doc_type" name="document_type">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-modal-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-modal-primary"><i class="fas fa-paper-plane" style="margin-right:6px;"></i>Submit Revision</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- DOCUMENT VIEWER MODAL -->
<div class="modal fade" id="viewerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewerTitle">Document <em>Preview</em></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:0;background:var(--cream);">
        <div id="viewerContent" style="min-height:300px;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-modal-secondary" data-bs-dismiss="modal">Close</button>
        <a href="#" id="viewerDownload" class="btn-modal-primary" download>
          <i class="fas fa-download" style="margin-right:6px;"></i>Download
        </a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── Sidebar ── */
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebarOverlay');
const toggle  = document.getElementById('mobileToggle');
function openSidebar(){sidebar.classList.add('active');overlay.classList.add('active');document.body.style.overflow='hidden';}
function closeSidebar(){sidebar.classList.remove('active');overlay.classList.remove('active');document.body.style.overflow='';}
toggle.addEventListener('click',()=>sidebar.classList.contains('active')?closeSidebar():openSidebar());
overlay.addEventListener('click',closeSidebar);
window.addEventListener('resize',()=>{ if(window.innerWidth>768) closeSidebar(); });

/* ── Type chips: update hidden field and submit ── */
function setTypeAndSubmit(type) {
  document.getElementById('docTypeHidden').value = type;
  document.getElementById('filterForm').submit();
}
document.querySelectorAll('.type-chip').forEach(chip => {
  chip.addEventListener('click', () => setTypeAndSubmit(chip.dataset.type));
});

/* ── Revise button: populate modal hidden fields ── */
document.querySelectorAll('.revise-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('revise_original_id').value = btn.dataset.id;
    document.getElementById('revise_doc_type').value    = btn.dataset.type;
  });
});

/* ── Document viewer ── */
document.querySelectorAll('.view-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const file = btn.dataset.file;
    const name = btn.dataset.name;
    const ext  = name.split('.').pop().toLowerCase();

    document.getElementById('viewerTitle').innerHTML = 'Document <em>Preview</em>';
    const dl = document.getElementById('viewerDownload');
    dl.href     = file;
    dl.download = name;

    const content = document.getElementById('viewerContent');
    if (ext === 'pdf') {
      content.innerHTML = `<iframe src="${file}" class="document-viewer"></iframe>`;
    } else if (['jpg','jpeg','png','gif'].includes(ext)) {
      content.innerHTML = `<div style="padding:20px;text-align:center;"><img src="${file}" style="max-width:100%;border-radius:var(--r-md);"></div>`;
    } else if (ext === 'txt') {
      fetch(file)
        .then(r => r.text())
        .then(t => {
          content.innerHTML = `<pre style="padding:24px;white-space:pre-wrap;font-family:var(--font-body);font-size:13px;max-height:70vh;overflow-y:auto;">${t.replace(/</g,'&lt;')}</pre>`;
        })
        .catch(() => showFallback(content, name, ext));
    } else {
      showFallback(content, name, ext);
    }

    new bootstrap.Modal(document.getElementById('viewerModal')).show();
  });
});

function showFallback(el, name, ext) {
  el.innerHTML = `<div class="file-preview-fallback"><i class="fas fa-file"></i><h5>${name}</h5><p>Preview not available for .${ext} files.</p><p>Use the Download button below.</p></div>`;
}
</script>
</body>
</html>