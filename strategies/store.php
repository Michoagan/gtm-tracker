<?php
session_start();
require_once __DIR__ . '/../auth/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
if ($_SERVER['REQUEST_METHOD']!=='POST' || !verifyCsrfToken($_POST['csrf_token']??'')) { header('Location: /gtm-tracker/strategies/create.php'); exit; }
$uid = $_SESSION['user_id'];
$db = getDB();
$name = trim($_POST['name']??'');
$desc = trim($_POST['description']??'');
if (empty($name)) { header('Location: /gtm-tracker/strategies/create.php'); exit; }
$db->prepare("INSERT INTO strategies (name,description,created_by) VALUES (?,?,?)")->execute([$name,$desc,$uid]);
$_SESSION['flash'] = ['type'=>'success','message'=>'Strategie creee!'];
header('Location: /gtm-tracker/strategies/index.php');
exit;