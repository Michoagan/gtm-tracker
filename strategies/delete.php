<?php
session_start();
require_once __DIR__ . '/../auth/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
$id = (int)($_GET['id']??0);
getDB()->prepare("DELETE FROM strategies WHERE id=?")->execute([$id]);
$_SESSION['flash'] = ['type'=>'success','message'=>'Strategie supprimee.'];
header('Location: /gtm-tracker/strategies/index.php');
exit;