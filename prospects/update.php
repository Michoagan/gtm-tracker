<?php
session_start();
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';
if ($_SERVER['REQUEST_METHOD']!=='POST' || !verifyCsrfToken($_POST['csrf_token']??'')) { header('Location: /gtm-tracker/prospects/index.php'); exit; }
$uid = $_SESSION['user_id'];
$isAdm = isAdmin();
$id = (int)($_POST['id']??0);
$db = getDB();
$prospect = $db->prepare("SELECT * FROM prospects WHERE id=?");
$prospect->execute([$id]); $prospect = $prospect->fetch();
if (!$prospect || (!$isAdm && $prospect['assigned_to']!=$uid)) { header('Location: /gtm-tracker/prospects/index.php'); exit; }
$name      = trim($_POST['name']??'');
$company   = trim($_POST['company']??'');
$contact   = trim($_POST['contact']??'');
$chanId    = !empty($_POST['channel_id']) ? (int)$_POST['channel_id'] : null;
$status    = $_POST['status'] ?? 'Nouveau';
$assignedTo = $isAdm && !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : $prospect['assigned_to'];
$db->prepare("UPDATE prospects SET name=?,company=?,contact=?,channel_id=?,status=?,assigned_to=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")
   ->execute([$name,$company,$contact,$chanId,$status,$assignedTo,$id]);
$_SESSION['flash'] = ['type'=>'success','message'=>'Prospect mis a jour.'];
header('Location: /gtm-tracker/prospects/index.php');
exit;