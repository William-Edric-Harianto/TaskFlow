<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get User Info
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Create Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_project'])) {
    $name = $_POST['name'];
    $desc = $_POST['description'];
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO Project (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $desc]);
        $project_id = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("INSERT INTO users_project (user_id, project_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $project_id]);
        
        $stmt = $pdo->prepare("INSERT INTO Activity (action, date, time, project_id, user_id) VALUES (?, CURDATE(), CURTIME(), ?, ?)");
        $stmt->execute(['Created Project', $project_id, $user_id]);
        
        $pdo->commit();
        header('Location: dashboard.php');
        exit;
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = "Gagal membuat proyek.";
    }
}

// Get User Projects
$stmt = $pdo->prepare("
    SELECT p.*, 
    (SELECT COUNT(*) FROM Task t WHERE t.project_id = p.project_id) as total_tasks,
    (SELECT COUNT(*) FROM Task t WHERE t.project_id = p.project_id AND t.status = 'done') as completed_tasks
    FROM Project p 
    JOIN users_project up ON p.project_id = up.project_id 
    WHERE up.user_id = ?
");
$stmt->execute([$user_id]);
$projects = $stmt->fetchAll();

// Get Recent Activities
$stmt = $pdo->prepare("
    SELECT a.*, p.name as project_name, t.name as task_name 
    FROM Activity a
    LEFT JOIN Project p ON a.project_id = p.project_id
    LEFT JOIN Task t ON a.task_id = t.task_id
    WHERE a.user_id = ?
    ORDER BY a.date DESC, a.time DESC LIMIT 5
");
$stmt->execute([$user_id]);
$activities = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TaskFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">

<nav class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <a href="dashboard.php" class="text-2xl font-bold tracking-tighter">Task<span class="text-blue-600">Flow</span></a>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm font-medium text-gray-700">Halo, <?= htmlspecialchars($user['name']) ?></span>
                <a href="logout.php" class="text-sm text-gray-500 hover:text-red-600 transition">Keluar</a>
            </div>
        </div>
    </div>
</nav>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">Proyek Saya</h1>
        <button onclick="document.getElementById('modal').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium shadow-sm transition">
            + Proyek Baru
        </button>
    </div>

    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?= $error ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
        <?php foreach ($projects as $proj): ?>
            <?php 
                $progress = $proj['total_tasks'] > 0 ? round(($proj['completed_tasks'] / $proj['total_tasks']) * 100) : 0;
            ?>
            <a href="project.php?id=<?= $proj['project_id'] ?>" class="block bg-white p-6 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition group">
                <h3 class="text-xl font-bold mb-2 group-hover:text-blue-600 transition"><?= htmlspecialchars($proj['name']) ?></h3>
                <p class="text-gray-500 text-sm mb-4 line-clamp-2"><?= htmlspecialchars($proj['description']) ?></p>
                <div class="flex justify-between text-xs text-gray-400 mb-2 font-medium">
                    <span>Progress</span>
                    <span><?= $progress ?>%</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $progress ?>%"></div>
                </div>
            </a>
        <?php endforeach; ?>
        <?php if (empty($projects)): ?>
            <div class="col-span-full text-center py-12 bg-white rounded-xl border border-dashed border-gray-300 text-gray-500">
                Belum ada proyek. Buat proyek pertamamu!
            </div>
        <?php endif; ?>
    </div>

    <h2 class="text-xl font-bold mb-4">Aktivitas Terakhir</h2>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <ul class="divide-y divide-gray-200">
            <?php foreach ($activities as $act): ?>
                <li class="p-4 hover:bg-gray-50 transition">
                    <p class="text-sm">
                        <span class="font-medium text-gray-900"><?= htmlspecialchars($act['action']) ?></span>
                        <?php if ($act['task_name']): ?>
                            pada tugas <span class="font-medium">"<?= htmlspecialchars($act['task_name']) ?>"</span>
                        <?php endif; ?>
                        <?php if ($act['project_name']): ?>
                            di proyek <span class="font-medium">"<?= htmlspecialchars($act['project_name']) ?>"</span>
                        <?php endif; ?>
                    </p>
                    <p class="text-xs text-gray-400 mt-1"><?= $act['date'] ?> <?= $act['time'] ?></p>
                </li>
            <?php endforeach; ?>
            <?php if (empty($activities)): ?>
                <li class="p-4 text-sm text-gray-500 text-center">Belum ada aktivitas.</li>
            <?php endif; ?>
        </ul>
    </div>
</main>

<!-- Modal Create Project -->
<div id="modal" class="fixed inset-0 bg-gray-900/50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold">Buat Proyek Baru</h3>
            <button onclick="document.getElementById('modal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="create_project" value="1">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Proyek</label>
                <input type="text" name="name" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                <textarea name="description" rows="3" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></textarea>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal').classList.add('hidden')" class="px-4 py-2 text-gray-600 font-medium hover:bg-gray-100 rounded-lg transition">Batal</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition">Simpan</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
