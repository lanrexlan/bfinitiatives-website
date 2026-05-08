<?php
// Start session
session_start();

// If user is already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    switch($_SESSION['user_program']) {
        case 'primary':
            header('Location: dashboard_primary.php');
            break;
        case 'secondary':
            header('Location: dashboard_secondary.php');
            break;
        case 'graduate':
            header('Location: dashboard.php');
            break;
        default:
            header('Location: dashboard.php');
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Scholar Portal | Bold Footprint Initiatives</title>
  <link rel="icon" type="image/png" sizes="32x32" href="/Images/bfi-new-logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <style>
    :root {
      --midnight:#080E1C; --navy:#0D1829; --navy-mid:#132038; --navy-light:#1C2F52;
      --gold:#C8A058; --gold-bright:#E0B96C; --gold-pale:#F0D9A8;
      --cream:#FAF6EF; --white:#FFFFFF;
      --text-primary:#0D1829; --text-secondary:#4A526A; --text-muted:#8A92A8;
      --border-light:#E8E4DA;
      --font-display:'Cormorant Garamond',Georgia,serif;
      --font-body:'Outfit',-apple-system,sans-serif;
      --ease:cubic-bezier(0.25,0.46,0.45,0.94);
      --transition:0.35s var(--ease);
      --shadow-md:0 8px 32px rgba(8,14,28,0.10);
      --shadow-lg:0 20px 60px rgba(8,14,28,0.14);
      --r-sm:8px; --r-md:16px; --r-lg:24px; --r-xl:32px;
    }
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    html{scroll-behavior:smooth;}
    body{font-family:var(--font-body);color:var(--text-primary);background:var(--white);line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden;}
    img{max-width:100%;display:block;}
    a{text-decoration:none;color:inherit;}

    .container{max-width:1240px;margin:0 auto;padding:0 32px;}
    .eyebrow{font-size:11px;font-weight:600;letter-spacing:3px;text-transform:uppercase;color:var(--gold);display:inline-flex;align-items:center;gap:14px;margin-bottom:20px;}
    .eyebrow::before{content:'';width:28px;height:1px;background:var(--gold);flex-shrink:0;}
    .eyebrow-light{color:var(--gold-bright);}.eyebrow-light::before{background:var(--gold-bright);}
    .btn{display:inline-flex;align-items:center;gap:10px;padding:14px 30px;font-family:var(--font-body);font-size:14px;font-weight:500;border-radius:var(--r-sm);border:none;cursor:pointer;transition:var(--transition);}
    .btn-gold{background:var(--gold);color:var(--midnight);}.btn-gold:hover{background:var(--gold-bright);transform:translateY(-2px);}
    .btn-ghost{background:transparent;color:rgba(255,255,255,0.85);border:1px solid rgba(255,255,255,0.2);}.btn-ghost:hover{border-color:var(--gold);color:var(--gold-pale);}
    .btn-outline-gold{background:transparent;color:var(--gold-bright);border:1px solid var(--gold);}.btn-outline-gold:hover{background:var(--gold);color:var(--midnight);}
    .btn-navy{background:var(--navy);color:var(--white);}.btn-navy:hover{background:var(--navy-light);transform:translateY(-2px);}
    .arrow-icon{width:16px;height:16px;transition:transform 0.25s;}.btn:hover .arrow-icon{transform:translateX(3px);}
    .reveal{opacity:0;transform:translateY(24px);transition:opacity 0.7s var(--ease),transform 0.7s var(--ease);}.reveal.visible{opacity:1;transform:translateY(0);}

    /* NAV */
    .nav{position:fixed;top:0;left:0;right:0;z-index:1000;padding:20px 0;transition:var(--transition);}
    .nav.scrolled{background:rgba(8,14,28,0.97);backdrop-filter:blur(20px);padding:13px 0;border-bottom:1px solid rgba(200,160,88,0.08);}
    .nav-inner{display:flex;align-items:center;justify-content:space-between;}
    .nav-logo{display:flex;align-items:center;gap:14px;}
    .nav-logomark{width:38px;height:38px;background:var(--gold);border-radius:8px;display:flex;align-items:center;justify-content:center;}
    .nav-logomark svg{width:22px;height:22px;}
    .nav-logo-text{font-family:var(--font-display);font-size:18px;font-weight:500;color:var(--white);line-height:1.2;}
    .nav-logo-text span{display:block;font-family:var(--font-body);font-size:10px;letter-spacing:2.5px;text-transform:uppercase;color:rgba(200,160,88,0.65);}
    .nav-right{display:flex;align-items:center;gap:20px;}
    .nav-back{font-size:13px;color:rgba(255,255,255,0.55);transition:color var(--transition);display:flex;align-items:center;gap:6px;}
    .nav-back:hover{color:var(--gold-bright);}
    .nav-toggle{display:none;flex-direction:column;gap:5px;background:none;border:none;cursor:pointer;padding:4px;}
    .nav-toggle span{width:22px;height:1.5px;background:rgba(255,255,255,0.8);border-radius:2px;}

    /* HERO */
    .hero{position:relative;min-height:100vh;display:flex;align-items:center;background:var(--midnight);overflow:hidden;}
    .hero-bg{position:absolute;inset:0;background:linear-gradient(148deg,var(--midnight) 0%,var(--navy) 50%,var(--navy-mid) 100%);}
    .hero-grid{position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.04) 1px,transparent 1px);background-size:44px 44px;}
    .hero-glow{position:absolute;top:40%;right:-60px;width:600px;height:600px;background:radial-gradient(ellipse,rgba(200,160,88,0.07) 0%,transparent 65%);}
    .hero::after{content:'';position:absolute;bottom:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(200,160,88,0.2),transparent);}
    .hero-inner{position:relative;z-index:2;padding:140px 0 100px;display:grid;grid-template-columns:1fr 480px;gap:80px;align-items:center;}
    .hero h1{font-family:var(--font-display);font-size:clamp(44px,6vw,72px);font-weight:500;color:var(--white);line-height:1.08;letter-spacing:-0.02em;margin-bottom:20px;opacity:0;animation:fadeUp 0.9s 0.2s forwards;}
    .hero h1 em{font-style:italic;color:var(--gold-bright);}
    .hero-sub{font-size:17px;font-weight:300;color:rgba(255,255,255,0.6);line-height:1.8;max-width:520px;margin-bottom:40px;opacity:0;animation:fadeUp 0.9s 0.4s forwards;}
    .hero-btns{display:flex;gap:14px;flex-wrap:wrap;opacity:0;animation:fadeUp 0.9s 0.55s forwards;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);}}

    /* PORTAL ENTRY CARDS */
    .portal-cards{display:flex;flex-direction:column;gap:14px;opacity:0;animation:fadeUp 1s 0.5s forwards;}
    .portal-card{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.07);border-radius:var(--r-md);padding:20px 24px;display:flex;align-items:center;gap:18px;transition:var(--transition);cursor:pointer;}
    .portal-card:hover{background:rgba(255,255,255,0.08);border-color:rgba(200,160,88,0.2);transform:translateX(4px);}
    .portal-card-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:18px;}
    .icon-primary{background:rgba(239,68,68,0.15);color:#F87171;}
    .icon-secondary{background:rgba(200,160,88,0.15);color:var(--gold-bright);}
    .icon-graduate{background:rgba(52,211,153,0.15);color:#34D399;}
    .icon-register{background:rgba(96,165,250,0.15);color:#60A5FA;}
    .portal-card-info{flex:1;}
    .portal-card-title{font-size:14px;font-weight:600;color:rgba(255,255,255,0.9);margin-bottom:2px;}
    .portal-card-desc{font-size:12px;color:rgba(255,255,255,0.4);}
    .portal-card-arrow{font-size:14px;color:rgba(255,255,255,0.25);transition:var(--transition);}
    .portal-card:hover .portal-card-arrow{color:var(--gold);transform:translateX(3px);}

    /* FEATURES */
    .features{padding:100px 0;background:var(--cream);}
    .features-header{text-align:center;max-width:560px;margin:0 auto 64px;}
    .section-title{font-family:var(--font-display);font-size:clamp(32px,4vw,48px);font-weight:500;color:var(--navy);line-height:1.15;letter-spacing:-0.01em;}
    .section-title em{font-style:italic;color:var(--gold);}
    .features-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;}
    .feature-card{background:var(--white);border-radius:var(--r-lg);padding:36px 28px;border:1px solid var(--border-light);transition:var(--transition);}
    .feature-card:hover{transform:translateY(-5px);box-shadow:var(--shadow-lg);border-color:transparent;}
    .feature-icon{width:52px;height:52px;background:var(--navy);border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;margin-bottom:24px;font-size:20px;color:var(--gold-bright);}
    .feature-card h3{font-family:var(--font-display);font-size:20px;font-weight:500;color:var(--navy);margin-bottom:10px;}
    .feature-card p{font-size:13.5px;font-weight:300;color:var(--text-secondary);line-height:1.72;}

    /* DISTINCTIVE FEATURE: Journey Tracker Preview */
    .journey-section{padding:100px 0;background:var(--white);}
    .journey-grid{display:grid;grid-template-columns:1fr 1fr;gap:80px;align-items:center;}
    .journey-label{font-size:11px;font-weight:600;letter-spacing:3px;text-transform:uppercase;color:var(--gold);display:inline-flex;align-items:center;gap:14px;margin-bottom:20px;}
    .journey-label::before{content:'';width:28px;height:1px;background:var(--gold);}
    .journey-visual{background:var(--navy);border-radius:var(--r-xl);padding:36px;overflow:hidden;position:relative;}
    .journey-visual::before{content:'';position:absolute;top:-60px;right:-60px;width:200px;height:200px;background:radial-gradient(circle,rgba(200,160,88,0.1) 0%,transparent 70%);}
    .jv-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;}
    .jv-title{font-size:13px;font-weight:600;color:rgba(255,255,255,0.5);letter-spacing:1px;text-transform:uppercase;}
    .jv-badge{font-size:11px;color:var(--gold);background:rgba(200,160,88,0.1);border:1px solid rgba(200,160,88,0.2);padding:3px 10px;border-radius:20px;}
    .jv-steps{display:flex;flex-direction:column;gap:0;}
    .jv-step{display:flex;gap:16px;position:relative;}
    .jv-step:not(:last-child) .jv-line{position:absolute;left:15px;top:32px;width:2px;height:calc(100% + 4px);background:rgba(255,255,255,0.07);}
    .jv-step.done .jv-line{background:rgba(200,160,88,0.25);}
    .jv-dot{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:13px;margin-top:4px;z-index:1;}
    .jv-dot.done{background:var(--gold);color:var(--midnight);}
    .jv-dot.active{background:rgba(200,160,88,0.15);border:2px solid var(--gold);color:var(--gold-bright);}
    .jv-dot.future{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);color:rgba(255,255,255,0.25);}
    .jv-content{padding:4px 0 24px;}
    .jv-step-title{font-size:14px;font-weight:500;color:rgba(255,255,255,0.85);margin-bottom:2px;}
    .jv-step-sub{font-size:12px;color:rgba(255,255,255,0.35);}
    .jv-step.active .jv-step-title{color:var(--gold-bright);}
    .jv-step.active .jv-step-sub{color:rgba(200,160,88,0.6);}
    .jv-progress-bar{margin-top:4px;height:3px;background:rgba(255,255,255,0.06);border-radius:2px;overflow:hidden;}
    .jv-progress-fill{height:100%;background:linear-gradient(90deg,var(--gold),var(--gold-bright));border-radius:2px;}

    /* CTA */
    .cta-section{padding:100px 0;background:var(--midnight);text-align:center;position:relative;overflow:hidden;}
    .cta-section::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.025) 1px,transparent 1px);background-size:44px 44px;}
    .cta-section::after{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(200,160,88,0.2),transparent);}
    .cta-inner{position:relative;z-index:2;max-width:520px;margin:0 auto;}
    .cta-inner h2{font-family:var(--font-display);font-size:clamp(32px,4vw,52px);font-weight:500;color:var(--white);line-height:1.15;margin-bottom:16px;}
    .cta-inner h2 em{font-style:italic;color:var(--gold-bright);}
    .cta-inner p{font-size:16px;font-weight:300;color:rgba(255,255,255,0.5);line-height:1.8;margin-bottom:36px;}
    .cta-btns{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;}

    /* FOOTER */
    .footer{background:var(--navy);padding:56px 0 28px;border-top:1px solid rgba(255,255,255,0.05);}
    .footer-inner{display:flex;justify-content:space-between;align-items:flex-start;gap:48px;margin-bottom:40px;flex-wrap:wrap;}
    .footer-brand-logo{display:flex;align-items:center;gap:12px;margin-bottom:14px;}
    .footer-logomark{width:32px;height:32px;background:var(--gold);border-radius:7px;display:flex;align-items:center;justify-content:center;}
    .footer-logomark svg{width:18px;height:18px;}
    .footer-logo-text{font-family:var(--font-display);font-size:15px;color:var(--white);}
    .footer-desc{font-size:13px;font-weight:300;color:rgba(255,255,255,0.35);max-width:240px;line-height:1.75;}
    .footer-links-col h4{font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,0.3);margin-bottom:16px;}
    .footer-links-list{list-style:none;display:flex;flex-direction:column;gap:9px;}
    .footer-links-list a{font-size:13px;font-weight:300;color:rgba(255,255,255,0.5);transition:color var(--transition);}.footer-links-list a:hover{color:var(--gold-bright);}
    .footer-bottom{padding-top:24px;border-top:1px solid rgba(255,255,255,0.05);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;}
    .footer-bottom p{font-size:12px;color:rgba(255,255,255,0.22);}
    .footer-social{display:flex;gap:10px;}
    .footer-social a{width:32px;height:32px;border-radius:7px;background:rgba(255,255,255,0.05);display:flex;align-items:center;justify-content:center;font-size:13px;color:rgba(255,255,255,0.45);transition:var(--transition);}
    .footer-social a:hover{background:var(--gold);color:var(--midnight);}

    @media(max-width:1100px){.hero-inner{grid-template-columns:1fr}.portal-cards{display:none}.journey-grid{grid-template-columns:1fr}}
    @media(max-width:768px){.nav-right{display:none}.nav-toggle{display:flex}.features-grid{grid-template-columns:repeat(2,1fr)}.footer-inner{flex-direction:column;gap:32px}}
    @media(max-width:480px){.container{padding:0 20px}.features-grid{grid-template-columns:1fr}.hero-btns,.cta-btns{flex-direction:column}}
  

    .nav-back,.btn-submit,.password-toggle,.nav-toggle,.btn,a,input,select,textarea,button{min-height:44px}
    a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{outline:3px solid var(--gold-bright);outline-offset:2px}
    .form-control::placeholder{color:rgba(255,255,255,0.55)}
    @media(max-width:768px){
      .nav{padding:16px}
      .nav-logo-text{font-size:15px}
      .form-title{font-size:28px}
      .form-sub{font-size:14px;line-height:1.6}
    }
    @media(max-width:480px){
      .main{padding:10px 10px 28px}
      .panel-right,.right-panel{padding:28px 18px !important}
      .form-row-inline{flex-direction:column;align-items:flex-start;gap:12px}
      .nav > div{gap:10px !important;flex-wrap:wrap}
    }

  </style>
  <link rel="stylesheet" href="/mobile-fixes.css?v=20260507b">
</head>
<body>

<nav class="nav" id="navbar">
  <div class="container"><div class="nav-inner">
    <a href="index.php" class="nav-logo">
      <div class="nav-logomark"><svg viewBox="0 0 22 22" fill="none"><path d="M4 18L11 4L18 18" stroke="#080E1C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 13H15" stroke="#080E1C" stroke-width="1.8" stroke-linecap="round"/></svg></div>
      <div class="nav-logo-text">Bold Footprint<span>Scholar Portal</span></div>
    </a>
    <div class="nav-right">
      <a href="/index.html" class="nav-back"><i class="fas fa-arrow-left" style="font-size:11px;"></i> Main Site</a>
      <a href="login.php" class="btn btn-outline-gold" style="padding:10px 22px;font-size:13px;">Sign In</a>
      <a href="register.php" class="btn btn-gold" style="padding:10px 22px;font-size:13px;">Register</a>
    </div>
    <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation menu" aria-expanded="false"><span></span><span></span><span></span></button>
  </div></div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-bg"></div><div class="hero-grid"></div><div class="hero-glow"></div>
  <div class="container">
    <div class="hero-inner">
      <div>
        <div class="eyebrow eyebrow-light">BFI Scholar Portal</div>
        <h1>Your gateway to<br><em>academic excellence.</em></h1>
        <p class="hero-sub">Access resources, track your scholarship journey, connect with mentors, and manage your academic progress — all in one place built for BFI scholars.</p>
        <div class="hero-btns">
          <a href="login.php" class="btn btn-gold">Sign In to Portal <svg class="arrow-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
          <a href="register.php" class="btn btn-ghost">Create Account</a>
        </div>
      </div>
      <div class="portal-cards">
        <a href="login.php" class="portal-card">
          <div class="portal-card-icon icon-primary"><i class="fas fa-child"></i></div>
          <div class="portal-card-info"><div class="portal-card-title">Primary School Scholars</div><div class="portal-card-desc">For parents & guardians — manage your child's journey</div></div>
          <div class="portal-card-arrow"><i class="fas fa-arrow-right"></i></div>
        </a>
        <a href="login.php" class="portal-card">
          <div class="portal-card-icon icon-secondary"><i class="fas fa-user-graduate"></i></div>
          <div class="portal-card-info"><div class="portal-card-title">Secondary School Scholars</div><div class="portal-card-desc">Access records, submit documents, track requirements</div></div>
          <div class="portal-card-arrow"><i class="fas fa-arrow-right"></i></div>
        </a>
        <a href="login.php" class="portal-card">
          <div class="portal-card-icon icon-graduate"><i class="fas fa-university"></i></div>
          <div class="portal-card-info"><div class="portal-card-title">Graduate Scholars</div><div class="portal-card-desc">Manage research resources & academic milestones</div></div>
          <div class="portal-card-arrow"><i class="fas fa-arrow-right"></i></div>
        </a>
        <a href="register.php" class="portal-card">
          <div class="portal-card-icon icon-register"><i class="fas fa-user-plus"></i></div>
          <div class="portal-card-info"><div class="portal-card-title">Create New Account</div><div class="portal-card-desc">Start your BFI scholarship journey today</div></div>
          <div class="portal-card-arrow"><i class="fas fa-arrow-right"></i></div>
        </a>
      </div>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section class="features reveal">
  <div class="container">
    <div class="features-header">
      <div class="eyebrow" style="justify-content:center;">Portal Features</div>
      <h2 class="section-title" style="text-align:center;">Everything you need,<br><em>in one place.</em></h2>
    </div>
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon"><i class="fas fa-route"></i></div>
        <h3>Journey Tracker</h3>
        <p>Visualise your scholarship milestones from acceptance to placement — know exactly where you stand and what comes next.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="fas fa-file-alt"></i></div>
        <h3>Document Hub</h3>
        <p>Upload, organise, and manage all your academic documents — CVs, transcripts, SOPs — in one secure location.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="fas fa-users"></i></div>
        <h3>Mentor Connect</h3>
        <p>Access your assigned mentor's profile, message thread, and session history. Your guide is always one click away.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="fas fa-graduation-cap"></i></div>
        <h3>Scholarship Finder</h3>
        <p>Browse curated international opportunities — filtered by field, country, and funding level — tailored to your profile.</p>
      </div>
    </div>
  </div>
</section>

<!-- JOURNEY TRACKER PREVIEW — DISTINCTIVE FEATURE -->
<section class="journey-section reveal">
  <div class="container">
    <div class="journey-grid">
      <div>
        <div class="journey-label">Distinctive Feature</div>
        <h2 class="section-title">The <em>BFI Journey</em><br>Tracker.</h2>
        <p style="font-size:16px;font-weight:300;color:var(--text-secondary);line-height:1.8;margin:20px 0 32px;max-width:460px;">Every scholar's path is unique — but the milestones are shared. The Journey Tracker shows you exactly where you are, what you've achieved, and what's coming next. No guesswork, no anxiety — just clarity.</p>
        <a href="login.php" class="btn btn-navy">Access Your Tracker <svg class="arrow-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
      </div>
      <div class="journey-visual">
        <div class="jv-header"><div class="jv-title">My Journey</div><div class="jv-badge">3 of 5 complete</div></div>
        <div class="jv-steps">
          <div class="jv-step done">
            <div class="jv-line"></div>
            <div class="jv-dot done"><i class="fas fa-check" style="font-size:11px;"></i></div>
            <div class="jv-content"><div class="jv-step-title">Scholarship Awarded</div><div class="jv-step-sub">Confirmed · Sept 2024</div></div>
          </div>
          <div class="jv-step done">
            <div class="jv-line"></div>
            <div class="jv-dot done"><i class="fas fa-check" style="font-size:11px;"></i></div>
            <div class="jv-content"><div class="jv-step-title">Documents Submitted</div><div class="jv-step-sub">All 6 documents approved</div></div>
          </div>
          <div class="jv-step done">
            <div class="jv-line"></div>
            <div class="jv-dot done"><i class="fas fa-check" style="font-size:11px;"></i></div>
            <div class="jv-content"><div class="jv-step-title">Mentor Assigned</div><div class="jv-step-sub">Habeeb Adegoke · ASU</div></div>
          </div>
          <div class="jv-step active">
            <div class="jv-line"></div>
            <div class="jv-dot active"><i class="fas fa-pen" style="font-size:11px;"></i></div>
            <div class="jv-content">
              <div class="jv-step-title">Application Preparation</div>
              <div class="jv-step-sub">SOP in progress · 2 of 3 drafts reviewed</div>
              <div class="jv-progress-bar"><div class="jv-progress-fill" style="width:65%;"></div></div>
            </div>
          </div>
          <div class="jv-step">
            <div class="jv-dot future"><i class="fas fa-flag" style="font-size:11px;"></i></div>
            <div class="jv-content"><div class="jv-step-title">University Placement</div><div class="jv-step-sub">Target: Fall 2025</div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-section reveal">
  <div class="container">
    <div class="cta-inner">
      <div class="eyebrow eyebrow-light" style="justify-content:center;">Get Started</div>
      <h2>Ready to manage your<br><em>scholarship journey?</em></h2>
      <p>Sign in to your BFI Scholar Portal or create an account to begin. Your bold footprint starts here.</p>
      <div class="cta-btns">
        <a href="login.php" class="btn btn-gold">Sign In <svg class="arrow-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
        <a href="register.php" class="btn btn-outline-gold">Create Account</a>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="footer">
  <div class="container">
    <div class="footer-inner">
      <div>
        <div class="footer-brand-logo">
          <div class="footer-logomark"><svg viewBox="0 0 22 22" fill="none"><path d="M4 18L11 4L18 18" stroke="#080E1C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 13H15" stroke="#080E1C" stroke-width="1.8" stroke-linecap="round"/></svg></div>
          <span class="footer-logo-text">Bold Footprint Initiatives</span>
        </div>
        <p class="footer-desc">Empowering high-potential Nigerian scholars with scholarships, mentorship, and access to globally competitive academic careers.</p>
      </div>
      <div class="footer-links-col"><h4>Portal</h4><ul class="footer-links-list"><li><a href="login.php">Sign In</a></li><li><a href="register.php">Register</a></li><li><a href="forgot-password.php">Forgot Password</a></li></ul></div>
      <div class="footer-links-col"><h4>Main Site</h4><ul class="footer-links-list"><li><a href="/index.html">Home</a></li><li><a href="/about.html">About Us</a></li><li><a href="/programs.html">Programs</a></li><li><a href="/talent.html">Talent of the Year</a></li></ul></div>
      <div class="footer-links-col"><h4>Contact</h4><ul class="footer-links-list"><li><a href="mailto:info@bfinitiatives.com">info@bfinitiatives.com</a></li><li><a href="tel:+2348165011291">(+234) 816 501 1291</a></li></ul></div>
    </div>
    <div class="footer-bottom">
      <p>&copy; 2026 Bold Footprint Initiatives. All rights reserved.</p>
      <div class="footer-social">
        <a href="#" target="_blank" rel="noopener"><i class="fab fa-linkedin-in"></i></a>
        <a href="https://twitter.com/bfinitiatives" target="_blank" rel="noopener"><i class="fab fa-twitter"></i></a>
        <a href="https://web.facebook.com/profile.php?id=61574771032448" target="_blank" rel="noopener"><i class="fab fa-facebook-f"></i></a>
      </div>
    </div>
  </div>
</footer>

<script>
  const navbar=document.getElementById('navbar');
  window.addEventListener('scroll',()=>navbar.classList.toggle('scrolled',window.scrollY>60),{passive:true});
  document.getElementById('navToggle').addEventListener('click',()=>{});
  document.querySelectorAll('.reveal').forEach(el=>{new IntersectionObserver(([e])=>{if(e.isIntersecting)e.target.classList.add('visible');},{threshold:0.1}).observe(el);});
</script>
</body>
</html>