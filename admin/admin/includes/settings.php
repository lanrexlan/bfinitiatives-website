<?php
// includes/Settings.php

class Settings {
    private static $instance = null;
    private $settings = [];
    private $db;
    
    private function __construct() {
        require_once 'db.php';
        $this->db = new Database();
        $this->loadSettings();
    }
    
    // Singleton pattern to ensure only one instance
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Settings();
        }
        return self::$instance;
    }
    
    // Load all settings from database
    private function loadSettings() {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings");
            $stmt->execute();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            error_log("Failed to load settings: " . $e->getMessage());
            // Set some default values as fallback
            $this->settings = [
                'primary_scholarship_status' => 'closed',
                'secondary_scholarship_status' => 'closed',
                'graduate_scholarship_status' => 'closed',
                'application_message' => 'Applications are currently closed. Please check back later.',
                'maintenance_message' => 'The system is currently under maintenance. Please try again later.',
                'notifications_enabled' => 'true',
                'max_file_size' => '5MB',
                'allowed_file_types' => 'pdf,doc,docx,jpg,png',
                'default_pagination' => '10'
            ];
        }
    }
    
    // Get a specific setting
    public function get($key, $default = null) {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
    
    // Check if applications are open for a specific type
    public function isApplicationOpen($type) {
        $key = "{$type}_status";
        return $this->get($key) === 'open';
    }
    
    // Get application status message
    public function getApplicationStatusMessage($type) {
        $key = "{$type}_status";
        $status = $this->get($key);
        
        if ($status === 'maintenance') {
            return $this->get('maintenance_message');
        } elseif ($status === 'closed') {
            return $this->get('application_message');
        }
        
        return '';
    }
    
    // Refresh settings from database
    public function refresh() {
        $this->loadSettings();
    }
}
?>