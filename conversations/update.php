<?php
session_start();
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';
if ($_SERVER['REQUEST_METHOD']!=='POST' || !verifyCsrfToken($_POST['csrf_token']??'')) { header('Location: /gtm-tracker/conversations/index.php'); exit; }
$uid = $_SESSION['user_id'];
$isAdm = isAdmin();
$id = (int)($_POST['id']??0);
$db = getDB();
$conv = $db->prepare("SELECT * FROM conversations WHERE id=?");
$conv->execute([$id]); $conv = $conv->fetch();
if (!$conv || (!$isAdm && $conv['user_id']!=$uid)) { header('Location: /gtm-tracker/conversations/index.php'); exit; }
$prospectId = !empty($_POST['prospect_id']) ? (int)$_POST['prospect_id'] : null;
$stratId    = !empty($_POST['strategy_id']) ? (int)$_POST['strategy_id'] : null;
$chanId     = !empty($_POST['channel_id'])  ? (int)$_POST['channel_id']  : null;
$status     = $_POST['status'] ?? 'Nouveau';
$convDate   = $_POST['conversation_date'] ?? date('Y-m-d');
$msgSent    = trim($_POST['message_sent'] ?? '');
$respRcv    = trim($_POST['response_received'] ?? '');
$nextAction = trim($_POST['next_action'] ?? '');
$comment    = trim($_POST['comment'] ?? '');
$allowed = ['Nouveau','Contacte','En discussion','Interesse','Rendez-vous','Converti','Perdu','A relancer'];
if (!in_array($status,$allowed)) $status='Nouveau';
$db->prepare("UPDATE conversations SET prospect_id=?,strategy_id=?,channel_id=?,message_sent=?,response_received=?,next_action=?,status=?,conversation_date=?,comment=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")
   ->execute([$prospectId,$stratId,$chanId,$msgSent,$respRcv,$nextAction,$status,$convDate,$comment,$id]);
logActivity($uid,'modif_conversation',"Conv ID:$id");
$_SESSION['flash'] = ['type'=>'success','message'=>'Conversation mise a jour.'];
header("Location: /gtm-tracker/conversations/view.php?id=$id");
exit;