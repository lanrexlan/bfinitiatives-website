<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

define('INCLUDES_PATH', dirname(__FILE__) . '/includes/');
require_once INCLUDES_PATH . 'config.php';
require_once INCLUDES_PATH . 'db.php';
require_once INCLUDES_PATH . 'functions.php';
require_once INCLUDES_PATH . 'csrf.php';

$error = null;
$success = null;

try {
    $database = new Database();
    $conn = $database->getConnection();
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Connection failed. Please try again later.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!bfi_validate_csrf_post()) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
    $first_name = sanitize_input($_POST['first_name'] ?? '');
    $last_name = sanitize_input($_POST['last_name'] ?? '');
    $email = strtolower(sanitize_input($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            $conn->beginTransaction();
            $auth_check_stmt = $conn->prepare("SELECT id, email, is_used, verification_token FROM admin_authorized_emails WHERE LOWER(email) = LOWER(:email) FOR UPDATE");
            $auth_check_stmt->execute(['email' => $email]);
            $authorized = $auth_check_stmt->fetch(PDO::FETCH_ASSOC);
            $admin_check_stmt = $conn->prepare("SELECT id, email, is_active FROM admins WHERE LOWER(email) = LOWER(:email)");
            $admin_check_stmt->execute(['email' => $email]);
            $existing_admin = $admin_check_stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing_admin) {
                $conn->rollBack(); $error = "This email is already registered as an admin.";
            } elseif (!$authorized) {
                $conn->rollBack(); $error = "This email is not authorised for admin registration.";
            } elseif ($authorized['is_used']) {
                $conn->rollBack(); $error = "This email authorisation has already been used.";
            } else {
                $verification_token = bin2hex(random_bytes(32));
                $stmt = $conn->prepare("INSERT INTO admins (email, password, first_name, last_name, role, is_active, verification_token, token_expiry, created_at, updated_at) VALUES (:email, :password, :first_name, :last_name, 'admin', FALSE, :verification_token, CURRENT_TIMESTAMP + INTERVAL '24 hours', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING id");
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt->execute([':email' => $email, ':password' => $hashed_password, ':first_name' => $first_name, ':last_name' => $last_name, ':verification_token' => $verification_token]);
                $admin_id = $stmt->fetchColumn();
                if ($admin_id) {
                    $mark_used_stmt = $conn->prepare("UPDATE admin_authorized_emails SET is_used = TRUE, verification_token = :token, token_expiry = CURRENT_TIMESTAMP + INTERVAL '24 hours' WHERE LOWER(email) = LOWER(:email)");
                    $mark_used_stmt->execute(['email' => $email, 'token' => $verification_token]);
                    $conn->commit();
                    require_once INCLUDES_PATH . 'mail_config.php';
                    try {
                        if (function_exists('sendAdminVerificationEmail')) {
                            $emailSent = sendAdminVerificationEmail($email, $first_name, $verification_token);
                            $success = $emailSent ? "Registration successful! Please check your email to verify your account." : "Registration successful! However, there was an issue sending the verification email. Please contact support.";
                        } else {
                            $success = "Registration successful! Please contact support to verify your account.";
                        }
                    } catch (Exception $e) {
                        $success = "Registration successful! However, there was an issue sending the verification email. Please contact support.";
                    }
                } else {
                    $conn->rollBack(); $error = "Failed to create admin account.";
                }
            }
        } catch (PDOException $e) {
            $conn->rollBack(); error_log("DB error during registration: " . $e->getMessage()); $error = "An error occurred. Please try again.";
        } catch (Exception $e) {
            $conn->rollBack(); $error = "An error occurred. Please try again.";
        }
    }
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Registration | Bold Footprint Initiatives</title>
  <link rel="icon" type="image/png" sizes="32x32" href="/Images/BFI_Logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <style>
    :root{
      --midnight:#080E1C;--navy:#0D1829;--navy-mid:#132038;--navy-light:#1C2F52;
      --gold:#C8A058;--gold-bright:#E0B96C;--cream:#FAF6EF;--white:#FFFFFF;
      --admin-crimson:#9F1239;--admin-crimson-light:#BE123C;--admin-crimson-pale:rgba(159,18,57,0.1);
      --success:#059669;--danger:#DC2626;
      --font-display:'Cormorant Garamond',Georgia,serif;
      --font-body:'Outfit',-apple-system,sans-serif;
      --ease:cubic-bezier(0.25,0.46,0.45,0.94);
      --transition:0.35s var(--ease);
      --shadow-lg:0 20px 60px rgba(8,14,28,0.22);
      --r-sm:8px;--r-md:16px;--r-lg:24px;--r-xl:32px;
    }
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:var(--font-body);background:var(--midnight);min-height:100vh;display:flex;flex-direction:column;-webkit-font-smoothing:antialiased;overflow-x:hidden;}
    a{text-decoration:none;color:inherit;}
    .page-bg{position:fixed;inset:0;background:linear-gradient(148deg,var(--midnight) 0%,var(--navy) 55%,var(--navy-mid) 100%);z-index:0;}
    .page-grid{position:fixed;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:44px 44px;z-index:0;}
    .page-glow-1{position:fixed;top:0;right:-5%;width:600px;height:600px;background:radial-gradient(ellipse,rgba(200,160,88,0.06) 0%,transparent 60%);z-index:0;}
    .page-glow-2{position:fixed;bottom:-5%;left:-5%;width:500px;height:500px;background:radial-gradient(ellipse,rgba(159,18,57,0.06) 0%,transparent 60%);z-index:0;}

    .nav{position:relative;z-index:10;padding:20px 32px;display:flex;align-items:center;justify-content:space-between;}
    .nav-logo{display:flex;align-items:center;gap:12px;}
    .nav-logomark{width:34px;height:34px;background:var(--gold);border-radius:7px;display:flex;align-items:center;justify-content:center;}
    .nav-logomark svg{width:19px;height:19px;}
    .nav-logo-text{font-family:var(--font-display);font-size:16px;font-weight:500;color:var(--white);line-height:1.2;}
    .nav-logo-text span{display:block;font-family:var(--font-body);font-size:9px;letter-spacing:2px;text-transform:uppercase;color:rgba(200,160,88,0.55);}
    .admin-pill{display:inline-flex;align-items:center;gap:5px;background:var(--admin-crimson);color:white;font-size:9px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:4px 10px;border-radius:20px;}
    .nav-back{font-size:12.5px;color:rgba(255,255,255,0.4);display:flex;align-items:center;gap:7px;transition:color var(--transition);}
    .nav-back:hover{color:var(--gold-bright);}

    .main{flex:1;display:flex;align-items:center;justify-content:center;padding:24px 32px 48px;position:relative;z-index:5;}
    .reg-wrapper{display:grid;grid-template-columns:1fr 520px;gap:0;background:rgba(13,24,41,0.7);border:1px solid rgba(255,255,255,0.08);border-radius:var(--r-xl);overflow:hidden;box-shadow:var(--shadow-lg);backdrop-filter:blur(20px);max-width:980px;width:100%;animation:slideUp 0.7s var(--ease) forwards;}
    @keyframes slideUp{from{opacity:0;transform:translateY(28px);}to{opacity:1;transform:translateY(0);}}

    /* LEFT */
    .panel-left{background:var(--navy-mid);padding:52px 40px;display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden;}
    .panel-left::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.025) 1px,transparent 1px);background-size:32px 32px;}
    .panel-left::after{content:'';position:absolute;bottom:-60px;left:-60px;width:280px;height:280px;background:radial-gradient(ellipse,rgba(159,18,57,0.1) 0%,transparent 65%);}
    .panel-left-content{position:relative;z-index:1;}
    .panel-seal{width:56px;height:56px;background:var(--admin-crimson);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;color:white;margin-bottom:30px;}
    .panel-title{font-family:var(--font-display);font-size:32px;font-weight:500;color:var(--white);line-height:1.15;margin-bottom:14px;}
    .panel-title em{font-style:italic;color:var(--gold-bright);}
    .panel-sub{font-size:13.5px;font-weight:300;color:rgba(255,255,255,0.42);line-height:1.8;margin-bottom:36px;}
    .panel-steps{display:flex;flex-direction:column;gap:0;}
    .step-item{display:flex;gap:16px;position:relative;}
    .step-item:not(:last-child)::after{content:'';position:absolute;left:15px;top:34px;width:1px;height:calc(100% - 8px);background:rgba(255,255,255,0.08);}
    .step-num{width:32px;height:32px;border-radius:50%;background:rgba(200,160,88,0.12);border:1px solid rgba(200,160,88,0.25);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;color:var(--gold-bright);flex-shrink:0;z-index:1;}
    .step-content{padding:4px 0 24px;}
    .step-title{font-size:13.5px;font-weight:500;color:rgba(255,255,255,0.8);margin-bottom:2px;}
    .step-desc{font-size:12px;font-weight:300;color:rgba(255,255,255,0.35);}
    .panel-left-footer{position:relative;z-index:1;font-size:11.5px;color:rgba(255,255,255,0.2);}
    .panel-left-footer a{color:rgba(200,160,88,0.5);transition:color var(--transition);}
    .panel-left-footer a:hover{color:var(--gold-bright);}

    /* RIGHT */
    .panel-right{padding:44px 44px;background:rgba(8,14,28,0.5);overflow-y:auto;}
    .form-eyebrow{font-size:10px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:var(--gold);display:flex;align-items:center;gap:10px;margin-bottom:8px;}
    .form-eyebrow::before{content:'';width:20px;height:1px;background:var(--gold);}
    .form-title{font-family:var(--font-display);font-size:28px;font-weight:500;color:var(--white);margin-bottom:6px;}
    .form-sub{font-size:13px;font-weight:300;color:rgba(255,255,255,0.38);margin-bottom:32px;}
    .alert{padding:13px 16px;border-radius:var(--r-sm);margin-bottom:22px;font-size:13px;font-weight:400;display:flex;align-items:flex-start;gap:10px;border-left:3px solid;}
    .alert i{margin-top:2px;flex-shrink:0;}
    .alert-danger{background:rgba(220,38,38,0.1);color:#FCA5A5;border-color:#DC2626;}
    .alert-success{background:rgba(5,150,105,0.1);color:#6EE7B7;border-color:#059669;}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
    .form-group{margin-bottom:20px;}
    .form-label{display:block;font-size:12px;font-weight:500;color:rgba(255,255,255,0.5);margin-bottom:7px;letter-spacing:0.3px;}
    .input-wrap{position:relative;}
    .input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,0.22);font-size:13px;pointer-events:none;}
    .form-control{width:100%;padding:12px 14px 12px 42px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13.5px;color:var(--white);transition:var(--transition);}
    .form-control::placeholder{color:rgba(255,255,255,0.18);}
    .form-control:focus{outline:none;border-color:rgba(200,160,88,0.4);background:rgba(255,255,255,0.07);box-shadow:0 0 0 3px rgba(200,160,88,0.07);}
    .form-hint{font-size:11px;color:rgba(255,255,255,0.22);margin-top:5px;}
    .password-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.22);cursor:pointer;font-size:13px;transition:color var(--transition);}
    .password-toggle:hover{color:var(--gold-bright);}
    .strength-bar-wrap{height:3px;background:rgba(255,255,255,0.07);border-radius:2px;overflow:hidden;margin-top:7px;}
    .strength-bar{height:100%;width:0;border-radius:2px;transition:all 0.3s;}
    .strength-text{font-size:11px;font-weight:500;margin-top:4px;height:14px;}
    .btn-submit{width:100%;padding:14px;background:var(--admin-crimson);color:white;font-family:var(--font-body);font-size:14px;font-weight:600;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:20px;margin-top:8px;}
    .btn-submit:hover{background:var(--admin-crimson-light);transform:translateY(-2px);}
    .login-row{text-align:center;font-size:13px;color:rgba(255,255,255,0.3);padding-top:16px;border-top:1px solid rgba(255,255,255,0.06);}
    .login-row a{color:var(--gold-bright);font-weight:500;display:inline-flex;align-items:center;gap:5px;transition:gap var(--transition);}
    .login-row a:hover{gap:8px;}

    /* SUCCESS STATE */
    .success-state{text-align:center;padding:20px 0;}
    .success-icon{width:72px;height:72px;background:rgba(5,150,105,0.12);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;color:#34D399;margin:0 auto 24px;}
    .success-state h2{font-family:var(--font-display);font-size:28px;font-weight:500;color:var(--white);margin-bottom:12px;}
    .success-state p{font-size:14px;font-weight:300;color:rgba(255,255,255,0.45);line-height:1.8;margin-bottom:32px;}
    .btn-signin{display:inline-flex;align-items:center;gap:10px;padding:13px 28px;background:var(--gold);color:var(--midnight);font-family:var(--font-body);font-size:14px;font-weight:600;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-signin:hover{background:var(--gold-bright);}

    @media(max-width:900px){.reg-wrapper{grid-template-columns:1fr}.panel-left{display:none}.main{padding:16px 16px 40px}}
    @media(max-width:480px){.panel-right{padding:36px 24px}.form-row{grid-template-columns:1fr}}
  </style>
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
    <a href="admin-login.php" class="nav-back"><i class="fas fa-arrow-left" style="font-size:10px;"></i> Sign In</a>
  </div>
</nav>

<main class="main">
  <div class="reg-wrapper">

    <!-- LEFT PANEL -->
    <div class="panel-left">
      <div class="panel-left-content">
        <div class="panel-seal"><i class="fas fa-user-shield"></i></div>
        <h2 class="panel-title">Join the<br><em>admin team.</em></h2>
        <p class="panel-sub">Create your secure administrator account with a pre-authorised BFI email address.</p>
        <div class="panel-steps">
          <div class="step-item">
            <div class="step-num">1</div>
            <div class="step-content"><div class="step-title">Fill in your details</div><div class="step-desc">Provide your name, authorised email, and a strong password.</div></div>
          </div>
          <div class="step-item">
            <div class="step-num">2</div>
            <div class="step-content"><div class="step-title">Email verification</div><div class="step-desc">Check your inbox and verify your account within 24 hours.</div></div>
          </div>
          <div class="step-item">
            <div class="step-num">3</div>
            <div class="step-content"><div class="step-title">Access the dashboard</div><div class="step-desc">Once verified, sign in to begin managing BFI scholars.</div></div>
          </div>
        </div>
      </div>
      <div class="panel-left-footer">For support, contact <a href="mailto:info@bfinitiatives.com">info@bfinitiatives.com</a></div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="panel-right">
      <div class="form-eyebrow">Create Account</div>
      <h1 class="form-title">Register</h1>
      <p class="form-sub">Complete the form below with your authorised email address.</p>

      <?php if ($error): ?>
      <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i><span><?php echo htmlspecialchars($error); ?></span></div>
      <?php endif; ?>

      <?php if ($success): ?>
      <div class="success-state">
        <div class="success-icon"><i class="fas fa-check"></i></div>
        <h2>Account Created</h2>
        <p><?php echo htmlspecialchars($success); ?></p>
        <a href="admin-login.php" class="btn-signin"><i class="fas fa-sign-in-alt"></i> Sign In Now</a>
      </div>
      <?php else: ?>

      <form method="POST" action="admin-register.php" id="regForm">
        <?php echo bfi_csrf_field(); ?>
        <div class="form-row">
          <div class="form-group">
            <label for="first_name" class="form-label">First Name</label>
            <div class="input-wrap">
              <i class="fas fa-user input-icon"></i>
              <input type="text" id="first_name" name="first_name" class="form-control" placeholder="e.g. Habeeb" required>
            </div>
          </div>
          <div class="form-group">
            <label for="last_name" class="form-label">Last Name</label>
            <div class="input-wrap">
              <i class="fas fa-user input-icon"></i>
              <input type="text" id="last_name" name="last_name" class="form-control" placeholder="e.g. Adegoke" required>
            </div>
          </div>
        </div>
        <div class="form-group">
          <label for="email" class="form-label">Authorised Email Address</label>
          <div class="input-wrap">
            <i class="fas fa-envelope input-icon"></i>
            <input type="email" id="email" name="email" class="form-control" placeholder="admin@bfinitiatives.com" value="<?php echo isset($_SESSION['admin_email']) ? htmlspecialchars($_SESSION['admin_email']) : ''; ?>" required>
          </div>
          <p class="form-hint"><i class="fas fa-info-circle" style="margin-right:4px;"></i>Must match your pre-authorised BFI organisation email.</p>
        </div>
        <div class="form-group">
          <label for="password" class="form-label">Password</label>
          <div class="input-wrap">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" id="password" name="password" class="form-control" placeholder="Create a strong password" required>
            <button type="button" class="password-toggle" id="togglePwd"><i class="fas fa-eye"></i></button>
          </div>
          <div class="strength-bar-wrap"><div class="strength-bar" id="strengthBar"></div></div>
          <div class="strength-text" id="strengthText"></div>
        </div>
        <div class="form-group">
          <label for="confirm_password" class="form-label">Confirm Password</label>
          <div class="input-wrap">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Repeat your password" required>
            <button type="button" class="password-toggle" id="toggleConfirmPwd"><i class="fas fa-eye"></i></button>
          </div>
        </div>
        <button type="submit" class="btn-submit"><i class="fas fa-check-circle"></i> Complete Registration</button>
      </form>

      <div class="login-row">
        Already have an account? <a href="admin-login.php"><i class="fas fa-sign-in-alt" style="font-size:11px;"></i> Sign in <i class="fas fa-arrow-right" style="font-size:10px;"></i></a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<script>
  // Password visibility toggles
  function makeToggle(btnId, inputId) {
    const btn = document.getElementById(btnId);
    const inp = document.getElementById(inputId);
    if (!btn || !inp) return;
    btn.addEventListener('click', () => {
      const isText = inp.type === 'text';
      inp.type = isText ? 'password' : 'text';
      btn.innerHTML = isText ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
    });
  }
  makeToggle('togglePwd', 'password');
  makeToggle('toggleConfirmPwd', 'confirm_password');

  // Password strength
  const pwdInput = document.getElementById('password');
  const bar = document.getElementById('strengthBar');
  const txt = document.getElementById('strengthText');
  const levels = [
    {w:'20%',c:'#DC2626',t:'Very weak'},
    {w:'40%',c:'#F97316',t:'Weak'},
    {w:'60%',c:'#F59E0B',t:'Fair'},
    {w:'80%',c:'#84CC16',t:'Strong'},
    {w:'100%',c:'#059669',t:'Very strong'}
  ];
  pwdInput.addEventListener('input', () => {
    const v = pwdInput.value;
    if (!v.length) { bar.style.width = '0'; txt.textContent = ''; return; }
    let s = 0;
    if (v.match(/[a-z]/)) s++;
    if (v.match(/[A-Z]/)) s++;
    if (v.match(/[0-9]/)) s++;
    if (v.match(/[^a-zA-Z0-9]/)) s++;
    if (v.length >= 8) s++;
    const l = levels[Math.min(s - 1, 4)];
    bar.style.width = l.w; bar.style.background = l.c;
    txt.textContent = l.t; txt.style.color = l.c;
  });

  // Form validation
  document.getElementById('regForm')?.addEventListener('submit', function(e) {
    const p = document.getElementById('password').value;
    const cp = document.getElementById('confirm_password').value;
    if (p !== cp) { e.preventDefault(); alert('Passwords do not match.'); }
  });
</script>
</body>
</html>