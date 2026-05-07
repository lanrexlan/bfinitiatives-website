<?php
session_start();

error_log("Scholarships page accessed — Session: " . print_r($_SESSION, true));

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: admin-login.php');
    exit();
}

require_once 'includes/config.php';
require_once 'includes/db.php';

// ─── State ───────────────────────────────────────────────────────────────────
$error_message   = '';
$success_message = '';
$filter_status   = $_GET['status']   ?? 'all';
$filter_category = $_GET['category'] ?? 'all';
$search_term     = $_GET['search']   ?? '';
$view_mode       = $_GET['view']     ?? 'grid';
$current_page    = max(1, (int)($_GET['page'] ?? 1));
$per_page        = 9;
$total_items     = 0;
$total_pages     = 1;
$scholarships    = [];
$stats           = ['total' => 0, 'open' => 0, 'closed' => 0, 'draft' => 0];
$base_categories = ['Undergraduate', 'Masters', 'PhD', 'Postdoctoral', 'Research', 'Fellowship'];
$doc_count       = 0;
$app_count       = 0;

// ─── Table bootstrap ─────────────────────────────────────────────────────────
function ensureScholarshipsTable($conn) {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS scholarships (
            id               SERIAL PRIMARY KEY,
            title            VARCHAR(255) NOT NULL,
            description      TEXT,
            category         VARCHAR(100),
            host_institution VARCHAR(255),
            country          VARCHAR(100),
            deadline         DATE,
            amount           DECIMAL(12,2),
            currency         VARCHAR(10)  DEFAULT 'GBP',
            status           VARCHAR(20)  DEFAULT 'draft',
            requirements     TEXT,
            eligibility      TEXT,
            application_link VARCHAR(500),
            is_featured      BOOLEAN      DEFAULT FALSE,
            created_by       INTEGER,
            created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            updated_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

// ─── POST handler ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $db   = new Database();
        $conn = $db->getConnection();
        ensureScholarshipsTable($conn);
        $action = $_POST['action'];

        // Helper: build scholarship fields array
        $fields = function() {
            return [
                ':title'    => trim($_POST['title']            ?? ''),
                ':desc'     => trim($_POST['description']      ?? ''),
                ':cat'      => trim($_POST['category']         ?? ''),
                ':host'     => trim($_POST['host_institution'] ?? ''),
                ':country'  => trim($_POST['country']          ?? ''),
                ':deadline' => !empty($_POST['deadline']) ? $_POST['deadline'] : null,
                ':amount'   => isset($_POST['amount']) && $_POST['amount'] !== '' ? (float)$_POST['amount'] : null,
                ':currency' => $_POST['currency']  ?? 'GBP',
                ':status'   => $_POST['status']    ?? 'draft',
                ':req'      => trim($_POST['requirements'] ?? ''),
                ':elig'     => trim($_POST['eligibility']  ?? ''),
                ':link'     => trim($_POST['application_link'] ?? ''),
                ':featured' => isset($_POST['is_featured']) ? true : false,
            ];
        };

        if ($action === 'add') {
            $f = $fields();
            $f[':admin'] = $_SESSION['admin_id'] ?? 1;
            $stmt = $conn->prepare("
                INSERT INTO scholarships
                    (title,description,category,host_institution,country,deadline,
                     amount,currency,status,requirements,eligibility,application_link,
                     is_featured,created_by)
                VALUES
                    (:title,:desc,:cat,:host,:country,:deadline,
                     :amount,:currency,:status,:req,:elig,:link,
                     :featured,:admin)
            ");
            $stmt->execute($f);
            $_SESSION['success_message'] = "Scholarship <strong>" . htmlspecialchars(trim($_POST['title'] ?? '')) . "</strong> added successfully.";
            header('Location: scholarships.php');
            exit();
        }

        if ($action === 'edit' && !empty($_POST['id'])) {
            $f = $fields();
            $f[':id'] = (int)$_POST['id'];
            $stmt = $conn->prepare("
                UPDATE scholarships SET
                    title=:title, description=:desc, category=:cat,
                    host_institution=:host, country=:country, deadline=:deadline,
                    amount=:amount, currency=:currency, status=:status,
                    requirements=:req, eligibility=:elig, application_link=:link,
                    is_featured=:featured, updated_at=NOW()
                WHERE id=:id
            ");
            $stmt->execute($f);
            $_SESSION['success_message'] = "Scholarship updated successfully.";
            header('Location: scholarships.php');
            exit();
        }

        if ($action === 'delete' && !empty($_POST['id'])) {
            $conn->prepare("DELETE FROM scholarships WHERE id=:id")
                 ->execute([':id' => (int)$_POST['id']]);
            $_SESSION['success_message'] = "Scholarship deleted.";
            header('Location: scholarships.php');
            exit();
        }

        if ($action === 'toggle_status' && !empty($_POST['id'])) {
            $cur = $conn->prepare("SELECT status FROM scholarships WHERE id=:id");
            $cur->execute([':id' => (int)$_POST['id']]);
            $s   = $cur->fetchColumn();
            $new = ($s === 'open') ? 'closed' : 'open';
            $conn->prepare("UPDATE scholarships SET status=:s, updated_at=NOW() WHERE id=:id")
                 ->execute([':s' => $new, ':id' => (int)$_POST['id']]);
            $_SESSION['success_message'] = "Status changed to <strong>" . ucfirst($new) . "</strong>.";
            header('Location: ' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'scholarships.php'));
            exit();
        }

        if ($action === 'toggle_featured' && !empty($_POST['id'])) {
            $conn->prepare("UPDATE scholarships SET is_featured=NOT is_featured, updated_at=NOW() WHERE id=:id")
                 ->execute([':id' => (int)$_POST['id']]);
            header('Location: ' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'scholarships.php'));
            exit();
        }

        if ($action === 'duplicate' && !empty($_POST['id'])) {
            $row = $conn->prepare("SELECT * FROM scholarships WHERE id=:id");
            $row->execute([':id' => (int)$_POST['id']]);
            $orig = $row->fetch(PDO::FETCH_ASSOC);
            if ($orig) {
                $ins = $conn->prepare("
                    INSERT INTO scholarships
                        (title,description,category,host_institution,country,deadline,
                         amount,currency,status,requirements,eligibility,application_link,
                         is_featured,created_by)
                    VALUES
                        (:title,:desc,:cat,:host,:country,:deadline,
                         :amount,:currency,'draft',:req,:elig,:link,
                         false,:admin)
                ");
                $ins->execute([
                    ':title'   => '[Copy] ' . $orig['title'],
                    ':desc'    => $orig['description'],
                    ':cat'     => $orig['category'],
                    ':host'    => $orig['host_institution'],
                    ':country' => $orig['country'],
                    ':deadline'=> $orig['deadline'],
                    ':amount'  => $orig['amount'],
                    ':currency'=> $orig['currency'],
                    ':req'     => $orig['requirements'],
                    ':elig'    => $orig['eligibility'],
                    ':link'    => $orig['application_link'],
                    ':admin'   => $_SESSION['admin_id'] ?? 1,
                ]);
                $_SESSION['success_message'] = "Scholarship duplicated as a draft.";
            }
            header('Location: scholarships.php');
            exit();
        }

    } catch (Exception $e) {
        error_log("Scholarships POST: " . $e->getMessage());
        $error_message = "Error: " . $e->getMessage();
    }
}

// ─── Session flash ────────────────────────────────────────────────────────────
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// ─── Fetch ────────────────────────────────────────────────────────────────────
try {
    $db   = new Database();
    $conn = $db->getConnection();
    ensureScholarshipsTable($conn);

    // Stats
    $srows = $conn->query("SELECT status, COUNT(*) c FROM scholarships GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($srows as $r) {
        $key = strtolower($r['status']);
        $stats[$key] = (int)$r['c'];
        $stats['total'] += (int)$r['c'];
    }

    // Merged categories
    $db_cats    = $conn->query("SELECT DISTINCT category FROM scholarships WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
    $categories = array_unique(array_merge($base_categories, $db_cats));
    sort($categories);

    // Build filters
    $where  = "WHERE 1=1";
    $params = [];
    if ($filter_status !== 'all')   { $where .= " AND status=:st";  $params[':st']  = $filter_status;   }
    if ($filter_category !== 'all') { $where .= " AND category=:ct"; $params[':ct'] = $filter_category; }
    if (!empty($search_term)) {
        $where .= " AND (title ILIKE :s OR description ILIKE :s OR host_institution ILIKE :s OR country ILIKE :s)";
        $params[':s'] = '%' . $search_term . '%';
    }

    // Count
    $cs = $conn->prepare("SELECT COUNT(*) FROM scholarships $where");
    $cs->execute($params);
    $total_items = (int)$cs->fetchColumn();
    $total_pages = max(1, ceil($total_items / $per_page));
    $current_page = min($current_page, $total_pages);
    $offset = ($current_page - 1) * $per_page;

    // Rows — featured first, then by deadline ascending (NULLs last), then newest
    $ss = $conn->prepare("
        SELECT * FROM scholarships $where
        ORDER BY is_featured DESC,
                 deadline ASC NULLS LAST,
                 created_at DESC
        LIMIT :lim OFFSET :off
    ");
    foreach ($params as $k => $v) $ss->bindValue($k, $v);
    $ss->bindValue(':lim', $per_page, PDO::PARAM_INT);
    $ss->bindValue(':off', $offset,   PDO::PARAM_INT);
    $ss->execute();
    $scholarships = $ss->fetchAll(PDO::FETCH_ASSOC);

    // Sidebar notification counts
    try {
        $doc_count = (int)$conn->query("SELECT COUNT(*) FROM user_documents WHERE review_status='pending'")->fetchColumn();
        $app_count = (int)$conn->query("SELECT COUNT(*) FROM scholarship_applications WHERE status='pending'")->fetchColumn();
    } catch (Exception $e) { /* tables may not exist yet */ }

} catch (Exception $e) {
    error_log("Scholarships fetch: " . $e->getMessage());
    $error_message = $error_message ?: "Database error: " . $e->getMessage();
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
$admin_full_name  = $_SESSION['admin_name']  ?? 'Admin';
$admin_first_name = explode(' ', $admin_full_name)[0];
$admin_role       = ucfirst($_SESSION['admin_role'] ?? 'Administrator');

function fmtAmt($amount, $currency) {
    if ($amount === null || $amount === '') return null;
    $syms = ['GBP' => '£', 'USD' => '$', 'EUR' => '€', 'NGN' => '₦', 'CAD' => 'CA$', 'AUD' => 'A$'];
    $sym  = $syms[$currency] ?? ($currency . ' ');
    return $sym . number_format((float)$amount, 0);
}

function deadlineInfo($d) {
    if (empty($d)) return null;
    $ts   = strtotime($d);
    $diff = (int)(($ts - time()) / 86400);
    if ($diff < 0)  return ['text' => 'Expired',                 'cls' => 'di-expired'];
    if ($diff === 0) return ['text' => 'Closes today',           'cls' => 'di-urgent'];
    if ($diff <= 7)  return ['text' => "Closes in {$diff}d",     'cls' => 'di-soon'];
    if ($diff <= 30) return ['text' => date('d M', $ts),         'cls' => 'di-near'];
    return              ['text' => date('d M Y', $ts),           'cls' => 'di-far'];
}

function catIcon($cat) {
    $m = ['Undergraduate'=>'fa-book','Masters'=>'fa-graduation-cap','PhD'=>'fa-flask',
          'Postdoctoral'=>'fa-microscope','Research'=>'fa-search','Fellowship'=>'fa-award'];
    return $m[$cat] ?? 'fa-star';
}

function catColor($cat) {
    $m = ['Undergraduate'=>'#D97706','Masters'=>'#4361EE','PhD'=>'#7C3AED',
          'Postdoctoral'=>'#059669','Research'=>'#0284C7','Fellowship'=>'#C8A058'];
    return $m[$cat] ?? '#8A92A8';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Scholarships — Admin Portal</title>
  <link rel="icon" type="image/png" href="/Images/BFI_Logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
  <style>
    /* ═══════════════════════════════════════════════════════════
       DESIGN TOKENS  — exact match to admin-dashboard.php
    ═══════════════════════════════════════════════════════════ */
    :root {
      --midnight:#080E1C; --navy:#0D1829; --navy-mid:#132038; --navy-light:#1C2F52;
      --gold:#C8A058; --gold-bright:#E0B96C; --gold-pale:#F0D9A8;
      --cream:#FAF6EF; --cream-dark:#F2EAD8; --white:#FFFFFF;
      --text-primary:#0D1829; --text-secondary:#4A526A; --text-muted:#8A92A8;
      --border-light:#E8E4DA;
      --success:#059669;  --success-pale:rgba(5,150,105,.10);
      --warning:#D97706;  --warning-pale:rgba(217,119,6,.10);
      --danger:#DC2626;   --danger-pale:rgba(220,38,38,.10);
      --info:#0284C7;     --info-pale:rgba(2,132,199,.10);
      --admin-crimson:#9F1239; --admin-crimson-pale:rgba(159,18,57,.10);
      --font-display:'Cormorant Garamond',Georgia,serif;
      --font-body:'Outfit',-apple-system,sans-serif;
      --ease:cubic-bezier(.25,.46,.45,.94);
      --transition:.3s var(--ease);
      --shadow-sm:0 2px 8px rgba(8,14,28,.06);
      --shadow-md:0 8px 32px rgba(8,14,28,.10);
      --shadow-lg:0 20px 60px rgba(8,14,28,.14);
      --sidebar-width:268px; --header-height:64px;
      --r-sm:8px; --r-md:14px; --r-lg:20px; --r-xl:28px;
    }
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:var(--font-body);background:#F0F2F7;color:var(--text-primary);line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden;}
    a{text-decoration:none;color:inherit;}
    ::-webkit-scrollbar{width:6px;height:6px;}
    ::-webkit-scrollbar-track{background:transparent;}
    ::-webkit-scrollbar-thumb{background:#D1D5DB;border-radius:4px;}

    /* ─── SIDEBAR (identical to dashboard) ─── */
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
    .sidebar-user-name{font-size:13px;font-weight:500;color:var(--white);line-height:1.3;}
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

    /* ─── HEADER ─── */
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
    .header-avatar{width:32px;height:32px;border-radius:50%;background:var(--navy-light);border:2px solid var(--border-light);display:flex;align-items:center;justify-content:center;}
    .header-avatar-init{font-family:var(--font-display);font-size:13px;color:var(--gold-bright);}
    .header-admin-label{font-size:12px;font-weight:500;color:var(--text-primary);}
    .header-admin-role{font-size:10.5px;color:var(--text-muted);}

    /* ─── MAIN ─── */
    .main{margin-left:var(--sidebar-width);margin-top:var(--header-height);padding:26px;min-height:calc(100vh - var(--header-height));}

    /* ─── FLASH MESSAGES ─── */
    .flash{padding:14px 20px;border-radius:var(--r-md);margin-bottom:20px;font-size:13.5px;display:flex;align-items:center;gap:10px;border:1px solid transparent;animation:slideDown .3s var(--ease);}
    .flash-success{background:var(--success-pale);color:var(--success);border-color:rgba(5,150,105,.2);}
    .flash-error{background:var(--danger-pale);color:var(--danger);border-color:rgba(220,38,38,.2);}
    @keyframes slideDown{from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:translateY(0);}}

    /* ─── PAGE HERO ─── */
    .page-hero{background:var(--navy);border-radius:var(--r-xl);padding:28px 32px;margin-bottom:22px;position:relative;overflow:hidden;}
    .page-hero::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,.025) 1px,transparent 1px);background-size:30px 30px;}
    .page-hero::after{content:'';position:absolute;top:-40px;right:-40px;width:240px;height:240px;background:radial-gradient(circle,rgba(200,160,88,.09) 0%,transparent 65%);}
    .hero-inner{position:relative;z-index:1;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;}
    .hero-eyebrow{font-size:9.5px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:rgba(200,160,88,.65);margin-bottom:4px;display:flex;align-items:center;gap:6px;}
    .hero-title{font-family:var(--font-display);font-size:clamp(20px,2.5vw,28px);font-weight:500;color:var(--white);margin-bottom:3px;}
    .hero-title em{font-style:italic;color:var(--gold-bright);}
    .hero-sub{font-size:13px;color:rgba(255,255,255,.45);}
    .btn-gold{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:var(--gold);color:var(--midnight);font-family:var(--font-body);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-gold:hover{background:var(--gold-bright);transform:translateY(-1px);}
    .btn-ghost{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;background:rgba(255,255,255,.06);color:rgba(255,255,255,.75);font-family:var(--font-body);font-size:13px;border:1px solid rgba(255,255,255,.1);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-ghost:hover{background:rgba(255,255,255,.1);color:var(--white);}

    /* ─── STATS ROW ─── */
    .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:22px;}
    .stat-card{background:var(--white);border-radius:var(--r-lg);padding:22px 24px;border:1px solid var(--border-light);transition:var(--transition);position:relative;overflow:hidden;cursor:pointer;display:block;text-decoration:none;}
    .stat-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-md);border-color:transparent;}
    .stat-card.active-filter{border-bottom:3px solid var(--gold);box-shadow:var(--shadow-sm);}
    .stat-icon{position:absolute;top:18px;right:18px;width:44px;height:44px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:17px;}
    .si-navy{background:var(--navy);color:var(--gold-bright);}
    .si-green{background:var(--success-pale);color:var(--success);}
    .si-red{background:var(--danger-pale);color:var(--danger);}
    .si-gold{background:rgba(200,160,88,.12);color:var(--gold);}
    .stat-label{font-size:12px;color:var(--text-muted);margin-bottom:8px;}
    .stat-value{font-family:var(--font-display);font-size:36px;font-weight:500;color:var(--navy);line-height:1;margin-bottom:6px;}
    .stat-sub{font-size:11.5px;color:var(--text-muted);}

    /* ─── TOOLBAR ─── */
    .toolbar{background:var(--white);border-radius:var(--r-lg);padding:16px 20px;border:1px solid var(--border-light);margin-bottom:22px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
    .toolbar-search{flex:1;min-width:200px;position:relative;}
    .toolbar-search input{width:100%;padding:9px 12px 9px 36px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13px;background:var(--cream);color:var(--text-primary);outline:none;transition:var(--transition);}
    .toolbar-search input:focus{background:var(--white);border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,160,88,.12);}
    .toolbar-search i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:12px;pointer-events:none;}
    .toolbar-select{padding:9px 14px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13px;background:var(--cream);color:var(--text-primary);outline:none;cursor:pointer;transition:var(--transition);}
    .toolbar-select:focus{border-color:var(--gold);}
    .toolbar-divider{width:1px;height:32px;background:var(--border-light);flex-shrink:0;}
    .view-toggle{display:flex;background:var(--cream);border-radius:var(--r-sm);overflow:hidden;border:1px solid var(--border-light);}
    .view-btn{width:36px;height:36px;display:flex;align-items:center;justify-content:center;border:none;background:transparent;color:var(--text-muted);cursor:pointer;transition:var(--transition);font-size:13px;}
    .view-btn.active{background:var(--navy);color:var(--gold-bright);}
    .toolbar-clear{display:inline-flex;align-items:center;gap:5px;padding:9px 14px;background:transparent;border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:12px;color:var(--text-muted);cursor:pointer;transition:var(--transition);}
    .toolbar-clear:hover{border-color:var(--danger);color:var(--danger);}

    /* ─── SECTION LABEL ─── */
    .section-label{font-size:10px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;}

    /* ─── SCHOLARSHIP GRID ─── */
    .sch-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:28px;}
    .sch-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);overflow:hidden;transition:var(--transition);position:relative;display:flex;flex-direction:column;}
    .sch-card:hover{transform:translateY(-5px);box-shadow:var(--shadow-md);border-color:transparent;}
    .sch-card.is-featured{border-top:3px solid var(--gold);}
    .sch-card-body{padding:20px 20px 14px;flex:1;}
    .sch-badges{display:flex;align-items:center;gap:7px;margin-bottom:14px;flex-wrap:wrap;}
    .cat-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:500;}
    .status-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:10.5px;font-weight:600;letter-spacing:.3px;text-transform:uppercase;}
    .sp-open{background:var(--success-pale);color:var(--success);}
    .sp-closed{background:var(--danger-pale);color:var(--danger);}
    .sp-draft{background:var(--cream);color:var(--text-muted);border:1px solid var(--border-light);}
    .featured-star{margin-left:auto;color:var(--gold);font-size:14px;flex-shrink:0;}
    .sch-title{font-family:var(--font-display);font-size:19px;font-weight:500;color:var(--navy);line-height:1.25;margin-bottom:6px;}
    .sch-host{font-size:12.5px;color:var(--text-muted);display:flex;align-items:center;gap:5px;margin-bottom:12px;}
    .sch-meta{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;}
    .meta-chip{display:flex;align-items:center;gap:5px;font-size:12px;color:var(--text-secondary);background:var(--cream);padding:5px 10px;border-radius:20px;}
    .meta-chip i{font-size:10px;color:var(--text-muted);}
    .sch-desc{font-size:12.5px;color:var(--text-muted);line-height:1.55;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
    .sch-card-foot{padding:13px 20px;border-top:1px solid var(--border-light);display:flex;align-items:center;gap:7px;background:#FAFBFC;}
    .act-btn{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:var(--r-sm);font-family:var(--font-body);font-size:12px;font-weight:500;border:none;cursor:pointer;transition:var(--transition);}
    .ab-primary{background:var(--navy);color:var(--white);flex:1;justify-content:center;}
    .ab-primary:hover{background:var(--navy-light);}
    .ab-edit{background:rgba(200,160,88,.12);color:var(--gold);}
    .ab-edit:hover{background:var(--gold);color:var(--midnight);}
    .ab-toggle{background:var(--cream);color:var(--text-secondary);border:1px solid var(--border-light);}
    .ab-toggle:hover{background:var(--navy);color:var(--gold-bright);border-color:transparent;}
    .ab-dupe{background:var(--info-pale);color:var(--info);}
    .ab-dupe:hover{background:var(--info);color:var(--white);}
    .ab-delete{background:var(--danger-pale);color:var(--danger);}
    .ab-delete:hover{background:var(--danger);color:var(--white);}
    .ab-icon{width:32px;height:32px;padding:0;justify-content:center;font-size:13px;}

    /* ─── DEADLINE CHIPS ─── */
    .dl-chip{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:11.5px;font-weight:500;}
    .di-expired{background:var(--danger-pale);color:var(--danger);}
    .di-urgent{background:rgba(220,38,38,.15);color:var(--danger);font-weight:700;animation:pulse 1.5s infinite;}
    .di-soon{background:var(--warning-pale);color:var(--warning);}
    .di-near{background:var(--info-pale);color:var(--info);}
    .di-far{background:var(--success-pale);color:var(--success);}
    @keyframes pulse{0%,100%{opacity:1;}50%{opacity:.6;}}

    /* ─── LIST VIEW ─── */
    .list-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);overflow:hidden;margin-bottom:22px;}
    .list-table{width:100%;border-collapse:separate;border-spacing:0;}
    .list-table th{background:#F8F9FB;color:var(--text-muted);font-size:10.5px;font-weight:600;letter-spacing:.8px;text-transform:uppercase;padding:12px 16px;text-align:left;border-bottom:1px solid var(--border-light);}
    .list-table td{padding:14px 16px;vertical-align:middle;border-bottom:1px solid var(--border-light);font-size:13.5px;}
    .list-table tr:last-child td{border-bottom:none;}
    .list-table tbody tr{transition:background var(--transition);}
    .list-table tbody tr:hover{background:#F8F9FB;}

    /* ─── EMPTY STATE ─── */
    .empty-state{text-align:center;padding:72px 32px;}
    .empty-icon{font-size:52px;color:rgba(200,160,88,.25);margin-bottom:18px;}
    .empty-title{font-family:var(--font-display);font-size:26px;color:var(--navy);margin-bottom:8px;}
    .empty-title em{font-style:italic;color:var(--gold);}
    .empty-sub{font-size:13.5px;color:var(--text-muted);margin-bottom:24px;max-width:420px;margin-left:auto;margin-right:auto;}

    /* ─── PAGINATION ─── */
    .pag-wrap{display:flex;align-items:center;justify-content:space-between;padding:14px 0;flex-wrap:wrap;gap:10px;margin-bottom:22px;}
    .pag-info{font-size:12.5px;color:var(--text-muted);}
    .pag-list{display:flex;gap:4px;list-style:none;}
    .pag-item{width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:var(--r-sm);font-size:13px;font-weight:500;cursor:pointer;transition:var(--transition);border:1px solid var(--border-light);background:var(--white);color:var(--text-secondary);}
    .pag-item:hover{background:var(--cream);color:var(--navy);}
    .pag-item.active{background:var(--navy);color:var(--gold-bright);border-color:var(--navy);}
    .pag-item.disabled{opacity:.4;pointer-events:none;}

    /* ─── MODAL ─── */
    .modal-overlay{position:fixed;inset:0;background:rgba(8,14,28,.65);z-index:500;display:none;align-items:center;justify-content:center;padding:24px;backdrop-filter:blur(6px);}
    .modal-overlay.open{display:flex;animation:fadeIn .2s var(--ease);}
    @keyframes fadeIn{from{opacity:0;}to{opacity:1;}}
    .modal-box{background:var(--white);border-radius:var(--r-xl);width:100%;max-width:700px;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-lg);animation:slideUp .25s var(--ease);}
    @keyframes slideUp{from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);}}
    .modal-head{padding:22px 26px;border-bottom:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--white);z-index:1;}
    .modal-title{font-family:var(--font-display);font-size:24px;font-weight:500;color:var(--navy);}
    .modal-title em{font-style:italic;color:var(--gold);}
    .modal-close{width:32px;height:32px;border:none;background:var(--cream);border-radius:var(--r-sm);cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-muted);transition:var(--transition);font-size:14px;}
    .modal-close:hover{background:var(--danger-pale);color:var(--danger);}
    .modal-body{padding:26px;}
    .modal-foot{padding:18px 26px;border-top:1px solid var(--border-light);display:flex;gap:10px;justify-content:flex-end;position:sticky;bottom:0;background:var(--white);}
    .form-section{margin-bottom:22px;}
    .form-section-label{font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);margin-bottom:14px;padding-bottom:8px;border-bottom:1px solid var(--border-light);}
    .form-row{display:grid;gap:16px;margin-bottom:0;}
    .form-row-2{grid-template-columns:1fr 1fr;}
    .form-row-3{grid-template-columns:1fr 1fr 1fr;}
    .form-group{display:flex;flex-direction:column;gap:5px;}
    .form-group label{font-size:11.5px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px;}
    .form-group input,.form-group select,.form-group textarea{padding:10px 14px;border:1.5px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13.5px;color:var(--text-primary);background:var(--white);outline:none;transition:var(--transition);}
    .form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,160,88,.12);}
    .form-group textarea{resize:vertical;min-height:80px;}
    .form-group small{font-size:11.5px;color:var(--text-muted);}
    .form-check-row{display:flex;align-items:center;gap:10px;padding:12px 0;border-top:1px solid var(--border-light);margin-top:6px;}
    .form-check-row input[type=checkbox]{width:18px;height:18px;accent-color:var(--gold);cursor:pointer;flex-shrink:0;}
    .form-check-row label{font-size:13.5px;color:var(--text-secondary);cursor:pointer;}
    .modal-btn-primary{padding:11px 24px;background:var(--navy);color:var(--white);border:none;border-radius:var(--r-sm);font-family:var(--font-body);font-size:13.5px;font-weight:500;cursor:pointer;transition:var(--transition);display:inline-flex;align-items:center;gap:7px;}
    .modal-btn-primary:hover{background:var(--navy-light);}
    .modal-btn-secondary{padding:11px 20px;background:var(--cream);color:var(--text-secondary);border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13.5px;cursor:pointer;transition:var(--transition);}
    .modal-btn-secondary:hover{background:var(--cream-dark);}

    /* ─── DETAIL MODAL ─── */
    .detail-section{margin-bottom:20px;}
    .detail-label{font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px;}
    .detail-value{font-size:14px;color:var(--text-primary);line-height:1.6;}

    /* ─── SIDEBAR OVERLAY ─── */
    .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:150;backdrop-filter:blur(4px);}
    .sidebar-overlay.active{display:block;}

    /* ─── FOOTER ─── */
    .portal-footer{margin-left:var(--sidebar-width);background:var(--white);padding:14px 26px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;}
    .footer-copy{font-size:11.5px;color:var(--text-muted);}
    .footer-links{display:flex;gap:18px;}
    .footer-links a{font-size:11.5px;color:var(--text-muted);transition:color var(--transition);}
    .footer-links a:hover{color:var(--gold);}

    /* ─── RESPONSIVE ─── */
    @media(max-width:1200px){.sch-grid{grid-template-columns:repeat(2,1fr);}.stats-row{grid-template-columns:repeat(2,1fr);}}
    @media(max-width:1024px){.form-row-3{grid-template-columns:1fr 1fr;}}
    @media(max-width:768px){
      .sidebar{transform:translateX(-100%);}.sidebar.active{transform:translateX(0);}
      .header,.main,.portal-footer{left:0;margin-left:0;}
      .mobile-toggle{display:flex;}.header-breadcrumb{display:none;}
      .sch-grid{grid-template-columns:1fr;}
      .stats-row{grid-template-columns:repeat(2,1fr);}
      .form-row-2,.form-row-3{grid-template-columns:1fr;}
      .toolbar{gap:8px;}
      .toolbar-search,.toolbar-select{width:100%;}
      .hero-inner{flex-direction:column;}
    }
    @media(max-width:480px){.stats-row{grid-template-columns:1fr;}}
  </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ═══════════════════════════════ SIDEBAR ═══════════════════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-top">
    <div class="sidebar-logo">
      <div class="sidebar-logomark">
        <svg viewBox="0 0 22 22" fill="none">
          <path d="M4 18L11 4L18 18" stroke="#080E1C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M7 13H15" stroke="#080E1C" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
      </div>
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
    <div class="nav-item">
      <a href="applications.php" class="nav-link"><i class="fas fa-tasks"></i> Applications
        <?php if ($app_count > 0): ?><span class="nav-badge"><?php echo $app_count; ?></span><?php endif; ?>
      </a>
    </div>
    <div class="nav-section-label">Management</div>
    <div class="nav-item">
      <a href="admin-document-review.php" class="nav-link"><i class="fas fa-file-alt"></i> Review Documents
        <?php if ($doc_count > 0): ?><span class="nav-badge"><?php echo $doc_count; ?></span><?php endif; ?>
      </a>
    </div>
    <div class="nav-item"><a href="scholarships.php" class="nav-link active"><i class="fas fa-graduation-cap"></i> Scholarships</a></div>
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

<!-- ═══════════════════════════════ HEADER ════════════════════════════════ -->
<header class="header">
  <div class="header-left">
    <button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
    <div class="header-breadcrumb">
      <span>Admin</span><span class="sep">/</span><strong>Scholarships</strong>
    </div>
  </div>
  <div class="header-right">
    <div class="header-time" id="headerTime"></div>
    <a href="reports.php"><button class="header-icon-btn" title="Reports"><i class="fas fa-chart-bar"></i></button></a>
    <button class="header-icon-btn" title="Notifications">
      <i class="fas fa-bell"></i>
      <?php if ($app_count > 0 || $doc_count > 0): ?><div class="notif-dot"></div><?php endif; ?>
    </button>
    <div class="header-avatar-wrap">
      <div class="header-avatar"><div class="header-avatar-init"><?php echo strtoupper(substr($admin_first_name,0,1)); ?></div></div>
      <div>
        <div class="header-admin-label"><?php echo htmlspecialchars($admin_first_name); ?></div>
        <div class="header-admin-role"><?php echo htmlspecialchars($admin_role); ?></div>
      </div>
    </div>
  </div>
</header>

<!-- ═══════════════════════════════ MAIN ══════════════════════════════════ -->
<main class="main">

  <?php if ($error_message): ?>
    <div class="flash flash-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
  <?php endif; ?>
  <?php if ($success_message): ?>
    <div class="flash flash-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
  <?php endif; ?>

  <!-- PAGE HERO -->
  <div class="page-hero">
    <div class="hero-inner">
      <div>
        <div class="hero-eyebrow"><i class="fas fa-graduation-cap" style="font-size:8px;"></i> Scholarship Library</div>
        <div class="hero-title">Manage <em>Scholarships</em></div>
        <div class="hero-sub">
          <?php echo $stats['total']; ?> scholarships &nbsp;·&nbsp;
          <span style="color:rgba(5,150,105,.9);"><?php echo $stats['open'] ?? 0; ?> open</span>
          &nbsp;·&nbsp;
          <span style="color:rgba(217,119,6,.9);"><?php echo $stats['draft'] ?? 0; ?> draft</span>
        </div>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button class="btn-gold" id="openAddModal"><i class="fas fa-plus"></i> Add Scholarship</button>
        <a href="reports.php" class="btn-ghost"><i class="fas fa-chart-bar"></i> Analytics</a>
      </div>
    </div>
  </div>

  <!-- STATS -->
  <div class="stats-row">
    <?php
    $stat_cards = [
      ['all',    'Total',  $stats['total'],        'si-navy',  'fa-graduation-cap', 'All programmes'],
      ['open',   'Open',   $stats['open']  ?? 0,   'si-green', 'fa-door-open',      'Accepting now'],
      ['closed', 'Closed', $stats['closed']?? 0,   'si-red',   'fa-door-closed',    'No longer open'],
      ['draft',  'Drafts', $stats['draft'] ?? 0,   'si-gold',  'fa-pencil-alt',     'Unpublished'],
    ];
    $value_colors = ['all'=>'var(--navy)','open'=>'var(--success)','closed'=>'var(--danger)','draft'=>'var(--gold)'];
    foreach ($stat_cards as [$key, $label, $val, $icon_cls, $icon, $sub]):
      $qs = http_build_query(['status'=>$key, 'category'=>$filter_category, 'search'=>$search_term, 'view'=>$view_mode]);
    ?>
    <a href="?<?php echo $qs; ?>" class="stat-card <?php echo $filter_status === $key ? 'active-filter' : ''; ?>">
      <div class="stat-icon <?php echo $icon_cls; ?>"><i class="fas <?php echo $icon; ?>"></i></div>
      <div class="stat-label"><?php echo $label; ?></div>
      <div class="stat-value" style="color:<?php echo $value_colors[$key]; ?>;"><?php echo $val; ?></div>
      <div class="stat-sub"><?php echo $sub; ?></div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- TOOLBAR / FILTERS -->
  <form method="GET" action="scholarships.php" id="filterForm">
    <div class="toolbar">
      <div class="toolbar-search">
        <i class="fas fa-search"></i>
        <input type="text" name="search" id="searchInput"
               placeholder="Search by title, institution, country…"
               value="<?php echo htmlspecialchars($search_term); ?>">
      </div>

      <select name="status" class="toolbar-select" onchange="this.form.submit()">
        <option value="all"    <?php echo $filter_status==='all'    ?'selected':''; ?>>All Statuses</option>
        <option value="open"   <?php echo $filter_status==='open'   ?'selected':''; ?>>Open</option>
        <option value="closed" <?php echo $filter_status==='closed' ?'selected':''; ?>>Closed</option>
        <option value="draft"  <?php echo $filter_status==='draft'  ?'selected':''; ?>>Draft</option>
      </select>

      <select name="category" class="toolbar-select" onchange="this.form.submit()">
        <option value="all" <?php echo $filter_category==='all'?'selected':''; ?>>All Categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $filter_category===$c?'selected':''; ?>>
            <?php echo htmlspecialchars($c); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <div class="toolbar-divider"></div>

      <div class="view-toggle">
        <button type="submit" name="view" value="grid" class="view-btn <?php echo $view_mode==='grid'?'active':''; ?>" title="Grid view">
          <i class="fas fa-th"></i>
        </button>
        <button type="submit" name="view" value="list" class="view-btn <?php echo $view_mode==='list'?'active':''; ?>" title="List view">
          <i class="fas fa-list"></i>
        </button>
      </div>

      <?php if (!empty($search_term) || $filter_status!=='all' || $filter_category!=='all'): ?>
        <a href="scholarships.php?view=<?php echo $view_mode; ?>" class="toolbar-clear">
          <i class="fas fa-times"></i> Clear
        </a>
      <?php endif; ?>

      <!-- hidden: preserve view in submit -->
      <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_mode); ?>">
    </div>
  </form>

  <!-- RESULT COUNT -->
  <div class="section-label">
    <span>
      <?php echo $total_items; ?> scholarship<?php echo $total_items!==1?'s':''; ?> found
      <?php if (!empty($search_term)): ?>
        — results for "<em style="letter-spacing:0;font-style:italic;"><?php echo htmlspecialchars($search_term); ?></em>"
      <?php endif; ?>
    </span>
    <?php if ($total_items > 0): ?>
      <span style="font-size:11px;font-style:italic;letter-spacing:0;color:var(--text-muted);">
        Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
      </span>
    <?php endif; ?>
  </div>

  <?php if (empty($scholarships)): ?>
    <!-- EMPTY STATE -->
    <div class="empty-state">
      <div class="empty-icon"><i class="fas fa-graduation-cap"></i></div>
      <div class="empty-title">No scholarships <em>here yet</em></div>
      <div class="empty-sub">
        <?php if (!empty($search_term) || $filter_status!=='all' || $filter_category!=='all'): ?>
          Try clearing your filters or searching for something else.
        <?php else: ?>
          Start building your scholarship library. Add your first opportunity and watch scholars find their path.
        <?php endif; ?>
      </div>
      <?php if (empty($search_term) && $filter_status==='all' && $filter_category==='all'): ?>
        <button class="btn-gold" id="openAddModalEmpty"><i class="fas fa-plus"></i> Add First Scholarship</button>
      <?php else: ?>
        <a href="scholarships.php" class="btn-gold"><i class="fas fa-sync-alt"></i> Reset Filters</a>
      <?php endif; ?>
    </div>

  <?php elseif ($view_mode === 'list'): ?>
    <!-- ─── LIST VIEW ─── -->
    <div class="list-card">
      <div style="overflow-x:auto;">
        <table class="list-table">
          <thead>
            <tr>
              <th>Scholarship</th>
              <th>Category</th>
              <th>Host Institution</th>
              <th>Deadline</th>
              <th>Award</th>
              <th>Status</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($scholarships as $s):
              $dl  = deadlineInfo($s['deadline']);
              $amt = fmtAmt($s['amount'], $s['currency'] ?? 'GBP');
              $col = catColor($s['category'] ?? '');
            ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <?php if ($s['is_featured']): ?>
                    <i class="fas fa-star" style="color:var(--gold);font-size:11px;flex-shrink:0;" title="Featured"></i>
                  <?php else: ?>
                    <span style="width:11px;flex-shrink:0;"></span>
                  <?php endif; ?>
                  <div>
                    <div style="font-weight:500;color:var(--navy);font-size:14px;line-height:1.3;"><?php echo htmlspecialchars($s['title']); ?></div>
                    <?php if (!empty($s['country'])): ?>
                      <div style="font-size:11.5px;color:var(--text-muted);margin-top:2px;">
                        <i class="fas fa-map-marker-alt" style="font-size:10px;"></i> <?php echo htmlspecialchars($s['country']); ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td>
                <?php if (!empty($s['category'])): ?>
                  <span class="cat-badge" style="background:<?php echo $col; ?>22;color:<?php echo $col; ?>;">
                    <i class="fas <?php echo catIcon($s['category']); ?>"></i>
                    <?php echo htmlspecialchars($s['category']); ?>
                  </span>
                <?php else: ?>
                  <span style="color:var(--text-muted);font-size:12px;">—</span>
                <?php endif; ?>
              </td>
              <td style="font-size:12.5px;color:var(--text-secondary);"><?php echo htmlspecialchars($s['host_institution'] ?? '—'); ?></td>
              <td>
                <?php if ($dl): ?>
                  <span class="dl-chip <?php echo $dl['cls']; ?>">
                    <i class="fas fa-calendar-alt" style="font-size:10px;"></i> <?php echo $dl['text']; ?>
                  </span>
                <?php else: ?>
                  <span style="color:var(--text-muted);font-size:12px;">No deadline</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($amt): ?>
                  <span style="font-family:var(--font-display);font-size:16px;color:var(--navy);font-weight:500;"><?php echo $amt; ?></span>
                <?php else: ?>
                  <span style="color:var(--text-muted);font-size:12px;">—</span>
                <?php endif; ?>
              </td>
              <td><span class="status-pill sp-<?php echo $s['status']; ?>"><?php echo ucfirst($s['status']); ?></span></td>
              <td>
                <div style="display:flex;gap:6px;justify-content:flex-end;">
                  <button class="act-btn ab-edit ab-icon edit-btn" title="Edit"
                    <?php echo buildDataAttrs($s); ?>>
                    <i class="fas fa-pencil-alt"></i>
                  </button>
                  <button class="act-btn ab-toggle ab-icon detail-btn" data-id="<?php echo $s['id']; ?>" title="View details">
                    <i class="fas fa-eye"></i>
                  </button>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                    <button type="submit" class="act-btn ab-toggle ab-icon"
                      title="<?php echo $s['status']==='open' ? 'Close' : 'Open'; ?> scholarship">
                      <i class="fas fa-<?php echo $s['status']==='open' ? 'lock' : 'lock-open'; ?>"></i>
                    </button>
                  </form>
                  <button class="act-btn ab-delete ab-icon delete-btn"
                    data-id="<?php echo $s['id']; ?>"
                    data-title="<?php echo htmlspecialchars($s['title']); ?>"
                    title="Delete">
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php else: ?>
    <!-- ─── GRID VIEW ─── -->
    <div class="sch-grid">
      <?php foreach ($scholarships as $s):
        $dl  = deadlineInfo($s['deadline']);
        $amt = fmtAmt($s['amount'], $s['currency'] ?? 'GBP');
        $col = catColor($s['category'] ?? '');
      ?>
      <div class="sch-card <?php echo $s['is_featured'] ? 'is-featured' : ''; ?>">
        <div class="sch-card-body">

          <!-- Badges row -->
          <div class="sch-badges">
            <?php if (!empty($s['category'])): ?>
              <span class="cat-badge" style="background:<?php echo $col; ?>22;color:<?php echo $col; ?>;">
                <i class="fas <?php echo catIcon($s['category']); ?>"></i>
                <?php echo htmlspecialchars($s['category']); ?>
              </span>
            <?php endif; ?>
            <span class="status-pill sp-<?php echo $s['status']; ?>"><?php echo ucfirst($s['status']); ?></span>
            <?php if ($s['is_featured']): ?>
              <i class="fas fa-star featured-star" title="Featured scholarship"></i>
            <?php endif; ?>
          </div>

          <!-- Title -->
          <div class="sch-title"><?php echo htmlspecialchars($s['title']); ?></div>

          <!-- Host -->
          <?php if (!empty($s['host_institution']) || !empty($s['country'])): ?>
            <div class="sch-host">
              <i class="fas fa-university"></i>
              <?php echo htmlspecialchars($s['host_institution'] ?? ''); ?>
              <?php if (!empty($s['host_institution']) && !empty($s['country'])): ?> &nbsp;·&nbsp; <?php endif; ?>
              <?php if (!empty($s['country'])): ?>
                <i class="fas fa-map-marker-alt" style="font-size:10px;"></i> <?php echo htmlspecialchars($s['country']); ?>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <!-- Meta chips -->
          <div class="sch-meta">
            <?php if ($dl): ?>
              <div class="dl-chip <?php echo $dl['cls']; ?>">
                <i class="fas fa-calendar-alt" style="font-size:10px;"></i> <?php echo $dl['text']; ?>
              </div>
            <?php endif; ?>
            <?php if ($amt): ?>
              <div class="meta-chip">
                <i class="fas fa-sterling-sign"></i> <?php echo $amt; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Description snippet -->
          <?php if (!empty($s['description'])): ?>
            <p class="sch-desc"><?php echo htmlspecialchars($s['description']); ?></p>
          <?php endif; ?>

        </div><!-- /sch-card-body -->

        <div class="sch-card-foot">
          <!-- Primary CTA -->
          <?php if (!empty($s['application_link'])): ?>
            <a href="<?php echo htmlspecialchars($s['application_link']); ?>" target="_blank" rel="noopener" class="act-btn ab-primary">
              <i class="fas fa-external-link-alt"></i> Apply
            </a>
          <?php else: ?>
            <button class="act-btn ab-primary detail-btn" data-id="<?php echo $s['id']; ?>">
              <i class="fas fa-eye"></i> Details
            </button>
          <?php endif; ?>

          <!-- Edit -->
          <button class="act-btn ab-edit ab-icon edit-btn" title="Edit"
            <?php echo buildDataAttrs($s); ?>>
            <i class="fas fa-pencil-alt"></i>
          </button>

          <!-- Toggle status -->
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
            <button type="submit" class="act-btn ab-toggle ab-icon"
              title="<?php echo $s['status']==='open' ? 'Close scholarship' : 'Open scholarship'; ?>">
              <i class="fas fa-<?php echo $s['status']==='open' ? 'lock' : 'lock-open'; ?>"></i>
            </button>
          </form>

          <!-- Duplicate -->
          <form method="POST" style="display:inline;" class="dupe-form" data-title="<?php echo htmlspecialchars($s['title']); ?>">
            <input type="hidden" name="action" value="duplicate">
            <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
            <button type="submit" class="act-btn ab-dupe ab-icon" title="Duplicate as draft">
              <i class="fas fa-copy"></i>
            </button>
          </form>

          <!-- Delete -->
          <button class="act-btn ab-delete ab-icon delete-btn"
            data-id="<?php echo $s['id']; ?>"
            data-title="<?php echo htmlspecialchars($s['title']); ?>"
            title="Delete scholarship">
            <i class="fas fa-trash"></i>
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- PAGINATION -->
  <?php if ($total_pages > 1): ?>
  <div class="pag-wrap">
    <div class="pag-info">
      Showing <?php echo ($current_page-1)*$per_page+1; ?>–<?php echo min($current_page*$per_page,$total_items); ?>
      of <?php echo $total_items; ?> scholarships
    </div>
    <ul class="pag-list">
      <?php
      $base = ['status'=>$filter_status,'category'=>$filter_category,'search'=>$search_term,'view'=>$view_mode];
      $prev = max(1, $current_page-1);
      $next = min($total_pages, $current_page+1);
      echo '<li><a href="?'.http_build_query(array_merge($base,['page'=>$prev])).'" class="pag-item'.($current_page===1?' disabled':'').'"><i class="fas fa-chevron-left" style="font-size:11px;"></i></a></li>';
      $s = max(1,$current_page-2); $e = min($total_pages,$s+4);
      for($i=$s;$i<=$e;$i++):
        echo '<li><a href="?'.http_build_query(array_merge($base,['page'=>$i])).'" class="pag-item'.($i===$current_page?' active':'').'">'.$i.'</a></li>';
      endfor;
      echo '<li><a href="?'.http_build_query(array_merge($base,['page'=>$next])).'" class="pag-item'.($current_page===$total_pages?' disabled':'').'"><i class="fas fa-chevron-right" style="font-size:11px;"></i></a></li>';
      ?>
    </ul>
  </div>
  <?php endif; ?>

</main>

<footer class="portal-footer">
  <div class="footer-copy">© 2026 Bold Footprint Initiatives. Admin Portal.</div>
  <div class="footer-links">
    <a href="/index.html"><i class="fas fa-home" style="font-size:10px;margin-right:3px;"></i> Main Site</a>
    <a href="/scholar-portal/index.php">Scholar Portal</a>
    <a href="/programs.html">Programmes</a>
  </div>
</footer>

<!-- ═══════════════════ ADD / EDIT MODAL ════════════════════════════════ -->
<div class="modal-overlay" id="scholarshipModal">
  <div class="modal-box">
    <div class="modal-head">
      <div class="modal-title" id="modalTitle">Add <em>Scholarship</em></div>
      <button class="modal-close" id="closeModal" type="button"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="scholarships.php" id="scholarshipForm" novalidate>
      <input type="hidden" name="action" id="formAction" value="add">
      <input type="hidden" name="id"     id="formId"     value="">
      <div class="modal-body">

        <div class="form-section">
          <div class="form-section-label">Basic Information</div>
          <div class="form-row" style="margin-bottom:16px;">
            <div class="form-group">
              <label>Title <span style="color:var(--danger);">*</span></label>
              <input type="text" name="title" id="fTitle" required placeholder="e.g. Commonwealth Masters Scholarship 2026">
            </div>
          </div>
          <div class="form-row form-row-2" style="margin-bottom:16px;">
            <div class="form-group">
              <label>Category</label>
              <select name="category" id="fCategory">
                <option value="">— Select category —</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                <?php endforeach; ?>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="form-group">
              <label>Status</label>
              <select name="status" id="fStatus">
                <option value="draft">Draft (hidden from scholars)</option>
                <option value="open">Open (accepting applications)</option>
                <option value="closed">Closed</option>
              </select>
            </div>
          </div>
          <div class="form-row form-row-2" style="margin-bottom:0;">
            <div class="form-group">
              <label>Host Institution</label>
              <input type="text" name="host_institution" id="fHost" placeholder="e.g. University of Oxford">
            </div>
            <div class="form-group">
              <label>Country</label>
              <input type="text" name="country" id="fCountry" placeholder="e.g. United Kingdom">
            </div>
          </div>
        </div>

        <div class="form-section">
          <div class="form-section-label">Award Details</div>
          <div class="form-row form-row-3" style="margin-bottom:0;">
            <div class="form-group">
              <label>Application Deadline</label>
              <input type="date" name="deadline" id="fDeadline">
            </div>
            <div class="form-group">
              <label>Award Amount</label>
              <input type="number" name="amount" id="fAmount" placeholder="e.g. 15000" min="0" step="any">
            </div>
            <div class="form-group">
              <label>Currency</label>
              <select name="currency" id="fCurrency">
                <option value="GBP">£ GBP</option>
                <option value="USD">$ USD</option>
                <option value="EUR">€ EUR</option>
                <option value="NGN">₦ NGN</option>
                <option value="CAD">CA$ CAD</option>
                <option value="AUD">A$ AUD</option>
              </select>
            </div>
          </div>
        </div>

        <div class="form-section">
          <div class="form-section-label">Description & Criteria</div>
          <div class="form-row" style="margin-bottom:16px;">
            <div class="form-group">
              <label>Overview / Description</label>
              <textarea name="description" id="fDescription" rows="3"
                        placeholder="Brief overview of what this scholarship offers…"></textarea>
            </div>
          </div>
          <div class="form-row form-row-2" style="margin-bottom:0;">
            <div class="form-group">
              <label>Eligibility Criteria</label>
              <textarea name="eligibility" id="fEligibility" rows="4"
                        placeholder="Who can apply — nationality, degree, GPA…"></textarea>
            </div>
            <div class="form-group">
              <label>Required Documents</label>
              <textarea name="requirements" id="fRequirements" rows="4"
                        placeholder="CV, transcripts, reference letters…"></textarea>
            </div>
          </div>
        </div>

        <div class="form-section" style="margin-bottom:0;">
          <div class="form-section-label">Link & Visibility</div>
          <div class="form-row" style="margin-bottom:0;">
            <div class="form-group">
              <label>Application / Info URL</label>
              <input type="url" name="application_link" id="fLink" placeholder="https://…">
              <small>External link where scholars can apply or find out more</small>
            </div>
          </div>
          <div class="form-check-row">
            <input type="checkbox" name="is_featured" id="fFeatured" value="1">
            <label for="fFeatured">
              <i class="fas fa-star" style="color:var(--gold);margin-right:5px;"></i>
              <strong>Feature this scholarship</strong> — pins it to the top of all listings
            </label>
          </div>
        </div>

      </div><!-- /modal-body -->
      <div class="modal-foot">
        <button type="button" class="modal-btn-secondary" id="cancelModal">Cancel</button>
        <button type="submit" class="modal-btn-primary" id="modalSubmitBtn">
          <i class="fas fa-save"></i> <span id="modalSubmitLabel">Save Scholarship</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ═══════════════════ DETAIL MODAL ════════════════════════════════════ -->
<div class="modal-overlay" id="detailModal">
  <div class="modal-box">
    <div class="modal-head">
      <div class="modal-title">Scholarship <em>Details</em></div>
      <button class="modal-close" id="closeDetailModal" type="button"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" id="detailModalBody"><!-- filled by JS --></div>
    <div class="modal-foot">
      <button type="button" class="modal-btn-secondary" id="cancelDetailModal">Close</button>
      <button type="button" class="modal-btn-primary" id="detailEditBtn">
        <i class="fas fa-pencil-alt"></i> Edit Scholarship
      </button>
    </div>
  </div>
</div>

<!-- Hidden delete form -->
<form method="POST" id="deleteForm" style="display:none;">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" id="deleteFormId">
</form>

<!-- ═══════════════════ SCRIPTS ═══════════════════════════════════════════ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// ─── Scholarship data for JS (from PHP) ──────────────────────────────────────
const scholarshipsData = <?php echo json_encode(array_column($scholarships, null, 'id')); ?>;

// ─── Clock ───────────────────────────────────────────────────────────────────
(function tick(){
  const el = document.getElementById('headerTime');
  if(!el) return;
  const n = new Date();
  el.textContent = n.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'}) +
                   ' · ' + n.toLocaleDateString('en-GB',{day:'numeric',month:'short'});
})();
setInterval(()=>{
  const el=document.getElementById('headerTime'); if(!el)return;
  const n=new Date();
  el.textContent=n.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'})+
                 ' · '+n.toLocaleDateString('en-GB',{day:'numeric',month:'short'});
},30000);

// ─── Sidebar ─────────────────────────────────────────────────────────────────
const sidebar  = document.getElementById('sidebar');
const sbOverlay= document.getElementById('sidebarOverlay');
const toggle   = document.getElementById('mobileToggle');
const openSB   = ()=>{ sidebar.classList.add('active'); sbOverlay.classList.add('active'); document.body.style.overflow='hidden'; };
const closeSB  = ()=>{ sidebar.classList.remove('active'); sbOverlay.classList.remove('active'); document.body.style.overflow=''; };
toggle?.addEventListener('click', ()=> sidebar.classList.contains('active') ? closeSB() : openSB());
sbOverlay?.addEventListener('click', closeSB);
window.addEventListener('resize', ()=>{ if(window.innerWidth > 768) closeSB(); });

// ─── Modal helpers ────────────────────────────────────────────────────────────
const modalOverlay    = document.getElementById('scholarshipModal');
const detailOverlay   = document.getElementById('detailModal');
let   currentEditId   = null;

function openModal(mode='add', data={}) {
  document.getElementById('formAction').value     = mode;
  document.getElementById('formId').value         = data.id     || '';
  document.getElementById('fTitle').value         = data.title  || '';
  document.getElementById('fDescription').value   = data.description || '';
  document.getElementById('fHost').value          = data.host_institution || '';
  document.getElementById('fCountry').value       = data.country       || '';
  document.getElementById('fDeadline').value      = data.deadline      || '';
  document.getElementById('fAmount').value        = data.amount        || '';
  document.getElementById('fRequirements').value  = data.requirements  || '';
  document.getElementById('fEligibility').value   = data.eligibility   || '';
  document.getElementById('fLink').value          = data.application_link || '';
  document.getElementById('fFeatured').checked    = data.is_featured == true || data.is_featured == '1' || data.is_featured == 1;

  setSelectVal('fCategory', data.category || '');
  setSelectVal('fStatus',   data.status   || 'draft');
  setSelectVal('fCurrency', data.currency || 'GBP');

  document.getElementById('modalTitle').innerHTML       = mode==='add' ? 'Add <em>Scholarship</em>' : 'Edit <em>Scholarship</em>';
  document.getElementById('modalSubmitLabel').textContent= mode==='add' ? 'Save Scholarship'         : 'Update Scholarship';

  modalOverlay.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeModal() {
  modalOverlay.classList.remove('open');
  document.body.style.overflow = '';
}

function setSelectVal(id, val) {
  const s = document.getElementById(id); if(!s) return;
  for(let i=0;i<s.options.length;i++) if(s.options[i].value===val){ s.selectedIndex=i; return; }
}

// Open add modal
document.getElementById('openAddModal')?.addEventListener('click', ()=> openModal('add'));
document.getElementById('openAddModalEmpty')?.addEventListener('click', ()=> openModal('add'));
document.getElementById('closeModal')?.addEventListener('click', closeModal);
document.getElementById('cancelModal')?.addEventListener('click', closeModal);
modalOverlay?.addEventListener('click', e=>{ if(e.target===modalOverlay) closeModal(); });

// ─── Edit buttons ─────────────────────────────────────────────────────────────
document.querySelectorAll('.edit-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.dataset.id;
    const d  = scholarshipsData[id] || {};
    openModal('edit', Object.assign({}, d, btn.dataset));
  });
});

// ─── Detail modal ─────────────────────────────────────────────────────────────
document.querySelectorAll('.detail-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.dataset.id;
    const d  = scholarshipsData[id];
    if(!d) return;
    currentEditId = id;

    const statusColors = {open:'var(--success)',closed:'var(--danger)',draft:'var(--text-muted)'};
    const statusCol    = statusColors[d.status] || 'var(--text-muted)';
    const fmtAmt = (a,c) => {
      if(!a) return null;
      const syms = {GBP:'£',USD:'$',EUR:'€',NGN:'₦',CAD:'CA$',AUD:'A$'};
      return (syms[c]||c+' ') + Number(a).toLocaleString();
    };
    const amt = fmtAmt(d.amount, d.currency);

    document.getElementById('detailModalBody').innerHTML = `
      <div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:22px;padding-bottom:18px;border-bottom:1px solid var(--border-light);">
        <div style="flex:1;">
          <div style="font-family:var(--font-display);font-size:24px;font-weight:500;color:var(--navy);margin-bottom:6px;">${d.title}</div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            ${d.category ? `<span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:500;background:rgba(200,160,88,.12);color:var(--gold);">${d.category}</span>` : ''}
            <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;color:${statusCol};background:${statusCol}22;">${d.status}</span>
            ${d.is_featured ? '<span style="color:var(--gold);font-size:13px;" title="Featured"><i class="fas fa-star"></i></span>' : ''}
          </div>
        </div>
        ${amt ? `<div style="text-align:right;"><div style="font-size:11px;color:var(--text-muted);margin-bottom:2px;">Award</div><div style="font-family:var(--font-display);font-size:28px;font-weight:500;color:var(--navy);">${amt}</div></div>` : ''}
      </div>
      ${d.host_institution||d.country ? `<div class="detail-section"><div class="detail-label">Host</div><div class="detail-value">${[d.host_institution,d.country].filter(Boolean).join(' · ')}</div></div>` : ''}
      ${d.deadline ? `<div class="detail-section"><div class="detail-label">Deadline</div><div class="detail-value">${new Date(d.deadline).toLocaleDateString('en-GB',{day:'numeric',month:'long',year:'numeric'})}</div></div>` : ''}
      ${d.description ? `<div class="detail-section"><div class="detail-label">Description</div><div class="detail-value" style="white-space:pre-line;">${d.description}</div></div>` : ''}
      ${d.eligibility ? `<div class="detail-section"><div class="detail-label">Eligibility</div><div class="detail-value" style="white-space:pre-line;">${d.eligibility}</div></div>` : ''}
      ${d.requirements ? `<div class="detail-section"><div class="detail-label">Required Documents</div><div class="detail-value" style="white-space:pre-line;">${d.requirements}</div></div>` : ''}
      ${d.application_link ? `<div class="detail-section"><div class="detail-label">Apply at</div><div class="detail-value"><a href="${d.application_link}" target="_blank" rel="noopener" style="color:var(--gold);word-break:break-all;">${d.application_link}</a></div></div>` : ''}
    `;
    detailOverlay.classList.add('open');
    document.body.style.overflow = 'hidden';
  });
});

document.getElementById('closeDetailModal')?.addEventListener('click',  ()=>{ detailOverlay.classList.remove('open'); document.body.style.overflow=''; });
document.getElementById('cancelDetailModal')?.addEventListener('click', ()=>{ detailOverlay.classList.remove('open'); document.body.style.overflow=''; });
detailOverlay?.addEventListener('click', e=>{ if(e.target===detailOverlay){ detailOverlay.classList.remove('open'); document.body.style.overflow=''; } });

document.getElementById('detailEditBtn')?.addEventListener('click', ()=>{
  if(!currentEditId) return;
  detailOverlay.classList.remove('open');
  const d = scholarshipsData[currentEditId];
  if(d) openModal('edit', d);
});

// ─── Delete ───────────────────────────────────────────────────────────────────
document.querySelectorAll('.delete-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    Swal.fire({
      title: 'Delete this scholarship?',
      html: `<p style="font-size:14px;color:var(--text-muted);">You are about to permanently delete <strong style="color:var(--navy);">${btn.dataset.title}</strong>. This cannot be undone.</p>`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it',
      cancelButtonText:  'Cancel',
      confirmButtonColor:'#DC2626',
    }).then(r => {
      if(r.isConfirmed) {
        document.getElementById('deleteFormId').value = btn.dataset.id;
        document.getElementById('deleteForm').submit();
      }
    });
  });
});

// ─── Duplicate confirmation ───────────────────────────────────────────────────
document.querySelectorAll('.dupe-form').forEach(form => {
  form.addEventListener('submit', e => {
    e.preventDefault();
    Swal.fire({
      title: 'Duplicate scholarship?',
      html: `<p style="font-size:14px;color:var(--text-muted);">A draft copy of <strong>${form.dataset.title}</strong> will be created.</p>`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Duplicate',
      cancelButtonText:  'Cancel',
      confirmButtonColor:'#0D1829',
    }).then(r => { if(r.isConfirmed) form.submit(); });
  });
});

// ─── Live search (debounced) ──────────────────────────────────────────────────
let searchTimer;
document.getElementById('searchInput')?.addEventListener('input', function() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => document.getElementById('filterForm').submit(), 600);
});

// ─── Client-side form validation ─────────────────────────────────────────────
document.getElementById('scholarshipForm')?.addEventListener('submit', function(e) {
  const title = document.getElementById('fTitle').value.trim();
  if(!title) {
    e.preventDefault();
    document.getElementById('fTitle').focus();
    Swal.fire({
      icon: 'warning', title: 'Title required',
      text: 'Please enter a scholarship title before saving.',
      confirmButtonColor: '#0D1829'
    });
  }
});

// ─── Auto-dismiss flash messages ─────────────────────────────────────────────
document.querySelectorAll('.flash').forEach(el => {
  setTimeout(() => {
    el.style.transition = 'opacity .5s, max-height .5s, margin .5s, padding .5s';
    el.style.opacity = '0'; el.style.maxHeight = '0'; el.style.margin = '0'; el.style.padding = '0';
    setTimeout(() => el.remove(), 600);
  }, 4500);
});
</script>

<?php
// Helper: build data attributes for edit buttons
function buildDataAttrs($s) {
    $attrs = [
        'data-id'               => $s['id'],
        'data-title'            => $s['title'],
        'data-description'      => $s['description']      ?? '',
        'data-category'         => $s['category']         ?? '',
        'data-host_institution' => $s['host_institution'] ?? '',
        'data-country'          => $s['country']          ?? '',
        'data-deadline'         => $s['deadline']         ?? '',
        'data-amount'           => $s['amount']           ?? '',
        'data-currency'         => $s['currency']         ?? 'GBP',
        'data-status'           => $s['status'],
        'data-requirements'     => $s['requirements']     ?? '',
        'data-eligibility'      => $s['eligibility']      ?? '',
        'data-application_link' => $s['application_link'] ?? '',
        'data-is_featured'      => $s['is_featured'] ? '1' : '0',
    ];
    $out = '';
    foreach ($attrs as $k => $v) $out .= ' ' . $k . '="' . htmlspecialchars($v, ENT_QUOTES) . '"';
    return $out;
}
?>
</body>
</html>