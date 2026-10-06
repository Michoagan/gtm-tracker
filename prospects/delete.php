<?php
session_start();
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$uid = $_SESSION['user_id'];
$isAdm = isAdmin();
$id = (int)($_GET['id']??0);
$p = $db->prepare("SELECT * FROM prospects WHERE id=?");
$p->execute([$id]); $p = $p->fetch();
if (!$p || (!$isAdm && $p['assigned_to']!=$uid)) { header('Location: /gtm-tracker/prospects/index.php'); exit; }
$db->prepare("DELETE FROM prospects WHERE id=?")->execute([$id]);
$_SESSION['flash'] = ['type'=>'success','message'=>'Prospect supprime.'];
header('Location: /gtm-tracker/prospects/index.php');
exit;