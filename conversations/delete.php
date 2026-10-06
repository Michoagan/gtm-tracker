<?php
session_start();
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$uid = $_SESSION['user_id'];
$isAdm = isAdmin();
$id = (int)($_GET['id']??0);
$conv = $db->prepare("SELECT * FROM conversations WHERE id=?");
$conv->execute([$id]); $conv = $conv->fetch();
if (!$conv || (!$isAdm && $conv['user_id']!=$uid)) { header('Location: /gtm-tracker/conversations/index.php'); exit; }
logActivity($uid,'suppression_conversation',"Conv ID:$id");
$db->prepare("DELETE FROM conversations WHERE id=?")->execute([$id]);
$_SESSION['flash'] = ['type'=>'success','message'=>'Conversation supprimee.'];
header('Location: /gtm-tracker/conversations/index.php');
exit;