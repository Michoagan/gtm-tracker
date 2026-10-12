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

// SEUL L'ADMIN PEUT VALIDER UNE TÂCHE TERMINÉE
if (!isAdmin()) {
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Action refusée : seul l\'administrateur peut valider les tâches des collaborateurs.'
    ];
    header('Location: /gtm-tracker/tasks/index.php');
    exit;
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
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Tâche introuvable.'];
    header('Location: /gtm-tracker/tasks/index.php');
    exit;
}

$adminId = (int)$_SESSION['user_id'];
$action = trim($_POST['action'] ?? 'validate');
$notes = trim($_POST['validation_notes'] ?? '');
$redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : '/gtm-tracker/tasks/view.php?id=' . $taskId;

if ($action === 'validate') {
    $now = date('Y-m-d H:i:s');
    $stmtUp = $db->prepare("
        UPDATE tasks 
        SET status = 'Validée', 
            validated_at = ?, 
            validated_by = ?, 
            validation_notes = ?, 
            updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $stmtUp->execute([$now, $adminId, $notes, $taskId]);

    logActivity($adminId, 'validation_tache', "Tâche validée par l'administrateur : " . $task['title'] . " (ID: $taskId)");
    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => "✅ La tâche « " . htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') . " » a été validée et approuvée avec succès par l'administrateur !"
    ];
} elseif ($action === 'request_changes') {
    // Renvoyer au collaborateur pour révision
    $stmtUp = $db->prepare("
        UPDATE tasks 
        SET status = 'En cours', 
            validated_at = NULL, 
            validated_by = NULL, 
            validation_notes = ?, 
            updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $stmtUp->execute([$notes, $taskId]);

    logActivity($adminId, 'demande_corrections_tache', "Demande de corrections sur la tâche : " . $task['title'] . " (ID: $taskId)");
    $_SESSION['flash'] = [
        'type' => 'warning',
        'message' => "🔄 Tâche remise en cours : des corrections ont été demandées au collaborateur."
    ];
}

header('Location: ' . $redirect);
exit;
