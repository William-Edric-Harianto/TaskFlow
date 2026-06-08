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
$task_id = $_GET['id'];

// Get Task & Project Info
$stmt = $pdo->prepare("
    SELECT t.*, p.name as project_name 
    FROM Task t 
    JOIN Project p ON t.project_id = p.project_id 
    JOIN users_project up ON p.project_id = up.project_id
    WHERE t.task_id = ? AND up.user_id = ?
");
$stmt->execute([$task_id, $user_id]);
$task = $stmt->fetch();

if (!$task) {
    header('Location: dashboard.php');
    exit;
}

$project_id = $task['project_id'];

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    if ($new_status !== $task['status']) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE Task SET status = ? WHERE task_id = ?");
            $stmt->execute([$new_status, $task_id]);
            
            $stmt = $pdo->prepare("INSERT INTO Activity (action, date, time, task_id, project_id, user_id) VALUES (?, CURDATE(), CURTIME(), ?, ?, ?)");
            $action = "Changed status to " . str_replace('_', ' ', $new_status);
            $stmt->execute([$action, $task_id, $project_id, $user_id]);
            
            $pdo->commit();
            header("Location: task.php?id=$task_id");
            exit;
        } catch(Exception $e) {
            $pdo->rollBack();
            $error = "Gagal mengupdate status.";
        }
    }
}

// Handle Task Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_task'])) {
    $name = $_POST['name'];
    $desc = $_POST['description'];
    $status = $_POST['status'];
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE Task SET name = ?, description = ?, status = ? WHERE task_id = ?");
        $stmt->execute([$name, $desc, $status, $task_id]);
        
        $stmt = $pdo->prepare("INSERT INTO Activity (action, date, time, task_id, project_id, user_id) VALUES (?, CURDATE(), CURTIME(), ?, ?, ?)");
        $stmt->execute(['Edited Task Info', $task_id, $project_id, $user_id]);
        
        $pdo->commit();
        header("Location: task.php?id=$task_id");
        exit;
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = "Gagal mengubah tugas.";
    }
}

// Handle Task Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task'])) {
    $pdo->beginTransaction();
    try {
        // 1. Delete notes related to the task
        $stmt = $pdo->prepare("DELETE FROM Note WHERE task_id = ?");
        $stmt->execute([$task_id]);

        // 2. Delete activities related to the task
        $stmt = $pdo->prepare("DELETE FROM Activity WHERE task_id = ?");
        $stmt->execute([$task_id]);

        // 3. Delete task itself
        $stmt = $pdo->prepare("DELETE FROM Task WHERE task_id = ?");
        $stmt->execute([$task_id]);

        $pdo->commit();
        header("Location: project.php?id=$project_id");
        exit;
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = "Gagal menghapus tugas: " . $e->getMessage();
    }
}

// Handle Add Note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO Note (title, content, time, date, task_id, user_id) VALUES (?, ?, CURTIME(), CURDATE(), ?, ?)");
        $stmt->execute([$title, $content, $task_id, $user_id]);
        
        $stmt = $pdo->prepare("INSERT INTO Activity (action, date, time, task_id, project_id, user_id) VALUES (?, CURDATE(), CURTIME(), ?, ?, ?)");
        $stmt->execute(['Added Note', $task_id, $project_id, $user_id]);
        
        $pdo->commit();
        header("Location: task.php?id=$task_id");
        exit;
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = "Gagal menambah catatan.";
    }
}

// Get Notes
$stmt = $pdo->prepare("SELECT n.*, u.name as user_name FROM Note n JOIN users u ON n.user_id = u.user_id WHERE n.task_id = ? ORDER BY n.date DESC, n.time DESC");
$stmt->execute([$task_id]);
$notes = $stmt->fetchAll();

// Get Activities for this task
$stmt = $pdo->prepare("SELECT a.*, u.name as user_name FROM Activity a JOIN users u ON a.user_id = u.user_id WHERE a.task_id = ? ORDER BY a.date DESC, a.time DESC LIMIT 10");
$stmt->execute([$task_id]);
$activities = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($task['name']) ?> - TaskFlow</title>
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
                <a href="project.php?id=<?= $project_id ?>" class="text-gray-500 hover:text-blue-600 transition">&larr; Kembali ke Proyek</a>
                <span class="text-gray-300">|</span>
                <span class="text-xl font-bold tracking-tight text-gray-800"><?= htmlspecialchars($task['project_name']) ?></span>
            </div>
            <div class="flex items-center">
                <a href="logout.php" class="text-sm text-gray-500 hover:text-red-600 transition">Keluar</a>
            </div>
        </div>
    </div>
</nav>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex flex-col lg:flex-row gap-8">
    
    <!-- Left Column: Task Detail & Notes -->
    <div class="lg:w-2/3">
        <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm mb-8">
            <div class="flex flex-col sm:flex-row justify-between items-start gap-4 mb-4">
                <div>
                    <div class="flex items-center gap-3 flex-wrap mb-1">
                        <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-800 uppercase">
                            Tugas
                        </span>
                        <button onclick="document.getElementById('modal-edit-task').classList.remove('hidden')" class="text-xs text-blue-600 hover:underline font-medium">
                            Edit Tugas
                        </button>
                        <span class="text-gray-300">|</span>
                        <button onclick="if(confirm('Apakah Anda yakin ingin menghapus tugas ini?')) { document.getElementById('delete-task-form').submit(); }" class="text-xs text-red-600 hover:underline font-medium">
                            Hapus Tugas
                        </button>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900"><?= htmlspecialchars($task['name']) ?></h1>
                </div>
                
                <form method="POST" class="flex items-center gap-2">
                    <input type="hidden" name="update_status" value="1">
                    <select name="status" onchange="this.form.submit()" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2 font-medium">
                        <option value="todo" <?= $task['status'] === 'todo' ? 'selected' : '' ?>>To Do</option>
                        <option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="done" <?= $task['status'] === 'done' ? 'selected' : '' ?>>Done</option>
                    </select>
                </form>
            </div>
            
            <p class="text-gray-600 text-lg mb-6 whitespace-pre-wrap"><?= htmlspecialchars($task['description']) ?></p>
        </div>

        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold">Catatan & Komentar</h2>
            <button onclick="document.getElementById('modal').classList.remove('hidden')" class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-3 py-1.5 rounded-lg text-sm font-medium transition">
                + Tambah Catatan
            </button>
        </div>

        <div class="space-y-4">
            <?php foreach ($notes as $note): ?>
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex gap-4">
                    <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold flex-shrink-0">
                        <?= substr($note['user_name'], 0, 1) ?>
                    </div>
                    <div class="flex-1">
                        <div class="flex justify-between items-start mb-1">
                            <h4 class="font-bold text-gray-900"><?= htmlspecialchars($note['title']) ?></h4>
                            <span class="text-xs text-gray-400"><?= $note['date'] ?> <?= $note['time'] ?></span>
                        </div>
                        <p class="text-xs text-gray-500 mb-2">Oleh <?= htmlspecialchars($note['user_name']) ?></p>
                        <p class="text-gray-700 whitespace-pre-wrap text-sm"><?= htmlspecialchars($note['content']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($notes)): ?>
                <div class="text-center py-8 bg-gray-50 rounded-xl border border-dashed border-gray-300 text-gray-500 text-sm">
                    Belum ada catatan untuk tugas ini.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Activities -->
    <div class="lg:w-1/3">
        <h3 class="text-lg font-bold mb-4">Aktivitas Tugas</h3>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <ul class="divide-y divide-gray-100">
                <?php foreach ($activities as $act): ?>
                    <li class="p-4 hover:bg-gray-50 transition">
                        <p class="text-sm text-gray-800">
                            <span class="font-medium"><?= htmlspecialchars($act['user_name']) ?></span> 
                            <?= htmlspecialchars(strtolower($act['action'])) ?>
                        </p>
                        <p class="text-xs text-gray-400 mt-1"><?= $act['date'] ?> <?= $act['time'] ?></p>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($activities)): ?>
                    <li class="p-4 text-sm text-gray-500 text-center">Belum ada aktivitas.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</main>

<!-- Modal Create Note -->
<div id="modal" class="fixed inset-0 bg-gray-900/50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold">Tambah Catatan</h3>
            <button onclick="document.getElementById('modal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="add_note" value="1">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Judul Catatan</label>
                <input type="text" name="title" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Isi Catatan</label>
                <textarea name="content" rows="4" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></textarea>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal').classList.add('hidden')" class="px-4 py-2 text-gray-600 font-medium hover:bg-gray-100 rounded-lg transition">Batal</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Task -->
<div id="modal-edit-task" class="fixed inset-0 bg-gray-900/50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-900">Edit Tugas</h3>
            <button onclick="document.getElementById('modal-edit-task').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="edit_task" value="1">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Tugas</label>
                <input type="text" name="name" value="<?= htmlspecialchars($task['name']) ?>" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-gray-900">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-gray-900">
                    <option value="todo" <?= $task['status'] === 'todo' ? 'selected' : '' ?>>To Do</option>
                    <option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="done" <?= $task['status'] === 'done' ? 'selected' : '' ?>>Done</option>
                </select>
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                <textarea name="description" rows="3" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-gray-900"><?= htmlspecialchars($task['description']) ?></textarea>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-edit-task').classList.add('hidden')" class="px-4 py-2 text-gray-600 font-medium hover:bg-gray-100 rounded-lg transition">Batal</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Task Form -->
<form id="delete-task-form" method="POST" style="display:none;">
    <input type="hidden" name="delete_task" value="1">
</form>

</body>
</html>
