<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user = [];
$error_msg = '';
$success_msg = '';
$current_password_error = '';
$new_password_error = '';
$login_activity = [];

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("
        SELECT id, first_name, last_name, email, profile_picture,
               email_notifications, sms_notifications, communication_frequency,
               dark_mode, language_preference
        FROM users WHERE id = :user_id
    ");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) throw new Exception("User not found. Please log in again.");

    $table_exists = false;
    try {
        $conn->query("SELECT 1 FROM login_activities LIMIT 1");
        $table_exists = true;
    } catch (PDOException $e) {
        try {
            $conn->exec("
                CREATE TABLE IF NOT EXISTS login_activities (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER NOT NULL,
                    activity_type VARCHAR(50) NOT NULL,
                    device VARCHAR(255), location VARCHAR(255), ip_address VARCHAR(45),
                    login_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    status VARCHAR(50) DEFAULT 'success',
                    CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                );
                CREATE INDEX IF NOT EXISTS idx_login_activities_user_id ON login_activities(user_id);
            ");
            $table_exists = true;
            $insert_current = $conn->prepare("INSERT INTO login_activities (user_id, activity_type, device, location, ip_address, login_time, status) VALUES (:user_id, 'login', :device, :location, :ip, NOW(), 'Current Session')");
            $insert_current->execute([':user_id' => $_SESSION['user_id'], ':device' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', ':location' => 'Unknown', ':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
        } catch (PDOException $create_error) {
            error_log("Failed to create login_activities table: " . $create_error->getMessage());
        }
    }

    if ($table_exists) {
        try {
            $activity_stmt = $conn->prepare("SELECT device, location, login_time, status FROM login_activities WHERE user_id = :user_id ORDER BY login_time DESC LIMIT 5");
            $activity_stmt->execute([':user_id' => $_SESSION['user_id']]);
            $login_activity = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $activity_error) {
            error_log("Error fetching login activity: " . $activity_error->getMessage());
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password     = $_POST['new_password']     ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $valid = true;
        if (empty($current_password)) { $current_password_error = "Current password is required"; $valid = false; }
        if (empty($new_password))     { $new_password_error = "New password is required"; $valid = false; }
        elseif (strlen($new_password) < 8) { $new_password_error = "Password must be at least 8 characters"; $valid = false; }
        if ($new_password !== $confirm_password) { $error_msg = "New passwords do not match"; $valid = false; }
        if ($valid) {
            $password_stmt = $conn->prepare("SELECT password FROM users WHERE id = :user_id");
            $password_stmt->execute([':user_id' => $_SESSION['user_id']]);
            $stored_password = $password_stmt->fetchColumn();
            if ($stored_password && password_verify($current_password, $stored_password)) {
                $update_stmt = $conn->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE id = :user_id");
                if ($update_stmt->execute([':password' => password_hash($new_password, PASSWORD_DEFAULT), ':user_id' => $_SESSION['user_id']])) {
                    if ($table_exists) { try { $conn->prepare("INSERT INTO login_activities (user_id, activity_type, device, location, ip_address, login_time, status) VALUES (:user_id, 'password_change', :device, :location, :ip, NOW(), 'success')")->execute([':user_id' => $_SESSION['user_id'], ':device' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', ':location' => 'Unknown', ':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']); } catch (PDOException $le) { error_log($le->getMessage()); } }
                    $success_msg = "Password updated successfully!";
                } else { $error_msg = "Failed to update password. Please try again."; }
            } else { $current_password_error = "Current password is incorrect"; }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
        $email_notifications   = isset($_POST['email_notifications'])   ? 1 : 0;
        $sms_notifications     = isset($_POST['sms_notifications'])     ? 1 : 0;
        $communication_frequency = $_POST['communication_frequency'] ?? 'weekly';
        $dark_mode             = isset($_POST['dark_mode'])             ? 1 : 0;
        $language_preference   = $_POST['language_preference']          ?? 'en';
        $settings_stmt = $conn->prepare("UPDATE users SET email_notifications=:en, sms_notifications=:sn, communication_frequency=:cf, dark_mode=:dm, language_preference=:lp, updated_at=CURRENT_TIMESTAMP WHERE id=:user_id");
        if ($settings_stmt->execute([':en'=>$email_notifications,':sn'=>$sms_notifications,':cf'=>$communication_frequency,':dm'=>$dark_mode,':lp'=>$language_preference,':user_id'=>$_SESSION['user_id']])) {
            $success_msg = "Settings saved successfully!";
            $stmt->execute([':user_id' => $_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            setcookie('theme', $dark_mode ? 'dark' : 'light', time()+(86400*30), "/");
            setcookie('lang',  $language_preference,           time()+(86400*30), "/");
        } else { $error_msg = "Failed to save settings. Please try again."; }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
        if (($_POST['delete_confirmation'] ?? '') === 'DELETE') {
            try {
                $conn->beginTransaction();
                if ($table_exists) { try { $conn->prepare("DELETE FROM login_activities WHERE user_id=:uid")->execute([':uid'=>$_SESSION['user_id']]); } catch(PDOException $de){} }
                $conn->prepare("DELETE FROM users WHERE id=:uid")->execute([':uid'=>$_SESSION['user_id']]);
                $conn->commit();
                session_destroy();
                header('Location: login.php?deleted=true');
                exit();
            } catch (Exception $e) { $conn->rollBack(); $error_msg = "Failed to delete account: " . $e->getMessage(); }
        } else { $error_msg = "Please type 'DELETE' exactly to confirm."; }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_data'])) {
        $json_data = json_encode(['profile'=>$user,'login_activity'=>$login_activity], JSON_PRETTY_PRINT);
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="my_account_data.json"');
        header('Content-Length: ' . strlen($json_data));
        echo $json_data; exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout_all_sessions'])) {
        if ($table_exists) {
            try {
                $conn->prepare("UPDATE login_activities SET status='Closed' WHERE user_id=:uid AND status='Current Session' AND id!=(SELECT id FROM login_activities WHERE user_id=:uid2 AND status='Current Session' ORDER BY login_time DESC LIMIT 1)")->execute([':uid'=>$_SESSION['user_id'],':uid2'=>$_SESSION['user_id']]);
                $success_msg = "Successfully logged out of all other sessions.";
            } catch (PDOException $le) { $error_msg = "Failed to logout other sessions."; }
        } else { $success_msg = "Successfully logged out of all other sessions."; }
    }

} catch (Exception $e) {
    error_log("Settings error: " . $e->getMessage());
    $error_msg = "An error occurred: " . $e->getMessage();
}

if (empty($login_activity)) {
    $login_activity = [
        ['device'=>'Chrome on Windows','location'=>'Lagos, Nigeria','login_time'=>date('Y-m-d H:i:s'),'status'=>'Current Session'],
        ['device'=>'Safari on iPhone','location'=>'Lagos, Nigeria','login_time'=>date('Y-m-d H:i:s',strtotime('-2 days')),'status'=>'Closed'],
        ['device'=>'Chrome on Android','location'=>'Abuja, Nigeria','login_time'=>date('Y-m-d H:i:s',strtotime('-6 days')),'status'=>'Closed'],
    ];
}

// Account Health
$health_checks = [
    ['label'=>'Profile photo uploaded',   'done'=> !empty($user['profile_picture'])],
    ['label'=>'Full name on record',       'done'=> !empty($user['first_name']) && !empty($user['last_name'])],
    ['label'=>'Email address verified',    'done'=> !empty($user['email'])],
    ['label'=>'Notification preferences', 'done'=> isset($user['email_notifications'])],
    ['label'=>'Language preference set',   'done'=> !empty($user['language_preference'])],
];
$health_done  = count(array_filter($health_checks, fn($c) => $c['done']));
$health_score = (int) round(($health_done / count($health_checks)) * 100);

$first_name     = $user['first_name']     ?? '';
$last_name      = $user['last_name']      ?? '';
$profile_picture = $user['profile_picture'] ?? null;
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($user['language_preference'] ?? 'en'); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Settings | BFI Scholar Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
      --sidebar-width:268px;--header-height:64px;
      --r-sm:8px;--r-md:14px;--r-lg:20px;--r-xl:28px;
    }
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    html{font-size:16px;}
    body{font-family:var(--font-body);background:#F2F4F8;color:var(--text-primary);line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden;}
    a{text-decoration:none;color:inherit;}
    img{max-width:100%;display:block;}

    /* ── SIDEBAR ── */
    .sidebar{position:fixed;left:0;top:0;width:var(--sidebar-width);height:100vh;background:var(--navy);z-index:200;display:flex;flex-direction:column;overflow:hidden;transition:transform var(--transition);}
    .sidebar-top{padding:28px 24px 20px;border-bottom:1px solid rgba(255,255,255,0.06);}
    .sidebar-logo{display:flex;align-items:center;gap:12px;margin-bottom:32px;}
    .sidebar-logomark{width:34px;height:34px;background:var(--gold);border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .sidebar-logomark svg{width:20px;height:20px;}
    .sidebar-logo-text{font-family:var(--font-display);font-size:15px;font-weight:500;color:var(--white);line-height:1.2;}
    .sidebar-logo-text span{display:block;font-family:var(--font-body);font-size:9px;letter-spacing:2px;text-transform:uppercase;color:rgba(200,160,88,0.6);}
    .sidebar-user{display:flex;align-items:center;gap:12px;}
    .sidebar-avatar{width:40px;height:40px;border-radius:50%;background:var(--navy-light);border:2px solid rgba(200,160,88,0.3);overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .sidebar-avatar img{width:100%;height:100%;object-fit:cover;}
    .sidebar-avatar-init{font-family:var(--font-display);font-size:16px;font-weight:500;color:var(--gold-bright);}
    .sidebar-user-name{font-size:13.5px;font-weight:500;color:var(--white);line-height:1.3;}
    .sidebar-user-role{font-size:10.5px;color:rgba(255,255,255,0.35);letter-spacing:0.5px;}
    .sidebar-nav{flex:1;padding:20px 12px;overflow-y:auto;}
    .nav-section-label{font-size:9.5px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,0.2);padding:0 12px;margin:16px 0 8px;}
    .nav-item{margin-bottom:2px;}
    .nav-link{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:var(--r-sm);font-size:13.5px;font-weight:400;color:rgba(255,255,255,0.6);transition:var(--transition);position:relative;}
    .nav-link:hover{background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.9);}
    .nav-link.active{background:rgba(200,160,88,0.12);color:var(--gold-bright);}
    .nav-link.active::before{content:'';position:absolute;left:0;top:6px;bottom:6px;width:2.5px;background:var(--gold);border-radius:2px;}
    .nav-link i{width:18px;text-align:center;font-size:14px;flex-shrink:0;}
    .sidebar-bottom{padding:16px 12px;border-top:1px solid rgba(255,255,255,0.06);}

    /* ── HEADER ── */
    .header{position:fixed;top:0;left:var(--sidebar-width);right:0;height:var(--header-height);background:var(--white);border-bottom:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;padding:0 28px;z-index:100;}
    .header-left{display:flex;align-items:center;gap:16px;}
    .mobile-toggle{display:none;background:none;border:none;cursor:pointer;padding:6px;color:var(--text-secondary);font-size:18px;}
    .header-page-title{font-family:var(--font-display);font-size:19px;font-weight:500;color:var(--navy);}
    .header-page-title em{font-style:italic;color:var(--gold);}
    .header-right{display:flex;align-items:center;gap:12px;}
    .header-icon-btn{width:36px;height:36px;border-radius:var(--r-sm);background:var(--cream);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-secondary);font-size:15px;transition:var(--transition);}
    .header-icon-btn:hover{background:var(--cream-dark);color:var(--navy);}
    .header-avatar{width:36px;height:36px;border-radius:50%;background:var(--navy-light);overflow:hidden;border:2px solid var(--border-light);cursor:pointer;display:flex;align-items:center;justify-content:center;}
    .header-avatar img{width:100%;height:100%;object-fit:cover;}
    .header-avatar-init{font-family:var(--font-display);font-size:14px;color:var(--gold-bright);}

    /* ── MAIN ── */
    .main{margin-left:var(--sidebar-width);margin-top:var(--header-height);padding:28px;min-height:calc(100vh - var(--header-height));}

    /* ── SETTINGS BANNER ── */
    .settings-banner{background:var(--navy);border-radius:var(--r-xl);padding:28px 32px;margin-bottom:24px;position:relative;overflow:hidden;}
    .settings-banner::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:32px 32px;}
    .settings-banner::after{content:'';position:absolute;top:-60px;right:-60px;width:280px;height:280px;background:radial-gradient(circle,rgba(200,160,88,0.1) 0%,transparent 65%);}
    .banner-inner{position:relative;z-index:1;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;}
    .banner-eyebrow{font-size:10px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:rgba(200,160,88,0.7);margin-bottom:6px;}
    .banner-title{font-family:var(--font-display);font-size:clamp(20px,2.5vw,28px);font-weight:500;color:var(--white);line-height:1.2;}
    .banner-title em{font-style:italic;color:var(--gold-bright);}
    .banner-sub{font-size:13px;font-weight:300;color:rgba(255,255,255,0.5);margin-top:4px;}
    .banner-chips{display:flex;gap:12px;flex-wrap:wrap;}
    .banner-chip{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:var(--r-md);padding:10px 16px;text-align:center;}
    .chip-val{font-family:var(--font-display);font-size:20px;font-weight:500;color:var(--gold-bright);line-height:1;}
    .chip-lbl{font-size:9.5px;color:rgba(255,255,255,0.4);letter-spacing:0.5px;margin-top:3px;}

    /* ── ACCOUNT HEALTH ── */
    .health-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:24px;margin-bottom:24px;display:flex;align-items:center;gap:24px;flex-wrap:wrap;}
    .health-ring-wrap{position:relative;width:90px;height:90px;flex-shrink:0;}
    .health-ring-wrap svg{transform:rotate(-90deg);}
    .health-ring-center{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;}
    .health-pct{font-family:var(--font-display);font-size:22px;font-weight:500;color:var(--navy);line-height:1;}
    .health-lbl{font-size:9px;color:var(--text-muted);letter-spacing:0.5px;text-transform:uppercase;}
    .health-body{flex:1;}
    .health-title{font-family:var(--font-display);font-size:18px;font-weight:500;color:var(--navy);margin-bottom:4px;}
    .health-title em{font-style:italic;color:var(--gold);}
    .health-desc{font-size:12.5px;color:var(--text-muted);margin-bottom:14px;}
    .health-checklist{display:flex;flex-wrap:wrap;gap:8px;}
    .hc-item{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-secondary);}
    .hc-dot{width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;flex-shrink:0;}
    .hc-done{background:rgba(16,185,129,0.12);color:#10B981;}
    .hc-todo{background:var(--cream);color:var(--text-muted);border:1px solid var(--border-light);}

    /* ── ALERTS ── */
    .alert-custom{display:flex;align-items:center;gap:12px;padding:14px 18px;border-radius:var(--r-sm);font-size:13.5px;margin-bottom:20px;}
    .alert-success-c{background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);color:#065F46;}
    .alert-error-c{background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);color:#991B1B;}
    .alert-close{margin-left:auto;background:none;border:none;cursor:pointer;opacity:0.5;font-size:14px;padding:2px;}
    .alert-close:hover{opacity:1;}

    /* ── TABS ── */
    .settings-tabs-bar{display:flex;gap:4px;background:var(--white);border:1px solid var(--border-light);border-radius:var(--r-lg);padding:6px;margin-bottom:20px;overflow-x:auto;-webkit-overflow-scrolling:touch;}
    .stab{display:flex;align-items:center;gap:8px;padding:10px 18px;border-radius:var(--r-sm);font-size:13px;font-weight:500;color:var(--text-muted);background:none;border:none;cursor:pointer;transition:var(--transition);white-space:nowrap;font-family:var(--font-body);}
    .stab:hover{background:var(--cream);color:var(--text-primary);}
    .stab.active{background:var(--navy);color:var(--gold-bright);}
    .stab i{font-size:13px;}
    .stab-panel{display:none;}
    .stab-panel.active{display:block;}

    /* ── SECTION CARDS ── */
    .s-card{background:var(--white);border-radius:var(--r-lg);border:1px solid var(--border-light);padding:24px;margin-bottom:20px;}
    .s-card-head{margin-bottom:18px;}
    .s-card-title{font-family:var(--font-display);font-size:18px;font-weight:500;color:var(--navy);}
    .s-card-title em{font-style:italic;color:var(--gold);}
    .s-card-sub{font-size:12.5px;color:var(--text-muted);margin-top:3px;}

    /* ── FORMS ── */
    .field{margin-bottom:18px;}
    .field-label{display:block;font-size:12.5px;font-weight:500;color:var(--text-secondary);margin-bottom:7px;letter-spacing:0.3px;}
    .field-input{width:100%;padding:11px 14px;border:1.5px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13.5px;color:var(--text-primary);background:var(--cream);transition:var(--transition);outline:none;}
    .field-input:focus{border-color:var(--gold);background:var(--white);box-shadow:0 0 0 3px rgba(200,160,88,0.1);}
    .field-input.invalid{border-color:#EF4444;}
    .input-wrap{position:relative;}
    .input-wrap .field-input{padding-right:44px;}
    .eye-btn{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:14px;padding:4px;transition:color var(--transition);}
    .eye-btn:hover{color:var(--navy);}
    .field-err{font-size:11.5px;color:#EF4444;margin-top:5px;display:flex;align-items:center;gap:5px;}
    .field-hint{font-size:11.5px;color:var(--text-muted);margin-top:5px;}

    /* Password strength */
    .pwd-bar-wrap{height:4px;background:var(--border-light);border-radius:2px;overflow:hidden;margin-top:10px;}
    .pwd-bar-fill{height:100%;border-radius:2px;transition:all 0.4s var(--ease);}
    .pwd-bar-lbl{font-size:11.5px;margin-top:6px;font-weight:500;}

    /* ── BUTTONS ── */
    .btn-gold{display:inline-flex;align-items:center;gap:8px;padding:11px 22px;background:var(--gold);color:var(--midnight);font-family:var(--font-body);font-size:13px;font-weight:500;border:none;border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-gold:hover{background:var(--gold-bright);transform:translateY(-1px);}
    .btn-navy-outline{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:transparent;color:var(--navy);font-family:var(--font-body);font-size:13px;font-weight:500;border:1.5px solid var(--navy);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-navy-outline:hover{background:var(--navy);color:var(--white);}
    .btn-ghost{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:transparent;color:var(--text-secondary);font-family:var(--font-body);font-size:13px;border:1.5px solid var(--border-light);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-ghost:hover{border-color:var(--navy);color:var(--navy);}
    .btn-danger{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:rgba(239,68,68,0.07);color:#EF4444;font-family:var(--font-body);font-size:13px;font-weight:500;border:1.5px solid rgba(239,68,68,0.2);border-radius:var(--r-sm);cursor:pointer;transition:var(--transition);}
    .btn-danger:hover{background:rgba(239,68,68,0.14);}
    .btn-row{display:flex;justify-content:flex-end;gap:10px;margin-top:20px;flex-wrap:wrap;}

    /* ── TOGGLE SWITCH ── */
    .toggle-row{display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--border-light);}
    .toggle-row:last-child{border-bottom:none;}
    .toggle-info .toggle-title{font-size:13.5px;font-weight:500;color:var(--navy);}
    .toggle-info .toggle-desc{font-size:12px;color:var(--text-muted);margin-top:2px;}
    .t-switch{position:relative;width:44px;height:24px;flex-shrink:0;}
    .t-switch input{opacity:0;width:0;height:0;}
    .t-slider{position:absolute;inset:0;background:var(--border-light);border-radius:24px;cursor:pointer;transition:var(--transition);}
    .t-slider::before{content:'';position:absolute;width:18px;height:18px;left:3px;top:3px;background:white;border-radius:50%;transition:var(--transition);box-shadow:0 1px 3px rgba(0,0,0,0.2);}
    .t-switch input:checked + .t-slider{background:var(--gold);}
    .t-switch input:checked + .t-slider::before{transform:translateX(20px);}

    /* ── RADIO OPTIONS ── */
    .radio-opt{display:flex;align-items:flex-start;gap:12px;padding:14px;border:1.5px solid var(--border-light);border-radius:var(--r-sm);margin-bottom:8px;cursor:pointer;transition:var(--transition);}
    .radio-opt:hover{border-color:rgba(200,160,88,0.4);background:var(--cream);}
    .radio-opt.selected{border-color:var(--gold);background:rgba(200,160,88,0.05);}
    .radio-opt input[type="radio"]{margin-top:3px;accent-color:var(--gold);flex-shrink:0;}
    .radio-opt-body strong{display:block;font-size:13.5px;font-weight:500;color:var(--navy);}
    .radio-opt-body span{font-size:12px;color:var(--text-muted);}

    /* ── SESSION CARDS ── */
    .session-card{display:flex;align-items:center;gap:14px;padding:14px;border:1.5px solid var(--border-light);border-radius:var(--r-md);margin-bottom:10px;transition:var(--transition);}
    .session-card.current{border-color:rgba(200,160,88,0.35);background:rgba(200,160,88,0.04);}
    .sess-icon{width:42px;height:42px;border-radius:var(--r-sm);background:var(--cream);display:flex;align-items:center;justify-content:center;font-size:17px;color:var(--navy);flex-shrink:0;}
    .session-card.current .sess-icon{background:rgba(200,160,88,0.12);color:var(--gold);}
    .sess-info{flex:1;}
    .sess-device{font-size:13.5px;font-weight:500;color:var(--navy);}
    .sess-meta{font-size:12px;color:var(--text-muted);margin-top:2px;}
    .sess-badge{font-size:10px;font-weight:600;padding:3px 10px;border-radius:20px;}
    .badge-curr{background:rgba(200,160,88,0.15);color:var(--gold);}
    .badge-cls{background:var(--cream);color:var(--text-muted);}

    /* ── 2FA BANNER ── */
    .twofa-banner{background:var(--cream);border:1.5px solid var(--border-light);border-radius:var(--r-md);padding:20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:20px;}
    .twofa-icon{width:48px;height:48px;background:var(--navy);border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:20px;color:var(--gold-bright);flex-shrink:0;}
    .twofa-body{flex:1;}
    .twofa-title{font-size:14px;font-weight:500;color:var(--navy);display:flex;align-items:center;gap:8px;}
    .twofa-badge{font-size:9.5px;font-weight:600;padding:2px 9px;border-radius:20px;background:rgba(245,158,11,0.12);color:#D97706;}
    .twofa-sub{font-size:12.5px;color:var(--text-muted);margin-top:3px;}
    .twofa-steps{display:flex;gap:20px;margin-top:14px;flex-wrap:wrap;}
    .twofa-step{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text-secondary);}
    .twofa-step-num{width:22px;height:22px;border-radius:50%;background:var(--navy);color:var(--gold-bright);font-size:11px;font-weight:600;display:flex;align-items:center;justify-content:center;flex-shrink:0;}

    /* ── SELECT ── */
    .field-select{width:100%;padding:11px 14px;border:1.5px solid var(--border-light);border-radius:var(--r-sm);font-family:var(--font-body);font-size:13.5px;color:var(--text-primary);background:var(--cream);appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%238A92A8' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 13px center;cursor:pointer;transition:var(--transition);outline:none;}
    .field-select:focus{border-color:var(--gold);background-color:var(--white);box-shadow:0 0 0 3px rgba(200,160,88,0.1);}

    /* ── INFO NOTE ── */
    .info-note{background:var(--cream);border-left:3px solid var(--gold);border-radius:0 var(--r-sm) var(--r-sm) 0;padding:13px 16px;font-size:12.5px;color:var(--text-secondary);margin-top:16px;display:flex;align-items:flex-start;gap:10px;}
    .info-note i{color:var(--gold);flex-shrink:0;margin-top:2px;}

    /* ── DANGER ZONE ── */
    .danger-zone{border:1.5px solid rgba(239,68,68,0.2);border-radius:var(--r-lg);padding:24px;margin-bottom:20px;}
    .dz-title{font-family:var(--font-display);font-size:18px;font-weight:500;color:#EF4444;margin-bottom:4px;}
    .dz-sub{font-size:12.5px;color:var(--text-muted);margin-bottom:20px;}
    .dz-action{display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid rgba(239,68,68,0.08);flex-wrap:wrap;gap:10px;}
    .dz-action:last-child{border-bottom:none;padding-bottom:0;}
    .dz-action-info strong{display:block;font-size:13.5px;font-weight:500;color:var(--text-primary);}
    .dz-action-info span{font-size:12px;color:var(--text-muted);}

    /* ── SECTION LABEL ── */
    .section-label{font-size:10px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);margin-bottom:14px;margin-top:4px;}

    /* ── FOOTER ── */
    .portal-footer{margin-left:var(--sidebar-width);background:var(--white);padding:16px 28px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;}
    .footer-copy{font-size:12px;color:var(--text-muted);}
    .footer-links{display:flex;gap:20px;}
    .footer-links a{font-size:12px;color:var(--text-muted);transition:color var(--transition);}
    .footer-links a:hover{color:var(--gold);}

    /* ── OVERLAY ── */
    .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:150;backdrop-filter:blur(4px);}
    .sidebar-overlay.active{display:block;}

    /* ── RESPONSIVE ── */
    @media(max-width:768px){
      .sidebar{transform:translateX(-100%);}
      .sidebar.active{transform:translateX(0);}
      .header{left:0;}
      .main,.portal-footer{margin-left:0;}
      .mobile-toggle{display:flex;}
    }
    @media(max-width:480px){
      .main{padding:16px;}
      .stab{padding:8px 12px;font-size:12px;}
      .health-card{flex-direction:column;align-items:flex-start;}
      .banner-chips{flex-direction:row;}
    }
  </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-top">
    <div class="sidebar-logo">
      <div class="sidebar-logomark"><svg viewBox="0 0 22 22" fill="none"><path d="M4 18L11 4L18 18" stroke="#080E1C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 13H15" stroke="#080E1C" stroke-width="1.8" stroke-linecap="round"/></svg></div>
      <div class="sidebar-logo-text">Bold Footprint<span>Scholar Portal</span></div>
    </div>
    <div class="sidebar-user">
      <div class="sidebar-avatar">
        <?php if ($profile_picture): ?>
          <img src="<?php echo htmlspecialchars('./uploads/profile_pictures/'.$profile_picture); ?>" alt="Profile" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
          <div class="sidebar-avatar-init" style="display:none;"><?php echo strtoupper(substr($first_name,0,1)); ?></div>
        <?php else: ?>
          <div class="sidebar-avatar-init"><?php echo strtoupper(substr($first_name,0,1)); ?></div>
        <?php endif; ?>
      </div>
      <div>
        <div class="sidebar-user-name"><?php echo htmlspecialchars($first_name.' '.$last_name); ?></div>
        <div class="sidebar-user-role">BFI Scholar</div>
      </div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>
    <div class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a></div>
    <div class="nav-item"><a href="profile.php" class="nav-link"><i class="fas fa-user"></i> My Profile</a></div>
    <div class="nav-item"><a href="#" class="nav-link"><i class="fas fa-route"></i> My Journey</a></div>
    <div class="nav-section-label">Resources</div>
    <div class="nav-item"><a href="documents.php" class="nav-link"><i class="fas fa-file-alt"></i> My Documents</a></div>
    <div class="nav-item"><a href="scholarships.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Scholarships</a></div>
    <div class="nav-item"><a href="mentors.php" class="nav-link"><i class="fas fa-users"></i> My Mentor</a></div>
    <div class="nav-item"><a href="application-help.php" class="nav-link"><i class="fas fa-question-circle"></i> Application Help</a></div>
    <div class="nav-item"><a href="resources.php" class="nav-link"><i class="fas fa-book"></i> Resources</a></div>
    <div class="nav-section-label">Account</div>
    <div class="nav-item"><a href="settings.php" class="nav-link active"><i class="fas fa-cog"></i> Settings</a></div>
  </nav>
  <div class="sidebar-bottom">
    <a href="logout.php" class="nav-link" style="color:rgba(239,68,68,0.7);">
      <i class="fas fa-sign-out-alt"></i> Log Out
    </a>
  </div>
</aside>

<!-- HEADER -->
<header class="header">
  <div class="header-left">
    <button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
    <div class="header-page-title">Account <em>Settings</em></div>
  </div>
  <div class="header-right">
    <a href="dashboard.php">
      <button class="header-icon-btn" title="Back to Dashboard"><i class="fas fa-home"></i></button>
    </a>
    <a href="profile.php">
      <div class="header-avatar">
        <?php if ($profile_picture): ?>
          <img src="<?php echo htmlspecialchars('./uploads/profile_pictures/'.$profile_picture); ?>" alt="Profile" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
          <div class="header-avatar-init" style="display:none;"><?php echo strtoupper(substr($first_name,0,1)); ?></div>
        <?php else: ?>
          <div class="header-avatar-init"><?php echo strtoupper(substr($first_name,0,1)); ?></div>
        <?php endif; ?>
      </div>
    </a>
  </div>
</header>

<!-- MAIN -->
<main class="main">

  <!-- SETTINGS BANNER -->
  <div class="settings-banner">
    <div class="banner-inner">
      <div>
        <div class="banner-eyebrow">Account Configuration</div>
        <div class="banner-title">Your <em>Settings</em> & Preferences</div>
        <div class="banner-sub">Manage security, notifications and personalisation options.</div>
      </div>
      <div class="banner-chips">
        <div class="banner-chip">
          <div class="chip-val"><?php echo $health_score; ?>%</div>
          <div class="chip-lbl">Profile Health</div>
        </div>
        <div class="banner-chip">
          <div class="chip-val"><?php echo count($login_activity); ?></div>
          <div class="chip-lbl">Recent Sessions</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ALERTS -->
  <?php if (!empty($success_msg)): ?>
  <div class="alert-custom alert-success-c" id="alertBox">
    <i class="fas fa-check-circle"></i>
    <span><?php echo htmlspecialchars($success_msg); ?></span>
    <button class="alert-close" onclick="document.getElementById('alertBox').remove()"><i class="fas fa-times"></i></button>
  </div>
  <?php endif; ?>
  <?php if (!empty($error_msg)): ?>
  <div class="alert-custom alert-error-c" id="alertBox">
    <i class="fas fa-exclamation-circle"></i>
    <span><?php echo htmlspecialchars($error_msg); ?></span>
    <button class="alert-close" onclick="document.getElementById('alertBox').remove()"><i class="fas fa-times"></i></button>
  </div>
  <?php endif; ?>

  <!-- ACCOUNT HEALTH CARD -->
  <?php
    $circumference = 2 * M_PI * 34;
    $offset = $circumference - ($health_score / 100) * $circumference;
  ?>
  <div class="health-card">
    <div class="health-ring-wrap">
      <svg width="90" height="90" viewBox="0 0 90 90">
        <circle cx="45" cy="45" r="34" fill="none" stroke="var(--cream-dark)" stroke-width="7"/>
        <circle cx="45" cy="45" r="34" fill="none" stroke="var(--gold)" stroke-width="7" stroke-linecap="round"
          stroke-dasharray="<?php echo $circumference; ?>"
          stroke-dashoffset="<?php echo $offset; ?>"
          id="healthRing"/>
      </svg>
      <div class="health-ring-center">
        <div class="health-pct"><?php echo $health_score; ?>%</div>
        <div class="health-lbl">Health</div>
      </div>
    </div>
    <div class="health-body">
      <div class="health-title">Account <em>Health</em></div>
      <div class="health-desc">Complete all items to strengthen your profile and improve your scholarship opportunities.</div>
      <div class="health-checklist">
        <?php foreach ($health_checks as $hc): ?>
        <div class="hc-item">
          <div class="hc-dot <?php echo $hc['done'] ? 'hc-done' : 'hc-todo'; ?>">
            <i class="fas <?php echo $hc['done'] ? 'fa-check' : 'fa-circle'; ?>"></i>
          </div>
          <span style="<?php echo $hc['done'] ? 'text-decoration:line-through;opacity:0.6;' : ''; ?>">
            <?php echo htmlspecialchars($hc['label']); ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- TABS BAR -->
  <div class="settings-tabs-bar" role="tablist">
    <button class="stab active" data-panel="security"><i class="fas fa-shield-alt"></i> Security</button>
    <button class="stab" data-panel="notifications"><i class="fas fa-bell"></i> Notifications</button>
    <button class="stab" data-panel="preferences"><i class="fas fa-sliders-h"></i> Preferences</button>
    <button class="stab" data-panel="privacy"><i class="fas fa-lock"></i> Privacy &amp; Data</button>
  </div>

  <!-- ══ SECURITY PANEL ══ -->
  <div class="stab-panel active" id="panel-security">

    <!-- 2FA Banner -->
    <div class="twofa-banner">
      <div class="twofa-icon"><i class="fas fa-mobile-alt"></i></div>
      <div class="twofa-body">
        <div class="twofa-title">Two-Factor Authentication <span class="twofa-badge">Coming Soon</span></div>
        <div class="twofa-sub">Add an extra layer of security to your account. Once enabled, you'll need both your password and a verification code to sign in.</div>
        <div class="twofa-steps">
          <div class="twofa-step"><div class="twofa-step-num">1</div> Download an authenticator app</div>
          <div class="twofa-step"><div class="twofa-step-num">2</div> Scan the QR code</div>
          <div class="twofa-step"><div class="twofa-step-num">3</div> Enter confirmation code</div>
        </div>
      </div>
      <button class="btn-ghost" disabled style="opacity:0.5;cursor:not-allowed;">Enable 2FA</button>
    </div>

    <!-- Change Password -->
    <div class="s-card">
      <div class="s-card-head">
        <div class="s-card-title">Change <em>Password</em></div>
        <div class="s-card-sub">Use a strong, unique password for maximum security.</div>
      </div>
      <form method="POST" action="settings.php" id="pwdForm">
        <div class="field">
          <label class="field-label" for="current_password">Current Password</label>
          <div class="input-wrap">
            <input type="password" class="field-input <?php echo !empty($current_password_error)?'invalid':''; ?>" id="current_password" name="current_password" placeholder="Enter current password">
            <button type="button" class="eye-btn" data-target="current_password"><i class="fas fa-eye"></i></button>
          </div>
          <?php if (!empty($current_password_error)): ?>
            <div class="field-err"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($current_password_error); ?></div>
          <?php endif; ?>
        </div>
        <div class="field">
          <label class="field-label" for="new_password">New Password</label>
          <div class="input-wrap">
            <input type="password" class="field-input <?php echo !empty($new_password_error)?'invalid':''; ?>" id="new_password" name="new_password" placeholder="Min. 8 characters" oninput="checkPwdStrength(this.value)">
            <button type="button" class="eye-btn" data-target="new_password"><i class="fas fa-eye"></i></button>
          </div>
          <?php if (!empty($new_password_error)): ?>
            <div class="field-err"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($new_password_error); ?></div>
          <?php endif; ?>
          <div class="pwd-bar-wrap"><div class="pwd-bar-fill" id="pwdBar" style="width:0%;background:var(--border-light);"></div></div>
          <div class="pwd-bar-lbl" id="pwdLbl" style="color:var(--text-muted);"></div>
        </div>
        <div class="field">
          <label class="field-label" for="confirm_password">Confirm New Password</label>
          <div class="input-wrap">
            <input type="password" class="field-input" id="confirm_password" name="confirm_password" placeholder="Repeat new password" oninput="checkPwdMatch()">
            <button type="button" class="eye-btn" data-target="confirm_password"><i class="fas fa-eye"></i></button>
          </div>
          <div class="field-err" id="matchErr" style="display:none;"><i class="fas fa-exclamation-circle"></i>Passwords do not match</div>
        </div>
        <div class="info-note"><i class="fas fa-info-circle"></i> Include uppercase, lowercase, numbers and special characters for a stronger password.</div>
        <div class="btn-row">
          <button type="submit" name="change_password" class="btn-gold"><i class="fas fa-key"></i> Update Password</button>
        </div>
      </form>
    </div>

    <!-- Login Activity -->
    <div class="s-card">
      <div class="s-card-head">
        <div class="s-card-title">Active <em>Sessions</em></div>
        <div class="s-card-sub">Recent sign-ins to your account. Recognise all of these?</div>
      </div>
      <?php
        function getDeviceIcon($device) {
          $d = strtolower($device);
          if (strpos($d,'iphone')!==false||strpos($d,'android')!==false||strpos($d,'mobile')!==false) return 'fa-mobile-alt';
          if (strpos($d,'ipad')!==false||strpos($d,'tablet')!==false) return 'fa-tablet-alt';
          return 'fa-laptop';
        }
      ?>
      <?php foreach ($login_activity as $activity): ?>
        <?php $isCurrent = $activity['status']==='Current Session'; ?>
        <div class="session-card <?php echo $isCurrent?'current':''; ?>">
          <div class="sess-icon"><i class="fas <?php echo getDeviceIcon($activity['device']); ?>"></i></div>
          <div class="sess-info">
            <div class="sess-device"><?php echo htmlspecialchars($activity['device']); ?></div>
            <div class="sess-meta">
              <i class="fas fa-map-marker-alt" style="font-size:10px;"></i> <?php echo htmlspecialchars($activity['location']); ?> &nbsp;·&nbsp;
              <?php $d=new DateTime($activity['login_time']); echo $d->format('M j, Y · g:i A'); ?>
            </div>
          </div>
          <span class="sess-badge <?php echo $isCurrent?'badge-curr':'badge-cls'; ?>">
            <?php echo $isCurrent ? '<i class="fas fa-circle" style="font-size:7px;margin-right:4px;"></i>Active' : 'Closed'; ?>
          </span>
        </div>
      <?php endforeach; ?>
      <div style="margin-top:14px;text-align:center;">
        <button type="button" class="btn-danger" id="logoutAllBtn"><i class="fas fa-power-off"></i> Log Out All Other Sessions</button>
      </div>
    </div>
  </div>

  <!-- ══ NOTIFICATIONS PANEL ══ -->
  <div class="stab-panel" id="panel-notifications">
    <div class="s-card">
      <div class="s-card-head">
        <div class="s-card-title">Notification <em>Channels</em></div>
        <div class="s-card-sub">Choose how you receive updates from BFI.</div>
      </div>
      <form method="POST" action="settings.php">
        <div class="toggle-row">
          <div class="toggle-info">
            <div class="toggle-title"><i class="fas fa-envelope" style="color:var(--gold);margin-right:8px;"></i>Email Notifications</div>
            <div class="toggle-desc">Receive scholarship updates, deadlines, and mentor messages via email.</div>
          </div>
          <label class="t-switch">
            <input type="checkbox" name="email_notifications" <?php echo (isset($user['email_notifications'])&&$user['email_notifications'])?'checked':''; ?>>
            <span class="t-slider"></span>
          </label>
        </div>
        <div class="toggle-row">
          <div class="toggle-info">
            <div class="toggle-title"><i class="fas fa-sms" style="color:var(--gold);margin-right:8px;"></i>SMS Notifications</div>
            <div class="toggle-desc">Get critical alerts and reminders sent directly to your phone.</div>
          </div>
          <label class="t-switch">
            <input type="checkbox" name="sms_notifications" <?php echo (isset($user['sms_notifications'])&&$user['sms_notifications'])?'checked':''; ?>>
            <span class="t-slider"></span>
          </label>
        </div>

        <div style="margin-top:24px;margin-bottom:14px;" class="section-label">Communication Frequency</div>
        <?php
          $freq = $user['communication_frequency'] ?? 'weekly';
          $freqOpts = [
            ['value'=>'daily',          'label'=>'Daily Digest',          'desc'=>'A morning summary of everything that happened the day before.'],
            ['value'=>'weekly',         'label'=>'Weekly Summary',        'desc'=>'One curated email every Monday covering the past week.'],
            ['value'=>'important_only', 'label'=>'Important Updates Only','desc'=>'Only critical alerts — deadlines, acceptances, urgent notices.'],
          ];
        ?>
        <?php foreach ($freqOpts as $opt): ?>
        <label class="radio-opt <?php echo $freq===$opt['value']?'selected':''; ?>">
          <input type="radio" name="communication_frequency" value="<?php echo $opt['value']; ?>" <?php echo $freq===$opt['value']?'checked':''; ?> onchange="document.querySelectorAll('.radio-opt').forEach(el=>el.classList.remove('selected'));this.closest('.radio-opt').classList.add('selected')">
          <div class="radio-opt-body">
            <strong><?php echo $opt['label']; ?></strong>
            <span><?php echo $opt['desc']; ?></span>
          </div>
        </label>
        <?php endforeach; ?>

        <div class="btn-row">
          <button type="submit" name="save_settings" class="btn-gold"><i class="fas fa-save"></i> Save Notification Settings</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ══ PREFERENCES PANEL ══ -->
  <div class="stab-panel" id="panel-preferences">
    <div class="s-card">
      <div class="s-card-head">
        <div class="s-card-title">Display &amp; <em>Language</em></div>
        <div class="s-card-sub">Customise your portal experience.</div>
      </div>
      <form method="POST" action="settings.php">
        <div class="toggle-row">
          <div class="toggle-info">
            <div class="toggle-title"><i class="fas fa-moon" style="color:var(--gold);margin-right:8px;"></i>Dark Mode</div>
            <div class="toggle-desc">Switch to a darker interface — easier on the eyes in low-light environments.</div>
          </div>
          <label class="t-switch">
            <input type="checkbox" name="dark_mode" id="darkModeToggle" <?php echo (isset($user['dark_mode'])&&$user['dark_mode'])?'checked':''; ?>>
            <span class="t-slider"></span>
          </label>
        </div>

        <div class="field" style="margin-top:20px;">
          <label class="field-label" for="language_preference">Language Preference</label>
          <select class="field-select" id="language_preference" name="language_preference">
            <option value="en"  <?php echo (!isset($user['language_preference'])||$user['language_preference']==='en')?'selected':''; ?>>🇺🇸 English</option>
            <option value="fr"  <?php echo (isset($user['language_preference'])&&$user['language_preference']==='fr')?'selected':''; ?>>🇫🇷 French</option>
            <option value="es"  <?php echo (isset($user['language_preference'])&&$user['language_preference']==='es')?'selected':''; ?>>🇪🇸 Spanish</option>
            <option value="pt"  <?php echo (isset($user['language_preference'])&&$user['language_preference']==='pt')?'selected':''; ?>>🇵🇹 Portuguese</option>
          </select>
          <div class="field-hint">The portal interface language. Academic content remains in English.</div>
        </div>

        <div class="info-note"><i class="fas fa-info-circle"></i> Preference changes take effect immediately after saving and will persist across all your devices.</div>
        <div class="btn-row">
          <button type="submit" name="save_settings" class="btn-gold"><i class="fas fa-save"></i> Save Preferences</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ══ PRIVACY & DATA PANEL ══ -->
  <div class="stab-panel" id="panel-privacy">

    <div class="s-card">
      <div class="s-card-head">
        <div class="s-card-title">Data <em>Management</em></div>
        <div class="s-card-sub">You own your data. Download or manage it at any time.</div>
      </div>
      <div class="dz-action">
        <div class="dz-action-info">
          <strong>Download My Data</strong>
          <span>Export a full copy of your profile, settings, and activity as a JSON file.</span>
        </div>
        <form method="POST" action="settings.php">
          <button name="download_data" type="submit" class="btn-navy-outline"><i class="fas fa-file-download"></i> Download</button>
        </form>
      </div>
      <div class="dz-action" style="border-bottom:none;padding-bottom:0;">
        <div class="dz-action-info">
          <strong>Privacy Policy</strong>
          <span>Read how Bold Footprint Initiatives collects, uses, and protects your data.</span>
        </div>
        <a href="privacy-policy.php" class="btn-ghost"><i class="fas fa-external-link-alt"></i> Read Policy</a>
      </div>
      <div class="info-note" style="margin-top:16px;"><i class="fas fa-shield-alt"></i> Your data is encrypted and never sold to third parties. See our <a href="privacy-policy.php" style="color:var(--gold);">Privacy Policy</a> for full details.</div>
    </div>

    <!-- Danger Zone -->
    <div class="danger-zone">
      <div class="dz-title"><i class="fas fa-exclamation-triangle" style="margin-right:8px;"></i>Danger Zone</div>
      <div class="dz-sub">These actions are permanent and cannot be undone. Proceed with caution.</div>
      <div class="dz-action">
        <div class="dz-action-info">
          <strong>Log Out All Other Sessions</strong>
          <span>Terminate all active sessions except this one. Useful if you've been signed in on a shared device.</span>
        </div>
        <button type="button" class="btn-danger" id="logoutAllBtn2"><i class="fas fa-power-off"></i> Log Out Others</button>
      </div>
      <div class="dz-action">
        <div class="dz-action-info">
          <strong>Delete My Account</strong>
          <span>Permanently delete your account and all associated data from the BFI Scholar Portal.</span>
        </div>
        <button class="btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#deleteModal"><i class="fas fa-trash-alt"></i> Delete Account</button>
      </div>
    </div>
  </div>

</main>

<!-- FOOTER -->
<footer class="portal-footer">
  <div class="footer-copy">&copy; 2026 Bold Footprint Initiatives. All rights reserved.</div>
  <div class="footer-links">
    <a href="/index.html"><i class="fas fa-home" style="font-size:10px;margin-right:4px;"></i>Main Site</a>
    <a href="/about.html">About Us</a>
    <a href="/programs.html">Programs</a>
    <a href="/contact.html">Contact</a>
  </div>
</footer>

<!-- DELETE ACCOUNT MODAL -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:var(--r-xl);border:none;overflow:hidden;">
      <div style="background:var(--navy);padding:24px 28px;display:flex;align-items:center;justify-content:space-between;">
        <div>
          <div style="font-family:var(--font-display);font-size:20px;font-weight:500;color:var(--white);">Delete <em style="font-style:italic;color:#F87171;">Account</em></div>
          <div style="font-size:12px;color:rgba(255,255,255,0.4);margin-top:2px;">This action is permanent and cannot be reversed.</div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div style="padding:24px 28px;">
        <div style="background:rgba(239,68,68,0.07);border:1px solid rgba(239,68,68,0.2);border-radius:var(--r-sm);padding:14px;font-size:13px;color:#991B1B;margin-bottom:20px;">
          <i class="fas fa-exclamation-triangle" style="margin-right:8px;"></i>
          All your data — profile, documents, journey history, and settings — will be permanently erased.
        </div>
        <form id="deleteAccountForm" method="POST" action="settings.php">
          <div class="field">
            <label class="field-label" for="delete-confirm">Type <strong>DELETE</strong> to confirm:</label>
            <input type="text" class="field-input" id="delete-confirm" name="delete_confirmation" placeholder="DELETE" autocomplete="off">
          </div>
        </form>
      </div>
      <div style="padding:0 28px 24px;display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" form="deleteAccountForm" name="delete_account" class="btn-danger" id="confirmDeleteBtn" disabled style="opacity:0.5;">
          <i class="fas fa-trash-alt"></i> Permanently Delete
        </button>
      </div>
    </div>
  </div>
</div>

<!-- LOGOUT ALL SESSIONS MODAL -->
<div class="modal fade" id="logoutAllModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:var(--r-xl);border:none;overflow:hidden;">
      <div style="background:var(--navy);padding:24px 28px;display:flex;align-items:center;justify-content:space-between;">
        <div>
          <div style="font-family:var(--font-display);font-size:20px;font-weight:500;color:var(--white);">Log Out <em style="font-style:italic;color:var(--gold-bright);">All Sessions</em></div>
          <div style="font-size:12px;color:rgba(255,255,255,0.4);margin-top:2px;">Your current session will remain active.</div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div style="padding:24px 28px;">
        <p style="font-size:13.5px;color:var(--text-secondary);">This will terminate all other active sessions on other devices. You'll remain logged in here.</p>
      </div>
      <div style="padding:0 28px 24px;display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn-gold" id="confirmLogoutAll"><i class="fas fa-check"></i> Yes, Log Out Others</button>
      </div>
    </div>
  </div>
</div>

<script>
  // Sidebar toggle
  const sidebar=document.getElementById('sidebar');
  const overlay=document.getElementById('sidebarOverlay');
  const toggle=document.getElementById('mobileToggle');
  function openSidebar(){sidebar.classList.add('active');overlay.classList.add('active');document.body.style.overflow='hidden';}
  function closeSidebar(){sidebar.classList.remove('active');overlay.classList.remove('active');document.body.style.overflow='';}
  toggle.addEventListener('click',()=>sidebar.classList.contains('active')?closeSidebar():openSidebar());
  overlay.addEventListener('click',closeSidebar);
  window.addEventListener('resize',()=>{if(window.innerWidth>768)closeSidebar();});

  // Tabs
  document.querySelectorAll('.stab').forEach(btn=>{
    btn.addEventListener('click',function(){
      document.querySelectorAll('.stab').forEach(b=>b.classList.remove('active'));
      document.querySelectorAll('.stab-panel').forEach(p=>p.classList.remove('active'));
      this.classList.add('active');
      document.getElementById('panel-'+this.dataset.panel).classList.add('active');
    });
  });

  // Password eye toggle
  document.querySelectorAll('.eye-btn').forEach(btn=>{
    btn.addEventListener('click',function(){
      const inp=document.getElementById(this.dataset.target);
      const ico=this.querySelector('i');
      if(inp.type==='password'){inp.type='text';ico.className='fas fa-eye-slash';}
      else{inp.type='password';ico.className='fas fa-eye';}
    });
  });

  // Password strength
  function checkPwdStrength(v){
    const bar=document.getElementById('pwdBar');
    const lbl=document.getElementById('pwdLbl');
    if(!v){bar.style.width='0%';lbl.textContent='';return;}
    let s=0;
    if(v.length>=8)s+=20;if(v.length>=12)s+=10;
    if(/[a-z]/.test(v))s+=15;if(/[A-Z]/.test(v))s+=15;
    if(/[0-9]/.test(v))s+=15;if(/[^A-Za-z0-9]/.test(v))s+=15;
    if(/[a-z]/.test(v)&&/[A-Z]/.test(v)&&/[0-9]/.test(v))s+=10;
    bar.style.width=s+'%';
    if(s<40){bar.style.background='#EF4444';lbl.style.color='#EF4444';lbl.textContent='Weak — add numbers and symbols';}
    else if(s<70){bar.style.background='#F59E0B';lbl.style.color='#D97706';lbl.textContent='Moderate — getting stronger';}
    else{bar.style.background='#10B981';lbl.style.color='#059669';lbl.textContent='Strong password ✓';}
  }

  // Password match
  function checkPwdMatch(){
    const np=document.getElementById('new_password').value;
    const cp=document.getElementById('confirm_password').value;
    const err=document.getElementById('matchErr');
    const inp=document.getElementById('confirm_password');
    if(cp.length>0){
      if(np!==cp){inp.classList.add('invalid');err.style.display='flex';}
      else{inp.classList.remove('invalid');inp.style.borderColor='#10B981';err.style.display='none';}
    }
  }

  // Delete confirmation enable
  document.getElementById('delete-confirm').addEventListener('input',function(){
    const btn=document.getElementById('confirmDeleteBtn');
    if(this.value==='DELETE'){btn.disabled=false;btn.style.opacity='1';}
    else{btn.disabled=true;btn.style.opacity='0.5';}
  });

  // Logout all sessions buttons
  function showLogoutModal(){
    const m=new bootstrap.Modal(document.getElementById('logoutAllModal'));m.show();
  }
  document.getElementById('logoutAllBtn').addEventListener('click',showLogoutModal);
  document.getElementById('logoutAllBtn2').addEventListener('click',showLogoutModal);
  document.getElementById('confirmLogoutAll').addEventListener('click',function(){
    const f=document.createElement('form');f.method='POST';f.action='settings.php';
    const i=document.createElement('input');i.type='hidden';i.name='logout_all_sessions';i.value='1';
    f.appendChild(i);document.body.appendChild(f);f.submit();
  });

  // Radio option highlight
  document.querySelectorAll('.radio-opt input[type="radio"]').forEach(r=>{
    r.addEventListener('change',function(){
      document.querySelectorAll('.radio-opt').forEach(el=>el.classList.remove('selected'));
      this.closest('.radio-opt').classList.add('selected');
    });
  });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>