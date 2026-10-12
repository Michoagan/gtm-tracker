<?php
session_start();
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';

$db = getDB();
$currentUid = (int)$_SESSION['user_id'];
$isAdm      = isAdmin();

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Token CSRF invalide');
    }

    $taskId = (int)($_POST['task_id'] ?? 0);
    if ($taskId <= 0) {
        header('Location: /gtm-tracker/tasks/index.php');
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();

    if (!$task) {
        header('Location: /gtm-tracker/tasks/index.php?error=not_found');
        exit;
    }

    // Permission : le membre assigné ou l'admin
    if (!$isAdm && (int)$task['user_id'] !== $currentUid) {
        die('Accès non autorisé');
    }

    $completedAt = date('Y-m-d H:i:s');
    $startedAt   = $task['started_at'] ?? null;
    
    // Si la tâche n'avait pas été marquée commencée, on met la date de création ou il y a 1h par défaut
    if (!$startedAt) {
        $startedAt = $task['created_at'] ?? date('Y-m-d H:i:s');
    }

    // Calcul automatique de la durée écoulée en heures
    $diffSeconds = max(60, strtotime($completedAt) - strtotime($startedAt));
    $autoHours   = round($diffSeconds / 3600, 2);

    // Si l'utilisateur a spécifié des heures manuelles dans le formulaire
    $hoursSpent = isset($_POST['hours_spent']) && $_POST['hours_spent'] !== '' ? (float)$_POST['hours_spent'] : $autoHours;
    if ($hoursSpent <= 0 && $autoHours > 0) {
        $hoursSpent = $autoHours;
    }

    $resultText = isset($_POST['result']) ? trim($_POST['result']) : ($task['result'] ?? '');
    $linkUrl    = isset($_POST['link_url']) && trim($_POST['link_url']) !== '' ? trim($_POST['link_url']) : ($task['link_url'] ?? '');

    // Mise à jour de la tâche
    $stmtUp = $db->prepare("
        UPDATE tasks 
        SET status = 'Terminée', 
            started_at = COALESCE(started_at, ?), 
            completed_at = ?, 
            hours_spent = ?, 
            result = ?, 
            link_url = ?, 
            updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $stmtUp->execute([$startedAt, $completedAt, $hoursSpent, $resultText, $linkUrl, $taskId]);

    // Handle optional file attachment if provided
    if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === 0) {
        $file = $_FILES['attachment'];
        $allowedExt = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf', 'zip', 'txt'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExt) && $file['size'] <= 10 * 1024 * 1024) {
            $newName = uniqid('task_finish_', true) . '.' . $ext;
            $dest = UPLOAD_DIR . $newName;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $db->prepare("INSERT INTO attachments (user_id, task_id, original_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?, ?)")
                   ->execute([$currentUid, $taskId, $file['name'], UPLOAD_URL . $newName, $file['type'], $file['size']]);
            }
        }
    }

    // Format human-readable duration
    $hours = floor($diffSeconds / 3600);
    $mins  = round(($diffSeconds % 3600) / 60);
    $durationStr = ($hours > 0 ? "{$hours}h " : "") . "{$mins}min";

    logActivity($currentUid, 'finalisation_tache', "Tâche terminée : " . $task['title'] . " (Durée: $durationStr, $hoursSpent h)");

    $heure = date('H:i');
    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => "🎉 Félicitations ! Tâche marquée comme terminée à $heure (Durée totale enregistrée : $durationStr • {$hoursSpent}h)."
    ];

    $redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : '/gtm-tracker/tasks/view.php?id=' . $taskId;
    header('Location: ' . $redirect);
    exit;
}

// GET : Page dédiée de validation et saisie de livrable lors de la fin de tâche
$taskId = (int)($_GET['id'] ?? 0);
if ($taskId <= 0) {
    header('Location: /gtm-tracker/tasks/index.php');
    exit;
}

$stmt = $db->prepare("SELECT t.*, u.name as user_name FROM tasks t JOIN users u ON u.id = t.user_id WHERE t.id = ?");
$stmt->execute([$taskId]);
$task = $stmt->fetch();

if (!$task) {
    header('Location: /gtm-tracker/tasks/index.php?error=not_found');
    exit;
}

$pageTitle = 'Finaliser la tâche';
require_once __DIR__ . '/../includes/header.php';

$now = time();
$startedTimestamp = !empty($task['started_at']) ? strtotime($task['started_at']) : strtotime($task['created_at']);
$diffSeconds = max(60, $now - $startedTimestamp);
$hours = floor($diffSeconds / 3600);
$mins  = round(($diffSeconds % 3600) / 60);
$suggestedHours = round($diffSeconds / 3600, 2);
?>

<div class="page-header">
    <div>
        <h2 class="page-header-title">🏁 Clôturer la tâche</h2>
        <p class="page-header-sub">Enregistrez la fin de votre travail et le livrable produit</p>
    </div>
    <a href="/gtm-tracker/tasks/view.php?id=<?= $task['id'] ?>" class="btn btn-secondary">
        ← Retour
    </a>
</div>

<div class="card" style="max-width:700px;margin:0 auto">
    <div class="card-body" style="padding:28px">
        <!-- Résumé de la tâche -->
        <div style="padding:16px;background:var(--bg-elevated);border-radius:var(--radius-sm);border:1px solid var(--border);margin-bottom:24px">
            <div style="font-size:12px;color:var(--text-dim);text-transform:uppercase;font-weight:600;margin-bottom:4px">Tâche à finaliser</div>
            <div style="font-size:16px;font-weight:700;color:var(--text-main);margin-bottom:8px">
                <?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div style="display:flex;gap:12px;font-size:12.5px;color:var(--text-muted);flex-wrap:wrap">
                <span>👤 Réalisé par : <strong><?= htmlspecialchars($task['user_name'], ENT_QUOTES, 'UTF-8') ?></strong></span>
                <?php if (!empty($task['started_at'])): ?>
                    <span>⏱️ Démarrée le : <strong><?= date('d/m/Y à H:i', strtotime($task['started_at'])) ?></strong></span>
                <?php endif; ?>
                <span style="color:#10b981">⌛ Durée écoulée estimée : <strong><?= ($hours > 0 ? "{$hours}h " : "") . "{$mins}min" ?></strong></span>
            </div>
        </div>

        <form method="POST" action="/gtm-tracker/tasks/finish.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">

            <div class="form-grid">
                <!-- Heures passées -->
                <div class="form-group form-full">
                    <label class="form-label" style="font-weight:600">
                        Temps passé effectif (en heures) *
                    </label>
                    <input type="number" name="hours_spent" class="form-control" step="0.25" min="0.1" max="100" value="<?= $suggestedHours > 0 ? $suggestedHours : 1.0 ?>" required style="font-size:15px;font-weight:600">
                    <span class="form-hint">Calculé automatiquement selon l'heure de début (vous pouvez ajuster la valeur si besoin).</span>
                </div>

                <!-- Résultat / Livrable -->
                <div class="form-group form-full">
                    <label class="form-label" style="font-weight:600">
                        🎯 Livrable / Résultat obtenu *
                    </label>
                    <textarea name="result" class="form-control" rows="3" placeholder="Décrivez le résultat concret : Qu'avez-vous produit ? Qu'est-ce qui fonctionne ? (ex: API déployée, branche mergée, tests passés, 15 contacts effectués...)" required><?= htmlspecialchars($task['result'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    <span class="form-hint">Permet à l'admin et à l'équipe de voir immédiatement ce qui a été accompli.</span>
                </div>

                <!-- Lien externe (PR / Git / Doc) -->
                <div class="form-group form-full">
                    <label class="form-label" style="font-weight:600">
                        Lien du livrable / PR GitHub / Documentation (optionnel)
                    </label>
                    <input type="url" name="link_url" class="form-control" value="<?= htmlspecialchars($task['link_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://github.com/... ou lien Figma, Notion, Issue...">
                </div>

                <!-- Pièce jointe / Capture -->
                <div class="form-group form-full">
                    <label class="form-label" style="font-weight:600">
                        Pièce jointe / Preuve / Capture d'écran (optionnel)
                    </label>
                    <input type="file" name="attachment" class="form-control" accept="image/*,.pdf,.zip,.txt">
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:24px;border-top:1px solid var(--border);padding-top:18px">
                <a href="/gtm-tracker/tasks/view.php?id=<?= $task['id'] ?>" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-success" style="padding:10px 24px;font-size:14px;font-weight:700">
                    ✅ Valider &amp; Marquer comme Terminée
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
