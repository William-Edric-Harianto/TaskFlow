<?php
session_start();
require 'db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Validasi panjang
    if (strlen($username) > 20 || strlen($password) > 20) {
        $error = "Username dan Password maksimal 20 karakter.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, username, password) VALUES (?, ?, ?)");
            // Karena schema user menggunakan VARCHAR(20) untuk password, kita simpan plaintext
            // Peringatan: di production, pastikan panjang kolom password minimal 60 dan gunakan password_hash()
            $stmt->execute([$name, $username, $password]);
            
            // Set session dan login
            $_SESSION['user_id'] = $pdo->lastInsertId();
            header('Location: dashboard.php');
            exit;
        } catch(PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error = "Username sudah digunakan, silakan pilih yang lain.";
            } else {
                $error = "Terjadi kesalahan saat pendaftaran.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - TaskFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center p-6">
    <div class="bg-gray-800 p-8 rounded-2xl shadow-xl border border-gray-700 w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold tracking-tighter mb-2">Task<span class="text-blue-500">Flow</span></h1>
            <p class="text-gray-400">Buat akun untuk mulai mengelola tugas.</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-500/10 border border-red-500 text-red-500 px-4 py-3 rounded-lg mb-6 text-sm">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Nama Lengkap</label>
                <input type="text" name="name" required maxlength="20" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Username</label>
                <input type="text" name="username" required maxlength="20" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Password</label>
                <input type="password" name="password" required maxlength="20" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-semibold py-3 px-4 rounded-lg transition duration-200 mt-4">
                Daftar Sekarang
            </button>
        </form>

        <p class="text-gray-400 text-center mt-6 text-sm">
            Sudah punya akun? <a href="login.php" class="text-blue-500 hover:underline">Masuk di sini</a>
        </p>
    </div>
</body>
</html>
