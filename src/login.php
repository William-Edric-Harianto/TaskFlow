<?php
session_start();
require 'db.php';

// Check session endpoint for JS
if (isset($_GET['check'])) {
    header('Content-Type: application/json');
    echo json_encode(['logged_in' => isset($_SESSION['user_id'])]);
    exit;
}

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.html');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && $user['password'] === $password) {
        $_SESSION['user_id'] = $user['user_id'];
        header('Location: dashboard.html');
        exit;
    } else {
        $error = urlencode("Username atau password salah.");
        header("Location: login.html?error=$error");
        exit;
    }
} else {
    // If someone visits login.php directly without POST, redirect to login.html
    header('Location: login.html');
    exit;
}
?>
