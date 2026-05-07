<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$first_name = '';
$last_name  = '';
$profile_picture = null;
$selected_story = isset($_GET['story']) ? $_GET['story'] : null;
$story_details  = null;
$related_stories = [];
$success_stories = [];

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT first_name, last_name, profile_picture FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $first_name = $user['first_name'];
        $last_name  = $user['last_name'] ?? '';
        $profile_picture = $user['profile_picture'] ?? null;
    }

    if ($selected_story) {
        $story_stmt = $conn->prepare("SELECT * FROM success_stories WHERE slug = :slug");
        $story_stmt->execute([':slug' => $selected_story]);
        $story_details = $story_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$story_details) { header('Location: success-stories.php'); exit(); }

        $related_stmt = $conn->prepare("
            SELECT id, full_name, photo, slug, scholarship_name, university_name, program, country
            FROM success_stories WHERE slug != :slug ORDER BY RANDOM() LIMIT 3
        ");
        $related_stmt->execute([':slug' => $selected_story]);
        $related_stories = $related_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stories_stmt = $conn->prepare("SELECT * FROM success_stories ORDER BY created_at DESC");
        $stories_stmt->execute();
        $success_stories = $stories_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Exception $e) {
    error_log("Success stories error: " . $e->getMessage());
    if (file_exists('includes/fallback_success_stories.php')) include_once 'includes/fallback_success_stories.php';
}

function getInitials($name) {
    $parts = explode(' ', $name);
    return strtoupper(implode('', array_map(fn($p) => $p[0] ?? '', array_slice($parts, 0, 2))));
}

// Stats
$total_stories    = count($success_stories);
$total_countries  = count(array_unique(array_filter(array_column($success_stories, 'country'))));
$total_unis       = count(array_unique(array_filter(array_column($success_stories, 'university_name'))));
$total_schols     = count(array_unique(array_filter(array_column($success_stories, 'scholarship_name'))));

// Notification count
$notification_count = 0;
try {
    $notif_stmt = $conn->prepare("SELECT id FROM notifications WHERE user_id = :user_id AND read_status = 0");
    $notif_stmt->execute([':user_id' => $_SESSION['user_id']]);
    $notification_count = $notif_stmt->rowCount();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Scholar Stories | BFI Scholar Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

    /* ── SIDEBAR (exact match to dashboard) ── */
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

    /* ── HEADER (exact match to dashboard) ── */
    .header{position:fixed;top:0;left:var(--sidebar-width);right:0;height:var(--header-height);background:var(--white);border-bottom:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;padding:0 28px;z-index:100;}
    .header-left{display:flex;align-items:center;gap:16px;}
    .mobile-toggle{display:none;background:none;border:none;cursor:pointer;padding:6px;color:var(--text-secondary);}
    .header-breadcrumb{font-size:13.5px;color:var(--text-muted);}
    .header-breadcrumb strong{color:var(--text-primary);font-weight:600;}
    .header-right{display:flex;align-items:center;gap:16px;}
    .header-icon-btn{width:36px;height:36px;border-radius:var(--r-sm);background:var(--cream);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-secondary);font-size:15px;transition:var(--transition);position:relative;}
    .header-icon-btn:hover{background:var(--cream-dark);color:var(--navy);}
    .notif-dot{position:absolute;top:6px;right:6px;width:8px;height:8px;border-radius:50%;background:#EF4444;border:2px solid var(--white);}
    .header-avatar{width:36px;height:36px;border-radius:50%;background:var(--navy-light);overflow:hidden;border:2px solid var(--border-light);cursor:pointer;display:flex;align-items:center;justify-content:center;}
    .header-avatar img{width:100%;height:100%;object-fit:cover;}
    .header-avatar-init{font-family:var(--font-display);font-size:14px;color:var(--gold-bright);}

    /* ── MAIN ── */
    .main{margin-left:var(--sidebar-width);margin-top:var(--header-height);padding:28px;min-height:calc(100vh - var(--header-height));}

    /* ── PAGE BANNER ── */
    .page-banner{background:var(--navy);border-radius:var(--r-xl);padding:32px 36px;margin-bottom:24px;position:relative;overflow:hidden;}
    .page-banner::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:32px 32px;}
    .page-banner::after{content:'';position:absolute;top:-60px;right:-60px;width:280px;height:280px;background:radial-gradient(circle,rgba(200,160,88,0.1) 0%,transparent 65%);}
    .banner-inner{position:relative;z-index:1;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;}
    .banner-eyebrow{font-size:10px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:rgba(200,160,88,0.7);margin-bottom:8px;}
    .banner-title{font-family:var(--font-display);font-size:clamp(22px,3vw,30px);font-weight:500;color:var(--white);line-height:1.15;margin-bottom:6px;}
    .banner-title em{font-style:italic;color:var(--gold-bright);}
    .banner-sub{font-size:13.5px;font-weight:300;color:rgba(255,255,255,0.5);}
    .banner-back{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.75);font-family:var(--font-body);font-size:13px;font-weight:400;border:1px solid rgba(255,255,255,0.1);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .banner-back:hover{background:rgba(255,255,255,0.1);color:var(--white);}

    /* ── STATS BAR ── */
    .stats-bar{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
    .stat-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:20px 24px;display:flex;align-items:center;gap:14px;transition:var(--transition);}
    .stat-card:hover{box-shadow:var(--shadow-md);}
    .stat-icon-wrap{width:44px;height:44px;border-radius:var(--r-sm);background:var(--cream);display:flex;align-items:center;justify-content:center;font-size:17px;color:var(--navy);flex-shrink:0;}
    .stat-number{font-family:var(--font-display);font-size:26px;font-weight:500;color:var(--navy);line-height:1;}
    .stat-label{font-size:11.5px;color:var(--text-muted);margin-top:2px;}

    /* ── SEARCH + FILTER ── */
    .filters-bar{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:16px 20px;margin-bottom:24px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
    .search-wrap{position:relative;flex:1;min-width:200px;}
    .search-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;}
    .search-input{width:100%;padding:9px 12px 9px 36px;border:1.5px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13px;color:var(--text-primary);outline:none;transition:var(--transition);}
    .search-input:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,160,88,0.1);}
    .filter-chips{display:flex;gap:8px;flex-wrap:wrap;}
    .filter-chip{padding:6px 14px;border-radius:20px;border:1.5px solid var(--border-light);background:var(--cream);font-size:12px;font-weight:500;color:var(--text-secondary);cursor:pointer;transition:var(--transition);}
    .filter-chip:hover,.filter-chip.active{background:var(--navy);color:var(--white);border-color:var(--navy);}
    .filter-count{font-size:11.5px;color:var(--text-muted);white-space:nowrap;margin-left:auto;}

    /* ── STORY CARDS GRID ── */
    .section-label{font-size:10px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);margin-bottom:14px;}
    .stories-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;margin-bottom:24px;}
    .story-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);overflow:hidden;transition:var(--transition);cursor:pointer;}
    .story-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-md);border-color:transparent;}
    .story-card-top{padding:20px 20px 14px;display:flex;align-items:flex-start;gap:14px;background:linear-gradient(135deg,var(--cream) 0%,var(--white) 100%);}
    .story-avatar{width:52px;height:52px;border-radius:50%;overflow:hidden;border:2px solid var(--white);box-shadow:var(--shadow-sm);flex-shrink:0;background:var(--navy-light);display:flex;align-items:center;justify-content:center;}
    .story-avatar img{width:100%;height:100%;object-fit:cover;}
    .story-avatar-init{font-family:var(--font-display);font-size:18px;font-weight:500;color:var(--gold-bright);}
    .story-meta{flex:1;}
    .story-name{font-size:15px;font-weight:600;color:var(--navy);margin-bottom:3px;line-height:1.3;}
    .story-program{font-size:12px;color:var(--text-muted);margin-bottom:8px;}
    .story-tags{display:flex;gap:6px;flex-wrap:wrap;}
    .story-tag{font-size:10.5px;padding:2px 9px;border-radius:20px;background:rgba(200,160,88,0.1);color:var(--gold);border:1px solid rgba(200,160,88,0.2);font-weight:500;}
    .story-tag.country{background:rgba(13,24,41,0.06);color:var(--navy);border-color:rgba(13,24,41,0.1);}
    .story-quote{font-family:var(--font-display);font-size:14.5px;font-style:italic;color:var(--text-secondary);line-height:1.6;padding:0 20px 16px;border-bottom:1px solid var(--border-light);}
    .story-quote::before{content:'\201C';font-size:28px;color:var(--gold-pale);line-height:0;vertical-align:-12px;margin-right:3px;}
    .story-footer{padding:14px 20px;display:flex;align-items:center;justify-content:space-between;}
    .story-schol{font-size:12px;color:var(--text-muted);display:flex;align-items:center;gap:6px;}
    .story-schol i{color:var(--gold);font-size:11px;}
    .story-read-btn{font-size:12px;font-weight:500;color:var(--gold);display:flex;align-items:center;gap:5px;transition:var(--transition);}
    .story-read-btn:hover{gap:8px;}

    /* ── EMPTY STATE ── */
    .empty-state{text-align:center;padding:60px 24px;background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);}
    .empty-icon{font-size:36px;color:var(--gold-pale);margin-bottom:16px;}
    .empty-title{font-family:var(--font-display);font-size:22px;font-weight:500;color:var(--navy);margin-bottom:8px;}
    .empty-sub{font-size:13.5px;color:var(--text-muted);}

    /* ── DETAIL VIEW ── */
    .detail-header-card{background:var(--navy);border-radius:var(--r-xl);padding:36px;margin-bottom:20px;position:relative;overflow:hidden;}
    .detail-header-card::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.025) 1px,transparent 1px);background-size:28px 28px;}
    .detail-header-card::after{content:'';position:absolute;top:-60px;right:-60px;width:300px;height:300px;background:radial-gradient(circle,rgba(200,160,88,0.12) 0%,transparent 65%);}
    .detail-header-inner{position:relative;z-index:1;display:flex;align-items:center;gap:28px;flex-wrap:wrap;}
    .detail-photo{width:96px;height:96px;border-radius:50%;overflow:hidden;border:3px solid rgba(200,160,88,0.4);flex-shrink:0;background:var(--navy-light);display:flex;align-items:center;justify-content:center;}
    .detail-photo img{width:100%;height:100%;object-fit:cover;}
    .detail-photo-init{font-family:var(--font-display);font-size:32px;font-weight:500;color:var(--gold-bright);}
    .detail-meta{}
    .detail-name{font-family:var(--font-display);font-size:clamp(22px,3vw,32px);font-weight:500;color:var(--white);margin-bottom:10px;}
    .detail-facts{display:flex;flex-wrap:wrap;gap:12px;}
    .detail-fact{display:flex;align-items:center;gap:7px;font-size:13px;color:rgba(255,255,255,0.6);}
    .detail-fact i{color:var(--gold-bright);font-size:12px;width:14px;}

    .detail-quote-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:28px 32px;margin-bottom:20px;border-left:4px solid var(--gold);}
    .detail-quote-text{font-family:var(--font-display);font-size:clamp(18px,2vw,22px);font-style:italic;color:var(--text-secondary);line-height:1.6;}
    .detail-quote-text::before{content:'\201C';font-size:48px;color:var(--gold-pale);line-height:0;vertical-align:-20px;margin-right:4px;}

    .detail-sections{display:flex;flex-direction:column;gap:20px;margin-bottom:24px;}
    .detail-section{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:24px 28px;}
    .detail-section-title{font-family:var(--font-display);font-size:20px;font-weight:500;color:var(--navy);margin-bottom:16px;display:flex;align-items:center;gap:10px;}
    .detail-section-title i{color:var(--gold);font-size:16px;}
    .detail-section-body{font-size:14px;color:var(--text-secondary);line-height:1.8;}

    .achievements-list{list-style:none;display:flex;flex-direction:column;gap:10px;margin-top:4px;}
    .achievement-item{display:flex;align-items:flex-start;gap:12px;padding:12px 16px;background:var(--cream);border-radius:var(--r-sm);font-size:13.5px;color:var(--text-secondary);}
    .achievement-item::before{content:'\f091';font-family:'Font Awesome 6 Free';font-weight:900;color:var(--gold);flex-shrink:0;margin-top:1px;}

    /* ── CTA CARD ── */
    .cta-card{background:var(--navy);border-radius:var(--r-xl);padding:32px 36px;margin-bottom:24px;position:relative;overflow:hidden;text-align:center;}
    .cta-card::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.025) 1px,transparent 1px);background-size:28px 28px;}
    .cta-card::after{content:'';position:absolute;bottom:-40px;left:50%;transform:translateX(-50%);width:400px;height:200px;background:radial-gradient(circle,rgba(200,160,88,0.1) 0%,transparent 65%);}
    .cta-inner{position:relative;z-index:1;}
    .cta-eyebrow{font-size:10px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:rgba(200,160,88,0.7);margin-bottom:8px;}
    .cta-title{font-family:var(--font-display);font-size:clamp(20px,2.5vw,28px);font-weight:500;color:var(--white);margin-bottom:8px;}
    .cta-sub{font-size:13.5px;color:rgba(255,255,255,0.5);margin-bottom:20px;}
    .cta-btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;}
    .btn-gold{display:inline-flex;align-items:center;gap:8px;padding:11px 22px;background:var(--gold);color:var(--midnight);font-family:var(--font-body);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-gold:hover{background:var(--gold-bright);transform:translateY(-1px);}
    .btn-ghost-sm{display:inline-flex;align-items:center;gap:8px;padding:11px 22px;background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.75);font-family:var(--font-body);font-size:13px;font-weight:400;border:1px solid rgba(255,255,255,0.1);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-ghost-sm:hover{background:rgba(255,255,255,0.1);color:var(--white);}

    /* ── RELATED STORIES ── */
    .related-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;}
    .related-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:16px 20px;display:flex;align-items:center;gap:14px;transition:var(--transition);}
    .related-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-md);border-color:transparent;}
    .related-avatar{width:44px;height:44px;border-radius:50%;overflow:hidden;border:2px solid var(--border-light);flex-shrink:0;background:var(--navy-light);display:flex;align-items:center;justify-content:center;}
    .related-avatar img{width:100%;height:100%;object-fit:cover;}
    .related-avatar-init{font-family:var(--font-display);font-size:15px;font-weight:500;color:var(--gold-bright);}
    .related-name{font-size:13.5px;font-weight:500;color:var(--navy);}
    .related-uni{font-size:12px;color:var(--text-muted);}
    .related-arrow{margin-left:auto;color:var(--gold);font-size:12px;transition:var(--transition);}
    .related-card:hover .related-arrow{transform:translateX(3px);}

    /* ── FOOTER ── */
    .portal-footer{margin-left:var(--sidebar-width);background:var(--white);padding:16px 28px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;}
    .footer-copy{font-size:12px;color:var(--text-muted);}
    .footer-links{display:flex;gap:20px;}
    .footer-links a{font-size:12px;color:var(--text-muted);transition:color var(--transition);}
    .footer-links a:hover{color:var(--gold);}

    /* ── OVERLAY ── */
    .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:150;backdrop-filter:blur(4px);}
    .sidebar-overlay.active{display:block;}

    /* ── NO-RESULTS (hidden initially) ── */
    .no-results{display:none;text-align:center;padding:40px;background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);}
    .no-results.show{display:block;}

    @media(max-width:1100px){.stats-bar{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:768px){
      .sidebar{transform:translateX(-100%);}
      .sidebar.active{transform:translateX(0);}
      .header{left:0;}
      .main,.portal-footer{margin-left:0;}
      .mobile-toggle{display:flex;}
      .header-breadcrumb{display:none;}
      .stats-bar{grid-template-columns:repeat(2,1fr);}
      .stories-grid{grid-template-columns:1fr;}
      .detail-header-inner{flex-direction:column;text-align:center;}
    }
    @media(max-width:480px){.stats-bar{grid-template-columns:1fr}.cta-btns{flex-direction:column;align-items:center}}
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
    <div class="nav-item"><a href="scholarships.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Scholarships</a></div>
    <div class="nav-item"><a href="mentors.php" class="nav-link"><i class="fas fa-users"></i> My Mentor</a></div>
    <div class="nav-item"><a href="application-help.php" class="nav-link"><i class="fas fa-question-circle"></i> Application Help</a></div>
    <div class="nav-item"><a href="resources.php" class="nav-link"><i class="fas fa-book"></i> Resources</a></div>
    <div class="nav-item"><a href="success-stories.php" class="nav-link active"><i class="fas fa-star"></i> Scholar Stories</a></div>
    <div class="nav-section-label">Account</div>
    <div class="nav-item"><a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a></div>
  </nav>

  <div class="sidebar-bottom">
    <a href="logout.php" class="nav-link" style="color:rgba(239,68,68,0.7);">
      <i class="fas fa-sign-out-alt"></i> Log Out
    </a>
  </div>
</aside>

<!-- HEADER -->
<header class="header">
  <div class="header-left">
    <button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
    <div class="header-breadcrumb"><a href="dashboard.php" style="color:var(--text-muted)">Dashboard</a> &nbsp;/&nbsp; <strong>Scholar Stories</strong></div>
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

<?php if ($story_details): ?>
  <!-- ── DETAIL VIEW ── -->
  <div class="page-banner">
    <div class="banner-inner">
      <div>
        <div class="banner-eyebrow">Scholar Story</div>
        <div class="banner-title"><?php echo htmlspecialchars($story_details['full_name']); ?></div>
        <div class="banner-sub"><?php echo htmlspecialchars($story_details['university_name'] ?? ''); ?> · <?php echo htmlspecialchars($story_details['program'] ?? ''); ?></div>
      </div>
      <a href="success-stories.php" class="banner-back"><i class="fas fa-arrow-left"></i> All Stories</a>
    </div>
  </div>

  <!-- Profile header -->
  <div class="detail-header-card">
    <div class="detail-header-inner">
      <div class="detail-photo">
        <img src="/uploads/scholars/<?php echo htmlspecialchars($story_details['photo'] ?? 'default-avatar.png'); ?>"
             alt="<?php echo htmlspecialchars($story_details['full_name']); ?>"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
        <div class="detail-photo-init" style="display:none;"><?php echo getInitials($story_details['full_name']); ?></div>
      </div>
      <div class="detail-meta">
        <div class="detail-name"><?php echo htmlspecialchars($story_details['full_name']); ?></div>
        <div class="detail-facts">
          <?php if (!empty($story_details['scholarship_name'])): ?><div class="detail-fact"><i class="fas fa-award"></i><?php echo htmlspecialchars($story_details['scholarship_name']); ?></div><?php endif; ?>
          <?php if (!empty($story_details['program'])): ?><div class="detail-fact"><i class="fas fa-graduation-cap"></i><?php echo htmlspecialchars($story_details['program']); ?></div><?php endif; ?>
          <?php if (!empty($story_details['university_name'])): ?><div class="detail-fact"><i class="fas fa-university"></i><?php echo htmlspecialchars($story_details['university_name']); ?></div><?php endif; ?>
          <?php if (!empty($story_details['country'])): ?><div class="detail-fact"><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($story_details['country']); ?></div><?php endif; ?>
          <?php if (!empty($story_details['start_year'])): ?><div class="detail-fact"><i class="fas fa-calendar"></i>Started <?php echo htmlspecialchars($story_details['start_year']); ?></div><?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Quote -->
  <?php if (!empty($story_details['quote'])): ?>
  <div class="detail-quote-card">
    <div class="detail-quote-text"><?php echo htmlspecialchars($story_details['quote']); ?></div>
  </div>
  <?php endif; ?>

  <!-- Sections -->
  <div class="detail-sections">
    <?php
    $sections = [
      ['background',         'fas fa-user',        'Background'],
      ['motivation',         'fas fa-fire',         'Motivation'],
      ['bfi_discovery',      'fas fa-search',       'Discovering BFI'],
      ['challenges',         'fas fa-mountain',     'Challenges Faced'],
      ['application_journey','fas fa-route',        'Application Journey'],
      ['story',              'fas fa-book-open',    'The Journey'],
      ['feeling',            'fas fa-heart',        'How It Feels'],
      ['looking_forward',    'fas fa-binoculars',   'Looking Forward'],
      ['advice',             'fas fa-lightbulb',    'Advice for Applicants'],
    ];
    foreach ($sections as [$field, $icon, $label]):
      if (!empty($story_details[$field])):
    ?>
    <div class="detail-section">
      <div class="detail-section-title"><i class="<?php echo $icon; ?>"></i><?php echo $label; ?></div>
      <div class="detail-section-body"><?php echo nl2br(htmlspecialchars($story_details[$field])); ?></div>
    </div>
    <?php
      endif;
    endforeach;
    ?>

    <?php if (!empty($story_details['achievements'])): ?>
    <div class="detail-section">
      <div class="detail-section-title"><i class="fas fa-trophy"></i>Achievements</div>
      <ul class="achievements-list">
        <?php foreach (array_filter(explode("\n", $story_details['achievements'])) as $ach): ?>
          <li class="achievement-item"><?php echo htmlspecialchars(trim($ach)); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>

  <!-- CTA -->
  <div class="cta-card">
    <div class="cta-inner">
      <div class="cta-eyebrow">Your Turn</div>
      <div class="cta-title">Ready to write your own <em>success story?</em></div>
      <div class="cta-sub">Join a community of scholars who have turned ambition into achievement with BFI's support.</div>
      <div class="cta-btns">
        <a href="application-help.php" class="btn-gold"><i class="fas fa-rocket"></i> Get Application Help</a>
        <a href="resources.php" class="btn-ghost-sm"><i class="fas fa-book"></i> Browse Resources</a>
      </div>
    </div>
  </div>

  <!-- Related -->
  <?php if (!empty($related_stories)): ?>
  <div class="section-label">More Stories</div>
  <div class="related-grid">
    <?php foreach ($related_stories as $r): ?>
    <a href="?story=<?php echo htmlspecialchars($r['slug']); ?>" class="related-card">
      <div class="related-avatar">
        <img src="/uploads/scholars/<?php echo htmlspecialchars($r['photo'] ?? 'default-avatar.png'); ?>"
             alt="<?php echo htmlspecialchars($r['full_name']); ?>"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
        <div class="related-avatar-init" style="display:none;"><?php echo getInitials($r['full_name']); ?></div>
      </div>
      <div>
        <div class="related-name"><?php echo htmlspecialchars($r['full_name']); ?></div>
        <div class="related-uni"><?php echo htmlspecialchars($r['university_name'] ?? ''); ?></div>
      </div>
      <i class="fas fa-arrow-right related-arrow"></i>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

<?php else: ?>
  <!-- ── LISTING VIEW ── -->

  <!-- Banner -->
  <div class="page-banner">
    <div class="banner-inner">
      <div>
        <div class="banner-eyebrow">Inspiration</div>
        <div class="banner-title">Scholar <em>Success Stories</em></div>
        <div class="banner-sub">Extraordinary journeys from BFI scholars around the world.</div>
      </div>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-bar">
    <div class="stat-card"><div class="stat-icon-wrap"><i class="fas fa-users"></i></div><div><div class="stat-number"><?php echo $total_stories; ?></div><div class="stat-label">Scholar Stories</div></div></div>
    <div class="stat-card"><div class="stat-icon-wrap"><i class="fas fa-globe"></i></div><div><div class="stat-number"><?php echo $total_countries; ?></div><div class="stat-label">Countries</div></div></div>
    <div class="stat-card"><div class="stat-icon-wrap"><i class="fas fa-university"></i></div><div><div class="stat-number"><?php echo $total_unis; ?></div><div class="stat-label">Universities</div></div></div>
    <div class="stat-card"><div class="stat-icon-wrap"><i class="fas fa-award"></i></div><div><div class="stat-number"><?php echo $total_schols; ?></div><div class="stat-label">Scholarships</div></div></div>
  </div>

  <!-- Search + Filter -->
  <?php
  $countries = array_unique(array_filter(array_column($success_stories, 'country')));
  sort($countries);
  ?>
  <div class="filters-bar">
    <div class="search-wrap">
      <i class="fas fa-search search-icon"></i>
      <input type="text" class="search-input" id="storySearch" placeholder="Search by name, university, or field…">
    </div>
    <div class="filter-chips" id="filterChips">
      <div class="filter-chip active" data-filter="all">All</div>
      <?php foreach ($countries as $c): ?>
        <div class="filter-chip" data-filter="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></div>
      <?php endforeach; ?>
    </div>
    <div class="filter-count" id="filterCount"><?php echo $total_stories; ?> stories</div>
  </div>

  <!-- Grid -->
  <?php if (empty($success_stories)): ?>
    <div class="empty-state">
      <div class="empty-icon"><i class="fas fa-book-open"></i></div>
      <div class="empty-title">Stories coming soon</div>
      <p class="empty-sub">Check back shortly for inspiring journeys from BFI scholars worldwide.</p>
    </div>
  <?php else: ?>
    <div class="section-label">All Stories</div>
    <div class="stories-grid" id="storiesGrid">
      <?php foreach ($success_stories as $story): ?>
      <div class="story-card"
           data-country="<?php echo htmlspecialchars($story['country'] ?? ''); ?>"
           data-name="<?php echo htmlspecialchars(strtolower($story['full_name'] ?? '')); ?>"
           data-uni="<?php echo htmlspecialchars(strtolower($story['university_name'] ?? '')); ?>"
           data-program="<?php echo htmlspecialchars(strtolower($story['program'] ?? '')); ?>"
           onclick="window.location.href='?story=<?php echo htmlspecialchars($story['slug']); ?>'">
        <div class="story-card-top">
          <div class="story-avatar">
            <img src="/uploads/scholars/<?php echo htmlspecialchars($story['photo'] ?? 'default-avatar.png'); ?>"
                 alt="<?php echo htmlspecialchars($story['full_name']); ?>"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
            <div class="story-avatar-init" style="display:none;"><?php echo getInitials($story['full_name']); ?></div>
          </div>
          <div class="story-meta">
            <div class="story-name"><?php echo htmlspecialchars($story['full_name']); ?></div>
            <div class="story-program"><?php echo htmlspecialchars($story['program'] ?? ''); ?> · <?php echo htmlspecialchars($story['university_name'] ?? ''); ?></div>
            <div class="story-tags">
              <?php if (!empty($story['country'])): ?><span class="story-tag country"><?php echo htmlspecialchars($story['country']); ?></span><?php endif; ?>
              <?php if (!empty($story['scholarship_name'])): ?><span class="story-tag"><?php echo htmlspecialchars($story['scholarship_name']); ?></span><?php endif; ?>
            </div>
          </div>
        </div>
        <?php if (!empty($story['quote'])): ?>
        <div class="story-quote"><?php echo htmlspecialchars(mb_strimwidth($story['quote'], 0, 140, '…')); ?></div>
        <?php endif; ?>
        <div class="story-footer">
          <div class="story-schol"><i class="fas fa-award"></i><?php echo htmlspecialchars(mb_strimwidth($story['scholarship_name'] ?? '', 0, 32, '…')); ?></div>
          <div class="story-read-btn">Read story <i class="fas fa-arrow-right" style="font-size:10px;"></i></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="no-results" id="noResults">
      <div style="font-size:28px;color:var(--gold-pale);margin-bottom:12px;"><i class="fas fa-search"></i></div>
      <div style="font-family:var(--font-display);font-size:20px;color:var(--navy);margin-bottom:6px;">No stories found</div>
      <p style="font-size:13.5px;color:var(--text-muted);">Try a different search term or filter.</p>
    </div>
  <?php endif; ?>

  <!-- CTA -->
  <div class="cta-card" style="margin-top:8px;">
    <div class="cta-inner">
      <div class="cta-eyebrow">Your Journey Awaits</div>
      <div class="cta-title">Become the next <em>success story.</em></div>
      <div class="cta-sub">Every scholar above started exactly where you are now.</div>
      <div class="cta-btns">
        <a href="application-help.php" class="btn-gold"><i class="fas fa-rocket"></i> Start Your Application</a>
        <a href="resources.php" class="btn-ghost-sm"><i class="fas fa-book"></i> Browse Resources</a>
      </div>
    </div>
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
  // Sidebar toggle
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  const toggle  = document.getElementById('mobileToggle');
  function openSidebar(){sidebar.classList.add('active');overlay.classList.add('active');document.body.style.overflow='hidden';}
  function closeSidebar(){sidebar.classList.remove('active');overlay.classList.remove('active');document.body.style.overflow='';}
  if (toggle) toggle.addEventListener('click',()=>sidebar.classList.contains('active')?closeSidebar():openSidebar());
  overlay.addEventListener('click',closeSidebar);
  window.addEventListener('resize',()=>{if(window.innerWidth>768)closeSidebar();});

  // Search + Filter
  const searchInput = document.getElementById('storySearch');
  const filterChips = document.querySelectorAll('.filter-chip');
  const grid        = document.getElementById('storiesGrid');
  const noResults   = document.getElementById('noResults');
  const filterCount = document.getElementById('filterCount');
  let activeFilter  = 'all';

  function applyFilters() {
    if (!grid) return;
    const q = (searchInput?.value || '').toLowerCase().trim();
    const cards = grid.querySelectorAll('.story-card');
    let visible = 0;
    cards.forEach(card => {
      const country = card.dataset.country || '';
      const matchFilter = activeFilter === 'all' || country === activeFilter;
      const matchSearch = !q ||
        card.dataset.name.includes(q) ||
        card.dataset.uni.includes(q) ||
        card.dataset.program.includes(q) ||
        country.toLowerCase().includes(q);
      const show = matchFilter && matchSearch;
      card.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    if (filterCount) filterCount.textContent = visible + ' ' + (visible === 1 ? 'story' : 'stories');
    if (noResults) noResults.classList.toggle('show', visible === 0);
  }

  filterChips.forEach(chip => {
    chip.addEventListener('click', () => {
      filterChips.forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      activeFilter = chip.dataset.filter;
      applyFilters();
    });
  });
  if (searchInput) searchInput.addEventListener('input', applyFilters);
</script>
</body>
</html>