<?php
session_start();
require_once __DIR__ . '/../auth/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
if ($_SERVER['REQUEST_METHOD']!=='POST' || !verifyCsrfToken($_POST['csrf_token']??'')) { header('Location: /gtm-tracker/channels/index.php'); exit; }
$id = (int)($_POST['id']??0);
$name = trim($_POST['name']??'');
getDB()->prepare("UPDATE channels SET name=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$name,$id]);
$_SESSION['flash'] = ['type'=>'success','message'=>'Canal mis a jour.'];
header('Location: /gtm-tracker/channels/index.php');
exit;