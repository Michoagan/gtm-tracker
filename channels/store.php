<?php
session_start();
require_once __DIR__ . '/../auth/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
if ($_SERVER['REQUEST_METHOD']!=='POST' || !verifyCsrfToken($_POST['csrf_token']??'')) { header('Location: /gtm-tracker/channels/index.php'); exit; }
$name = trim($_POST['name']??'');
if (empty($name)) { header('Location: /gtm-tracker/channels/create.php'); exit; }
try { getDB()->prepare("INSERT INTO channels (name) VALUES (?)")->execute([$name]); }
catch (Exception $e) { $_SESSION['flash']=['type'=>'error','message'=>'Ce canal existe deja.']; header('Location: /gtm-tracker/channels/create.php'); exit; }
$_SESSION['flash'] = ['type'=>'success','message'=>'Canal cree!'];
header('Location: /gtm-tracker/channels/index.php');
exit;