<?php
// Create this file as create_contact_table.php and run it once
require_once 'includes/config.php';
require_once 'includes/db.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $query = "
    CREATE TABLE IF NOT EXISTS contact_messages (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        subject VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        read_status BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $conn->exec($query);
    echo "Contact messages table created successfully";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>