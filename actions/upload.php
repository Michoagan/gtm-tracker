<?php
session_start();
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';
if ($_SERVER['REQUEST_METHOD']!=='POST' || !verifyCsrfToken($_POST['csrf_token']??'')) { header('Location: /gtm-tracker/actions/index.php'); exit; }
$uid = $_SESSION['user_id'];
$actionId = (int)($_POST['action_id']??0);
$db = getDB();
$action = $db->prepare("SELECT * FROM actions WHERE id=?");
$action->execute([$actionId]);
$action = $action->fetch();
if (!$action || (!isAdmin() && $action['user_id']!=$uid)) { header('Location: /gtm-tracker/actions/index.php'); exit; }
if (!empty($_FILES['attachment']['name'])) {
    $file = $_FILES['attachment'];
    $allowed = ['image/png','image/jpeg','image/jpg','application/pdf'];
    $allowedExt = ['png','jpg','jpeg','pdf'];
    $ext = strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
    if (in_array($file['type'],$allowed) && in_array($ext,$allowedExt) && $file['size']<=5*1024*1024 && $file['error']===0) {
        $newName = uniqid('att_',true).'.'.(string)$ext;
        if (move_uploaded_file($file['tmp_name'],UPLOAD_DIR.$newName)) {
            $db->prepare("INSERT INTO attachments (user_id,action_id,original_name,file_path,file_type,file_size) VALUES (?,?,?,?,?,?)")
               ->execute([$uid,$actionId,$file['name'],UPLOAD_URL.$newName,$file['type'],$file['size']]);
            logActivity($uid,'upload_fichier',"Fichier: ".(string)$file['name']);
        }
    }
}
header("Location: /gtm-tracker/actions/view.php?id=$actionId");
exit;