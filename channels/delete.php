<?php
session_start();
require_once __DIR__ . '/../auth/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
$id = (int)($_GET['id']??0);
getDB()->prepare("DELETE FROM channels WHERE id=?")->execute([$id]);
$_SESSION['flash'] = ['type'=>'success','message'=>'Canal supprime.'];
header('Location: /gtm-tracker/channels/index.php');
exit;