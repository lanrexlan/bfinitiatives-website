<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['send_email'])) {
    echo json_encode(['success'=>false,'message'=>'Invalid request']);
    exit;
}

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/mailer.php';

$application_id = trim($_POST['application_id'] ?? '');
$subject        = trim($_POST['email_subject'] ?? '');
$message        = trim($_POST['email_message'] ?? '');

if (empty($application_id) || empty($subject) || empty($message)) {
    echo json_encode(['success'=>false,'message'=>'Missing required fields']);
    exit;
}

try {
    $db   = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT first_name,last_name,email FROM scholarship_applications WHERE application_id=:id");
    $stmt->execute([':id'=>$application_id]);
    $applicant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$applicant) {
        echo json_encode(['success'=>false,'message'=>'Applicant not found']);
        exit;
    }

    // Log communication
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS communication_logs(id SERIAL PRIMARY KEY,application_id VARCHAR(50),message_type VARCHAR(50),subject TEXT,content TEXT,sent_by VARCHAR(100),sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $conn->prepare("INSERT INTO communication_logs(application_id,message_type,subject,content,sent_by,sent_at) VALUES(:aid,'email',:sub,:cnt,:sb,CURRENT_TIMESTAMP)")
             ->execute([':aid'=>$application_id,':sub'=>$subject,':cnt'=>$message,':sb'=>$_SESSION['admin_name']??'Admin']);
    } catch (Exception $e) { error_log("Log failed: ".$e->getMessage()); }

    // Send email
    $result = sendEmail($applicant['email'], $subject, nl2br(htmlspecialchars($message)), $applicant['first_name']);

    if ($result['success']) {
        echo json_encode(['success'=>true,'message'=>"Email sent to {$applicant['first_name']} {$applicant['last_name']}"]);
    } else {
        echo json_encode(['success'=>false,'message'=>'Failed to send: '.$result['message']]);
    }

} catch (Exception $e) {
    error_log("send_applicant_email.php: ".$e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Server error: '.$e->getMessage()]);
}
exit;
?>