<?php
require_once '../includes/db.php';

class Profile {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getProfile($user_id) {
        try {
            $stmt = $this->conn->prepare(
                "SELECT * FROM user_profiles WHERE user_id = ?"
            );
            $stmt->execute([$user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return false;
        }
    }

    public function updateProfile($user_id, $data) {
        try {
            $stmt = $this->conn->prepare(
                "UPDATE user_profiles SET 
                first_name = ?, 
                last_name = ?, 
                contact_number = ?, 
                address = ? 
                WHERE user_id = ?"
            );
            return $stmt->execute([
                $data['first_name'],
                $data['last_name'],
                $data['contact_number'],
                $data['address'],
                $user_id
            ]);
        } catch(PDOException $e) {
            return false;
        }
    }
}
?>