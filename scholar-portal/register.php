<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('INCLUDES_PATH', dirname(__FILE__) . '/includes/');
require_once INCLUDES_PATH . 'config.php';
require_once INCLUDES_PATH . 'db.php';
require_once INCLUDES_PATH . 'functions.php';

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
    $first_name = sanitize_input($_POST['first_name'] ?? '');
    $last_name  = sanitize_input($_POST['last_name'] ?? '');
    $email      = sanitize_input($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $program    = sanitize_input($_POST['program'] ?? '');

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($program)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            $auth_check_stmt = $conn->prepare("SELECT program FROM authorized_emails WHERE email = :email AND used = false");
            $auth_check_stmt->execute(['email' => $email]);
            $authorized = $auth_check_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$authorized) {
                $error = "This email is not authorized for registration. Only BFI Scholars can register.";
            } elseif ($authorized['program'] !== $program) {
                $error = "Please select the scholarship program associated with your email.";
            } else {
                $check_stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
                $check_stmt->execute(['email' => $email]);
                if ($check_stmt->fetchColumn()) {
                    $error = "This email address is already registered.";
                } else {
                    $conn->beginTransaction();
                    try {
                        $stmt = $conn->prepare("
                            INSERT INTO users (first_name, last_name, email, password, program, role_id, status, is_active, is_verified, created_at, updated_at)
                            VALUES (:first_name, :last_name, :email, :password, :program, :role_id, :status, TRUE, FALSE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                            RETURNING id
                        ");
                        $stmt->execute([
                            ':first_name' => $first_name, ':last_name' => $last_name,
                            ':email' => $email, ':password' => password_hash($password, PASSWORD_DEFAULT),
                            ':program' => $program, ':role_id' => 2, ':status' => 'active'
                        ]);
                        $user_id = $stmt->fetchColumn();

                        if ($user_id) {
                            $mark_used = $conn->prepare("UPDATE authorized_emails SET used = true WHERE email = :email");
                            $mark_used->execute(['email' => $email]);

                            $verificationToken = bin2hex(random_bytes(32));
                            $token_stmt = $conn->prepare("UPDATE users SET verification_token = :token WHERE id = :user_id");
                            $token_stmt->execute([':token' => $verificationToken, ':user_id' => $user_id]);

                            $conn->commit();

                            require_once INCLUDES_PATH . 'mail_config.php';
                            if (sendVerificationEmail($email, $first_name, $verificationToken)) {
                                $success = "Registration successful! Please check your email to verify your account.";
                            } else {
                                $success = "Registration successful! However, there was an issue sending the verification email. Please contact support.";
                            }
                        } else {
                            throw new Exception("Failed to create user account.");
                        }
                    } catch (Exception $e) {
                        $conn->rollBack();
                        error_log("Registration error: " . $e->getMessage());
                        $error = "Registration failed. Please try again.";
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("DB error: " . $e->getMessage());
            $error = "An error occurred. Please try again.";
        }
    }
}

$programs = [
    'primary'   => ['label' => 'Primary School',    'icon' => 'fa-school',         'desc' => 'For primary school scholarship recipients'],
    'secondary' => ['label' => 'Secondary School',  'icon' => 'fa-book-open',      'desc' => 'For secondary school scholarship recipients'],
    'graduate'  => ['label' => 'Graduate Studies',  'icon' => 'fa-graduation-cap', 'desc' => 'For graduate & postgraduate scholarship recipients'],
];
$selected_program = $_POST['program'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Scholar Registration | BFI Scholar Portal</title>
  <link rel="icon" type="image/png" href="/Images/BFI_Logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
      --r-sm:8px;--r-md:14px;--r-lg:20px;--r-xl:28px;
    }
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    html{font-size:16px;}
    body{font-family:var(--font-body);background:var(--cream);color:var(--text-primary);line-height:1.6;min-height:100vh;-webkit-font-smoothing:antialiased;}
    a{text-decoration:none;color:inherit;}

    /* LAYOUT */
    .auth-wrap{display:flex;min-height:100vh;}

    /* LEFT PANEL */
    .auth-panel{width:420px;flex-shrink:0;background:var(--navy);position:relative;overflow:hidden;display:flex;flex-direction:column;padding:48px 40px;}
    .auth-panel::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.025) 1px,transparent 1px);background-size:28px 28px;}
    .auth-panel::after{content:'';position:absolute;top:-80px;right:-80px;width:320px;height:320px;background:radial-gradient(circle,rgba(200,160,88,0.12) 0%,transparent 65%);pointer-events:none;}
    .panel-content{position:relative;z-index:1;display:flex;flex-direction:column;height:100%;}
    .panel-logo{display:flex;align-items:center;gap:12px;margin-bottom:56px;}
    .panel-logomark{width:36px;height:36px;background:var(--gold);border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .panel-logomark svg{width:22px;height:22px;}
    .panel-logo-text{font-family:var(--font-display);font-size:15px;font-weight:500;color:var(--white);line-height:1.2;}
    .panel-logo-text span{display:block;font-family:var(--font-body);font-size:9px;letter-spacing:2px;text-transform:uppercase;color:rgba(200,160,88,0.6);}
    .panel-eyebrow{font-size:10px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:rgba(200,160,88,0.7);margin-bottom:12px;}
    .panel-title{font-family:var(--font-display);font-size:clamp(28px,3vw,38px);font-weight:500;color:var(--white);line-height:1.15;margin-bottom:16px;}
    .panel-title em{font-style:italic;color:var(--gold-bright);}
    .panel-sub{font-size:13.5px;font-weight:300;color:rgba(255,255,255,0.5);line-height:1.7;margin-bottom:40px;}
    .panel-benefits{display:flex;flex-direction:column;gap:20px;margin-top:auto;}
    .benefit{display:flex;align-items:flex-start;gap:14px;}
    .benefit-icon{width:36px;height:36px;border-radius:var(--r-sm);background:rgba(200,160,88,0.12);border:1px solid rgba(200,160,88,0.2);display:flex;align-items:center;justify-content:center;font-size:14px;color:var(--gold-bright);flex-shrink:0;margin-top:2px;}
    .benefit-title{font-size:13.5px;font-weight:500;color:var(--white);margin-bottom:2px;}
    .benefit-desc{font-size:12px;color:rgba(255,255,255,0.4);}
    .panel-footer{margin-top:40px;padding-top:24px;border-top:1px solid rgba(255,255,255,0.06);}
    .panel-footer p{font-size:11.5px;color:rgba(255,255,255,0.25);line-height:1.6;}

    /* RIGHT FORM AREA */
    .auth-form-area{flex:1;display:flex;flex-direction:column;overflow-y:auto;}
    .form-topbar{padding:20px 40px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border-light);}
    .topbar-back{display:inline-flex;align-items:center;gap:8px;font-size:13px;color:var(--text-muted);transition:var(--transition);}
    .topbar-back:hover{color:var(--navy);}
    .topbar-login{font-size:13px;color:var(--text-muted);}
    .topbar-login a{color:var(--gold);font-weight:500;}
    .form-scroll{flex:1;padding:48px 40px;max-width:560px;}
    .form-heading{margin-bottom:32px;}
    .form-eyebrow{font-size:10px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px;}
    .form-title{font-family:var(--font-display);font-size:28px;font-weight:500;color:var(--navy);line-height:1.2;}

    /* ALERTS */
    .alert{padding:14px 18px;border-radius:var(--r-md);margin-bottom:24px;font-size:13.5px;display:flex;align-items:flex-start;gap:10px;}
    .alert i{margin-top:2px;flex-shrink:0;}
    .alert-error{background:rgba(239,68,68,0.07);color:#DC2626;border:1px solid rgba(239,68,68,0.15);}
    .alert-success{background:rgba(16,185,129,0.07);color:#059669;border:1px solid rgba(16,185,129,0.15);}

    /* SUCCESS STATE */
    .success-state{text-align:center;padding:40px 20px;}
    .success-icon{width:72px;height:72px;background:rgba(16,185,129,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;color:#059669;margin:0 auto 20px;}
    .success-title{font-family:var(--font-display);font-size:26px;font-weight:500;color:var(--navy);margin-bottom:8px;}
    .success-sub{font-size:13.5px;color:var(--text-muted);line-height:1.7;margin-bottom:24px;}
    .btn-go-login{display:inline-flex;align-items:center;gap:8px;padding:12px 28px;background:var(--gold);color:var(--midnight);font-family:var(--font-body);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-go-login:hover{background:var(--gold-bright);}

    /* FORM ELEMENTS */
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
    .form-group{margin-bottom:20px;}
    .form-label{display:block;font-size:12.5px;font-weight:500;color:var(--text-secondary);margin-bottom:7px;letter-spacing:0.3px;}
    .input-wrap{position:relative;}
    .input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;pointer-events:none;}
    .form-input{width:100%;padding:11px 14px 11px 40px;border:1.5px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13.5px;color:var(--text-primary);background:var(--white);transition:var(--transition);outline:none;}
    .form-input:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,160,88,0.1);}
    .form-input.is-error{border-color:#EF4444;}
    .toggle-vis{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:13px;padding:4px;transition:var(--transition);}
    .toggle-vis:hover{color:var(--navy);}

    /* PROGRAM CARDS */
    .program-label{font-size:12.5px;font-weight:500;color:var(--text-secondary);margin-bottom:10px;letter-spacing:0.3px;}
    .program-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:20px;}
    .program-card{position:relative;cursor:pointer;}
    .program-card input[type="radio"]{position:absolute;opacity:0;width:0;height:0;}
    .program-card-inner{padding:14px 12px;border:1.5px solid var(--border-light);border-radius:var(--r-md);background:var(--white);text-align:center;transition:var(--transition);cursor:pointer;}
    .program-card input:checked + .program-card-inner{border-color:var(--gold);background:rgba(200,160,88,0.05);}
    .program-card-inner:hover{border-color:var(--gold-pale);background:var(--cream);}
    .program-card-icon{font-size:20px;color:var(--text-muted);margin-bottom:8px;transition:var(--transition);}
    .program-card input:checked + .program-card-inner .program-card-icon{color:var(--gold);}
    .program-card-title{font-size:12px;font-weight:500;color:var(--text-secondary);line-height:1.3;transition:var(--transition);}
    .program-card input:checked + .program-card-inner .program-card-title{color:var(--navy);font-weight:600;}
    .program-card-desc{font-size:10.5px;color:var(--text-muted);margin-top:4px;line-height:1.4;}

    /* PASSWORD REQUIREMENTS */
    .pwd-requirements{background:var(--cream);border:1px solid var(--border-light);border-radius:var(--r-sm);padding:12px 16px;margin-top:10px;display:none;}
    .pwd-requirements.show{display:block;}
    .pwd-req-title{font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;}
    .pwd-req-list{display:grid;grid-template-columns:1fr 1fr;gap:4px;}
    .pwd-req{display:flex;align-items:center;gap:7px;font-size:12px;color:var(--text-muted);transition:var(--transition);}
    .pwd-req.met{color:#059669;}
    .pwd-req i{font-size:10px;width:14px;}
    .pwd-req.met i::before{content:'\f00c';}

    /* PASSWORD STRENGTH BAR */
    .pwd-strength-bar{height:3px;background:var(--border-light);border-radius:2px;margin-top:8px;overflow:hidden;}
    .pwd-strength-fill{height:100%;width:0;border-radius:2px;transition:var(--transition);}

    /* SUBMIT BUTTON */
    .btn-submit{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:13px 24px;background:var(--navy);color:var(--white);font-family:var(--font-body);font-size:13.5px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);margin-top:8px;}
    .btn-submit:hover{background:var(--navy-mid);transform:translateY(-1px);}
    .btn-submit:active{transform:none;}

    /* DIVIDER */
    .form-divider{display:flex;align-items:center;gap:12px;margin:20px 0;}
    .form-divider span{font-size:12px;color:var(--text-muted);white-space:nowrap;}
    .form-divider::before,.form-divider::after{content:'';flex:1;height:1px;background:var(--border-light);}

    @media(max-width:900px){.auth-panel{width:340px;padding:36px 28px;}.panel-benefits{display:none;}}
    @media(max-width:700px){
      .auth-wrap{flex-direction:column;}
      .auth-panel{width:100%;padding:28px 24px;}
      .panel-benefits{display:none;}
      .panel-footer{display:none;}
      .form-scroll{padding:32px 24px;max-width:100%;}
      .form-topbar{padding:16px 24px;}
      .form-row{grid-template-columns:1fr;}
      .program-cards{grid-template-columns:1fr;}
      .pwd-req-list{grid-template-columns:1fr;}
    }
  </style>
</head>
<body>
<div class="auth-wrap">

  <!-- LEFT PANEL -->
  <aside class="auth-panel">
    <div class="panel-content">
      <div class="panel-logo">
        <div class="panel-logomark">
          <svg viewBox="0 0 22 22" fill="none"><path d="M4 18L11 4L18 18" stroke="#080E1C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 13H15" stroke="#080E1C" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div class="panel-logo-text">Bold Footprint<span>Scholar Portal</span></div>
      </div>

      <div class="panel-eyebrow">New Scholar Registration</div>
      <h1 class="panel-title">Start your <em>journey</em> to international education.</h1>
      <p class="panel-sub">Create your BFI Scholar account to access mentorship, scholarship tracking, application support, and more — all in one place.</p>

      <div class="panel-benefits">
        <div class="benefit">
          <div class="benefit-icon"><i class="fas fa-route"></i></div>
          <div><div class="benefit-title">Journey Tracker</div><div class="benefit-desc">Monitor every step of your scholarship journey in real-time.</div></div>
        </div>
        <div class="benefit">
          <div class="benefit-icon"><i class="fas fa-users"></i></div>
          <div><div class="benefit-title">Dedicated Mentors</div><div class="benefit-desc">Get paired with a mentor who guides your application process.</div></div>
        </div>
        <div class="benefit">
          <div class="benefit-icon"><i class="fas fa-graduation-cap"></i></div>
          <div><div class="benefit-title">Scholarship Database</div><div class="benefit-desc">Browse curated opportunities matched to your profile.</div></div>
        </div>
        <div class="benefit">
          <div class="benefit-icon"><i class="fas fa-book"></i></div>
          <div><div class="benefit-title">Resource Library</div><div class="benefit-desc">Access templates, guides, and proven application materials.</div></div>
        </div>
      </div>

      <div class="panel-footer">
        <p>Only BFI Scholars with an authorized email address may register. If you believe you should have access, contact us at info@bfinitiatives.com.</p>
      </div>
    </div>
  </aside>

  <!-- RIGHT FORM AREA -->
  <main class="auth-form-area">
    <div class="form-topbar">
      <a href="/" class="topbar-back"><i class="fas fa-arrow-left"></i> Main Site</a>
      <div class="topbar-login">Already registered? <a href="login.php">Sign in</a></div>
    </div>

    <div class="form-scroll">
      <?php if ($success): ?>
        <div class="success-state">
          <div class="success-icon"><i class="fas fa-check"></i></div>
          <div class="success-title">You're all set!</div>
          <p class="success-sub"><?php echo htmlspecialchars($success); ?><br>Check your inbox (and spam folder) to verify your account before signing in.</p>
          <a href="login.php" class="btn-go-login"><i class="fas fa-sign-in-alt"></i> Go to Login</a>
        </div>
      <?php else: ?>
        <div class="form-heading">
          <div class="form-eyebrow">Create your account</div>
          <div class="form-title">Scholar Registration</div>
        </div>

        <?php if ($error): ?>
          <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><span><?php echo htmlspecialchars($error); ?></span></div>
        <?php endif; ?>

        <form method="POST" action="register.php" id="registerForm" novalidate>

          <!-- Name row -->
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="first_name">First Name</label>
              <div class="input-wrap"><i class="fas fa-user input-icon"></i>
                <input type="text" id="first_name" name="first_name" class="form-input" placeholder="Amara" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label" for="last_name">Last Name</label>
              <div class="input-wrap"><i class="fas fa-user input-icon"></i>
                <input type="text" id="last_name" name="last_name" class="form-input" placeholder="Okonkwo" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
              </div>
            </div>
          </div>

          <!-- Email -->
          <div class="form-group">
            <label class="form-label" for="email">Email Address</label>
            <div class="input-wrap"><i class="fas fa-envelope input-icon"></i>
              <input type="email" id="email" name="email" class="form-input" placeholder="you@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
          </div>

          <!-- Program cards -->
          <div class="form-group">
            <div class="program-label">Scholarship Program</div>
            <div class="program-cards">
              <?php foreach ($programs as $key => $prog): ?>
              <label class="program-card">
                <input type="radio" name="program" value="<?php echo $key; ?>" <?php echo ($selected_program === $key) ? 'checked' : ''; ?> required>
                <div class="program-card-inner">
                  <div class="program-card-icon"><i class="fas <?php echo $prog['icon']; ?>"></i></div>
                  <div class="program-card-title"><?php echo $prog['label']; ?></div>
                  <div class="program-card-desc"><?php echo $prog['desc']; ?></div>
                </div>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Password -->
          <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <div class="input-wrap"><i class="fas fa-lock input-icon"></i>
              <input type="password" id="password" name="password" class="form-input" placeholder="Create a strong password" required>
              <button type="button" class="toggle-vis" data-target="password"><i class="fas fa-eye"></i></button>
            </div>
            <div class="pwd-strength-bar"><div class="pwd-strength-fill" id="strengthFill"></div></div>
            <div class="pwd-requirements" id="pwdReqs">
              <div class="pwd-req-title">Password must include</div>
              <div class="pwd-req-list">
                <div class="pwd-req" id="req-length"><i class="fas fa-circle"></i> At least 8 characters</div>
                <div class="pwd-req" id="req-upper"><i class="fas fa-circle"></i> Uppercase letter</div>
                <div class="pwd-req" id="req-lower"><i class="fas fa-circle"></i> Lowercase letter</div>
                <div class="pwd-req" id="req-number"><i class="fas fa-circle"></i> Number</div>
                <div class="pwd-req" id="req-special"><i class="fas fa-circle"></i> Special character</div>
              </div>
            </div>
          </div>

          <!-- Confirm Password -->
          <div class="form-group">
            <label class="form-label" for="confirm_password">Confirm Password</label>
            <div class="input-wrap"><i class="fas fa-lock input-icon"></i>
              <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Repeat your password" required>
              <button type="button" class="toggle-vis" data-target="confirm_password"><i class="fas fa-eye"></i></button>
            </div>
            <div id="matchFeedback" style="font-size:12px;margin-top:5px;display:none;"></div>
          </div>

          <button type="submit" class="btn-submit"><i class="fas fa-user-plus"></i> Create My Scholar Account</button>

          <div class="form-divider"><span>Already have an account?</span></div>
          <a href="login.php" style="display:flex;align-items:center;justify-content:center;gap:8px;padding:11px 24px;border:1.5px solid var(--border-light);border-radius:var(--r-sm);font-size:13px;color:var(--text-secondary);transition:var(--transition);" onmouseover="this.style.borderColor='var(--gold)';this.style.color='var(--navy)'" onmouseout="this.style.borderColor='var(--border-light)';this.style.color='var(--text-secondary)'">
            <i class="fas fa-sign-in-alt"></i> Sign in to existing account
          </a>

        </form>
      <?php endif; ?>
    </div>
  </main>
</div>

<script>
  // Toggle password visibility
  document.querySelectorAll('.toggle-vis').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.target);
      const icon = btn.querySelector('i');
      if (input.type === 'password') { input.type = 'text'; icon.classList.replace('fa-eye','fa-eye-slash'); }
      else { input.type = 'password'; icon.classList.replace('fa-eye-slash','fa-eye'); }
    });
  });

  // Password requirements
  const pwdInput = document.getElementById('password');
  const pwdReqs = document.getElementById('pwdReqs');
  const strengthFill = document.getElementById('strengthFill');
  const reqs = {
    'req-length':  v => v.length >= 8,
    'req-upper':   v => /[A-Z]/.test(v),
    'req-lower':   v => /[a-z]/.test(v),
    'req-number':  v => /[0-9]/.test(v),
    'req-special': v => /[^A-Za-z0-9]/.test(v),
  };
  const strengthColors = ['','#EF4444','#F59E0B','#F59E0B','#10B981','#059669'];

  pwdInput.addEventListener('input', () => {
    const v = pwdInput.value;
    if (v.length > 0) pwdReqs.classList.add('show'); else pwdReqs.classList.remove('show');
    let met = 0;
    for (const [id, fn] of Object.entries(reqs)) {
      const el = document.getElementById(id);
      if (fn(v)) { el.classList.add('met'); met++; } else el.classList.remove('met');
    }
    strengthFill.style.width = (met / 5 * 100) + '%';
    strengthFill.style.background = strengthColors[met] || '';
  });

  // Confirm password match
  const confirmInput = document.getElementById('confirm_password');
  const matchFeedback = document.getElementById('matchFeedback');
  confirmInput.addEventListener('input', () => {
    if (!confirmInput.value) { matchFeedback.style.display = 'none'; return; }
    matchFeedback.style.display = 'block';
    if (pwdInput.value === confirmInput.value) {
      matchFeedback.textContent = '✓ Passwords match'; matchFeedback.style.color = '#059669';
    } else {
      matchFeedback.textContent = '✗ Passwords do not match'; matchFeedback.style.color = '#DC2626';
    }
  });

  // Form validation
  document.getElementById('registerForm').addEventListener('submit', e => {
    let valid = true;
    ['first_name','last_name','email','confirm_password'].forEach(id => {
      const el = document.getElementById(id);
      if (!el.value.trim()) { el.classList.add('is-error'); valid = false; }
      else el.classList.remove('is-error');
    });
    if (!document.querySelector('input[name="program"]:checked')) valid = false;
    if (pwdInput.value !== confirmInput.value) { confirmInput.classList.add('is-error'); valid = false; }
    if (!valid) e.preventDefault();
  });
</script>
</body>
</html>