<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';


function createRememberMeToken($user_id) {
    $token = bin2hex(random_bytes(32));
    $hashed_token = password_hash($token, PASSWORD_DEFAULT);
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
    try {
        $db = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        $stmt = $conn->prepare("
            INSERT INTO remember_tokens (user_id, token, expires_at, created_at) 
            VALUES (:user_id, :token, :expires, NOW())
        ");
        $stmt->execute([':user_id' => $user_id, ':token' => $hashed_token, ':expires' => $expires]);
        error_log("Remember token created successfully for user ID: " . $user_id);
        return $token;
    } catch (PDOException $e) {
        error_log("Remember token creation failed: " . $e->getMessage());
        return false;
    }
}

if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        list($user_id, $token) = explode(':', $_COOKIE['remember_token'], 2);
        $stmt = $conn->prepare("
            SELECT u.*, rt.token as hashed_token, ae.used as is_authorized
            FROM users u 
            JOIN remember_tokens rt ON u.id = rt.user_id 
            LEFT JOIN authorized_emails ae ON u.email = ae.email
            WHERE rt.user_id = :user_id AND rt.expires_at > CURRENT_TIMESTAMP
        ");
        $stmt->execute([':user_id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($token, $user['hashed_token']) && $user['is_authorized']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role_id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_program'] = $user['program'];
            if (isset($_POST['remember']) && $_POST['remember'] == 'on') {
                $remember_token = createRememberMeToken($user['id']);
                if ($remember_token) {
                    $cookie_value = $user['id'] . ':' . $remember_token;
                    error_log("Setting remember token cookie: " . $cookie_value);
                    setcookie('remember_token', $cookie_value, time() + (86400 * 30), '/', '', true, true);
                    error_log("Cookie set result: " . (isset($_COOKIE['remember_token']) ? 'Yes' : 'No'));
                }
            }
            switch($user['program']) {
                case 'primary': header('Location: dashboard_primary.php'); break;
                case 'secondary': header('Location: dashboard_secondary.php'); break;
                case 'graduate': header('Location: dashboard.php'); break;
                default: header('Location: dashboard.php');
            }
            exit();
        } else {
            setcookie('remember_token', '', time() - 3600, '/');
        }
    } catch (PDOException $e) {
        error_log("Remember token check failed: " . $e->getMessage());
    }
}

error_log("Login process started");

if (isset($_SESSION['user_id'])) {
    switch($_SESSION['user_program']) {
        case 'primary': header('Location: dashboard_primary.php'); break;
        case 'secondary': header('Location: dashboard_secondary.php'); break;
        case 'graduate': header('Location: dashboard.php'); break;
        default: header('Location: dashboard.php');
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    try {
        $db = new Database();
        $conn = $db->getConnection();
        error_log("Login attempt for: " . $email);
        $stmt = $conn->prepare("
            SELECT u.*, ae.used as is_authorized 
            FROM users u 
            LEFT JOIN authorized_emails ae ON u.email = ae.email
            WHERE u.email = :email 
            AND u.is_active = true
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("User found: " . ($user ? 'Yes' : 'No'));
        if ($user) {
            error_log("Verification status: " . ($user['is_verified'] ? 'Verified' : 'Not verified'));
            error_log("Authorization status: " . ($user['is_authorized'] ? 'Authorized' : 'Not authorized'));
        }
        if ($user && password_verify($password, $user['password'])) {
            if (!$user['is_authorized']) {
                $error = 'This account is not authorized to access the portal.';
                error_log("Login failed: User not authorized");
            } else if (!$user['is_verified']) {
                $error = 'Please verify your email address before logging in.';
                error_log("Login failed: Email not verified");
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role_id'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_program'] = $user['program'];
                if (isset($_POST['remember']) && $_POST['remember'] == 'on') {
                    $remember_token = createRememberMeToken($user['id']);
                    if ($remember_token) {
                        setcookie('remember_token', $user['id'] . ':' . $remember_token, time() + (86400 * 30), '/', '', true, true);
                    }
                }
                $update_stmt = $conn->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = $1");
                $update_stmt->execute([$user['id']]);
                error_log("Login successful for user: " . $user['email']);
                switch($user['program']) {
                    case 'primary': header('Location: dashboard_primary.php'); break;
                    case 'secondary': header('Location: dashboard_secondary.php'); break;
                    case 'graduate': header('Location: dashboard.php'); break;
                    default: header('Location: dashboard.php');
                }
                exit();
            }
        } else {
            error_log("Login failed: Invalid credentials");
            $error = 'Invalid email or password';
        }
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        $error = 'Database error occurred';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Scholar Login | Bold Footprint Initiatives</title>
  <link rel="icon" type="image/png" sizes="32x32" href="/Images/BFI_Logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <style>
    :root{--midnight:#080E1C;--navy:#0D1829;--navy-mid:#132038;--navy-light:#1C2F52;--gold:#C8A058;--gold-bright:#E0B96C;--gold-pale:#F0D9A8;--cream:#FAF6EF;--white:#FFFFFF;--text-primary:#0D1829;--text-secondary:#4A526A;--text-muted:#8A92A8;--border-light:#E8E4DA;--font-display:'Cormorant Garamond',Georgia,serif;--font-body:'Outfit',-apple-system,sans-serif;--ease:cubic-bezier(0.25,0.46,0.45,0.94);--transition:0.35s var(--ease);--r-sm:8px;--r-md:16px;--r-lg:24px;--r-xl:32px;}
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:var(--font-body);min-height:100vh;display:grid;grid-template-columns:1fr 480px;overflow:hidden;}
    a{text-decoration:none;color:inherit;}
    img{max-width:100%;display:block;}

    /* LEFT PANEL */
    .left-panel{background:var(--midnight);position:relative;overflow:hidden;display:flex;flex-direction:column;justify-content:space-between;padding:48px;}
    .left-bg{position:absolute;inset:0;background:linear-gradient(148deg,var(--midnight) 0%,var(--navy) 60%,var(--navy-mid) 100%);}
    .left-grid{position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.04) 1px,transparent 1px);background-size:44px 44px;}
    .left-glow{position:absolute;bottom:-100px;left:-100px;width:500px;height:500px;background:radial-gradient(ellipse,rgba(200,160,88,0.08) 0%,transparent 65%);}
    .left-content{position:relative;z-index:2;}
    .left-logo{display:flex;align-items:center;gap:12px;margin-bottom:64px;}
    .left-logomark{width:38px;height:38px;background:var(--gold);border-radius:8px;display:flex;align-items:center;justify-content:center;}
    .left-logomark svg{width:22px;height:22px;}
    .left-logo-text{font-family:var(--font-display);font-size:18px;font-weight:500;color:var(--white);line-height:1.2;}
    .left-logo-text span{display:block;font-family:var(--font-body);font-size:10px;letter-spacing:2.5px;text-transform:uppercase;color:rgba(200,160,88,0.6);}
    .left-headline{font-family:var(--font-display);font-size:clamp(36px,4vw,52px);font-weight:500;color:var(--white);line-height:1.1;letter-spacing:-0.02em;margin-bottom:16px;}
    .left-headline em{font-style:italic;color:var(--gold-bright);}
    .left-sub{font-size:15px;font-weight:300;color:rgba(255,255,255,0.5);line-height:1.8;max-width:380px;}
    .left-scholars{position:relative;z-index:2;}
    .left-scholars-label{font-size:10px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,0.3);margin-bottom:14px;}
    .scholar-avatars{display:flex;align-items:center;gap:-8px;}
    .scholar-avatar{width:38px;height:38px;border-radius:50%;border:2px solid var(--midnight);overflow:hidden;margin-right:-10px;background:var(--navy-light);}
    .scholar-avatar img{width:100%;height:100%;object-fit:cover;}
    .scholar-avatar-more{width:38px;height:38px;border-radius:50%;border:2px solid var(--midnight);background:rgba(200,160,88,0.15);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:600;color:var(--gold);margin-left:2px;}
    .scholar-count-text{font-size:13px;color:rgba(255,255,255,0.45);margin-left:16px;}

    /* RIGHT PANEL */
    .right-panel{background:var(--white);display:flex;flex-direction:column;justify-content:center;padding:56px 48px;overflow-y:auto;}
    .login-back{display:inline-flex;align-items:center;gap:8px;font-size:13px;color:var(--text-muted);margin-bottom:48px;transition:color var(--transition);}
    .login-back:hover{color:var(--gold);}
    .login-eyebrow{font-size:11px;font-weight:600;letter-spacing:3px;text-transform:uppercase;color:var(--gold);display:inline-flex;align-items:center;gap:12px;margin-bottom:16px;}
    .login-eyebrow::before{content:'';width:24px;height:1px;background:var(--gold);}
    .login-title{font-family:var(--font-display);font-size:36px;font-weight:500;color:var(--navy);line-height:1.15;margin-bottom:32px;letter-spacing:-0.01em;}
    .login-title em{font-style:italic;color:var(--gold);}

    /* ERROR */
    .alert-error{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.2);border-radius:var(--r-sm);margin-bottom:24px;}
    .alert-error i{color:#EF4444;font-size:14px;margin-top:1px;flex-shrink:0;}
    .alert-error span{font-size:13.5px;color:#B91C1C;line-height:1.5;}

    /* FORM */
    .form-group{margin-bottom:20px;}
    .form-label{display:block;font-size:13px;font-weight:500;color:var(--text-primary);margin-bottom:8px;}
    .input-wrap{position:relative;}
    .input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:14px;pointer-events:none;}
    .form-input{width:100%;padding:13px 14px 13px 42px;border:1px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:14px;color:var(--text-primary);background:var(--white);transition:border-color var(--transition),box-shadow var(--transition);}
    .form-input:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,160,88,0.1);}
    .form-input::placeholder{color:var(--text-muted);}
    .password-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:14px;padding:2px;}
    .password-toggle:hover{color:var(--gold);}
    .form-footer{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;}
    .check-wrap{display:flex;align-items:center;gap:8px;cursor:pointer;}
    .check-input{width:16px;height:16px;border-radius:4px;border:1px solid var(--border-light);accent-color:var(--navy);cursor:pointer;}
    .check-label{font-size:13px;color:var(--text-secondary);cursor:pointer;}
    .forgot-link{font-size:13px;color:var(--gold);font-weight:500;transition:color var(--transition);}
    .forgot-link:hover{color:var(--navy);}
    .btn-submit{width:100%;padding:15px;background:var(--navy);color:var(--white);font-family:var(--font-body);font-size:15px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);display:flex;align-items:center;justify-content:center;gap:10px;}
    .btn-submit:hover{background:var(--navy-light);transform:translateY(-1px);box-shadow:0 8px 24px rgba(8,14,28,0.15);}
    .divider{display:flex;align-items:center;gap:16px;margin:24px 0;}
    .divider hr{flex:1;border:none;height:1px;background:var(--border-light);}
    .divider span{font-size:12px;color:var(--text-muted);}
    .register-row{text-align:center;font-size:13.5px;color:var(--text-secondary);}
    .register-row a{color:var(--gold);font-weight:500;transition:color var(--transition);}.register-row a:hover{color:var(--navy);}

    @media(max-width:900px){body{grid-template-columns:1fr}.left-panel{display:none}.right-panel{padding:40px 32px}}
    @media(max-width:480px){.right-panel{padding:32px 24px}}
  </style>
</head>
<body>

<!-- LEFT PANEL -->
<div class="left-panel">
  <div class="left-bg"></div><div class="left-grid"></div><div class="left-glow"></div>
  <div class="left-content">
    <div class="left-logo">
      <div class="left-logomark"><svg viewBox="0 0 22 22" fill="none"><path d="M4 18L11 4L18 18" stroke="#080E1C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 13H15" stroke="#080E1C" stroke-width="1.8" stroke-linecap="round"/></svg></div>
      <div class="left-logo-text">Bold Footprint<span>Scholar Portal</span></div>
    </div>
    <div class="left-headline">Welcome back<br>to your <em>journey.</em></div>
    <p class="left-sub">Your scholarship dashboard, mentor connection, documents, and academic progress — all waiting for you inside.</p>
  </div>
  <div class="left-scholars">
    <div class="left-scholars-label">Joined by 80+ scholars</div>
    <div style="display:flex;align-items:center;">
      <div class="scholar-avatars">
        <div class="scholar-avatar"><img src="/Images/opeyemi.jpg" alt="" onerror="this.parentElement.style.background='var(--navy-light)';this.style.display='none';"></div>
        <div class="scholar-avatar"><img src="/Images/babatunde.jpg" alt="" onerror="this.parentElement.style.background='var(--navy-light)';this.style.display='none';"></div>
        <div class="scholar-avatar"><img src="/Images/Tijani_image.png" alt="" onerror="this.parentElement.style.background='var(--navy-light)';this.style.display='none';"></div>
        <div class="scholar-avatar-more">80+</div>
      </div>
      <span class="scholar-count-text">scholars already inside</span>
    </div>
  </div>
</div>

<!-- RIGHT PANEL -->
<div class="right-panel">
  <a href="index.php" class="login-back"><i class="fas fa-arrow-left" style="font-size:11px;"></i> Back to portal home</a>

  <div class="login-eyebrow">Scholar Login</div>
  <h1 class="login-title">Sign in to your<br><em>account.</em></h1>

  <?php if ($error): ?>
  <div class="alert-error">
    <i class="fas fa-exclamation-circle"></i>
    <span><?php echo htmlspecialchars($error); ?></span>
  </div>
  <?php endif; ?>

  <?php if (isset($_COOKIE['logout_success'])): ?>
  <div style="display:flex;align-items:flex-start;gap:12px;padding:14px 16px;background:rgba(52,211,153,0.06);border:1px solid rgba(52,211,153,0.2);border-radius:var(--r-sm);margin-bottom:24px;">
    <i class="fas fa-check-circle" style="color:#34D399;font-size:14px;margin-top:1px;flex-shrink:0;"></i>
    <span style="font-size:13.5px;color:#065F46;">You have been successfully logged out.</span>
  </div>
  <?php endif; ?>

  <form method="POST" action="login.php">
    <div class="form-group">
      <label class="form-label" for="email">Email Address</label>
      <div class="input-wrap">
        <i class="fas fa-envelope input-icon"></i>
        <input type="email" id="email" name="email" class="form-input" placeholder="you@example.com" required>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="password">Password</label>
      <div class="input-wrap">
        <i class="fas fa-lock input-icon"></i>
        <input type="password" id="password" name="password" class="form-input" placeholder="Your password" required>
        <button type="button" class="password-toggle" id="togglePassword"><i class="fas fa-eye"></i></button>
      </div>
    </div>

    <div class="form-footer">
      <label class="check-wrap">
        <input type="checkbox" id="remember" name="remember" class="check-input">
        <span class="check-label">Remember me</span>
      </label>
      <a href="forgot-password.php" class="forgot-link">Forgot password?</a>
    </div>

    <button type="submit" class="btn-submit">
      <i class="fas fa-sign-in-alt"></i> Sign In to Portal
    </button>
  </form>

  <div class="divider"><hr><span>or</span><hr></div>

  <div class="register-row">
    Don't have an account yet? <a href="register.php">Register here</a>
  </div>
</div>

<script>
  document.getElementById('togglePassword').addEventListener('click',function(){
    const p=document.getElementById('password');
    const icon=this.querySelector('i');
    if(p.type==='password'){p.type='text';icon.className='fas fa-eye-slash';}
    else{p.type='password';icon.className='fas fa-eye';}
  });
</script>
</body>
</html>