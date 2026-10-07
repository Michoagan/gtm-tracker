<?php
session_start();
require_once __DIR__ . '/config/database.php';
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== '') {
    logActivity($_SESSION['user_id'], 'deconnexion', 'Deconnexion utilisateur');
}
session_destroy();
header('Location: /gtm-tracker/login.php');
exit;