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
    header('Location: /gtm-tracker/tasks/index.php');
    exit;
}

$currentUid = (int)$_SESSION['user_id'];
$isAdm      = isAdmin();

if (!$isAdm && (int)$task['user_id'] !== $currentUid) {
    die('Accès refusé');
}

$currentStatus = mb_strtolower($task['status'], 'UTF-8');
$isDone = in_array($currentStatus, ['terminée', 'terminee', 'termine']);
$newStatus = $isDone ? 'En cours' : 'Terminée';

$db->prepare("UPDATE tasks SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
   ->execute([$newStatus, $taskId]);

logActivity($currentUid, 'statut_tache', "Tâche $taskId passée à: $newStatus");
$_SESSION['flash'] = ['type' => 'success', 'message' => "Statut mis à jour : $newStatus"];

$redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : '/gtm-tracker/tasks/index.php';
header('Location: ' . $redirect);
exit;
