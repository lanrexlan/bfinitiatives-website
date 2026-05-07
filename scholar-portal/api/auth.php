<?php
require_once '../includes/db.php';

class Auth {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($email, $password) {
        try {
            $stmt = $this->conn->prepare(
                "SELECT user_id, password_hash FROM users WHERE email = ?"
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['user_id'];
                return true;
            }
            return false;
        } catch(PDOException $e) {
            return false;
        }
    }

    public function register($email, $password) {
        try {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare(
                "INSERT INTO users (email, password_hash) VALUES (?, ?)"
            );
            return $stmt->execute([$email, $password_hash]);
        } catch(PDOException $e) {
            return false;
        }
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function logout() {
        session_destroy();
        return true;
    }
}
?>