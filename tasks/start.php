<?php
session_start();
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /gtm-tracker/tasks/index.php');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    die('Token CSRF invalide');
}

$taskId = (int)($_POST['task_id'] ?? 0);
if ($taskId <= 0) {
    header('Location: /gtm-tracker/tasks/index.php');
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$taskId]);
$task = $stmt->fetch();

if (!$task) {
    header('Location: /gtm-tracker/tasks/index.php?error=not_found');
    exit;
}

$currentUid = (int)$_SESSION['user_id'];
$isAdm      = isAdmin();

// Permission : le membre assigné ou l'admin
if (!$isAdm && (int)$task['user_id'] !== $currentUid) {
    die('Accès non autorisé');
}

$now = date('Y-m-d H:i:s');
$stmtUp = $db->prepare("
    UPDATE tasks 
    SET status = 'En cours', 
        started_at = COALESCE(started_at, ?), 
        updated_at = CURRENT_TIMESTAMP 
    WHERE id = ?
");
$stmtUp->execute([$now, $taskId]);

logActivity($currentUid, 'demarrage_tache', "Tâche démarrée : " . $task['title'] . " à " . date('H:i'));

$heure = date('H:i');
$date = date('d/m/Y');
$_SESSION['flash'] = [
    'type' => 'info',
    'message' => "⚡ Tâche démarrée le $date à $heure ! Bon travail, n'oubliez pas de marquer quand vous finissez."
];

$redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : '/gtm-tracker/tasks/index.php';
header('Location: ' . $redirect);
exit;
