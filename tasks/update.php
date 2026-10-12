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
    die('Tâche introuvable');
}

$currentUid = (int)$_SESSION['user_id'];
$isAdm      = isAdmin();

// Permission check
if (!$isAdm && (int)$task['user_id'] !== $currentUid) {
    die('Accès non autorisé');
}

$title      = trim($_POST['title'] ?? '');
$category   = trim($_POST['category'] ?? 'tech');
$desc       = trim($_POST['description'] ?? '');
$result     = trim($_POST['result'] ?? '');
$status     = trim($_POST['status'] ?? 'Terminée');
$priority   = trim($_POST['priority'] ?? 'Normale');
$taskDate   = trim($_POST['task_date'] ?? date('Y-m-d'));
$dueDate    = !empty($_POST['due_date']) ? trim($_POST['due_date']) : null;
$hoursSpent = isset($_POST['hours_spent']) && $_POST['hours_spent'] !== '' ? (float)$_POST['hours_spent'] : 0.0;
$linkUrl    = trim($_POST['link_url'] ?? '');

$targetUser = (!empty($_POST['user_id']) && $isAdm) ? (int)$_POST['user_id'] : (int)$task['user_id'];

if (empty($title)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Le titre de la tâche est obligatoire.'];
    header('Location: /gtm-tracker/tasks/edit.php?id=' . $id);
    exit;
}

$validCategories = array_keys(getTaskCategories());
if (!in_array($category, $validCategories)) {
    $category = 'tech';
}

$allowedStatuses   = ['A faire', 'En cours', 'Terminée', 'Bloquée', 'Terminee', 'Bloquee'];
$allowedPriorities = ['Basse', 'Normale', 'Haute', 'Urgente'];
if (!in_array($status, $allowedStatuses))     $status = 'A faire';
if (!in_array($priority, $allowedPriorities)) $priority = 'Normale';

$startedAt   = $task['started_at'];
$completedAt = $task['completed_at'];
if ($status === 'En cours' && empty($startedAt)) {
    $startedAt = date('Y-m-d H:i:s');
} elseif ($status === 'Terminée' && empty($completedAt)) {
    $completedAt = date('Y-m-d H:i:s');
    if (empty($startedAt)) $startedAt = date('Y-m-d H:i:s');
}

$stmtUp = $db->prepare("
    UPDATE tasks 
    SET user_id = ?, title = ?, category = ?, description = ?, result = ?, status = ?, priority = ?, task_date = ?, due_date = ?, started_at = ?, completed_at = ?, hours_spent = ?, link_url = ?, updated_at = CURRENT_TIMESTAMP
    WHERE id = ?
");
$stmtUp->execute([$targetUser, $title, $category, $desc, $result, $status, $priority, $taskDate, $dueDate, $startedAt, $completedAt, $hoursSpent, $linkUrl, $id]);

// Handle optional file attachment
if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === 0) {
    $file = $_FILES['attachment'];
    $allowedExt = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf', 'zip', 'txt', 'csv', 'json'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (in_array($ext, $allowedExt) && $file['size'] <= 10 * 1024 * 1024) {
        if (!is_dir(UPLOAD_DIR)) {
            @mkdir(UPLOAD_DIR, 0777, true);
        }
        $newName = uniqid('task_att_', true) . '.' . $ext;
        $dest = UPLOAD_DIR . $newName;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $stmtAtt = $db->prepare("INSERT INTO attachments (user_id, task_id, original_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtAtt->execute([$currentUid, $id, $file['name'], UPLOAD_URL . $newName, $file['type'], $file['size']]);
        }
    }
}

logActivity($currentUid, 'modification_tache', "Tâche mise à jour: $title (ID: $id)");
$_SESSION['flash'] = ['type' => 'success', 'message' => 'Tâche mise à jour avec succès !'];
header('Location: /gtm-tracker/tasks/view.php?id=' . $id);
exit;
