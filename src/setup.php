<?php
$host = 'localhost';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = file_get_contents('database.sql');
    $pdo->exec($sql);
    echo "Database setup successfully!";
} catch(PDOException $e) {
    die("Setup failed: " . $e->getMessage());
}
?>
