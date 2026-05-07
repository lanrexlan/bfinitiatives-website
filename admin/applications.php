<?php
session_start();
error_log("Applications page accessed - Session data: " . print_r($_SESSION, true));

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    error_log("Access denied - not logged in");
    $_SESSION['error'] = "Please log in to access the dashboard";
    header('Location: admin-login.php');
    exit();
}

require_once 'includes/config.php';
require_once 'includes/db.php';

// ── Init variables ──────────────────────────────────────────────────────────
$scholars        = [];
$error_message   = '';
$success_message = '';
$filter_status   = $_GET['status']   ?? 'all';
$search_term     = $_GET['search']   ?? '';
$current_page    = max(1, intval($_GET['page'] ?? 1));
$items_per_page  = intval($_GET['per_page'] ?? 15);
$total_items     = 0;
$total_pages     = 1;
$offset          = 0;
$stats           = [
    'total'=>0,'pending'=>0,'under_review'=>0,
    'shortlisted'=>0,'approved'=>0,'rejected'=>0
];

try {
    $db   = new Database();
    $conn = $db->getConnection();

    // ── Per-status counts ──────────────────────────────────────────────────
    $cnt_stmt = $conn->query("
        SELECT status, COUNT(*) as cnt
        FROM scholarship_applications
        GROUP BY status
    ");
    $cnt_rows = $cnt_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cnt_rows as $row) {
        $key = strtolower($row['status'] ?? '');
        if (isset($stats[$key])) $stats[$key] = intval($row['cnt']);
        $stats['total'] += intval($row['cnt']);
    }
    $pending_count = $stats['pending'];

    // ── Build filtered query ───────────────────────────────────────────────
    $where  = "WHERE 1=1";
    $params = [];

    if ($filter_status !== 'all') {
        $where .= " AND s.status = :status";
        $params[':status'] = $filter_status;
    }
    if (!empty($search_term)) {
        $where .= " AND (
            s.first_name ILIKE :search OR
            s.last_name  ILIKE :search OR
            s.email      ILIKE :search OR
            s.application_id::TEXT ILIKE :search OR
            s.undergraduate_institution ILIKE :search OR
            s.program_type ILIKE :search
        )";
        $params[':search'] = '%' . $search_term . '%';
    }

    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM scholarship_applications s $where");
    $count_stmt->execute($params);
    $total_items = intval($count_stmt->fetch(PDO::FETCH_ASSOC)['total']);
    $total_pages = max(1, ceil($total_items / $items_per_page));
    $current_page = min($current_page, $total_pages);
    $offset = ($current_page - 1) * $items_per_page;

    $base_query = "
        SELECT
            s.application_id, s.first_name, s.last_name, s.email,
            s.program_type, s.status, s.created_at, s.updated_at,
            s.undergraduate_institution, s.degree_class,
            s.admin_comments
        FROM scholarship_applications s
        $where
        ORDER BY s.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $conn->prepare($base_query);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit',  $items_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,         PDO::PARAM_INT);
    $stmt->execute();
    $scholars = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Applications error: " . $e->getMessage());
    $error_message = "Database error: " . $e->getMessage();
}

// ── Status update via POST ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        $applicationId = $_POST['application_id'];
        $newStatus     = $_POST['new_status'];
        $comments      = $_POST['admin_comments'] ?? '';

        $u = $conn->prepare("
            UPDATE scholarship_applications
            SET status = :status, admin_comments = :comments, updated_at = CURRENT_TIMESTAMP
            WHERE application_id = :app_id
        ");
        if ($u->execute([':status'=>$newStatus,':comments'=>$comments,':app_id'=>$applicationId])) {
            $success_message = "Application status updated to <strong>" . htmlspecialchars(ucwords(str_replace('_',' ',$newStatus))) . "</strong> successfully.";
            try {
                $sel = $conn->prepare("SELECT first_name, email FROM scholarship_applications WHERE application_id = :app_id");
                $sel->execute([':app_id'=>$applicationId]);
                $sch = $sel->fetch(PDO::FETCH_ASSOC);
                if ($sch) {
                    require_once 'includes/mailer.php';
                    sendStatusUpdateEmail($sch['email'], $sch['first_name'], $applicationId, $newStatus, $comments);
                }
            } catch (Exception $ignored) {}
        } else {
            $error_message = "Failed to update application status.";
        }
    } catch (Exception $e) {
        error_log("Status update error: " . $e->getMessage());
        $error_message = "Error updating status: " . $e->getMessage();
    }
}

// ── Helper functions ──────────────────────────────────────────────────────
function fmtDate($d)  { return $d ? date('d M Y', strtotime($d)) : '—'; }
function fmtDateShort($d) { return $d ? date('d M', strtotime($d)) : '—'; }
function fmtNumber($n){ if($n>1000000) return round($n/1000000,1).'M'; if($n>1000) return round($n/1000,1).'K'; return $n; }
function statusBadgeClass($s) {
    switch(strtolower((string)$s)) {
        case 'pending':      return 'badge-pending';
        case 'under_review': return 'badge-review';
        case 'shortlisted':  return 'badge-shortlisted';
        case 'approved':     return 'badge-approved';
        case 'rejected':     return 'badge-rejected';
        default:             return 'badge-secondary';
    }
}
function statusIcon($s) {
    switch(strtolower((string)$s)) {
        case 'pending':      return 'clock';
        case 'under_review': return 'search';
        case 'shortlisted':  return 'star';
        case 'approved':     return 'check-circle';
        case 'rejected':     return 'times-circle';
        default:             return 'circle';
    }
}
function statusLabel($s) { return ucwords(str_replace('_',' ',strtolower((string)$s))); }

$admin_full_name  = $_SESSION['admin_name'] ?? 'Admin';
$admin_first_name = explode(' ', $admin_full_name)[0];
$admin_role       = ucfirst($_SESSION['admin_role'] ?? 'Administrator');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Applications | BFI Admin Portal</title>
  <link rel="icon" type="image/png" sizes="32x32" href="/Images/bfi-new-logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
  <style>
    /* ── DESIGN TOKENS (matches admin-dashboard) ── */
    :root{
      --midnight:#080E1C;--navy:#0D1829;--navy-mid:#132038;--navy-light:#1C2F52;
      --gold:#C8A058;--gold-bright:#E0B96C;--gold-pale:#F0D9A8;
      --cream:#FAF6EF;--cream-dark:#F2EAD8;--white:#FFFFFF;
      --text-primary:#0D1829;--text-secondary:#4A526A;--text-muted:#8A92A8;
      --border-light:#E8E4DA;
      --admin-crimson:#9F1239;--admin-crimson-light:#BE123C;--admin-crimson-pale:rgba(159,18,57,0.10);
      --success:#059669;--success-pale:rgba(5,150,105,0.10);
      --warning:#D97706;--warning-pale:rgba(217,119,6,0.10);
      --danger:#DC2626;--danger-pale:rgba(220,38,38,0.10);
      --info:#0284C7;--info-pale:rgba(2,132,199,0.10);
      --purple:#7C3AED;--purple-pale:rgba(124,58,237,0.10);
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
    html{font-size:16px;scroll-behavior:smooth;}
    body{font-family:var(--font-body);background:#F0F2F7;color:var(--text-primary);line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden;}
    a{text-decoration:none;color:inherit;}
    img{max-width:100%;display:block;}
    ::-webkit-scrollbar{width:6px;height:6px;}
    ::-webkit-scrollbar-track{background:#F0F2F7;}
    ::-webkit-scrollbar-thumb{background:var(--border-light);border-radius:3px;}
    ::-webkit-scrollbar-thumb:hover{background:var(--text-muted);}

    /* ── SIDEBAR ── */
    .sidebar{position:fixed;left:0;top:0;width:var(--sidebar-width);height:100vh;background:var(--navy);z-index:200;display:flex;flex-direction:column;overflow:hidden;transition:transform var(--transition);}
    .sidebar-top{padding:24px 20px 18px;border-bottom:1px solid rgba(255,255,255,0.06);}
    .sidebar-logo{display:flex;align-items:center;gap:11px;margin-bottom:28px;}
    .sidebar-logomark{width:32px;height:32px;background:var(--gold);border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .sidebar-logomark svg{width:18px;height:18px;}
    .sidebar-logo-text{font-family:var(--font-display);font-size:14.5px;font-weight:500;color:var(--white);line-height:1.2;}
    .sidebar-logo-text span{display:block;font-family:var(--font-body);font-size:8.5px;letter-spacing:2px;text-transform:uppercase;color:rgba(200,160,88,0.55);}
    .admin-chip{display:inline-flex;align-items:center;gap:5px;background:var(--admin-crimson);color:white;font-size:8.5px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:3px 9px;border-radius:20px;margin-left:6px;flex-shrink:0;}
    .sidebar-user{display:flex;align-items:center;gap:11px;}
    .sidebar-avatar{width:38px;height:38px;border-radius:50%;background:var(--navy-light);border:2px solid rgba(200,160,88,0.25);overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .sidebar-avatar-init{font-family:var(--font-display);font-size:15px;font-weight:500;color:var(--gold-bright);}
    .sidebar-user-name{font-size:13px;font-weight:500;color:var(--white);line-height:1.3;}
    .sidebar-user-role{font-size:10px;color:rgba(255,255,255,0.3);letter-spacing:0.5px;text-transform:uppercase;}
    .sidebar-nav{flex:1;padding:18px 10px;overflow-y:auto;}
    .nav-section-label{font-size:9px;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;color:rgba(255,255,255,0.18);padding:0 12px;margin:18px 0 7px;}
    .nav-item{margin-bottom:2px;}
    .nav-link{display:flex;align-items:center;gap:11px;padding:10px 13px;border-radius:var(--r-sm);font-size:13px;font-weight:400;color:rgba(255,255,255,0.55);transition:var(--transition);position:relative;}
    .nav-link:hover{background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.9);}
    .nav-link.active{background:rgba(200,160,88,0.11);color:var(--gold-bright);}
    .nav-link.active::before{content:'';position:absolute;left:0;top:6px;bottom:6px;width:2.5px;background:var(--gold);border-radius:2px;}
    .nav-link i{width:17px;text-align:center;font-size:13.5px;flex-shrink:0;}
    .nav-badge{margin-left:auto;background:rgba(220,38,38,0.2);color:#F87171;font-size:9.5px;font-weight:600;padding:2px 7px;border-radius:10px;}
    .sidebar-bottom{padding:14px 10px;border-top:1px solid rgba(255,255,255,0.06);}
    .nav-logout{color:rgba(239,68,68,0.65)!important;}
    .nav-logout:hover{background:rgba(239,68,68,0.08)!important;color:rgba(239,68,68,0.9)!important;}

    /* ── HEADER ── */
    .header{position:fixed;top:0;left:var(--sidebar-width);right:0;height:var(--header-height);background:var(--white);border-bottom:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;padding:0 26px;z-index:100;}
    .header-left{display:flex;align-items:center;gap:14px;}
    .mobile-toggle{display:none;background:none;border:none;cursor:pointer;padding:6px;color:var(--text-secondary);font-size:18px;}
    .header-breadcrumb{font-size:13px;color:var(--text-muted);display:flex;align-items:center;gap:7px;}
    .header-breadcrumb strong{color:var(--text-primary);font-weight:600;}
    .header-breadcrumb .sep{color:var(--border-light);}
    .header-right{display:flex;align-items:center;gap:12px;}
    .header-time{font-size:12px;color:var(--text-muted);padding-right:12px;border-right:1px solid var(--border-light);}
    .header-icon-btn{width:36px;height:36px;border-radius:var(--r-sm);background:var(--cream);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-secondary);font-size:14px;transition:var(--transition);position:relative;}
    .header-icon-btn:hover{background:var(--cream-dark);color:var(--navy);}
    .notif-dot{position:absolute;top:6px;right:6px;width:7px;height:7px;border-radius:50%;background:var(--admin-crimson);border:1.5px solid var(--white);}
    .header-avatar-wrap{display:flex;align-items:center;gap:9px;cursor:pointer;padding:6px 10px;border-radius:var(--r-sm);transition:var(--transition);}
    .header-avatar-wrap:hover{background:var(--cream);}
    .header-avatar{width:32px;height:32px;border-radius:50%;background:var(--navy-light);overflow:hidden;border:2px solid var(--border-light);display:flex;align-items:center;justify-content:center;}
    .header-avatar-init{font-family:var(--font-display);font-size:13px;color:var(--gold-bright);}
    .header-admin-label{font-size:12px;font-weight:500;color:var(--text-primary);}
    .header-admin-role{font-size:10.5px;color:var(--text-muted);}

    /* ── MAIN ── */
    .main{margin-left:var(--sidebar-width);margin-top:var(--header-height);padding:26px;min-height:calc(100vh - var(--header-height));}

    /* ── PAGE HEADER BANNER ── */
    .page-banner{background:var(--navy);border-radius:var(--r-xl);padding:28px 32px;margin-bottom:22px;position:relative;overflow:hidden;}
    .page-banner::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.025) 1px,transparent 1px);background-size:30px 30px;}
    .page-banner::after{content:'';position:absolute;top:-50px;right:-50px;width:280px;height:280px;background:radial-gradient(circle,rgba(200,160,88,0.09) 0%,transparent 65%);}
    .pb-inner{position:relative;z-index:1;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;}
    .pb-eyebrow{font-size:9.5px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:rgba(200,160,88,0.65);margin-bottom:5px;}
    .pb-title{font-family:var(--font-display);font-size:clamp(22px,2.8vw,30px);font-weight:500;color:var(--white);line-height:1.15;margin-bottom:4px;}
    .pb-title em{font-style:italic;color:var(--gold-bright);}
    .pb-sub{font-size:13px;font-weight:300;color:rgba(255,255,255,0.45);}
    .pb-actions{display:flex;gap:10px;flex-wrap:wrap;}
    .btn-gold{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:var(--gold);color:var(--midnight);font-family:var(--font-body);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-gold:hover{background:var(--gold-bright);transform:translateY(-1px);}
    .btn-ghost-sm{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:rgba(255,255,255,0.07);color:rgba(255,255,255,0.75);font-family:var(--font-body);font-size:13px;font-weight:400;border:1px solid rgba(255,255,255,0.1);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-ghost-sm:hover{background:rgba(255,255,255,0.12);color:var(--white);}

    /* ── STATS PIPELINE ── */
    .pipeline{display:grid;grid-template-columns:repeat(6,1fr);gap:14px;margin-bottom:22px;}
    .pipe-card{background:var(--white);border-radius:var(--r-lg);padding:16px 18px;border:2px solid var(--border-light);cursor:pointer;transition:var(--transition);position:relative;overflow:hidden;}
    .pipe-card:hover,.pipe-card.active{transform:translateY(-3px);box-shadow:var(--shadow-md);}
    .pipe-card.active{border-color:currentColor;}
    .pipe-card-icon{width:36px;height:36px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:14px;margin-bottom:10px;}
    .pipe-label{font-size:10.5px;font-weight:500;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:4px;}
    .pipe-count{font-family:var(--font-display);font-size:28px;font-weight:500;line-height:1;}
    .pipe-bar{position:absolute;bottom:0;left:0;height:3px;border-radius:0 0 var(--r-lg) var(--r-lg);width:100%;transition:opacity var(--transition);}
    /* All */
    .pipe-all .pipe-card-icon{background:var(--cream);color:var(--navy);}
    .pipe-all .pipe-count{color:var(--navy);}
    .pipe-all .pipe-bar{background:var(--navy);}
    .pipe-all.active{border-color:var(--navy);}
    /* Pending */
    .pipe-pending .pipe-card-icon{background:var(--warning-pale);color:var(--warning);}
    .pipe-pending .pipe-count{color:var(--warning);}
    .pipe-pending .pipe-bar{background:var(--warning);}
    .pipe-pending.active{border-color:var(--warning);}
    /* Review */
    .pipe-review .pipe-card-icon{background:var(--info-pale);color:var(--info);}
    .pipe-review .pipe-count{color:var(--info);}
    .pipe-review .pipe-bar{background:var(--info);}
    .pipe-review.active{border-color:var(--info);}
    /* Shortlisted */
    .pipe-shortlisted .pipe-card-icon{background:var(--purple-pale);color:var(--purple);}
    .pipe-shortlisted .pipe-count{color:var(--purple);}
    .pipe-shortlisted .pipe-bar{background:var(--purple);}
    .pipe-shortlisted.active{border-color:var(--purple);}
    /* Approved */
    .pipe-approved .pipe-card-icon{background:var(--success-pale);color:var(--success);}
    .pipe-approved .pipe-count{color:var(--success);}
    .pipe-approved .pipe-bar{background:var(--success);}
    .pipe-approved.active{border-color:var(--success);}
    /* Rejected */
    .pipe-rejected .pipe-card-icon{background:var(--danger-pale);color:var(--danger);}
    .pipe-rejected .pipe-count{color:var(--danger);}
    .pipe-rejected .pipe-bar{background:var(--danger);}
    .pipe-rejected.active{border-color:var(--danger);}

    /* ── FILTER BAR ── */
    .filter-bar{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:16px 20px;margin-bottom:18px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
    .search-wrap{flex:1;min-width:200px;position:relative;}
    .search-icon-inner{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;}
    .search-inp{width:100%;padding:9px 12px 9px 36px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13.5px;color:var(--text-primary);background:var(--cream);transition:border-color var(--transition);}
    .search-inp:focus{outline:none;border-color:var(--gold);background:var(--white);box-shadow:0 0 0 3px rgba(200,160,88,0.1);}
    .filter-select{padding:9px 12px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13.5px;color:var(--text-primary);background:var(--cream);cursor:pointer;transition:border-color var(--transition);}
    .filter-select:focus{outline:none;border-color:var(--gold);}
    .filter-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:var(--navy);color:var(--white);font-family:var(--font-body);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .filter-btn:hover{background:var(--navy-light);}
    .filter-reset{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;background:var(--cream);color:var(--text-secondary);font-family:var(--font-body);font-size:13px;border:1px solid var(--border-light);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .filter-reset:hover{background:var(--cream-dark);}

    /* ── BULK ACTION BAR ── */
    .bulk-bar{background:var(--navy);border-radius:var(--r-md);padding:12px 20px;margin-bottom:14px;display:none;align-items:center;gap:14px;flex-wrap:wrap;}
    .bulk-bar.visible{display:flex;}
    .bulk-info{font-size:13px;font-weight:500;color:var(--white);flex:1;}
    .bulk-info span{color:var(--gold-bright);font-weight:600;}
    .btn-bulk{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;font-family:var(--font-body);font-size:12.5px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-bulk-approve{background:var(--success-pale);color:var(--success);}.btn-bulk-approve:hover{background:var(--success);color:var(--white);}
    .btn-bulk-reject{background:var(--danger-pale);color:var(--danger);}.btn-bulk-reject:hover{background:var(--danger);color:var(--white);}
    .btn-bulk-review{background:var(--info-pale);color:var(--info);}.btn-bulk-review:hover{background:var(--info);color:var(--white);}
    .btn-bulk-export{background:rgba(200,160,88,0.12);color:var(--gold-bright);}.btn-bulk-export:hover{background:var(--gold);color:var(--midnight);}
    .btn-bulk-clear{background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.6);}.btn-bulk-clear:hover{background:rgba(255,255,255,0.12);color:var(--white);}

    /* ── TABLE CARD ── */
    .table-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);overflow:hidden;margin-bottom:20px;}
    .table-card-header{padding:18px 22px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border-light);flex-wrap:wrap;gap:12px;}
    .tc-title{font-family:var(--font-display);font-size:19px;font-weight:500;color:var(--navy);}
    .tc-title em{font-style:italic;color:var(--gold);}
    .tc-subtitle{font-size:12px;color:var(--text-muted);margin-top:1px;}
    .tc-actions{display:flex;gap:8px;align-items:center;}
    .tc-btn{width:34px;height:34px;border-radius:var(--r-sm);background:var(--cream);border:1px solid var(--border-light);cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:13px;transition:var(--transition);}
    .tc-btn:hover{background:var(--navy);color:var(--gold-bright);border-color:transparent;}
    .per-page-sel{padding:7px 10px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:12.5px;color:var(--text-primary);background:var(--cream);cursor:pointer;}

    /* ── DATA TABLE ── */
    .data-table{width:100%;border-collapse:separate;border-spacing:0;}
    .data-table th{background:#F8F9FB;color:var(--text-muted);font-weight:600;font-size:10.5px;letter-spacing:0.8px;text-transform:uppercase;padding:12px 16px;text-align:left;border-bottom:1px solid var(--border-light);white-space:nowrap;}
    .data-table th.sortable{cursor:pointer;user-select:none;}
    .data-table th.sortable:hover{color:var(--navy);}
    .data-table th .sort-icon{font-size:9px;margin-left:4px;opacity:0.4;}
    .data-table td{padding:13px 16px;vertical-align:middle;border-bottom:1px solid var(--border-light);color:var(--text-primary);font-size:13.5px;}
    .data-table tbody tr:last-child td{border-bottom:none;}
    .data-table tbody tr{transition:background var(--transition);cursor:pointer;}
    .data-table tbody tr:hover{background:#F8F9FB;}
    .data-table tbody tr.selected{background:rgba(200,160,88,0.05);}
    /* Checkbox */
    .row-check{width:16px;height:16px;accent-color:var(--navy);cursor:pointer;}
    .th-check{width:44px;}
    /* User cell */
    .user-cell{display:flex;align-items:center;gap:10px;}
    .app-avatar{width:36px;height:36px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-weight:600;color:var(--white);font-size:15px;flex-shrink:0;}
    .user-name{font-weight:500;font-size:13.5px;color:var(--text-primary);margin-bottom:1px;}
    .user-email{font-size:11.5px;color:var(--text-muted);}
    .app-id{font-size:11px;color:var(--text-muted);font-family:monospace;background:var(--cream);padding:1px 6px;border-radius:4px;display:inline-block;margin-top:2px;}
    /* Truncated */
    .truncate{max-width:140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;}
    /* Badges */
    .badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:500;}
    .badge-pending{background:var(--warning-pale);color:var(--warning);}
    .badge-review{background:var(--info-pale);color:var(--info);}
    .badge-shortlisted{background:var(--purple-pale);color:var(--purple);}
    .badge-approved{background:var(--success-pale);color:var(--success);}
    .badge-rejected{background:var(--danger-pale);color:var(--danger);}
    .badge-secondary{background:var(--cream);color:var(--text-muted);}
    /* Action buttons */
    .action-btns{display:flex;gap:6px;align-items:center;}
    .btn-sm{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;font-family:var(--font-body);font-size:12px;font-weight:500;border-radius:var(--r-sm);border:none;cursor:pointer;transition:var(--transition);}
    .btn-sm:hover{transform:translateY(-1px);}
    .btn-update{background:var(--admin-crimson-pale);color:var(--admin-crimson);}.btn-update:hover{background:var(--admin-crimson);color:var(--white);}
    .btn-view{background:var(--cream);color:var(--text-secondary);border:1px solid var(--border-light);}.btn-view:hover{background:var(--navy);color:var(--gold-bright);border-color:transparent;}
    .btn-approve-quick{background:var(--success-pale);color:var(--success);}.btn-approve-quick:hover{background:var(--success);color:var(--white);}
    /* Empty state */
    .empty-state{text-align:center;padding:60px 24px;}
    .empty-icon{font-size:40px;color:var(--border-light);margin-bottom:16px;}
    .empty-title{font-family:var(--font-display);font-size:22px;color:var(--navy);margin-bottom:8px;}
    .empty-sub{font-size:13.5px;color:var(--text-muted);}

    /* ── TABLE FOOTER / PAGINATION ── */
    .table-card-footer{padding:14px 22px;display:flex;justify-content:space-between;align-items:center;border-top:1px solid var(--border-light);background:#FAFBFC;flex-wrap:wrap;gap:12px;}
    .tf-info{font-size:12.5px;color:var(--text-muted);}
    .tf-info strong{color:var(--navy);}
    .pagination{display:flex;gap:4px;align-items:center;}
    .page-btn{min-width:34px;height:34px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-family:var(--font-body);font-size:13px;font-weight:500;border:1px solid var(--border-light);background:var(--white);color:var(--text-secondary);cursor:pointer;transition:var(--transition);}
    .page-btn:hover{background:var(--cream-dark);color:var(--navy);}
    .page-btn.active{background:var(--navy);color:var(--white);border-color:var(--navy);}
    .page-btn.disabled{opacity:0.4;cursor:not-allowed;pointer-events:none;}
    .page-ellipsis{padding:0 4px;color:var(--text-muted);font-size:13px;}

    /* ── DETAIL DRAWER ── */
    .drawer-overlay{display:none;position:fixed;inset:0;background:rgba(8,14,28,0.4);z-index:300;backdrop-filter:blur(4px);}
    .drawer-overlay.open{display:block;}
    .drawer{position:fixed;top:0;right:0;width:480px;max-width:95vw;height:100vh;background:var(--white);z-index:301;display:flex;flex-direction:column;transform:translateX(100%);transition:transform 0.4s var(--ease);overflow:hidden;}
    .drawer.open{transform:translateX(0);}
    .drawer-header{padding:20px 24px;border-bottom:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;background:var(--navy);flex-shrink:0;}
    .drawer-title{font-family:var(--font-display);font-size:20px;font-weight:500;color:var(--white);}
    .drawer-title em{font-style:italic;color:var(--gold-bright);}
    .drawer-close{width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,0.08);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;color:rgba(255,255,255,0.6);transition:var(--transition);}
    .drawer-close:hover{background:rgba(255,255,255,0.15);color:var(--white);}
    .drawer-body{flex:1;overflow-y:auto;padding:24px;}
    .drawer-section{margin-bottom:24px;}
    .drawer-section-label{font-size:9.5px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid var(--border-light);}
    .drawer-avatar{width:64px;height:64px;border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-weight:600;color:var(--white);font-size:26px;margin-bottom:14px;}
    .drawer-name{font-family:var(--font-display);font-size:24px;font-weight:500;color:var(--navy);margin-bottom:3px;}
    .drawer-email{font-size:13.5px;color:var(--text-muted);margin-bottom:10px;}
    .drawer-field{display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid var(--border-light);}
    .drawer-field:last-child{border-bottom:none;}
    .drawer-field-icon{width:28px;height:28px;border-radius:6px;background:var(--cream);display:flex;align-items:center;justify-content:center;font-size:11px;color:var(--gold);flex-shrink:0;margin-top:1px;}
    .drawer-field-label{font-size:11px;color:var(--text-muted);margin-bottom:1px;}
    .drawer-field-value{font-size:13.5px;font-weight:500;color:var(--text-primary);}
    .drawer-feedback{background:rgba(200,160,88,0.05);border:1px solid rgba(200,160,88,0.15);border-radius:var(--r-md);padding:14px;}
    .drawer-feedback-label{font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--gold);margin-bottom:6px;}
    .drawer-feedback-text{font-size:13.5px;color:var(--text-secondary);line-height:1.65;}
    .drawer-footer{padding:18px 24px;border-top:1px solid var(--border-light);display:flex;gap:10px;flex-shrink:0;background:#FAFBFC;}
    .btn-drawer-update{flex:1;padding:12px;background:var(--admin-crimson);color:var(--white);font-family:var(--font-body);font-size:13.5px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-drawer-update:hover{background:var(--admin-crimson-light);}
    .btn-drawer-close{padding:12px 20px;background:var(--cream);color:var(--text-secondary);font-family:var(--font-body);font-size:13.5px;border:1px solid var(--border-light);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-drawer-close:hover{background:var(--cream-dark);}

    /* ── PORTAL FOOTER ── */
    .portal-footer{margin-left:var(--sidebar-width);background:var(--white);padding:14px 26px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;}
    .footer-copy{font-size:11.5px;color:var(--text-muted);}
    .footer-links{display:flex;gap:18px;}
    .footer-links a{font-size:11.5px;color:var(--text-muted);transition:color var(--transition);}.footer-links a:hover{color:var(--gold);}

    /* ── SIDEBAR OVERLAY ── */
    .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:150;backdrop-filter:blur(4px);}
    .sidebar-overlay.active{display:block;}

    @media(max-width:1200px){.pipeline{grid-template-columns:repeat(3,1fr)}}
    @media(max-width:1024px){.pipeline{grid-template-columns:repeat(3,1fr)}}
    @media(max-width:768px){
      .sidebar{transform:translateX(-100%)}.sidebar.active{transform:translateX(0)}
      .header{left:0}.main,.portal-footer{margin-left:0}
      .mobile-toggle{display:flex}.header-breadcrumb{display:none}
      .pipeline{grid-template-columns:repeat(2,1fr)}
      .filter-bar{flex-direction:column;align-items:stretch}
      .search-wrap{min-width:auto}
      .drawer{width:100vw}
    }
    @media(max-width:480px){.pipeline{grid-template-columns:1fr}.pb-actions{flex-direction:column}}
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
      <span class="admin-chip"><i class="fas fa-shield-alt" style="font-size:7px;"></i> Admin</span>
    </div>
    <div class="sidebar-user">
      <div class="sidebar-avatar"><div class="sidebar-avatar-init"><?php echo strtoupper(substr($admin_first_name,0,1)); ?></div></div>
      <div>
        <div class="sidebar-user-name"><?php echo htmlspecialchars($admin_full_name); ?></div>
        <div class="sidebar-user-role"><?php echo htmlspecialchars($admin_role); ?></div>
      </div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Command</div>
    <div class="nav-item"><a href="admin-dashboard.php" class="nav-link"><i class="fas fa-chart-pie"></i> Dashboard</a></div>
    <div class="nav-item"><a href="manage-scholars.php" class="nav-link"><i class="fas fa-user-graduate"></i> Scholars</a></div>
    <div class="nav-item"><a href="applications.php" class="nav-link active"><i class="fas fa-tasks"></i> Applications <?php if($pending_count>0): ?><span class="nav-badge"><?php echo $pending_count; ?></span><?php endif; ?></a></div>
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

<!-- HEADER -->
<header class="header">
  <div class="header-left">
    <button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
    <div class="header-breadcrumb">
      <a href="admin-dashboard.php">Admin</a><span class="sep">/</span><strong>Applications</strong>
    </div>
  </div>
  <div class="header-right">
    <div class="header-time" id="headerTime"></div>
    <a href="reports.php"><button class="header-icon-btn" title="Reports"><i class="fas fa-chart-bar"></i></button></a>
    <button class="header-icon-btn" title="Notifications">
      <i class="fas fa-bell"></i>
      <?php if($pending_count>0): ?><div class="notif-dot"></div><?php endif; ?>
    </button>
    <div class="header-avatar-wrap">
      <div class="header-avatar"><div class="header-avatar-init"><?php echo strtoupper(substr($admin_first_name,0,1)); ?></div></div>
      <div><div class="header-admin-label"><?php echo htmlspecialchars($admin_first_name); ?></div><div class="header-admin-role"><?php echo htmlspecialchars($admin_role); ?></div></div>
    </div>
  </div>
</header>

<!-- MAIN -->
<main class="main">

  <?php if ($error_message): ?>
  <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>
  <?php if ($success_message): ?>
  <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <!-- PAGE BANNER -->
  <div class="page-banner">
    <div class="pb-inner">
      <div>
        <div class="pb-eyebrow"><i class="fas fa-tasks" style="font-size:8px;"></i> Application Management</div>
        <div class="pb-title">Scholarship <em>Applications</em></div>
        <div class="pb-sub"><?php echo fmtNumber($stats['total']); ?> total applications · <?php echo $pending_count; ?> pending your review</div>
      </div>
      <div class="pb-actions">
        <button class="btn-gold" onclick="exportApplications()"><i class="fas fa-download"></i> Export CSV</button>
        <button class="btn-ghost-sm" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
        <a href="admin-dashboard.php" class="btn-ghost-sm"><i class="fas fa-arrow-left"></i> Dashboard</a>
      </div>
    </div>
  </div>

  <!-- PIPELINE STATS -->
  <?php
  $pipes = [
    ['key'=>'all',         'label'=>'All',          'count'=>$stats['total'],        'icon'=>'fa-th-large',    'class'=>'pipe-all'],
    ['key'=>'pending',     'label'=>'Pending',      'count'=>$stats['pending'],      'icon'=>'fa-clock',       'class'=>'pipe-pending'],
    ['key'=>'under_review','label'=>'Under Review', 'count'=>$stats['under_review'], 'icon'=>'fa-search',      'class'=>'pipe-review'],
    ['key'=>'shortlisted', 'label'=>'Shortlisted',  'count'=>$stats['shortlisted'],  'icon'=>'fa-star',        'class'=>'pipe-shortlisted'],
    ['key'=>'approved',    'label'=>'Approved',     'count'=>$stats['approved'],     'icon'=>'fa-check-circle','class'=>'pipe-approved'],
    ['key'=>'rejected',    'label'=>'Rejected',     'count'=>$stats['rejected'],     'icon'=>'fa-times-circle','class'=>'pipe-rejected'],
  ];
  ?>
  <div class="pipeline">
    <?php foreach ($pipes as $p): ?>
    <a href="?status=<?php echo $p['key']; ?>&search=<?php echo urlencode($search_term); ?>" style="text-decoration:none;">
      <div class="pipe-card <?php echo $p['class']; ?><?php echo $filter_status===$p['key']?' active':''; ?>">
        <div class="pipe-card-icon"><i class="fas <?php echo $p['icon']; ?>"></i></div>
        <div class="pipe-label"><?php echo $p['label']; ?></div>
        <div class="pipe-count"><?php echo fmtNumber($p['count']); ?></div>
        <div class="pipe-bar"></div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- FILTER BAR -->
  <form action="applications.php" method="GET" class="filter-bar" id="filterForm">
    <div class="search-wrap">
      <i class="fas fa-search search-icon-inner"></i>
      <input type="text" name="search" class="search-inp" placeholder="Search name, email, ID, institution, programme…" value="<?php echo htmlspecialchars($search_term); ?>">
    </div>
    <input type="hidden" name="status" id="filterStatusHidden" value="<?php echo htmlspecialchars($filter_status); ?>">
    <select name="per_page" class="filter-select" onchange="this.form.submit()">
      <?php foreach ([10,15,25,50] as $pp): ?>
      <option value="<?php echo $pp; ?>" <?php echo $items_per_page===$pp?'selected':''; ?>><?php echo $pp; ?> / page</option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="filter-btn"><i class="fas fa-search"></i> Search</button>
    <a href="applications.php" class="filter-reset"><i class="fas fa-times"></i> Reset</a>
  </form>

  <!-- BULK ACTION BAR -->
  <div class="bulk-bar" id="bulkBar">
    <div class="bulk-info"><span id="selectedCount">0</span> application(s) selected</div>
    <button class="btn-bulk btn-bulk-review"   onclick="bulkAction('under_review')"><i class="fas fa-search"></i> Move to Review</button>
    <button class="btn-bulk btn-bulk-approve"  onclick="bulkAction('approved')"><i class="fas fa-check"></i> Approve All</button>
    <button class="btn-bulk btn-bulk-reject"   onclick="bulkAction('rejected')"><i class="fas fa-times"></i> Reject All</button>
    <button class="btn-bulk btn-bulk-export"   onclick="exportSelected()"><i class="fas fa-download"></i> Export Selected</button>
    <button class="btn-bulk btn-bulk-clear"    onclick="clearSelection()"><i class="fas fa-times-circle"></i> Clear</button>
  </div>

  <!-- TABLE CARD -->
  <div class="table-card">
    <div class="table-card-header">
      <div>
        <div class="tc-title">Scholarship <em>Applications</em></div>
        <div class="tc-subtitle">
          Showing <?php echo $total_items>0?($offset+1):0; ?>–<?php echo min($offset+$items_per_page,$total_items); ?> of <?php echo fmtNumber($total_items); ?> applications
          <?php if($filter_status!=='all'): ?> · Filtered: <strong><?php echo statusLabel($filter_status); ?></strong><?php endif; ?>
        </div>
      </div>
      <div class="tc-actions">
        <button class="tc-btn" title="Refresh" onclick="location.reload()"><i class="fas fa-sync-alt"></i></button>
        <button class="tc-btn" title="Export CSV" onclick="exportApplications()"><i class="fas fa-download"></i></button>
        <button class="tc-btn" title="Print" onclick="window.print()"><i class="fas fa-print"></i></button>
      </div>
    </div>
    <div class="table-responsive">
      <table class="data-table" id="applicationsTable">
        <thead>
          <tr>
            <th class="th-check"><input type="checkbox" class="row-check" id="checkAll" title="Select all"></th>
            <th class="sortable" data-sort="id">ID <i class="fas fa-sort sort-icon"></i></th>
            <th class="sortable" data-sort="name">Applicant <i class="fas fa-sort sort-icon"></i></th>
            <th>Programme</th>
            <th>Institution</th>
            <th class="sortable" data-sort="status">Status <i class="fas fa-sort sort-icon"></i></th>
            <th class="sortable" data-sort="date">Applied <i class="fas fa-sort sort-icon"></i></th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($scholars)): ?>
            <?php
            $avatarColors = ['#0D1829','#1C2F52','#9F1239','#059669','#0284C7','#7C3AED','#D97706'];
            foreach ($scholars as $i => $s):
              $color = $avatarColors[$i % count($avatarColors)];
            ?>
            <tr class="app-row"
                data-app-id="<?php echo htmlspecialchars($s['application_id']); ?>"
                data-first="<?php echo htmlspecialchars($s['first_name']); ?>"
                data-last="<?php echo htmlspecialchars($s['last_name']); ?>"
                data-email="<?php echo htmlspecialchars($s['email']); ?>"
                data-program="<?php echo htmlspecialchars($s['program_type']??''); ?>"
                data-institution="<?php echo htmlspecialchars($s['undergraduate_institution']??''); ?>"
                data-degree="<?php echo htmlspecialchars($s['degree_class']??''); ?>"
                data-status="<?php echo htmlspecialchars($s['status']??''); ?>"
                data-date="<?php echo htmlspecialchars($s['created_at']??''); ?>"
                data-updated="<?php echo htmlspecialchars($s['updated_at']??''); ?>"
                data-comments="<?php echo htmlspecialchars($s['admin_comments']??''); ?>"
                data-color="<?php echo $color; ?>">
              <td onclick="event.stopPropagation()"><input type="checkbox" class="row-check row-checkbox" value="<?php echo htmlspecialchars($s['application_id']); ?>"></td>
              <td><code style="font-size:11px;background:var(--cream);padding:2px 6px;border-radius:4px;color:var(--text-secondary);"><?php echo htmlspecialchars($s['application_id']); ?></code></td>
              <td>
                <div class="user-cell">
                  <div class="app-avatar" style="background:<?php echo $color; ?>;"><?php echo strtoupper(substr($s['first_name'],0,1)); ?></div>
                  <div>
                    <div class="user-name"><?php echo htmlspecialchars($s['first_name'].' '.$s['last_name']); ?></div>
                    <div class="user-email"><?php echo htmlspecialchars($s['email']); ?></div>
                  </div>
                </div>
              </td>
              <td><span class="truncate" title="<?php echo htmlspecialchars($s['program_type']??''); ?>"><?php echo htmlspecialchars($s['program_type']??'—'); ?></span></td>
              <td><span class="truncate" title="<?php echo htmlspecialchars($s['undergraduate_institution']??''); ?>"><?php echo htmlspecialchars($s['undergraduate_institution']??'—'); ?></span></td>
              <td>
                <span class="badge <?php echo statusBadgeClass($s['status']); ?>">
                  <i class="fas fa-<?php echo statusIcon($s['status']); ?>" style="font-size:9px;"></i>
                  <?php echo statusLabel($s['status']); ?>
                </span>
              </td>
              <td style="font-size:12.5px;color:var(--text-muted);white-space:nowrap;"><?php echo fmtDate($s['created_at']); ?></td>
              <td onclick="event.stopPropagation()">
                <div class="action-btns">
                  <button class="btn-sm btn-update status-update-btn"
                    data-application-id="<?php echo htmlspecialchars($s['application_id']); ?>"
                    data-current-status="<?php echo htmlspecialchars($s['status']??''); ?>"
                    data-comments="<?php echo htmlspecialchars($s['admin_comments']??''); ?>"
                    title="Update status">
                    <i class="fas fa-edit"></i> Update
                  </button>
                  <?php if(strtolower($s['status']??'')!=='approved'): ?>
                  <button class="btn-sm btn-approve-quick"
                    onclick="quickApprove('<?php echo htmlspecialchars($s['application_id']); ?>')"
                    title="Quick approve">
                    <i class="fas fa-check"></i>
                  </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
          <tr>
            <td colspan="8">
              <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-inbox"></i></div>
                <div class="empty-title">No applications found</div>
                <div class="empty-sub">
                  <?php if(!empty($search_term)||$filter_status!=='all'): ?>
                  No applications match your current filters. Try adjusting your search or resetting the filter.
                  <?php else: ?>
                  No scholarship applications have been received yet.
                  <?php endif; ?>
                </div>
              </div>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($total_pages > 1 || $total_items > 0): ?>
    <div class="table-card-footer">
      <div class="tf-info">
        <strong><?php echo fmtNumber($total_items); ?></strong> application<?php echo $total_items!==1?'s':''; ?>
        · Page <strong><?php echo $current_page; ?></strong> of <strong><?php echo $total_pages; ?></strong>
      </div>
      <div class="pagination">
        <a href="?page=<?php echo max(1,$current_page-1); ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search_term); ?>&per_page=<?php echo $items_per_page; ?>" class="page-btn <?php echo $current_page<=1?'disabled':''; ?>"><i class="fas fa-chevron-left" style="font-size:11px;"></i></a>
        <?php
        $start = max(1, $current_page-2);
        $end   = min($total_pages, $start+4);
        if($end-$start<4) $start=max(1,$end-4);
        if($start>1){ echo '<a href="?page=1&status='.$filter_status.'&search='.urlencode($search_term).'&per_page='.$items_per_page.'" class="page-btn">1</a>'; if($start>2) echo '<span class="page-ellipsis">…</span>'; }
        for($i=$start;$i<=$end;$i++): ?>
        <a href="?page=<?php echo $i; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search_term); ?>&per_page=<?php echo $items_per_page; ?>" class="page-btn <?php echo $i==$current_page?'active':''; ?>"><?php echo $i; ?></a>
        <?php endfor;
        if($end<$total_pages){ if($end<$total_pages-1) echo '<span class="page-ellipsis">…</span>'; echo '<a href="?page='.$total_pages.'&status='.$filter_status.'&search='.urlencode($search_term).'&per_page='.$items_per_page.'" class="page-btn">'.$total_pages.'</a>'; }
        ?>
        <a href="?page=<?php echo min($total_pages,$current_page+1); ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search_term); ?>&per_page=<?php echo $items_per_page; ?>" class="page-btn <?php echo $current_page>=$total_pages?'disabled':''; ?>"><i class="fas fa-chevron-right" style="font-size:11px;"></i></a>
      </div>
    </div>
    <?php endif; ?>
  </div>

</main>

<!-- FOOTER -->
<footer class="portal-footer">
  <div class="footer-copy">&copy; 2026 Bold Footprint Initiatives. Admin Portal.</div>
  <div class="footer-links">
    <a href="/index.html"><i class="fas fa-home" style="font-size:10px;margin-right:3px;"></i> Main Site</a>
    <a href="/scholar-portal/">Scholar Portal</a>
    <a href="reports.php">Reports</a>
  </div>
</footer>

<!-- DETAIL DRAWER -->
<div class="drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>
<div class="drawer" id="drawer">
  <div class="drawer-header">
    <div class="drawer-title">Application <em>Detail</em></div>
    <button class="drawer-close" onclick="closeDrawer()"><i class="fas fa-times"></i></button>
  </div>
  <div class="drawer-body" id="drawerBody"><!-- filled by JS --></div>
  <div class="drawer-footer">
    <button class="btn-drawer-update" id="drawerUpdateBtn" onclick="drawerUpdate()"><i class="fas fa-edit"></i> Update Status</button>
    <button class="btn-drawer-close" onclick="closeDrawer()">Close</button>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// ── Sidebar ──────────────────────────────────────────────────────────────────
const sidebar=document.getElementById('sidebar'),sOverlay=document.getElementById('sidebarOverlay'),mToggle=document.getElementById('mobileToggle');
const openSB=()=>{sidebar.classList.add('active');sOverlay.classList.add('active');document.body.style.overflow='hidden';};
const closeSB=()=>{sidebar.classList.remove('active');sOverlay.classList.remove('active');document.body.style.overflow='';};
mToggle?.addEventListener('click',()=>sidebar.classList.contains('active')?closeSB():openSB());
sOverlay?.addEventListener('click',closeSB);
window.addEventListener('resize',()=>{if(window.innerWidth>768)closeSB();});

// ── Live clock ────────────────────────────────────────────────────────────────
function updateClock(){const el=document.getElementById('headerTime');if(!el)return;const now=new Date();el.textContent=now.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'})+' · '+now.toLocaleDateString('en-GB',{day:'numeric',month:'short'});}
updateClock();setInterval(updateClock,30000);

// ── Select all / row checkboxes ───────────────────────────────────────────────
const checkAll=document.getElementById('checkAll');
const bulkBar=document.getElementById('bulkBar');
const selectedCount=document.getElementById('selectedCount');

function updateBulkBar(){
  const checked=document.querySelectorAll('.row-checkbox:checked');
  const n=checked.length;
  selectedCount.textContent=n;
  bulkBar.classList.toggle('visible',n>0);
  document.querySelectorAll('.app-row').forEach(row=>{
    const cb=row.querySelector('.row-checkbox');
    row.classList.toggle('selected',cb&&cb.checked);
  });
}

checkAll?.addEventListener('change',function(){
  document.querySelectorAll('.row-checkbox').forEach(cb=>cb.checked=this.checked);
  updateBulkBar();
});
document.querySelectorAll('.row-checkbox').forEach(cb=>{
  cb.addEventListener('change',()=>{
    checkAll.checked=[...document.querySelectorAll('.row-checkbox')].every(c=>c.checked);
    updateBulkBar();
  });
});

function clearSelection(){
  document.querySelectorAll('.row-checkbox').forEach(cb=>cb.checked=false);
  checkAll.checked=false;
  updateBulkBar();
}
function getSelectedIds(){return[...document.querySelectorAll('.row-checkbox:checked')].map(c=>c.value);}

// ── Row click → open drawer ───────────────────────────────────────────────────
document.querySelectorAll('.app-row').forEach(row=>{
  row.addEventListener('click',function(e){
    if(e.target.type==='checkbox'||e.target.closest('button')||e.target.closest('a')) return;
    openDrawer(this.dataset);
  });
});

// ── Drawer ────────────────────────────────────────────────────────────────────
let currentDrawerAppId='';
let currentDrawerStatus='';

function openDrawer(d){
  currentDrawerAppId=d.appId;
  currentDrawerStatus=d.status;
  const body=document.getElementById('drawerBody');
  const colors=['#0D1829','#1C2F52','#9F1239','#059669','#0284C7','#7C3AED','#D97706'];
  const color=d.color||colors[0];
  const statusBadge=getStatusBadgeHTML(d.status);
  const dateStr=formatDateReadable(d.date);
  const updatedStr=d.updated?formatDateReadable(d.updated):'—';

  body.innerHTML=`
    <div class="drawer-section">
      <div class="drawer-avatar" style="background:${color};">${(d.first||'?')[0].toUpperCase()}</div>
      <div class="drawer-name">${escHtml(d.first)} ${escHtml(d.last)}</div>
      <div class="drawer-email">${escHtml(d.email)}</div>
      ${statusBadge}
    </div>

    <div class="drawer-section">
      <div class="drawer-section-label">Application Details</div>
      <div class="drawer-field">
        <div class="drawer-field-icon"><i class="fas fa-hashtag"></i></div>
        <div><div class="drawer-field-label">Application ID</div><div class="drawer-field-value" style="font-family:monospace;font-size:12.5px;">${escHtml(d.appId)}</div></div>
      </div>
      <div class="drawer-field">
        <div class="drawer-field-icon"><i class="fas fa-graduation-cap"></i></div>
        <div><div class="drawer-field-label">Programme</div><div class="drawer-field-value">${escHtml(d.program||'—')}</div></div>
      </div>
      <div class="drawer-field">
        <div class="drawer-field-icon"><i class="fas fa-university"></i></div>
        <div><div class="drawer-field-label">Institution</div><div class="drawer-field-value">${escHtml(d.institution||'—')}</div></div>
      </div>
      <div class="drawer-field">
        <div class="drawer-field-icon"><i class="fas fa-award"></i></div>
        <div><div class="drawer-field-label">Degree Class</div><div class="drawer-field-value">${escHtml(d.degree||'—')}</div></div>
      </div>
      <div class="drawer-field">
        <div class="drawer-field-icon"><i class="fas fa-calendar-alt"></i></div>
        <div><div class="drawer-field-label">Applied</div><div class="drawer-field-value">${dateStr}</div></div>
      </div>
      <div class="drawer-field">
        <div class="drawer-field-icon"><i class="fas fa-sync"></i></div>
        <div><div class="drawer-field-label">Last Updated</div><div class="drawer-field-value">${updatedStr}</div></div>
      </div>
    </div>

    ${d.comments?`
    <div class="drawer-section">
      <div class="drawer-section-label">Admin Notes</div>
      <div class="drawer-feedback">
        <div class="drawer-feedback-label">Comments</div>
        <div class="drawer-feedback-text">${escHtml(d.comments)}</div>
      </div>
    </div>`:''}

    <div class="drawer-section">
      <div class="drawer-section-label">Quick Actions</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button class="btn-sm" style="background:var(--success-pale);color:var(--success);" onclick="quickApproveFromDrawer()"><i class="fas fa-check"></i> Approve</button>
        <button class="btn-sm" style="background:var(--info-pale);color:var(--info);" onclick="moveToReview()"><i class="fas fa-search"></i> Move to Review</button>
        <button class="btn-sm" style="background:var(--purple-pale);color:var(--purple);" onclick="shortlist()"><i class="fas fa-star"></i> Shortlist</button>
        <button class="btn-sm" style="background:var(--danger-pale);color:var(--danger);" onclick="quickRejectFromDrawer()"><i class="fas fa-times"></i> Reject</button>
      </div>
    </div>
  `;

  document.getElementById('drawerOverlay').classList.add('open');
  document.getElementById('drawer').classList.add('open');
  document.body.style.overflow='hidden';
}

function closeDrawer(){
  document.getElementById('drawerOverlay').classList.remove('open');
  document.getElementById('drawer').classList.remove('open');
  document.body.style.overflow='';
}

function drawerUpdate(){ if(currentDrawerAppId) showStatusUpdateModal(currentDrawerAppId, currentDrawerStatus, ''); }
function quickApproveFromDrawer(){ if(currentDrawerAppId){ closeDrawer(); quickApprove(currentDrawerAppId); } }
function quickRejectFromDrawer(){ if(currentDrawerAppId){ closeDrawer(); quickReject(currentDrawerAppId); } }
function moveToReview(){ if(currentDrawerAppId){ closeDrawer(); submitStatusUpdate(currentDrawerAppId,'under_review',''); } }
function shortlist(){ if(currentDrawerAppId){ closeDrawer(); submitStatusUpdate(currentDrawerAppId,'shortlisted',''); } }

document.addEventListener('keydown',e=>{if(e.key==='Escape')closeDrawer();});

// ── Status update modal ───────────────────────────────────────────────────────
document.querySelectorAll('.status-update-btn').forEach(btn=>{
  btn.addEventListener('click',function(){
    showStatusUpdateModal(this.dataset.applicationId, this.dataset.currentStatus, this.dataset.comments||'');
  });
});

function showStatusUpdateModal(appId, currentStatus, comments){
  Swal.fire({
    title:'Update Application Status',
    html:`
      <div style="text-align:left;">
        <p style="font-size:13px;color:#64748b;margin-bottom:16px;">Application: <strong style="font-family:monospace;">${escHtml(appId)}</strong></p>
        <p style="font-size:13px;color:#64748b;margin-bottom:16px;">Currently: ${getStatusBadgeHTML(currentStatus)}</p>
        <div style="margin-bottom:16px;">
          <label style="display:block;font-size:11.5px;font-weight:600;color:#374151;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">New Status</label>
          <select id="swal-new-status" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;font-family:inherit;">
            <option value="">— Select status —</option>
            <option value="pending">Pending Review</option>
            <option value="under_review">Under Review</option>
            <option value="shortlisted">Shortlisted</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:11.5px;font-weight:600;color:#374151;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Admin Notes (sent in notification email)</label>
          <textarea id="swal-comments" rows="3" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;resize:vertical;outline:none;font-family:inherit;" placeholder="Optional notes for the applicant…">${escHtml(comments)}</textarea>
        </div>
      </div>`,
    showCancelButton:true,
    confirmButtonText:'Update Status',
    cancelButtonText:'Cancel',
    confirmButtonColor:'#9F1239',
    preConfirm:()=>{
      const ns=document.getElementById('swal-new-status').value;
      if(!ns){Swal.showValidationMessage('Please select a new status.');return false;}
      return{newStatus:ns,comments:document.getElementById('swal-comments').value};
    }
  }).then(r=>{
    if(r.isConfirmed&&r.value) submitStatusUpdate(appId,r.value.newStatus,r.value.comments);
  });
}

function quickApprove(appId){
  Swal.fire({
    title:'Approve Application?',
    html:`<p style="font-size:13.5px;color:#64748b;">You're about to <strong>approve</strong> application <code style="font-size:12px;">${escHtml(appId)}</code>. An email notification will be sent to the applicant.</p>`,
    icon:'question',
    showCancelButton:true,
    confirmButtonText:'Yes, Approve',
    cancelButtonText:'Cancel',
    confirmButtonColor:'#059669'
  }).then(r=>{if(r.isConfirmed) submitStatusUpdate(appId,'approved','Congratulations! Your application has been approved.');});
}

function quickReject(appId){
  Swal.fire({
    title:'Reject Application?',
    html:`<div style="text-align:left;"><p style="font-size:13.5px;color:#64748b;margin-bottom:12px;">Provide a reason for rejection (this will be included in the notification email):</p><textarea id="reject-reason" rows="3" style="width:100%;padding:10px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;outline:none;" placeholder="Reason for rejection…"></textarea></div>`,
    icon:'warning',
    showCancelButton:true,
    confirmButtonText:'Reject',
    cancelButtonText:'Cancel',
    confirmButtonColor:'#DC2626',
    preConfirm:()=>document.getElementById('reject-reason').value
  }).then(r=>{if(r.isConfirmed) submitStatusUpdate(appId,'rejected',r.value||'');});
}

function submitStatusUpdate(appId,newStatus,comments){
  Swal.fire({title:'Updating…',allowOutsideClick:false,showConfirmButton:false,willOpen:()=>Swal.showLoading()});
  const fd=new FormData();
  fd.append('application_id',appId);
  fd.append('new_status',newStatus);
  fd.append('admin_comments',comments);
  fd.append('update_status','1');
  fetch('update_status.php',{method:'POST',body:fd,credentials:'same-origin'})
    .then(r=>{if(!r.ok)throw new Error('Network error');return r.json();})
    .then(data=>{
      if(data.success){
        Swal.fire({icon:'success',title:'Updated!',text:data.message||'Status updated successfully.',confirmButtonColor:'#C8A058'}).then(()=>location.reload());
      } else throw new Error(data.message||'Failed to update.');
    })
    .catch(err=>Swal.fire({icon:'error',title:'Error',text:err.message}));
}

// ── Bulk actions ──────────────────────────────────────────────────────────────
function bulkAction(newStatus){
  const ids=getSelectedIds();
  if(!ids.length){Swal.fire({icon:'info',title:'No selection',text:'Please select at least one application.'});return;}
  const label=newStatus.replace('_',' ');
  Swal.fire({
    title:`Bulk ${label.replace(/\b\w/g,c=>c.toUpperCase())}`,
    html:`<p style="font-size:13.5px;color:#64748b;">You are about to mark <strong>${ids.length}</strong> application(s) as <strong>${label}</strong>.</p><div style="margin-top:12px;"><label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;">Notes (optional)</label><textarea id="bulk-comments" rows="2" style="width:100%;padding:9px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;outline:none;" placeholder="Reason or notes…"></textarea></div>`,
    showCancelButton:true,
    confirmButtonText:`Update ${ids.length} Application${ids.length>1?'s':''}`,
    confirmButtonColor:'#0D1829'
  }).then(r=>{
    if(r.isConfirmed){
      const comments=document.getElementById('bulk-comments')?.value||'';
      Swal.fire({title:'Processing…',allowOutsideClick:false,showConfirmButton:false,willOpen:()=>Swal.showLoading()});
      const fd=new FormData();
      fd.append('ids',JSON.stringify(ids));
      fd.append('new_status',newStatus);
      fd.append('admin_comments',comments);
      fd.append('bulk_update','1');
      fetch('bulk_update_status.php',{method:'POST',body:fd,credentials:'same-origin'})
        .then(r=>r.ok?r.json():Promise.reject('Network error'))
        .then(data=>{
          if(data.success) Swal.fire({icon:'success',title:'Done!',text:data.message||`${ids.length} application(s) updated.`,confirmButtonColor:'#C8A058'}).then(()=>location.reload());
          else throw new Error(data.message);
        })
        .catch(err=>{
          // Fallback: process one by one
          Swal.fire({title:'Processing…',allowOutsideClick:false,showConfirmButton:false,willOpen:()=>Swal.showLoading()});
          const submitNext=(arr,idx)=>{
            if(idx>=arr.length){Swal.fire({icon:'success',title:'Done!',text:`${arr.length} application(s) updated.`,confirmButtonColor:'#C8A058'}).then(()=>location.reload());return;}
            const fd2=new FormData();
            fd2.append('application_id',arr[idx]);fd2.append('new_status',newStatus);fd2.append('admin_comments',comments);fd2.append('update_status','1');
            fetch('update_status.php',{method:'POST',body:fd2,credentials:'same-origin'}).then(r=>r.json()).then(()=>submitNext(arr,idx+1)).catch(()=>submitNext(arr,idx+1));
          };
          submitNext(ids,0);
        });
    }
  });
}

function exportSelected(){
  const ids=getSelectedIds();
  if(!ids.length){Swal.fire({icon:'info',title:'No selection',text:'Please select at least one application.'});return;}
  const rows=[['Application ID','First Name','Last Name','Email','Programme','Institution','Degree','Status','Applied']];
  document.querySelectorAll('.app-row').forEach(row=>{
    if(ids.includes(row.dataset.appId)){
      rows.push([row.dataset.appId,row.dataset.first,row.dataset.last,row.dataset.email,row.dataset.program,row.dataset.institution,row.dataset.degree,row.dataset.status,row.dataset.date]);
    }
  });
  downloadCSV(rows,'selected_applications.csv');
}

// ── Export all (visible in table) ─────────────────────────────────────────────
function exportApplications(){
  const rows=[['Application ID','First Name','Last Name','Email','Programme','Institution','Degree','Status','Applied']];
  document.querySelectorAll('.app-row').forEach(row=>{
    rows.push([row.dataset.appId,row.dataset.first,row.dataset.last,row.dataset.email,row.dataset.program,row.dataset.institution,row.dataset.degree,row.dataset.status,row.dataset.date]);
  });
  downloadCSV(rows,'applications_export.csv');
}

function downloadCSV(rows,filename){
  const csv=rows.map(r=>r.map(v=>'"'+(v||'').toString().replace(/"/g,'""')+'"').join(',')).join('\n');
  const blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
  const url=URL.createObjectURL(blob);
  const a=document.createElement('a');a.href=url;a.download=filename;a.click();URL.revokeObjectURL(url);
  Swal.fire({icon:'success',title:'Export ready',text:`${filename} downloaded.`,timer:2500,showConfirmButton:false});
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function escHtml(s){const d=document.createElement('div');d.appendChild(document.createTextNode(s||''));return d.innerHTML;}

function getStatusBadgeHTML(status){
  const map={
    pending:['badge-pending','clock','Pending'],
    under_review:['badge-review','search','Under Review'],
    shortlisted:['badge-shortlisted','star','Shortlisted'],
    approved:['badge-approved','check-circle','Approved'],
    rejected:['badge-rejected','times-circle','Rejected'],
  };
  const v=map[(status||'').toLowerCase()]||['badge-secondary','circle','Unknown'];
  return `<span class="badge ${v[0]}" style="font-size:12.5px;"><i class="fas fa-${v[1]}" style="font-size:9px;"></i> ${v[2]}</span>`;
}

function formatDateReadable(str){
  if(!str)return'—';
  try{const d=new Date(str);return d.toLocaleDateString('en-GB',{day:'numeric',month:'long',year:'numeric'});}
  catch{return str;}
}

// ── Table sort (client-side, current page) ────────────────────────────────────
document.querySelectorAll('th.sortable').forEach(th=>{
  th.addEventListener('click',function(){
    const key=this.dataset.sort;
    const tbody=document.querySelector('#applicationsTable tbody');
    const rows=[...tbody.querySelectorAll('tr.app-row')];
    const asc=this.getAttribute('data-asc')==='1';
    rows.sort((a,b)=>{
      let va='',vb='';
      if(key==='name'){va=a.dataset.last+a.dataset.first;vb=b.dataset.last+b.dataset.first;}
      else if(key==='status'){va=a.dataset.status;vb=b.dataset.status;}
      else if(key==='date'){va=a.dataset.date;vb=b.dataset.date;}
      else if(key==='id'){va=a.dataset.appId;vb=b.dataset.appId;}
      return asc?va.localeCompare(vb):vb.localeCompare(va);
    });
    rows.forEach(r=>tbody.appendChild(r));
    document.querySelectorAll('th.sortable').forEach(t=>{t.removeAttribute('data-asc');t.querySelector('.sort-icon').className='fas fa-sort sort-icon';});
    this.setAttribute('data-asc',asc?'0':'1');
    this.querySelector('.sort-icon').className=`fas fa-sort-${asc?'down':'up'} sort-icon`;
  });
});
</script>
</body>
</html>