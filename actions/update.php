<?php
session_start();
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';
if ($_SERVER['REQUEST_METHOD']!=='POST' || !verifyCsrfToken($_POST['csrf_token']??'')) { header('Location: /gtm-tracker/actions/index.php'); exit; }
$uid = $_SESSION['user_id']; $isAdm = isAdmin();
$id  = (int)($_POST['id']??0);
$db  = getDB();
$action = $db->prepare("SELECT * FROM actions WHERE id=?");
$action->execute([$id]); $action = $action->fetch();
if (!$action || (!$isAdm && $action['user_id']!=$uid)) { header('Location: /gtm-tracker/actions/index.php'); exit; }
$title      = trim($_POST['title']??'');
$stratId    = !empty($_POST['strategy_id'])  ? (int)$_POST['strategy_id']  : null;
$chanId     = !empty($_POST['channel_id'])   ? (int)$_POST['channel_id']   : null;
$prospectId = !empty($_POST['prospect_id'])  ? (int)$_POST['prospect_id']  : null;
$status     = $_POST['status']   ?? 'A faire';
$priority   = $_POST['priority'] ?? 'Normale';
$result     = trim($_POST['result']??'');
$comment    = trim($_POST['comment']??'');
$desc       = trim($_POST['description']??'');
$date       = $_POST['action_date'] ?? date('Y-m-d');
if (!in_array($status,['A faire','En cours','Terminee','Bloquee']))     $status   = 'A faire';
if (!in_array($priority,['Basse','Normale','Haute','Urgente'])) $priority = 'Normale';
if ($isAdm && !empty($_POST['user_id'])) {
    $targetUserId = (int)$_POST['user_id'];
    $db->prepare("UPDATE actions SET user_id=?,strategy_id=?,channel_id=?,prospect_id=?,title=?,description=?,status=?,priority=?,result=?,comment=?,action_date=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")
       ->execute([$targetUserId,$stratId,$chanId,$prospectId,$title,$desc,$status,$priority,$result,$comment,$date,$id]);
} else {
    $db->prepare("UPDATE actions SET strategy_id=?,channel_id=?,prospect_id=?,title=?,description=?,status=?,priority=?,result=?,comment=?,action_date=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")
       ->execute([$stratId,$chanId,$prospectId,$title,$desc,$status,$priority,$result,$comment,$date,$id]);
}
logActivity($uid,'modification_action',"Action ID:$id");
$_SESSION['flash'] = ['type'=>'success','message'=>'Action mise a jour.'];
header("Location: /gtm-tracker/actions/view.php?id=$id");
exit;