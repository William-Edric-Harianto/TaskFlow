<?php
require 'db.php';

try {
    // Add columns
    $pdo->exec("ALTER TABLE users ADD COLUMN username VARCHAR(50) UNIQUE AFTER name");
    $pdo->exec("ALTER TABLE users ADD COLUMN password VARCHAR(255) AFTER username");
    
    // Update dummy users with default usernames and passwords
    $admin_pw = password_hash('admin123', PASSWORD_DEFAULT);
    $test_pw = password_hash('test123', PASSWORD_DEFAULT);
    
    $pdo->exec("UPDATE users SET username = 'admin', password = '$admin_pw' WHERE user_id = 1");
    $pdo->exec("UPDATE users SET username = 'test', password = '$test_pw' WHERE user_id = 2");
    
    echo "Database updated successfully!";
} catch(PDOException $e) {
    // Ignore error if column already exists
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist.";
    } else {
        die("Error updating database: " . $e->getMessage());
    }
}
?>
