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

$currentUid = (int)$_SESSION['user_id'];
$isAdm      = isAdmin();
$targetUser = (!empty($_POST['user_id']) && $isAdm) ? (int)$_POST['user_id'] : $currentUid;
$assignedBy = ($targetUser !== $currentUid) ? $currentUid : null;

$db         = getDB();
$title      = trim($_POST['title'] ?? '');
$category   = trim($_POST['category'] ?? 'tech');
$desc       = trim($_POST['description'] ?? '');
$result     = trim($_POST['result'] ?? '');
$status     = trim($_POST['status'] ?? 'A faire');
$priority   = trim($_POST['priority'] ?? 'Normale');
$taskDate   = trim($_POST['task_date'] ?? date('Y-m-d'));
$dueDate    = !empty($_POST['due_date']) ? trim($_POST['due_date']) : null;
$hoursSpent = isset($_POST['hours_spent']) && $_POST['hours_spent'] !== '' ? (float)$_POST['hours_spent'] : 0.0;
$linkUrl    = trim($_POST['link_url'] ?? '');

if (empty($title)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Le titre de la tâche est obligatoire.'];
    header('Location: /gtm-tracker/tasks/create.php');
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

$startedAt   = null;
$completedAt = null;
if (in_array($status, ['En cours', 'en cours'])) {
    $startedAt = date('Y-m-d H:i:s');
} elseif (in_array($status, ['Terminée', 'terminee', 'Terminee'])) {
    $startedAt   = date('Y-m-d H:i:s');
    $completedAt = date('Y-m-d H:i:s');
}

$stmt = $db->prepare("INSERT INTO tasks (user_id, assigned_by, title, category, description, result, status, priority, task_date, due_date, started_at, completed_at, hours_spent, link_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$targetUser, $assignedBy, $title, $category, $desc, $result, $status, $priority, $taskDate, $dueDate, $startedAt, $completedAt, $hoursSpent, $linkUrl]);
$taskId = (int)$db->lastInsertId();

// Handle file attachment
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
            $stmtAtt->execute([$currentUid, $taskId, $file['name'], UPLOAD_URL . $newName, $file['type'], $file['size']]);
        }
    }
}

logActivity($currentUid, 'creation_tache', "Tâche ajoutée: $title (Catégorie: $category, ID: $taskId)");
$_SESSION['flash'] = ['type' => 'success', 'message' => 'Tâche enregistrée avec succès !'];
header('Location: /gtm-tracker/tasks/view.php?id=' . $taskId);
exit;
