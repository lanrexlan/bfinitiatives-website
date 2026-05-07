<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ── Sidebar / header data ────────────────────────────────────────────────────
$first_name = ''; $last_name = ''; $profile_picture = null; $notification_count = 0;
try {
    $db0 = new Database(); $c0 = $db0->getConnection();
    $s0 = $c0->prepare("SELECT first_name, last_name, profile_picture FROM users WHERE id = :uid");
    $s0->execute([':uid' => $_SESSION['user_id']]);
    $u0 = $s0->fetch(PDO::FETCH_ASSOC);
    if ($u0) {
        $first_name    = $u0['first_name'];
        $last_name     = $u0['last_name'] ?? '';
        $profile_picture = $u0['profile_picture'] ?? null;
    }
    $n0 = $c0->prepare("SELECT id FROM notifications WHERE user_id = :uid AND read_status = 0");
    $n0->execute([':uid' => $_SESSION['user_id']]);
    $notification_count = $n0->rowCount();
} catch (Exception $e) {}

// ── Original scholarship logic (unchanged) ───────────────────────────────────
$search_query = ''; $country_filter = ''; $field_filter = '';
$scholarships = []; $error_msg = ''; $success_msg = '';
$google_search_url = ''; $using_google_search = false; $phd_only = false;

$sample_scholarships = [
    ['id'=>1,'title'=>'Fulbright Foreign Student Program','description'=>'Covers tuition, living stipend, health insurance and travel for graduate study in the United States.','country'=>'USA','field_of_study'=>'All Fields','deadline'=>'October 15, 2026','url'=>'https://foreign.fulbrightonline.org/'],
    ['id'=>2,'title'=>'Chevening Scholarships','description'=>"UK government's global scholarship programme, funded by the Foreign and Commonwealth Office and partner organisations.",'country'=>'UK','field_of_study'=>'All Fields','deadline'=>'November 7, 2026','url'=>'https://www.chevening.org/'],
    ['id'=>3,'title'=>'DAAD Scholarships','description'=>'German Academic Exchange Service scholarships for international students and researchers.','country'=>'Germany','field_of_study'=>'All Fields','deadline'=>'November 30, 2026','url'=>'https://www.daad.de/en/'],
    ['id'=>4,'title'=>'Australia Awards Scholarships','description'=>'Long-term development scholarships and short-term fellowships for study in Australia.','country'=>'Australia','field_of_study'=>'All Fields','deadline'=>'April 30, 2027','url'=>'https://www.australiaawards.gov.au/'],
    ['id'=>5,'title'=>'Vanier Canada Graduate Scholarships','description'=>'Doctoral scholarships worth $50,000 per year for three years.','country'=>'Canada','field_of_study'=>'PhD Research','deadline'=>'November 6, 2026','url'=>'https://vanier.gc.ca/'],
    ['id'=>6,'title'=>'Erasmus Mundus Joint Master Degrees','description'=>"European Union scholarships for joint master's programs taught in multiple European countries.",'country'=>'Europe','field_of_study'=>'Various Fields','deadline'=>'January 15, 2027','url'=>'https://ec.europa.eu/programmes/erasmus-plus/'],
    ['id'=>7,'title'=>'Chinese Government Scholarship','description'=>'Full scholarships for international students to study in China.','country'=>'China','field_of_study'=>'All Fields','deadline'=>'April 30, 2027','url'=>'https://www.csc.edu.cn/'],
    ['id'=>8,'title'=>'Gates Cambridge Scholarships','description'=>'Full-cost scholarships for outstanding applicants from outside the UK to pursue graduate study at Cambridge.','country'=>'UK','field_of_study'=>'All Fields','deadline'=>'December 4, 2026','url'=>'https://www.gatescambridge.org/'],
    ['id'=>9,'title'=>'Swiss Government Excellence Scholarships','description'=>'Research scholarships for foreign scholars and artists at Swiss higher education institutions.','country'=>'Switzerland','field_of_study'=>'All Fields','deadline'=>'December 1, 2026','url'=>'https://www.sbfi.admin.ch/'],
    ['id'=>10,'title'=>'MEXT Scholarships','description'=>'Japanese government scholarships for international students to study in Japan.','country'=>'Japan','field_of_study'=>'All Fields','deadline'=>'May 31, 2027','url'=>'https://www.mext.go.jp/'],
    ['id'=>11,'title'=>'Rhodes Scholarships','description'=>'The oldest international scholarship programme, enabling outstanding young people to study at Oxford.','country'=>'UK','field_of_study'=>'All Fields','deadline'=>'October 2, 2026','url'=>'https://www.rhodesscholar.org/'],
    ['id'=>12,'title'=>'Netherlands Fellowship Programmes','description'=>'Scholarships for mid-career professionals from developing countries.','country'=>'Netherlands','field_of_study'=>'Development Studies','deadline'=>'February 1, 2027','url'=>'https://www.nuffic.nl/en/subjects/netherlands-fellowship-programmes'],
    ['id'=>13,'title'=>'Swedish Institute Scholarships','description'=>'Scholarships for highly qualified international students to study in Sweden.','country'=>'Sweden','field_of_study'=>'All Fields','deadline'=>'February 14, 2027','url'=>'https://si.se/en/apply/scholarships/'],
    ['id'=>14,'title'=>'Korean Government Scholarship Program','description'=>'Scholarships for international students to promote international exchanges in education.','country'=>'South Korea','field_of_study'=>'All Fields','deadline'=>'March 31, 2027','url'=>'https://www.studyinkorea.go.kr/'],
    ['id'=>15,'title'=>'New Zealand Development Scholarships','description'=>'Scholarships for students from developing countries to study in New Zealand.','country'=>'New Zealand','field_of_study'=>'Development Studies','deadline'=>'July 15, 2027','url'=>'https://www.mfat.govt.nz/en/aid-and-development/scholarships/'],
    ['id'=>16,'title'=>'Eiffel Excellence Scholarship','description'=>"French government scholarships for international students at master's and PhD levels.",'country'=>'France','field_of_study'=>'All Fields','deadline'=>'January 8, 2027','url'=>'https://www.campusfrance.org/en/eiffel-scholarship-program-of-excellence'],
    ['id'=>17,'title'=>'Commonwealth Scholarships','description'=>'Scholarships for citizens of Commonwealth countries to study in the UK.','country'=>'UK','field_of_study'=>'All Fields','deadline'=>'December 15, 2026','url'=>'https://cscuk.fcdo.gov.uk/'],
    ['id'=>18,'title'=>'Rotary Peace Fellowship','description'=>'Fully-funded academic fellowships for leaders committed to solving conflicts worldwide.','country'=>'Various','field_of_study'=>'Peace Studies','deadline'=>'May 15, 2027','url'=>'https://www.rotary.org/en/our-programs/peace-fellowships'],
    ['id'=>19,'title'=>'Belgium Development Cooperation Scholarships','description'=>'Scholarships for students from developing countries to study in Belgium.','country'=>'Belgium','field_of_study'=>'Development Studies','deadline'=>'February 28, 2027','url'=>'https://www.belgium.be/en/about_belgium/country/Education'],
    ['id'=>20,'title'=>'Taiwan Scholarship Program','description'=>'Scholarships for international students to study Chinese language and academic subjects in Taiwan.','country'=>'Taiwan','field_of_study'=>'All Fields','deadline'=>'March 31, 2027','url'=>'https://taiwanscholarship.moe.gov.tw/'],
    ['id'=>21,'title'=>'Brazilian Government Scholarships','description'=>'Scholarships for international students and researchers to study in Brazil.','country'=>'Brazil','field_of_study'=>'All Fields','deadline'=>'August 15, 2027','url'=>'https://www.gov.br/capes/pt-br'],
    ['id'=>22,'title'=>'Turkey Scholarships','description'=>'Comprehensive scholarship program covering undergraduate, graduate, and doctoral studies.','country'=>'Turkey','field_of_study'=>'All Fields','deadline'=>'February 20, 2027','url'=>'https://www.turkiyeburslari.gov.tr/'],
    ['id'=>23,'title'=>'Austrian Development Cooperation Scholarships','description'=>'Scholarships for students from developing countries to study in Austria.','country'=>'Austria','field_of_study'=>'Development Studies','deadline'=>'April 1, 2027','url'=>'https://oead.at/en/'],
    ['id'=>24,'title'=>'Marie Skłodowska-Curie Actions','description'=>'EU funding for doctoral and post-doctoral fellowships across all research fields.','country'=>'Europe','field_of_study'=>'PhD Research','deadline'=>'September 10, 2027','url'=>'https://marie-sklodowska-curie-actions.ec.europa.eu/'],
    ['id'=>25,'title'=>'GREAT Scholarships','description'=>'Joint initiative by the British Council and UK universities offering scholarships worth £10,000.','country'=>'UK','field_of_study'=>'Various Fields','deadline'=>'January 30, 2027','url'=>'https://study-uk.britishcouncil.org/scholarships-funding/great-scholarships'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($_GET['search_query']) || !empty($_GET['country_filter']) || !empty($_GET['field_filter'])) {
    $search_query   = $_POST['search_query']   ?? $_GET['search_query']   ?? '';
    $country_filter = $_POST['country_filter'] ?? $_GET['country_filter'] ?? '';
    $field_filter   = $_POST['field_filter']   ?? $_GET['field_filter']   ?? '';
    $phd_only = isset($_POST['phd_only']) || isset($_GET['phd_only']) ? true : false;

    if (!empty($search_query)) {
        $using_google_search = true;
        $google_query = $search_query . " scholarship";
        if (!empty($country_filter)) $google_query .= " " . $country_filter;
        if (!empty($field_filter))   $google_query .= " " . $field_filter;
        if ($phd_only)               $google_query .= " PhD doctoral";
        $google_search_url = "https://www.google.com/search?q=" . urlencode($google_query);

        if (!empty($country_filter) || !empty($field_filter) || $phd_only) {
            try {
                $db = new Database(); $conn = $db->getConnection();
                $query = "SELECT id, title, description, country, field_of_study, deadline, url FROM scholarships WHERE 1=1";
                $params = [];
                if (!empty($country_filter)) { $query .= " AND country = :country"; $params[':country'] = $country_filter; }
                if (!empty($field_filter))   { $query .= " AND field_of_study = :field"; $params[':field'] = $field_filter; }
                if ($phd_only) $query .= " AND (field_of_study = 'PhD Research' OR field_of_study ILIKE '%PhD%' OR title ILIKE '%PhD%' OR description ILIKE '%doctoral%' OR description ILIKE '%PhD%')";
                $query .= " ORDER BY deadline ASC";
                $stmt = $conn->prepare($query); $stmt->execute($params);
                $scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $scholarships = array_filter($sample_scholarships, function($s) use ($country_filter, $field_filter, $phd_only) {
                    if (!empty($country_filter) && $s['country'] !== $country_filter) return false;
                    if (!empty($field_filter) && $s['field_of_study'] !== $field_filter) return false;
                    if ($phd_only) {
                        $is = $s['field_of_study'] === 'PhD Research' || stripos($s['field_of_study'],'PhD')!==false || stripos($s['title'],'PhD')!==false || stripos($s['description'],'doctoral')!==false;
                        if (!$is) return false;
                    }
                    return true;
                });
            }
        }
    } else {
        try {
            $db = new Database(); $conn = $db->getConnection();
            $check = $conn->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'scholarships')");
            $table_exists = $check->fetchColumn();
            if (!$table_exists) {
                $conn->exec("CREATE TABLE IF NOT EXISTS scholarships (id SERIAL PRIMARY KEY, title VARCHAR(255) NOT NULL, description TEXT, country VARCHAR(100), field_of_study VARCHAR(100), deadline VARCHAR(100), url VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
                $ins = $conn->prepare("INSERT INTO scholarships (title, description, country, field_of_study, deadline, url) VALUES (:title,:description,:country,:field_of_study,:deadline,:url)");
                foreach ($sample_scholarships as $s) $ins->execute([':title'=>$s['title'],':description'=>$s['description'],':country'=>$s['country'],':field_of_study'=>$s['field_of_study'],':deadline'=>$s['deadline'],':url'=>$s['url']]);
                $success_msg = "Sample scholarship data has been loaded.";
            }
            $query = "SELECT id, title, description, country, field_of_study, deadline, url FROM scholarships WHERE 1=1";
            $params = [];
            if (!empty($country_filter)) { $query .= " AND country = :country"; $params[':country'] = $country_filter; }
            if (!empty($field_filter))   { $query .= " AND field_of_study = :field"; $params[':field'] = $field_filter; }
            if ($phd_only) $query .= " AND (field_of_study = 'PhD Research' OR field_of_study ILIKE '%PhD%' OR title ILIKE '%PhD%' OR description ILIKE '%doctoral%' OR description ILIKE '%PhD%')";
            $query .= " ORDER BY deadline ASC";
            $stmt = $conn->prepare($query); $stmt->execute($params);
            $scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($scholarships)) {
                $count = $conn->query("SELECT COUNT(*) FROM scholarships")->fetchColumn();
                if ($count == 0) {
                    $ins = $conn->prepare("INSERT INTO scholarships (title, description, country, field_of_study, deadline, url) VALUES (:title,:description,:country,:field_of_study,:deadline,:url)");
                    foreach ($sample_scholarships as $s) $ins->execute([':title'=>$s['title'],':description'=>$s['description'],':country'=>$s['country'],':field_of_study'=>$s['field_of_study'],':deadline'=>$s['deadline'],':url'=>$s['url']]);
                    $stmt = $conn->prepare($query); $stmt->execute($params);
                    $scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }
        } catch (Exception $e) {
            $error_msg = "Using sample data.";
            $scholarships = $sample_scholarships;
            if (!empty($country_filter) || !empty($field_filter) || $phd_only) {
                $scholarships = array_filter($scholarships, function($s) use ($country_filter, $field_filter, $phd_only) {
                    if (!empty($country_filter) && $s['country'] !== $country_filter) return false;
                    if (!empty($field_filter) && $s['field_of_study'] !== $field_filter) return false;
                    if ($phd_only) {
                        $is = $s['field_of_study'] === 'PhD Research' || stripos($s['field_of_study'],'PhD')!==false || stripos($s['title'],'PhD')!==false || stripos($s['description'],'doctoral')!==false;
                        if (!$is) return false;
                    }
                    return true;
                });
            }
        }
    }
} else {
    try {
        $db = new Database(); $conn = $db->getConnection();
        $check = $conn->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'scholarships')");
        $table_exists = $check->fetchColumn();
        if (!$table_exists) {
            $scholarships = $sample_scholarships;
        } else {
            $count = $conn->query("SELECT COUNT(*) FROM scholarships")->fetchColumn();
            if ($count == 0) {
                $ins = $conn->prepare("INSERT INTO scholarships (title, description, country, field_of_study, deadline, url) VALUES (:title,:description,:country,:field_of_study,:deadline,:url)");
                foreach ($sample_scholarships as $s) $ins->execute([':title'=>$s['title'],':description'=>$s['description'],':country'=>$s['country'],':field_of_study'=>$s['field_of_study'],':deadline'=>$s['deadline'],':url'=>$s['url']]);
            }
            $stmt = $conn->query("SELECT id, title, description, country, field_of_study, deadline, url FROM scholarships ORDER BY deadline ASC");
            $scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $scholarships = $sample_scholarships;
    }
}

$countries = []; $fields_of_study = [];
foreach ($scholarships as $scholarship) {
    if (!empty($scholarship['country']) && !in_array($scholarship['country'], $countries)) $countries[] = $scholarship['country'];
    if (!empty($scholarship['field_of_study']) && !in_array($scholarship['field_of_study'], $fields_of_study)) $fields_of_study[] = $scholarship['field_of_study'];
}
sort($countries); sort($fields_of_study);

// ── Closing Soon (new feature) ───────────────────────────────────────────────
$closing_soon = [];
foreach ($scholarships as $s) {
    $ts = strtotime($s['deadline']);
    if ($ts !== false && $ts > time()) {
        $s['days_left'] = ceil(($ts - time()) / 86400);
        $closing_soon[] = $s;
    }
}
usort($closing_soon, fn($a,$b) => $a['days_left'] - $b['days_left']);
$closing_soon = array_slice($closing_soon, 0, 3);

// Country flag map
$country_flags = [
    'USA'=>'🇺🇸','UK'=>'🇬🇧','Germany'=>'🇩🇪','Australia'=>'🇦🇺','Canada'=>'🇨🇦',
    'France'=>'🇫🇷','Japan'=>'🇯🇵','China'=>'🇨🇳','Switzerland'=>'🇨🇭','Netherlands'=>'🇳🇱',
    'Sweden'=>'🇸🇪','South Korea'=>'🇰🇷','New Zealand'=>'🇳🇿','Belgium'=>'🇧🇪',
    'Taiwan'=>'🇹🇼','Brazil'=>'🇧🇷','Turkey'=>'🇹🇷','Austria'=>'🇦🇹',
    'Europe'=>'🇪🇺','Various'=>'🌍',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Scholarships | BFI Scholar Portal</title>
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

    /* ── SIDEBAR ── */
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

    /* ── HEADER ── */
    .header{position:fixed;top:0;left:var(--sidebar-width);right:0;height:var(--header-height);background:var(--white);border-bottom:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;padding:0 28px;z-index:100;}
    .header-left{display:flex;align-items:center;gap:16px;}
    .mobile-toggle{display:none;background:none;border:none;cursor:pointer;padding:6px;color:var(--text-secondary);}
    .header-greeting{font-size:13.5px;color:var(--text-muted);}
    .header-greeting strong{color:var(--text-primary);font-weight:600;}
    .header-right{display:flex;align-items:center;gap:16px;}
    .header-icon-btn{width:36px;height:36px;border-radius:var(--r-sm);background:var(--cream);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-secondary);font-size:15px;transition:var(--transition);position:relative;}
    .header-icon-btn:hover{background:var(--cream-dark);color:var(--navy);}
    .notif-dot{position:absolute;top:6px;right:6px;width:8px;height:8px;border-radius:50%;background:#EF4444;border:2px solid var(--white);}
    .header-avatar{width:36px;height:36px;border-radius:50%;background:var(--navy-light);overflow:hidden;border:2px solid var(--border-light);cursor:pointer;display:flex;align-items:center;justify-content:center;}
    .header-avatar img{width:100%;height:100%;object-fit:cover;}
    .header-avatar-init{font-family:var(--font-display);font-size:14px;color:var(--gold-bright);}

    /* ── MAIN ── */
    .main{margin-left:var(--sidebar-width);margin-top:var(--header-height);padding:28px;min-height:calc(100vh - var(--header-height));}

    /* ── WELCOME BANNER ── */
    .welcome-banner{background:var(--navy);border-radius:var(--r-xl);padding:32px 36px;margin-bottom:24px;position:relative;overflow:hidden;}
    .welcome-banner::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:32px 32px;}
    .welcome-banner::after{content:'';position:absolute;top:-60px;right:-60px;width:280px;height:280px;background:radial-gradient(circle,rgba(200,160,88,0.1) 0%,transparent 65%);}
    .welcome-inner{position:relative;z-index:1;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;}
    .welcome-text-eyebrow{font-size:10px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:rgba(200,160,88,0.7);margin-bottom:8px;}
    .welcome-title{font-family:var(--font-display);font-size:clamp(24px,3vw,32px);font-weight:500;color:var(--white);line-height:1.15;margin-bottom:6px;}
    .welcome-title em{font-style:italic;color:var(--gold-bright);}
    .welcome-sub{font-size:13.5px;font-weight:300;color:rgba(255,255,255,0.5);}
    .welcome-stats{display:flex;gap:28px;flex-wrap:wrap;}
    .wstat{text-align:center;}
    .wstat-num{font-family:var(--font-display);font-size:28px;font-weight:500;color:var(--gold-bright);line-height:1;}
    .wstat-label{font-size:10px;color:rgba(255,255,255,0.4);letter-spacing:1px;text-transform:uppercase;margin-top:4px;}

    /* ── SECTION LABEL ── */
    .section-label{font-size:10px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);margin-bottom:14px;}

    /* ── CARD BASE ── */
    .card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);overflow:hidden;}
    .card-header-row{padding:20px 24px 0;display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;}
    .card-title-text{font-family:var(--font-display);font-size:18px;font-weight:500;color:var(--navy);}
    .card-title-text em{font-style:italic;color:var(--gold);}
    .card-link{font-size:12px;color:var(--gold);font-weight:500;display:flex;align-items:center;gap:5px;transition:var(--transition);}
    .card-link:hover{gap:8px;}

    /* ── CLOSING SOON STRIP ── */
    .spotlight-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;}
    .spotlight-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:20px;position:relative;overflow:hidden;transition:var(--transition);}
    .spotlight-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-md);border-color:transparent;}
    .spotlight-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--gold),var(--gold-bright));}
    .spotlight-urgency{display:inline-flex;align-items:center;gap:6px;font-size:10px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:#EF4444;background:rgba(239,68,68,0.08);padding:3px 10px;border-radius:20px;margin-bottom:10px;}
    .spotlight-urgency.medium{color:#F59E0B;background:rgba(245,158,11,0.08);}
    .spotlight-urgency.low{color:#10B981;background:rgba(16,185,129,0.08);}
    .spotlight-title{font-size:14px;font-weight:500;color:var(--navy);margin-bottom:6px;line-height:1.35;}
    .spotlight-meta{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text-muted);margin-bottom:14px;}
    .spotlight-flag{font-size:16px;}
    .spotlight-deadline{font-size:12px;color:var(--text-secondary);}
    .spotlight-apply{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:500;color:var(--gold);background:rgba(200,160,88,0.08);border:1px solid rgba(200,160,88,0.2);padding:6px 14px;border-radius:20px;transition:var(--transition);}
    .spotlight-apply:hover{background:var(--gold);color:var(--midnight);}
    .countdown-badge{font-family:var(--font-display);font-size:22px;font-weight:500;color:var(--navy);margin-bottom:4px;}
    .countdown-unit{font-size:10px;color:var(--text-muted);letter-spacing:1px;text-transform:uppercase;}

    /* ── SEARCH CARD ── */
    .search-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:24px;margin-bottom:24px;}
    .search-row{display:flex;gap:12px;margin-bottom:16px;}
    .search-wrap{flex:1;position:relative;}
    .search-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:14px;}
    .search-input{width:100%;padding:12px 16px 12px 42px;border:1.5px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:14px;color:var(--text-primary);background:var(--cream);transition:var(--transition);}
    .search-input:focus{outline:none;border-color:var(--gold);background:var(--white);box-shadow:0 0 0 3px rgba(200,160,88,0.1);}
    .btn-gold{display:inline-flex;align-items:center;gap:8px;padding:11px 22px;background:var(--gold);color:var(--midnight);font-family:var(--font-body);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);white-space:nowrap;}
    .btn-gold:hover{background:var(--gold-bright);transform:translateY(-1px);}
    .filter-row{display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end;}
    .filter-group label{display:block;font-size:11px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted);margin-bottom:6px;}
    .filter-select{width:100%;padding:10px 14px;border:1.5px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13.5px;color:var(--text-primary);background:var(--cream);transition:var(--transition);}
    .filter-select:focus{outline:none;border-color:var(--gold);}
    .phd-check{display:flex;align-items:center;gap:8px;padding:10px 0;}
    .phd-check input[type=checkbox]{width:16px;height:16px;accent-color:var(--gold);}
    .phd-check label{font-size:13px;color:var(--text-secondary);cursor:pointer;}

    /* ── GOOGLE RESULT CARD ── */
    .google-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:28px;margin-bottom:24px;text-align:center;}
    .google-card::before{content:'';display:block;width:100%;height:3px;background:linear-gradient(90deg,#4285F4 25%,#34A853 50%,#FBBC05 75%,#EA4335);border-radius:3px 3px 0 0;position:relative;top:-28px;margin-bottom:-28px;margin-left:-24px;width:calc(100% + 48px);}
    .google-logo-text{font-size:28px;font-family:Georgia,serif;font-weight:700;letter-spacing:-1px;margin-bottom:12px;}
    .google-logo-text .g-blue{color:#4285F4;}
    .google-logo-text .g-red{color:#EA4335;}
    .google-logo-text .g-yellow{color:#FBBC05;}
    .google-logo-text .g-green{color:#34A853;}
    .google-query-pill{display:inline-flex;align-items:center;gap:8px;background:var(--cream);border:1px solid var(--border-light);padding:8px 18px;border-radius:20px;font-size:13.5px;color:var(--text-secondary);margin-bottom:16px;}
    .google-btn{display:inline-flex;align-items:center;gap:8px;padding:12px 28px;background:var(--navy);color:var(--white);border-radius:var(--r-sm);font-size:13px;font-weight:500;transition:var(--transition);}
    .google-btn:hover{background:var(--navy-light);color:var(--white);transform:translateY(-1px);}
    .google-note{font-size:12px;color:var(--text-muted);margin-top:12px;}

    /* ── SCHOLARSHIP CARDS ── */
    .schol-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px;margin-bottom:28px;}
    .schol-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);display:flex;flex-direction:column;transition:var(--transition);overflow:hidden;}
    .schol-card:hover{transform:translateY(-5px);box-shadow:var(--shadow-md);border-color:transparent;}
    .schol-card-top{padding:20px 22px 16px;border-bottom:1px solid var(--border-light);}
    .schol-card-badges{display:flex;align-items:center;gap:8px;margin-bottom:10px;}
    .badge-pill{font-size:10px;font-weight:600;padding:2px 10px;border-radius:20px;letter-spacing:0.5px;}
    .badge-soon{background:rgba(239,68,68,0.1);color:#DC2626;}
    .badge-active{background:rgba(16,185,129,0.1);color:#059669;}
    .badge-phd{background:rgba(200,160,88,0.1);color:var(--gold);}
    .schol-card-title{font-size:15px;font-weight:500;color:var(--navy);line-height:1.35;margin-bottom:6px;}
    .schol-card-country{display:flex;align-items:center;gap:6px;font-size:12.5px;color:var(--text-muted);}
    .schol-card-body{padding:16px 22px;flex:1;}
    .schol-card-desc{font-size:13px;color:var(--text-secondary);line-height:1.6;margin-bottom:14px;}
    .schol-meta-row{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:14px;}
    .schol-meta{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-muted);}
    .schol-meta i{color:var(--gold);font-size:11px;}
    .schol-tags{display:flex;gap:6px;flex-wrap:wrap;}
    .schol-tag{font-size:10.5px;padding:2px 10px;border-radius:20px;background:var(--cream);color:var(--text-secondary);border:1px solid var(--border-light);}
    .schol-card-footer{padding:14px 22px;border-top:1px solid var(--border-light);}
    .schol-apply-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:11px;background:var(--navy);color:var(--white);border-radius:var(--r-sm);font-size:13px;font-weight:500;transition:var(--transition);}
    .schol-apply-btn:hover{background:var(--gold);color:var(--midnight);}

    /* ── PhD RESOURCES ── */
    .phd-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;}
    .phd-res-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:22px;transition:var(--transition);}
    .phd-res-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-md);}
    .phd-res-icon{width:44px;height:44px;background:var(--navy);border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;color:var(--gold-bright);font-size:18px;margin-bottom:14px;}
    .phd-res-title{font-size:14px;font-weight:500;color:var(--navy);margin-bottom:6px;}
    .phd-res-desc{font-size:12.5px;color:var(--text-muted);line-height:1.55;margin-bottom:14px;}
    .phd-res-link{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:500;color:var(--gold);transition:var(--transition);}
    .phd-res-link:hover{gap:9px;}

    /* ── ALERTS ── */
    .portal-alert{padding:14px 18px;border-radius:var(--r-md);margin-bottom:20px;font-size:13.5px;display:flex;align-items:center;gap:10px;}
    .portal-alert-success{background:rgba(16,185,129,0.08);color:#059669;border:1px solid rgba(16,185,129,0.2);}
    .portal-alert-warning{background:rgba(245,158,11,0.08);color:#92400E;border:1px solid rgba(245,158,11,0.2);}

    /* ── NO RESULTS ── */
    .no-results{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:48px;text-align:center;}
    .no-results i{font-size:32px;color:var(--text-muted);margin-bottom:16px;}
    .no-results h3{font-family:var(--font-display);font-size:22px;font-weight:500;color:var(--navy);margin-bottom:8px;}
    .no-results p{font-size:13.5px;color:var(--text-muted);}

    /* ── FOOTER ── */
    .portal-footer{margin-left:var(--sidebar-width);background:var(--white);padding:16px 28px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;}
    .footer-copy{font-size:12px;color:var(--text-muted);}
    .footer-links{display:flex;gap:20px;}
    .footer-links a{font-size:12px;color:var(--text-muted);transition:color var(--transition);}
    .footer-links a:hover{color:var(--gold);}

    /* ── OVERLAY ── */
    .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:150;backdrop-filter:blur(4px);}
    .sidebar-overlay.active{display:block;}

    /* ── RESPONSIVE ── */
    @media(max-width:1100px){.spotlight-grid{grid-template-columns:repeat(2,1fr)}.phd-grid{grid-template-columns:repeat(2,1fr)}.filter-row{grid-template-columns:1fr 1fr}}
    @media(max-width:768px){
      .sidebar{transform:translateX(-100%);}
      .sidebar.active{transform:translateX(0);}
      .header{left:0;}
      .main,.portal-footer{margin-left:0;}
      .mobile-toggle{display:flex;}
      .header-greeting{display:none;}
      .spotlight-grid,.phd-grid{grid-template-columns:1fr;}
      .schol-grid{grid-template-columns:1fr;}
      .filter-row{grid-template-columns:1fr;}
      .search-row{flex-direction:column;}
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
      <div class="sidebar-logo-text">Bold Footprint<span>Scholar Portal</span></div>
    </div>
    <div class="sidebar-user">
      <div class="sidebar-avatar">
        <?php if ($profile_picture): ?>
          <img src="<?php echo htmlspecialchars('./uploads/profile_pictures/' . $profile_picture); ?>" alt="Profile" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
          <div class="sidebar-avatar-init" style="display:none;"><?php echo strtoupper(substr($first_name,0,1)); ?></div>
        <?php else: ?>
          <div class="sidebar-avatar-init"><?php echo strtoupper(substr($first_name,0,1)); ?></div>
        <?php endif; ?>
      </div>
      <div>
        <div class="sidebar-user-name"><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></div>
        <div class="sidebar-user-role">BFI Scholar</div>
      </div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>
    <div class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a></div>
    <div class="nav-item"><a href="profile.php" class="nav-link"><i class="fas fa-user"></i> My Profile</a></div>
    <div class="nav-item">
      <a href="#" class="nav-link"><i class="fas fa-route"></i> My Journey
        <?php if ($notification_count > 0): ?><span class="nav-badge"><?php echo $notification_count; ?></span><?php endif; ?>
      </a>
    </div>
    <div class="nav-section-label">Resources</div>
    <div class="nav-item"><a href="documents.php" class="nav-link"><i class="fas fa-file-alt"></i> My Documents</a></div>
    <div class="nav-item"><a href="scholarships.php" class="nav-link active"><i class="fas fa-graduation-cap"></i> Scholarships</a></div>
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
    <div class="header-greeting">Scholarships — <strong>Find Your Opportunity</strong></div>
  </div>
  <div class="header-right">
    <button class="header-icon-btn" title="Notifications">
      <i class="fas fa-bell"></i>
      <?php if ($notification_count > 0): ?><div class="notif-dot"></div><?php endif; ?>
    </button>
    <a href="profile.php">
      <div class="header-avatar">
        <?php if ($profile_picture): ?>
          <img src="<?php echo htmlspecialchars('./uploads/profile_pictures/' . $profile_picture); ?>" alt="Profile" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
          <div class="header-avatar-init" style="display:none;"><?php echo strtoupper(substr($first_name,0,1)); ?></div>
        <?php else: ?>
          <div class="header-avatar-init"><?php echo strtoupper(substr($first_name,0,1)); ?></div>
        <?php endif; ?>
      </div>
    </a>
  </div>
</header>

<!-- MAIN -->
<main class="main">

  <!-- WELCOME BANNER -->
  <div class="welcome-banner" style="margin-bottom:24px;">
    <div class="welcome-inner">
      <div>
        <div class="welcome-text-eyebrow">Scholarship Explorer</div>
        <div class="welcome-title">Discover <em>Opportunities</em></div>
        <div class="welcome-sub">Browse <?php echo count($scholarships); ?> scholarships across <?php echo count($countries); ?> countries worldwide.</div>
      </div>
      <div class="welcome-stats">
        <div class="wstat">
          <div class="wstat-num"><?php echo count($scholarships); ?></div>
          <div class="wstat-label">Listed</div>
        </div>
        <div class="wstat">
          <div class="wstat-num"><?php echo count($countries); ?></div>
          <div class="wstat-label">Countries</div>
        </div>
        <div class="wstat">
          <div class="wstat-num"><?php echo count($closing_soon); ?></div>
          <div class="wstat-label">Closing Soon</div>
        </div>
      </div>
    </div>
  </div>

  <?php if (!empty($success_msg)): ?>
    <div class="portal-alert portal-alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?></div>
  <?php endif; ?>
  <?php if (!empty($error_msg)): ?>
    <div class="portal-alert portal-alert-warning"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?></div>
  <?php endif; ?>

  <!-- CLOSING SOON SPOTLIGHT -->
  <?php if (!empty($closing_soon) && !$using_google_search): ?>
    <div class="section-label">⏰ Closing Soon — Act Now</div>
    <div class="spotlight-grid">
      <?php foreach ($closing_soon as $cs):
        $days = $cs['days_left'];
        $urgency_class = $days <= 30 ? '' : ($days <= 60 ? 'medium' : 'low');
        $urgency_label = $days <= 30 ? 'Urgent' : ($days <= 60 ? 'Soon' : 'Upcoming');
        $flag = $country_flags[$cs['country']] ?? '🌍';
      ?>
      <div class="spotlight-card">
        <div>
          <div class="spotlight-urgency <?php echo $urgency_class; ?>">
            <i class="fas fa-clock"></i> <?php echo $urgency_label; ?>
          </div>
          <div class="countdown-badge"><?php echo $days; ?></div>
          <div class="countdown-unit">days remaining</div>
        </div>
        <div class="spotlight-title" style="margin-top:12px;"><?php echo htmlspecialchars($cs['title']); ?></div>
        <div class="spotlight-meta">
          <span class="spotlight-flag"><?php echo $flag; ?></span>
          <span><?php echo htmlspecialchars($cs['country']); ?></span>
          <span>·</span>
          <span><?php echo htmlspecialchars($cs['field_of_study']); ?></span>
        </div>
        <div class="spotlight-deadline" style="margin-bottom:14px;">Deadline: <?php echo htmlspecialchars($cs['deadline']); ?></div>
        <?php if (!empty($cs['url'])): ?>
          <a href="<?php echo htmlspecialchars($cs['url']); ?>" target="_blank" class="spotlight-apply">
            Apply Now <i class="fas fa-arrow-right" style="font-size:10px;"></i>
          </a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- SEARCH -->
  <div class="section-label">Search & Filter</div>
  <div class="search-card">
    <form method="POST" action="scholarships.php">
      <div class="search-row">
        <div class="search-wrap">
          <i class="fas fa-search search-icon"></i>
          <input type="text" name="search_query" class="search-input"
                 placeholder="Search scholarships — e.g. PhD Engineering, Chevening, Gates Cambridge…"
                 value="<?php echo htmlspecialchars($search_query); ?>">
        </div>
        <button type="submit" class="btn-gold"><i class="fas fa-search"></i> Search</button>
      </div>
      <div class="filter-row">
        <div class="filter-group">
          <label>Country</label>
          <select name="country_filter" class="filter-select">
            <option value="">All Countries</option>
            <?php foreach ($countries as $c): ?>
              <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $country_filter === $c ? 'selected' : ''; ?>>
                <?php echo ($country_flags[$c] ?? '') . ' ' . htmlspecialchars($c); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-group">
          <label>Field of Study</label>
          <select name="field_filter" class="filter-select">
            <option value="">All Fields</option>
            <?php foreach ($fields_of_study as $f): ?>
              <option value="<?php echo htmlspecialchars($f); ?>" <?php echo $field_filter === $f ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($f); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-group">
          <label>Level</label>
          <div class="phd-check">
            <input type="checkbox" name="phd_only" id="phdOnly" <?php echo $phd_only ? 'checked' : ''; ?>>
            <label for="phdOnly">PhD / Doctoral only</label>
          </div>
        </div>
      </div>
    </form>
  </div>

  <!-- GOOGLE SEARCH RESULT -->
  <?php if ($using_google_search): ?>
    <div class="google-card">
      <div class="google-logo-text">
        <span class="g-blue">G</span><span class="g-red">o</span><span class="g-yellow">o</span><span class="g-blue">g</span><span class="g-green">l</span><span class="g-red">e</span>
      </div>
      <div style="margin-bottom:16px;font-size:14px;color:var(--text-muted);">Searching the web for:</div>
      <div class="google-query-pill">
        <i class="fas fa-search" style="color:var(--gold);"></i>
        <?php echo htmlspecialchars($search_query); ?>
        <?php if (!empty($country_filter)): ?> &nbsp;·&nbsp; <?php echo htmlspecialchars($country_filter); ?><?php endif; ?>
        <?php if (!empty($field_filter)): ?> &nbsp;·&nbsp; <?php echo htmlspecialchars($field_filter); ?><?php endif; ?>
        <?php if ($phd_only): ?> &nbsp;·&nbsp; PhD<?php endif; ?>
      </div>
      <br>
      <a href="<?php echo htmlspecialchars($google_search_url); ?>" target="_blank" class="google-btn">
        <i class="fab fa-google"></i> View Results on Google
      </a>
      <div class="google-note">Opens in a new tab on Google.com</div>
    </div>
  <?php endif; ?>

  <!-- PHD RESOURCES -->
  <div class="section-label">PhD & Doctoral Databases</div>
  <div class="phd-grid" style="margin-bottom:28px;">
    <div class="phd-res-card">
      <div class="phd-res-icon"><i class="fas fa-microscope"></i></div>
      <div class="phd-res-title">FindAPhD</div>
      <div class="phd-res-desc">Comprehensive search engine for PhD opportunities and funding worldwide with detailed project descriptions.</div>
      <a href="https://www.findaphd.com/" target="_blank" class="phd-res-link">Visit <i class="fas fa-arrow-right" style="font-size:10px;"></i></a>
    </div>
    <div class="phd-res-card">
      <div class="phd-res-icon"><i class="fas fa-university"></i></div>
      <div class="phd-res-title">PhD Portal</div>
      <div class="phd-res-desc">European database of doctoral programmes and funding opportunities with application guidance.</div>
      <a href="https://www.phdportal.com/" target="_blank" class="phd-res-link">Visit <i class="fas fa-arrow-right" style="font-size:10px;"></i></a>
    </div>
    <div class="phd-res-card">
      <div class="phd-res-icon"><i class="fas fa-award"></i></div>
      <div class="phd-res-title">ProFellow</div>
      <div class="phd-res-desc">Database of fellowships and funded doctoral opportunities with application tips and deadlines.</div>
      <a href="https://www.profellow.com/" target="_blank" class="phd-res-link">Visit <i class="fas fa-arrow-right" style="font-size:10px;"></i></a>
    </div>
  </div>

  <!-- SCHOLARSHIP RESULTS -->
  <?php if (!$using_google_search && !empty($scholarships)): ?>
    <div class="section-label">All Scholarships (<?php echo count($scholarships); ?> listed)</div>
    <div class="schol-grid">
      <?php foreach ($scholarships as $scholarship):
        $deadline_ts = strtotime($scholarship['deadline']);
        $days_left = $deadline_ts ? ceil(($deadline_ts - time()) / 86400) : null;
        $is_soon = $days_left !== null && $days_left > 0 && $days_left <= 60;
        $is_expired = $days_left !== null && $days_left <= 0;
        $is_phd = stripos($scholarship['field_of_study'],'PhD') !== false || stripos($scholarship['title'],'PhD') !== false;
        $flag = $country_flags[$scholarship['country']] ?? '🌍';
      ?>
      <div class="schol-card">
        <div class="schol-card-top">
          <div class="schol-card-badges">
            <?php if ($is_expired): ?>
              <span class="badge-pill" style="background:rgba(100,116,139,0.1);color:#64748B;">Closed</span>
            <?php elseif ($is_soon): ?>
              <span class="badge-pill badge-soon"><i class="fas fa-clock" style="font-size:9px;"></i> <?php echo $days_left; ?> days left</span>
            <?php else: ?>
              <span class="badge-pill badge-active">Active</span>
            <?php endif; ?>
            <?php if ($is_phd): ?><span class="badge-pill badge-phd">PhD</span><?php endif; ?>
          </div>
          <div class="schol-card-title"><?php echo htmlspecialchars($scholarship['title']); ?></div>
          <div class="schol-card-country"><span><?php echo $flag; ?></span><span><?php echo htmlspecialchars($scholarship['country']); ?></span></div>
        </div>
        <div class="schol-card-body">
          <div class="schol-card-desc"><?php echo htmlspecialchars($scholarship['description']); ?></div>
          <div class="schol-meta-row">
            <div class="schol-meta"><i class="fas fa-calendar-alt"></i><span><?php echo htmlspecialchars($scholarship['deadline']); ?></span></div>
            <div class="schol-meta"><i class="fas fa-book-open"></i><span><?php echo htmlspecialchars($scholarship['field_of_study']); ?></span></div>
          </div>
          <div class="schol-tags">
            <span class="schol-tag"><?php echo htmlspecialchars($scholarship['field_of_study']); ?></span>
            <span class="schol-tag"><?php echo htmlspecialchars($scholarship['country']); ?></span>
            <?php if ($is_phd): ?><span class="schol-tag">Doctoral</span><?php endif; ?>
          </div>
        </div>
        <div class="schol-card-footer">
          <?php if (!empty($scholarship['url'])): ?>
            <a href="<?php echo htmlspecialchars($scholarship['url']); ?>" target="_blank" class="schol-apply-btn">
              <i class="fas fa-external-link-alt" style="font-size:11px;"></i> Apply Now
            </a>
          <?php else: ?>
            <a href="apply-scholarship.php?id=<?php echo htmlspecialchars($scholarship['id']); ?>" class="schol-apply-btn">
              <i class="fas fa-info-circle" style="font-size:11px;"></i> Learn More
            </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  <?php elseif (!$using_google_search): ?>
    <div class="no-results">
      <i class="fas fa-search"></i>
      <h3>No Scholarships Found</h3>
      <p>Try adjusting your filters, or use the search bar above to find opportunities via Google.</p>
      <a href="scholarships.php" style="display:inline-flex;align-items:center;gap:8px;margin-top:16px;padding:10px 22px;background:var(--navy);color:var(--white);border-radius:var(--r-sm);font-size:13px;">
        <i class="fas fa-undo"></i> Clear Filters
      </a>
    </div>
  <?php endif; ?>

  <!-- DB results while using Google search -->
  <?php if ($using_google_search && !empty($scholarships)): ?>
    <div class="section-label">Also From Our Database</div>
    <div class="schol-grid">
      <?php foreach ($scholarships as $scholarship):
        $flag = $country_flags[$scholarship['country']] ?? '🌍';
      ?>
      <div class="schol-card">
        <div class="schol-card-top">
          <div class="schol-card-title"><?php echo htmlspecialchars($scholarship['title']); ?></div>
          <div class="schol-card-country"><span><?php echo $flag; ?></span><span><?php echo htmlspecialchars($scholarship['country']); ?></span></div>
        </div>
        <div class="schol-card-body">
          <div class="schol-card-desc"><?php echo htmlspecialchars($scholarship['description']); ?></div>
          <div class="schol-meta-row">
            <div class="schol-meta"><i class="fas fa-calendar-alt"></i><span><?php echo htmlspecialchars($scholarship['deadline']); ?></span></div>
            <div class="schol-meta"><i class="fas fa-book-open"></i><span><?php echo htmlspecialchars($scholarship['field_of_study']); ?></span></div>
          </div>
        </div>
        <div class="schol-card-footer">
          <?php if (!empty($scholarship['url'])): ?>
            <a href="<?php echo htmlspecialchars($scholarship['url']); ?>" target="_blank" class="schol-apply-btn">
              <i class="fas fa-external-link-alt" style="font-size:11px;"></i> Apply Now
            </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</main>

<!-- FOOTER -->
<footer class="portal-footer">
  <div class="footer-copy">&copy; 2026 Bold Footprint Initiatives. All rights reserved.</div>
  <div class="footer-links">
    <a href="/index.html"><i class="fas fa-home" style="font-size:10px;margin-right:4px;"></i>Main Site</a>
    <a href="/about.html">About Us</a>
    <a href="/programs.html">Programs</a>
    <a href="/contact.html">Contact</a>
  </div>
</footer>

<script>
  const sidebar=document.getElementById('sidebar');
  const overlay=document.getElementById('sidebarOverlay');
  const toggle=document.getElementById('mobileToggle');
  function openSidebar(){sidebar.classList.add('active');overlay.classList.add('active');document.body.style.overflow='hidden';}
  function closeSidebar(){sidebar.classList.remove('active');overlay.classList.remove('active');document.body.style.overflow='';}
  if(toggle) toggle.addEventListener('click',()=>sidebar.classList.contains('active')?closeSidebar():openSidebar());
  overlay.addEventListener('click',closeSidebar);
  window.addEventListener('resize',()=>{if(window.innerWidth>768)closeSidebar();});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>