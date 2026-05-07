<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/mailer.php';

header('Content-Type: application/json');

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

ob_start();
$response = ['success'=>false,'message'=>'Unknown error'];

try {
    if (empty($_POST['application_id']) || empty($_POST['new_status'])) {
        throw new Exception('Missing required fields: application_id and new_status');
    }

    $application_id = trim($_POST['application_id']);
    $new_status      = trim($_POST['new_status']);
    $admin_comments  = trim($_POST['admin_comments'] ?? '');

    $allowed_statuses = ['pending','under_review','shortlisted','approved','rejected'];
    if (!in_array($new_status, $allowed_statuses, true)) {
        throw new Exception("Invalid status value: $new_status");
    }

    require_once 'includes/db.php';
    $db   = new Database();
    $conn = $db->getConnection();

    // Build query (check for admin_comments column)
    try {
        $cc = $conn->query("SELECT column_name FROM information_schema.columns WHERE table_name='scholarship_applications' AND column_name='admin_comments'");
        $has_comments = ($cc->rowCount() > 0);
    } catch (Exception $e) { $has_comments = false; }

    if ($has_comments) {
        $sql = "UPDATE scholarship_applications SET status=:s,admin_comments=:c,updated_at=CURRENT_TIMESTAMP WHERE application_id=:id";
        $params = [':s'=>$new_status,':c'=>$admin_comments,':id'=>$application_id];
    } else {
        $sql = "UPDATE scholarship_applications SET status=:s,updated_at=CURRENT_TIMESTAMP WHERE application_id=:id";
        $params = [':s'=>$new_status,':id'=>$application_id];
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        throw new Exception("No application found with ID: $application_id");
    }

    // Fetch applicant for email
    $app = $conn->prepare("SELECT email,first_name FROM scholarship_applications WHERE application_id=:id");
    $app->execute([':id'=>$application_id]);
    $applicant = $app->fetch(PDO::FETCH_ASSOC);

    if ($applicant && !empty($applicant['email'])) {
        $result = sendStatusUpdateEmail($applicant['email'],$applicant['first_name'],$application_id,$new_status,$admin_comments);
        if (!$result['success']) error_log("Email failed: ".$result['message']);
    }

    // Try to log
    try {
        $conn->prepare("INSERT INTO admin_logs(admin_id,action,details,ip_address) VALUES(:ai,'update_status',:d,:ip)")
             ->execute([':ai'=>$_SESSION['admin_id']??0,':d'=>"Updated $application_id to $new_status",':ip'=>$_SERVER['REMOTE_ADDR']]);
    } catch (Exception $e) { /* Non-critical */ }

    $response = ['success'=>true,'message'=>"Status updated to ".ucwords(str_replace('_',' ',$new_status))];

} catch (Exception $e) {
    error_log("update_status.php error: ".$e->getMessage());
    $response = ['success'=>false,'message'=>$e->getMessage()];
}

$errors = ob_get_clean();
if ($errors) { error_log("Captured output: $errors"); }

echo json_encode($response);
exit;
?>