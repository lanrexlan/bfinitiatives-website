<?php
session_start();
ini_set('error_log', 'my_custom_error.log');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/csrf.php';

if (!function_exists('isAdminLoggedIn')) {
    function isAdminLoggedIn() {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    }
}

if (isAdminLoggedIn()) {
    header('Location: admin-dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!bfi_validate_csrf_post()) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    try {
        $db = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM admin_authorized_emails WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $authorized = $stmt->fetch();
        if ($authorized) {
            if (!$authorized['is_used']) {
                $_SESSION['admin_email'] = $email;
                $_SESSION['admin_role'] = $authorized['role'];
                $_SESSION['admin_registration'] = true;
                header("Location: admin-register.php");
                exit();
            }
            $stmt = $conn->prepare("SELECT * FROM admins WHERE email = :email AND is_active = TRUE");
            $stmt->execute([':email' => $email]);
            $admin = $stmt->fetch();
            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['is_admin'] = true;
                $_SESSION['admin_role'] = isset($authorized['role']) ? $authorized['role'] : '';
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
                $log_stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, ip_address) VALUES (:admin_id, 'login', :ip_address)");
                $log_stmt->execute([':admin_id' => $admin['id'], ':ip_address' => $_SERVER['REMOTE_ADDR']]);
                session_regenerate_id(true);
                header('Location: admin-dashboard.php');
                exit();
            } else {
                $error = 'Invalid credentials. Please check your email and password.';
            }
        } else {
            $error = 'This email is not authorised for admin access.';
        }
    } catch (Exception $e) {
        error_log("Admin login error: " . $e->getMessage());
        $error = 'An error occurred during login. Please try again.';
    }
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login | Bold Footprint Initiatives</title>
  <link rel="icon" type="image/png" sizes="32x32" href="/Images/bfi-new-logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <style>
    :root{
      --midnight:#080E1C;--navy:#0D1829;--navy-mid:#132038;--navy-light:#1C2F52;
      --gold:#C8A058;--gold-bright:#E0B96C;--gold-pale:#F0D9A8;
      --cream:#FAF6EF;--white:#FFFFFF;
      --text-primary:#0D1829;--text-secondary:#4A526A;--text-muted:#8A92A8;
      --border-light:#E8E4DA;
      --admin-crimson:#9F1239;--admin-crimson-light:#BE123C;--admin-crimson-pale:rgba(159,18,57,0.1);
      --font-display:'Cormorant Garamond',Georgia,serif;
      --font-body:'Outfit',-apple-system,sans-serif;
      --ease:cubic-bezier(0.25,0.46,0.45,0.94);
      --transition:0.35s var(--ease);
      --shadow-lg:0 20px 60px rgba(8,14,28,0.20);
      --r-sm:8px;--r-md:16px;--r-lg:24px;--r-xl:32px;
    }
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:var(--font-body);background:var(--midnight);min-height:100vh;display:flex;flex-direction:column;-webkit-font-smoothing:antialiased;overflow-x:hidden;}
    a{text-decoration:none;color:inherit;}

    /* BG */
    .page-bg{position:fixed;inset:0;background:linear-gradient(148deg,var(--midnight) 0%,var(--navy) 55%,var(--navy-mid) 100%);z-index:0;}
    .page-grid{position:fixed;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:44px 44px;z-index:0;}
    .page-glow-1{position:fixed;top:10%;right:-10%;width:600px;height:600px;background:radial-gradient(ellipse,rgba(200,160,88,0.06) 0%,transparent 60%);z-index:0;}
    .page-glow-2{position:fixed;bottom:-5%;left:-5%;width:500px;height:500px;background:radial-gradient(ellipse,rgba(159,18,57,0.06) 0%,transparent 60%);z-index:0;}

    /* NAV */
    .nav{position:relative;z-index:10;padding:20px 32px;display:flex;align-items:center;justify-content:space-between;}
    .nav-logo{display:flex;align-items:center;gap:12px;}
    .nav-logomark{width:34px;height:34px;background:var(--gold);border-radius:7px;display:flex;align-items:center;justify-content:center;}
    .nav-logomark svg{width:19px;height:19px;}
    .nav-logo-text{font-family:var(--font-display);font-size:16px;font-weight:500;color:var(--white);line-height:1.2;}
    .nav-logo-text span{display:block;font-family:var(--font-body);font-size:9px;letter-spacing:2px;text-transform:uppercase;color:rgba(200,160,88,0.55);}
    .admin-pill{display:inline-flex;align-items:center;gap:5px;background:var(--admin-crimson);color:white;font-size:9px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:4px 10px;border-radius:20px;}
    .nav-back{font-size:12.5px;color:rgba(255,255,255,0.4);display:flex;align-items:center;gap:7px;transition:color var(--transition);}
    .nav-back:hover{color:var(--gold-bright);}

    /* MAIN */
    .main{flex:1;display:flex;align-items:center;justify-content:center;padding:24px 32px 48px;position:relative;z-index:5;}
    .login-wrapper{display:grid;grid-template-columns:1fr 460px;gap:0;background:rgba(13,24,41,0.7);border:1px solid rgba(255,255,255,0.08);border-radius:var(--r-xl);overflow:hidden;box-shadow:var(--shadow-lg);backdrop-filter:blur(20px);max-width:900px;width:100%;animation:slideUp 0.7s var(--ease) forwards;}
    @keyframes slideUp{from{opacity:0;transform:translateY(28px);}to{opacity:1;transform:translateY(0);}}

    /* LEFT PANEL */
    .panel-left{background:var(--navy-mid);padding:56px 44px;display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden;}
    .panel-left::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.025) 1px,transparent 1px);background-size:32px 32px;}
    .panel-left::after{content:'';position:absolute;top:-60px;right:-60px;width:300px;height:300px;background:radial-gradient(ellipse,rgba(200,160,88,0.09) 0%,transparent 65%);}
    .panel-left-content{position:relative;z-index:1;}
    .panel-seal{width:56px;height:56px;background:var(--admin-crimson);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;color:white;margin-bottom:32px;}
    .panel-title{font-family:var(--font-display);font-size:36px;font-weight:500;color:var(--white);line-height:1.15;margin-bottom:14px;}
    .panel-title em{font-style:italic;color:var(--gold-bright);}
    .panel-sub{font-size:14px;font-weight:300;color:rgba(255,255,255,0.45);line-height:1.8;margin-bottom:40px;}
    .panel-features{display:flex;flex-direction:column;gap:16px;}
    .panel-feature{display:flex;align-items:center;gap:14px;}
    .panel-feature-dot{width:8px;height:8px;border-radius:50%;background:var(--gold);flex-shrink:0;}
    .panel-feature span{font-size:13px;font-weight:300;color:rgba(255,255,255,0.5);}
    .panel-left-footer{position:relative;z-index:1;}
    .panel-left-footer p{font-size:11.5px;color:rgba(255,255,255,0.2);}
    .panel-left-footer a{color:rgba(200,160,88,0.6);transition:color var(--transition);}
    .panel-left-footer a:hover{color:var(--gold-bright);}

    /* RIGHT PANEL */
    .panel-right{padding:48px 44px;background:rgba(8,14,28,0.5);}
    .form-eyebrow{font-size:10px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:var(--gold);display:flex;align-items:center;gap:10px;margin-bottom:8px;}
    .form-eyebrow::before{content:'';width:20px;height:1px;background:var(--gold);}
    .form-title{font-family:var(--font-display);font-size:30px;font-weight:500;color:var(--white);margin-bottom:6px;}
    .form-sub{font-size:13.5px;font-weight:300;color:rgba(255,255,255,0.38);margin-bottom:36px;}

    /* ALERT */
    .alert{padding:14px 16px;border-radius:var(--r-sm);margin-bottom:24px;font-size:13.5px;font-weight:400;display:flex;align-items:flex-start;gap:10px;border-left:3px solid;}
    .alert i{margin-top:2px;flex-shrink:0;}
    .alert-danger{background:rgba(220,38,38,0.1);color:#FCA5A5;border-color:#DC2626;}
    .alert-info{background:rgba(200,160,88,0.08);color:rgba(240,217,168,0.8);border-color:rgba(200,160,88,0.4);font-size:12.5px;}

    /* FORM */
    .form-group{margin-bottom:22px;}
    .form-label{display:block;font-size:12.5px;font-weight:500;color:rgba(255,255,255,0.55);margin-bottom:8px;letter-spacing:0.3px;}
    .input-wrap{position:relative;}
    .input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,0.25);font-size:14px;pointer-events:none;}
    .form-control{width:100%;padding:13px 14px 13px 42px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:var(--r-sm);font-family:var(--font-body);font-size:14px;color:var(--white);transition:var(--transition);}
    .form-control::placeholder{color:rgba(255,255,255,0.2);}
    .form-control:focus{outline:none;border-color:rgba(200,160,88,0.4);background:rgba(255,255,255,0.07);box-shadow:0 0 0 3px rgba(200,160,88,0.08);}
    .password-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.25);cursor:pointer;font-size:14px;transition:color var(--transition);}
    .password-toggle:hover{color:var(--gold-bright);}
    .form-row-inline{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;}
    .remember-label{display:flex;align-items:center;gap:8px;font-size:13px;color:rgba(255,255,255,0.4);cursor:pointer;}
    .remember-label input[type="checkbox"]{accent-color:var(--gold);width:14px;height:14px;}
    .forgot-link{font-size:13px;color:rgba(200,160,88,0.7);transition:color var(--transition);}
    .forgot-link:hover{color:var(--gold-bright);}
    .btn-submit{width:100%;padding:14px;background:var(--gold);color:var(--midnight);font-family:var(--font-body);font-size:14px;font-weight:600;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:24px;}
    .btn-submit:hover{background:var(--gold-bright);transform:translateY(-2px);}
    .btn-submit:active{transform:translateY(0);}
    .divider{display:flex;align-items:center;gap:14px;margin-bottom:22px;}
    .divider hr{flex:1;border:none;height:1px;background:rgba(255,255,255,0.07);}
    .divider span{font-size:11.5px;color:rgba(255,255,255,0.2);}
    .register-row{text-align:center;font-size:13px;color:rgba(255,255,255,0.35);}
    .register-row a{color:var(--gold-bright);font-weight:500;display:inline-flex;align-items:center;gap:5px;transition:gap var(--transition);}
    .register-row a:hover{gap:8px;}
    .scholar-link{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:14px;font-size:12px;color:rgba(255,255,255,0.25);padding-top:16px;border-top:1px solid rgba(255,255,255,0.06);}
    .scholar-link a{color:rgba(200,160,88,0.5);transition:color var(--transition);}
    .scholar-link a:hover{color:var(--gold-bright);}

    @media(max-width:768px){.login-wrapper{grid-template-columns:1fr}.panel-left{display:none}.main{padding:16px 16px 40px}}
    @media(max-width:480px){.panel-right{padding:36px 28px}}
  

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
  <link rel="stylesheet" href="/mobile-fixes.css?v=20260507">
</head>
<body>
<div class="page-bg"></div><div class="page-grid"></div>
<div class="page-glow-1"></div><div class="page-glow-2"></div>

<nav class="nav">
  <a href="index.php" class="nav-logo">
    <div class="nav-logomark"><svg viewBox="0 0 22 22" fill="none"><path d="M4 18L11 4L18 18" stroke="#080E1C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 13H15" stroke="#080E1C" stroke-width="1.8" stroke-linecap="round"/></svg></div>
    <div class="nav-logo-text">Bold Footprint<span>Initiatives</span></div>
  </a>
  <div style="display:flex;align-items:center;gap:20px;">
    <span class="admin-pill"><i class="fas fa-shield-alt" style="font-size:8px;"></i> Admin Portal</span>
    <a href="/index.html" class="nav-back"><i class="fas fa-arrow-left" style="font-size:10px;"></i> Main Site</a>
  </div>
</nav>

<main class="main">
  <div class="login-wrapper">

    <!-- LEFT PANEL -->
    <div class="panel-left">
      <div class="panel-left-content">
        <div class="panel-seal"><i class="fas fa-shield-alt"></i></div>
        <h2 class="panel-title">Admin<br><em>Command Centre.</em></h2>
        <p class="panel-sub">Secure access to the Bold Footprint Initiatives management portal. Authorised administrators only.</p>
        <div class="panel-features">
          <div class="panel-feature"><div class="panel-feature-dot"></div><span>Scholar profile management</span></div>
          <div class="panel-feature"><div class="panel-feature-dot"></div><span>Application review workflow</span></div>
          <div class="panel-feature"><div class="panel-feature-dot"></div><span>Document verification</span></div>
          <div class="panel-feature"><div class="panel-feature-dot"></div><span>Programme analytics & reports</span></div>
          <div class="panel-feature"><div class="panel-feature-dot"></div><span>Notifications & activity logs</span></div>
        </div>
      </div>
      <div class="panel-left-footer">
        <p>Need help? <a href="mailto:info@bfinitiatives.com">Contact support</a></p>
      </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="panel-right">
      <div class="form-eyebrow">Secure Access</div>
      <h1 class="form-title">Sign in</h1>
      <p class="form-sub">Welcome back. Enter your admin credentials below.</p>

      <?php if ($error): ?>
      <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i><span><?php echo htmlspecialchars($error); ?></span></div>
      <?php endif; ?>

      <div class="alert alert-info"><i class="fas fa-info-circle"></i><span>New administrators with an authorised email will be redirected to account setup on first sign-in.</span></div>

      <form method="POST" action="admin-login.php">
        <?php echo bfi_csrf_field(); ?>
        <div class="form-group">
          <label for="email" class="form-label">Email Address</label>
          <div class="input-wrap">
            <i class="fas fa-envelope input-icon"></i>
            <input type="email" id="email" name="email" class="form-control" placeholder="admin@bfinitiatives.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
          </div>
        </div>
        <div class="form-group">
          <label for="password" class="form-label">Password</label>
          <div class="input-wrap">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" id="password" name="password" class="form-control" placeholder="••••••••••" required>
            <button type="button" class="password-toggle" id="togglePwd"><i class="fas fa-eye"></i></button>
          </div>
        </div>
        <div class="form-row-inline">
          <label class="remember-label">
            <input type="checkbox" name="remember_me"> Remember me
          </label>
          <a href="forgot-password.php" class="forgot-link">Forgot password?</a>
        </div>
        <button type="submit" class="btn-submit"><i class="fas fa-shield-alt"></i> Access Dashboard</button>
      </form>

      <div class="divider"><hr><span>or</span><hr></div>

      <div class="register-row">
        New administrator? <a href="admin-register.php"><i class="fas fa-user-plus" style="font-size:11px;"></i> Create account <i class="fas fa-arrow-right" style="font-size:10px;"></i></a>
      </div>
      <div class="scholar-link">
        <i class="fas fa-user-graduate" style="font-size:11px;"></i>
        Not an admin? <a href="/scholar-portal/login.php">Go to Scholar Login</a>
      </div>
    </div>
  </div>
</main>

<script>
  const pwd = document.getElementById('password');
  document.getElementById('togglePwd').addEventListener('click', function() {
    const isText = pwd.type === 'text';
    pwd.type = isText ? 'password' : 'text';
    this.innerHTML = isText ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
  });
</script>
</body>
</html>