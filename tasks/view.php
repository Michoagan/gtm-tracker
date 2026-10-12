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
$stNorm = mb_strtolower($task['status'], 'UTF-8');
$isDone = in_array($stNorm, ['terminée', 'terminee', 'termine']);
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

<!-- BANDEAU INTERACTIF : CHANGER LE STATUT DE LA TÂCHE EN 1 CLIC -->
<?php if ($canManage): ?>
<div class="card" style="margin-bottom:24px;border:2px solid #6366f1;background:linear-gradient(135deg, rgba(99,102,241,0.12), rgba(16,185,129,0.06));border-radius:16px;padding:22px 24px;box-shadow:0 8px 32px rgba(0,0,0,0.35)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px">
        <div style="display:flex;align-items:center;gap:12px">
            <span style="font-size:26px">⚡</span>
            <div>
                <div style="font-size:12px;color:#a5b4fc;font-weight:800;text-transform:uppercase;letter-spacing:0.08em">
                    GESTION DU STATUT — CLIQUEZ SUR UN STATUT POUR LE CHANGER
                </div>
                <div style="font-size:18px;font-weight:800;color:#ffffff;display:flex;align-items:center;gap:10px;margin-top:2px">
                    Statut actuel : <span class="badge <?= statusBadge($task['status']) ?>" style="font-size:14px;padding:5px 14px;font-weight:700"><?= htmlspecialchars($task['status'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
        </div>
        <div style="font-size:13px;color:#cbd5e1;font-weight:500">
            👉 <em>Cliquez sur le statut souhaité ci-dessous :</em>
        </div>
    </div>

    <!-- 4 BOUTONS D'ACTION DIRECTE DU WORKFLOW -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(210px, 1fr));gap:12px">
        <!-- 1. À FAIRE -->
        <form method="POST" action="/gtm-tracker/tasks/toggle-status.php" style="margin:0">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
            <input type="hidden" name="new_status" value="A faire">
            <input type="hidden" name="redirect" value="/gtm-tracker/tasks/view.php?id=<?= $task['id'] ?>">
            <button type="submit" class="btn btn-full" style="padding:14px 16px;font-size:14px;font-weight:700;border-radius:10px;justify-content:flex-start;gap:12px;cursor:pointer;<?= ($stNorm === 'a faire' || $stNorm === 'à faire') ? 'background:#334155;color:#ffffff;border:2.5px solid #94a3b8;box-shadow:0 0 16px rgba(255,255,255,0.25)' : 'background:#131c2e;color:#cbd5e1;border:1.5px solid rgba(255,255,255,0.12)' ?>">
                <span style="font-size:22px">⏳</span>
                <div style="text-align:left">
                    <div style="font-weight:800;font-size:14.5px">1. À faire</div>
                    <div style="font-size:12px;opacity:0.85"><?= ($stNorm === 'a faire' || $stNorm === 'à faire') ? '● Actuellement sélectionné' : 'Mettre en attente' ?></div>
                </div>
            </button>
        </form>

        <!-- 2. EN COURS (DÉMARRER) -->
        <form method="POST" action="/gtm-tracker/tasks/start.php" style="margin:0">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
            <input type="hidden" name="redirect" value="/gtm-tracker/tasks/view.php?id=<?= $task['id'] ?>">
            <button type="submit" class="btn btn-full" style="padding:14px 16px;font-size:14px;font-weight:700;border-radius:10px;justify-content:flex-start;gap:12px;cursor:pointer;<?= ($stNorm === 'en cours') ? 'background:#4338ca;color:#ffffff;border:2.5px solid #818cf8;box-shadow:0 0 20px rgba(99,102,241,0.5)' : 'background:#131c2e;color:#cbd5e1;border:1.5px solid rgba(99,102,241,0.3)' ?>">
                <span style="font-size:22px">⚡</span>
                <div style="text-align:left">
                    <div style="font-weight:800;font-size:14.5px">2. En cours</div>
                    <div style="font-size:12px;opacity:0.85"><?= ($stNorm === 'en cours') ? '● Tâche démarrée' : '▶️ Commencer / Démarrer' ?></div>
                </div>
            </button>
        </form>

        <!-- 3. TERMINÉE (FINALISER) -->
        <a href="/gtm-tracker/tasks/finish.php?id=<?= $task['id'] ?>" class="btn btn-full" style="padding:14px 16px;font-size:14px;font-weight:700;border-radius:10px;justify-content:flex-start;gap:12px;text-decoration:none;cursor:pointer;<?= ($isDone) ? 'background:#065f46;color:#ffffff;border:2.5px solid #34d399;box-shadow:0 0 20px rgba(16,185,129,0.5)' : 'background:#131c2e;color:#cbd5e1;border:1.5px solid rgba(16,185,129,0.35)' ?>">
            <span style="font-size:22px">🏁</span>
            <div style="text-align:left">
                <div style="font-weight:800;font-size:14.5px">3. Terminée</div>
                <div style="font-size:12px;opacity:0.85"><?= ($isDone) ? '● Tâche terminée' : '🏁 Marquer fin & livrable' ?></div>
            </div>
        </a>

        <!-- 4. BLOQUÉE -->
        <form method="POST" action="/gtm-tracker/tasks/toggle-status.php" style="margin:0">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
            <input type="hidden" name="new_status" value="Bloquée">
            <input type="hidden" name="redirect" value="/gtm-tracker/tasks/view.php?id=<?= $task['id'] ?>">
            <button type="submit" class="btn btn-full" style="padding:14px 16px;font-size:14px;font-weight:700;border-radius:10px;justify-content:flex-start;gap:12px;cursor:pointer;<?= ($stNorm === 'bloquée' || $stNorm === 'bloquee') ? 'background:#7f1d1d;color:#ffffff;border:2.5px solid #f87171;box-shadow:0 0 20px rgba(239,68,68,0.5)' : 'background:#131c2e;color:#cbd5e1;border:1.5px solid rgba(239,68,68,0.3)' ?>">
                <span style="font-size:22px">🛑</span>
                <div style="text-align:left">
                    <div style="font-weight:800;font-size:14.5px">Bloquée</div>
                    <div style="font-size:12px;opacity:0.85"><?= ($stNorm === 'bloquée' || $stNorm === 'bloquee') ? '● Tâche bloquée' : 'Signaler un problème' ?></div>
                </div>
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:start">
    <!-- Main Content -->
    <div style="display:flex;flex-direction:column;gap:20px">
        <!-- Description & Détails du travail -->
        <div class="card" style="border:1.5px solid rgba(255,255,255,0.2);box-shadow:0 8px 30px rgba(0,0,0,0.4)">
            <div class="card-header" style="background:rgba(255,255,255,0.03);padding:18px 24px;border-bottom:1.5px solid rgba(255,255,255,0.12);display:flex;justify-content:space-between;align-items:center">
                <div style="display:flex;align-items:center;gap:10px">
                    <span style="font-size:22px">📋</span>
                    <div>
                        <h3 class="card-title" style="font-size:17px;font-weight:700;color:#ffffff;margin:0">
                            Détails du travail effectué
                        </h3>
                        <span style="font-size:12.5px;color:#cbd5e1">Spécifications, modules développés et déroulement</span>
                    </div>
                </div>
                <?php if ($canManage): ?>
                <a href="/gtm-tracker/tasks/edit.php?id=<?= $task['id'] ?>" class="btn btn-secondary btn-sm" style="font-size:12.5px;padding:5px 12px">
                    ✏️ Modifier les détails
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body" style="padding:24px">
                <?php if (!empty($task['description'])): ?>
                    <div style="background:#090d16;border:1.5px solid rgba(255,255,255,0.18);border-radius:12px;padding:22px 24px;font-size:16px;line-height:1.8;color:#ffffff;white-space:pre-wrap;word-break:break-word;font-weight:450;box-shadow:inset 0 2px 6px rgba(0,0,0,0.4)">
<?= htmlspecialchars($task['description'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php else: ?>
                    <div style="background:#090d16;border:1.5px dashed rgba(255,255,255,0.2);border-radius:12px;padding:24px;text-align:center">
                        <p style="color:#cbd5e1;font-size:15px;margin:0 0 12px;font-style:italic">Aucune description détaillée n'a été saisie pour cette tâche.</p>
                        <?php if ($canManage): ?>
                        <a href="/gtm-tracker/tasks/edit.php?id=<?= $task['id'] ?>" class="btn btn-primary btn-sm">
                            + Ajouter les détails du travail
                        </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($task['link_url'])): ?>
                    <div style="margin-top:20px;padding:16px 20px;background:#0c1e33;border:1.5px solid #0284c7;border-radius:12px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
                        <div style="display:flex;align-items:center;gap:12px">
                            <span style="font-size:24px">🔗</span>
                            <div>
                                <div style="font-size:12px;color:#7dd3fc;text-transform:uppercase;font-weight:800;letter-spacing:0.05em">
                                    Lien externe / Référence du livrable
                                </div>
                                <a href="<?= htmlspecialchars($task['link_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" style="font-weight:700;color:#ffffff;font-size:15px;word-break:break-all;text-decoration:underline">
                                    <?= htmlspecialchars($task['link_url'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </div>
                        </div>
                        <a href="<?= htmlspecialchars($task['link_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="btn btn-primary" style="white-space:nowrap;padding:8px 18px;font-size:13.5px;font-weight:700">
                            Ouvrir le lien ↗
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Result / Deliverable -->
        <div class="card" style="border:1.5px solid rgba(16,185,129,0.35);box-shadow:0 8px 30px rgba(0,0,0,0.4)">
            <div class="card-header" style="border-bottom:1.5px solid rgba(16,185,129,0.3);background:rgba(16,185,129,0.08);padding:18px 24px">
                <h3 class="card-title" style="color:#34d399;display:flex;align-items:center;gap:10px;font-size:17px;font-weight:700">
                    🎯 Livrable &amp; Résultat obtenu
                </h3>
            </div>
            <div class="card-body" style="padding:24px">
                <?php if (!empty($task['result'])): ?>
                    <div style="background:#042316;border:1.5px solid #10b981;border-radius:12px;padding:22px 24px;font-size:16px;line-height:1.8;color:#ffffff;white-space:pre-wrap;word-break:break-word;font-weight:450">
<?= htmlspecialchars($task['result'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php else: ?>
                    <div style="background:#090d16;border:1.5px dashed rgba(16,185,129,0.3);border-radius:12px;padding:24px;text-align:center">
                        <p style="color:#cbd5e1;font-size:15px;margin:0 0 12px;font-style:italic">Aucun livrable formalisé pour le moment.</p>
                        <?php if ($canManage && !$isDone): ?>
                        <a href="/gtm-tracker/tasks/finish.php?id=<?= $task['id'] ?>" class="btn btn-success btn-sm" style="background:#10b981;color:#fff">
                            🏁 Marquer comme terminée &amp; Saisir le livrable
                        </a>
                        <?php endif; ?>
                    </div>
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
                <div style="display:flex;flex-direction:column;gap:16px">
                    <div>
                        <div style="font-size:12.5px;color:#cbd5e1;text-transform:uppercase;font-weight:700">Responsable</div>
                        <div style="display:flex;align-items:center;gap:8px;margin-top:5px">
                            <div class="user-avatar" style="width:30px;height:30px;font-size:12px;font-weight:700">
                                <?= strtoupper(substr($task['user_name'], 0, 1)) ?>
                            </div>
                            <span style="font-weight:700;font-size:14px;color:#ffffff"><?= htmlspecialchars($task['user_name'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>

                    <?php if (!empty($task['assigned_by_name'])): ?>
                    <div>
                        <div style="font-size:12.5px;color:#cbd5e1;text-transform:uppercase;font-weight:700">Donnée / Assignée par</div>
                        <div style="font-weight:700;font-size:14px;color:#a5b4fc;margin-top:3px">
                            👤 <?= htmlspecialchars($task['assigned_by_name'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div>
                        <div style="font-size:12.5px;color:#cbd5e1;text-transform:uppercase;font-weight:700">Catégorie</div>
                        <div style="margin-top:5px">
                            <span class="badge <?= $catBadge ?>">
                                <?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                    </div>

                    <div>
                        <div style="font-size:12.5px;color:#cbd5e1;text-transform:uppercase;font-weight:700">Date d'attribution</div>
                        <div style="font-weight:700;font-size:14px;color:#ffffff;margin-top:4px">
                            📅 <?= date('d/m/Y', strtotime($task['task_date'])) ?>
                        </div>
                    </div>

                    <?php if (!empty($task['due_date'])): ?>
                    <div>
                        <div style="font-size:12.5px;color:#cbd5e1;text-transform:uppercase;font-weight:700">Échéance / Date limite</div>
                        <div style="font-weight:700;font-size:14px;color:#fbbf24;margin-top:4px">
                            ⏳ <?= date('d/m/Y', strtotime($task['due_date'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div style="background:#0b1120;padding:14px;border-radius:var(--radius-sm);border:1px solid rgba(255,255,255,0.15)">
                        <div style="font-size:12px;color:#cbd5e1;text-transform:uppercase;font-weight:800;margin-bottom:10px">⏱️ Suivi Début / Fin</div>
                        
                        <div style="margin-bottom:8px">
                            <div style="font-size:12.5px;color:#94a3b8">Commencée le :</div>
                            <div style="font-weight:700;font-size:13.5px;color:<?= !empty($task['started_at']) ? '#38bdf8' : '#94a3b8' ?>;margin-top:2px">
                                <?= !empty($task['started_at']) ? '▶️ ' . date('d/m/Y à H:i', strtotime($task['started_at'])) : '⏳ Pas encore commencée' ?>
                            </div>
                        </div>

                        <div style="margin-bottom:8px">
                            <div style="font-size:12.5px;color:#94a3b8">Terminée le :</div>
                            <div style="font-weight:700;font-size:13.5px;color:<?= !empty($task['completed_at']) ? '#34d399' : '#94a3b8' ?>;margin-top:2px">
                                <?= !empty($task['completed_at']) ? '🏁 ' . date('d/m/Y à H:i', strtotime($task['completed_at'])) : '— En attente de fin' ?>
                            </div>
                        </div>

                        <div>
                            <div style="font-size:12.5px;color:#94a3b8">Temps effectif total :</div>
                            <div style="font-size:15px;font-weight:800;color:#ffffff;margin-top:2px">
                                <?= ((float)$task['hours_spent'] > 0) ? number_format((float)$task['hours_spent'], 1, ',', ' ') . ' h' : '—' ?>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div style="font-size:12.5px;color:#cbd5e1;text-transform:uppercase;font-weight:700">Statut actuel</div>
                        <div style="margin-top:5px">
                            <span class="badge <?= statusBadge($task['status']) ?>">
                                <?= htmlspecialchars($task['status'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                    </div>

                    <div>
                        <div style="font-size:12.5px;color:#cbd5e1;text-transform:uppercase;font-weight:700">Niveau de priorité</div>
                        <div style="margin-top:5px">
                            <span class="badge <?= priorityBadge($task['priority']) ?>">
                                <?= htmlspecialchars($task['priority'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                    </div>

                    <div style="border-top:1px solid rgba(255,255,255,0.12);padding-top:12px;font-size:12.5px;color:#94a3b8">
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
