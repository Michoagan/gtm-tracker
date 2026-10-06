<?php
session_start();
require_once __DIR__ . '/../auth/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
if ($_SERVER['REQUEST_METHOD']!=='POST' || !verifyCsrfToken($_POST['csrf_token']??'')) { header('Location: /gtm-tracker/strategies/index.php'); exit; }
$id = (int)($_POST['id']??0);
$name = trim($_POST['name']??'');
$desc = trim($_POST['description']??'');
getDB()->prepare("UPDATE strategies SET name=?,description=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$name,$desc,$id]);
$_SESSION['flash'] = ['type'=>'success','message'=>'Strategie mise a jour.'];
header('Location: /gtm-tracker/strategies/index.php');
exit;