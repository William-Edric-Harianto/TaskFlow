<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not authenticated']);
    } else {
        header('Location: ../index.html');
    }
    exit;
}

if (!isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['error' => 'No task ID']);
    exit;
}

$user_id = $_SESSION['user_id'];
$task_id = $_GET['id'] ?? $_POST['task_id'];

// Get Task & Project Info to verify access and get project_id
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
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode(['error' => 'Task not found or access denied']);
    } else {
        header('Location: dashboard.html');
    }
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
            header("Location: task.html?id=$task_id");
            exit;
        } catch(Exception $e) {
            $pdo->rollBack();
            $error = urlencode("Gagal mengupdate status.");
            header("Location: task.html?id=$task_id&error=$error");
            exit;
        }
    } else {
        header("Location: task.html?id=$task_id");
        exit;
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
        header("Location: task.html?id=$task_id");
        exit;
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = urlencode("Gagal mengubah tugas.");
        header("Location: task.html?id=$task_id&error=$error");
        exit;
    }
}

// Handle Task Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task'])) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("DELETE FROM Note WHERE task_id = ?");
        $stmt->execute([$task_id]);

        $stmt = $pdo->prepare("DELETE FROM Activity WHERE task_id = ?");
        $stmt->execute([$task_id]);

        $stmt = $pdo->prepare("DELETE FROM Task WHERE task_id = ?");
        $stmt->execute([$task_id]);

        $pdo->commit();
        header("Location: project.html?id=$project_id");
        exit;
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = urlencode("Gagal menghapus tugas: " . $e->getMessage());
        header("Location: task.html?id=$task_id&error=$error");
        exit;
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
        header("Location: task.html?id=$task_id");
        exit;
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = urlencode("Gagal menambah catatan.");
        header("Location: task.html?id=$task_id&error=$error");
        exit;
    }
}

// Return JSON for GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get Notes
    $stmt = $pdo->prepare("SELECT n.*, u.name as user_name FROM Note n JOIN users u ON n.user_id = u.user_id WHERE n.task_id = ? ORDER BY n.date DESC, n.time DESC");
    $stmt->execute([$task_id]);
    $notes = $stmt->fetchAll();

    // Get Activities for this task
    $stmt = $pdo->prepare("SELECT a.*, u.name as user_name FROM Activity a JOIN users u ON a.user_id = u.user_id WHERE a.task_id = ? ORDER BY a.date DESC, a.time DESC LIMIT 10");
    $stmt->execute([$task_id]);
    $activities = $stmt->fetchAll();

    header('Content-Type: application/json');
    echo json_encode([
        'task' => $task,
        'notes' => $notes,
        'activities' => $activities
    ]);
    exit;
}
?>
