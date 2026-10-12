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

$allowedStatuses = $isAdm 
    ? ['A faire', 'En cours', 'Terminée', 'Validée', 'Bloquée'] 
    : ['A faire', 'En cours', 'Terminée', 'Bloquée'];

$requestedStatus = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';

if (!empty($requestedStatus) && in_array($requestedStatus, $allowedStatuses)) {
    $newStatus = $requestedStatus;
} else {
    $currentStatus = mb_strtolower($task['status'], 'UTF-8');
    $isDone = in_array($currentStatus, ['terminée', 'terminee', 'termine', 'validée', 'validee']);
    $newStatus = $isDone ? 'En cours' : 'Terminée';
}

if ($newStatus === 'Validée' && !$isAdm) {
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Action refusée : seul l\'administrateur peut valider une tâche terminée.'
    ];
    $redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : '/gtm-tracker/tasks/index.php';
    header('Location: ' . $redirect);
    exit;
}

$now = date('Y-m-d H:i:s');
if ($newStatus === 'En cours') {
    $db->prepare("UPDATE tasks SET status = ?, started_at = COALESCE(started_at, ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?")
       ->execute([$newStatus, $now, $taskId]);
} elseif ($newStatus === 'Terminée') {
    $started = $task['started_at'] ?: $task['created_at'] ?: $now;
    $diffSec = max(60, strtotime($now) - strtotime($started));
    $hours = ((float)$task['hours_spent'] > 0) ? (float)$task['hours_spent'] : round($diffSec / 3600, 2);
    $db->prepare("UPDATE tasks SET status = ?, completed_at = COALESCE(completed_at, ?), hours_spent = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
       ->execute([$newStatus, $now, $hours, $taskId]);
} elseif ($newStatus === 'Validée') {
    $started = $task['started_at'] ?: $task['created_at'] ?: $now;
    $completed = $task['completed_at'] ?: $now;
    $db->prepare("UPDATE tasks SET status = ?, completed_at = COALESCE(completed_at, ?), validated_at = COALESCE(validated_at, ?), validated_by = COALESCE(validated_by, ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?")
       ->execute([$newStatus, $completed, $now, $currentUid, $taskId]);
} else {
    $db->prepare("UPDATE tasks SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
       ->execute([$newStatus, $taskId]);
}

logActivity($currentUid, 'statut_tache', "Tâche « " . $task['title'] . " » passée à : $newStatus");
$_SESSION['flash'] = [
    'type' => 'success', 
    'message' => "Le statut de la tâche a été mis à jour avec succès : « $newStatus »."
];

$redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : '/gtm-tracker/tasks/index.php';
header('Location: ' . $redirect);
exit;
