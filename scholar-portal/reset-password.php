<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$error = '';
$success = '';
$valid_token = false;
$token = $_GET['token'] ?? '';

if (!empty($token)) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("
            SELECT pr.*, u.email, u.first_name
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            WHERE pr.token = $1 AND pr.expires_at > CURRENT_TIMESTAMP AND pr.used = false
        ");
        $stmt->execute([$token]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reset) {
            $valid_token = true;
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $password = $_POST['password'];
                $confirm_password = $_POST['confirm_password'];
                if (strlen($password) < 8) {
                    $error = "Password must be at least 8 characters.";
                } elseif ($password !== $confirm_password) {
                    $error = "Passwords do not match.";
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = $1 WHERE id = $2");
                    $stmt->execute([$hashed, $reset['user_id']]);
                    $stmt = $conn->prepare("UPDATE password_resets SET used = true WHERE token = $1");
                    $stmt->execute([$token]);
                    $success = "Your password has been reset successfully.";
                }
            }
        } else {
            $error = "This reset link is invalid or has expired. Please request a new one.";
        }
    } catch (PDOException $e) {
        error_log("Password reset error: " . $e->getMessage());
        $error = "An error occurred. Please try again later.";
    }
} else {
    $error = "No reset token provided.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Reset Password | BFI Scholar Portal</title>
  <link rel="icon" type="image/png" href="/Images/bfi-new-logo.svg">
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
      --shadow-lg:0 20px 60px rgba(8,14,28,0.14);
      --r-sm:8px;--r-md:14px;--r-lg:20px;--r-xl:28px;
    }
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:var(--font-body);background:var(--navy);color:var(--text-primary);min-height:100vh;display:flex;flex-direction:column;-webkit-font-smoothing:antialiased;}
    body::before{content:'';position:fixed;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.02) 1px,transparent 1px);background-size:28px 28px;pointer-events:none;}
    body::after{content:'';position:fixed;bottom:-100px;left:-100px;width:500px;height:500px;background:radial-gradient(circle,rgba(200,160,88,0.07) 0%,transparent 65%);pointer-events:none;}
    a{text-decoration:none;color:inherit;}

    .top-nav{position:relative;z-index:10;display:flex;align-items:center;justify-content:space-between;padding:20px 32px;border-bottom:1px solid rgba(255,255,255,0.06);}
    .nav-logo{display:flex;align-items:center;gap:12px;}
    .nav-logomark{width:32px;height:32px;background:var(--gold);border-radius:6px;display:flex;align-items:center;justify-content:center;}
    .nav-logomark svg{width:18px;height:18px;}
    .nav-logo-text{font-family:var(--font-display);font-size:14px;font-weight:500;color:var(--white);line-height:1.2;}
    .nav-logo-text span{display:block;font-family:var(--font-body);font-size:8.5px;letter-spacing:2px;text-transform:uppercase;color:rgba(200,160,88,0.6);}
    .nav-link-btn{padding:8px 18px;background:rgba(200,160,88,0.1);border:1px solid rgba(200,160,88,0.2);border-radius:var(--r-sm);color:var(--gold-bright);font-size:13px;transition:var(--transition);}
    .nav-link-btn:hover{background:rgba(200,160,88,0.18);}

    .page-main{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 24px;}
    .auth-card{width:100%;max-width:460px;background:var(--white);border-radius:var(--r-xl);overflow:hidden;box-shadow:var(--shadow-lg);animation:cardIn 0.5s var(--ease) both;}
    @keyframes cardIn{from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);}}

    .card-header{background:var(--navy);padding:32px 36px 28px;position:relative;overflow:hidden;}
    .card-header::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.025) 1px,transparent 1px);background-size:24px 24px;}
    .card-header::after{content:'';position:absolute;top:-40px;right:-40px;width:160px;height:160px;background:radial-gradient(circle,rgba(200,160,88,0.15) 0%,transparent 65%);}
    .card-header-inner{position:relative;z-index:1;}
    .card-icon{width:52px;height:52px;border-radius:var(--r-md);background:rgba(200,160,88,0.15);border:1px solid rgba(200,160,88,0.25);display:flex;align-items:center;justify-content:center;font-size:20px;color:var(--gold-bright);margin-bottom:16px;}
    .card-title{font-family:var(--font-display);font-size:24px;font-weight:500;color:var(--white);margin-bottom:6px;}
    .card-subtitle{font-size:13px;font-weight:300;color:rgba(255,255,255,0.5);}

    .card-body{padding:32px 36px;}

    .alert{padding:13px 16px;border-radius:var(--r-sm);margin-bottom:20px;font-size:13.5px;display:flex;align-items:flex-start;gap:10px;}
    .alert i{margin-top:2px;flex-shrink:0;}
    .alert-error{background:rgba(239,68,68,0.07);color:#DC2626;border:1px solid rgba(239,68,68,0.15);}

    /* SUCCESS */
    .success-state{text-align:center;padding:8px 0;}
    .success-icon-wrap{width:72px;height:72px;background:rgba(16,185,129,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;color:#059669;margin:0 auto 20px;animation:popIn 0.4s var(--ease);}
    @keyframes popIn{from{transform:scale(0.7);opacity:0;}to{transform:scale(1);opacity:1;}}
    .success-title{font-family:var(--font-display);font-size:26px;font-weight:500;color:var(--navy);margin-bottom:8px;}
    .success-sub{font-size:13.5px;color:var(--text-muted);line-height:1.7;margin-bottom:24px;}
    .btn-go-login{display:inline-flex;align-items:center;gap:8px;padding:12px 28px;background:var(--navy);color:var(--white);font-family:var(--font-body);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-go-login:hover{background:var(--navy-mid);transform:translateY(-1px);}

    /* EXPIRED STATE */
    .expired-state{text-align:center;padding:8px 0;}
    .expired-icon{font-size:44px;color:var(--gold);margin-bottom:16px;}
    .expired-title{font-family:var(--font-display);font-size:22px;font-weight:500;color:var(--navy);margin-bottom:8px;}
    .expired-sub{font-size:13.5px;color:var(--text-muted);line-height:1.7;margin-bottom:20px;}
    .btn-request-new{display:inline-flex;align-items:center;gap:8px;padding:12px 28px;background:var(--gold);color:var(--midnight);font-family:var(--font-body);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-request-new:hover{background:var(--gold-bright);}

    /* FORM */
    .form-group{margin-bottom:20px;}
    .form-label{display:block;font-size:12.5px;font-weight:500;color:var(--text-secondary);margin-bottom:7px;}
    .input-wrap{position:relative;}
    .input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;pointer-events:none;}
    .form-input{width:100%;padding:11px 42px 11px 40px;border:1.5px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13.5px;color:var(--text-primary);background:var(--white);transition:var(--transition);outline:none;}
    .form-input:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,160,88,0.1);}
    .toggle-vis{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:13px;padding:4px;transition:var(--transition);}
    .toggle-vis:hover{color:var(--navy);}

    /* STRENGTH */
    .pwd-strength-bar{height:3px;background:var(--border-light);border-radius:2px;margin-top:8px;overflow:hidden;}
    .pwd-strength-fill{height:100%;width:0;border-radius:2px;transition:var(--transition);}

    /* REQUIREMENTS */
    .pwd-requirements{background:var(--cream);border:1px solid var(--border-light);border-radius:var(--r-sm);padding:14px 16px;margin-top:10px;display:none;}
    .pwd-requirements.show{display:block;}
    .req-title{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:10px;}
    .req-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px;}
    .req-item{display:flex;align-items:center;gap:7px;font-size:12px;color:var(--text-muted);transition:var(--transition);}
    .req-item.met{color:#059669;}
    .req-item i{font-size:10px;width:13px;}

    .btn-submit{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:13px 24px;background:var(--navy);color:var(--white);font-family:var(--font-body);font-size:13.5px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);margin-top:4px;}
    .btn-submit:hover{background:var(--navy-mid);transform:translateY(-1px);}
    .back-link{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:18px;font-size:13px;color:var(--text-muted);transition:var(--transition);}
    .back-link:hover{color:var(--navy);}

    .page-footer{text-align:center;padding:20px;border-top:1px solid rgba(255,255,255,0.06);}
    .page-footer p{font-size:11.5px;color:rgba(255,255,255,0.2);}
    .page-footer a{color:rgba(200,160,88,0.5);transition:var(--transition);}
    .page-footer a:hover{color:var(--gold);}

    @media(max-width:480px){.auth-card{border-radius:var(--r-lg);}.card-header,.card-body{padding:24px 24px;}.req-grid{grid-template-columns:1fr;}}
  </style>
</head>
<body>

  <nav class="top-nav">
    <div class="nav-logo">
      <div class="nav-logomark"><svg viewBox="0 0 22 22" fill="none"><path d="M4 18L11 4L18 18" stroke="#080E1C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 13H15" stroke="#080E1C" stroke-width="1.8" stroke-linecap="round"/></svg></div>
      <div class="nav-logo-text">Bold Footprint<span>Scholar Portal</span></div>
    </div>
    <a href="login.php" class="nav-link-btn">Sign In</a>
  </nav>

  <main class="page-main">
    <div class="auth-card">
      <div class="card-header">
        <div class="card-header-inner">
          <div class="card-icon"><i class="fas fa-shield-alt"></i></div>
          <div class="card-title">Reset Your Password</div>
          <div class="card-subtitle">Choose a new, strong password for your scholar account.</div>
        </div>
      </div>

      <div class="card-body">
        <?php if ($success): ?>
          <div class="success-state">
            <div class="success-icon-wrap"><i class="fas fa-check"></i></div>
            <div class="success-title">Password updated!</div>
            <p class="success-sub">Your password has been changed successfully. You can now sign in with your new credentials.</p>
            <a href="login.php" class="btn-go-login"><i class="fas fa-sign-in-alt"></i> Sign In Now</a>
          </div>

        <?php elseif (!$valid_token): ?>
          <div class="expired-state">
            <div class="expired-icon"><i class="fas fa-clock"></i></div>
            <div class="expired-title">Link expired or invalid</div>
            <p class="expired-sub">This password reset link has expired or has already been used. Reset links are valid for 1 hour. Please request a new one.</p>
            <a href="forgot-password.php" class="btn-request-new"><i class="fas fa-paper-plane"></i> Request New Link</a>
          </div>

        <?php else: ?>
          <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><span><?php echo htmlspecialchars($error); ?></span></div>
          <?php endif; ?>

          <?php if (isset($reset['first_name'])): ?>
            <p style="font-size:13.5px;color:var(--text-muted);margin-bottom:20px;">Hi <strong style="color:var(--navy);"><?php echo htmlspecialchars($reset['first_name']); ?></strong>, create a new password below.</p>
          <?php endif; ?>

          <form method="POST" id="resetForm" novalidate>
            <div class="form-group">
              <label class="form-label" for="password">New Password</label>
              <div class="input-wrap">
                <i class="fas fa-lock input-icon"></i>
                <input type="password" id="password" name="password" class="form-input" placeholder="Create a strong password" required>
                <button type="button" class="toggle-vis" data-target="password"><i class="fas fa-eye"></i></button>
              </div>
              <div class="pwd-strength-bar"><div class="pwd-strength-fill" id="strengthFill"></div></div>
              <div class="pwd-requirements" id="pwdReqs">
                <div class="req-title">Requirements</div>
                <div class="req-grid">
                  <div class="req-item" id="req-length"><i class="fas fa-circle"></i> 8+ characters</div>
                  <div class="req-item" id="req-upper"><i class="fas fa-circle"></i> Uppercase letter</div>
                  <div class="req-item" id="req-lower"><i class="fas fa-circle"></i> Lowercase letter</div>
                  <div class="req-item" id="req-number"><i class="fas fa-circle"></i> Number</div>
                  <div class="req-item" id="req-special"><i class="fas fa-circle"></i> Special character</div>
                </div>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="confirm_password">Confirm New Password</label>
              <div class="input-wrap">
                <i class="fas fa-lock input-icon"></i>
                <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Repeat your new password" required>
                <button type="button" class="toggle-vis" data-target="confirm_password"><i class="fas fa-eye"></i></button>
              </div>
              <div id="matchFeedback" style="font-size:12px;margin-top:6px;display:none;"></div>
            </div>

            <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Set New Password</button>
          </form>
        <?php endif; ?>

        <a href="login.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Sign In</a>
      </div>
    </div>
  </main>

  <footer class="page-footer">
    <p>&copy; 2026 Bold Footprint Initiatives &nbsp;·&nbsp; <a href="/index.html">Main Site</a></p>
  </footer>

<script>
  document.querySelectorAll('.toggle-vis').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.target);
      const icon = btn.querySelector('i');
      if (input.type === 'password') { input.type = 'text'; icon.classList.replace('fa-eye','fa-eye-slash'); }
      else { input.type = 'password'; icon.classList.replace('fa-eye-slash','fa-eye'); }
    });
  });

  const pwdInput = document.getElementById('password');
  if (pwdInput) {
    const strengthFill = document.getElementById('strengthFill');
    const pwdReqs = document.getElementById('pwdReqs');
    const confirmInput = document.getElementById('confirm_password');
    const matchFeedback = document.getElementById('matchFeedback');
    const reqs = {
      'req-length': v => v.length >= 8,
      'req-upper':  v => /[A-Z]/.test(v),
      'req-lower':  v => /[a-z]/.test(v),
      'req-number': v => /[0-9]/.test(v),
      'req-special':v => /[^A-Za-z0-9]/.test(v),
    };
    const colors = ['','#EF4444','#F59E0B','#F59E0B','#10B981','#059669'];

    pwdInput.addEventListener('input', () => {
      const v = pwdInput.value;
      if (v) pwdReqs.classList.add('show'); else pwdReqs.classList.remove('show');
      let met = 0;
      for (const [id, fn] of Object.entries(reqs)) {
        const el = document.getElementById(id);
        if (fn(v)) { el.classList.add('met'); met++; } else el.classList.remove('met');
      }
      strengthFill.style.width = (met / 5 * 100) + '%';
      strengthFill.style.background = colors[met] || '';
    });

    confirmInput.addEventListener('input', () => {
      if (!confirmInput.value) { matchFeedback.style.display = 'none'; return; }
      matchFeedback.style.display = 'block';
      if (pwdInput.value === confirmInput.value) {
        matchFeedback.textContent = '✓ Passwords match'; matchFeedback.style.color = '#059669';
      } else {
        matchFeedback.textContent = '✗ Passwords do not match'; matchFeedback.style.color = '#DC2626';
      }
    });

    document.getElementById('resetForm').addEventListener('submit', e => {
      if (pwdInput.value !== confirmInput.value || pwdInput.value.length < 8) e.preventDefault();
    });
  }
</script>
</body>
</html>