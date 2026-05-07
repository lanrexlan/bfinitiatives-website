<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$first_name = '';
$last_name = '';
$profile_picture = null;
$notification_count = 0;
$success_msg = '';
$error_msg = '';

// Journey stages definition
$stages = [
    ['id'=>1, 'key'=>'awarded',    'label'=>'Scholarship Awarded',     'icon'=>'fa-award',         'color'=>'#C8A058',
     'desc'=>'You have been selected as a BFI Scholar. This marks the beginning of your international education journey.',
     'resources'=>[['label'=>'Read your award letter','url'=>'view-agreement.php'],['label'=>'Complete your profile','url'=>'profile.php']]],
    ['id'=>2, 'key'=>'documents',  'label'=>'Documents Submitted',     'icon'=>'fa-file-alt',       'color'=>'#7C9EBF',
     'desc'=>'All required documents including transcripts, identification, and recommendation letters have been submitted and verified.',
     'resources'=>[['label'=>'View my documents','url'=>'documents.php'],['label'=>'Upload more documents','url'=>'documents.php']]],
    ['id'=>3, 'key'=>'mentor',     'label'=>'Mentor Assigned',         'icon'=>'fa-user-tie',       'color'=>'#6BAF8A',
     'desc'=>'You have been matched with a dedicated BFI mentor who will guide you through your scholarship application process.',
     'resources'=>[['label'=>'Meet your mentor','url'=>'mentors.php'],['label'=>'Schedule a session','url'=>'mentors.php']]],
    ['id'=>4, 'key'=>'preparation','label'=>'Application Preparation', 'icon'=>'fa-pen-nib',        'color'=>'#B07CC6',
     'desc'=>'Working on your personal statement, research proposal, CV, and other application materials with mentor support.',
     'resources'=>[['label'=>'Application materials','url'=>'application-help.php'],['label'=>'Resource library','url'=>'resources.php']]],
    ['id'=>5, 'key'=>'placement',  'label'=>'Placement',               'icon'=>'fa-flag-checkered', 'color'=>'#E07C5A',
     'desc'=>'The final stage — submitting your applications to target universities and awaiting admission decisions.',
     'resources'=>[['label'=>'Find scholarships','url'=>'scholarships.php'],['label'=>'Contact mentors','url'=>'mentors.php']]],
];

// Current active stage (in a real app this comes from the DB per user; default to 4)
$active_stage = 4;

try {
    $db   = new Database();
    $conn = $db->getConnection();

    $us = $conn->prepare("SELECT first_name, last_name, profile_picture FROM users WHERE id = :uid");
    $us->execute([':uid' => $_SESSION['user_id']]);
    $ud = $us->fetch(PDO::FETCH_ASSOC);
    if ($ud) { $first_name=$ud['first_name']; $last_name=$ud['last_name']??''; $profile_picture=$ud['profile_picture']??null; }

    $ns = $conn->prepare("SELECT id FROM notifications WHERE user_id = :uid AND read_status = 0");
    $ns->execute([':uid' => $_SESSION['user_id']]);
    $notification_count = $ns->rowCount();

    // Try to get journey stage from DB
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS scholar_journey (id SERIAL PRIMARY KEY, user_id INTEGER UNIQUE NOT NULL, active_stage INTEGER DEFAULT 4, notes TEXT DEFAULT '', updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_sj_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)");
        $jq = $conn->prepare("SELECT active_stage, notes FROM scholar_journey WHERE user_id = :uid");
        $jq->execute([':uid' => $_SESSION['user_id']]);
        $jr = $jq->fetch(PDO::FETCH_ASSOC);
        if ($jr) {
            $active_stage = (int)$jr['active_stage'];
            $journey_notes = $jr['notes'] ?? '';
        } else {
            // Insert default row
            $conn->prepare("INSERT INTO scholar_journey (user_id, active_stage, notes) VALUES (:uid, 4, '') ON CONFLICT (user_id) DO NOTHING")->execute([':uid'=>$_SESSION['user_id']]);
            $journey_notes = '';
        }
    } catch (Exception $e) { $journey_notes = ''; }

    // Save notes
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notes'])) {
        $notes = trim($_POST['journey_notes'] ?? '');
        try {
            $conn->prepare("INSERT INTO scholar_journey (user_id, active_stage, notes, updated_at) VALUES (:uid, :stage, :notes, NOW()) ON CONFLICT (user_id) DO UPDATE SET notes=:notes2, updated_at=NOW()")
                 ->execute([':uid'=>$_SESSION['user_id'],':stage'=>$active_stage,':notes'=>$notes,':notes2'=>$notes]);
            $journey_notes = $notes;
            $success_msg = "Your notes have been saved.";
        } catch (Exception $e) { $error_msg = "Could not save notes."; }
    }

} catch (Exception $e) {
    error_log("My Journey error: ".$e->getMessage());
}

// Document stats for journey checklist
$doc_stats = ['cv'=>false,'statement'=>false,'research'=>false,'recommendation'=>false,'language'=>false];
try {
    $dq = $conn->prepare("SELECT DISTINCT document_type FROM user_documents WHERE user_id=:uid AND review_status != 'rejected'");
    $dq->execute([':uid'=>$_SESSION['user_id']]);
    foreach ($dq->fetchAll(PDO::FETCH_COLUMN) as $t) { if (isset($doc_stats[$t])) $doc_stats[$t] = true; }
} catch (Exception $e) { /* silent */ }

$stage_status = function($i) use ($active_stage) {
    if ($i < $active_stage) return 'done';
    if ($i === $active_stage) return 'active';
    return 'future';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>My Journey | BFI Scholar Portal</title>
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
    .header{position:fixed;top:0;left:var(--sidebar-width);right:0;height:var(--header-height);background:var(--white);border-bottom:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;padding:0 28px;z-index:100;}
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
    .main{margin-left:var(--sidebar-width);margin-top:var(--header-height);padding:28px;min-height:calc(100vh - var(--header-height));}

    /* ALERTS */
    .alert-banner{border-radius:var(--r-md);padding:14px 18px;margin-bottom:20px;font-size:13.5px;display:flex;align-items:center;gap:10px;}
    .alert-success-banner{background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);color:#065F46;}
    .alert-error-banner{background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);color:#991B1B;}

    /* JOURNEY HERO BANNER */
    .journey-hero{background:var(--navy);border-radius:var(--r-xl);padding:32px 36px;margin-bottom:24px;position:relative;overflow:hidden;}
    .journey-hero::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:32px 32px;}
    .journey-hero::after{content:'';position:absolute;top:-60px;right:-60px;width:280px;height:280px;background:radial-gradient(circle,rgba(200,160,88,0.1) 0%,transparent 65%);}
    .journey-hero-inner{position:relative;z-index:1;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;}
    .jh-eyebrow{font-size:10px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:rgba(200,160,88,0.7);margin-bottom:8px;}
    .jh-title{font-family:var(--font-display);font-size:clamp(22px,3vw,32px);font-weight:500;color:var(--white);line-height:1.15;margin-bottom:6px;}
    .jh-title em{font-style:italic;color:var(--gold-bright);}
    .jh-sub{font-size:13.5px;font-weight:300;color:rgba(255,255,255,0.5);}
    .jh-progress{display:flex;flex-direction:column;align-items:flex-end;gap:8px;min-width:180px;}
    .jh-prog-label{font-size:12px;color:rgba(255,255,255,0.5);text-align:right;}
    .jh-prog-label strong{color:var(--gold-bright);font-weight:600;}
    .jh-prog-bar{width:180px;height:6px;background:rgba(255,255,255,0.1);border-radius:3px;overflow:hidden;}
    .jh-prog-fill{height:100%;background:linear-gradient(90deg,var(--gold),var(--gold-bright));border-radius:3px;transition:width 0.6s var(--ease);}
    .jh-stage-pill{background:rgba(200,160,88,0.15);border:1px solid rgba(200,160,88,0.25);color:var(--gold-bright);font-size:12px;font-weight:500;padding:6px 14px;border-radius:20px;}

    /* SECTION LABEL */
    .section-label{font-size:10px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);margin-bottom:14px;}

    /* VISUAL TRACK */
    .track-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:28px 24px;margin-bottom:24px;}
    .track-steps{display:flex;align-items:flex-start;gap:0;position:relative;padding-bottom:8px;}
    .track-step{display:flex;flex-direction:column;align-items:center;flex:1;position:relative;cursor:pointer;}
    .track-step:not(:last-child)::after{content:'';position:absolute;top:18px;left:50%;width:100%;height:2px;background:var(--border-light);z-index:0;}
    .track-step.done:not(:last-child)::after{background:var(--gold);}
    .track-step.active:not(:last-child)::after{background:linear-gradient(90deg,var(--gold),var(--border-light));}
    .track-dot{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;z-index:1;transition:var(--transition);border:2px solid transparent;}
    .track-dot.done{background:var(--gold);color:var(--midnight);}
    .track-dot.active{background:var(--navy);color:var(--white);box-shadow:0 0 0 5px rgba(200,160,88,0.2);}
    .track-dot.future{background:var(--cream);color:var(--text-muted);border:2px solid var(--border-light);}
    .track-step:hover .track-dot{transform:scale(1.12);}
    .track-label{font-size:10.5px;font-weight:500;text-align:center;margin-top:10px;max-width:80px;line-height:1.35;}
    .track-step.done .track-label{color:var(--gold);}
    .track-step.active .track-label{color:var(--navy);font-weight:600;}
    .track-step.future .track-label{color:var(--text-muted);}

    /* STAGE DETAIL CARDS */
    .stage-detail-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);overflow:hidden;margin-bottom:16px;transition:var(--transition);}
    .stage-detail-card:hover{box-shadow:var(--shadow-sm);}
    .stage-detail-head{display:flex;align-items:center;gap:16px;padding:18px 20px;cursor:pointer;user-select:none;}
    .stage-detail-icon{width:42px;height:42px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
    .stage-detail-title{font-size:14px;font-weight:600;color:var(--navy);margin-bottom:2px;}
    .stage-detail-sub{font-size:12px;color:var(--text-muted);}
    .stage-status-chip{margin-left:auto;display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:12px;font-size:11px;font-weight:600;white-space:nowrap;flex-shrink:0;}
    .chip-done{background:rgba(16,185,129,0.1);color:#059669;}
    .chip-active{background:rgba(200,160,88,0.12);color:var(--gold);}
    .chip-future{background:var(--cream);color:var(--text-muted);}
    .stage-chevron{margin-left:12px;color:var(--text-muted);font-size:12px;transition:transform var(--transition);flex-shrink:0;}
    .stage-chevron.open{transform:rotate(180deg);}
    .stage-detail-body{padding:0 20px;max-height:0;overflow:hidden;transition:max-height 0.4s var(--ease),padding 0.3s var(--ease);}
    .stage-detail-body.open{max-height:500px;padding:0 20px 20px;}
    .stage-desc{font-size:13.5px;color:var(--text-secondary);line-height:1.65;margin-bottom:16px;padding-top:4px;}
    .stage-resources{display:flex;gap:10px;flex-wrap:wrap;}
    .stage-resource-link{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:var(--cream);color:var(--navy);border:1px solid var(--border-light);border-radius:var(--r-sm);font-size:12.5px;font-weight:500;transition:var(--transition);}
    .stage-resource-link:hover{background:var(--navy);color:var(--gold-bright);border-color:var(--navy);}

    /* TWO-COL LAYOUT */
    .two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;}

    /* CHECKLIST CARD */
    .plain-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:24px;}
    .card-title-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;}
    .card-heading{font-family:var(--font-display);font-size:18px;font-weight:500;color:var(--navy);}
    .card-heading em{font-style:italic;color:var(--gold);}
    .card-link{font-size:12px;color:var(--gold);font-weight:500;display:flex;align-items:center;gap:5px;transition:var(--transition);}
    .card-link:hover{gap:8px;}
    .checklist-item{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border-light);}
    .checklist-item:last-child{border-bottom:none;}
    .ci-dot{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;}
    .ci-done{background:rgba(16,185,129,0.1);color:#10B981;}
    .ci-todo{background:var(--cream);color:var(--text-muted);border:1px solid var(--border-light);}
    .ci-title{font-size:13px;font-weight:500;color:var(--navy);flex:1;}
    .ci-action{font-size:11.5px;font-weight:500;color:var(--gold);background:rgba(200,160,88,0.08);border:1px solid rgba(200,160,88,0.2);padding:4px 10px;border-radius:10px;transition:var(--transition);}
    .ci-action:hover{background:var(--gold);color:var(--midnight);}

    /* NOTES CARD */
    .notes-textarea{width:100%;min-height:140px;padding:12px 14px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13px;color:var(--text-primary);background:var(--cream);resize:vertical;transition:var(--transition);}
    .notes-textarea:focus{outline:none;border-color:var(--gold);background:var(--white);box-shadow:0 0 0 3px rgba(200,160,88,0.1);}
    .btn-save-notes{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:var(--gold);color:var(--midnight);font-family:var(--font-body);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);margin-top:12px;}
    .btn-save-notes:hover{background:var(--gold-bright);}

    /* QUICK LINKS */
    .quick-links-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;}
    .quick-link-card{background:var(--cream);border-radius:var(--r-md);padding:16px;text-align:center;border:1px solid var(--border-light);transition:var(--transition);cursor:pointer;}
    .quick-link-card:hover{background:var(--navy);border-color:var(--navy);}
    .quick-link-card:hover .ql-icon{background:rgba(200,160,88,0.2);color:var(--gold-bright);}
    .quick-link-card:hover .ql-title{color:var(--white);}
    .quick-link-card:hover .ql-desc{color:rgba(255,255,255,0.5);}
    .ql-icon{width:40px;height:40px;background:var(--white);border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:16px;color:var(--navy);margin:0 auto 10px;transition:var(--transition);}
    .ql-title{font-size:13px;font-weight:500;color:var(--navy);margin-bottom:3px;transition:var(--transition);}
    .ql-desc{font-size:11px;color:var(--text-muted);transition:var(--transition);}

    /* FOOTER */
    .portal-footer{margin-left:var(--sidebar-width);background:var(--white);padding:16px 28px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;}
    .footer-copy{font-size:12px;color:var(--text-muted);}
    .footer-links{display:flex;gap:20px;}
    .footer-links a{font-size:12px;color:var(--text-muted);transition:color var(--transition);}
    .footer-links a:hover{color:var(--gold);}
    .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:150;backdrop-filter:blur(4px);}
    .sidebar-overlay.active{display:block;}

    @media(max-width:1100px){.two-col{grid-template-columns:1fr;}.quick-links-grid{grid-template-columns:repeat(2,1fr);}}
    @media(max-width:768px){
      .sidebar{transform:translateX(-100%);}.sidebar.active{transform:translateX(0);}
      .header{left:0;}.main,.portal-footer{margin-left:0;}
      .mobile-toggle{display:flex;}
      .track-steps{overflow-x:auto;padding-bottom:8px;}
      .quick-links-grid{grid-template-columns:repeat(2,1fr);}
      .journey-hero-inner{flex-direction:column;align-items:flex-start;}
      .jh-progress{align-items:flex-start;}
      .jh-prog-bar{width:100%;}
    }
    @media(max-width:480px){.quick-links-grid{grid-template-columns:1fr;}}
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
          <img src="<?= htmlspecialchars('./uploads/profile_pictures/'.$profile_picture) ?>" alt="Profile" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
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
    <div class="nav-item"><a href="my-journey.php" class="nav-link active"><i class="fas fa-route"></i> My Journey
      <?php if ($notification_count > 0): ?><span class="nav-badge"><?= $notification_count ?></span><?php endif; ?>
    </a></div>
    <div class="nav-section-label">Resources</div>
    <div class="nav-item"><a href="documents.php" class="nav-link"><i class="fas fa-file-alt"></i> My Documents</a></div>
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
    <div class="header-page-title">My <em>Journey</em></div>
  </div>
  <div class="header-right">
    <button class="header-icon-btn" title="Notifications">
      <i class="fas fa-bell"></i>
      <?php if ($notification_count > 0): ?><div class="notif-dot"></div><?php endif; ?>
    </button>
    <a href="profile.php">
      <div class="header-avatar">
        <?php if ($profile_picture): ?>
          <img src="<?= htmlspecialchars('./uploads/profile_pictures/'.$profile_picture) ?>" alt="Profile" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
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

  <?php if ($success_msg): ?><div class="alert-banner alert-success-banner"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
  <?php if ($error_msg):   ?><div class="alert-banner alert-error-banner"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

  <!-- HERO BANNER -->
  <div class="journey-hero">
    <div class="journey-hero-inner">
      <div>
        <div class="jh-eyebrow">Scholar Journey</div>
        <div class="jh-title">Welcome back, <em><?= htmlspecialchars($first_name) ?>.</em></div>
        <div class="jh-sub">Track your progress, explore what's next, and stay on course.</div>
        <div style="margin-top:16px;">
          <span class="jh-stage-pill"><i class="fas fa-map-marker-alt" style="margin-right:6px;"></i>
            Stage <?= $active_stage ?> of <?= count($stages) ?> — <?= htmlspecialchars($stages[$active_stage-1]['label']) ?>
          </span>
        </div>
      </div>
      <div class="jh-progress">
        <div class="jh-prog-label">Overall Progress · <strong><?= round(($active_stage - 1) / (count($stages) - 1) * 100) ?>%</strong></div>
        <div class="jh-prog-bar">
          <div class="jh-prog-fill" style="width:<?= round(($active_stage - 1) / (count($stages) - 1) * 100) ?>%;"></div>
        </div>
        <div style="font-size:11px;color:rgba(255,255,255,0.3);margin-top:4px;"><?= $active_stage - 1 ?> of <?= count($stages) ?> milestones reached</div>
      </div>
    </div>
  </div>

  <!-- VISUAL TRACK -->
  <div class="track-card">
    <div class="section-label" style="margin-bottom:20px;">Journey Milestones</div>
    <div class="track-steps">
      <?php foreach ($stages as $i => $s):
        $st = $stage_status($s['id']);
      ?>
      <div class="track-step <?= $st ?>" data-stage="<?= $s['id'] ?>">
        <div class="track-dot <?= $st ?>">
          <?php if ($st === 'done'): ?><i class="fas fa-check"></i>
          <?php elseif ($st === 'active'): ?><i class="fas <?= $s['icon'] ?>"></i>
          <?php else: ?><i class="fas <?= $s['icon'] ?>"></i>
          <?php endif; ?>
        </div>
        <div class="track-label"><?= htmlspecialchars($s['label']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- EXPANDABLE STAGE CARDS -->
  <div class="section-label">Stage Details</div>
  <?php foreach ($stages as $s):
    $st = $stage_status($s['id']);
    $chipClass = $st === 'done' ? 'chip-done' : ($st === 'active' ? 'chip-active' : 'chip-future');
    $chipIcon  = $st === 'done' ? 'fa-check' : ($st === 'active' ? 'fa-spinner' : 'fa-lock');
    $chipLabel = $st === 'done' ? 'Completed' : ($st === 'active' ? 'In Progress' : 'Upcoming');
    $isOpen    = $st === 'active';
  ?>
  <div class="stage-detail-card" id="stageCard<?= $s['id'] ?>">
    <div class="stage-detail-head" onclick="toggleStage(<?= $s['id'] ?>)">
      <div class="stage-detail-icon" style="background:<?= $s['color'] ?>22;color:<?= $s['color'] ?>;"><i class="fas <?= $s['icon'] ?>"></i></div>
      <div>
        <div class="stage-detail-title">Stage <?= $s['id'] ?> — <?= htmlspecialchars($s['label']) ?></div>
        <div class="stage-detail-sub"><?= $st === 'done' ? 'Milestone reached' : ($st === 'active' ? 'Currently active' : 'Awaiting previous stages') ?></div>
      </div>
      <div class="stage-status-chip <?= $chipClass ?>"><i class="fas <?= $chipIcon ?>"></i><?= $chipLabel ?></div>
      <i class="fas fa-chevron-down stage-chevron <?= $isOpen ? 'open' : '' ?>" id="chevron<?= $s['id'] ?>"></i>
    </div>
    <div class="stage-detail-body <?= $isOpen ? 'open' : '' ?>" id="stageBody<?= $s['id'] ?>">
      <div class="stage-desc"><?= htmlspecialchars($s['desc']) ?></div>
      <div class="stage-resources">
        <?php foreach ($s['resources'] as $r): ?>
        <a href="<?= htmlspecialchars($r['url']) ?>" class="stage-resource-link"><i class="fas fa-arrow-right" style="font-size:10px;"></i><?= htmlspecialchars($r['label']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- CHECKLIST + NOTES -->
  <div class="two-col" style="margin-top:24px;">

    <!-- APPLICATION CHECKLIST -->
    <div class="plain-card">
      <div class="card-title-row">
        <div class="card-heading">Application <em>Checklist</em></div>
        <a href="documents.php" class="card-link">My Docs <i class="fas fa-arrow-right" style="font-size:10px;"></i></a>
      </div>
      <?php
      $checks = [
        ['key'=>'cv',             'label'=>'CV / Resume',            'url'=>'application-help.php?doc=cv'],
        ['key'=>'statement',      'label'=>'Personal Statement',     'url'=>'application-help.php?doc=statement'],
        ['key'=>'research',       'label'=>'Research Proposal',      'url'=>'application-help.php?doc=research'],
        ['key'=>'recommendation', 'label'=>'Recommendation Letters', 'url'=>'application-help.php?doc=recommendations'],
        ['key'=>'language',       'label'=>'Language Proficiency',   'url'=>'application-help.php?doc=language'],
      ];
      foreach ($checks as $c): $done = $doc_stats[$c['key']]; ?>
      <div class="checklist-item">
        <div class="ci-dot <?= $done ? 'ci-done' : 'ci-todo' ?>"><i class="fas <?= $done ? 'fa-check' : 'fa-circle' ?>"></i></div>
        <div class="ci-title"><?= htmlspecialchars($c['label']) ?></div>
        <?php if (!$done): ?><a href="<?= $c['url'] ?>" class="ci-action">Start</a><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- JOURNEY NOTES -->
    <div class="plain-card">
      <div class="card-title-row">
        <div class="card-heading">My <em>Notes</em></div>
        <span style="font-size:11px;color:var(--text-muted);">Saved to your profile</span>
      </div>
      <form method="POST" action="my-journey.php">
        <textarea class="notes-textarea" name="journey_notes" placeholder="Use this space to jot down goals, questions for your mentor, deadlines, or anything else related to your journey…"><?= htmlspecialchars($journey_notes ?? '') ?></textarea>
        <button type="submit" name="save_notes" class="btn-save-notes"><i class="fas fa-save"></i> Save Notes</button>
      </form>
    </div>
  </div>

  <!-- QUICK LINKS -->
  <div class="section-label" style="margin-top:8px;">Quick Links</div>
  <div class="quick-links-grid">
    <a href="documents.php" style="text-decoration:none;">
      <div class="quick-link-card"><div class="ql-icon"><i class="fas fa-file-alt"></i></div><div class="ql-title">My Documents</div><div class="ql-desc">Upload & track files</div></div>
    </a>
    <a href="scholarships.php" style="text-decoration:none;">
      <div class="quick-link-card"><div class="ql-icon"><i class="fas fa-graduation-cap"></i></div><div class="ql-title">Find Scholarships</div><div class="ql-desc">Browse opportunities</div></div>
    </a>
    <a href="mentors.php" style="text-decoration:none;">
      <div class="quick-link-card"><div class="ql-icon"><i class="fas fa-users"></i></div><div class="ql-title">My Mentor</div><div class="ql-desc">Connect & get guidance</div></div>
    </a>
    <a href="application-help.php" style="text-decoration:none;">
      <div class="quick-link-card"><div class="ql-icon"><i class="fas fa-pen-nib"></i></div><div class="ql-title">Application Help</div><div class="ql-desc">Materials & templates</div></div>
    </a>
    <a href="resources.php" style="text-decoration:none;">
      <div class="quick-link-card"><div class="ql-icon"><i class="fas fa-book"></i></div><div class="ql-title">Resources</div><div class="ql-desc">Guides & articles</div></div>
    </a>
    <a href="view-agreement.php" style="text-decoration:none;">
      <div class="quick-link-card"><div class="ql-icon"><i class="fas fa-file-contract"></i></div><div class="ql-title">My Agreement</div><div class="ql-desc">View scholarship terms</div></div>
    </a>
  </div>

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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebarOverlay');
const toggle  = document.getElementById('mobileToggle');
function openSidebar(){sidebar.classList.add('active');overlay.classList.add('active');document.body.style.overflow='hidden';}
function closeSidebar(){sidebar.classList.remove('active');overlay.classList.remove('active');document.body.style.overflow='';}
toggle.addEventListener('click',()=>sidebar.classList.contains('active')?closeSidebar():openSidebar());
overlay.addEventListener('click',closeSidebar);
window.addEventListener('resize',()=>{ if(window.innerWidth>768) closeSidebar(); });

function toggleStage(id) {
  const body    = document.getElementById('stageBody'+id);
  const chevron = document.getElementById('chevron'+id);
  const isOpen  = body.classList.contains('open');
  // Close all
  document.querySelectorAll('.stage-detail-body').forEach(b => b.classList.remove('open'));
  document.querySelectorAll('.stage-chevron').forEach(c => c.classList.remove('open'));
  // Open clicked if it was closed
  if (!isOpen) { body.classList.add('open'); chevron.classList.add('open'); }
}

// Track step click → open corresponding stage detail
document.querySelectorAll('.track-step').forEach(step => {
  step.addEventListener('click', () => {
    const id = step.dataset.stage;
    const card = document.getElementById('stageCard'+id);
    if (card) { card.scrollIntoView({behavior:'smooth',block:'center'}); toggleStage(parseInt(id)); }
  });
});
</script>
</body>
</html>