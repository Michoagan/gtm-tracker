<?php
session_start();
require_once __DIR__ . '/../auth/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /gtm-tracker/actions/create.php'); exit; }
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { die('Token CSRF invalide'); }

$currentUid = $_SESSION['user_id'];
$isAdm      = isAdmin();
// Admin can assign the action to any user; a regular user can only create for himself
$targetUserId = (!empty($_POST['user_id']) && $isAdm) ? (int)$_POST['user_id'] : $currentUid;
$assignedBy   = ($targetUserId !== $currentUid) ? $currentUid : null;
$db         = getDB();
$title      = trim($_POST['title'] ?? '');
$desc       = trim($_POST['description'] ?? '');
$stratId    = !empty($_POST['strategy_id'])  ? (int)$_POST['strategy_id']  : null;
$chanId     = !empty($_POST['channel_id'])   ? (int)$_POST['channel_id']   : null;
$prospectId = !empty($_POST['prospect_id'])  ? (int)$_POST['prospect_id']  : null;
$status     = $_POST['status']   ?? 'A faire';
$priority   = $_POST['priority'] ?? 'Normale';
$result     = trim($_POST['result']   ?? '');
$comment    = trim($_POST['comment']  ?? '');
$actionDate = $_POST['action_date']   ?? date('Y-m-d');

if (empty($title)) { header('Location: /gtm-tracker/actions/create.php?error=title_required'); exit; }

$allowed_statuses   = ['A faire','En cours','Terminee','Bloquee'];
$allowed_priorities = ['Basse','Normale','Haute','Urgente'];
if (!in_array($status, $allowed_statuses))     $status   = 'A faire';
if (!in_array($priority, $allowed_priorities)) $priority = 'Normale';

$stmt = $db->prepare("INSERT INTO actions (user_id,assigned_by,strategy_id,channel_id,prospect_id,title,description,status,priority,result,comment,action_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
$stmt->execute([$targetUserId,$assignedBy,$stratId,$chanId,$prospectId,$title,$desc,$status,$priority,$result,$comment,$actionDate]);
$actionId = $db->lastInsertId();

// Handle file upload
if (!empty($_FILES['attachment']['name'])) {
    $file       = $_FILES['attachment'];
    $allowed    = ['image/png','image/jpeg','image/jpg','application/pdf'];
    $allowedExt = ['png','jpg','jpeg','pdf'];
    $ext        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (in_array($file['type'],$allowed) && in_array($ext,$allowedExt) && $file['size'] <= 5*1024*1024 && $file['error']===0) {
        $newName = uniqid('att_',true).'.'.$ext;
        $dest    = UPLOAD_DIR.$newName;
        if (move_uploaded_file($file['tmp_name'],$dest)) {
            $db->prepare("INSERT INTO attachments (user_id,action_id,original_name,file_path,file_type,file_size) VALUES (?,?,?,?,?,?)")
               ->execute([$currentUid,$actionId,$file['name'],UPLOAD_URL.$newName,$file['type'],$file['size']]);
        }
    }
}

logActivity($currentUid,'creation_action',"Action: $title (ID:$actionId)");
$_SESSION['flash'] = ['type'=>'success','message'=>'Action enregistree avec succes!'];
header('Location: /gtm-tracker/actions/view.php?id='.$actionId);
exit;