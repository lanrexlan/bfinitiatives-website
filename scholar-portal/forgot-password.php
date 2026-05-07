<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    try {
        $db = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT id, email, first_name FROM users WHERE email = :email AND is_active = true");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (:user_id, :token, :expires)");
            $stmt->execute(['user_id' => $user['id'], 'token' => $token, 'expires' => $expires]);

            $reset_link = "https://" . $_SERVER['HTTP_HOST'] . "/scholar-portal/reset-password.php?token=" . $token;
            $emailBody = "
                <html><body style='font-family:Arial,sans-serif;line-height:1.6;color:#333;'>
                <div style='max-width:600px;margin:0 auto;padding:20px;'>
                <h2 style='color:#0D1829;'>Password Reset Request</h2>
                <p>Dear {$user['first_name']},</p>
                <p>You recently requested to reset your password. Click the link below to reset it:</p>
                <p><a href='{$reset_link}' style='background:#C8A058;color:#080E1C;padding:12px 20px;text-decoration:none;border-radius:6px;display:inline-block;font-weight:600;'>Reset My Password</a></p>
                <p>This link will expire in <strong>1 hour</strong>.</p>
                <p>If you did not request this, please ignore this email.</p>
                <p>Regards,<br><strong>BFI Scholar Portal Team</strong></p>
                </div></body></html>";

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP(); $mail->Host = SMTP_HOST; $mail->SMTPAuth = true;
                $mail->Username = SMTP_USER; $mail->Password = SMTP_PASS;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; $mail->Port = SMTP_PORT;
                $mail->setFrom('noreply@bfinitiatives.com', 'BFI Scholar Portal');
                $mail->addAddress($user['email'], $user['first_name']);
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request — BFI Scholar Portal';
                $mail->Body = $emailBody;
                $mail->send();
                $success = "Password reset instructions have been sent to your email.";
            } catch (Exception $e) {
                error_log("Mailer Error: {$mail->ErrorInfo}");
                $error = "Failed to send reset email. Please try again or contact support.";
            }
        } else {
            $success = "If your email address exists in our database, you will receive a password reset link.";
        }
    } catch (PDOException $e) {
        error_log("DB error: " . $e->getMessage());
        $error = "An error occurred. Please try again later.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Forgot Password | BFI Scholar Portal</title>
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
    body{font-family:var(--font-body);background:var(--navy);color:var(--text-primary);min-height:100vh;display:flex;flex-direction:column;-webkit-font-smoothing:antialiased;}
    body::before{content:'';position:fixed;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.02) 1px,transparent 1px);background-size:28px 28px;pointer-events:none;}
    body::after{content:'';position:fixed;top:-100px;right:-100px;width:500px;height:500px;background:radial-gradient(circle,rgba(200,160,88,0.08) 0%,transparent 65%);pointer-events:none;}
    a{text-decoration:none;color:inherit;}

    /* TOP NAV */
    .top-nav{position:relative;z-index:10;display:flex;align-items:center;justify-content:space-between;padding:20px 32px;border-bottom:1px solid rgba(255,255,255,0.06);}
    .nav-logo{display:flex;align-items:center;gap:12px;}
    .nav-logomark{width:32px;height:32px;background:var(--gold);border-radius:6px;display:flex;align-items:center;justify-content:center;}
    .nav-logomark svg{width:18px;height:18px;}
    .nav-logo-text{font-family:var(--font-display);font-size:14px;font-weight:500;color:var(--white);line-height:1.2;}
    .nav-logo-text span{display:block;font-family:var(--font-body);font-size:8.5px;letter-spacing:2px;text-transform:uppercase;color:rgba(200,160,88,0.6);}
    .nav-links{display:flex;align-items:center;gap:20px;}
    .nav-link{font-size:13px;color:rgba(255,255,255,0.5);transition:var(--transition);}
    .nav-link:hover{color:var(--white);}
    .nav-link-btn{padding:8px 18px;background:rgba(200,160,88,0.1);border:1px solid rgba(200,160,88,0.2);border-radius:var(--r-sm);color:var(--gold-bright);font-size:13px;transition:var(--transition);}
    .nav-link-btn:hover{background:rgba(200,160,88,0.18);}

    /* MAIN */
    .page-main{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 24px;}
    .auth-card{width:100%;max-width:440px;background:var(--white);border-radius:var(--r-xl);overflow:hidden;box-shadow:var(--shadow-lg);animation:cardIn 0.5s var(--ease) both;}
    @keyframes cardIn{from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);}}

    /* CARD HEADER */
    .card-header{background:var(--navy);padding:32px 36px 28px;position:relative;overflow:hidden;}
    .card-header::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.025) 1px,transparent 1px);background-size:24px 24px;}
    .card-header::after{content:'';position:absolute;bottom:-40px;right:-40px;width:160px;height:160px;background:radial-gradient(circle,rgba(200,160,88,0.15) 0%,transparent 65%);}
    .card-header-inner{position:relative;z-index:1;}
    .card-icon{width:52px;height:52px;border-radius:var(--r-md);background:rgba(200,160,88,0.15);border:1px solid rgba(200,160,88,0.25);display:flex;align-items:center;justify-content:center;font-size:20px;color:var(--gold-bright);margin-bottom:16px;}
    .card-title{font-family:var(--font-display);font-size:24px;font-weight:500;color:var(--white);margin-bottom:6px;}
    .card-subtitle{font-size:13px;font-weight:300;color:rgba(255,255,255,0.5);}

    /* CARD BODY */
    .card-body{padding:32px 36px;}

    /* ALERTS */
    .alert{padding:13px 16px;border-radius:var(--r-sm);margin-bottom:20px;font-size:13.5px;display:flex;align-items:flex-start;gap:10px;}
    .alert i{margin-top:2px;flex-shrink:0;}
    .alert-error{background:rgba(239,68,68,0.07);color:#DC2626;border:1px solid rgba(239,68,68,0.15);}

    /* SUCCESS STATE */
    .success-state{text-align:center;padding:16px 0;}
    .success-envelope{font-size:48px;color:var(--gold);margin-bottom:16px;display:inline-block;animation:floatBounce 2.5s ease-in-out infinite;}
    @keyframes floatBounce{0%,100%{transform:translateY(0);}50%{transform:translateY(-10px);}}
    .success-title{font-family:var(--font-display);font-size:24px;font-weight:500;color:var(--navy);margin-bottom:8px;}
    .success-sub{font-size:13.5px;color:var(--text-muted);line-height:1.7;margin-bottom:20px;}
    .tips-box{background:var(--cream);border:1px solid var(--border-light);border-radius:var(--r-md);padding:16px 20px;text-align:left;margin-bottom:20px;}
    .tips-title{font-size:11px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:var(--text-muted);margin-bottom:10px;}
    .tips-list{list-style:none;display:flex;flex-direction:column;gap:6px;}
    .tips-list li{display:flex;align-items:flex-start;gap:8px;font-size:12.5px;color:var(--text-secondary);}
    .tips-list li::before{content:'·';color:var(--gold);font-size:16px;line-height:1;margin-top:1px;}

    /* FORM */
    .form-group{margin-bottom:20px;}
    .form-label{display:block;font-size:12.5px;font-weight:500;color:var(--text-secondary);margin-bottom:7px;}
    .input-wrap{position:relative;}
    .input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;pointer-events:none;}
    .form-input{width:100%;padding:11px 14px 11px 40px;border:1.5px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13.5px;color:var(--text-primary);background:var(--white);transition:var(--transition);outline:none;}
    .form-input:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,160,88,0.1);}
    .btn-submit{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:13px 24px;background:var(--navy);color:var(--white);font-family:var(--font-body);font-size:13.5px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-submit:hover{background:var(--navy-mid);transform:translateY(-1px);}
    .back-link{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:20px;font-size:13px;color:var(--text-muted);transition:var(--transition);}
    .back-link:hover{color:var(--navy);}

    /* FOOTER */
    .page-footer{text-align:center;padding:20px;border-top:1px solid rgba(255,255,255,0.06);}
    .page-footer p{font-size:11.5px;color:rgba(255,255,255,0.2);}
    .page-footer a{color:rgba(200,160,88,0.5);transition:var(--transition);}
    .page-footer a:hover{color:var(--gold);}

    @media(max-width:480px){.auth-card{border-radius:var(--r-lg);}.card-header,.card-body{padding:24px 24px;}.top-nav{padding:16px 20px;}.nav-links span{display:none;}}
  </style>
</head>
<body>

  <nav class="top-nav">
    <div class="nav-logo">
      <div class="nav-logomark"><svg viewBox="0 0 22 22" fill="none"><path d="M4 18L11 4L18 18" stroke="#080E1C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 13H15" stroke="#080E1C" stroke-width="1.8" stroke-linecap="round"/></svg></div>
      <div class="nav-logo-text">Bold Footprint<span>Scholar Portal</span></div>
    </div>
    <div class="nav-links">
      <a href="/" class="nav-link"><span>Main Site</span></a>
      <a href="login.php" class="nav-link nav-link-btn">Sign In</a>
    </div>
  </nav>

  <main class="page-main">
    <div class="auth-card">
      <div class="card-header">
        <div class="card-header-inner">
          <div class="card-icon"><i class="fas fa-key"></i></div>
          <div class="card-title">Forgot Password?</div>
          <div class="card-subtitle">Enter your registered email to receive reset instructions.</div>
        </div>
      </div>

      <div class="card-body">
        <?php if ($success): ?>
          <div class="success-state">
            <div class="success-envelope"><i class="fas fa-envelope-open-text"></i></div>
            <div class="success-title">Check your inbox</div>
            <p class="success-sub">We've sent password reset instructions to your email address. The link expires in 1 hour.</p>
            <div class="tips-box">
              <div class="tips-title">Didn't receive it?</div>
              <ul class="tips-list">
                <li>Check your spam or junk mail folder</li>
                <li>Verify the email address you entered</li>
                <li>Allow a few minutes for delivery</li>
                <li>Contact us at info@bfinitiatives.com if the issue persists</li>
              </ul>
            </div>
          </div>
        <?php else: ?>
          <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><span><?php echo htmlspecialchars($error); ?></span></div>
          <?php endif; ?>
          <form method="POST" action="forgot-password.php">
            <div class="form-group">
              <label class="form-label" for="email">Email Address</label>
              <div class="input-wrap">
                <i class="fas fa-envelope input-icon"></i>
                <input type="email" id="email" name="email" class="form-input" placeholder="you@example.com" required>
              </div>
            </div>
            <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Send Reset Link</button>
          </form>
        <?php endif; ?>
        <a href="login.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Sign In</a>
      </div>
    </div>
  </main>

  <footer class="page-footer">
    <p>&copy; 2026 Bold Footprint Initiatives &nbsp;·&nbsp; <a href="/index.html">Main Site</a> &nbsp;·&nbsp; <a href="privacy-policy.php">Privacy Policy</a></p>
  </footer>

</body>
</html>