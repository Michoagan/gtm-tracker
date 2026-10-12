<?php
$pageTitle = 'Détails de la tâche';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$uid = (int)$_SESSION['user_id'];
$isAdm = isAdmin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /gtm-tracker/tasks/index.php');
    exit;
}

$stmt = $db->prepare("
    SELECT t.*, u.name as user_name, u.email as user_email, assigner.name as assigned_by_name
    FROM tasks t
    JOIN users u ON u.id = t.user_id
    LEFT JOIN users assigner ON assigner.id = t.assigned_by
    WHERE t.id = ?
");
$stmt->execute([$id]);
$task = $stmt->fetch();

if (!$task) {
    echo "<div class='alert alert-danger'>Tâche introuvable.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Fetch attachments for this task
$stmtAtt = $db->prepare("SELECT * FROM attachments WHERE task_id = ? ORDER BY id DESC");
$stmtAtt->execute([$id]);
$attachments = $stmtAtt->fetchAll();

$isOwner = ((int)$task['user_id'] === $uid);
$canManage = ($isOwner || $isAdm);
$catBadge = taskCategoryBadge($task['category']);
$catLabel = taskCategoryLabel($task['category']);
$isDone = in_array(mb_strtolower($task['status'], 'UTF-8'), ['terminée', 'terminee', 'termine']);
?>

<div class="page-header">
    <div>
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:6px">
            <span class="badge <?= $catBadge ?>">
                <?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?>
            </span>
            <span class="badge <?= statusBadge($task['status']) ?>">
                <?= htmlspecialchars($task['status'], ENT_QUOTES, 'UTF-8') ?>
            </span>
            <span class="badge <?= priorityBadge($task['priority']) ?>">
                Priorité <?= htmlspecialchars($task['priority'], ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>
        <h2 class="page-header-title"><?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="page-header-sub">
            Réalisé par <strong><?= htmlspecialchars($task['user_name'], ENT_QUOTES, 'UTF-8') ?></strong>
            le <?= date('d/m/Y', strtotime($task['task_date'])) ?>
            <?php if (!empty($task['assigned_by_name'])): ?>
                • Assigné par <?= htmlspecialchars($task['assigned_by_name'], ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
        </p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <a href="/gtm-tracker/tasks/index.php" class="btn btn-secondary">
            ← Liste des tâches
        </a>
        <?php if ($canManage): ?>
            <?php if (in_array(mb_strtolower($task['status'], 'UTF-8'), ['a faire', 'a faire'])): ?>
                <form method="POST" action="/gtm-tracker/tasks/start.php" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                    <input type="hidden" name="redirect" value="/gtm-tracker/tasks/view.php?id=<?= $task['id'] ?>">
                    <button type="submit" class="btn btn-primary" style="background:#6366f1;font-weight:700">
                        ▶️ Commencer la tâche
                    </button>
                </form>
            <?php elseif (in_array(mb_strtolower($task['status'], 'UTF-8'), ['en cours'])): ?>
                <a href="/gtm-tracker/tasks/finish.php?id=<?= $task['id'] ?>" class="btn btn-success" style="font-weight:700">
                    🏁 Marquer comme terminée
                </a>
            <?php else: ?>
                <form method="POST" action="/gtm-tracker/tasks/toggle-status.php" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                    <input type="hidden" name="redirect" value="/gtm-tracker/tasks/view.php?id=<?= $task['id'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm" title="Remettre la tâche en cours si besoin">
                        ↩️ Remettre En cours
                    </button>
                </form>
            <?php endif; ?>

            <a href="/gtm-tracker/tasks/edit.php?id=<?= $task['id'] ?>" class="btn btn-secondary">
                ✏️ Modifier
            </a>
            <form method="POST" action="/gtm-tracker/tasks/delete.php" style="display:inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette tâche ?');">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="id" value="<?= $task['id'] ?>">
                <button type="submit" class="btn btn-danger">
                    🗑️ Supprimer
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_SESSION['flash'])): ?>
    <div class="alert alert-<?= htmlspecialchars($_SESSION['flash']['type'], ENT_QUOTES, 'UTF-8') ?>" style="margin-bottom:20px">
        <?= htmlspecialchars($_SESSION['flash']['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:start">
    <!-- Main Content -->
    <div style="display:flex;flex-direction:column;gap:20px">
        <!-- Description -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Détails du travail effectué</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($task['description'])): ?>
                    <div style="font-size:14.5px;line-height:1.7;color:var(--text-main);white-space:pre-wrap">
                        <?= htmlspecialchars($task['description'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php else: ?>
                    <p style="color:var(--text-muted);font-style:italic">Aucune description détaillée fournie.</p>
                <?php endif; ?>

                <?php if (!empty($task['link_url'])): ?>
                    <div style="margin-top:20px;padding:12px 16px;background:rgba(14,165,233,0.08);border:1px solid rgba(14,165,233,0.25);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:space-between">
                        <div style="display:flex;align-items:center;gap:10px">
                            <span style="font-size:18px">🔗</span>
                            <div>
                                <div style="font-size:12px;color:var(--text-dim);text-transform:uppercase;font-weight:600">Lien externe / Référence</div>
                                <a href="<?= htmlspecialchars($task['link_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" style="font-weight:600;color:var(--info);word-break:break-all">
                                    <?= htmlspecialchars($task['link_url'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </div>
                        </div>
                        <a href="<?= htmlspecialchars($task['link_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="btn btn-sm btn-primary" style="white-space:nowrap">
                            Ouvrir ↗
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Result / Deliverable -->
        <div class="card">
            <div class="card-header" style="border-bottom:1px solid rgba(16,185,129,0.2);background:rgba(16,185,129,0.04)">
                <h3 class="card-title" style="color:#10b981;display:flex;align-items:center;gap:8px">
                    🎯 Livrable &amp; Résultat obtenu
                </h3>
            </div>
            <div class="card-body">
                <?php if (!empty($task['result'])): ?>
                    <div style="font-size:14px;line-height:1.6;color:var(--text-main);white-space:pre-wrap">
                        <?= htmlspecialchars($task['result'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php else: ?>
                    <p style="color:var(--text-muted);font-style:italic">Aucun livrable formalisé pour le moment.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Attachments -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Pièces jointes &amp; Fichiers (<?= count($attachments) ?>)</h3>
            </div>
            <div class="card-body">
                <?php if (empty($attachments)): ?>
                    <p style="color:var(--text-muted);font-style:italic;margin-bottom:0">Aucun fichier joint à cette tâche.</p>
                <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:10px">
                        <?php foreach ($attachments as $att): 
                            $filePath = '/' . ltrim($att['file_path'], '/');
                            if (strpos($filePath, '/gtm-tracker/') !== 0) {
                                $filePath = '/gtm-tracker/' . ltrim($att['file_path'], '/');
                            }
                            $isImg = in_array(strtolower(pathinfo($att['original_name'], PATHINFO_EXTENSION)), ['png','jpg','jpeg','gif','webp']);
                        ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--bg-elevated);border-radius:var(--radius-sm);border:1px solid var(--border)">
                            <div style="display:flex;align-items:center;gap:10px">
                                <span style="font-size:18px"><?= $isImg ? '🖼️' : '📄' ?></span>
                                <div>
                                    <div style="font-weight:600;color:var(--text-main);font-size:13.5px">
                                        <?= htmlspecialchars($att['original_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div style="font-size:11.5px;color:var(--text-dim)">
                                        <?= round($att['file_size'] / 1024, 1) ?> Ko • Ajouté le <?= date('d/m/Y H:i', strtotime($att['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                            <div style="display:flex;gap:8px">
                                <a href="<?= htmlspecialchars($filePath, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn btn-secondary btn-sm">
                                    Voir / Télécharger
                                </a>
                            </div>
                        </div>
                        <?php if ($isImg): ?>
                            <div style="margin-top:4px;margin-bottom:8px;border-radius:8px;overflow:hidden;border:1px solid var(--border);max-width:500px">
                                <img src="<?= htmlspecialchars($filePath, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($att['original_name'], ENT_QUOTES, 'UTF-8') ?>" style="width:100%;height:auto;display:block">
                            </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar Info -->
    <div style="display:flex;flex-direction:column;gap:18px">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title" style="font-size:14px">Informations clés</h4>
            </div>
            <div class="card-body" style="padding:16px 20px">
                <div style="display:flex;flex-direction:column;gap:14px">
                    <div>
                        <div style="font-size:11.5px;color:var(--text-dim);text-transform:uppercase;font-weight:600">Responsable</div>
                        <div style="display:flex;align-items:center;gap:8px;margin-top:4px">
                            <div class="user-avatar" style="width:28px;height:28px;font-size:11px">
                                <?= strtoupper(substr($task['user_name'], 0, 1)) ?>
                            </div>
                            <span style="font-weight:600;color:var(--text-main)"><?= htmlspecialchars($task['user_name'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>

                    <?php if (!empty($task['assigned_by_name'])): ?>
                    <div>
                        <div style="font-size:11.5px;color:var(--text-dim);text-transform:uppercase;font-weight:600">Donnée / Assignée par</div>
                        <div style="font-weight:600;color:var(--primary);margin-top:2px">
                            👤 <?= htmlspecialchars($task['assigned_by_name'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div>
                        <div style="font-size:11.5px;color:var(--text-dim);text-transform:uppercase;font-weight:600">Catégorie</div>
                        <div style="margin-top:4px">
                            <span class="badge <?= $catBadge ?>">
                                <?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                    </div>

                    <div>
                        <div style="font-size:11.5px;color:var(--text-dim);text-transform:uppercase;font-weight:600">Date d'attribution</div>
                        <div style="font-weight:600;color:var(--text-main);margin-top:4px">
                            📅 <?= date('d/m/Y', strtotime($task['task_date'])) ?>
                        </div>
                    </div>

                    <?php if (!empty($task['due_date'])): ?>
                    <div>
                        <div style="font-size:11.5px;color:var(--text-dim);text-transform:uppercase;font-weight:600">Échéance / Date limite</div>
                        <div style="font-weight:600;color:#f59e0b;margin-top:4px">
                            ⏳ <?= date('d/m/Y', strtotime($task['due_date'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div style="background:var(--bg-base);padding:12px;border-radius:var(--radius-sm);border:1px solid var(--border)">
                        <div style="font-size:11px;color:var(--text-dim);text-transform:uppercase;font-weight:700;margin-bottom:8px">⏱️ Suivi Début / Fin</div>
                        
                        <div style="margin-bottom:6px">
                            <div style="font-size:11px;color:var(--text-muted)">Commencée le :</div>
                            <div style="font-weight:600;color:<?= !empty($task['started_at']) ? 'var(--info)' : 'var(--text-dim)' ?>">
                                <?= !empty($task['started_at']) ? '▶️ ' . date('d/m/Y à H:i', strtotime($task['started_at'])) : '⏳ Pas encore commencée' ?>
                            </div>
                        </div>

                        <div style="margin-bottom:6px">
                            <div style="font-size:11px;color:var(--text-muted)">Terminée le :</div>
                            <div style="font-weight:600;color:<?= !empty($task['completed_at']) ? '#10b981' : 'var(--text-dim)' ?>">
                                <?= !empty($task['completed_at']) ? '🏁 ' . date('d/m/Y à H:i', strtotime($task['completed_at'])) : '— En attente de fin' ?>
                            </div>
                        </div>

                        <div>
                            <div style="font-size:11px;color:var(--text-muted)">Temps effectif total :</div>
                            <div style="font-size:14px;font-weight:700;color:var(--text-main);margin-top:2px">
                                <?= ((float)$task['hours_spent'] > 0) ? number_format((float)$task['hours_spent'], 1, ',', ' ') . ' h' : '—' ?>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div style="font-size:11.5px;color:var(--text-dim);text-transform:uppercase;font-weight:600">Statut actuel</div>
                        <div style="margin-top:4px">
                            <span class="badge <?= statusBadge($task['status']) ?>">
                                <?= htmlspecialchars($task['status'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                    </div>

                    <div>
                        <div style="font-size:11.5px;color:var(--text-dim);text-transform:uppercase;font-weight:600">Niveau de priorité</div>
                        <div style="margin-top:4px">
                            <span class="badge <?= priorityBadge($task['priority']) ?>">
                                <?= htmlspecialchars($task['priority'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                    </div>

                    <div style="border-top:1px solid var(--border);padding-top:10px;font-size:11.5px;color:var(--text-dim)">
                        Créée le <?= date('d/m/Y à H:i', strtotime($task['created_at'])) ?>
                    </div>
                </div>
            </div>
        </div>

        <a href="/gtm-tracker/tasks/create.php" class="btn btn-primary" style="width:100%;text-align:center">
            + Enregistrer une autre tâche
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
