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

$user_id = $_SESSION['user_id'];

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
        header('Location: dashboard.html');
        exit;
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = urlencode("Gagal membuat proyek.");
        header("Location: dashboard.html?error=$error");
        exit;
    }
}

// Return Data for GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get User Info
    $stmt = $pdo->prepare("SELECT user_id, name, username FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

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

    header('Content-Type: application/json');
    echo json_encode([
        'user' => $user,
        'projects' => $projects,
        'activities' => $activities
    ]);
    exit;
}
?>
