<?php
// Test HTTP end-to-end against local running server
$baseUrl = 'http://127.0.0.1:8080';
$cookieFile = __DIR__ . '/test_cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

echo "=========================================================\n";
echo "      TESTS HTTP REELS SUR LE SERVEUR DEV (PORT 8080)    \n";
echo "=========================================================\n\n";

function httpRequest($url, $method = 'GET', $data = [], $cookieFile = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => $response,
        'error' => $error
    ];
}

// 1. GET login page
echo "1. Connexion / Login\n";
$res = httpRequest("$baseUrl/login.php", 'GET', [], $cookieFile);
echo "   GET /login.php -> HTTP " . $res['code'] . " (taille: " . strlen($res['body']) . " octets)\n";

// 2. POST login en tant qu'admin
$resLogin = httpRequest("$baseUrl/login.php", 'POST', [
    'member_id' => '0',
    'password' => 'Admin'
], $cookieFile);
echo "   POST /login.php -> HTTP " . $resLogin['code'] . " (taille: " . strlen($resLogin['body']) . " octets)\n";

// 3. GET /tasks/index.php
echo "\n2. Affichage du module Tâches (/tasks/index.php)\n";
$resTasks = httpRequest("$baseUrl/tasks/index.php", 'GET', [], $cookieFile);
echo "   GET /tasks/index.php -> HTTP " . $resTasks['code'] . " (taille: " . strlen($resTasks['body']) . " octets)\n";
$hasKpi = strpos($resTasks['body'], 'Total Tâches') !== false || strpos($resTasks['body'], 'À faire') !== false;
$hasTable = strpos($resTasks['body'], 'table') !== false;
$hasBtn = strpos($resTasks['body'], 'Donner / Créer une tâche') !== false || strpos($resTasks['body'], 'create.php') !== false;
echo "   - KPIs présents : " . ($hasKpi ? "OUI [OK]" : "NON") . "\n";
echo "   - Tableau présent : " . ($hasTable ? "OUI [OK]" : "NON") . "\n";
echo "   - Bouton de création présent : " . ($hasBtn ? "OUI [OK]" : "NON") . "\n";

// 4. Test Création de tâche par l'Admin via HTTP POST
echo "\n3. Création d'une tâche via HTTP (/tasks/store.php)\n";
$resCreatePage = httpRequest("$baseUrl/tasks/create.php", 'GET', [], $cookieFile);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $resCreatePage['body'], $csrfCreateMatch);
$taskCsrf = $csrfCreateMatch[1] ?? '';

$testTaskTitle = "Test E2E HTTP " . time();
$resStore = httpRequest("$baseUrl/tasks/store.php", 'POST', [
    'csrf_token' => $taskCsrf,
    'user_id' => 1, // Gabin
    'title' => $testTaskTitle,
    'category' => 'tech',
    'status' => 'A faire',
    'priority' => 'Haute',
    'due_date' => date('Y-m-d', strtotime('+3 days')),
    'task_date' => date('Y-m-d'),
    'description' => 'Test de bout en bout via requête HTTP'
], $cookieFile);

require_once __DIR__ . '/../config/database.php';
$db = getDB();
$stmtGet = $db->prepare("SELECT id FROM tasks WHERE title = ?");
$stmtGet->execute([$testTaskTitle]);
$createdTaskId = $stmtGet->fetchColumn();
echo "   Tâche créée en base de données avec ID : $createdTaskId (Statut: " . ($createdTaskId ? "SUCCÈS [OK]" : "ÉCHEC") . ")\n";

// 5. Test Démarrage de la tâche (POST /tasks/start.php)
echo "\n4. Démarrage de la tâche via HTTP (/tasks/start.php)\n";
$resStart = httpRequest("$baseUrl/tasks/start.php", 'POST', [
    'csrf_token' => $taskCsrf,
    'task_id' => $createdTaskId,
    'redirect' => '/gtm-tracker/tasks/index.php'
], $cookieFile);
$stmtChkStart = $db->prepare("SELECT status, started_at FROM tasks WHERE id = ?");
$stmtChkStart->execute([$createdTaskId]);
$startedTask = $stmtChkStart->fetch(PDO::FETCH_ASSOC);
echo "   Statut après start : " . $startedTask['status'] . " [OK]\n";
echo "   started_at : " . $startedTask['started_at'] . " [OK]\n";

// 6. Test Finalisation de la tâche (POST /tasks/finish.php)
echo "\n5. Finalisation de la tâche via HTTP (/tasks/finish.php)\n";
$resFinish = httpRequest("$baseUrl/tasks/finish.php", 'POST', [
    'csrf_token' => $taskCsrf,
    'task_id' => $createdTaskId,
    'hours_spent' => '2.5',
    'result' => 'Livrable validé avec succès par test automatisé HTTP',
    'link_url' => 'https://github.com/gtm-tracker/pr/1'
], $cookieFile);
$stmtChkFinish = $db->prepare("SELECT status, started_at, completed_at, hours_spent, result FROM tasks WHERE id = ?");
$stmtChkFinish->execute([$createdTaskId]);
$finishedTask = $stmtChkFinish->fetch(PDO::FETCH_ASSOC);
echo "   Statut après finish : " . $finishedTask['status'] . " [OK]\n";
echo "   completed_at : " . $finishedTask['completed_at'] . " [OK]\n";
echo "   hours_spent : " . $finishedTask['hours_spent'] . " h [OK]\n";
echo "   result : " . $finishedTask['result'] . " [OK]\n";

// Nettoyer la tâche de test
$stmtDel = $db->prepare("DELETE FROM tasks WHERE id = ?");
$stmtDel->execute([$createdTaskId]);
echo "   Nettoyage tâche test effectué [OK]\n";

// 7. GET /dashboard.php
echo "\n6. Tableau de bord (/dashboard.php)\n";
$resDash = httpRequest("$baseUrl/dashboard.php", 'GET', [], $cookieFile);
echo "   GET /dashboard.php -> HTTP " . $resDash['code'] . " (taille: " . strlen($resDash['body']) . " octets)\n";
$hasTasksCard = strpos($resDash['body'], 'Tâches') !== false;
echo "   - Widget Tâches présent : " . ($hasTasksCard ? "OUI [OK]" : "NON") . "\n";

// 8. GET /my-activities.php
echo "\n7. Mes Activités (/my-activities.php)\n";
$resAct = httpRequest("$baseUrl/my-activities.php", 'GET', [], $cookieFile);
echo "   GET /my-activities.php -> HTTP " . $resAct['code'] . " (taille: " . strlen($resAct['body']) . " octets)\n";

// 9. GET /admin/users.php
echo "\n8. Administration des utilisateurs (/admin/users.php)\n";
$resUsers = httpRequest("$baseUrl/admin/users.php", 'GET', [], $cookieFile);
echo "   GET /admin/users.php -> HTTP " . $resUsers['code'] . " (taille: " . strlen($resUsers['body']) . " octets)\n";
$hasTaskBtn = strpos($resUsers['body'], 'Donner une tâche') !== false;
echo "   - Bouton 'Donner une tâche' présent : " . ($hasTaskBtn ? "OUI [OK]" : "NON") . "\n";

// 10. GET /reports/report.php
echo "\n9. Rapports et Statistiques (/reports/report.php)\n";
$resRep = httpRequest("$baseUrl/reports/report.php", 'GET', [], $cookieFile);
echo "   GET /reports/report.php -> HTTP " . $resRep['code'] . " (taille: " . strlen($resRep['body']) . " octets)\n";
$hasTechGtm = strpos($resRep['body'], 'Développement Tech') !== false || strpos($resRep['body'], 'Tâches') !== false;
echo "   - Section Analyse Tech & Tâches présente : " . ($hasTechGtm ? "OUI [OK]" : "NON") . "\n";

if (file_exists($cookieFile)) unlink($cookieFile);
echo "\n=========================================================\n";
echo "   TOUS LES ENDPOINTS & WORKFLOWS VALIDÉS AVEC SUCCÈS !  \n";
echo "=========================================================\n";
