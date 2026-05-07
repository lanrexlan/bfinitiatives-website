<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_logout'])) {
    session_start();

    require_once 'includes/config.php';
    require_once 'includes/db.php';
    require_once 'includes/functions.php';

    if (isset($_COOKIE['remember_token'])) {
        list($user_id, $token) = explode(':', $_COOKIE['remember_token'], 2);
        try {
            $db = new Database();
            $conn = $db->getConnection();
            $conn->prepare("DELETE FROM remember_tokens WHERE user_id = $1")->execute([$user_id]);
        } catch (Exception $e) {
            error_log("Logout token deletion error: " . $e->getMessage());
        }
        setcookie('remember_token', '', time() - 3600, '/');
    }

    session_destroy();
    setcookie('logout_success', '1', time() + 30, '/');
    header('Location: admin-login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign Out | Bold Footprint Initiatives</title>
  <link rel="icon" type="image/png" sizes="32x32" href="/Images/BFI_Logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <style>
    :root{
      --midnight:#080E1C;--navy:#0D1829;--navy-mid:#132038;
      --gold:#C8A058;--gold-bright:#E0B96C;--white:#FFFFFF;
      --admin-crimson:#9F1239;--admin-crimson-light:#BE123C;
      --font-display:'Cormorant Garamond',Georgia,serif;
      --font-body:'Outfit',-apple-system,sans-serif;
      --ease:cubic-bezier(0.25,0.46,0.45,0.94);
      --transition:0.35s var(--ease);
      --r-sm:8px;--r-md:16px;--r-lg:24px;--r-xl:32px;
    }
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:var(--font-body);background:var(--midnight);min-height:100vh;display:flex;align-items:center;justify-content:center;-webkit-font-smoothing:antialiased;overflow:hidden;}
    .page-bg{position:fixed;inset:0;background:linear-gradient(148deg,var(--midnight) 0%,var(--navy) 55%,var(--navy-mid) 100%);z-index:0;}
    .page-grid{position:fixed;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:44px 44px;z-index:0;}
    .page-glow{position:fixed;top:40%;left:50%;transform:translate(-50%,-50%);width:600px;height:600px;background:radial-gradient(ellipse,rgba(159,18,57,0.06) 0%,transparent 60%);z-index:0;}

    .card{position:relative;z-index:10;background:rgba(13,24,41,0.8);border:1px solid rgba(255,255,255,0.08);border-radius:var(--r-xl);padding:52px 44px;max-width:440px;width:calc(100% - 40px);text-align:center;backdrop-filter:blur(20px);box-shadow:0 20px 60px rgba(8,14,28,0.3);animation:slideUp 0.6s var(--ease) forwards;}
    @keyframes slideUp{from{opacity:0;transform:translateY(28px);}to{opacity:1;transform:translateY(0);}}

    .card-logo{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:36px;}
    .logomark{width:32px;height:32px;background:var(--gold);border-radius:7px;display:flex;align-items:center;justify-content:center;}
    .logomark svg{width:18px;height:18px;}
    .logo-text{font-family:var(--font-display);font-size:15px;font-weight:500;color:var(--white);}

    .wave-icon{width:72px;height:72px;border-radius:50%;background:rgba(200,160,88,0.1);border:1px solid rgba(200,160,88,0.2);display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 24px;animation:wavePulse 2s ease-in-out infinite;}
    @keyframes wavePulse{0%,100%{transform:scale(1);}50%{transform:scale(1.06);}}

    h1{font-family:var(--font-display);font-size:34px;font-weight:500;color:var(--white);line-height:1.15;margin-bottom:12px;}
    h1 em{font-style:italic;color:var(--gold-bright);}
    p{font-size:14px;font-weight:300;color:rgba(255,255,255,0.45);line-height:1.8;margin-bottom:36px;}

    .btn-row{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 28px;font-family:var(--font-body);font-size:13.5px;font-weight:500;border-radius:var(--r-sm);border:none;cursor:pointer;transition:var(--transition);}
    .btn-cancel{background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.7);border:1px solid rgba(255,255,255,0.1);}
    .btn-cancel:hover{background:rgba(255,255,255,0.1);color:var(--white);}
    .btn-confirm{background:var(--admin-crimson);color:white;}
    .btn-confirm:hover{background:var(--admin-crimson-light);transform:translateY(-2px);}

    .admin-pill{display:inline-flex;align-items:center;gap:5px;background:var(--admin-crimson);color:white;font-size:8.5px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:3px 9px;border-radius:20px;margin-top:28px;}
  </style>
</head>
<body>
<div class="page-bg"></div><div class="page-grid"></div><div class="page-glow"></div>

<div class="card">
  <div class="card-logo">
    <div class="logomark"><svg viewBox="0 0 22 22" fill="none"><path d="M4 18L11 4L18 18" stroke="#080E1C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 13H15" stroke="#080E1C" stroke-width="1.8" stroke-linecap="round"/></svg></div>
    <span class="logo-text">Bold Footprint</span>
  </div>

  <div class="wave-icon">👋</div>
  <h1>Sign <em>out?</em></h1>
  <p>You're about to leave the Admin Command Centre. You'll need to sign in again to access scholar management and programme data.</p>

  <div class="btn-row">
    <a href="admin-dashboard.php">
      <button type="button" class="btn btn-cancel"><i class="fas fa-arrow-left" style="font-size:11px;"></i> Stay in Portal</button>
    </a>
    <form method="POST" style="display:inline;">
      <input type="hidden" name="confirm_logout" value="1">
      <button type="submit" class="btn btn-confirm"><i class="fas fa-sign-out-alt" style="font-size:12px;"></i> Yes, Sign Out</button>
    </form>
  </div>

  <div style="display:flex;justify-content:center;">
    <span class="admin-pill"><i class="fas fa-shield-alt" style="font-size:7px;"></i> Admin Portal — Secure Session</span>
  </div>
</div>
</body>
</html>