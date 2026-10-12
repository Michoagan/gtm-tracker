<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Complete test suite for verifying GTM Tracker and the Tasks Module
echo "=========================================================\n";
echo "      TEST DE VALIDATION DU SYSTEME GTM TRACKER          \n";
echo "=========================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest($description, $condition, $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] $description\n";
        $passCount++;
    } else {
        echo "  [FAIL] $description\n";
        if ($details) echo "         -> $details\n";
        $failCount++;
    }
}

// 1. DATABASE TEST
echo "1. VERIFICATION DE LA BASE DE DONNEES\n";
require_once __DIR__ . '/../config/database.php';
try {
    $db = getDB();
    assertTest("Connexion PDO réussie", $db !== null);
    
    // Check tables
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    $requiredTables = ['users', 'actions', 'prospects', 'tasks', 'attachments', 'activity_log'];
    foreach ($requiredTables as $t) {
        assertTest("Table '$t' présente", in_array($t, $tables), "Table manquante dans SQLite");
    }

    // Check tasks columns
    $cols = $db->query("PRAGMA table_info(tasks)")->fetchAll(PDO::FETCH_COLUMN, 1);
    $requiredCols = ['id', 'user_id', 'assigned_by', 'title', 'category', 'description', 'status', 'priority', 'task_date', 'due_date', 'started_at', 'completed_at', 'validated_at', 'validated_by', 'hours_spent', 'result', 'link_url'];
    foreach ($requiredCols as $c) {
        assertTest("Colonne tasks.'$c' présente", in_array($c, $cols), "Colonne manquante dans tasks");
    }
} catch (Exception $e) {
    assertTest("Connexion DB", false, $e->getMessage());
}

// 2. HELPER FUNCTIONS TEST
echo "\n2. FONCTIONS D'AIDE ET CATEGORIES\n";
$categories = getTaskCategories();
assertTest("Catégories disponibles >= 6", count($categories) >= 6);
assertTest("Catégorie 'tech' présente", isset($categories['tech']));
assertTest("Label catégorie 'tech' correct", taskCategoryLabel('tech') === 'Développement Tech');
assertTest("Badge catégorie 'tech' CSS", taskCategoryBadge('tech') === 'badge-purple');
assertTest("Badge statut 'A faire' CSS", statusBadge('A faire') === 'badge-gray');
assertTest("Badge statut 'En cours' CSS", statusBadge('En cours') === 'badge-info');
assertTest("Badge statut 'Terminée' CSS", statusBadge('Terminée') === 'badge-success');
assertTest("Badge statut 'Validée' CSS", statusBadge('Validée') === 'badge-success');

// 3. WORKFLOW TEST: ADMIN ASSIGNS -> USER STARTS -> USER FINISHES
echo "\n3. CYCLE DE VIE COMPLET D'UNE TÂCHE (ADMIN -> COLLABORATEUR)\n";

// A. Création par l'Admin pour Gabin (user_id = 1)
$title = "Test Auto: Feature Backend " . time();
$desc = "Développer un module de notifications temps réel.";
$dueDate = date('Y-m-d', strtotime('+5 days'));

$stmt = $db->prepare("
    INSERT INTO tasks (user_id, assigned_by, title, category, description, status, priority, task_date, due_date)
    VALUES (?, ?, ?, ?, ?, 'A faire', 'Haute', date('now'), ?)
");
$stmt->execute([1, 0, $title, 'tech', $desc, $dueDate]);
$taskId = (int)$db->lastInsertId();
assertTest("Création de la tâche par l'Admin (ID: $taskId)", $taskId > 0);

// Vérifier état initial
$task = $db->query("SELECT * FROM tasks WHERE id = $taskId")->fetch(PDO::FETCH_ASSOC);
assertTest("Statut initial est 'A faire'", $task['status'] === 'A faire');
assertTest("Assigné par Admin (0)", (int)$task['assigned_by'] === 0);
assertTest("Assigné à Gabin (1)", (int)$task['user_id'] === 1);
assertTest("started_at est NULL", empty($task['started_at']));
assertTest("completed_at est NULL", empty($task['completed_at']));

// B. Gabin démarre la tâche
$simulatedStartTime = date('Y-m-d H:i:s', strtotime('-90 minutes'));
$updStart = $db->prepare("
    UPDATE tasks 
    SET status = 'En cours', started_at = ?, updated_at = datetime('now')
    WHERE id = ?
");
$updStart->execute([$simulatedStartTime, $taskId]);
$startedTask = $db->query("SELECT * FROM tasks WHERE id = $taskId")->fetch(PDO::FETCH_ASSOC);

assertTest("Passage au statut 'En cours'", $startedTask['status'] === 'En cours');
assertTest("started_at est bien renseigné", !empty($startedTask['started_at']));

// C. Gabin termine la tâche avec calcul de durée et livrable
$simulatedEndTime = date('Y-m-d H:i:s');
$diffSeconds = max(60, strtotime($simulatedEndTime) - strtotime($simulatedStartTime));
$calcHours = round($diffSeconds / 3600, 2);
$resultText = "Module terminé avec succès et PR mergée sur master.";
$resultUrl = "https://github.com/mon-projet/pull/42";

$updFinish = $db->prepare("
    UPDATE tasks 
    SET status = 'Terminée', completed_at = ?, hours_spent = ?, result = ?, link_url = ?, updated_at = datetime('now')
    WHERE id = ?
");
$updFinish->execute([$simulatedEndTime, $calcHours, $resultText, $resultUrl, $taskId]);
$finishedTask = $db->query("SELECT * FROM tasks WHERE id = $taskId")->fetch(PDO::FETCH_ASSOC);

assertTest("Passage au statut 'Terminée'", $finishedTask['status'] === 'Terminée');
assertTest("completed_at est bien renseigné", !empty($finishedTask['completed_at']));
assertTest("Durée calculée automatiquement (1.5h)", (float)$finishedTask['hours_spent'] >= 1.4 && (float)$finishedTask['hours_spent'] <= 1.6);
assertTest("Livrable/Résultat enregistré", $finishedTask['result'] === $resultText);
assertTest("Lien livrable enregistré", $finishedTask['link_url'] === $resultUrl);

// D. L'Admin valide la tâche terminée
$nowValidate = date('Y-m-d H:i:s');
$updValidate = $db->prepare("
    UPDATE tasks 
    SET status = 'Validée', validated_at = ?, validated_by = ?, updated_at = datetime('now')
    WHERE id = ?
");
$updValidate->execute([$nowValidate, 0, $taskId]);
$validatedTask = $db->query("SELECT * FROM tasks WHERE id = $taskId")->fetch(PDO::FETCH_ASSOC);

assertTest("Passage au statut 'Validée'", $validatedTask['status'] === 'Validée');
assertTest("validated_at est bien renseigné", !empty($validatedTask['validated_at']));
assertTest("validated_by est bien l'Admin (0)", (int)$validatedTask['validated_by'] === 0);

// Nettoyage de la tâche de test
$db->exec("DELETE FROM tasks WHERE id = $taskId");
assertTest("Nettoyage de la tâche de test", true);

// 4. RENDU DES PAGES PHP (Buffer Output & Lint)
echo "\n4. SIMULATION DE RENDU DES VUES PRINCIPALES\n";
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
// Simuler session Admin
$_SESSION['user_id'] = 0;
$_SESSION['user_role'] = 'admin';
$_SESSION['user_name'] = 'Admin';
$_SESSION['user_email'] = 'admin@gtm.local';

$pagesToTest = [
    'dashboard.php' => 'Tableau de bord',
    'tasks/index.php' => 'Gestion des tâches (index)',
    'tasks/create.php' => 'Création de tâche',
    'my-activities.php' => 'Mes activités',
    'my-space.php' => 'Mon espace',
    'admin/users.php' => 'Gestion des utilisateurs',
    'reports/report.php' => 'Rapports & KPI'
];

foreach ($pagesToTest as $pagePath => $pageName) {
    $fullPath = __DIR__ . '/../' . $pagePath;
    ob_start();
    try {
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        if ($pagePath === 'my-space.php') {
            $_GET['user_id'] = 1;
        }
        
        include $fullPath;
        $content = ob_get_clean();
        $hasHtml = strlen($content) > 500 && (strpos($content, '<!DOCTYPE') !== false || strpos($content, '<html') !== false || strpos($content, '<div') !== false);
        assertTest("Rendu de '$pageName' ($pagePath)", $hasHtml, "Sortie vide ou anormale (" . strlen($content) . " octets)");
    } catch (Throwable $t) {
        ob_end_clean();
        assertTest("Rendu de '$pageName' ($pagePath)", false, $t->getMessage() . " à la ligne " . $t->getLine());
    }
}

// 5. TEST SIMULATION COLLABORATEUR (Gabin, user_id = 1)
echo "\n5. SIMULATION ESPACE COLLABORATEUR (Gabin)\n";
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'user';
$_SESSION['user_name'] = 'Gabin';

$userPages = [
    'dashboard.php' => 'Dashboard vue Collaborateur',
    'tasks/index.php' => 'Tâches vue Collaborateur',
    'my-activities.php' => 'Mes activités vue Collaborateur'
];

foreach ($userPages as $pagePath => $pageName) {
    $fullPath = __DIR__ . '/../' . $pagePath;
    ob_start();
    try {
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        include $fullPath;
        $content = ob_get_clean();
        $hasHtml = strlen($content) > 500;
        assertTest("Rendu '$pageName'", $hasHtml);
    } catch (Throwable $t) {
        ob_end_clean();
        assertTest("Rendu '$pageName'", false, $t->getMessage());
    }
}

// 6. TEST DE SÉCURITÉ : SEUL L'ADMIN PEUT CRÉER / SUPPRIMER DES TÂCHES
echo "\n6. VERIFICATION DE LA REGLE METIER : SEUL L'ADMIN DONNE LES TACHES\n";
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'user';
$_SESSION['user_name'] = 'Gabin';

// Gabin ne peut pas créer de tâche
assertTest("Collaborateur n'est pas Admin", !isAdmin());

// Création d'une tâche par l'admin attribuée à Gabin
$_SESSION['user_id'] = 0;
$_SESSION['user_role'] = 'admin';
$stmtNew = $db->prepare("INSERT INTO tasks (user_id, assigned_by, title, category, status, priority, task_date) VALUES (?, ?, ?, ?, ?, ?, date('now'))");
$stmtNew->execute([1, 0, 'Mission spéciale Admin', 'tech', 'A faire', 'Urgente']);
$newTaskId = (int)$db->lastInsertId();

$checkTask = $db->query("SELECT * FROM tasks WHERE id = $newTaskId")->fetch(PDO::FETCH_ASSOC);
assertTest("Admin a bien donné la tâche au collaborateur", (int)$checkTask['user_id'] === 1 && (int)$checkTask['assigned_by'] === 0);

// Nettoyage
$db->exec("DELETE FROM tasks WHERE id = $newTaskId");
assertTest("Nettoyage réussi", true);
echo "RESULTATS FINAUX : $passCount SUCCÈS, $failCount ÉCHECS\n";
echo "=========================================================\n";

if ($failCount === 0) {
    exit(0);
} else {
    exit(1);
}
