<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

// Initialize variables
$error_message = '';
$document = null;
$download_url = '';

// Get and validate token
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error_message = "Invalid or missing share token.";
} else {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Check if document_shares table exists in PostgreSQL
        $table_check = $conn->query("
            SELECT EXISTS (
                SELECT 1 
                FROM information_schema.tables 
                WHERE table_name = 'document_shares'
                AND table_schema = 'public'
            )
        ");
        
        if (!$table_check->fetchColumn()) {
            // Create document_shares table for PostgreSQL
            $conn->exec("
                CREATE TABLE document_shares (
                    id SERIAL PRIMARY KEY,
                    document_id INTEGER NOT NULL,
                    share_token VARCHAR(255) UNIQUE NOT NULL,
                    created_by INTEGER NOT NULL,
                    expires_at TIMESTAMP NOT NULL,
                    access_count INTEGER DEFAULT 0,
                    max_access INTEGER DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT fk_doc_shares_document FOREIGN KEY (document_id) REFERENCES user_documents(id) ON DELETE CASCADE,
                    CONSTRAINT fk_doc_shares_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
        }
        
        // Find the shared document
        $stmt = $conn->prepare("
            SELECT 
                ds.*,
                ud.file_name,
                ud.document_type,
                ud.upload_date,
                ud.review_status,
                u.first_name,
                u.last_name
            FROM document_shares ds
            JOIN user_documents ud ON ds.document_id = ud.id
            JOIN users u ON ds.created_by = u.id
            WHERE ds.share_token = $1 AND ds.expires_at > CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([$token]);
        $share_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$share_data) {
            $error_message = "This share link has expired or is invalid.";
        } else {
            // Check access limits
            if ($share_data['max_access'] && $share_data['access_count'] >= $share_data['max_access']) {
                $error_message = "This share link has reached its maximum number of accesses.";
            } else {
                $document = $share_data;
                $download_url = 'uploads/documents/' . $document['file_name'];
                
                // Increment access count
                $update_stmt = $conn->prepare("
                    UPDATE document_shares 
                    SET access_count = access_count + 1 
                    WHERE id = $1
                ");
                $update_stmt->execute([$document['id']]);
                
                // Log the access if document_activities table exists
                $activities_check = $conn->query("
                    SELECT EXISTS (
                        SELECT 1 
                        FROM information_schema.tables 
                        WHERE table_name = 'document_activities'
                        AND table_schema = 'public'
                    )
                ");
                
                if ($activities_check->fetchColumn()) {
                    try {
                        $log_stmt = $conn->prepare("
                            INSERT INTO document_activities (document_id, user_id, activity_type, description) 
                            VALUES ($1, $2, $3, $4)
                        ");
                        $log_stmt->execute([
                            $document['document_id'], 
                            $document['created_by'], 
                            'viewed', 
                            'Document accessed via share link'
                        ]);
                    } catch (Exception $e) {
                        // Log error but don't fail the request
                        error_log("Error logging document activity: " . $e->getMessage());
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Shared document error: " . $e->getMessage());
        $error_message = "An error occurred while accessing the document.";
    }
}

// Helper functions (same as before)
function getDocumentTypeLabel($type) {
    switch($type) {
        case 'cv': return 'CV/Resume';
        case 'statement': return 'Personal Statement';
        case 'research': return 'Research Proposal';
        case 'recommendation': return 'Recommendation Letter';
        case 'language': return 'Language Test';
        default: return 'Document';
    }
}

function getDocumentTypeIcon($type) {
    switch($type) {
        case 'cv': return 'fa-file-alt';
        case 'statement': return 'fa-pen-fancy';
        case 'research': return 'fa-flask';
        case 'recommendation': return 'fa-envelope';
        case 'language': return 'fa-language';
        default: return 'fa-file';
    }
}

function getStatusBadgeClass($status) {
    switch($status) {
        case 'pending': return 'bg-warning';
        case 'in_review': return 'bg-primary';
        case 'needs_revision': return 'bg-danger';
        case 'approved': return 'bg-success';
        default: return 'bg-secondary';
    }
}

function canPreview($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['pdf', 'txt', 'jpg', 'jpeg', 'png', 'gif']);
}
?>

<!-- The HTML remains the same as the previous version -->


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $document ? 'Shared Document - ' . htmlspecialchars($document['file_name']) : 'Document Not Found'; ?> - Scholar Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Use the same CSS variables and base styles from documents.php */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --primary-dark: #3a0ca3;
            --secondary: #8ecae6;
            --accent: #f72585;
            --success: #4cc9f0;
            --warning: #ffbe0b;
            --danger: #d00000;
            --dark: #212529;
            --light: #f8f9fa;
            --gray: #adb5bd;
            --white: #ffffff;
            
            --shadow-sm: 0 .125rem .25rem rgba(0,0,0,.075);
            --shadow: 0 .5rem 1rem rgba(0,0,0,.15);
            --shadow-lg: 0 1rem 3rem rgba(0,0,0,.175);
            
            --gradient-primary: linear-gradient(120deg, #4361ee, #4cc9f0);
            --border-radius-sm: 0.5rem;
            --border-radius: 1rem;
        }

        body {
            background-color: #f3f4f9;
            color: var(--dark);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        /* Animated background */
        .bg-animated {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
            background: linear-gradient(135deg, rgba(73, 86, 238, 0.05) 0%, rgba(72, 149, 239, 0.05) 100%);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .shared-document-container {
            max-width: 800px;
            width: 100%;
            margin: 20px;
        }

        .shared-header {
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 25px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .shared-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--gradient-primary);
        }

        .shared-header h1 {
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 10px;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .shared-header p {
            color: var(--gray);
            margin-bottom: 0;
        }

        .document-preview-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s;
        }

        .document-preview-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .document-header-section {
            padding: 25px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
        }

        .document-header-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--gradient-primary);
        }

        .document-icon-large {
            width: 70px;
            height: 70px;
            border-radius: var(--border-radius);
            background: rgba(67, 97, 238, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 2rem;
            flex-shrink: 0;
        }

        .document-info h2 {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
        }

        .document-meta {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .document-actions {
            padding: 25px;
            background: rgba(248, 249, 250, 0.5);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            padding: 12px 24px;
            border-radius: var(--border-radius-sm);
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(67, 97, 238, 0.3);
        }

        .btn-outline-secondary {
            border-color: var(--gray);
            color: var(--gray);
            padding: 12px 24px;
            border-radius: var(--border-radius-sm);
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-outline-secondary:hover {
            background: var(--gray);
            border-color: var(--gray);
            color: white;
            transform: translateY(-2px);
        }

        .document-preview-section {
            padding: 25px;
            min-height: 400px;
        }

        .document-viewer {
            width: 100%;
            height: 500px;
            border: none;
            border-radius: var(--border-radius-sm);
        }

        .preview-image {
            max-width: 100%;
            height: auto;
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-sm);
        }

        .file-preview-placeholder {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
        }

        .file-preview-placeholder i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: var(--primary);
        }

        .error-container {
            background: white;
            padding: 40px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
        }

        .error-icon {
            font-size: 4rem;
            color: var(--danger);
            margin-bottom: 20px;
        }

        .shared-by {
            background: rgba(67, 97, 238, 0.05);
            padding: 15px 25px;
            border-radius: var(--border-radius-sm);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .shared-by i {
            color: var(--primary);
        }

        .badge {
            padding: 5px 12px;
            font-weight: 500;
            font-size: 0.75rem;
            border-radius: 20px;
        }

        .watermark {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .shared-document-container {
                margin: 10px;
            }
            
            .shared-header {
                padding: 20px;
            }
            
            .document-header-section {
                padding: 20px;
                flex-direction: column;
                text-align: center;
            }
            
            .document-actions {
                padding: 20px;
            }
            
            .document-preview-section {
                padding: 20px;
            }
            
            .document-viewer {
                height: 400px;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animated"></div>

    <div class="shared-document-container">
        <?php if ($error_message): ?>
            <div class="error-container">
                <i class="fas fa-exclamation-circle error-icon"></i>
                <h2>Access Denied</h2>
                <p class="text-muted mb-4"><?php echo htmlspecialchars($error_message); ?></p>
                <a href="/" class="btn btn-primary">
                    <i class="fas fa-home me-2"></i>Go to Homepage
                </a>
            </div>
        <?php else: ?>
            <div class="shared-header">
                <h1><i class="fas fa-share me-2"></i>Shared Document</h1>
                <p>You are viewing a document shared via Scholar Portal</p>
            </div>

            <div class="document-preview-card">
                <div class="watermark">
                    <i class="fas fa-share me-1"></i>Shared
                </div>
                
                <div class="document-header-section">
                    <div class="document-icon-large">
                        <i class="fas <?php echo getDocumentTypeIcon($document['document_type']); ?>"></i>
                    </div>
                    <div class="document-info flex-grow-1">
                        <h2><?php echo getDocumentTypeLabel($document['document_type']); ?></h2>
                        <div class="document-meta">
                            <div class="mb-1">
                                <i class="fas fa-file me-2"></i>
                                <strong>File:</strong> <?php echo htmlspecialchars($document['file_name']); ?>
                            </div>
                            <div class="mb-1">
                                <i class="fas fa-calendar me-2"></i>
                                <strong>Uploaded:</strong> <?php echo date('F j, Y', strtotime($document['upload_date'])); ?>
                            </div>
                            <div>
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Status:</strong> 
                                <span class="badge <?php echo getStatusBadgeClass($document['review_status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $document['review_status'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="shared-by">
                    <i class="fas fa-user"></i>
                    <span>Shared by <strong><?php echo htmlspecialchars($document['first_name'] . ' ' . $document['last_name']); ?></strong></span>
                </div>

                <?php if (canPreview($document['file_name'])): ?>
                    <div class="document-preview-section">
                        <?php 
                        $file_ext = strtolower(pathinfo($document['file_name'], PATHINFO_EXTENSION));
                        if ($file_ext === 'pdf'): ?>
                            <iframe src="<?php echo htmlspecialchars($download_url); ?>" class="document-viewer"></iframe>
                        <?php elseif (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                            <div class="text-center">
                                <img src="<?php echo htmlspecialchars($download_url); ?>" 
                                     alt="<?php echo htmlspecialchars($document['file_name']); ?>" 
                                     class="preview-image">
                            </div>
                        <?php else: ?>
                            <div class="file-preview-placeholder">
                                <i class="fas fa-file"></i>
                                <h5><?php echo htmlspecialchars($document['file_name']); ?></h5>
                                <p>Preview not available for this file type.</p>
                                <p>Click download to view the complete file.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="document-preview-section">
                        <div class="file-preview-placeholder">
                            <i class="fas fa-file"></i>
                            <h5><?php echo htmlspecialchars($document['file_name']); ?></h5>
                            <p>Preview not available for this file type.</p>
                            <p>Click download to view the complete file.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="document-actions">
                    <a href="<?php echo htmlspecialchars($download_url); ?>" 
                       class="btn btn-primary" 
                       download
                       onclick="trackDownload()">
                        <i class="fas fa-download me-2"></i>Download Document
                    </a>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print
                    </button>
                    <a href="/" class="btn btn-outline-secondary">
                        <i class="fas fa-home me-2"></i>Scholar Portal
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function trackDownload() {
            // Track download event (you can send this to analytics or backend)
            console.log('Document downloaded');
            
            // You could send an AJAX request to track the download
            fetch('track-document-access.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'download',
                    token: '<?php echo htmlspecialchars($token); ?>'
                })
            }).catch(error => console.log('Tracking error:', error));
        }

        // Add print styles
        const printStyles = `
            @media print {
                .document-actions,
                .shared-by,
                .watermark {
                    display: none !important;
                }
                
                .document-preview-card {
                    box-shadow: none !important;
                    border: 1px solid #ddd;
                }
                
                body {
                    background: white !important;
                }
                
                .bg-animated {
                    display: none !important;
                }
            }
        `;
        
        const style = document.createElement('style');
        style.textContent = printStyles;
        document.head.appendChild(style);
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>