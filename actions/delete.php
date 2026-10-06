<?php
session_start();
require_once __DIR__ . '/../auth/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$uid = $_SESSION['user_id'];
$isAdm = isAdmin();
$id = (int)($_GET['id'] ?? 0);
$action = $db->prepare("SELECT * FROM actions WHERE id=?");
$action->execute([$id]);
$action = $action->fetch();
if (!$action || (!$isAdm && $action['user_id']!=$uid)) { header('Location: /gtm-tracker/actions/index.php'); exit; }
logActivity($uid,'suppression_action',"Action ID:$id - ".(string)$action['title']);
$db->prepare("DELETE FROM actions WHERE id=?")->execute([$id]);
$_SESSION['flash'] = ['type'=>'success','message'=>'Action supprimee.'];
header('Location: /gtm-tracker/actions/index.php');
exit;