<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$first_name=''; $last_name=''; $email=''; $profile_picture=null; $notification_count=0;
try {
    $db=new Database(); $conn=$db->getConnection();
    $stmt=$conn->prepare("SELECT first_name, last_name, email, profile_picture FROM users WHERE id = :user_id");
    $stmt->execute([':user_id'=>$_SESSION['user_id']]);
    $user=$stmt->fetch(PDO::FETCH_ASSOC);
    if($user){$first_name=$user['first_name'];$last_name=$user['last_name']??'';$email=$user['email']??'';$profile_picture=$user['profile_picture']??null;}
    $notif_stmt=$conn->prepare("SELECT id FROM notifications WHERE user_id = :user_id AND read_status = 0");
    $notif_stmt->execute([':user_id'=>$_SESSION['user_id']]);
    $notification_count=$notif_stmt->rowCount();
} catch(Exception $e){error_log("Documents error: ".$e->getMessage());}

$host='localhost'; $dbname='bfinitia_resource_library'; $user_db='bfinitia'; $password='Akande_Olanrewaju123@'; $port='5432';

function getFileIcon($filename){
    $ext=strtolower(pathinfo($filename,PATHINFO_EXTENSION));
    $icons=['pdf'=>'fas fa-file-pdf','doc'=>'fas fa-file-word','docx'=>'fas fa-file-word',
            'xls'=>'fas fa-file-excel','xlsx'=>'fas fa-file-excel','xlsm'=>'fas fa-file-excel',
            'ppt'=>'fas fa-file-powerpoint','pptx'=>'fas fa-file-powerpoint',
            'txt'=>'fas fa-file-alt','rtf'=>'fas fa-file-alt'];
    return $icons[$ext]??'fas fa-file';
}
function getIconColor($filename){
    $ext=strtolower(pathinfo($filename,PATHINFO_EXTENSION));
    $colors=['pdf'=>'#EF4444','doc'=>'#2563EB','docx'=>'#2563EB',
             'xls'=>'#16A34A','xlsx'=>'#16A34A','xlsm'=>'#16A34A',
             'ppt'=>'#EA580C','pptx'=>'#EA580C'];
    return $colors[$ext]??'#8A92A8';
}
function formatFileSize($size){
    if(empty($size)) return 'N/A';
    if(is_numeric($size)){if($size>=1048576) return round($size/1048576,2).' MB'; elseif($size>=1024) return round($size/1024,2).' KB'; else return $size.' bytes';}
    return $size;
}

$documents=[];
try {
    $dsn="pgsql:host=$host;port=$port;dbname=$dbname;user=$user_db;password=$password";
    $pdo=new PDO($dsn); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $documents=$pdo->query("SELECT * FROM documents ORDER BY category, title")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e){error_log("Documents DB error: ".$e->getMessage()); $documents=[];}

$db_categories=[
    'gre-prep'           => ['label'=>'GRE Preparation Materials',        'icon'=>'fas fa-pencil-alt'],
    'sop-samples'        => ['label'=>'Statement of Purpose Samples',      'icon'=>'fas fa-pen-nib'],
    'essay-samples'      => ['label'=>'Essay Samples',                     'icon'=>'fas fa-feather'],
    'cv-samples'         => ['label'=>'CV / Resume Samples',               'icon'=>'fas fa-id-card'],
    'recommendation'     => ['label'=>'Recommendation Letter Samples',     'icon'=>'fas fa-envelope-open-text'],
    'application-guides' => ['label'=>'Scholarship Application Guides',    'icon'=>'fas fa-map'],
    'financial-docs'     => ['label'=>'Financial Document Templates',      'icon'=>'fas fa-money-bill'],
];

/*
 * Templates extracted from application-help.php (downloads/ folder).
 * Each entry carries a 'group' key used to build grouped sub-sections.
 * File existence is checked against __DIR__ . '/downloads/' at render time.
 */
$static_templates=[
    /* ── CV & Resume ──────────────────────────────────────────────── */
    ['id'=>'tpl-cv-academic',
     'group'=>'CV & Resume',
     'title'=>'Academic CV Template',
     'filename'=>'cv_template_academic.docx',
     'icon'=>'fas fa-id-card',
     'desc'=>'A clean, field-tested academic CV template formatted for international scholarship applications.'],

    ['id'=>'tpl-cv-research',
     'group'=>'CV & Resume',
     'title'=>'Research-Focused CV',
     'filename'=>'cv_template_research.docx',
     'icon'=>'fas fa-microscope',
     'desc'=>'CV template for research-track applications with dedicated sections for publications and research experience.'],

    ['id'=>'tpl-cv-guide',
     'group'=>'CV & Resume',
     'title'=>'CV Writing Guide & Samples',
     'filename'=>'academic-CV-guide.pdf',
     'icon'=>'fas fa-book-open',
     'desc'=>'Comprehensive guide to writing an academic CV for scholarships, complete with annotated real-world samples.'],

    /* ── Personal Statement ────────────────────────────────────────── */
    ['id'=>'tpl-sop-general',
     'group'=>'Personal Statement',
     'title'=>'Personal Statement – General',
     'filename'=>'personal_statement_general.docx',
     'icon'=>'fas fa-pen-nib',
     'desc'=>'Structured SOP template with section prompts to help you craft a compelling narrative for any scholarship.'],

    ['id'=>'tpl-sop-research',
     'group'=>'Personal Statement',
     'title'=>'Personal Statement – Research Focus',
     'filename'=>'personal_statement_research.docx',
     'icon'=>'fas fa-pen-nib',
     'desc'=>'Personal statement template for research-track scholarships, emphasising academic contributions and research goals.'],

    ['id'=>'tpl-sop-dev',
     'group'=>'Personal Statement',
     'title'=>'Personal Statement – Development Focus',
     'filename'=>'personal_statement_development.docx',
     'icon'=>'fas fa-pen-nib',
     'desc'=>'Tailored for development-focused scholarships (e.g. Commonwealth), emphasising community impact and leadership.'],

    ['id'=>'tpl-sop-professional',
     'group'=>'Personal Statement',
     'title'=>'Personal Statement – Professional Focus',
     'filename'=>'personal_statement_professional.docx',
     'icon'=>'fas fa-pen-nib',
     'desc'=>'Designed for professionally oriented programmes, highlighting industry experience and career trajectory.'],

    ['id'=>'tpl-sop-samples',
     'group'=>'Personal Statement',
     'title'=>'Personal Statement Samples',
     'filename'=>'personal-statement-samples.pdf',
     'icon'=>'fas fa-feather',
     'desc'=>'A curated collection of successful personal statement examples spanning multiple scholarship programmes.'],

    ['id'=>'tpl-sop-checklist',
     'group'=>'Personal Statement',
     'title'=>'Personal Statement Checklist',
     'filename'=>'personal-statement-checklist.pdf',
     'icon'=>'fas fa-list-ol',
     'desc'=>'A step-by-step checklist to review and refine your personal statement before submission.'],

    /* ── Research Proposal ─────────────────────────────────────────── */
    ['id'=>'tpl-research-main',
     'group'=>'Research Proposal',
     'title'=>'Research Proposal Template',
     'filename'=>'research_proposal_template.docx',
     'icon'=>'fas fa-flask',
     'desc'=>'Full research proposal template covering abstract, background, methodology, timeline, and expected outcomes.'],

    ['id'=>'tpl-research-stem',
     'group'=>'Research Proposal',
     'title'=>'Research Proposal – STEM',
     'filename'=>'research_proposal_stem.docx',
     'icon'=>'fas fa-atom',
     'desc'=>'Optimised for STEM disciplines with sections for quantitative methodology, experimental design, and preliminary data.'],

    ['id'=>'tpl-research-soc',
     'group'=>'Research Proposal',
     'title'=>'Research Proposal – Social Sciences',
     'filename'=>'research_proposal_social_science.docx',
     'icon'=>'fas fa-users',
     'desc'=>'Designed for social science research, balancing qualitative and quantitative approaches with thorough ethical considerations.'],

    ['id'=>'tpl-research-hum',
     'group'=>'Research Proposal',
     'title'=>'Research Proposal – Humanities',
     'filename'=>'research_proposal_humanities.docx',
     'icon'=>'fas fa-landmark',
     'desc'=>'Focuses on theoretical frameworks, archival and textual analysis, and situating work within broader scholarly debates.'],

    ['id'=>'tpl-research-inter',
     'group'=>'Research Proposal',
     'title'=>'Research Proposal – Interdisciplinary',
     'filename'=>'research_proposal_interdisciplinary.docx',
     'icon'=>'fas fa-project-diagram',
     'desc'=>'For cross-disciplinary research; includes sections justifying the interdisciplinary approach and integrating diverse methodologies.'],

    ['id'=>'tpl-research-examples',
     'group'=>'Research Proposal',
     'title'=>'Sample Research Proposals',
     'filename'=>'research_proposal_examples.pdf',
     'icon'=>'fas fa-file-pdf',
     'desc'=>'Real-world examples of successful research proposals across disciplines, with committee feedback annotations.'],

    /* ── Recommendation Letters ────────────────────────────────────── */
    ['id'=>'tpl-recom-email',
     'group'=>'Recommendation Letters',
     'title'=>'Recommendation Request Email',
     'filename'=>'recommendation-request-email.docx',
     'icon'=>'fas fa-envelope',
     'desc'=>'A professional email template for requesting recommendation letters from supervisors and faculty members.'],

    ['id'=>'tpl-recom-guide',
     'group'=>'Recommendation Letters',
     'title'=>'Recommender Information Guide',
     'filename'=>'recommender_guide.pdf',
     'icon'=>'fas fa-envelope-open-text',
     'desc'=>'A briefing guide to share with your recommenders, explaining what makes an effective scholarship reference letter.'],

    ['id'=>'tpl-recom-tracker',
     'group'=>'Recommendation Letters',
     'title'=>'Recommendation Tracker',
     'filename'=>'recommendation_tracker.xlsx',
     'icon'=>'fas fa-clipboard-list',
     'desc'=>'Excel tracker to monitor recommendation requests, submission statuses, and upcoming letter deadlines.'],

    /* ── Language Tests ────────────────────────────────────────────── */
    ['id'=>'tpl-ielts',
     'group'=>'Language Tests',
     'title'=>'IELTS Preparation Guide',
     'filename'=>'ielts_prep_guide.pdf',
     'icon'=>'fas fa-language',
     'desc'=>'Comprehensive strategies and practice materials for IELTS Academic preparation, covering all four test sections.'],

    ['id'=>'tpl-lang-writing',
     'group'=>'Language Tests',
     'title'=>'Language Test Writing Templates',
     'filename'=>'language_test_writing_templates.pdf',
     'icon'=>'fas fa-pen-fancy',
     'desc'=>'Essay structure templates for the writing components of IELTS and TOEFL English proficiency tests.'],

    /* ── Checklists & Trackers ─────────────────────────────────────── */
    ['id'=>'tpl-checklist',
     'group'=>'Checklists & Trackers',
     'title'=>'Scholarship Application Checklist',
     'filename'=>'scholarship-checklist.pdf',
     'icon'=>'fas fa-tasks',
     'desc'=>'A comprehensive PDF checklist covering every component of a strong international scholarship application.'],

    ['id'=>'tpl-app-tracker',
     'group'=>'Checklists & Trackers',
     'title'=>'Application & Deadline Tracker',
     'filename'=>'scholarship_application_tracker.xlsx',
     'icon'=>'fas fa-calendar-alt',
     'desc'=>'Excel tracker to monitor multiple scholarship applications, deadlines, requirements, and submission statuses.'],

    ['id'=>'tpl-doc-checklist',
     'group'=>'Checklists & Trackers',
     'title'=>'Document Submission Checklist',
     'filename'=>'document_chekclist.xlsm',
     'icon'=>'fas fa-check-square',
     'desc'=>'Macro-enabled Excel checklist to track each required document across all your active scholarship applications.'],

    ['id'=>'tpl-deadline-cal',
     'group'=>'Checklists & Trackers',
     'title'=>'Scholarship Deadline Calendar',
     'filename'=>'Scholarship_deadline_tracker.xlsm',
     'icon'=>'fas fa-calendar-check',
     'desc'=>'Interactive Excel deadline calendar with automated reminders and colour-coded urgency flags for upcoming submissions.'],
];

/* Build grouped map for rendering */
$template_groups_map=[];
foreach($static_templates as $tpl){ $template_groups_map[$tpl['group']][]=$tpl; }
$static_template_count=count($static_templates);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document Library | BFI Scholar Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    :root{--midnight:#080E1C;--navy:#0D1829;--navy-light:#1C2F52;--gold:#C8A058;--gold-bright:#E0B96C;--cream:#FAF6EF;--cream-dark:#F2EAD8;--white:#FFFFFF;--text-primary:#0D1829;--text-secondary:#4A526A;--text-muted:#8A92A8;--border-light:#E8E4DA;--font-display:'Cormorant Garamond',Georgia,serif;--font-body:'Outfit',-apple-system,sans-serif;--ease:cubic-bezier(0.25,0.46,0.45,0.94);--transition:0.3s var(--ease);--shadow-md:0 8px 32px rgba(8,14,28,0.10);--shadow-lg:0 20px 60px rgba(8,14,28,0.14);--sidebar-width:268px;--header-height:64px;--r-sm:8px;--r-md:14px;--r-lg:20px;--r-xl:28px;}
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:var(--font-body);background:#F2F4F8;color:var(--text-primary);line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden;}
    a{text-decoration:none;color:inherit;}
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
    .sidebar-user-role{font-size:10.5px;color:rgba(255,255,255,0.35);}
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
    .header{position:fixed;top:0;left:var(--sidebar-width);right:0;height:var(--header-height);background:var(--white);border-bottom:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;padding:0 28px;z-index:100;}
    .header-left{display:flex;align-items:center;gap:16px;}
    .mobile-toggle{display:none;background:none;border:none;cursor:pointer;padding:6px;color:var(--text-secondary);font-size:18px;}
    .breadcrumb{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-muted);}
    .breadcrumb a{color:var(--text-muted);transition:color var(--transition);}.breadcrumb a:hover{color:var(--gold);}
    .breadcrumb-sep{font-size:9px;}.breadcrumb-current{color:var(--text-primary);font-weight:500;}
    .header-right{display:flex;align-items:center;gap:14px;}
    .header-icon-btn{width:36px;height:36px;border-radius:var(--r-sm);background:var(--cream);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-secondary);font-size:14px;transition:var(--transition);position:relative;}
    .header-icon-btn:hover{background:var(--cream-dark);color:var(--navy);}
    .notif-dot{position:absolute;top:6px;right:6px;width:7px;height:7px;border-radius:50%;background:#EF4444;border:2px solid var(--white);}
    .header-avatar{width:36px;height:36px;border-radius:50%;background:var(--navy-light);overflow:hidden;border:2px solid var(--border-light);cursor:pointer;display:flex;align-items:center;justify-content:center;}
    .header-avatar img{width:100%;height:100%;object-fit:cover;}
    .header-avatar-init{font-family:var(--font-display);font-size:14px;color:var(--gold-bright);}
    .main{margin-left:var(--sidebar-width);margin-top:var(--header-height);padding:28px;min-height:calc(100vh - var(--header-height));}
    .page-header{background:var(--navy);border-radius:var(--r-xl);padding:28px 32px;margin-bottom:24px;position:relative;overflow:hidden;}
    .page-header::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:32px 32px;}
    .page-header::after{content:'';position:absolute;top:-60px;right:-60px;width:240px;height:240px;background:radial-gradient(circle,rgba(200,160,88,0.1) 0%,transparent 65%);}
    .ph-inner{position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;}
    .ph-eyebrow{font-size:10px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:rgba(200,160,88,0.7);margin-bottom:6px;}
    .ph-title{font-family:var(--font-display);font-size:clamp(22px,3vw,30px);font-weight:500;color:var(--white);margin-bottom:4px;}
    .ph-title em{font-style:italic;color:var(--gold-bright);}
    .ph-sub{font-size:13.5px;font-weight:300;color:rgba(255,255,255,0.5);}
    .btn-back-sm{display:inline-flex;align-items:center;gap:8px;padding:9px 18px;background:rgba(255,255,255,0.07);color:rgba(255,255,255,0.7);font-size:13px;border:1px solid rgba(255,255,255,0.1);border-radius:var(--r-sm);transition:var(--transition);}
    .btn-back-sm:hover{background:rgba(255,255,255,0.12);color:var(--white);}
    .search-bar{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:12px;}
    .search-wrap{flex:1;position:relative;}
    .search-icon-inner{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;}
    .search-input{width:100%;padding:9px 12px 9px 36px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13.5px;color:var(--text-primary);background:var(--cream);transition:border-color var(--transition);}
    .search-input:focus{outline:none;border-color:var(--gold);background:var(--white);box-shadow:0 0 0 3px rgba(200,160,88,0.1);}
    .search-info{font-size:12px;color:var(--text-muted);white-space:nowrap;}
    /* Section labels */
    .section-label{font-size:10px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);margin-bottom:14px;}
    .section-label-sub{display:flex;align-items:center;gap:10px;font-size:10.5px;font-weight:600;letter-spacing:1.8px;text-transform:uppercase;color:var(--navy-light);margin:20px 0 12px;padding-bottom:8px;border-bottom:1px solid var(--border-light);}
    .section-label-sub:first-child{margin-top:0;}
    .section-label-sub i{font-size:12px;color:var(--gold);opacity:0.8;}
    /* Template grid */
    .template-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:4px;}
    .template-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:22px;transition:var(--transition);display:flex;flex-direction:column;}
    .template-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-md);border-color:transparent;}
    .template-card-top{display:flex;align-items:flex-start;gap:14px;margin-bottom:14px;}
    .template-icon{width:44px;height:44px;background:var(--navy);border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:17px;color:var(--gold-bright);flex-shrink:0;}
    .template-title{font-size:14px;font-weight:600;color:var(--navy);line-height:1.35;margin-bottom:2px;}
    .template-badge{display:inline-block;font-size:9.5px;font-weight:700;letter-spacing:1px;text-transform:uppercase;padding:2px 8px;border-radius:10px;background:rgba(200,160,88,0.1);color:var(--gold);}
    .template-badge.badge-coming{background:rgba(138,146,168,0.1);color:var(--text-muted);}
    .template-desc{font-size:13px;font-weight:300;color:var(--text-secondary);line-height:1.65;margin-bottom:16px;flex:1;}
    .template-actions{display:flex;gap:8px;margin-top:auto;}
    .btn-view-sm{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;background:rgba(200,160,88,0.08);color:var(--gold);font-family:var(--font-body);font-size:12px;font-weight:500;border:1px solid rgba(200,160,88,0.25);border-radius:20px;cursor:pointer;transition:var(--transition);}
    .btn-view-sm:hover:not(:disabled){background:var(--gold);color:var(--midnight);}
    .btn-view-sm:disabled{opacity:0.42;cursor:not-allowed;}
    .btn-dl-sm{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;background:var(--navy);color:var(--white);font-family:var(--font-body);font-size:12px;font-weight:500;border:none;border-radius:20px;cursor:pointer;transition:var(--transition);}
    .btn-dl-sm:hover:not(:disabled){background:var(--navy-light);}
    .btn-dl-sm:disabled{opacity:0.42;cursor:not-allowed;}
    /* Category sections */
    .cat-section{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);margin-bottom:16px;overflow:hidden;}
    .cat-header{display:flex;align-items:center;gap:14px;padding:16px 22px;background:var(--cream);border-bottom:1px solid var(--border-light);cursor:pointer;user-select:none;}
    .cat-header:hover{background:var(--cream-dark);}
    .cat-icon{width:36px;height:36px;background:var(--navy);border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:14px;color:var(--gold-bright);flex-shrink:0;}
    .cat-title{font-family:var(--font-display);font-size:18px;font-weight:500;color:var(--navy);flex:1;}
    .cat-title em{font-style:italic;color:var(--gold);}
    .cat-count{font-size:11px;color:var(--text-muted);background:var(--white);border:1px solid var(--border-light);padding:2px 9px;border-radius:20px;margin-right:8px;}
    .cat-chevron{font-size:12px;color:var(--text-muted);transition:transform var(--transition);}
    .cat-section.collapsed .cat-chevron{transform:rotate(-90deg);}
    .doc-list{display:none;}
    .doc-list.open{display:block;}
    .doc-row{display:flex;align-items:center;gap:14px;padding:13px 22px;border-bottom:1px solid var(--border-light);transition:var(--transition);}
    .doc-row:last-child{border-bottom:none;}
    .doc-row:hover{background:rgba(200,160,88,0.025);}
    .doc-row.hidden-row{display:none;}
    .doc-type-icon{width:38px;height:38px;border-radius:var(--r-sm);background:var(--cream);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
    .doc-info{flex:1;min-width:0;}
    .doc-title{font-size:14px;font-weight:500;color:var(--navy);margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .doc-meta{font-size:11.5px;color:var(--text-muted);display:flex;gap:12px;flex-wrap:wrap;}
    .doc-actions{display:flex;gap:8px;flex-shrink:0;}
    .empty-cat{padding:32px 22px;text-align:center;}
    .empty-cat-icon{font-size:28px;color:var(--border-light);margin-bottom:10px;}
    .empty-cat-text{font-size:13px;color:var(--text-muted);}
    /* Document viewer modal */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(8,14,28,0.82);z-index:500;align-items:center;justify-content:center;padding:24px;backdrop-filter:blur(8px);}
    .modal-overlay.active{display:flex;}
    .modal{background:var(--white);border-radius:var(--r-xl);width:100%;max-width:920px;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 40px 80px rgba(8,14,28,0.3);}
    .modal-header{display:flex;align-items:center;gap:14px;padding:16px 24px;border-bottom:1px solid var(--border-light);flex-shrink:0;}
    .modal-header-icon{width:36px;height:36px;background:var(--navy);border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:15px;color:var(--gold-bright);flex-shrink:0;}
    .modal-title{font-family:var(--font-display);font-size:18px;font-weight:500;color:var(--navy);flex:1;}
    .modal-title em{font-style:italic;color:var(--gold);}
    .modal-close{width:32px;height:32px;border-radius:50%;background:var(--cream);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;color:var(--text-secondary);transition:var(--transition);flex-shrink:0;}
    .modal-close:hover{background:var(--cream-dark);color:var(--navy);}
    .modal-body{flex:1;overflow:hidden;min-height:0;}
    .modal-body iframe{width:100%;height:100%;min-height:520px;border:none;display:block;}
    .modal-unavailable{display:none;flex-direction:column;align-items:center;justify-content:center;height:100%;min-height:280px;padding:40px;text-align:center;}
    .modal-unavailable.show{display:flex;}
    .modal-unavailable-icon{font-size:40px;color:var(--border-light);margin-bottom:16px;}
    .modal-unavailable-title{font-family:var(--font-display);font-size:22px;color:var(--navy);margin-bottom:8px;}
    .modal-unavailable-sub{font-size:13.5px;color:var(--text-muted);max-width:340px;line-height:1.7;}
    .modal-footer{padding:14px 24px;border-top:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;flex-wrap:wrap;gap:10px;}
    .modal-footer-info{font-size:12px;color:var(--text-muted);}
    .modal-footer-actions{display:flex;gap:10px;}
    .btn-modal-close{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:var(--cream);color:var(--text-primary);font-family:var(--font-body);font-size:13px;font-weight:500;border:1px solid var(--border-light);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-modal-close:hover{background:var(--cream-dark);}
    .btn-modal-dl{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:var(--navy);color:var(--white);font-family:var(--font-body);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-modal-dl:hover{background:var(--navy-light);}
    /* Footer */
    .portal-footer{margin-left:var(--sidebar-width);background:var(--white);padding:14px 28px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;}
    .footer-copy{font-size:12px;color:var(--text-muted);}
    .footer-links{display:flex;gap:18px;}
    .footer-links a{font-size:12px;color:var(--text-muted);transition:color var(--transition);}.footer-links a:hover{color:var(--gold);}
    .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:150;backdrop-filter:blur(4px);}
    .sidebar-overlay.active{display:block;}
    @media(max-width:900px){.template-grid{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:768px){.sidebar{transform:translateX(-100%)}.sidebar.active{transform:translateX(0)}.header{left:0}.main,.portal-footer{margin-left:0}.mobile-toggle{display:flex}.template-grid{grid-template-columns:1fr}.doc-actions{flex-direction:column}.search-bar{flex-direction:column;align-items:stretch}}
    @media(max-width:480px){.main{padding:16px}}
  </style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-top">
    <div class="sidebar-logo">
      <div class="sidebar-logomark"><svg viewBox="0 0 22 22" fill="none"><path d="M4 18L11 4L18 18" stroke="#080E1C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 13H15" stroke="#080E1C" stroke-width="1.8" stroke-linecap="round"/></svg></div>
      <div class="sidebar-logo-text">Bold Footprint<span>Scholar Portal</span></div>
    </div>
    <div class="sidebar-user">
      <div class="sidebar-avatar"><?php if($profile_picture): ?><img src="<?php echo htmlspecialchars('./uploads/profile_pictures/'.$profile_picture); ?>" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"><div class="sidebar-avatar-init" style="display:none;"><?php echo strtoupper(substr($first_name,0,1)); ?></div><?php else: ?><div class="sidebar-avatar-init"><?php echo strtoupper(substr($first_name,0,1)); ?></div><?php endif; ?></div>
      <div><div class="sidebar-user-name"><?php echo htmlspecialchars($first_name.' '.$last_name); ?></div><div class="sidebar-user-role">BFI Scholar</div></div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>
    <div class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a></div>
    <div class="nav-item"><a href="profile.php" class="nav-link"><i class="fas fa-user"></i> My Profile</a></div>
    <div class="nav-item"><a href="#" class="nav-link"><i class="fas fa-route"></i> My Journey<?php if($notification_count>0): ?><span class="nav-badge"><?php echo $notification_count; ?></span><?php endif; ?></a></div>
    <div class="nav-section-label">Resources</div>
    <div class="nav-item"><a href="documents.php" class="nav-link"><i class="fas fa-file-alt"></i> My Documents</a></div>
    <div class="nav-item"><a href="scholarships.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Scholarships</a></div>
    <div class="nav-item"><a href="mentors.php" class="nav-link"><i class="fas fa-users"></i> My Mentor</a></div>
    <div class="nav-item"><a href="application-help.php" class="nav-link"><i class="fas fa-question-circle"></i> Application Help</a></div>
    <div class="nav-item"><a href="resources.php" class="nav-link active"><i class="fas fa-book"></i> Resources</a></div>
    <div class="nav-section-label">Account</div>
    <div class="nav-item"><a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a></div>
  </nav>
  <div class="sidebar-bottom"><a href="logout.php" class="nav-link" style="color:rgba(239,68,68,0.7);"><i class="fas fa-sign-out-alt"></i> Log Out</a></div>
</aside>

<header class="header">
  <div class="header-left">
    <button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
    <div class="breadcrumb">
      <a href="dashboard.php">Dashboard</a><span class="breadcrumb-sep"><i class="fas fa-chevron-right" style="font-size:9px;"></i></span>
      <a href="resources.php">Resources</a><span class="breadcrumb-sep"><i class="fas fa-chevron-right" style="font-size:9px;"></i></span>
      <span class="breadcrumb-current">Document Library</span>
    </div>
  </div>
  <div class="header-right">
    <button class="header-icon-btn"><i class="fas fa-bell"></i><?php if($notification_count>0): ?><div class="notif-dot"></div><?php endif; ?></button>
    <a href="profile.php"><div class="header-avatar"><?php if($profile_picture): ?><img src="<?php echo htmlspecialchars('./uploads/profile_pictures/'.$profile_picture); ?>" alt=""><div class="header-avatar-init" style="display:none;"><?php echo strtoupper(substr($first_name,0,1)); ?></div><?php else: ?><div class="header-avatar-init"><?php echo strtoupper(substr($first_name,0,1)); ?></div><?php endif; ?></div></a>
  </div>
</header>

<main class="main">
  <div class="page-header">
    <div class="ph-inner">
      <div>
        <div class="ph-eyebrow">Resource Library</div>
        <div class="ph-title">Document <em>Library</em></div>
        <div class="ph-sub">Templates, samples, and guides — all viewable in-browser and downloadable</div>
      </div>
      <a href="resources.php" class="btn-back-sm"><i class="fas fa-arrow-left"></i> Back to Library</a>
    </div>
  </div>

  <!-- SEARCH -->
  <div class="search-bar">
    <div class="search-wrap">
      <i class="fas fa-search search-icon-inner"></i>
      <input type="text" class="search-input" id="docSearch" placeholder="Search documents and templates…" oninput="filterDocs(this.value)">
    </div>
    <div class="search-info" id="searchInfo"><?php echo count($documents) + $static_template_count; ?> resources available</div>
  </div>

  <!-- APPLICATION TEMPLATES -->
  <div class="section-label">Application Templates</div>
  <div id="templateGrid">
    <?php
    $group_icons = [
        'CV & Resume'            => 'fas fa-id-card',
        'Personal Statement'     => 'fas fa-pen-nib',
        'Research Proposal'      => 'fas fa-flask',
        'Recommendation Letters' => 'fas fa-envelope',
        'Language Tests'         => 'fas fa-language',
        'Checklists & Trackers'  => 'fas fa-tasks',
    ];
    foreach($template_groups_map as $group_label => $group_items):
      $g_icon = $group_icons[$group_label] ?? 'fas fa-folder';
    ?>
    <div class="section-label-sub">
      <i class="<?php echo $g_icon; ?>"></i>
      <?php echo htmlspecialchars($group_label); ?>
    </div>
    <div class="template-grid">
      <?php foreach($group_items as $tpl):
        $tpl_web_path = 'downloads/' . $tpl['filename'];
        $tpl_exists   = file_exists(__DIR__ . '/downloads/' . $tpl['filename']);
        $ext          = strtoupper(pathinfo($tpl['filename'], PATHINFO_EXTENSION));
      ?>
      <div class="template-card" data-search="<?php echo strtolower(htmlspecialchars($tpl['title'].' '.$tpl['desc'].' '.$group_label)); ?>">
        <div class="template-card-top">
          <div class="template-icon"><i class="<?php echo $tpl['icon']; ?>"></i></div>
          <div>
            <div class="template-title"><?php echo htmlspecialchars($tpl['title']); ?></div>
            <?php if($tpl_exists): ?>
              <span class="template-badge"><?php echo $ext; ?> Template</span>
            <?php else: ?>
              <span class="template-badge badge-coming">Coming Soon</span>
            <?php endif; ?>
          </div>
        </div>
        <p class="template-desc"><?php echo htmlspecialchars($tpl['desc']); ?></p>
        <div class="template-actions">
          <?php if($tpl_exists): ?>
            <button class="btn-view-sm" onclick="openDoc('<?php echo htmlspecialchars($tpl_web_path); ?>','<?php echo htmlspecialchars(addslashes($tpl['title'])); ?>','<?php echo $ext; ?>')"><i class="fas fa-eye"></i> View</button>
            <a href="<?php echo htmlspecialchars($tpl_web_path); ?>" download="<?php echo htmlspecialchars($tpl['filename']); ?>" class="btn-dl-sm"><i class="fas fa-download"></i> Download</a>
          <?php else: ?>
            <button class="btn-view-sm" disabled><i class="fas fa-clock"></i> Coming Soon</button>
            <button class="btn-dl-sm" disabled><i class="fas fa-download"></i> Download</button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- DB DOCUMENT CATEGORIES -->
  <div class="section-label" style="margin-top:32px;">Document Library</div>

  <?php foreach ($db_categories as $cat_key => $cat):
    $cat_docs  = array_values(array_filter($documents, fn($d) => ($d['category'] ?? '') === $cat_key));
    $cat_count = count($cat_docs);
  ?>
  <div class="cat-section" id="cat-<?php echo $cat_key; ?>">
    <div class="cat-header" onclick="toggleCat('<?php echo $cat_key; ?>')">
      <div class="cat-icon"><i class="<?php echo $cat['icon']; ?>"></i></div>
      <div class="cat-title"><?php echo htmlspecialchars($cat['label']); ?></div>
      <span class="cat-count"><?php echo $cat_count; ?> file<?php echo $cat_count!==1?'s':''; ?></span>
      <i class="fas fa-chevron-down cat-chevron"></i>
    </div>
    <div class="doc-list open" id="doclist-<?php echo $cat_key; ?>">
      <?php if($cat_count > 0): ?>
        <?php foreach($cat_docs as $doc):
          $doc_web_path = '/media/documents/' . ($doc['filename'] ?? '');
          $doc_exists   = !empty($doc['filename']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/media/documents/' . $doc['filename']);
          $ext          = strtoupper(pathinfo($doc['filename'] ?? '', PATHINFO_EXTENSION));
          $icon_color   = getIconColor($doc['filename'] ?? '');
        ?>
        <div class="doc-row" data-search="<?php echo strtolower(htmlspecialchars(($doc['title']??'').' '.($doc['category']??''))); ?>">
          <div class="doc-type-icon"><i class="<?php echo getFileIcon($doc['filename'] ?? ''); ?>" style="color:<?php echo $icon_color; ?>;"></i></div>
          <div class="doc-info">
            <div class="doc-title"><?php echo htmlspecialchars($doc['title'] ?? 'Untitled'); ?></div>
            <div class="doc-meta">
              <span><?php echo $ext; ?></span>
              <span><?php echo formatFileSize($doc['file_size'] ?? null); ?></span>
              <?php if(!empty($doc['upload_date'])): ?><span><?php echo date('M d, Y', strtotime($doc['upload_date'])); ?></span><?php endif; ?>
            </div>
          </div>
          <div class="doc-actions">
            <?php if($doc_exists): ?>
              <button class="btn-view-sm" onclick="openDoc('<?php echo htmlspecialchars($doc_web_path); ?>','<?php echo htmlspecialchars(addslashes($doc['title']??'Document')); ?>','<?php echo $ext; ?>')"><i class="fas fa-eye"></i> View</button>
              <a href="<?php echo htmlspecialchars($doc_web_path); ?>" download="<?php echo htmlspecialchars($doc['filename']??'document'); ?>" class="btn-dl-sm"><i class="fas fa-download"></i> Download</a>
            <?php else: ?>
              <button class="btn-view-sm" onclick="openDocUnavailable('<?php echo htmlspecialchars(addslashes($doc['title']??'Document')); ?>')"><i class="fas fa-eye"></i> View</button>
              <button class="btn-dl-sm" disabled><i class="fas fa-download"></i> Download</button>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
      <div class="empty-cat">
        <div class="empty-cat-icon"><i class="<?php echo $cat['icon']; ?>"></i></div>
        <div class="empty-cat-text">No <?php echo htmlspecialchars($cat['label']); ?> available yet — check back soon.</div>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</main>

<!-- DOCUMENT VIEWER MODAL -->
<div class="modal-overlay" id="docModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-header-icon" id="modalIcon"><i class="fas fa-file-alt"></i></div>
      <div class="modal-title" id="modalTitle">Document <em>Viewer</em></div>
      <button class="modal-close" onclick="closeDoc()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <iframe id="docFrame" src="" title="Document Viewer"></iframe>
      <div class="modal-unavailable" id="modalUnavailable">
        <div class="modal-unavailable-icon"><i class="fas fa-file-slash"></i></div>
        <div class="modal-unavailable-title">Document Unavailable</div>
        <div class="modal-unavailable-sub">This document hasn't been uploaded yet. Your programme coordinator will add it soon. You can still download it once available.</div>
      </div>
    </div>
    <div class="modal-footer">
      <div class="modal-footer-info" id="modalInfo"></div>
      <div class="modal-footer-actions">
        <button class="btn-modal-close" onclick="closeDoc()"><i class="fas fa-times"></i> Close</button>
        <a id="modalDlBtn" href="#" download class="btn-modal-dl"><i class="fas fa-download"></i> Download</a>
      </div>
    </div>
  </div>
</div>

<footer class="portal-footer">
  <div class="footer-copy">&copy; 2026 Bold Footprint Initiatives. All rights reserved.</div>
  <div class="footer-links"><a href="/index.html">Main Site</a><a href="resources.php">Resources</a><a href="video-resources.php">Videos</a></div>
</footer>

<script>
  // Sidebar
  const sidebar=document.getElementById('sidebar'),overlay=document.getElementById('sidebarOverlay'),toggle=document.getElementById('mobileToggle');
  function openS(){sidebar.classList.add('active');overlay.classList.add('active');document.body.style.overflow='hidden';}
  function closeS(){sidebar.classList.remove('active');overlay.classList.remove('active');document.body.style.overflow='';}
  if(toggle) toggle.addEventListener('click',()=>sidebar.classList.contains('active')?closeS():openS());
  overlay.addEventListener('click',closeS);
  window.addEventListener('resize',()=>{if(window.innerWidth>768)closeS();});

  // Category collapse/expand
  function toggleCat(key){
    const section=document.getElementById('cat-'+key);
    const list=document.getElementById('doclist-'+key);
    if(list.classList.contains('open')){list.classList.remove('open');section.classList.add('collapsed');}
    else{list.classList.add('open');section.classList.remove('collapsed');}
  }

  // Document viewer
  function openDoc(src, title, ext) {
    const frame   = document.getElementById('docFrame');
    const unavail = document.getElementById('modalUnavailable');
    const dlBtn   = document.getElementById('modalDlBtn');

    document.getElementById('modalTitle').innerHTML = title + ' <em>— ' + ext + '</em>';
    document.getElementById('modalInfo').textContent = ext + ' Document';
    document.getElementById('modalIcon').innerHTML = '<i class="fas fa-file-alt"></i>';

    frame.style.display = 'block';
    unavail.classList.remove('show');
    dlBtn.style.display = '';

    const lower = src.toLowerCase();
    let viewSrc = src;

    if (lower.endsWith('.docx') || lower.endsWith('.doc') ||
        lower.endsWith('.pptx') || lower.endsWith('.ppt') ||
        lower.endsWith('.xlsx') || lower.endsWith('.xls') ||
        lower.endsWith('.xlsm')) {
      viewSrc = 'https://docs.google.com/gview?url=' +
                encodeURIComponent(window.location.origin + '/' + src) +
                '&embedded=true';
    }

    frame.src      = viewSrc;
    dlBtn.href     = src;
    dlBtn.download = src.split('/').pop();

    document.getElementById('docModal').classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function openDocUnavailable(title) {
    document.getElementById('docFrame').style.display = 'none';
    document.getElementById('docFrame').src = '';
    document.getElementById('modalUnavailable').classList.add('show');
    document.getElementById('modalTitle').innerHTML = title + ' <em>— Unavailable</em>';
    document.getElementById('modalInfo').textContent = 'Not yet uploaded';
    document.getElementById('modalDlBtn').style.display = 'none';
    document.getElementById('docModal').classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeDoc() {
    const frame = document.getElementById('docFrame');
    frame.src = '';
    frame.style.display = 'block';
    document.getElementById('modalUnavailable').classList.remove('show');
    document.getElementById('modalDlBtn').style.display = '';
    document.getElementById('docModal').classList.remove('active');
    document.body.style.overflow = '';
  }

  document.getElementById('docModal').addEventListener('click', function(e){ if(e.target===this) closeDoc(); });

  // Search / filter
  const TOTAL = <?php echo count($documents) + $static_template_count; ?>;
  function filterDocs(q) {
    q = q.toLowerCase().trim();
    let visible = 0;

    document.querySelectorAll('#templateGrid .template-card').forEach(card => {
      const match = !q || card.dataset.search.includes(q);
      card.style.display = match ? '' : 'none';
      if (match) visible++;
    });

    // Show/hide sub-labels and grids when all cards in a group are hidden
    document.querySelectorAll('#templateGrid .template-grid').forEach(grid => {
      const anyVisible = [...grid.querySelectorAll('.template-card')].some(c => c.style.display !== 'none');
      grid.style.display = anyVisible ? '' : 'none';
      const label = grid.previousElementSibling;
      if (label && label.classList.contains('section-label-sub')) {
        label.style.display = anyVisible ? '' : 'none';
      }
    });

    document.querySelectorAll('.doc-row').forEach(row => {
      const match = !q || row.dataset.search.includes(q);
      row.classList.toggle('hidden-row', !match);
      if (match) visible++;
    });

    if (q) {
      document.querySelectorAll('.doc-list').forEach(l => l.classList.add('open'));
      document.querySelectorAll('.cat-section').forEach(s => s.classList.remove('collapsed'));
    } else {
      // Restore hidden sub-labels and grids on clear
      document.querySelectorAll('#templateGrid .template-grid').forEach(grid => {
        grid.style.display = '';
        const label = grid.previousElementSibling;
        if (label && label.classList.contains('section-label-sub')) label.style.display = '';
      });
    }

    document.getElementById('searchInfo').textContent = q
      ? (visible + ' result' + (visible !== 1 ? 's' : ''))
      : TOTAL + ' resources available';
  }
</script>
</body>
</html>