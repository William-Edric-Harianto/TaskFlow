<?php
session_start();
require 'db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.html');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Validasi panjang
    if (strlen($username) > 20 || strlen($password) > 20) {
        $error = urlencode("Username dan Password maksimal 20 karakter.");
        header("Location: register.html?error=$error");
        exit;
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, username, password) VALUES (?, ?, ?)");
            $stmt->execute([$name, $username, $password]);
            
            $_SESSION['user_id'] = $pdo->lastInsertId();
            header('Location: dashboard.html');
            exit;
        } catch(PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error = urlencode("Username sudah digunakan, silakan pilih yang lain.");
            } else {
                $error = urlencode("Terjadi kesalahan saat pendaftaran.");
            }
            header("Location: register.html?error=$error");
            exit;
        }
    }
} else {
    header('Location: register.html');
    exit;
}
?>
