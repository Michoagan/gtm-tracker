<?php
$pageTitle = 'Modifier la tâche';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$uid = (int)$_SESSION['user_id'];
$isAdm = isAdmin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /gtm-tracker/tasks/index.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$id]);
$task = $stmt->fetch();

if (!$task) {
    echo "<div class='alert alert-danger'>Tâche introuvable.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Permission check: owner or admin
if (!$isAdm && (int)$task['user_id'] !== $uid) {
    header('Location: /gtm-tracker/tasks/index.php?error=unauthorized');
    exit;
}

$allMembers = array_values(getMembers());
$categories = getTaskCategories();
$statuses   = ['Terminée', 'En cours', 'A faire', 'Bloquée'];
$priorities = ['Normale', 'Haute', 'Urgente', 'Basse'];

// Fetch current attachments
$stmtAtt = $db->prepare("SELECT * FROM attachments WHERE task_id = ?");
$stmtAtt->execute([$id]);
$attachments = $stmtAtt->fetchAll();
?>

<div class="page-header">
    <div>
        <h2 class="page-header-title">Modifier la tâche</h2>
        <p class="page-header-sub">Mettez à jour les informations et l'avancement de cette tâche</p>
    </div>
    <div style="display:flex;gap:10px">
        <a href="/gtm-tracker/tasks/view.php?id=<?= $task['id'] ?>" class="btn btn-secondary">
            ← Voir la tâche
        </a>
    </div>
</div>

<div class="card" style="max-width:850px;margin:0 auto">
    <div class="card-body" style="padding:28px">
        <form method="POST" action="/gtm-tracker/tasks/update.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="id" value="<?= $task['id'] ?>">

            <div class="form-grid">
                <!-- Titre -->
                <div class="form-group form-full">
                    <label class="form-label" style="font-weight:600">Intitulé de la tâche *</label>
                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <!-- Catégorie -->
                <div class="form-group">
                    <label class="form-label" style="font-weight:600">Domaine / Catégorie *</label>
                    <select name="category" class="form-control" required style="font-weight:600">
                        <?php foreach ($categories as $catKey => $catInfo): ?>
                        <option value="<?= $catKey ?>" <?= ($task['category'] === $catKey) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($catInfo['label'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Utilisateur assigné -->
                <div class="form-group">
                    <label class="form-label" style="font-weight:600">Réalisé par *</label>
                    <?php if ($isAdm): ?>
                        <select name="user_id" class="form-control" style="font-weight:600">
                            <?php foreach ($allMembers as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= ((int)$task['user_id'] === (int)$m['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8') ?>" disabled style="background:var(--bg-elevated);color:var(--text-main);font-weight:600">
                        <input type="hidden" name="user_id" value="<?= $task['user_id'] ?>">
                    <?php endif; ?>
                </div>

                <!-- Date -->
                <div class="form-group">
                    <label class="form-label" style="font-weight:600">Date d'attribution / Prévue *</label>
                    <input type="date" name="task_date" class="form-control" value="<?= htmlspecialchars($task['task_date'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <!-- Date d'échéance -->
                <div class="form-group">
                    <label class="form-label" style="font-weight:600">Date limite / Échéance</label>
                    <input type="date" name="due_date" class="form-control" value="<?= htmlspecialchars($task['due_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <!-- Temps passé -->
                <div class="form-group">
                    <label class="form-label" style="font-weight:600">Temps passé (heures)</label>
                    <input type="number" name="hours_spent" class="form-control" step="0.25" min="0" max="100" value="<?= (float)$task['hours_spent'] ?>">
                    <?php if (!empty($task['started_at'])): ?>
                        <span class="form-hint">Commencée le : <?= date('d/m/Y à H:i', strtotime($task['started_at'])) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Statut -->
                <div class="form-group">
                    <label class="form-label" style="font-weight:600">Statut</label>
                    <select name="status" class="form-control">
                        <?php foreach ($statuses as $st): ?>
                        <option value="<?= $st ?>" <?= (mb_strtolower($task['status'], 'UTF-8') === mb_strtolower($st, 'UTF-8')) ? 'selected' : '' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Priorité -->
                <div class="form-group">
                    <label class="form-label" style="font-weight:600">Priorité</label>
                    <select name="priority" class="form-control">
                        <?php foreach ($priorities as $pr): ?>
                        <option value="<?= $pr ?>" <?= ($task['priority'] === $pr) ? 'selected' : '' ?>><?= $pr ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Lien externe -->
                <div class="form-group form-full">
                    <label class="form-label" style="font-weight:600">Lien externe / Référence</label>
                    <input type="url" name="link_url" class="form-control" value="<?= htmlspecialchars($task['link_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://github.com/...">
                </div>

                <!-- Description -->
                <div class="form-group form-full">
                    <label class="form-label" style="font-size:14px;font-weight:700;color:#000000">
                        📋 Détails du travail à effectuer / Spécifications
                    </label>
                    <textarea name="description" class="form-control" rows="5" style="background:#ffffff;color:#000000;font-size:15px;line-height:1.6;border:1.5px solid #cbd5e1;border-radius:8px;padding:14px 16px"><?= htmlspecialchars($task['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <!-- Résultat / Livrable -->
                <div class="form-group form-full">
                    <label class="form-label" style="font-size:14px;font-weight:700;color:#000000">
                        🎯 Livrable / Résultat obtenu
                    </label>
                    <textarea name="result" class="form-control" rows="3" style="background:#ffffff;color:#000000;font-size:15px;line-height:1.6;border:1.5px solid #cbd5e1;border-radius:8px;padding:14px 16px"><?= htmlspecialchars($task['result'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <!-- Ajouter un fichier -->
                <div class="form-group form-full">
                    <label class="form-label" style="font-weight:600">Ajouter une pièce jointe (optionnel)</label>
                    <input type="file" name="attachment" class="form-control" accept="image/*,.pdf,.zip,.txt">
                    <?php if (!empty($attachments)): ?>
                        <div style="margin-top:8px;font-size:12px;color:var(--text-muted)">
                            📎 <?= count($attachments) ?> pièce(s) déjà jointe(s) à cette tâche.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:24px;border-top:1px solid var(--border);padding-top:18px">
                <a href="/gtm-tracker/tasks/view.php?id=<?= $task['id'] ?>" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-primary" style="padding:10px 24px">
                    💾 Mettre à jour la tâche
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
