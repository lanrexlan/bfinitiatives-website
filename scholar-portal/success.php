<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Application Submitted - BFI Initiatives</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .success-container {
            max-width: 600px;
            width: 100%;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 40px;
            text-align: center;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: scaleIn 0.5s ease-out;
        }

        .warning-icon {
            background: #ffc107;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }

        .success-icon i {
            color: white;
            font-size: 40px;
        }

        .alert {
            background-color: #fff3cd;
            border-color: #ffeeba;
            color: #856404;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            font-size: 1.1rem;
            line-height: 1.6;
        }

        .application-id-container {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin: 30px 0;
            border: 2px dashed #dee2e6;
        }

        .application-id {
            font-family: 'Courier New', monospace;
            font-size: 2rem;
            font-weight: bold;
            color: #28a745;
            letter-spacing: 2px;
            margin: 10px 0;
        }

        .copy-btn {
            background: #e9ecef;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            color: #495057;
            margin-top: 10px;
        }

        .copy-btn:hover {
            background: #dee2e6;
        }

        .copy-btn i {
            margin-right: 5px;
        }

        .btn {
            padding: 12px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 10px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: #007bff;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.2);
        }

        .btn-success {
            background-color: #28a745;
            border: none;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .buttons-container {
            margin-top: 30px;
        }

        .important-notice {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 0.9rem;
            text-align: left;
        }

        .important-notice h5 {
            color: #721c24;
            margin-bottom: 10px;
        }

        @media (max-width: 576px) {
            .success-container {
                padding: 20px;
            }

            .btn {
                display: block;
                margin: 10px 0;
                width: 100%;
            }

            .application-id {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon <?php echo strpos($_SESSION['message'] ?? '', 'issue sending') ? 'warning-icon' : ''; ?>">
            <i class="<?php echo strpos($_SESSION['message'] ?? '', 'issue sending') ? 'fas fa-exclamation-triangle' : 'fas fa-check'; ?>"></i>
        </div>

        <h2 class="mb-4">Application Submitted Successfully!</h2>

        <?php if (isset($_SESSION['message'])): ?>
            <?php
            $message = $_SESSION['message'];
            unset($_SESSION['message']);
            
            // Extract application ID
            preg_match('/BFI\d+/', $message, $matches);
            $applicationId = $matches[0] ?? null;
            ?>

            <?php if (strpos($message, 'issue sending') !== false): ?>
                <div class="important-notice">
                    <h5><i class="fas fa-exclamation-circle"></i> Important Notice</h5>
                    <p>There was an issue sending the confirmation email. Please make sure to:</p>
                    <ul>
                        <li>Save your Application ID securely</li>
                        <li>Take a screenshot of this page</li>
                        <li>Check your spam folder for the confirmation email</li>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="application-id-container">
                <p class="mb-2">Your Application ID</p>
                <div class="application-id" id="applicationId"><?php echo $applicationId; ?></div>
                <button class="copy-btn" onclick="copyApplicationId()">
                    <i class="far fa-copy"></i> Copy ID
                </button>
            </div>
        <?php endif; ?>

        <div class="buttons-container">
            <a href="check-status.php" class="btn btn-success">
                <i class="fas fa-search mr-2"></i>Check Application Status
            </a>
            <a href="https://bfinitiatives.com" class="btn btn-primary">
                <i class="fas fa-home mr-2"></i>Return to Home
            </a>
        </div>
    </div>

    <script>
        function copyApplicationId() {
            const applicationId = document.getElementById('applicationId').innerText;
            navigator.clipboard.writeText(applicationId).then(() => {
                const copyBtn = document.querySelector('.copy-btn');
                copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                setTimeout(() => {
                    copyBtn.innerHTML = '<i class="far fa-copy"></i> Copy ID';
                }, 2000);
            });
        }
    </script>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>