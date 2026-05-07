<?php
// Start session only when form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_logout'])) {
    session_start();
    
    // Include the database connection
    require_once 'includes/config.php';
    require_once 'includes/db.php';
    require_once 'includes/functions.php';
    
    // Delete remember token
    if (isset($_COOKIE['remember_token'])) {
        list($user_id, $token) = explode(':', $_COOKIE['remember_token'], 2);
        $db = new Database();
        $conn = $db->getConnection();
        $conn->prepare("DELETE FROM remember_tokens WHERE user_id = $1")
             ->execute([$user_id]);
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    session_destroy();
    
    // Set a temporary cookie to show logout success message on login page
    setcookie('logout_success', '1', time() + 30, '/');
    
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Log Out | BFI Scholar Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    :root{--midnight:#080E1C;--navy:#0D1829;--navy-mid:#132038;--gold:#C8A058;--gold-bright:#E0B96C;--cream:#FAF6EF;--white:#FFFFFF;--text-primary:#0D1829;--text-secondary:#4A526A;--text-muted:#8A92A8;--border-light:#E8E4DA;--font-display:'Cormorant Garamond',Georgia,serif;--font-body:'Outfit',-apple-system,sans-serif;--ease:cubic-bezier(0.25,0.46,0.45,0.94);--transition:0.35s var(--ease);}
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:var(--font-body);min-height:100vh;background:var(--midnight);display:flex;align-items:center;justify-content:center;padding:24px;position:relative;overflow:hidden;}
    body::before{content:'';position:absolute;inset:0;background:linear-gradient(148deg,var(--midnight) 0%,var(--navy) 60%,var(--navy-mid) 100%);}
    body::after{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.04) 1px,transparent 1px);background-size:44px 44px;}
    a{text-decoration:none;color:inherit;}

    .card{position:relative;z-index:2;background:var(--white);border-radius:24px;padding:48px 40px;max-width:420px;width:100%;text-align:center;box-shadow:0 40px 80px rgba(0,0,0,0.4);}

    /* Gold top accent */
    .card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--gold),var(--gold-bright));border-radius:24px 24px 0 0;}

    /* Icon */
    .logout-icon{width:72px;height:72px;border-radius:50%;background:var(--cream);border:2px solid var(--border-light);display:flex;align-items:center;justify-content:center;margin:0 auto 28px;font-size:28px;}

    .card-title{font-family:var(--font-display);font-size:32px;font-weight:500;color:var(--navy);letter-spacing:-0.01em;margin-bottom:12px;}
    .card-title em{font-style:italic;color:var(--gold);}
    .card-sub{font-size:14.5px;font-weight:300;color:var(--text-secondary);line-height:1.7;margin-bottom:36px;}

    .btn-row{display:flex;gap:12px;justify-content:center;}
    .btn{display:inline-flex;align-items:center;gap:8px;padding:13px 28px;font-family:var(--font-body);font-size:14px;font-weight:500;border-radius:8px;border:none;cursor:pointer;transition:var(--transition);}
    .btn-cancel{background:var(--cream);color:var(--text-primary);border:1px solid var(--border-light);}
    .btn-cancel:hover{background:var(--border-light);}
    .btn-logout{background:#EF4444;color:var(--white);}
    .btn-logout:hover{background:#DC2626;transform:translateY(-1px);box-shadow:0 8px 20px rgba(239,68,68,0.3);}

    .portal-link{display:block;margin-top:24px;font-size:12.5px;color:var(--text-muted);}
    .portal-link a{color:var(--gold);font-weight:500;}
    .portal-link a:hover{color:var(--navy);}
  </style>
</head>
<body>
  <div class="card">
    <div class="logout-icon">👋</div>
    <h1 class="card-title">Leaving so <em>soon?</em></h1>
    <p class="card-sub">Are you sure you want to log out? You'll need to sign in again to access your scholarship dashboard and resources.</p>
    <div class="btn-row">
      <a href="dashboard.php"><button type="button" class="btn btn-cancel">Stay in Portal</button></a>
      <form method="POST" style="display:inline;">
        <input type="hidden" name="confirm_logout" value="1">
        <button type="submit" class="btn btn-logout">Yes, Log Out</button>
      </form>
    </div>
    <p class="portal-link">Go back to the <a href="/index.html">BFI main site</a></p>
  </div>
</body>
</html>