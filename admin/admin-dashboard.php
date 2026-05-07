<?php
session_start();
error_log("Dashboard accessed - Session data: " . print_r($_SESSION, true));

$is_logged_in = false;
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    $is_logged_in = true;
}

if (!$is_logged_in) {
    $_SESSION['error'] = "Please log in to access the dashboard.";
    header('Location: admin-login.php');
    exit();
}

require_once 'includes/config.php';
require_once 'includes/db.php';

$stats = ['total_scholars' => 0, 'pending_count' => 0, 'verified_count' => 0, 'active_count' => 0, 'total_applications' => 0, 'approved_count' => 0, 'rejected_count' => 0];
$recent_applications = [];
$error_message = '';
$success_message = '';
$program_stats = [];

try {
    $db = new Database();
    $conn = $db->getConnection();
    if (!$conn) throw new Exception("Database connection failed");

    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role_id = 2");
    if ($stmt) $stats['total_scholars'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $conn->query("SELECT status, COUNT(*) as count FROM users WHERE role_id = 2 GROUP BY status");
    if ($stmt) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            switch ($row['status']) {
                case 'pending':   $stats['pending_count']  = $row['count']; break;
                case 'verified':  $stats['verified_count'] = $row['count']; break;
                case 'active':    $stats['active_count']   = $row['count']; break;
            }
        }
    }

    try {
        $stmt = $conn->query("SELECT COUNT(*) as count FROM scholarship_applications");
        $stats['total_applications'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $stmt = $conn->query("SELECT status, COUNT(*) as count FROM scholarship_applications GROUP BY status");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            switch ($row['status']) {
                case 'pending':  $stats['pending_count']  = $row['count']; break;
                case 'approved': $stats['approved_count'] = $row['count']; break;
                case 'rejected': $stats['rejected_count'] = $row['count']; break;
            }
        }

        $stmt = $conn->query("SELECT application_id, first_name, last_name, email, program_type, status, created_at, undergraduate_institution, degree_class, cv_file, transcript_file FROM scholarship_applications ORDER BY created_at DESC LIMIT 10");
        $recent_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Applications fetch error: " . $e->getMessage());
    }

    $stmt = $conn->query("SELECT program, COUNT(*) as count FROM users WHERE role_id = 2 GROUP BY program");
    if ($stmt) $program_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $error_message = "Database error: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        $stmt = $conn->prepare("UPDATE users SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :user_id");
        if ($stmt->execute([':status' => $_POST['new_status'], ':user_id' => $_POST['user_id']])) {
            $success_message = "Status updated successfully!";
        } else {
            $error_message = "Failed to update status.";
        }
    } catch (Exception $e) {
        $error_message = "Error updating status: " . $e->getMessage();
    }
}

function formatNumber($n) {
    if ($n > 1000000) return round($n / 1000000, 1) . 'M';
    if ($n > 1000) return round($n / 1000, 1) . 'K';
    return $n;
}
function formatDate($d) { return date('d M Y', strtotime($d)); }
function getStatusBadgeClass($s) {
    if ($s === null) return 'badge-secondary';
    switch (strtolower((string)$s)) {
        case 'pending':      return 'badge-pending';
        case 'verified':
        case 'approved':     return 'badge-approved';
        case 'rejected':     return 'badge-rejected';
        case 'active':       return 'badge-active';
        case 'under_review': return 'badge-review';
        case 'shortlisted':  return 'badge-shortlisted';
        default:             return 'badge-secondary';
    }
}
function getStatusIcon($s) {
    switch (strtolower((string)$s)) {
        case 'pending':      return 'clock';
        case 'verified':
        case 'approved':     return 'check-circle';
        case 'rejected':     return 'times-circle';
        case 'active':       return 'user-check';
        case 'under_review': return 'search';
        case 'shortlisted':  return 'star';
        default:             return 'circle';
    }
}

$admin_full_name  = $_SESSION['admin_name'] ?? 'Admin';
$admin_first_name = explode(' ', $admin_full_name)[0];
$admin_role       = ucfirst($_SESSION['admin_role'] ?? 'Administrator');

echo "<!--\nDebug: Total=" . $stats['total_scholars'] . " Pending=" . $stats['pending_count'] . " Verified=" . $stats['verified_count'] . " Active=" . $stats['active_count'] . "\n-->\n";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Admin Dashboard | Bold Footprint Initiatives</title>
  <link rel="icon" type="image/png" sizes="32x32" href="/Images/BFI_Logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
  <style>
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
    body{font-family:var(--font-body);background:#F0F2F7;color:var(--text-primary);line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden;}
    a{text-decoration:none;color:inherit;}
    img{max-width:100%;display:block;}

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
    .sidebar-avatar img{width:100%;height:100%;object-fit:cover;}
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
    .header-time{font-size:12px;color:var(--text-muted);font-weight:400;padding-right:12px;border-right:1px solid var(--border-light);}
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

    /* ── WELCOME BANNER ── */
    .welcome-banner{background:var(--navy);border-radius:var(--r-xl);padding:30px 34px;margin-bottom:22px;position:relative;overflow:hidden;}
    .welcome-banner::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.025) 1px,transparent 1px);background-size:30px 30px;}
    .welcome-banner::after{content:'';position:absolute;top:-50px;right:-50px;width:280px;height:280px;background:radial-gradient(circle,rgba(200,160,88,0.09) 0%,transparent 65%);}
    .welcome-inner{position:relative;z-index:1;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:18px;}
    .welcome-text-eyebrow{font-size:9.5px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:rgba(200,160,88,0.65);margin-bottom:6px;display:flex;align-items:center;gap:8px;}
    .welcome-title{font-family:var(--font-display);font-size:clamp(22px,2.8vw,30px);font-weight:500;color:var(--white);line-height:1.15;margin-bottom:5px;}
    .welcome-title em{font-style:italic;color:var(--gold-bright);}
    .welcome-sub{font-size:13px;font-weight:300;color:rgba(255,255,255,0.45);}
    .welcome-cta{display:flex;gap:10px;flex-wrap:wrap;}
    .btn-gold{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:var(--gold);color:var(--midnight);font-family:var(--font-body);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-gold:hover{background:var(--gold-bright);transform:translateY(-1px);}
    .btn-crimson{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:var(--admin-crimson);color:white;font-family:var(--font-body);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-crimson:hover{background:var(--admin-crimson-light);transform:translateY(-1px);}
    .btn-ghost-sm{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.75);font-family:var(--font-body);font-size:13px;font-weight:400;border:1px solid rgba(255,255,255,0.1);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-ghost-sm:hover{background:rgba(255,255,255,0.1);color:var(--white);}

    /* ── STATS ── */
    .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:22px;}
    .stat-card{background:var(--white);border-radius:var(--r-lg);padding:22px 24px;border:1px solid var(--border-light);transition:var(--transition);position:relative;overflow:hidden;}
    .stat-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-md);border-color:transparent;}
    .stat-card-icon{position:absolute;top:18px;right:18px;width:44px;height:44px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:17px;}
    .si-navy{background:var(--navy);color:var(--gold-bright);}
    .si-gold{background:rgba(200,160,88,0.12);color:var(--gold);}
    .si-green{background:var(--success-pale);color:var(--success);}
    .si-blue{background:var(--info-pale);color:var(--info);}
    .si-crimson{background:var(--admin-crimson-pale);color:var(--admin-crimson);}
    .stat-label{font-size:12px;font-weight:400;color:var(--text-muted);margin-bottom:8px;}
    .stat-value{font-family:var(--font-display);font-size:36px;font-weight:500;color:var(--navy);line-height:1;margin-bottom:8px;}
    .stat-meta{font-size:11.5px;color:var(--text-muted);display:flex;align-items:center;gap:5px;}
    .trend-up{color:var(--success);font-weight:600;}
    .trend-down{color:var(--danger);font-weight:600;}

    /* ── SECTION LABEL ── */
    .section-label{font-size:10px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);margin-bottom:14px;}

    /* ── QUICK ACTIONS ── */
    .quick-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:22px;}
    .quick-card{background:var(--white);border-radius:var(--r-lg);padding:20px;border:1px solid var(--border-light);cursor:pointer;transition:var(--transition);display:flex;flex-direction:column;align-items:flex-start;}
    .quick-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-md);border-color:transparent;}
    .quick-icon{width:44px;height:44px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;margin-bottom:14px;font-size:17px;transition:var(--transition);}
    .qi-navy{background:var(--cream);color:var(--navy);}
    .quick-card:hover .qi-navy{background:var(--navy);color:var(--gold-bright);}
    .qi-gold{background:rgba(200,160,88,0.1);color:var(--gold);}
    .quick-card:hover .qi-gold{background:var(--gold);color:var(--midnight);}
    .qi-green{background:var(--success-pale);color:var(--success);}
    .quick-card:hover .qi-green{background:var(--success);color:var(--white);}
    .qi-crimson{background:var(--admin-crimson-pale);color:var(--admin-crimson);}
    .quick-card:hover .qi-crimson{background:var(--admin-crimson);color:var(--white);}
    .quick-title{font-size:13.5px;font-weight:500;color:var(--navy);margin-bottom:3px;}
    .quick-desc{font-size:11.5px;color:var(--text-muted);}

    /* ── TWO-COL ── */
    .two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:22px;}
    .card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);overflow:hidden;}
    .card-header{padding:18px 22px 0;display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;}
    .card-title{font-family:var(--font-display);font-size:18px;font-weight:500;color:var(--navy);}
    .card-title em{font-style:italic;color:var(--gold);}
    .card-link{font-size:12px;color:var(--gold);font-weight:500;display:flex;align-items:center;gap:5px;transition:gap var(--transition);}
    .card-link:hover{gap:8px;}
    .card-body{padding:0 22px 22px;}

    /* ── TABLE ── */
    .table-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);overflow:hidden;margin-bottom:22px;}
    .table-card-header{padding:20px 24px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border-light);flex-wrap:wrap;gap:12px;}
    .table-title{font-family:var(--font-display);font-size:20px;font-weight:500;color:var(--navy);}
    .table-title em{font-style:italic;color:var(--gold);}
    .table-subtitle{font-size:12.5px;color:var(--text-muted);margin-top:2px;}
    .table-actions{display:flex;gap:8px;align-items:center;}
    .table-action-btn{width:34px;height:34px;border-radius:var(--r-sm);background:var(--cream);border:1px solid var(--border-light);cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:13px;transition:var(--transition);}
    .table-action-btn:hover{background:var(--navy);color:var(--gold-bright);border-color:transparent;}
    .data-table{width:100%;border-collapse:separate;border-spacing:0;}
    .data-table th{background:#F8F9FB;color:var(--text-muted);font-weight:600;font-size:10.5px;letter-spacing:0.8px;text-transform:uppercase;padding:12px 16px;text-align:left;border-bottom:1px solid var(--border-light);}
    .data-table td{padding:13px 16px;vertical-align:middle;border-bottom:1px solid var(--border-light);color:var(--text-primary);font-size:13.5px;}
    .data-table tbody tr:last-child td{border-bottom:none;}
    .data-table tbody tr{transition:background var(--transition);}
    .data-table tbody tr:hover{background:#F8F9FB;}
    .user-cell{display:flex;align-items:center;gap:10px;}
    .user-avatar{width:36px;height:36px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-weight:600;color:white;font-size:14px;background:linear-gradient(135deg,var(--navy),var(--navy-light));flex-shrink:0;}
    .user-name{font-weight:500;font-size:13.5px;color:var(--text-primary);}
    .user-email{font-size:11.5px;color:var(--text-muted);}

    /* ── BADGES ── */
    .badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:500;}
    .badge-pending{background:var(--warning-pale);color:var(--warning);}
    .badge-approved,.badge-verified{background:var(--success-pale);color:var(--success);}
    .badge-rejected{background:var(--danger-pale);color:var(--danger);}
    .badge-active{background:var(--info-pale);color:var(--info);}
    .badge-review{background:rgba(99,102,241,0.1);color:#6366F1;}
    .badge-shortlisted{background:rgba(139,92,246,0.1);color:#8B5CF6;}
    .badge-secondary{background:var(--cream);color:var(--text-muted);}

    /* ── BUTTONS ── */
    .btn-sm{display:inline-flex;align-items:center;gap:5px;padding:6px 13px;font-family:var(--font-body);font-size:12px;font-weight:500;border-radius:var(--r-sm);border:none;cursor:pointer;transition:var(--transition);}
    .btn-sm:hover{transform:translateY(-1px);}
    .btn-primary{background:var(--navy);color:var(--white);}.btn-primary:hover{background:var(--navy-light);}
    .btn-info{background:var(--info-pale);color:var(--info);}.btn-info:hover{background:var(--info);color:var(--white);}
    .btn-success{background:var(--success-pale);color:var(--success);}.btn-success:hover{background:var(--success);color:var(--white);}
    .btn-danger{background:var(--danger-pale);color:var(--danger);}.btn-danger:hover{background:var(--danger);color:var(--white);}
    .btn-update{background:var(--admin-crimson-pale);color:var(--admin-crimson);}.btn-update:hover{background:var(--admin-crimson);color:var(--white);}
    .doc-link{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;background:var(--cream);color:var(--text-secondary);font-size:11.5px;border:1px solid var(--border-light);transition:var(--transition);}
    .doc-link:hover{background:var(--navy);color:var(--gold-bright);border-color:transparent;}
    .action-btns{display:flex;gap:6px;flex-wrap:wrap;}

    /* ── APPROVAL QUEUE ── */
    .queue-item{display:flex;align-items:center;gap:14px;padding:13px 0;border-bottom:1px solid var(--border-light);}
    .queue-item:last-child{border-bottom:none;}
    .queue-avatar{width:36px;height:36px;border-radius:var(--r-sm);background:linear-gradient(135deg,var(--navy),var(--navy-light));display:flex;align-items:center;justify-content:center;font-weight:600;color:var(--gold-bright);font-family:var(--font-display);font-size:15px;flex-shrink:0;}
    .queue-info{flex:1;}
    .queue-name{font-size:13.5px;font-weight:500;color:var(--navy);margin-bottom:2px;}
    .queue-sub{font-size:11.5px;color:var(--text-muted);}
    .queue-actions{display:flex;gap:6px;}
    .btn-approve{padding:5px 13px;background:var(--success-pale);color:var(--success);font-size:11.5px;font-weight:500;border:none;border-radius:20px;cursor:pointer;transition:var(--transition);}
    .btn-approve:hover{background:var(--success);color:var(--white);}
    .btn-reject{padding:5px 13px;background:var(--danger-pale);color:var(--danger);font-size:11.5px;font-weight:500;border:none;border-radius:20px;cursor:pointer;transition:var(--transition);}
    .btn-reject:hover{background:var(--danger);color:var(--white);}

    /* ── ACTIVITY FEED ── */
    .activity-item{display:flex;gap:13px;padding:12px 0;border-bottom:1px solid var(--border-light);}
    .activity-item:last-child{border-bottom:none;}
    .activity-dot{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;margin-top:2px;}
    .ad-green{background:var(--success-pale);color:var(--success);}
    .ad-gold{background:rgba(200,160,88,0.12);color:var(--gold);}
    .ad-blue{background:var(--info-pale);color:var(--info);}
    .ad-crimson{background:var(--admin-crimson-pale);color:var(--admin-crimson);}
    .activity-text{font-size:13px;color:var(--text-secondary);line-height:1.55;flex:1;}
    .activity-text strong{color:var(--navy);font-weight:500;}
    .activity-time{font-size:11px;color:var(--text-muted);flex-shrink:0;margin-top:3px;}

    /* ── TABLE FOOTER ── */
    .table-card-footer{padding:14px 24px;display:flex;justify-content:space-between;align-items:center;border-top:1px solid var(--border-light);background:#FAFBFC;flex-wrap:wrap;gap:10px;}
    .tf-text{font-size:12.5px;color:var(--text-muted);}
    .tf-strong{color:var(--navy);font-weight:600;}

    /* ── SIDEBAR OVERLAY ── */
    .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:150;backdrop-filter:blur(4px);}
    .sidebar-overlay.active{display:block;}

    /* ── PORTAL FOOTER ── */
    .portal-footer{margin-left:var(--sidebar-width);background:var(--white);padding:14px 26px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;}
    .footer-copy{font-size:11.5px;color:var(--text-muted);}
    .footer-links{display:flex;gap:18px;}
    .footer-links a{font-size:11.5px;color:var(--text-muted);transition:color var(--transition);}
    .footer-links a:hover{color:var(--gold);}

    @media(max-width:1200px){.stats-grid{grid-template-columns:repeat(2,1fr)}.quick-grid{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:1024px){.two-col{grid-template-columns:1fr}}
    @media(max-width:768px){
      .sidebar{transform:translateX(-100%);}.sidebar.active{transform:translateX(0);}
      .header{left:0;}.main,.portal-footer{margin-left:0;}
      .mobile-toggle{display:flex;}.header-breadcrumb{display:none;}
      .stats-grid{grid-template-columns:repeat(2,1fr);}
      .quick-grid{grid-template-columns:repeat(2,1fr);}
    }
    @media(max-width:480px){.stats-grid{grid-template-columns:1fr}.quick-grid{grid-template-columns:1fr}.welcome-cta{flex-direction:column}}
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
      <div class="sidebar-avatar"><div class="sidebar-avatar-init"><?php echo strtoupper(substr($admin_first_name, 0, 1)); ?></div></div>
      <div>
        <div class="sidebar-user-name"><?php echo htmlspecialchars($admin_full_name); ?></div>
        <div class="sidebar-user-role"><?php echo htmlspecialchars($admin_role); ?></div>
      </div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Command</div>
    <div class="nav-item"><a href="admin-dashboard.php" class="nav-link active"><i class="fas fa-chart-pie"></i> Dashboard</a></div>
    <div class="nav-item"><a href="manage-scholars.php" class="nav-link"><i class="fas fa-user-graduate"></i> Scholars</a></div>
    <div class="nav-item"><a href="applications.php" class="nav-link"><i class="fas fa-tasks"></i> Applications <span class="nav-badge"><?php echo $stats['pending_count']; ?></span></a></div>
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
      <span>Admin</span><span class="sep">/</span>
      <strong>Dashboard</strong>
    </div>
  </div>
  <div class="header-right">
    <div class="header-time" id="headerTime"></div>
    <a href="reports.php">
      <button class="header-icon-btn" title="Reports"><i class="fas fa-chart-bar"></i></button>
    </a>
    <button class="header-icon-btn" title="Notifications" id="notifBtn">
      <i class="fas fa-bell"></i>
      <?php if ($stats['pending_count'] > 0): ?><div class="notif-dot"></div><?php endif; ?>
    </button>
    <div class="header-avatar-wrap">
      <div class="header-avatar"><div class="header-avatar-init"><?php echo strtoupper(substr($admin_first_name, 0, 1)); ?></div></div>
      <div>
        <div class="header-admin-label"><?php echo htmlspecialchars($admin_first_name); ?></div>
        <div class="header-admin-role"><?php echo htmlspecialchars($admin_role); ?></div>
      </div>
    </div>
  </div>
</header>

<!-- MAIN CONTENT -->
<main class="main">

  <?php if ($error_message): ?>
  <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>
  <?php if ($success_message): ?>
  <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <!-- WELCOME BANNER -->
  <div class="welcome-banner">
    <div class="welcome-inner">
      <div>
        <div class="welcome-text-eyebrow"><i class="fas fa-shield-alt" style="font-size:8px;"></i> Admin Command Centre</div>
        <div class="welcome-title">Welcome back, <em><?php echo htmlspecialchars($admin_first_name); ?>.</em></div>
        <div class="welcome-sub">Here's what needs your attention today.</div>
      </div>
      <div class="welcome-cta">
        <a href="applications.php" class="btn-gold"><i class="fas fa-tasks"></i> Review Applications</a>
        <a href="manage-scholars.php" class="btn-ghost-sm"><i class="fas fa-user-graduate"></i> Manage Scholars</a>
        <a href="reports.php" class="btn-ghost-sm"><i class="fas fa-chart-bar"></i> Reports</a>
      </div>
    </div>
  </div>

  <!-- STATS -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-card-icon si-navy"><i class="fas fa-users"></i></div>
      <div class="stat-label">Total Scholars</div>
      <div class="stat-value"><?php echo formatNumber($stats['total_scholars']); ?></div>
      <div class="stat-meta"><i class="fas fa-arrow-up trend-up" style="font-size:10px;"></i><span>Across all programmes</span></div>
    </div>
    <div class="stat-card">
      <div class="stat-card-icon si-gold"><i class="fas fa-clock"></i></div>
      <div class="stat-label">Pending Review</div>
      <div class="stat-value" style="color:var(--warning);"><?php echo formatNumber($stats['pending_count']); ?></div>
      <div class="stat-meta"><i class="fas fa-exclamation-circle" style="font-size:10px;color:var(--warning);"></i><span>Awaiting action</span></div>
    </div>
    <div class="stat-card">
      <div class="stat-card-icon si-green"><i class="fas fa-check-circle"></i></div>
      <div class="stat-label">Verified Scholars</div>
      <div class="stat-value" style="color:var(--success);"><?php echo formatNumber($stats['verified_count']); ?></div>
      <div class="stat-meta"><i class="fas fa-arrow-up trend-up" style="font-size:10px;"></i><span>Fully verified accounts</span></div>
    </div>
    <div class="stat-card">
      <div class="stat-card-icon si-blue"><i class="fas fa-graduation-cap"></i></div>
      <div class="stat-label">Active Scholars</div>
      <div class="stat-value" style="color:var(--info);"><?php echo formatNumber($stats['active_count']); ?></div>
      <div class="stat-meta"><i class="fas fa-arrow-up trend-up" style="font-size:10px;"></i><span>Currently active</span></div>
    </div>
  </div>

  <!-- QUICK ACTIONS -->
  <div class="section-label">Quick Actions</div>
  <div class="quick-grid">
    <div class="quick-card" onclick="window.location.href='manage-scholars.php'">
      <div class="quick-icon qi-navy"><i class="fas fa-user-graduate"></i></div>
      <div class="quick-title">Manage Scholars</div>
      <div class="quick-desc">View and update scholar records</div>
    </div>
    <div class="quick-card" onclick="window.location.href='admin-document-review.php'">
      <div class="quick-icon qi-gold"><i class="fas fa-file-alt"></i></div>
      <div class="quick-title">Review Documents</div>
      <div class="quick-desc">Verify and approve submissions</div>
    </div>
    <div class="quick-card" onclick="window.location.href='scholarships.php'">
      <div class="quick-icon qi-green"><i class="fas fa-graduation-cap"></i></div>
      <div class="quick-title">Manage Scholarships</div>
      <div class="quick-desc">Add and edit opportunities</div>
    </div>
    <div class="quick-card" onclick="window.location.href='reports.php'">
      <div class="quick-icon qi-crimson"><i class="fas fa-chart-bar"></i></div>
      <div class="quick-title">Generate Reports</div>
      <div class="quick-desc">Analytics and export data</div>
    </div>
  </div>

  <!-- TWO-COL: APPROVAL QUEUE + ACTIVITY -->
  <div class="two-col">

    <!-- APPROVAL QUEUE -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Approval <em>Queue</em></div>
        <a href="applications.php?status=pending" class="card-link">View all <i class="fas fa-arrow-right" style="font-size:10px;"></i></a>
      </div>
      <div class="card-body">
        <?php if (!empty($recent_applications)): ?>
          <?php foreach (array_slice($recent_applications, 0, 4) as $app): ?>
          <div class="queue-item">
            <div class="queue-avatar"><?php echo strtoupper(substr($app['first_name'], 0, 1)); ?></div>
            <div class="queue-info">
              <div class="queue-name"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></div>
              <div class="queue-sub"><?php echo htmlspecialchars($app['program_type'] ?? 'Application'); ?> · <?php echo formatDate($app['created_at']); ?></div>
            </div>
            <div class="queue-actions">
              <button class="btn-approve" onclick="submitStatusUpdate('<?php echo $app['application_id']; ?>', 'approved', '')"><i class="fas fa-check" style="font-size:10px;"></i></button>
              <button class="btn-reject"  onclick="submitStatusUpdate('<?php echo $app['application_id']; ?>', 'rejected', '')"><i class="fas fa-times" style="font-size:10px;"></i></button>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div style="text-align:center;padding:32px 0;color:var(--text-muted);">
            <i class="fas fa-check-double" style="font-size:28px;margin-bottom:10px;display:block;opacity:0.4;"></i>
            <p style="font-size:13px;">No pending applications</p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- RECENT ACTIVITY -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Recent <em>Activity</em></div>
        <span style="font-size:11.5px;color:var(--text-muted);">Last 24 hours</span>
      </div>
      <div class="card-body">
        <div class="activity-item">
          <div class="activity-dot ad-green"><i class="fas fa-check"></i></div>
          <div class="activity-text"><strong>Application approved</strong> — Scholarship application reviewed and approved.</div>
          <div class="activity-time">2h ago</div>
        </div>
        <div class="activity-item">
          <div class="activity-dot ad-gold"><i class="fas fa-file-alt"></i></div>
          <div class="activity-text"><strong>Document uploaded</strong> — New scholar submitted CV and transcript for review.</div>
          <div class="activity-time">4h ago</div>
        </div>
        <div class="activity-item">
          <div class="activity-dot ad-blue"><i class="fas fa-user-plus"></i></div>
          <div class="activity-text"><strong>New registration</strong> — A new scholar created an account and is pending verification.</div>
          <div class="activity-time">6h ago</div>
        </div>
        <div class="activity-item">
          <div class="activity-dot ad-crimson"><i class="fas fa-bell"></i></div>
          <div class="activity-text"><strong>Deadline alert</strong> — Commonwealth Scholarship closing date is in 7 days.</div>
          <div class="activity-time">8h ago</div>
        </div>
      </div>
    </div>

  </div>

  <!-- APPLICATIONS TABLE -->
  <div class="table-card">
    <div class="table-card-header">
      <div>
        <div class="table-title">Recent <em>Applications</em></div>
        <div class="table-subtitle">Latest scholarship applications received</div>
      </div>
      <div class="table-actions">
        <button class="table-action-btn" title="Refresh" onclick="location.reload()"><i class="fas fa-sync-alt"></i></button>
        <button class="table-action-btn" title="Filter"><i class="fas fa-filter"></i></button>
        <button class="table-action-btn" title="Export"><i class="fas fa-download"></i></button>
        <a href="applications.php" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:var(--navy);color:var(--white);font-size:12px;font-weight:500;border-radius:var(--r-sm);transition:var(--transition);" onmouseover="this.style.background='var(--navy-light)'" onmouseout="this.style.background='var(--navy)'">
          <i class="fas fa-list"></i> All Applications
        </a>
      </div>
    </div>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Applicant</th>
            <th>Programme</th>
            <th>Institution</th>
            <th>Documents</th>
            <th>Status</th>
            <th>Applied</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($recent_applications)): ?>
            <?php foreach ($recent_applications as $app): ?>
            <tr>
              <td>
                <div class="user-cell">
                  <div class="user-avatar"><?php echo strtoupper(substr($app['first_name'], 0, 1)); ?></div>
                  <div>
                    <div class="user-name"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></div>
                    <div class="user-email"><?php echo htmlspecialchars($app['email']); ?></div>
                  </div>
                </div>
              </td>
              <td style="font-size:13px;max-width:140px;"><div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($app['program_type'] ?? '—'); ?></div></td>
              <td style="font-size:13px;max-width:140px;"><div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($app['undergraduate_institution'] ?? '—'); ?></div></td>
              <td>
                <div style="display:flex;gap:5px;">
                  <?php if (!empty($app['cv_file'])): ?><a href="uploads/<?php echo htmlspecialchars($app['cv_file']); ?>" class="doc-link" target="_blank"><i class="fas fa-file-pdf"></i> CV</a><?php endif; ?>
                  <?php if (!empty($app['transcript_file'])): ?><a href="uploads/<?php echo htmlspecialchars($app['transcript_file']); ?>" class="doc-link" target="_blank"><i class="fas fa-file-alt"></i> Transcript</a><?php endif; ?>
                </div>
              </td>
              <td>
                <span class="badge <?php echo getStatusBadgeClass($app['status']); ?>">
                  <i class="fas fa-<?php echo getStatusIcon($app['status']); ?>" style="font-size:10px;"></i>
                  <?php echo ucwords(str_replace('_', ' ', strtolower($app['status'] ?? 'unknown'))); ?>
                </span>
              </td>
              <td style="font-size:12.5px;color:var(--text-muted);"><?php echo formatDate($app['created_at']); ?></td>
              <td>
                <div class="action-btns">
                  <button class="btn-sm btn-update status-update-btn"
                    data-application-id="<?php echo $app['application_id']; ?>"
                    data-current-status="<?php echo htmlspecialchars($app['status'] ?? ''); ?>">
                    <i class="fas fa-edit"></i> Update
                  </button>
                  <button class="btn-sm btn-info" onclick="viewApplication('<?php echo $app['application_id']; ?>')">
                    <i class="fas fa-eye"></i> View
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
          <tr>
            <td colspan="7" style="text-align:center;padding:48px 0;color:var(--text-muted);">
              <i class="fas fa-inbox" style="font-size:32px;display:block;margin-bottom:12px;opacity:0.3;"></i>
              <span style="font-size:13.5px;">No recent applications found</span>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="table-card-footer">
      <div class="tf-text">Showing <span class="tf-strong"><?php echo count($recent_applications); ?></span> of <span class="tf-strong"><?php echo $stats['total_applications'] ?? 0; ?></span> applications</div>
      <a href="applications.php" style="font-size:12.5px;color:var(--gold);font-weight:500;display:flex;align-items:center;gap:5px;">View all applications <i class="fas fa-arrow-right" style="font-size:10px;"></i></a>
    </div>
  </div>

</main>

<!-- FOOTER -->
<footer class="portal-footer">
  <div class="footer-copy">© 2026 Bold Footprint Initiatives. Admin Portal.</div>
  <div class="footer-links">
    <a href="/index.html"><i class="fas fa-home" style="font-size:10px;margin-right:3px;"></i> Main Site</a>
    <a href="/scholar-portal/index.php">Scholar Portal</a>
    <a href="/programs.html">Programmes</a>
    <a href="/contact.html">Contact</a>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  // Sidebar toggle
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebarOverlay');
  const toggle   = document.getElementById('mobileToggle');
  const openSB   = () => { sidebar.classList.add('active'); overlay.classList.add('active'); document.body.style.overflow = 'hidden'; };
  const closeSB  = () => { sidebar.classList.remove('active'); overlay.classList.remove('active'); document.body.style.overflow = ''; };
  toggle?.addEventListener('click', () => sidebar.classList.contains('active') ? closeSB() : openSB());
  overlay?.addEventListener('click', closeSB);
  window.addEventListener('resize', () => { if (window.innerWidth > 768) closeSB(); });

  // Live clock
  function updateClock() {
    const el = document.getElementById('headerTime');
    if (!el) return;
    const now = new Date();
    el.textContent = now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) + ' · ' + now.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
  }
  updateClock(); setInterval(updateClock, 30000);

  // Status update modal
  document.querySelectorAll('.status-update-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      showStatusUpdateModal(this.dataset.applicationId, this.dataset.currentStatus, '');
    });
  });

  function showStatusUpdateModal(appId, currentStatus, currentComments) {
    Swal.fire({
      title: 'Update Application Status',
      html: `
        <div style="text-align:left;">
          <p style="font-size:13px;color:#64748b;margin-bottom:16px;">Currently: <strong style="text-transform:capitalize;">${currentStatus || 'Unknown'}</strong></p>
          <div style="margin-bottom:16px;">
            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">New Status</label>
            <select id="swal-new-status" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;">
              <option value="">— Select status —</option>
              <option value="pending">Pending Review</option>
              <option value="under_review">Under Review</option>
              <option value="shortlisted">Shortlisted</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Admin Notes</label>
            <textarea id="swal-comments" rows="3" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;resize:vertical;outline:none;" placeholder="Optional notes...">${currentComments}</textarea>
          </div>
        </div>`,
      showCancelButton: true,
      confirmButtonText: 'Update Status',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#9F1239',
      preConfirm: () => {
        const ns = document.getElementById('swal-new-status').value;
        if (!ns) { Swal.showValidationMessage('Please select a status.'); return false; }
        return { newStatus: ns, comments: document.getElementById('swal-comments').value };
      }
    }).then(result => {
      if (result.isConfirmed && result.value) submitStatusUpdate(appId, result.value.newStatus, result.value.comments);
    });
  }

  function submitStatusUpdate(appId, newStatus, comments) {
    Swal.fire({ title: 'Updating…', allowOutsideClick: false, showConfirmButton: false, willOpen: () => Swal.showLoading() });
    const fd = new FormData();
    fd.append('application_id', appId);
    fd.append('new_status', newStatus);
    fd.append('admin_comments', comments);
    fetch('update_status.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(r => { if (!r.ok) throw new Error('Network error'); return r.json(); })
      .then(data => {
        if (data.success) {
          Swal.fire({ icon: 'success', title: 'Updated!', text: data.message || 'Status updated.', confirmButtonColor: '#C8A058' }).then(() => location.reload());
        } else throw new Error(data.message || 'Failed to update.');
      })
      .catch(err => Swal.fire({ icon: 'error', title: 'Error', text: err.message }));
  }

  function viewApplication(id) { window.location.href = `application-details.php?id=${id}`; }
</script>
</body>
</html>