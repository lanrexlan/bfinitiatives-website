<?php
session_start();

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    $_SESSION['error'] = "Please log in to access documents";
    header('Location: admin-login.php'); exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid document ID";
    header('Location: admin-document-review.php'); exit();
}

require_once 'includes/config.php';
require_once 'includes/db.php';

$document_id = (int)$_GET['id'];

try {
    $db   = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT file_name,document_type FROM user_documents WHERE id=:id");
    $stmt->execute([':doc_id'=>$document_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        $_SESSION['error_message'] = "Document not found";
        header('Location: admin-document-review.php'); exit();
    }

    $docRoot   = rtrim($_SERVER['DOCUMENT_ROOT']??'','/\\');
    $file_path = $docRoot.'/scholar-portal/uploads/documents/'.$document['file_name'];

    // Try alternative paths if needed
    if (!file_exists($file_path)) {
        $alts = [
            $docRoot.'/uploads/documents/'.$document['file_name'],
            dirname(__FILE__).'/uploads/documents/'.$document['file_name'],
            '../uploads/documents/'.$document['file_name'],
        ];
        foreach ($alts as $alt) {
            if (file_exists($alt)) { $file_path=$alt; break; }
        }
    }

    if (!file_exists($file_path)) {
        error_log("File not found: $file_path");
        $_SESSION['error_message'] = "File not found on server. Path checked: $file_path";
        header('Location: admin-document-review.php?id='.$document_id); exit();
    }

    // Log download
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS document_access_logs(id SERIAL PRIMARY KEY,document_id INTEGER,user_id INTEGER,action_type VARCHAR(50),action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,ip_address VARCHAR(45))");
        $conn->prepare("INSERT INTO document_access_logs(document_id,user_id,action_type,ip_address) VALUES(:di,:ui,'download',:ip)")
             ->execute([':di'=>$document_id,':ui'=>$_SESSION['admin_id']??0,':ip'=>$_SERVER['REMOTE_ADDR']]);
    } catch (Exception $e) { error_log("Log failed: ".$e->getMessage()); }

    $ext = strtolower(pathinfo($file_path,PATHINFO_EXTENSION));
    $mime_map = [
        'pdf'=>'application/pdf','doc'=>'application/msword',
        'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif',
    ];
    $content_type = $mime_map[$ext] ?? 'application/octet-stream';

    // Serve file
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: '.$content_type);
    header('Content-Disposition: attachment; filename="'.basename($document['file_name']).'"');
    header('Content-Length: '.filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    readfile($file_path);
    exit;

} catch (Exception $e) {
    error_log("download-document.php: ".$e->getMessage());
    $_SESSION['error_message'] = "Download error: ".$e->getMessage();
    header('Location: admin-document-review.php'); exit();
}
?>