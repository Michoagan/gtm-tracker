<?php
session_start();
require_once __DIR__ . '/../auth/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
$id = (int)($_GET['id']??0);
if ($id == $_SESSION['user_id']) { header('Location: /gtm-tracker/admin/users.php'); exit; }
getDB()->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
$_SESSION['flash'] = ['type'=>'success','message'=>'Utilisateur supprime.'];
header('Location: /gtm-tracker/admin/users.php');
exit;