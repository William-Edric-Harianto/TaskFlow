<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$project_id = $_GET['id'];

// Check if user has access to this project
$stmt = $pdo->prepare("SELECT p.* FROM Project p JOIN users_project up ON p.project_id = up.project_id WHERE p.project_id = ? AND up.user_id = ?");
$stmt->execute([$project_id, $user_id]);
$project = $stmt->fetch();

if (!$project) {
    header('Location: dashboard.php');
    exit;
}

// Handle Task Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    $name = $_POST['name'];
    $desc = $_POST['description'];
    $status = $_POST['status'] ?? 'todo';
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO Task (name, description, project_id, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $desc, $project_id, $status]);
        $task_id = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("INSERT INTO Activity (action, date, time, task_id, project_id, user_id) VALUES (?, CURDATE(), CURTIME(), ?, ?, ?)");
        $stmt->execute(['Created Task', $task_id, $project_id, $user_id]);
        
        $pdo->commit();
        header("Location: project.php?id=$project_id");
        exit;
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = "Gagal membuat tugas.";
    }
}

// Handle Project Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_project'])) {
    $name = $_POST['name'];
    $desc = $_POST['description'];
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE Project SET name = ?, description = ? WHERE project_id = ?");
        $stmt->execute([$name, $desc, $project_id]);
        
        $stmt = $pdo->prepare("INSERT INTO Activity (action, date, time, project_id, user_id) VALUES (?, CURDATE(), CURTIME(), ?, ?)");
        $stmt->execute(['Edited Project Info', $project_id, $user_id]);
        
        $pdo->commit();
        header("Location: project.php?id=$project_id");
        exit;
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = "Gagal mengubah info proyek.";
    }
}

// Handle Project Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project'])) {
    $pdo->beginTransaction();
    try {
        // 1. Set user_project to NULL in users
        $stmt = $pdo->prepare("UPDATE users SET user_project = NULL WHERE user_project = ?");
        $stmt->execute([$project_id]);

        // 2. Delete notes related to tasks of the project
        $stmt = $pdo->prepare("DELETE FROM note WHERE task_id IN (SELECT task_id FROM task WHERE project_id = ?)");
        $stmt->execute([$project_id]);

        // 3. Delete activities related to the project or its tasks
        $stmt = $pdo->prepare("DELETE FROM activity WHERE project_id = ? OR task_id IN (SELECT task_id FROM task WHERE project_id = ?)");
        $stmt->execute([$project_id, $project_id]);

        // 4. Delete tasks of the project
        $stmt = $pdo->prepare("DELETE FROM task WHERE project_id = ?");
        $stmt->execute([$project_id]);

        // 5. Delete users_project entries
        $stmt = $pdo->prepare("DELETE FROM users_project WHERE project_id = ?");
        $stmt->execute([$project_id]);

        // 6. Delete project
        $stmt = $pdo->prepare("DELETE FROM project WHERE project_id = ?");
        $stmt->execute([$project_id]);

        $pdo->commit();
        header("Location: dashboard.php");
        exit;
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = "Gagal menghapus proyek: " . $e->getMessage();
    }
}

// Get Tasks
$stmt = $pdo->prepare("SELECT * FROM Task WHERE project_id = ? ORDER BY task_id DESC");
$stmt->execute([$project_id]);
$tasks = $stmt->fetchAll();

$tasks_by_status = ['todo' => [], 'in_progress' => [], 'done' => []];
foreach ($tasks as $task) {
    $tasks_by_status[$task['status']][] = $task;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($project['name']) ?> - TaskFlow</title>
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
            <div class="flex items-center gap-4">
                <a href="dashboard.php" class="text-gray-500 hover:text-blue-600 transition">&larr; Kembali</a>
                <span class="text-gray-300">|</span>
                <span class="text-xl font-bold tracking-tight text-gray-800"><?= htmlspecialchars($project['name']) ?></span>
            </div>
            <div class="flex items-center">
                <a href="logout.php" class="text-sm text-gray-500 hover:text-red-600 transition">Keluar</a>
            </div>
        </div>
    </div>
</nav>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-4 mb-8">
        <div>
            <div class="flex items-center gap-3 mb-2 flex-wrap">
                <h1 class="text-3xl font-bold">Daftar Tugas</h1>
                <span class="text-gray-300">|</span>
                <button onclick="document.getElementById('modal-edit-project').classList.remove('hidden')" class="text-sm bg-blue-50 text-blue-600 hover:bg-blue-100 px-3 py-1.5 rounded-lg font-semibold transition">
                    Edit Proyek
                </button>
                <button onclick="if(confirm('Apakah Anda yakin ingin menghapus proyek ini beserta seluruh tugas, catatan, dan aktivitas di dalamnya?')) { document.getElementById('delete-project-form').submit(); }" class="text-sm bg-red-50 text-red-600 hover:bg-red-100 px-3 py-1.5 rounded-lg font-semibold transition">
                    Hapus Proyek
                </button>
            </div>
            <p class="text-gray-500 max-w-2xl"><?= htmlspecialchars($project['description']) ?></p>
        </div>
        <div class="flex gap-3">
            <button onclick="document.getElementById('modal').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium shadow-sm transition whitespace-nowrap">
                + Tugas Baru
            </button>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?= $error ?></div>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row gap-6">
        <!-- Todo Column -->
        <div class="flex-1">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-3 h-3 rounded-full bg-gray-400"></div>
                <h2 class="font-bold text-gray-700">To Do <span class="text-gray-400 ml-1">(<?= count($tasks_by_status['todo']) ?>)</span></h2>
            </div>
            <div class="space-y-4">
                <?php foreach ($tasks_by_status['todo'] as $t): ?>
                    <a href="task.php?id=<?= $t['task_id'] ?>" class="block bg-white p-4 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition">
                        <h4 class="font-bold text-gray-800 mb-1"><?= htmlspecialchars($t['name']) ?></h4>
                        <p class="text-sm text-gray-500 line-clamp-2"><?= htmlspecialchars($t['description']) ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- In Progress Column -->
        <div class="flex-1">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                <h2 class="font-bold text-gray-700">In Progress <span class="text-gray-400 ml-1">(<?= count($tasks_by_status['in_progress']) ?>)</span></h2>
            </div>
            <div class="space-y-4">
                <?php foreach ($tasks_by_status['in_progress'] as $t): ?>
                    <a href="task.php?id=<?= $t['task_id'] ?>" class="block bg-white p-4 rounded-xl border border-blue-200 shadow-sm hover:shadow-md transition">
                        <h4 class="font-bold text-gray-800 mb-1"><?= htmlspecialchars($t['name']) ?></h4>
                        <p class="text-sm text-gray-500 line-clamp-2"><?= htmlspecialchars($t['description']) ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Done Column -->
        <div class="flex-1">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-3 h-3 rounded-full bg-green-500"></div>
                <h2 class="font-bold text-gray-700">Done <span class="text-gray-400 ml-1">(<?= count($tasks_by_status['done']) ?>)</span></h2>
            </div>
            <div class="space-y-4">
                <?php foreach ($tasks_by_status['done'] as $t): ?>
                    <a href="task.php?id=<?= $t['task_id'] ?>" class="block bg-white p-4 rounded-xl border border-green-200 shadow-sm hover:shadow-md transition opacity-75 hover:opacity-100">
                        <h4 class="font-bold text-gray-800 mb-1 line-through"><?= htmlspecialchars($t['name']) ?></h4>
                        <p class="text-sm text-gray-500 line-clamp-2"><?= htmlspecialchars($t['description']) ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>

<!-- Modal Create Task -->
<div id="modal" class="fixed inset-0 bg-gray-900/50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold">Buat Tugas Baru</h3>
            <button onclick="document.getElementById('modal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="create_task" value="1">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Tugas</label>
                <input type="text" name="name" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="todo">To Do</option>
                    <option value="in_progress">In Progress</option>
                    <option value="done">Done</option>
                </select>
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

<!-- Modal Edit Project -->
<div id="modal-edit-project" class="fixed inset-0 bg-gray-900/50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-900">Edit Proyek</h3>
            <button onclick="document.getElementById('modal-edit-project').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="edit_project" value="1">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Proyek</label>
                <input type="text" name="name" value="<?= htmlspecialchars($project['name']) ?>" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-gray-900">
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                <textarea name="description" rows="3" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-gray-900"><?= htmlspecialchars($project['description']) ?></textarea>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-edit-project').classList.add('hidden')" class="px-4 py-2 text-gray-600 font-medium hover:bg-gray-100 rounded-lg transition">Batal</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Project Form -->
<form id="delete-project-form" method="POST" style="display:none;">
    <input type="hidden" name="delete_project" value="1">
</form>

</body>
</html>
