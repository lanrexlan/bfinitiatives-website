<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$first_name = $last_name = '';
$profile_picture = null;
$notification_count = 0;
$mentors = [];
$user    = [];
$error_msg = $success_msg = '';
$active_filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search_q      = isset($_GET['q'])      ? trim($_GET['q'])  : '';

/* ── Email function: sends directly, no DB ── */
function sendMentorDirectEmail($user, $mentor, $message) {
    $uname  = htmlspecialchars($user['first_name'].' '.$user['last_name']);
    $uemail = filter_var($user['email'], FILTER_VALIDATE_EMAIL);
    $mname  = htmlspecialchars($mentor['first_name'].' '.$mentor['last_name']);
    $memail = filter_var($mentor['email'], FILTER_VALIDATE_EMAIL);
    if (!$uemail || !$memail) return false;

    $subject = "Mentorship Request from {$uname} — BFI Scholar Portal";
    $body = "<!DOCTYPE html><html><body style='font-family:sans-serif;background:#F2F4F8;margin:0;padding:20px;'>
    <div style='max-width:560px;margin:auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);'>
      <div style='background:#0D1829;padding:28px;text-align:center;'>
        <p style='color:rgba(200,160,88,0.8);font-size:11px;letter-spacing:2px;text-transform:uppercase;margin:0 0 8px;'>Bold Footprint Initiatives · Scholar Portal</p>
        <h2 style='color:#E0B96C;margin:0;font-family:Georgia,serif;font-size:22px;font-weight:400;font-style:italic;'>New Mentorship Request</h2>
      </div>
      <div style='padding:28px;'>
        <p style='color:#4A526A;font-size:14px;'>Dear <strong style='color:#0D1829;'>{$mname}</strong>,</p>
        <p style='color:#4A526A;font-size:14px;'>A BFI scholar has reached out to you directly through the Scholar Portal.</p>
        <div style='background:#FAF6EF;border-radius:10px;padding:16px 20px;margin:20px 0;border:1px solid #E8E4DA;'>
          <p style='margin:0 0 6px;font-size:12px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:#C8A058;'>Scholar Details</p>
          <p style='margin:0 0 4px;font-size:14px;color:#0D1829;'><strong>Name:</strong> {$uname}</p>
          <p style='margin:0;font-size:14px;color:#0D1829;'><strong>Email:</strong> <a href='mailto:{$uemail}' style='color:#C8A058;'>{$uemail}</a></p>
        </div>
        <div style='background:#FAF6EF;border-left:3px solid #C8A058;padding:16px 20px;border-radius:0 10px 10px 0;margin:20px 0;'>
          <p style='font-weight:600;color:#C8A058;margin:0 0 10px;font-size:12px;letter-spacing:1px;text-transform:uppercase;'>Their Message</p>
          <p style='margin:0;color:#4A526A;font-size:14px;line-height:1.7;'>".nl2br(htmlspecialchars($message))."</p>
        </div>
        <p style='color:#4A526A;font-size:13.5px;'>To respond, simply reply to this email — it will go directly to <a href='mailto:{$uemail}' style='color:#C8A058;'>{$uname}</a>.</p>
      </div>
      <div style='background:#F2F4F8;padding:16px;text-align:center;font-size:12px;color:#8A92A8;border-top:1px solid #E8E4DA;'>
        Bold Footprint Initiatives · Supporting Academic Excellence Since 2016
      </div>
    </div></body></html>";

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: BFI Scholar Portal <noreply@brightfutureinitiatives.org>\r\n";
    $headers .= "Reply-To: {$uname} <{$uemail}>\r\n";

    $result = mail($memail, $subject, $body, $headers);
    if ($result) error_log("Mentorship email sent to: {$memail}");
    return $result;
}

try {
    $db   = new Database();
    $conn = $db->getConnection();

    /* ── User ── */
    $us = $conn->prepare("SELECT first_name, last_name, email, profile_picture FROM users WHERE id = :uid");
    $us->execute([':uid' => $_SESSION['user_id']]);
    $user = $us->fetch(PDO::FETCH_ASSOC);
    if ($user) { $first_name=$user['first_name']; $last_name=$user['last_name']??''; $profile_picture=$user['profile_picture']??null; }

    /* ── Notifications ── */
    try {
        $ns = $conn->prepare("SELECT id FROM notifications WHERE user_id = :uid AND read_status = 0");
        $ns->execute([':uid' => $_SESSION['user_id']]);
        $notification_count = $ns->rowCount();
    } catch (Exception $e) {}

    /* ── Ensure mentors table + seed data ── */
    $ck = $conn->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name='mentors')");
    if (!$ck->fetchColumn()) {
        $conn->exec("CREATE TABLE IF NOT EXISTS mentors (
            id SERIAL PRIMARY KEY, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL,
            title VARCHAR(200), expertise TEXT, bio TEXT, achievements TEXT,
            profile_picture VARCHAR(255), email VARCHAR(100), linkedin VARCHAR(255),
            researchgate VARCHAR(255), featured BOOLEAN DEFAULT FALSE,
            mentee_count INT DEFAULT 0, rating DECIMAL(3,1) DEFAULT 4.5,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $conn->exec("INSERT INTO mentors
            (first_name,last_name,title,expertise,bio,email,linkedin,researchgate,profile_picture,featured) VALUES
            ('Habeeb','Adegoke','PhD Researcher, Arizona State University',
             'Analytical Research, Strategic Planning, Scholarship Applications',
             'A PhD researcher at Arizona State University who established BFI in 2016. Habeeb brings his analytical mindset and passion for sustainable solutions to guide BFI scholarship programs.',
             'habeebadesola1@gmail.com','https://linkedin.com/in/adesola-habeeb-adegoke','https://www.researchgate.net/profile/Adesola-Adegoke','habeeb.jpeg',TRUE),
            ('Ridwan','Taiwo','PhD, Hong Kong Polytechnic University',
             'Data Analysis, Program Development, Research Methodology',
             'With a PhD from Hong Kong Polytechnic University, Dr. Ridwan combines data-driven approaches with humanitarian values to strengthen BFI''s impact assessment and program development.',
             'manbau10@gmail.com','https://linkedin.com/in/taiwo-ridwan','https://www.researchgate.net/profile/Ridwan-Taiwo','ridwan.jpeg',TRUE),
            ('Kelvin','Ojasanya','PhD Candidate, University of Vermont',
             'International Education, Cross-Cultural Studies, Application Strategy',
             'A PhD candidate at the University of Vermont who helped shape BFI''s international perspective across three continents.',
             'k.kelv68@gmail.com','https://www.linkedin.com/in/kehindeojasanya/','https://www.researchgate.net/profile/Kehinde-Ojasanya-2','kelvin.jpeg',FALSE),
            ('Yusuf','Kehinde','Electrical and Electronic Engineering & Blockchain Technology',
             'Technical Writing, Engineering Applications, Technology Integration',
             'Utilizing his background in Electrical and Electronic Engineering and Blockchain technology, Yusuf coordinates BFI activities with technical expertise.',
             'yusufkehinde8@gmail.com','https://www.linkedin.com/in/kehinde-yusuf','','yusuf.jpeg',FALSE),
            ('Olanrewaju','Akande','Agricultural Engineering and Data Science',
             'Mentorship Coordination, Data Analysis, Student Support',
             'Leveraging his background in Agricultural Engineering and data science, Olanrewaju manages BFI''s mentorship programs, matching scholars with the right mentors.',
             'lanreylan@gmail.com','https://www.linkedin.com/in/olanrewaju-akande-903693b9/','','lanre.jpeg',FALSE),
            ('Miracle','Adegun','Materials Engineer, Hong Kong University of Science and Technology',
             'STEM Fields, Material Science, Sustainable Development',
             'Dr. Adegun is a distinguished Materials Engineer and Data Analyst. As an EIT and HKPFS Scholar, he mentors BFI students in STEM fields.',
             'adegunmiracle@gmail.com','https://www.linkedin.com/in/miracle-hope-adegun','https://www.researchgate.net/profile/Miracle-Adegun','miracle.jpeg',TRUE),
            ('Johnson','Adetooto','PhD Student, Purdue University',
             'Building Information Modeling, AI Applications, Technology Innovation',
             'A PhD student at Purdue University with expertise in Building Information Modeling and AI. His experience navigating competitive scholarships makes him an invaluable resource.',
             'adetootojohnsondamilola@gmail.com','https://linkedin.com/in/johnson-adetooto','https://www.researchgate.net/profile/Johnson-Adetooto','Johnson_image.jpg',FALSE),
            ('Peace','Adara','Materials Engineer, KU Leuven',
             'Product Development, Data Analysis, Engineering Applications',
             'A Materials Engineer specialising in product development and data analysis. A GES Scholar and Commonwealth Shared Scholar from University College London.',
             'adex.peace@gmail.com','https://linkedin.com/in/adara-peace','https://www.researchgate.net/profile/Peace-Adara','peace.jpeg',FALSE),
            ('Hafsat','Alabere','PhD in Biomedical Sciences, West Virginia University',
             'Healthcare, Biological Sciences, Research Methodology',
             'Currently pursuing her PhD in Biomedical Sciences at West Virginia University. A Commonwealth Shared Scholar and EducationUSA OFP Scholar mentoring students in healthcare fields.',
             'hafsatalabere@gmail.com','https://linkedin.com/in/hafsat-alabere','https://www.researchgate.net/profile/Hafsat-Alabere-2','Hafsoh_image.jpg',FALSE)");
    }

    /* ── Fetch mentors ── */
    $mq = "SELECT * FROM mentors WHERE 1=1";
    $mp = [];
    if ($active_filter === 'featured') { $mq .= " AND featured = TRUE"; }
    if (!empty($search_q)) {
        $mq .= " AND (first_name ILIKE :q OR last_name ILIKE :q OR expertise ILIKE :q OR title ILIKE :q)";
        $mp[':q'] = '%'.$search_q.'%';
    }
    $mq .= " ORDER BY featured DESC, last_name ASC";
    $ms = $conn->prepare($mq);
    $ms->execute($mp);
    $mentors = $ms->fetchAll(PDO::FETCH_ASSOC);

    /* ── Handle direct email submission ── */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
        $mentor_id = filter_input(INPUT_POST, 'mentor_id', FILTER_VALIDATE_INT);
        $message   = trim($_POST['message'] ?? '');

        if (!$mentor_id || $mentor_id <= 0) {
            $error_msg = "Invalid mentor selection.";
        } elseif (strlen($message) < 10) {
            $error_msg = "Please write a more detailed message (at least 10 characters).";
        } elseif (strlen($message) > 2000) {
            $error_msg = "Message is too long. Please keep it under 2000 characters.";
        } else {
            $mdata = $conn->prepare("SELECT * FROM mentors WHERE id = :mid");
            $mdata->execute([':mid' => $mentor_id]);
            $mentor_row = $mdata->fetch(PDO::FETCH_ASSOC);

            if (!$mentor_row) {
                $error_msg = "Mentor not found. Please refresh and try again.";
            } elseif (empty($mentor_row['email']) || !filter_var($mentor_row['email'], FILTER_VALIDATE_EMAIL)) {
                $error_msg = "This mentor's email is not available. Please contact BFI directly.";
            } else {
                $sent = sendMentorDirectEmail($user, $mentor_row, $message);
                $mfull = htmlspecialchars($mentor_row['first_name'].' '.$mentor_row['last_name']);
                if ($sent) {
                    $success_msg = "Your message was sent directly to <strong>{$mfull}</strong>! They will reply to your email address.";
                } else {
                    // Provide mailto fallback when server mail fails
                    $mailto_subject = urlencode("Mentorship Request — BFI Scholar Portal");
                    $mailto_body    = urlencode("Hi {$mentor_row['first_name']},\n\n{$message}\n\nBest regards,\n{$user['first_name']} {$user['last_name']}");
                    $mailto_link    = "mailto:".htmlspecialchars($mentor_row['email'])."?subject={$mailto_subject}&body={$mailto_body}";
                    $success_msg = "Server mail unavailable — <a href='{$mailto_link}' style='color:inherit;font-weight:600;text-decoration:underline;'>click here to email {$mentor_row['first_name']} directly</a> from your email client.";
                }
            }
        }
    }

} catch (Exception $e) {
    error_log("Mentors error: ".$e->getMessage());
    $error_msg = "An error occurred. Please try again later.";
}

$featured_mentors = array_filter($mentors, fn($m) => $m['featured']);
$regular_mentors  = array_filter($mentors, fn($m) => !$m['featured']);

/* ── Collect all expertise tags for client-side filtering ── */
$all_expertise_tags = [];
foreach ($mentors as $m) {
    foreach (explode(',', $m['expertise'] ?? '') as $tag) {
        $t = trim($tag);
        if ($t && !in_array($t, $all_expertise_tags)) $all_expertise_tags[] = $t;
    }
}
sort($all_expertise_tags);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
  <title>My Mentor | BFI Scholar Portal</title>
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
    .sidebar-user-name{font-size:13.5px;font-weight:500;color:var(--white);}
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
    .alert-banner{border-radius:var(--r-md);padding:14px 18px;margin-bottom:20px;font-size:13.5px;display:flex;align-items:flex-start;gap:10px;}
    .alert-success-banner{background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);color:#065F46;}
    .alert-error-banner{background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);color:#991B1B;}
    .alert-banner i{margin-top:2px;flex-shrink:0;}

    /* MENTOR BANNER */
    .mentor-banner{background:var(--navy);border-radius:var(--r-xl);padding:28px 32px;margin-bottom:24px;position:relative;overflow:hidden;}
    .mentor-banner::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:28px 28px;}
    .mentor-banner::after{content:'';position:absolute;top:-40px;right:-40px;width:220px;height:220px;background:radial-gradient(circle,rgba(200,160,88,0.12) 0%,transparent 65%);}
    .mentor-banner-inner{position:relative;z-index:1;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;}
    .mb-eyebrow{font-size:10px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:rgba(200,160,88,0.7);margin-bottom:6px;}
    .mb-title{font-family:var(--font-display);font-size:clamp(20px,2.5vw,28px);font-weight:500;color:var(--white);line-height:1.15;margin-bottom:4px;}
    .mb-title em{font-style:italic;color:var(--gold-bright);}
    .mb-sub{font-size:13px;font-weight:300;color:rgba(255,255,255,0.5);max-width:380px;}
    .mb-stats{display:flex;gap:24px;flex-wrap:wrap;}
    .mb-stat{text-align:center;}
    .mb-stat-val{font-family:var(--font-display);font-size:28px;font-weight:500;color:var(--gold-bright);}
    .mb-stat-lbl{font-size:10px;color:rgba(255,255,255,0.4);letter-spacing:0.5px;}

    /* HOW IT WORKS */
    .how-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:24px;margin-bottom:24px;}
    .how-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-top:16px;}
    .how-step{text-align:center;padding:16px 12px;border-radius:var(--r-md);transition:var(--transition);}
    .how-step:hover{background:var(--cream);}
    .how-step-icon{width:52px;height:52px;background:var(--cream);border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;font-size:18px;color:var(--navy);margin:0 auto 12px;transition:var(--transition);}
    .how-step:hover .how-step-icon{background:var(--navy);color:var(--gold-bright);}
    .how-step-num{font-size:10px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:var(--gold);margin-bottom:4px;}
    .how-step-title{font-size:14px;font-weight:600;color:var(--navy);margin-bottom:4px;}
    .how-step-desc{font-size:12px;color:var(--text-muted);line-height:1.5;}

    /* FILTER ROW */
    .mentor-filter-row{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:16px 20px;margin-bottom:12px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;}
    .mentor-search-wrap{flex:1;min-width:200px;position:relative;}
    .mentor-search-wrap input{width:100%;padding:9px 14px 9px 36px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13px;color:var(--text-primary);background:var(--cream);transition:var(--transition);}
    .mentor-search-wrap input:focus{outline:none;border-color:var(--gold);background:var(--white);}
    .mentor-search-wrap i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:12px;pointer-events:none;}
    .filter-chips{display:flex;gap:8px;flex-wrap:wrap;}
    .filter-chip{padding:7px 14px;border-radius:20px;font-size:12px;font-weight:500;border:1px solid var(--border-light);background:var(--white);color:var(--text-secondary);cursor:pointer;transition:var(--transition);text-decoration:none;display:inline-block;}
    .filter-chip:hover,.filter-chip.active{background:var(--navy);color:var(--gold-bright);border-color:var(--navy);}
    .search-submit{padding:9px 18px;background:var(--navy);color:var(--white);border:none;border-radius:var(--r-sm);font-family:var(--font-body);font-size:13px;font-weight:500;cursor:pointer;transition:var(--transition);}
    .search-submit:hover{background:var(--navy-light);}

    /* EXPERTISE TAGS (client-side filter) */
    .expertise-filter{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:14px 20px;margin-bottom:20px;}
    .expertise-filter-label{font-size:10px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);margin-bottom:10px;}
    .expertise-tags{display:flex;gap:6px;flex-wrap:wrap;}
    .exp-tag{font-size:11px;padding:4px 10px;border-radius:10px;background:var(--cream);color:var(--text-secondary);border:1px solid var(--border-light);cursor:pointer;transition:var(--transition);}
    .exp-tag:hover,.exp-tag.active{background:var(--navy);color:var(--gold-bright);border-color:var(--navy);}
    .exp-tag-clear{font-size:11px;padding:4px 10px;border-radius:10px;background:transparent;color:var(--text-muted);border:1px dashed var(--border-light);cursor:pointer;transition:var(--transition);display:none;}
    .exp-tag-clear.visible{display:inline-block;}
    .exp-tag-clear:hover{color:var(--navy);}

    /* SECTION LABEL */
    .section-label{font-size:10px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);margin-bottom:14px;}

    /* FEATURED CARDS */
    .featured-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px;margin-bottom:28px;}
    .mentor-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);overflow:hidden;transition:var(--transition);display:flex;flex-direction:column;}
    .mentor-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-md);border-color:transparent;}
    .mentor-card.hidden{display:none;}
    .mentor-card-banner{height:100px;background:var(--navy);position:relative;overflow:hidden;}
    .mentor-card-banner::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.04) 1px,transparent 1px);background-size:20px 20px;}
    .mentor-card-banner::after{content:'';position:absolute;top:-30px;right:-30px;width:120px;height:120px;background:radial-gradient(circle,rgba(200,160,88,0.15) 0%,transparent 65%);}
    .featured-badge{position:absolute;top:12px;left:12px;background:var(--gold);color:var(--midnight);font-size:9.5px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;padding:3px 9px;border-radius:10px;z-index:1;}
    .mentor-avatar-wrap{position:absolute;bottom:-28px;left:50%;transform:translateX(-50%);z-index:2;}
    .mentor-avatar{width:56px;height:56px;border-radius:50%;border:3px solid var(--white);background:var(--navy-light);overflow:hidden;display:flex;align-items:center;justify-content:center;box-shadow:var(--shadow-sm);}
    .mentor-avatar img{width:100%;height:100%;object-fit:cover;}
    .mentor-avatar-init{font-family:var(--font-display);font-size:20px;font-weight:500;color:var(--gold-bright);}
    .mentor-card-body{padding:42px 20px 18px;flex:1;display:flex;flex-direction:column;}
    .mentor-name{font-family:var(--font-display);font-size:19px;font-weight:500;color:var(--navy);text-align:center;margin-bottom:2px;}
    .mentor-title{font-size:11.5px;color:var(--text-muted);text-align:center;margin-bottom:14px;line-height:1.4;}
    .mentor-bio{font-size:12.5px;color:var(--text-secondary);line-height:1.6;margin-bottom:14px;flex:1;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;}
    .mentor-tags{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;}
    .mentor-tag{font-size:10.5px;padding:3px 9px;border-radius:10px;background:var(--cream);color:var(--text-secondary);border:1px solid var(--border-light);cursor:pointer;transition:var(--transition);}
    .mentor-tag:hover{background:var(--navy);color:var(--gold-bright);border-color:var(--navy);}
    .mentor-meta-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-top:1px solid var(--border-light);border-bottom:1px solid var(--border-light);margin-bottom:14px;}
    .mentor-meta-item{display:flex;align-items:center;gap:5px;font-size:12px;color:var(--text-muted);}
    .mentor-meta-item i{color:var(--gold);font-size:11px;}
    .mentor-social{display:flex;gap:8px;margin-bottom:14px;}
    .mentor-social-link{width:30px;height:30px;border-radius:var(--r-sm);background:var(--cream);display:flex;align-items:center;justify-content:center;font-size:12px;color:var(--text-muted);transition:var(--transition);border:1px solid var(--border-light);}
    .mentor-social-link:hover{background:var(--navy);color:var(--gold-bright);border-color:var(--navy);}
    .btn-connect{display:flex;align-items:center;justify-content:center;gap:8px;padding:11px;background:var(--navy);color:var(--white);border:none;border-radius:var(--r-sm);font-family:var(--font-body);font-size:13px;font-weight:500;cursor:pointer;transition:var(--transition);width:100%;}
    .btn-connect:hover{background:var(--navy-light);}

    /* REGULAR CARDS */
    .regular-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;margin-bottom:24px;}
    .mentor-card-sm{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:20px;transition:var(--transition);display:flex;flex-direction:column;gap:12px;}
    .mentor-card-sm:hover{transform:translateY(-3px);box-shadow:var(--shadow-md);border-color:transparent;}
    .mentor-card-sm.hidden{display:none;}
    .mentor-sm-top{display:flex;align-items:center;gap:12px;}
    .mentor-sm-avatar{width:46px;height:46px;border-radius:50%;background:var(--navy-light);overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:2px solid var(--border-light);}
    .mentor-sm-avatar img{width:100%;height:100%;object-fit:cover;}
    .mentor-sm-avatar-init{font-family:var(--font-display);font-size:16px;font-weight:500;color:var(--gold-bright);}
    .mentor-sm-name{font-size:14px;font-weight:600;color:var(--navy);line-height:1.3;}
    .mentor-sm-title{font-size:11px;color:var(--text-muted);line-height:1.4;}
    .mentor-sm-tags{display:flex;gap:5px;flex-wrap:wrap;}
    .mentor-sm-tag{font-size:10px;padding:2px 8px;border-radius:8px;background:var(--cream);color:var(--text-secondary);border:1px solid var(--border-light);cursor:pointer;transition:var(--transition);}
    .mentor-sm-tag:hover{background:var(--navy);color:var(--gold-bright);border-color:var(--navy);}
    .mentor-card-sm-footer{display:flex;gap:8px;margin-top:auto;}
    .btn-connect-sm{flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:9px;background:var(--cream);color:var(--navy);border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:12px;font-weight:500;cursor:pointer;transition:var(--transition);}
    .btn-connect-sm:hover{background:var(--navy);color:var(--gold-bright);border-color:var(--navy);}
    .btn-mailto-sm{width:36px;height:36px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:var(--cream);color:var(--gold);border:1px solid var(--border-light);border-radius:var(--r-sm);font-size:13px;transition:var(--transition);cursor:pointer;}
    .btn-mailto-sm:hover{background:var(--gold);color:var(--white);border-color:var(--gold);}

    /* EMPTY / NO RESULTS */
    .no-results-msg{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:40px 24px;text-align:center;display:none;}
    .no-results-msg.visible{display:block;}

    /* MODAL */
    .modal-content{border:none;border-radius:var(--r-xl);overflow:hidden;}
    .modal-header{background:var(--navy);padding:20px 24px;border:none;}
    .modal-title{font-family:var(--font-display);font-size:20px;font-weight:500;color:var(--white);}
    .modal-title em{font-style:italic;color:var(--gold-bright);}
    .btn-close-white{filter:invert(1);}
    .modal-body{padding:24px;background:var(--cream);}
    .modal-footer{background:var(--white);border-top:1px solid var(--border-light);padding:16px 24px;}
    .modal-mentor-info{background:var(--white);border-radius:var(--r-md);padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:14px;}
    .modal-mentor-avatar{width:52px;height:52px;border-radius:50%;background:var(--navy-light);overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:2px solid var(--border-light);}
    .modal-mentor-avatar img{width:100%;height:100%;object-fit:cover;}
    .modal-mentor-avatar-init{font-family:var(--font-display);font-size:18px;font-weight:500;color:var(--gold-bright);}
    .modal-mentor-name{font-size:15px;font-weight:600;color:var(--navy);}
    .modal-mentor-title{font-size:12px;color:var(--text-muted);}
    .modal-section{background:var(--white);border-radius:var(--r-md);padding:16px 20px;margin-bottom:14px;}
    .modal-section-label{font-size:10px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px;}
    .modal-section-text{font-size:13px;color:var(--text-secondary);line-height:1.6;}
    .modal-contact-item{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-secondary);margin-bottom:8px;}
    .modal-contact-item:last-child{margin-bottom:0;}
    .modal-contact-item i{width:16px;color:var(--gold);font-size:12px;flex-shrink:0;}
    .modal-contact-item a{color:var(--gold);}
    .modal-contact-item a:hover{color:var(--gold-bright);}
    .form-group{margin-bottom:16px;}
    .form-group label{font-size:12px;font-weight:600;letter-spacing:0.5px;color:var(--text-secondary);margin-bottom:6px;display:block;}
    .form-control{width:100%;padding:10px 14px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13px;color:var(--text-primary);background:var(--white);transition:var(--transition);resize:vertical;}
    .form-control:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,160,88,0.1);}
    .char-count{font-size:11px;color:var(--text-muted);text-align:right;margin-top:4px;}
    .char-count.warn{color:#F59E0B;}
    .char-count.danger{color:#EF4444;}
    .message-templates{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px;}
    .msg-template{font-size:11px;padding:4px 10px;border-radius:10px;background:var(--cream);color:var(--text-secondary);border:1px solid var(--border-light);cursor:pointer;transition:var(--transition);}
    .msg-template:hover{background:var(--navy);color:var(--gold-bright);border-color:var(--navy);}
    .mailto-fallback{display:flex;align-items:center;gap:8px;padding:10px 14px;background:var(--cream);border-radius:var(--r-sm);border:1px dashed var(--border-light);margin-top:10px;font-size:12px;color:var(--text-muted);}
    .mailto-fallback a{color:var(--gold);font-weight:500;}
    .mailto-fallback a:hover{color:var(--gold-bright);}
    .btn-modal-primary{padding:10px 22px;background:var(--gold);color:var(--midnight);font-family:var(--font-body);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-modal-primary:hover{background:var(--gold-bright);}
    .btn-modal-secondary{padding:10px 18px;background:var(--cream);color:var(--text-secondary);font-family:var(--font-body);font-size:13px;border:1px solid var(--border-light);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-modal-secondary:hover{background:var(--cream-dark);}

    /* SIDEBAR OVERLAY */
    .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:150;backdrop-filter:blur(4px);}
    .sidebar-overlay.active{display:block;}

    /* FOOTER */
    .portal-footer{margin-left:var(--sidebar-width);background:var(--white);padding:16px 28px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;transition:margin-left var(--transition);}
    .footer-copy{font-size:12px;color:var(--text-muted);}
    .footer-links{display:flex;gap:20px;}
    .footer-links a{font-size:12px;color:var(--text-muted);transition:color var(--transition);}
    .footer-links a:hover{color:var(--gold);}

    @media(max-width:1100px){.featured-grid{grid-template-columns:1fr 1fr;}.how-steps{grid-template-columns:1fr 1fr 1fr;}}
    @media(max-width:768px){
      .sidebar{transform:translateX(-100%);}.sidebar.active{transform:translateX(0);}
      .header{left:0;}.main,.portal-footer{margin-left:0;}
      .mobile-toggle{display:flex;}
      .how-steps{grid-template-columns:1fr;}.featured-grid{grid-template-columns:1fr;}.regular-grid{grid-template-columns:1fr;}
      .mentor-filter-row{flex-direction:column;align-items:stretch;}
    }
    @media(max-width:480px){.mentor-banner-inner{flex-direction:column;}.mb-stats{justify-content:center;}}
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
    <div class="nav-item"><a href="documents.php" class="nav-link"><i class="fas fa-file-alt"></i> My Documents</a></div>
    <div class="nav-item"><a href="scholarships.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Scholarships</a></div>
    <div class="nav-item"><a href="mentors.php" class="nav-link active"><i class="fas fa-users"></i> My Mentor</a></div>
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
    <div class="header-page-title">My <em>Mentor</em></div>
  </div>
  <div class="header-right">
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

  <?php if ($success_msg): ?>
    <div class="alert-banner alert-success-banner">
      <i class="fas fa-check-circle"></i>
      <div><?= $success_msg /* may contain safe HTML link */ ?></div>
    </div>
  <?php endif; ?>
  <?php if ($error_msg): ?>
    <div class="alert-banner alert-error-banner">
      <i class="fas fa-exclamation-circle"></i>
      <div><?= htmlspecialchars($error_msg) ?></div>
    </div>
  <?php endif; ?>

  <!-- BANNER -->
  <div class="mentor-banner">
    <div class="mentor-banner-inner">
      <div>
        <div class="mb-eyebrow">Mentorship Network</div>
        <div class="mb-title">Connect with <em>Expert Mentors</em></div>
        <div class="mb-sub">Get guidance from scholars who've navigated the same journey — reach out directly by email.</div>
      </div>
      <div class="mb-stats">
        <div class="mb-stat">
          <div class="mb-stat-val"><?= count($mentors) ?></div>
          <div class="mb-stat-lbl">Mentors Available</div>
        </div>
        <div class="mb-stat">
          <div class="mb-stat-val"><?= count($featured_mentors) ?></div>
          <div class="mb-stat-lbl">Featured</div>
        </div>
        <div class="mb-stat">
          <div class="mb-stat-val">9+</div>
          <div class="mb-stat-lbl">Countries Represented</div>
        </div>
      </div>
    </div>
  </div>

  <!-- HOW IT WORKS -->
  <div class="how-card">
    <div class="section-label">How It Works</div>
    <div class="how-steps">
      <div class="how-step">
        <div class="how-step-icon"><i class="fas fa-hand-point-up"></i></div>
        <div class="how-step-num">Step 1</div>
        <div class="how-step-title">Choose a Mentor</div>
        <div class="how-step-desc">Browse profiles and find someone whose expertise matches your goals.</div>
      </div>
      <div class="how-step">
        <div class="how-step-icon"><i class="fas fa-paper-plane"></i></div>
        <div class="how-step-num">Step 2</div>
        <div class="how-step-title">Send a Direct Email</div>
        <div class="how-step-desc">Write a personalised message — it goes straight to the mentor's inbox. No middleman.</div>
      </div>
      <div class="how-step">
        <div class="how-step-icon"><i class="fas fa-graduation-cap"></i></div>
        <div class="how-step-num">Step 3</div>
        <div class="how-step-title">Grow Together</div>
        <div class="how-step-desc">The mentor replies directly to your email and you take it from there.</div>
      </div>
    </div>
  </div>

  <!-- SEARCH + SERVER FILTER -->
  <form method="GET" id="filterForm">
    <div class="mentor-filter-row">
      <div class="mentor-search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" name="q" placeholder="Search by name, expertise, or institution…"
               value="<?= htmlspecialchars($search_q) ?>">
      </div>
      <div class="filter-chips">
        <a href="?filter=all" class="filter-chip <?= $active_filter==='all'    ?'active':'' ?>">All</a>
        <a href="?filter=featured" class="filter-chip <?= $active_filter==='featured'?'active':'' ?>">
          <i class="fas fa-star" style="font-size:10px;margin-right:3px;"></i>Featured
        </a>
      </div>
      <button type="submit" class="search-submit"><i class="fas fa-search"></i></button>
    </div>
  </form>

  <!-- CLIENT-SIDE EXPERTISE FILTER -->
  <?php if (!empty($all_expertise_tags)): ?>
  <div class="expertise-filter">
    <div class="expertise-filter-label">Filter by Expertise</div>
    <div class="expertise-tags">
      <?php foreach ($all_expertise_tags as $tag): ?>
        <span class="exp-tag" data-tag="<?= htmlspecialchars($tag) ?>"><?= htmlspecialchars($tag) ?></span>
      <?php endforeach; ?>
      <span class="exp-tag-clear" id="clearExpFilter"><i class="fas fa-times" style="margin-right:4px;"></i>Clear</span>
    </div>
  </div>
  <?php endif; ?>

  <!-- FEATURED MENTORS -->
  <?php if (!empty($featured_mentors) && $active_filter !== 'featured'): ?>
    <div class="section-label">Featured Mentors</div>
    <div class="featured-grid" id="featuredGrid">
      <?php foreach ($featured_mentors as $m):
        $tags = array_slice(array_map('trim', explode(',', $m['expertise'] ?? '')), 0, 3);
        $all_tags_str = implode(',', array_map('trim', explode(',', $m['expertise'] ?? '')));
      ?>
        <div class="mentor-card" data-expertise="<?= htmlspecialchars($all_tags_str) ?>">
          <div class="mentor-card-banner">
            <span class="featured-badge"><i class="fas fa-star" style="margin-right:3px;"></i>Featured</span>
            <div class="mentor-avatar-wrap">
              <div class="mentor-avatar">
                <?php if (!empty($m['profile_picture'])): ?>
                  <img src="/Images/<?= htmlspecialchars($m['profile_picture']) ?>"
                       alt="<?= htmlspecialchars($m['first_name']) ?>"
                       onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                  <div class="mentor-avatar-init" style="display:none;"><?= strtoupper(substr($m['first_name'],0,1)) ?></div>
                <?php else: ?>
                  <div class="mentor-avatar-init"><?= strtoupper(substr($m['first_name'],0,1)) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="mentor-card-body">
            <div class="mentor-name"><?= htmlspecialchars($m['first_name'].' '.$m['last_name']) ?></div>
            <div class="mentor-title"><?= htmlspecialchars($m['title'] ?? '') ?></div>
            <div class="mentor-bio"><?= htmlspecialchars($m['bio'] ?? '') ?></div>
            <div class="mentor-tags">
              <?php foreach ($tags as $tag): ?>
                <span class="mentor-tag tag-clickable" data-tag="<?= htmlspecialchars(trim($tag)) ?>"><?= htmlspecialchars(trim($tag)) ?></span>
              <?php endforeach; ?>
            </div>
            <div class="mentor-meta-row">
              <div class="mentor-meta-item"><i class="fas fa-user-graduate"></i><?= $m['mentee_count'] ?? 0 ?> mentees</div>
              <div style="display:flex;align-items:center;gap:3px;">
                <?php for($i=0;$i<5;$i++): ?>
                  <i class="fas fa-star" style="font-size:11px;color:<?= $i < round($m['rating'] ?? 4.5) ? '#F59E0B' : 'var(--border-light)' ?>;"></i>
                <?php endfor; ?>
                <span style="font-size:12px;color:var(--text-muted);margin-left:4px;"><?= number_format($m['rating'] ?? 4.5, 1) ?></span>
              </div>
            </div>
            <?php if (!empty($m['linkedin']) || !empty($m['researchgate']) || !empty($m['email'])): ?>
              <div class="mentor-social">
                <?php if (!empty($m['linkedin'])): ?>
                  <a href="<?= htmlspecialchars($m['linkedin']) ?>" target="_blank" class="mentor-social-link" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                <?php endif; ?>
                <?php if (!empty($m['researchgate'])): ?>
                  <a href="<?= htmlspecialchars($m['researchgate']) ?>" target="_blank" class="mentor-social-link" title="ResearchGate"><i class="fab fa-researchgate"></i></a>
                <?php endif; ?>
                <?php if (!empty($m['email'])): ?>
                  <a href="mailto:<?= htmlspecialchars($m['email']) ?>" class="mentor-social-link" title="Email directly"><i class="fas fa-envelope"></i></a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <button class="btn-connect" data-bs-toggle="modal" data-bs-target="#connectModal<?= $m['id'] ?>">
              <i class="fas fa-paper-plane"></i> Email <?= htmlspecialchars($m['first_name']) ?>
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- ALL / REGULAR MENTORS -->
  <div class="section-label" id="regularLabel">
    <?= $active_filter === 'featured' ? 'Featured Mentors' : 'All Mentors' ?>
  </div>
  <div class="regular-grid" id="regularGrid">
    <?php foreach ($mentors as $m):
      $show = $active_filter === 'featured' || !$m['featured'];
      if (!$show) continue;                         // featured already shown above
      if ($active_filter === 'all' && $m['featured']) continue;
      $tags = array_slice(array_map('trim', explode(',', $m['expertise'] ?? '')), 0, 2);
      $all_tags_str = implode(',', array_map('trim', explode(',', $m['expertise'] ?? '')));
    ?>
      <div class="mentor-card-sm" data-expertise="<?= htmlspecialchars($all_tags_str) ?>">
        <div class="mentor-sm-top">
          <div class="mentor-sm-avatar">
            <?php if (!empty($m['profile_picture'])): ?>
              <img src="/Images/<?= htmlspecialchars($m['profile_picture']) ?>"
                   alt="<?= htmlspecialchars($m['first_name']) ?>"
                   onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
              <div class="mentor-sm-avatar-init" style="display:none;"><?= strtoupper(substr($m['first_name'],0,1)) ?></div>
            <?php else: ?>
              <div class="mentor-sm-avatar-init"><?= strtoupper(substr($m['first_name'],0,1)) ?></div>
            <?php endif; ?>
          </div>
          <div>
            <div class="mentor-sm-name"><?= htmlspecialchars($m['first_name'].' '.$m['last_name']) ?></div>
            <div class="mentor-sm-title"><?= htmlspecialchars(mb_substr($m['title'] ?? '', 0, 60)).(mb_strlen($m['title'] ?? '') > 60 ? '…' : '') ?></div>
          </div>
        </div>
        <div class="mentor-sm-tags">
          <?php foreach ($tags as $tag): ?>
            <span class="mentor-sm-tag tag-clickable" data-tag="<?= htmlspecialchars(trim($tag)) ?>"><?= htmlspecialchars(trim($tag)) ?></span>
          <?php endforeach; ?>
        </div>
        <div class="mentor-card-sm-footer">
          <button class="btn-connect-sm" data-bs-toggle="modal" data-bs-target="#connectModal<?= $m['id'] ?>">
            <i class="fas fa-paper-plane"></i> Connect
          </button>
          <?php if (!empty($m['email'])): ?>
            <a href="mailto:<?= htmlspecialchars($m['email']) ?>" class="btn-mailto-sm" title="Email <?= htmlspecialchars($m['first_name']) ?> directly">
              <i class="fas fa-envelope"></i>
            </a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="no-results-msg" id="noResultsMsg">
    <i class="fas fa-search" style="font-size:32px;color:var(--border-light);display:block;margin-bottom:12px;"></i>
    <div style="font-family:var(--font-display);font-size:20px;color:var(--navy);margin-bottom:6px;">No mentors match that expertise</div>
    <div style="font-size:13px;color:var(--text-muted);">Try a different tag or <span style="color:var(--gold);cursor:pointer;" id="clearFromMsg">clear the filter</span>.</div>
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

<!-- CONNECT MODALS (one per mentor) -->
<?php foreach ($mentors as $m):
  $all_tags = array_filter(array_map('trim', explode(',', $m['expertise'] ?? '')));
  $mailto_subject = urlencode("Mentorship Request — BFI Scholar Portal");
  $mailto_pre     = urlencode("Hi {$m['first_name']},\n\n[Write your message here]\n\nBest regards,\n{$first_name} {$last_name}");
?>
<div class="modal fade" id="connectModal<?= $m['id'] ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Email <em><?= htmlspecialchars($m['first_name']) ?></em></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <!-- Mentor snapshot -->
        <div class="modal-mentor-info">
          <div class="modal-mentor-avatar">
            <?php if (!empty($m['profile_picture'])): ?>
              <img src="/Images/<?= htmlspecialchars($m['profile_picture']) ?>" alt=""
                   onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
              <div class="modal-mentor-avatar-init" style="display:none;"><?= strtoupper(substr($m['first_name'],0,1)) ?></div>
            <?php else: ?>
              <div class="modal-mentor-avatar-init"><?= strtoupper(substr($m['first_name'],0,1)) ?></div>
            <?php endif; ?>
          </div>
          <div>
            <div class="modal-mentor-name"><?= htmlspecialchars($m['first_name'].' '.$m['last_name']) ?></div>
            <div class="modal-mentor-title"><?= htmlspecialchars($m['title'] ?? '') ?></div>
          </div>
        </div>

        <!-- About -->
        <div class="modal-section">
          <div class="modal-section-label">About</div>
          <div class="modal-section-text"><?= htmlspecialchars($m['bio'] ?? 'No bio available.') ?></div>
        </div>

        <!-- Expertise -->
        <?php if (!empty($all_tags)): ?>
        <div class="modal-section">
          <div class="modal-section-label">Expertise</div>
          <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;">
            <?php foreach ($all_tags as $tag): ?>
              <span style="font-size:10.5px;padding:3px 9px;border-radius:10px;background:var(--cream);color:var(--text-secondary);border:1px solid var(--border-light);"><?= htmlspecialchars($tag) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Contact (visible links) -->
        <?php if (!empty($m['email']) || !empty($m['linkedin'])): ?>
        <div class="modal-section">
          <div class="modal-section-label">Contact</div>
          <?php if (!empty($m['email'])): ?>
            <div class="modal-contact-item">
              <i class="fas fa-envelope"></i>
              <a href="mailto:<?= htmlspecialchars($m['email']) ?>"><?= htmlspecialchars($m['email']) ?></a>
            </div>
          <?php endif; ?>
          <?php if (!empty($m['linkedin'])): ?>
            <div class="modal-contact-item">
              <i class="fab fa-linkedin"></i>
              <a href="<?= htmlspecialchars($m['linkedin']) ?>" target="_blank">LinkedIn Profile</a>
            </div>
          <?php endif; ?>
          <?php if (!empty($m['researchgate'])): ?>
            <div class="modal-contact-item">
              <i class="fab fa-researchgate"></i>
              <a href="<?= htmlspecialchars($m['researchgate']) ?>" target="_blank">ResearchGate Profile</a>
            </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Message form -->
        <form method="POST" action="mentors.php" id="emailForm<?= $m['id'] ?>">
          <input type="hidden" name="mentor_id" value="<?= $m['id'] ?>">
          <div class="form-group">
            <label>Your Message <span style="color:#EF4444;">*</span></label>
            <div style="font-size:11px;color:var(--text-muted);margin-bottom:8px;">Quick starters:</div>
            <div class="message-templates">
              <span class="msg-template" data-mid="<?= $m['id'] ?>"
                    data-text="Hi <?= htmlspecialchars($m['first_name']) ?>, I'm working on my personal statement and would really value your guidance based on your experience.">Personal Statement</span>
              <span class="msg-template" data-mid="<?= $m['id'] ?>"
                    data-text="Hi <?= htmlspecialchars($m['first_name']) ?>, I'm preparing a research proposal for scholarship applications and would love your advice on how to strengthen it.">Research Proposal</span>
              <span class="msg-template" data-mid="<?= $m['id'] ?>"
                    data-text="Hi <?= htmlspecialchars($m['first_name']) ?>, I'd love to learn from your academic journey and get advice on navigating scholarship applications as someone from a similar background.">General Guidance</span>
            </div>
            <textarea class="form-control"
                      name="message"
                      id="msgArea<?= $m['id'] ?>"
                      rows="5"
                      placeholder="Introduce yourself and explain what specific guidance you're looking for… (min. 10 characters)"
                      required
                      maxlength="2000"></textarea>
            <div class="char-count" id="charCount<?= $m['id'] ?>">0 / 2000</div>
          </div>

          <!-- Mailto fallback always visible -->
          <?php if (!empty($m['email'])): ?>
          <div class="mailto-fallback">
            <i class="fas fa-info-circle" style="color:var(--gold);flex-shrink:0;"></i>
            <span>Prefer your own email client?
              <a href="mailto:<?= htmlspecialchars($m['email']) ?>?subject=<?= $mailto_subject ?>&body=<?= $mailto_pre ?>"
                 id="mailtoLink<?= $m['id'] ?>">Open in your email app instead</a>
            </span>
          </div>
          <?php endif; ?>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-modal-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit"
                form="emailForm<?= $m['id'] ?>"
                name="send_email"
                class="btn-modal-primary"
                id="sendBtn<?= $m['id'] ?>">
          <i class="fas fa-paper-plane" style="margin-right:6px;"></i>Send Email
        </button>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── Sidebar ── */
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebarOverlay');
const toggle  = document.getElementById('mobileToggle');
function openSidebar(){sidebar.classList.add('active');overlay.classList.add('active');document.body.style.overflow='hidden';}
function closeSidebar(){sidebar.classList.remove('active');overlay.classList.remove('active');document.body.style.overflow='';}
toggle.addEventListener('click', () => sidebar.classList.contains('active') ? closeSidebar() : openSidebar());
overlay.addEventListener('click', closeSidebar);
window.addEventListener('resize', () => { if (window.innerWidth > 768) closeSidebar(); });

/* ── Character counters ── */
document.querySelectorAll('textarea[name="message"]').forEach(ta => {
  const mid = ta.id.replace('msgArea','');
  const cc  = document.getElementById('charCount'+mid);
  ta.addEventListener('input', () => {
    const len = ta.value.length;
    cc.textContent = len + ' / 2000';
    cc.className = 'char-count' + (len > 1800 ? ' danger' : len > 1400 ? ' warn' : '');
    /* Update the mailto fallback link body in real time */
    const ml = document.getElementById('mailtoLink'+mid);
    if (ml) {
      const subj = encodeURIComponent('Mentorship Request — BFI Scholar Portal');
      const body = encodeURIComponent(ta.value);
      const base = ml.href.split('?')[0];
      ml.href = base + '?subject=' + subj + '&body=' + body;
    }
  });
});

/* ── Message templates ── */
document.querySelectorAll('.msg-template').forEach(btn => {
  btn.addEventListener('click', () => {
    const mid = btn.dataset.mid;
    const ta  = document.getElementById('msgArea'+mid);
    ta.value  = btn.dataset.text;
    ta.dispatchEvent(new Event('input'));
    ta.focus();
  });
});

/* ── Submit feedback: disable button while posting ── */
document.querySelectorAll('[id^="emailForm"]').forEach(form => {
  form.addEventListener('submit', () => {
    const mid = form.id.replace('emailForm','');
    const btn = document.getElementById('sendBtn'+mid);
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-circle-notch fa-spin" style="margin-right:6px;"></i>Sending…'; }
  });
});

/* ── Client-side expertise filter ── */
let activeExpTag = null;

function applyExpFilter(tag) {
  const allCards = document.querySelectorAll('.mentor-card, .mentor-card-sm');
  let anyVisible = false;
  allCards.forEach(card => {
    const expertise = (card.dataset.expertise || '').toLowerCase();
    const match = !tag || expertise.split(',').some(t => t.trim() === tag.toLowerCase());
    card.classList.toggle('hidden', !match);
    if (match) anyVisible = true;
  });
  document.getElementById('noResultsMsg').classList.toggle('visible', !anyVisible);
}

document.querySelectorAll('.exp-tag').forEach(tag => {
  tag.addEventListener('click', () => {
    const val = tag.dataset.tag;
    if (activeExpTag === val) {
      activeExpTag = null;
      tag.classList.remove('active');
      document.getElementById('clearExpFilter').classList.remove('visible');
      applyExpFilter(null);
    } else {
      document.querySelectorAll('.exp-tag').forEach(t => t.classList.remove('active'));
      tag.classList.add('active');
      activeExpTag = val;
      document.getElementById('clearExpFilter').classList.add('visible');
      applyExpFilter(val);
    }
  });
});

function clearExpFilter() {
  activeExpTag = null;
  document.querySelectorAll('.exp-tag').forEach(t => t.classList.remove('active'));
  document.getElementById('clearExpFilter').classList.remove('visible');
  applyExpFilter(null);
}
document.getElementById('clearExpFilter').addEventListener('click', clearExpFilter);
const clearFromMsg = document.getElementById('clearFromMsg');
if (clearFromMsg) clearFromMsg.addEventListener('click', clearExpFilter);

/* ── Clicking a tag on a card filters by that expertise ── */
document.querySelectorAll('.tag-clickable').forEach(tag => {
  tag.addEventListener('click', (e) => {
    e.stopPropagation();
    const val = tag.dataset.tag;
    document.querySelectorAll('.exp-tag').forEach(t => {
      if (t.dataset.tag === val) {
        t.classList.add('active');
        t.scrollIntoView({ behavior:'smooth', block:'nearest' });
      } else {
        t.classList.remove('active');
      }
    });
    activeExpTag = val;
    document.getElementById('clearExpFilter').classList.add('visible');
    applyExpFilter(val);
  });
});
</script>
</body>
</html>