<?php
session_start();
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    header('Location: /gtm-tracker/prospects/create.php');
    exit;
}

$uid = $_SESSION['user_id'];
$db = getDB();

$name       = trim($_POST['name'] ?? '');
$company    = trim($_POST['company'] ?? '');
$contact    = trim($_POST['contact'] ?? '');
$channelId  = !empty($_POST['channel_id']) ? (int)$_POST['channel_id'] : null;
$status     = $_POST['status'] ?? 'Nouveau';
$assignedTo = !empty($_POST['assigned_to']) && isAdmin() ? (int)$_POST['assigned_to'] : $uid;

$followUpStatus   = $_POST['follow_up_status'] ?? 'A relancer';
$nextFollowupDate = !empty($_POST['next_followup_date']) ? $_POST['next_followup_date'] : null;
$notes            = trim($_POST['notes'] ?? '');

$messageSent      = trim($_POST['message_sent'] ?? '');
$responseReceived = trim($_POST['response_received'] ?? '');
$nextAction       = trim($_POST['next_action'] ?? '');

if (empty($name)) {
    header('Location: /gtm-tracker/prospects/create.php');
    exit;
}

// 1. Enregistrer le prospect avec les champs de suivi et relance
$stmt = $db->prepare("
    INSERT INTO prospects (name, company, contact, channel_id, status, assigned_to, follow_up_status, next_followup_date, notes) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([$name, $company, $contact, $channelId, $status, $assignedTo, $followUpStatus, $nextFollowupDate, $notes]);
$prospectId = $db->lastInsertId();

// 2. Si un message ou un échange a été saisi, créer directement la conversation liée
if (!empty($messageSent) || !empty($responseReceived) || !empty($nextAction)) {
    $convDate = date('Y-m-d');
    $stmtC = $db->prepare("
        INSERT INTO conversations (prospect_id, user_id, channel_id, status, conversation_date, message_sent, response_received, next_action)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtC->execute([$prospectId, $assignedTo, $channelId, $status, $convDate, $messageSent, $responseReceived, $nextAction]);
}

logActivity($uid, 'ajout_prospect', "Prospect: $name (ID: $prospectId)");
$_SESSION['flash'] = ['type' => 'success', 'message' => 'Prospect et suivi enregistrés avec succès !'];
header('Location: /gtm-tracker/prospects/view.php?id=' . $prospectId);
exit;
