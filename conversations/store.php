<?php
session_start();
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';
if ($_SERVER['REQUEST_METHOD']!=='POST' || !verifyCsrfToken($_POST['csrf_token']??'')) { header('Location: /gtm-tracker/conversations/create.php'); exit; }
$uid = $_SESSION['user_id'];
$db  = getDB();
$prospectId  = !empty($_POST['prospect_id'])  ? (int)$_POST['prospect_id']  : null;
$stratId     = !empty($_POST['strategy_id'])  ? (int)$_POST['strategy_id']  : null;
$chanId      = !empty($_POST['channel_id'])   ? (int)$_POST['channel_id']   : null;
$msgSent     = trim($_POST['message_sent']     ?? '');
$respRcv     = trim($_POST['response_received'] ?? '');
$nextAction  = trim($_POST['next_action']      ?? '');
$status      = $_POST['status'] ?? 'Nouveau';
$convDate    = $_POST['conversation_date'] ?? date('Y-m-d');
$comment     = trim($_POST['comment'] ?? '');
$allowed = ['Nouveau','Contacte','En discussion','Interesse','Rendez-vous','Converti','Perdu','A relancer'];
if (!in_array($status,$allowed)) $status='Nouveau';
$stmt = $db->prepare("INSERT INTO conversations (prospect_id,user_id,strategy_id,channel_id,message_sent,response_received,next_action,status,conversation_date,comment) VALUES (?,?,?,?,?,?,?,?,?,?)");
$stmt->execute([$prospectId,$uid,$stratId,$chanId,$msgSent,$respRcv,$nextAction,$status,$convDate,$comment]);
$convId = $db->lastInsertId();
if (!empty($_FILES['attachment']['name'])) {
    $file = $_FILES['attachment'];
    $allowed2 = ['image/png','image/jpeg','image/jpg','application/pdf'];
    $allowedExt = ['png','jpg','jpeg','pdf'];
    $ext = strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
    if (in_array($file['type'],$allowed2) && in_array($ext,$allowedExt) && $file['size']<=5*1024*1024 && $file['error']===0) {
        $newName = uniqid('att_',true).'.'.(string)$ext;
        if (move_uploaded_file($file['tmp_name'],UPLOAD_DIR.$newName)) {
            $db->prepare("INSERT INTO attachments (user_id,conversation_id,original_name,file_path,file_type,file_size) VALUES (?,?,?,?,?,?)")
               ->execute([$uid,$convId,$file['name'],UPLOAD_URL.$newName,$file['type'],$file['size']]);
        }
    }
}
logActivity($uid,'ajout_conversation',"Conv ID:$convId");
$_SESSION['flash'] = ['type'=>'success','message'=>'Conversation enregistree!'];
header('Location: /gtm-tracker/conversations/index.php');
exit;