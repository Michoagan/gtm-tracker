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

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: /gtm-tracker/tasks/index.php');
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$id]);
$task = $stmt->fetch();

if (!$task) {
    header('Location: /gtm-tracker/tasks/index.php?error=not_found');
    exit;
}

$currentUid = (int)$_SESSION['user_id'];
$isAdm      = isAdmin();

if (!$isAdm && (int)$task['user_id'] !== $currentUid) {
    die('Accès refusé');
}

// Remove attachments files
$stmtAtt = $db->prepare("SELECT file_path FROM attachments WHERE task_id = ?");
$stmtAtt->execute([$id]);
$files = $stmtAtt->fetchAll();
foreach ($files as $f) {
    $fullPath = __DIR__ . '/../' . ltrim($f['file_path'], '/');
    if (file_exists($fullPath)) {
        @unlink($fullPath);
    }
}
$db->prepare("DELETE FROM attachments WHERE task_id = ?")->execute([$id]);

// Delete task
$db->prepare("DELETE FROM tasks WHERE id = ?")->execute([$id]);

logActivity($currentUid, 'suppression_tache', "Tâche supprimée: " . $task['title'] . " (ID: $id)");
$_SESSION['flash'] = ['type' => 'success', 'message' => 'Tâche supprimée avec succès.'];
header('Location: /gtm-tracker/tasks/index.php');
exit;
