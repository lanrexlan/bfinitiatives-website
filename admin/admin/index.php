<?php
session_start();
if (isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
    header('Location: admin-dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Portal | Bold Footprint Initiatives</title>
  <link rel="icon" type="image/png" sizes="32x32" href="/Images/BFI_Logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <style>
    :root {
      --midnight:#080E1C;--navy:#0D1829;--navy-mid:#132038;--navy-light:#1C2F52;
      --gold:#C8A058;--gold-bright:#E0B96C;--gold-pale:#F0D9A8;
      --cream:#FAF6EF;--white:#FFFFFF;
      --text-primary:#0D1829;--text-secondary:#4A526A;--text-muted:#8A92A8;
      --border-light:#E8E4DA;
      --admin-crimson:#9F1239;--admin-crimson-light:#BE123C;--admin-crimson-pale:rgba(159,18,57,0.12);
      --success:#059669;--warning:#D97706;--danger:#DC2626;
      --font-display:'Cormorant Garamond',Georgia,serif;
      --font-body:'Outfit',-apple-system,sans-serif;
      --ease:cubic-bezier(0.25,0.46,0.45,0.94);
      --transition:0.35s var(--ease);
      --shadow-md:0 8px 32px rgba(8,14,28,0.10);
      --shadow-lg:0 20px 60px rgba(8,14,28,0.16);
      --r-sm:8px;--r-md:16px;--r-lg:24px;--r-xl:32px;
    }
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    html{scroll-behavior:smooth;}
    body{font-family:var(--font-body);background:var(--midnight);color:var(--white);line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden;}
    a{text-decoration:none;color:inherit;}
    img{max-width:100%;display:block;}
    .container{max-width:1200px;margin:0 auto;padding:0 32px;}

    /* ── NAV ── */
    .nav{position:fixed;top:0;left:0;right:0;z-index:1000;padding:20px 0;transition:var(--transition);}
    .nav.scrolled{background:rgba(8,14,28,0.97);backdrop-filter:blur(20px);padding:13px 0;border-bottom:1px solid rgba(200,160,88,0.08);}
    .nav-inner{display:flex;align-items:center;justify-content:space-between;}
    .nav-logo{display:flex;align-items:center;gap:12px;}
    .nav-logomark{width:36px;height:36px;background:var(--gold);border-radius:7px;display:flex;align-items:center;justify-content:center;}
    .nav-logomark svg{width:20px;height:20px;}
    .nav-logo-text{font-family:var(--font-display);font-size:17px;font-weight:500;color:var(--white);line-height:1.2;}
    .nav-logo-text span{display:block;font-family:var(--font-body);font-size:9px;letter-spacing:2.5px;text-transform:uppercase;color:rgba(200,160,88,0.6);}
    .admin-pill{display:inline-flex;align-items:center;gap:5px;background:var(--admin-crimson);color:white;font-size:9px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:4px 10px;border-radius:20px;}
    .nav-links{display:flex;align-items:center;gap:20px;}
    .nav-text-link{font-size:12.5px;color:rgba(255,255,255,0.45);transition:color var(--transition);}
    .nav-text-link:hover{color:var(--gold-bright);}
    .btn{display:inline-flex;align-items:center;gap:8px;padding:11px 24px;font-family:var(--font-body);font-size:13px;font-weight:500;border-radius:var(--r-sm);border:none;cursor:pointer;transition:var(--transition);}
    .btn-gold{background:var(--gold);color:var(--midnight);}.btn-gold:hover{background:var(--gold-bright);transform:translateY(-2px);}
    .btn-ghost{background:transparent;color:rgba(255,255,255,0.75);border:1px solid rgba(255,255,255,0.18);}.btn-ghost:hover{border-color:var(--gold);color:var(--gold-pale);}
    .btn-crimson{background:var(--admin-crimson);color:white;}.btn-crimson:hover{background:var(--admin-crimson-light);transform:translateY(-2px);}
    .btn-outline-gold{background:transparent;color:var(--gold-bright);border:1px solid var(--gold);}.btn-outline-gold:hover{background:var(--gold);color:var(--midnight);}
    .arrow-icon{width:15px;height:15px;transition:transform 0.25s;}.btn:hover .arrow-icon{transform:translateX(3px);}

    /* ── HERO ── */
    .hero{position:relative;min-height:100vh;display:flex;align-items:center;overflow:hidden;}
    .hero-bg{position:absolute;inset:0;background:linear-gradient(150deg,var(--midnight) 0%,var(--navy) 55%,var(--navy-mid) 100%);}
    .hero-grid{position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.04) 1px,transparent 1px);background-size:44px 44px;}
    .hero-glow-gold{position:absolute;top:25%;right:-80px;width:700px;height:700px;background:radial-gradient(ellipse,rgba(200,160,88,0.07) 0%,transparent 60%);}
    .hero-glow-crimson{position:absolute;bottom:-60px;left:-80px;width:500px;height:500px;background:radial-gradient(ellipse,rgba(159,18,57,0.07) 0%,transparent 60%);}
    .hero::after{content:'';position:absolute;bottom:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(200,160,88,0.2),transparent);}
    .hero-inner{position:relative;z-index:2;width:100%;padding:150px 0 110px;display:grid;grid-template-columns:1fr 420px;gap:80px;align-items:center;}
    .eyebrow{font-size:11px;font-weight:600;letter-spacing:3px;text-transform:uppercase;color:var(--gold);display:inline-flex;align-items:center;gap:14px;margin-bottom:22px;}
    .eyebrow::before{content:'';width:28px;height:1px;background:var(--gold);flex-shrink:0;}
    .hero h1{font-family:var(--font-display);font-size:clamp(44px,6vw,72px);font-weight:500;color:var(--white);line-height:1.08;letter-spacing:-0.02em;margin-bottom:20px;opacity:0;animation:fadeUp 0.9s 0.2s forwards;}
    .hero h1 em{font-style:italic;color:var(--gold-bright);}
    .hero-sub{font-size:17px;font-weight:300;color:rgba(255,255,255,0.55);line-height:1.8;max-width:520px;margin-bottom:40px;opacity:0;animation:fadeUp 0.9s 0.4s forwards;}
    .hero-btns{display:flex;gap:12px;flex-wrap:wrap;opacity:0;animation:fadeUp 0.9s 0.55s forwards;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);}}

    /* ── PORTAL CARDS (right side) ── */
    .portal-cards{display:flex;flex-direction:column;gap:14px;opacity:0;animation:fadeUp 1s 0.5s forwards;}
    .portal-card{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.07);border-radius:var(--r-md);padding:22px 24px;display:flex;align-items:center;gap:18px;transition:var(--transition);}
    .portal-card:hover{background:rgba(255,255,255,0.07);border-color:rgba(200,160,88,0.2);transform:translateX(4px);}
    .portal-card-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:17px;}
    .icon-shield{background:var(--admin-crimson-pale);color:#F87171;}
    .icon-gold{background:rgba(200,160,88,0.15);color:var(--gold-bright);}
    .icon-blue{background:rgba(96,165,250,0.12);color:#60A5FA;}
    .portal-card-info{flex:1;}
    .portal-card-title{font-size:14px;font-weight:600;color:rgba(255,255,255,0.9);margin-bottom:2px;}
    .portal-card-desc{font-size:12px;color:rgba(255,255,255,0.38);}
    .portal-card-arrow{font-size:13px;color:rgba(255,255,255,0.22);transition:var(--transition);}
    .portal-card:hover .portal-card-arrow{color:var(--gold);transform:translateX(3px);}

    /* ── SECTIONS ── */
    .section-divider{height:1px;background:linear-gradient(90deg,transparent,rgba(200,160,88,0.15),transparent);}
    .section{padding:100px 0;}
    .section-dark{background:var(--midnight);}
    .section-navy{background:var(--navy);}
    .section-header{margin-bottom:56px;}
    .section-title{font-family:var(--font-display);font-size:clamp(32px,4vw,48px);font-weight:500;color:var(--white);line-height:1.15;letter-spacing:-0.01em;}
    .section-title em{font-style:italic;color:var(--gold-bright);}
    .section-sub{font-size:16px;font-weight:300;color:rgba(255,255,255,0.42);line-height:1.8;max-width:480px;margin-top:14px;}

    /* ── FEATURES ── */
    .features-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;}
    .feature-card{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:var(--r-lg);padding:32px 26px;transition:var(--transition);}
    .feature-card:hover{background:rgba(255,255,255,0.055);border-color:rgba(200,160,88,0.22);transform:translateY(-4px);}
    .feature-icon{width:48px;height:48px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;margin-bottom:20px;font-size:18px;}
    .fi-gold{background:rgba(200,160,88,0.12);color:var(--gold-bright);}
    .fi-crimson{background:var(--admin-crimson-pale);color:#F87171;}
    .fi-blue{background:rgba(96,165,250,0.12);color:#60A5FA;}
    .fi-green{background:rgba(52,211,153,0.12);color:#34D399;}
    .feature-card h3{font-family:var(--font-display);font-size:20px;font-weight:500;color:rgba(255,255,255,0.9);margin-bottom:10px;}
    .feature-card p{font-size:13px;font-weight:300;color:rgba(255,255,255,0.38);line-height:1.75;}

    /* ── STATS ── */
    .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:rgba(255,255,255,0.06);border-radius:var(--r-xl);overflow:hidden;border:1px solid rgba(255,255,255,0.08);}
    .stat-item{background:var(--navy);padding:44px 32px;text-align:center;transition:var(--transition);}
    .stat-item:hover{background:var(--navy-mid);}
    .stat-num{font-family:var(--font-display);font-size:54px;font-weight:500;color:var(--gold-bright);line-height:1;margin-bottom:8px;}
    .stat-lbl{font-size:11px;font-weight:500;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,0.3);}

    /* ── CTA ── */
    .cta-band{background:var(--navy-mid);border-radius:var(--r-xl);padding:56px;display:flex;align-items:center;justify-content:space-between;gap:40px;flex-wrap:wrap;position:relative;overflow:hidden;}
    .cta-band::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.025) 1px,transparent 1px);background-size:32px 32px;}
    .cta-band::after{content:'';position:absolute;top:0;right:-100px;width:400px;height:400px;background:radial-gradient(ellipse,rgba(200,160,88,0.08) 0%,transparent 65%);}
    .cta-text{position:relative;z-index:1;}
    .cta-text h2{font-family:var(--font-display);font-size:clamp(28px,3vw,40px);font-weight:500;color:var(--white);line-height:1.2;margin-bottom:8px;}
    .cta-text h2 em{font-style:italic;color:var(--gold-bright);}
    .cta-text p{font-size:15px;font-weight:300;color:rgba(255,255,255,0.45);}
    .cta-btns{display:flex;gap:12px;flex-wrap:wrap;position:relative;z-index:1;}

    /* ── FOOTER ── */
    .footer{background:var(--navy);padding:56px 0 28px;border-top:1px solid rgba(255,255,255,0.05);}
    .footer-inner{display:flex;justify-content:space-between;align-items:flex-start;gap:48px;margin-bottom:40px;flex-wrap:wrap;}
    .footer-brand-logo{display:flex;align-items:center;gap:12px;margin-bottom:12px;}
    .footer-logomark{width:30px;height:30px;background:var(--gold);border-radius:6px;display:flex;align-items:center;justify-content:center;}
    .footer-logomark svg{width:17px;height:17px;}
    .footer-logo-text{font-family:var(--font-display);font-size:15px;color:var(--white);}
    .footer-desc{font-size:12.5px;font-weight:300;color:rgba(255,255,255,0.3);max-width:240px;line-height:1.75;}
    .footer-col h4{font-size:9.5px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,0.25);margin-bottom:14px;}
    .footer-col ul{list-style:none;display:flex;flex-direction:column;gap:8px;}
    .footer-col a{font-size:13px;font-weight:300;color:rgba(255,255,255,0.45);transition:color var(--transition);}
    .footer-col a:hover{color:var(--gold-bright);}
    .footer-bottom{padding-top:24px;border-top:1px solid rgba(255,255,255,0.05);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;}
    .footer-bottom p{font-size:11.5px;color:rgba(255,255,255,0.2);}

    @media(max-width:1100px){.hero-inner{grid-template-columns:1fr}.portal-cards{display:none}.features-grid{grid-template-columns:repeat(2,1fr)}.stats-grid{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:768px){.nav-links{display:none}.cta-band{flex-direction:column}}
    @media(max-width:480px){.container{padding:0 20px}.hero-btns,.cta-btns{flex-direction:column}.features-grid{grid-template-columns:1fr}.stats-grid{grid-template-columns:1fr 1fr}}
  </style>
</head>
<body>

<nav class="nav" id="navbar">
  <div class="container"><div class="nav-inner">
    <a href="index.php" class="nav-logo">
      <div class="nav-logomark"><svg viewBox="0 0 22 22" fill="none"><path d="M4 18L11 4L18 18" stroke="#080E1C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 13H15" stroke="#080E1C" stroke-width="1.8" stroke-linecap="round"/></svg></div>
      <div class="nav-logo-text">Bold Footprint<span>Initiatives</span></div>
    </a>
    <div class="nav-links">
      <span class="admin-pill"><i class="fas fa-shield-alt" style="font-size:8px;"></i> Admin Portal</span>
      <a href="/index.html" class="nav-text-link">Main Site</a>
      <a href="/scholar-portal/index.php" class="nav-text-link">Scholar Portal</a>
      <a href="admin-login.php" class="btn btn-outline-gold" style="padding:10px 22px;font-size:13px;">Sign In</a>
      <a href="admin-register.php" class="btn btn-crimson" style="padding:10px 22px;font-size:13px;">Register</a>
    </div>
  </div></div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-bg"></div><div class="hero-grid"></div>
  <div class="hero-glow-gold"></div><div class="hero-glow-crimson"></div>
  <div class="container">
    <div class="hero-inner">
      <div>
        <div class="eyebrow">BFI Administration</div>
        <h1>Command centre<br>for <em>scholar impact.</em></h1>
        <p class="hero-sub">The administrative backbone of Bold Footprint Initiatives — manage scholars, review applications, track programme outcomes, and drive lasting impact from one secure portal.</p>
        <div class="hero-btns">
          <a href="admin-login.php" class="btn btn-gold"><i class="fas fa-shield-alt"></i> Admin Sign In <svg class="arrow-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
          <a href="admin-register.php" class="btn btn-ghost">Create Account</a>
        </div>
      </div>
      <div class="portal-cards">
        <a href="admin-login.php" class="portal-card">
          <div class="portal-card-icon icon-shield"><i class="fas fa-user-shield"></i></div>
          <div class="portal-card-info"><div class="portal-card-title">Existing Administrator</div><div class="portal-card-desc">Sign in with your credentials</div></div>
          <div class="portal-card-arrow"><i class="fas fa-arrow-right"></i></div>
        </a>
        <a href="admin-register.php" class="portal-card">
          <div class="portal-card-icon icon-gold"><i class="fas fa-user-plus"></i></div>
          <div class="portal-card-info"><div class="portal-card-title">New Administrator</div><div class="portal-card-desc">Register with an authorised email</div></div>
          <div class="portal-card-arrow"><i class="fas fa-arrow-right"></i></div>
        </a>
        <a href="admin-dashboard.php" class="portal-card">
          <div class="portal-card-icon icon-blue"><i class="fas fa-chart-pie"></i></div>
          <div class="portal-card-info"><div class="portal-card-title">Admin Dashboard</div><div class="portal-card-desc">Scholar management & analytics</div></div>
          <div class="portal-card-arrow"><i class="fas fa-arrow-right"></i></div>
        </a>
      </div>
    </div>
  </div>
</section>

<!-- FEATURES -->
<div class="section-divider"></div>
<section class="section section-navy">
  <div class="container">
    <div class="section-header">
      <div class="eyebrow">Portal Capabilities</div>
      <h2 class="section-title">Everything you need to<br><em>manage scholars.</em></h2>
    </div>
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon fi-gold"><i class="fas fa-user-graduate"></i></div>
        <h3>Scholar Management</h3>
        <p>Full visibility into scholar profiles, academic progress, and programme milestones across all programme levels.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon fi-crimson"><i class="fas fa-file-alt"></i></div>
        <h3>Document Review</h3>
        <p>Verify, approve, and annotate scholar documents — CVs, transcripts, SOPs — in a streamlined review workflow.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon fi-blue"><i class="fas fa-chart-line"></i></div>
        <h3>Analytics & Reports</h3>
        <p>Real-time dashboards showing programme performance, application trends, and scholar placement outcomes.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon fi-green"><i class="fas fa-bell"></i></div>
        <h3>Smart Notifications</h3>
        <p>Automated alerts for pending approvals, approaching deadlines, and programme milestones requiring attention.</p>
      </div>
    </div>
  </div>
</section>

<!-- STATS -->
<div class="section-divider"></div>
<section class="section section-dark">
  <div class="container">
    <div class="section-header" style="text-align:center;margin-bottom:48px;">
      <div class="eyebrow" style="justify-content:center;">Impact Numbers</div>
      <h2 class="section-title" style="text-align:center;">Bold results from<br><em>bold decisions.</em></h2>
    </div>
    <div class="stats-grid">
      <div class="stat-item"><div class="stat-num">50+</div><div class="stat-lbl">Scholars Supported</div></div>
      <div class="stat-item"><div class="stat-num">3</div><div class="stat-lbl">Active Programmes</div></div>
      <div class="stat-item"><div class="stat-num">98%</div><div class="stat-lbl">Completion Rate</div></div>
      <div class="stat-item"><div class="stat-num">9+</div><div class="stat-lbl">Mentor Disciplines</div></div>
    </div>
  </div>
</section>

<!-- CTA -->
<div class="section-divider"></div>
<section class="section section-navy">
  <div class="container">
    <div class="cta-band">
      <div class="cta-text">
        <h2>Ready to manage your<br><em>scholar programmes?</em></h2>
        <p>Access the command centre and keep BFI's mission moving forward.</p>
      </div>
      <div class="cta-btns">
        <a href="admin-login.php" class="btn btn-gold"><i class="fas fa-shield-alt"></i> Admin Sign In <svg class="arrow-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
        <a href="admin-register.php" class="btn btn-ghost">Create Account</a>
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
        <p class="footer-desc">Administration portal for managing scholars, programmes, and academic outcomes since 2016.</p>
      </div>
      <div class="footer-col"><h4>Admin Portal</h4><ul><li><a href="admin-login.php">Sign In</a></li><li><a href="admin-register.php">Register</a></li><li><a href="admin-dashboard.php">Dashboard</a></li></ul></div>
      <div class="footer-col"><h4>Main Site</h4><ul><li><a href="/index.html">Home</a></li><li><a href="/about.html">About Us</a></li><li><a href="/programs.html">Programmes</a></li></ul></div>
      <div class="footer-col"><h4>Contact</h4><ul><li><a href="mailto:info@bfinitiatives.com">info@bfinitiatives.com</a></li><li><a href="tel:+2348165011291">(+234) 816 501 1291</a></li></ul></div>
    </div>
    <div class="footer-bottom">
      <p>© 2026 Bold Footprint Initiatives. All rights reserved.</p>
      <span class="admin-pill" style="font-size:8px;"><i class="fas fa-shield-alt" style="font-size:7px;"></i> Secure Admin Access</span>
    </div>
  </div>
</footer>

<script>
  const nav = document.getElementById('navbar');
  window.addEventListener('scroll', () => nav.classList.toggle('scrolled', window.scrollY > 60), { passive: true });
</script>
</body>
</html>