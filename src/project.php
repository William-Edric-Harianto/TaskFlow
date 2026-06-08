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
    echo json_encode(['error' => 'No project ID']);
    exit;
}

$user_id = $_SESSION['user_id'];
$project_id = $_GET['id'] ?? $_POST['project_id'];

// Check if user has access to this project
$stmt = $pdo->prepare("SELECT p.* FROM Project p JOIN users_project up ON p.project_id = up.project_id WHERE p.project_id = ? AND up.user_id = ?");
$stmt->execute([$project_id, $user_id]);
$project = $stmt->fetch();

if (!$project) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode(['error' => 'Project not found or access denied']);
    } else {
        header('Location: dashboard.html');
    }
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
        header("Location: project.html?id=$project_id");
        exit;
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = urlencode("Gagal membuat tugas.");
        header("Location: project.html?id=$project_id&error=$error");
        exit;
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
        header("Location: project.html?id=$project_id");
        exit;
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = urlencode("Gagal mengubah info proyek.");
        header("Location: project.html?id=$project_id&error=$error");
        exit;
    }
}

// Handle Project Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project'])) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("DELETE FROM note WHERE task_id IN (SELECT task_id FROM task WHERE project_id = ?)");
        $stmt->execute([$project_id]);

        $stmt = $pdo->prepare("DELETE FROM activity WHERE project_id = ? OR task_id IN (SELECT task_id FROM task WHERE project_id = ?)");
        $stmt->execute([$project_id, $project_id]);

        $stmt = $pdo->prepare("DELETE FROM task WHERE project_id = ?");
        $stmt->execute([$project_id]);

        $stmt = $pdo->prepare("DELETE FROM users_project WHERE project_id = ?");
        $stmt->execute([$project_id]);

        $stmt = $pdo->prepare("DELETE FROM project WHERE project_id = ?");
        $stmt->execute([$project_id]);

        $pdo->commit();
        header("Location: dashboard.html");
        exit;
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = urlencode("Gagal menghapus proyek: " . $e->getMessage());
        header("Location: project.html?id=$project_id&error=$error");
        exit;
    }
}

// Handle User Invite
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invite_user'])) {
    $username_to_invite = $_POST['username'];
    
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->execute([$username_to_invite]);
    $invited_user = $stmt->fetch();
    
    if ($invited_user) {
        $invited_user_id = $invited_user['user_id'];
        
        $stmt_check = $pdo->prepare("SELECT * FROM users_project WHERE user_id = ? AND project_id = ?");
        $stmt_check->execute([$invited_user_id, $project_id]);
        if (!$stmt_check->fetch()) {
            $stmt_insert = $pdo->prepare("INSERT INTO users_project (user_id, project_id) VALUES (?, ?)");
            $stmt_insert->execute([$invited_user_id, $project_id]);
            
            $stmt_act = $pdo->prepare("INSERT INTO Activity (action, date, time, project_id, user_id) VALUES (?, CURDATE(), CURTIME(), ?, ?)");
            $stmt_act->execute(["Invited User $username_to_invite", $project_id, $user_id]);
            
            $success = urlencode("User berhasil diundang ke proyek.");
            header("Location: project.html?id=$project_id&success=$success");
            exit;
        } else {
            $error = urlencode("User sudah ada di proyek ini.");
            header("Location: project.html?id=$project_id&error=$error");
            exit;
        }
    } else {
        $error = urlencode("Username tidak ditemukan.");
        header("Location: project.html?id=$project_id&error=$error");
        exit;
    }
}

// Return JSON for GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get Project Members
    $stmt = $pdo->prepare("SELECT u.name, u.username FROM users u JOIN users_project up ON u.user_id = up.user_id WHERE up.project_id = ?");
    $stmt->execute([$project_id]);
    $members = $stmt->fetchAll();

    // Get Tasks
    $stmt = $pdo->prepare("SELECT * FROM Task WHERE project_id = ? ORDER BY task_id DESC");
    $stmt->execute([$project_id]);
    $tasks = $stmt->fetchAll();

    header('Content-Type: application/json');
    echo json_encode([
        'project' => $project,
        'members' => $members,
        'tasks' => $tasks
    ]);
    exit;
}
?>
