<?php
session_start();
require_once __DIR__ . '/app_bootstrap.php';

try {
    $db_connection = bfi_pg_connect('users');
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Connection failed. Please try again later.");
}

$error = null;
$application = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $tables = [
            'scholarship_applications',
            'primary_scholarship_applications',
            'secondary_scholarship_applications'
        ];

        foreach ($tables as $table) {
            $query = "SELECT * FROM $table WHERE application_id = $1";
            $result = pg_query_params($db_connection, $query, [$_POST['application_id']]);

            if (!$result) {
                throw new Exception(pg_last_error($db_connection));
            }

            $application = pg_fetch_assoc($result);
            if ($application) {
                break;
            }
        }

        if (!$application || !password_verify($_POST['password'], $application['password'])) {
            $application = null;
            $error = "Invalid application ID or password.";
        }
    } catch (Exception $e) {
        $application = null;
        $error = "An error occurred. Please try again.";
        error_log($e->getMessage());
    }
}

function bfi_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$statusKey = 'pending';
$statusLabel = 'Pending';
$programLabel = 'Scholarship';
$submittedOn = 'Not available';
$applicantName = '';
$statusMessage = "Your application has been received and is waiting to move into formal review.";
$nextStepTitle = "Keep your details handy.";
$nextStepCopy = "Use the same application ID and password any time you want to check for a fresh update.";

if ($application) {
    $statusKey = strtolower((string) ($application['status'] ?? 'pending'));
    if (!in_array($statusKey, ['pending', 'reviewing', 'approved', 'rejected'], true)) {
        $statusKey = 'pending';
    }

    $statusLabel = ucwords(str_replace('_', ' ', $statusKey));
    $programLabel = ucwords(str_replace('_', ' ', (string) ($application['program_type'] ?? 'Scholarship')));
    $submittedOn = !empty($application['created_at']) ? date('F j, Y', strtotime($application['created_at'])) : 'Not available';
    $applicantName = trim((string) ($application['first_name'] ?? '') . ' ' . (string) ($application['last_name'] ?? ''));

    if ($statusKey === 'reviewing') {
        $statusMessage = "Your materials are currently under review. The team may contact you if any clarification is needed.";
        $nextStepTitle = "Watch your inbox.";
        $nextStepCopy = "Review is ongoing. Please keep your email and phone number active while the process continues.";
    } elseif ($statusKey === 'approved') {
        $statusMessage = "Congratulations. Your application has been approved and the next steps should be shared with you by email.";
        $nextStepTitle = "Prepare for next steps.";
        $nextStepCopy = "Follow the instructions shared by the BFI team and keep your documents within reach.";
    } elseif ($statusKey === 'rejected') {
        $statusMessage = "Your application was reviewed carefully but was not selected in this cycle.";
        $nextStepTitle = "Stay ready for future calls.";
        $nextStepCopy = "You can explore future opportunities with BFI and submit again when a suitable call opens.";
    }
}

$reviewState = '';
$decisionState = '';

if ($application) {
    if ($statusKey === 'reviewing') {
        $reviewState = 'is-active';
    } elseif (in_array($statusKey, ['approved', 'rejected'], true)) {
        $reviewState = 'is-complete';
        $decisionState = 'is-active';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/Images/bfi-new-logo.svg">
    <link rel="shortcut icon" href="/Images/bfi-new-logo.svg">
    <title>Check Application Status | Bold Footprint Initiatives</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="site.css">
    <link rel="stylesheet" href="application-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script defer src="site.js"></script>
    <style>
        .status-page {
            padding-bottom: 44px;
        }

        .status-shell {
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(280px, 0.92fr);
            gap: 32px;
            align-items: start;
        }

        .status-main,
        .status-side,
        .lookup-form,
        .lookup-copy {
            display: grid;
            gap: 24px;
        }

        .lookup-panel {
            padding: clamp(24px, 3vw, 36px);
        }

        .status-title-row {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
        }

        .status-title-row .display-title {
            margin-bottom: 10px;
        }

        .status-badge.rejected {
            background: rgba(189, 64, 64, 0.12);
            color: #8a2424;
        }

        .status-progress-grid,
        .detail-grid {
            display: grid;
            gap: 18px;
        }

        .status-progress-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .detail-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .progress-milestone,
        .status-meta,
        .mini-card {
            border: 1px solid var(--line-soft);
            border-radius: 24px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(250, 246, 239, 0.94));
            box-shadow: var(--shadow-sm);
        }

        .progress-milestone,
        .mini-card {
            padding: 24px;
        }

        .progress-milestone {
            display: grid;
            gap: 14px;
        }

        .progress-icon {
            width: 52px;
            height: 52px;
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--line-soft);
            color: var(--text-muted);
            background: rgba(255, 255, 255, 0.92);
        }

        .progress-milestone.is-active,
        .progress-milestone.is-complete {
            border-color: rgba(200, 160, 88, 0.34);
            box-shadow: var(--shadow-md);
        }

        .progress-milestone.is-active .progress-icon,
        .progress-milestone.is-complete .progress-icon {
            background: var(--midnight);
            border-color: var(--midnight);
            color: var(--white);
        }

        .progress-kicker,
        .meta-label,
        .lookup-form label {
            margin: 0;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-muted);
        }

        .progress-milestone h3,
        .mini-card h3 {
            margin: 0;
            font-size: 24px;
            font-family: var(--font-display);
            font-weight: 500;
            color: var(--text-primary);
        }

        .progress-milestone p,
        .mini-card p,
        .field-help,
        .status-note p,
        .mini-card li,
        .lookup-copy .lead {
            margin: 0;
            color: var(--text-secondary);
        }

        .status-meta {
            padding: 22px;
            display: grid;
            gap: 6px;
        }

        .meta-value {
            margin: 0;
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
        }

        .status-note {
            padding: 24px;
            border-radius: 24px;
            background: linear-gradient(135deg, rgba(8, 14, 28, 0.98), rgba(13, 24, 41, 0.92));
            color: var(--white);
            box-shadow: var(--shadow-md);
        }

        .status-note h3 {
            margin: 0 0 10px;
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 500;
        }

        .status-note p {
            color: rgba(255, 255, 255, 0.76);
        }

        .lookup-form .form-field-stack {
            display: grid;
            gap: 10px;
        }

        .input-shell {
            position: relative;
        }

        .input-shell i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .input-shell input {
            width: 100%;
            min-height: 58px;
            border-radius: 18px;
            border: 1px solid var(--line-strong);
            background: rgba(255, 255, 255, 0.96);
            color: var(--text-primary);
            padding: 0 18px 0 52px;
            font: inherit;
        }

        .input-shell input:focus {
            outline: none;
            border-color: rgba(200, 160, 88, 0.68);
            box-shadow: 0 0 0 4px rgba(200, 160, 88, 0.12);
        }

        .field-help {
            font-size: 14px;
            line-height: 1.6;
        }

        .mini-card ul {
            display: grid;
            gap: 12px;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .mini-card li {
            position: relative;
            padding-left: 18px;
        }

        .mini-card li::before {
            content: "";
            position: absolute;
            left: 0;
            top: 10px;
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: var(--gold);
        }

        .inline-link {
            color: var(--text-primary);
            font-weight: 600;
        }

        .alert {
            margin-bottom: 24px;
        }

        @media (max-width: 980px) {
            .status-shell,
            .status-progress-grid,
            .detail-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .status-title-row {
                flex-direction: column;
            }

            .lookup-panel,
            .mini-card,
            .progress-milestone {
                padding: 22px;
            }
        }
    </style>
</head>
<body>
    <nav class="nav" data-nav>
        <div class="container">
            <div class="nav-inner">
                <a href="index.html" class="brand">
                    <div class="brand-mark">
                        <img src="/Images/bfi-new-logo.svg" alt="Bold Footprint Initiatives logo">
                    </div>
                    <div class="brand-copy">Bold Footprint<span>Initiatives</span></div>
                </a>
                <ul class="nav-links">
                    <li><a href="about.html">About</a></li>
                    <li><a href="programs.html">Programs</a></li>
                    <li><a href="stories.html">Stories</a></li>
                    <li><a href="achievements.html">Achievements</a></li>
                    <li><a href="talent.html">Talent</a></li>
                    <li><a href="mentor.html">Mentor</a></li>
                </ul>
                <div class="nav-actions">
                    <a href="contact.html" class="nav-link-muted">Contact</a>
                    <a href="check-status.php" class="btn btn-primary">Check status</a>
                </div>
                <button class="nav-toggle" data-nav-toggle aria-expanded="false" aria-label="Open navigation">Menu</button>
            </div>
            <div class="mobile-menu" data-mobile-menu>
                <a href="about.html">About</a>
                <a href="programs.html">Programs</a>
                <a href="stories.html">Stories</a>
                <a href="achievements.html">Achievements</a>
                <a href="talent.html">Talent</a>
                <a href="mentor.html">Mentor</a>
                <a href="apply.html">Apply</a>
                <a href="support.html">Support</a>
                <a href="contact.html">Contact</a>
            </div>
        </div>
    </nav>

    <header class="hero">
        <div class="container hero-grid">
            <div>
                <div class="breadcrumb"><a href="index.html">Home</a><span>/</span><span>Check Status</span></div>
                <div class="eyebrow eyebrow-light">Application Tracker</div>
                <h1 class="hero-title">Check your scholarship application <em>status</em>.</h1>
                <p class="hero-sub">Use the application ID and password shared after submission to track progress across BFI scholarship programmes.</p>
            </div>
            <div class="hero-aside">
                <div class="hero-card">
                    <div class="tag">What you need</div>
                    <h3>Your application ID and password.</h3>
                    <p>Both details were issued after your application was submitted. If you are applying for the first time, begin from the scholarship tracks page.</p>
                </div>
            </div>
        </div>
    </header>

    <main class="page-shell status-page">
        <section class="section section-compact">
            <div class="container">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <div class="alert-content">
                            <div class="alert-title">Authentication failed</div>
                            <p><?php echo bfi_escape($error); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($application): ?>
                    <div class="status-shell">
                        <article class="panel lookup-panel status-main">
                            <div class="status-title-row">
                                <div class="lookup-copy">
                                    <div class="eyebrow">Application Located</div>
                                    <h2 class="display-title"><?php echo $applicantName ? "Latest update for " . bfi_escape($applicantName) : "Latest application update"; ?></h2>
                                    <p class="lead">Here is the most recent recorded status for your BFI scholarship application.</p>
                                </div>
                                <span class="status-badge <?php echo bfi_escape($statusKey); ?>"><?php echo bfi_escape($statusLabel); ?></span>
                            </div>

                            <div class="status-progress-grid">
                                <article class="progress-milestone is-complete">
                                    <div class="progress-icon"><i class="fas fa-file-circle-check"></i></div>
                                    <p class="progress-kicker">Step 01</p>
                                    <h3>Submitted</h3>
                                    <p>Your application has been received successfully.</p>
                                </article>
                                <article class="progress-milestone <?php echo bfi_escape($reviewState); ?>">
                                    <div class="progress-icon"><i class="fas fa-magnifying-glass"></i></div>
                                    <p class="progress-kicker">Step 02</p>
                                    <h3>Review</h3>
                                    <p>BFI is assessing your materials, academic record, and supporting documents.</p>
                                </article>
                                <article class="progress-milestone <?php echo bfi_escape($decisionState); ?>">
                                    <div class="progress-icon"><i class="fas <?php echo $statusKey === 'rejected' ? 'fa-circle-info' : 'fa-envelope-open-text'; ?>"></i></div>
                                    <p class="progress-kicker">Step 03</p>
                                    <h3>Decision</h3>
                                    <p><?php echo $statusKey === 'approved' ? 'Your application has reached a successful outcome.' : ($statusKey === 'rejected' ? 'The review cycle has concluded for this application.' : 'A final outcome will appear here once review is complete.'); ?></p>
                                </article>
                            </div>

                            <div class="detail-grid">
                                <div class="status-meta">
                                    <p class="meta-label">Application ID</p>
                                    <p class="meta-value"><?php echo bfi_escape($application['application_id']); ?></p>
                                </div>
                                <div class="status-meta">
                                    <p class="meta-label">Programme</p>
                                    <p class="meta-value"><?php echo bfi_escape($programLabel); ?></p>
                                </div>
                                <div class="status-meta">
                                    <p class="meta-label">Submitted on</p>
                                    <p class="meta-value"><?php echo bfi_escape($submittedOn); ?></p>
                                </div>
                                <div class="status-meta">
                                    <p class="meta-label">Current stage</p>
                                    <p class="meta-value"><?php echo bfi_escape($statusLabel); ?></p>
                                </div>
                            </div>

                            <div class="status-note">
                                <h3>What this status means</h3>
                                <p><?php echo bfi_escape($statusMessage); ?></p>
                            </div>

                            <div class="btn-row">
                                <a href="apply.html" class="btn btn-primary">Explore scholarship tracks</a>
                                <a href="contact.html" class="btn btn-outlined">Contact BFI</a>
                            </div>
                        </article>

                        <aside class="status-side">
                            <article class="mini-card">
                                <div class="tag">Next Step</div>
                                <h3><?php echo bfi_escape($nextStepTitle); ?></h3>
                                <p><?php echo bfi_escape($nextStepCopy); ?></p>
                            </article>
                            <article class="mini-card">
                                <div class="tag">Need Help?</div>
                                <h3>Reach the team directly.</h3>
                                <ul>
                                    <li>Include your application ID in any message so the team can locate your record quickly.</li>
                                    <li>Email <a class="inline-link" href="mailto:info@bfinitiatives.com">info@bfinitiatives.com</a> for support.</li>
                                    <li>Use the contact page if you need to share additional context.</li>
                                </ul>
                            </article>
                        </aside>
                    </div>
                <?php else: ?>
                    <div class="status-shell">
                        <article class="panel lookup-panel">
                            <div class="lookup-copy">
                                <div class="eyebrow">Applicant Login</div>
                                <h2 class="display-title">Track your application in a few simple steps.</h2>
                                <p class="lead">Enter the credentials sent to you after submission to view the latest review stage for your application.</p>
                            </div>

                            <form method="POST" action="" class="lookup-form">
                                <div class="form-field-stack">
                                    <label for="application_id">Application ID</label>
                                    <div class="input-shell">
                                        <i class="fa-regular fa-id-card"></i>
                                        <input id="application_id" type="text" name="application_id" value="<?php echo isset($_POST['application_id']) ? bfi_escape($_POST['application_id']) : ''; ?>" placeholder="Enter your application ID" required>
                                    </div>
                                    <p class="field-help">Use the unique application ID shared after you completed your scholarship form.</p>
                                </div>

                                <div class="form-field-stack">
                                    <label for="password">Password</label>
                                    <div class="input-shell">
                                        <i class="fa-solid fa-lock"></i>
                                        <input id="password" type="password" name="password" placeholder="Enter your password" required>
                                    </div>
                                    <p class="field-help">Passwords are case-sensitive and must match the one sent to your email.</p>
                                </div>

                                <div class="btn-row">
                                    <button type="submit" class="submit-button"><i class="fas fa-search"></i> Check status</button>
                                    <a href="apply.html" class="btn btn-outlined">Start a new application</a>
                                </div>
                            </form>
                        </article>

                        <aside class="status-side">
                            <article class="mini-card">
                                <div class="tag">What you need</div>
                                <h3>Your application ID and password.</h3>
                                <p>These details were provided after you completed your application. Keep them somewhere secure for future checks.</p>
                            </article>
                            <article class="mini-card">
                                <div class="tag">Before you begin</div>
                                <ul>
                                    <li>Use the exact application ID shared with you after submission.</li>
                                    <li>Passwords are case-sensitive.</li>
                                    <li>Applications may remain pending while the review queue is still active.</li>
                                </ul>
                            </article>
                            <article class="mini-card">
                                <div class="tag">New Application</div>
                                <h3>Need to apply instead?</h3>
                                <p>Review the available scholarship tracks and choose the pathway that best matches your level.</p>
                                <div class="btn-row">
                                    <a href="apply.html" class="btn btn-secondary">View scholarship tracks</a>
                                </div>
                            </article>
                        </aside>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <a href="index.html" class="brand" style="margin-bottom:18px;">
                        <div class="brand-mark">
                            <img src="/Images/bfi-new-logo.svg" alt="Bold Footprint Initiatives logo">
                        </div>
                        <div class="brand-copy">Bold Footprint<span>Initiatives</span></div>
                    </a>
                    <p>Clear application tracking helps every applicant stay informed, prepared, and confident throughout the review process.</p>
                </div>
                <div>
                    <h4>Explore</h4>
                    <ul>
                        <li><a href="about.html">About</a></li>
                        <li><a href="programs.html">Programs</a></li>
                        <li><a href="stories.html">Stories</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Get Involved</h4>
                    <ul>
                        <li><a href="apply.html">Apply</a></li>
                        <li><a href="mentor.html">Mentor</a></li>
                        <li><a href="support.html">Support</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Contact</h4>
                    <ul>
                        <li><a href="mailto:info@bfinitiatives.com">info@bfinitiatives.com</a></li>
                        <li><a href="contact.html">Contact page</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2026 Bold Footprint Initiatives. All rights reserved.</p>
                <div class="footer-socials">
                    <a href="https://web.facebook.com/profile.php?id=61574771032448" target="_blank" rel="noopener" aria-label="Facebook">Fb</a>
                    <a href="https://x.com/BFIniatiatives" target="_blank" rel="noopener" aria-label="X (formerly Twitter)">X</a>
                    <a href="https://www.linkedin.com/company/bright-future-initiative" target="_blank" rel="noopener" aria-label="LinkedIn">In</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
