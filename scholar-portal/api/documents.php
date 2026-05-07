<?php
require_once '../includes/db.php';

class Documents {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function uploadDocument($user_id, $file, $document_type) {
        try {
            $file_name = time() . '_' . $file['name'];
            $file_path = UPLOAD_PATH . $file_name;

            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                $stmt = $this->conn->prepare(
                    "INSERT INTO documents 
                    (user_id, document_type, file_name, file_path) 
                    VALUES (?, ?, ?, ?)"
                );
                return $stmt->execute([
                    $user_id,
                    $document_type,
                    $file_name,
                    $file_path
                ]);
            }
            return false;
        } catch(PDOException $e) {
            return false;
        }
    }

    public function getUserDocuments($user_id) {
        try {
            $stmt = $this->conn->prepare(
                "SELECT * FROM documents WHERE user_id = ?"
            );
            $stmt->execute([$user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return false;
        }
    }
}
?>