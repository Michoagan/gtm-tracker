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
    SELECT t.*, u.name as user_name, u.email as user_email, 
           assigner.name as assigned_by_name,
           validator.name as validated_by_name
    FROM tasks t
    JOIN users u ON u.id = t.user_id
    LEFT JOIN users assigner ON assigner.id = t.assigned_by
    LEFT JOIN users validator ON validator.id = t.validated_by
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
$isValidated = ($stNorm === 'validée' || $stNorm === 'validee' || !empty($task['validated_at']));
$isDone = in_array($stNorm, ['terminée', 'terminee', 'termine', 'validée', 'validee']);
$isFinishedPending = in_array($stNorm, ['terminée', 'terminee', 'termine']) && !$isValidated;
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
            Attribuée à <strong><?= htmlspecialchars($task['user_name'], ENT_QUOTES, 'UTF-8') ?></strong>
            le <?= date('d/m/Y', strtotime($task['task_date'])) ?>
            <?php if (!empty($task['assigned_by_name'])): ?>
                • Donnée par <strong><?= htmlspecialchars($task['assigned_by_name'], ENT_QUOTES, 'UTF-8') ?> (Admin)</strong>
            <?php else: ?>
                • Donnée par l'Administrateur
            <?php endif; ?>
        </p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <a href="/gtm-tracker/tasks/index.php" class="btn btn-secondary">
            ← Liste des tâches
        </a>
        <?php if ($canManage): ?>
            <?php if ($isAdm && $isFinishedPending): ?>
                <form method="POST" action="/gtm-tracker/tasks/validate.php" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                    <input type="hidden" name="action" value="validate">
                    <input type="hidden" name="redirect" value="/gtm-tracker/tasks/view.php?id=<?= $task['id'] ?>">
                    <button type="submit" class="btn" style="background:#059669;color:#ffffff;font-weight:800;border:none;padding:9px 20px;border-radius:8px;box-shadow:0 4px 12px rgba(5,150,105,0.3);cursor:pointer">
                        ✅ Valider la tâche (Admin)
                    </button>
                </form>
            <?php elseif (in_array(mb_strtolower($task['status'], 'UTF-8'), ['a faire', 'a faire'])): ?>
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
            <?php endif; ?>

            <a href="/gtm-tracker/tasks/edit.php?id=<?= $task['id'] ?>" class="btn btn-secondary">
                ✏️ Modifier
            </a>
            <?php if ($isAdm): ?>
            <form method="POST" action="/gtm-tracker/tasks/delete.php" style="display:inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette tâche ?');">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="id" value="<?= $task['id'] ?>">
                <button type="submit" class="btn btn-danger">
                    🗑️ Supprimer
                </button>
            </form>
            <?php endif; ?>
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
<div class="card" style="margin-bottom:24px;border:2px solid #6366f1;background:#ffffff;border-radius:16px;padding:22px 24px;box-shadow:0 6px 24px rgba(99,102,241,0.12)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px">
        <div style="display:flex;align-items:center;gap:12px">
            <span style="font-size:28px">⚡</span>
            <div>
                <div style="font-size:12px;color:#4338ca;font-weight:800;text-transform:uppercase;letter-spacing:0.08em">
                    GESTION DU STATUT — CLIQUEZ SUR UN STATUT POUR LE CHANGER
                </div>
                <div style="font-size:18px;font-weight:800;color:#000000;display:flex;align-items:center;gap:10px;margin-top:2px">
                    Statut actuel : <span class="badge <?= statusBadge($task['status']) ?>" style="font-size:14px;padding:5px 14px;font-weight:700"><?= htmlspecialchars($task['status'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
        </div>
        <div style="font-size:13.5px;color:#0f172a;font-weight:600">
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
            <button type="submit" class="btn btn-full" style="padding:14px 16px;font-size:14px;border-radius:10px;justify-content:flex-start;gap:12px;cursor:pointer;<?= ($stNorm === 'a faire' || $stNorm === 'à faire') ? 'background:#f1f5f9;color:#000000;border:2.5px solid #334155;box-shadow:0 4px 14px rgba(15,23,42,0.15)' : 'background:#f8fafc;color:#000000;border:1.5px solid #cbd5e1' ?>">
                <span style="font-size:22px">⏳</span>
                <div style="text-align:left">
                    <div style="font-weight:800;font-size:14.5px;color:#000000">1. À faire</div>
                    <div style="font-size:12px;color:#334155;font-weight:600"><?= ($stNorm === 'a faire' || $stNorm === 'à faire') ? '● Actuellement sélectionné' : 'Attente démarrage' ?></div>
                </div>
            </button>
        </form>

        <!-- 2. EN COURS (DÉMARRER) -->
        <form method="POST" action="/gtm-tracker/tasks/start.php" style="margin:0">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
            <input type="hidden" name="redirect" value="/gtm-tracker/tasks/view.php?id=<?= $task['id'] ?>">
            <button type="submit" class="btn btn-full" style="padding:14px 16px;font-size:14px;border-radius:10px;justify-content:flex-start;gap:12px;cursor:pointer;<?= ($stNorm === 'en cours') ? 'background:#4f46e5;color:#ffffff;border:2.5px solid #3730a3;box-shadow:0 6px 18px rgba(79,70,229,0.35)' : 'background:#f8fafc;color:#000000;border:1.5px solid #cbd5e1' ?>">
                <span style="font-size:22px">⚡</span>
                <div style="text-align:left">
                    <div style="font-weight:800;font-size:14.5px;color:<?= ($stNorm === 'en cours') ? '#ffffff' : '#000000' ?>">2. En cours</div>
                    <div style="font-size:12px;font-weight:600;color:<?= ($stNorm === 'en cours') ? '#e0e7ff' : '#334155' ?>"><?= ($stNorm === 'en cours') ? '● Travail en cours' : '▶️ Commencer le travail' ?></div>
                </div>
            </button>
        </form>

        <!-- 3. TERMINÉE (PAR COLLABORATEUR) -->
        <a href="/gtm-tracker/tasks/finish.php?id=<?= $task['id'] ?>" class="btn btn-full" style="padding:14px 16px;font-size:14px;border-radius:10px;justify-content:flex-start;gap:12px;text-decoration:none;cursor:pointer;<?= ($isFinishedPending) ? 'background:#d97706;color:#ffffff;border:2.5px solid #b45309;box-shadow:0 6px 18px rgba(217,119,6,0.35)' : 'background:#f8fafc;color:#000000;border:1.5px solid #cbd5e1' ?>">
            <span style="font-size:22px">🏁</span>
            <div style="text-align:left">
                <div style="font-weight:800;font-size:14.5px;color:<?= ($isFinishedPending) ? '#ffffff' : '#000000' ?>">3. Terminée</div>
                <div style="font-size:12px;font-weight:600;color:<?= ($isFinishedPending) ? '#fef3c7' : '#334155' ?>"><?= ($isFinishedPending) ? '● En attente validation' : '🏁 Marquer fin de mission' ?></div>
            </div>
        </a>

        <!-- 4. VALIDÉE (VALIDATION EXCLUSIVE ADMIN) -->
        <?php if ($isAdm): ?>
        <form method="POST" action="/gtm-tracker/tasks/validate.php" style="margin:0">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
            <input type="hidden" name="action" value="validate">
            <input type="hidden" name="redirect" value="/gtm-tracker/tasks/view.php?id=<?= $task['id'] ?>">
            <button type="submit" class="btn btn-full" style="padding:14px 16px;font-size:14px;border-radius:10px;justify-content:flex-start;gap:12px;cursor:pointer;<?= ($isValidated) ? 'background:#059669;color:#ffffff;border:2.5px solid #065f46;box-shadow:0 6px 18px rgba(5,150,105,0.35)' : 'background:#f8fafc;color:#000000;border:1.5px solid #cbd5e1' ?>" title="Valider définitivement la tâche en tant qu'administrateur">
                <span style="font-size:22px">✅</span>
                <div style="text-align:left">
                    <div style="font-weight:800;font-size:14.5px;color:<?= ($isValidated) ? '#ffffff' : '#000000' ?>">4. Validée</div>
                    <div style="font-size:12px;font-weight:600;color:<?= ($isValidated) ? '#d1fae5' : '#047857' ?>"><?= ($isValidated) ? '● Validée par Admin' : '👑 Valider (Admin)' ?></div>
                </div>
            </button>
        </form>
        <?php else: ?>
        <div class="btn btn-full" style="padding:14px 16px;font-size:14px;border-radius:10px;justify-content:flex-start;gap:12px;<?= ($isValidated) ? 'background:#059669;color:#ffffff;border:2.5px solid #065f46' : 'background:#f8fafc;color:#64748b;border:1.5px dashed #cbd5e1;cursor:default' ?>">
            <span style="font-size:22px">✅</span>
            <div style="text-align:left">
                <div style="font-weight:800;font-size:14.5px;color:<?= ($isValidated) ? '#ffffff' : '#64748b' ?>">4. Validée</div>
                <div style="font-size:12px;font-weight:600;color:<?= ($isValidated) ? '#d1fae5' : '#64748b' ?>"><?= ($isValidated) ? '● Validée par l\'Admin' : '👑 Validation Admin requise' ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- CARTE SPÉCIALE : ÉTAT DE VALIDATION PAR L'ADMIN -->
<?php if ($isFinishedPending): ?>
    <?php if ($isAdm): ?>
    <!-- AVIS DE VALIDATION ADMIN -->
    <div class="card" style="margin-bottom:24px;border:2.5px solid #10b981;background:#ecfdf5;border-radius:16px;padding:22px 24px;box-shadow:0 8px 30px rgba(16,185,129,0.18)">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
            <div style="display:flex;gap:14px;align-items:center">
                <span style="font-size:36px;line-height:1">👑</span>
                <div>
                    <div style="font-size:12px;font-weight:800;color:#047857;text-transform:uppercase;letter-spacing:0.08em">
                        Action requise de l'administrateur
                    </div>
                    <h3 style="font-size:18px;font-weight:900;color:#064e3b;margin:3px 0 4px">
                        Tâche terminée par le collaborateur — Validation requise
                    </h3>
                    <p style="color:#065f46;font-size:13.5px;margin:0;font-weight:600">
                        Le collaborateur a fini son travail. Vérifiez le temps passé et les livrables ci-dessous, puis validez la tâche.
                    </p>
                </div>
            </div>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <form method="POST" action="/gtm-tracker/tasks/validate.php" style="margin:0">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                    <input type="hidden" name="action" value="validate">
                    <input type="hidden" name="redirect" value="/gtm-tracker/tasks/view.php?id=<?= $task['id'] ?>">
                    <button type="submit" class="btn" style="background:#059669;color:#ffffff;font-size:15px;font-weight:800;padding:12px 24px;border-radius:10px;border:none;box-shadow:0 4px 14px rgba(5,150,105,0.4);cursor:pointer;display:inline-flex;align-items:center;gap:8px">
                        <span style="font-size:18px">✅</span> Valider la tâche (Approuver)
                    </button>
                </form>
                <form method="POST" action="/gtm-tracker/tasks/validate.php" style="margin:0" onsubmit="return confirm('Renvoyer cette tâche en cours au collaborateur pour révision ?');">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                    <input type="hidden" name="action" value="request_changes">
                    <input type="hidden" name="redirect" value="/gtm-tracker/tasks/view.php?id=<?= $task['id'] ?>">
                    <button type="submit" class="btn" style="background:#ffffff;color:#b45309;border:2px solid #f59e0b;font-size:14px;font-weight:700;padding:11px 18px;border-radius:10px;cursor:pointer">
                        🔄 Demander des corrections
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- AVIS COLLABORATEUR EN ATTENTE DE VALIDATION -->
    <div class="card" style="margin-bottom:24px;border:2px solid #f59e0b;background:#fffbeb;border-radius:16px;padding:20px 24px">
        <div style="display:flex;align-items:center;gap:14px">
            <span style="font-size:32px">⏳</span>
            <div>
                <div style="font-size:12px;font-weight:800;color:#b45309;text-transform:uppercase;letter-spacing:0.08em">
                    Statut : Travail soumis
                </div>
                <h4 style="font-size:17px;font-weight:800;color:#92400e;margin:2px 0 4px">
                    Tâche terminée — En attente de validation par l'Administrateur
                </h4>
                <p style="color:#78350f;font-size:13.5px;margin:0;font-weight:500">
                    Vos livrables et heures ont été enregistrés avec succès. L'administrateur va vérifier votre mission et la valider.
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php elseif ($isValidated): ?>
    <!-- TÂCHE DÉJÀ VALIDÉE PAR L'ADMIN -->
    <div class="card" style="margin-bottom:24px;border:2px solid #059669;background:#f0fdf4;border-radius:16px;padding:22px 24px;box-shadow:0 4px 20px rgba(5,150,105,0.12)">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
            <div style="display:flex;align-items:center;gap:14px">
                <span style="font-size:34px">🏆</span>
                <div>
                    <div style="font-size:12px;font-weight:800;color:#047857;text-transform:uppercase;letter-spacing:0.08em">
                        Validation officielle
                    </div>
                    <h4 style="font-size:18px;font-weight:900;color:#064e3b;margin:2px 0 4px">
                        ✅ Tâche validée et approuvée par l'Administrateur
                    </h4>
                    <p style="color:#065f46;font-size:13.5px;margin:0;font-weight:600">
                        Validée par <strong><?= htmlspecialchars($task['validated_by_name'] ?? 'Administrateur', ENT_QUOTES, 'UTF-8') ?></strong>
                        <?= !empty($task['validated_at']) ? 'le ' . date('d/m/Y à H:i', strtotime($task['validated_at'])) : '' ?>.
                    </p>
                </div>
            </div>
            <?php if ($isAdm): ?>
            <form method="POST" action="/gtm-tracker/tasks/toggle-status.php" style="margin:0" onsubmit="return confirm('Rouvrir cette tâche et la remettre en cours ?');">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                <input type="hidden" name="new_status" value="En cours">
                <input type="hidden" name="redirect" value="/gtm-tracker/tasks/view.php?id=<?= $task['id'] ?>">
                <button type="submit" class="btn btn-secondary btn-sm" style="font-weight:700">
                    ↩️ Rouvrir la tâche
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:start">
    <!-- Main Content -->
    <div style="display:flex;flex-direction:column;gap:20px">
        <!-- Description & Détails du travail -->
        <div class="card" style="border:1.5px solid #cbd5e1;box-shadow:var(--w-shadow-sm)">
            <div class="card-header" style="background:#f8fafc;padding:18px 24px;border-bottom:1.5px solid #cbd5e1;display:flex;justify-content:space-between;align-items:center">
                <div style="display:flex;align-items:center;gap:10px">
                    <span style="font-size:24px">📋</span>
                    <div>
                        <h3 class="card-title" style="font-size:17px;font-weight:800;color:#000000;margin:0">
                            Détails du travail effectué
                        </h3>
                        <span style="font-size:13px;color:#1e293b;font-weight:500">Spécifications, modules développés et déroulement</span>
                    </div>
                </div>
                <?php if ($canManage): ?>
                <a href="/gtm-tracker/tasks/edit.php?id=<?= $task['id'] ?>" class="btn btn-secondary btn-sm" style="font-size:12.5px;padding:6px 14px;font-weight:700">
                    ✏️ Modifier les détails
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body" style="padding:24px">
                <?php if (!empty($task['description'])): ?>
                    <div style="background:#f8fafc;border:1.5px solid #cbd5e1;border-radius:12px;padding:22px 24px;font-size:16px;line-height:1.8;color:#000000;white-space:pre-wrap;word-break:break-word;font-weight:500">
<?= htmlspecialchars($task['description'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php else: ?>
                    <div style="background:#f8fafc;border:1.5px dashed #cbd5e1;border-radius:12px;padding:24px;text-align:center">
                        <p style="color:#1e293b;font-size:15px;margin:0 0 12px;font-style:italic;font-weight:500">Aucune description détaillée n'a été saisie pour cette tâche.</p>
                        <?php if ($canManage): ?>
                        <a href="/gtm-tracker/tasks/edit.php?id=<?= $task['id'] ?>" class="btn btn-primary btn-sm">
                            + Ajouter les détails du travail
                        </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($task['link_url'])): ?>
                    <div style="margin-top:20px;padding:16px 20px;background:#eff6ff;border:1.5px solid #93c5fd;border-radius:12px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
                        <div style="display:flex;align-items:center;gap:12px">
                            <span style="font-size:24px">🔗</span>
                            <div>
                                <div style="font-size:12px;color:#1e40af;text-transform:uppercase;font-weight:800;letter-spacing:0.05em">
                                    Lien externe / Référence du livrable
                                </div>
                                <a href="<?= htmlspecialchars($task['link_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" style="font-weight:700;color:#1d4ed8;font-size:15px;word-break:break-all;text-decoration:underline">
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
        <div class="card" style="border:1.5px solid #a7f3d0;box-shadow:var(--w-shadow-sm)">
            <div class="card-header" style="border-bottom:1.5px solid #a7f3d0;background:#ecfdf5;padding:18px 24px">
                <h3 class="card-title" style="color:#065f46;display:flex;align-items:center;gap:10px;font-size:17px;font-weight:800">
                    🎯 Livrable &amp; Résultat obtenu
                </h3>
            </div>
            <div class="card-body" style="padding:24px">
                <?php if (!empty($task['result'])): ?>
                    <div style="background:#ecfdf5;border:1.5px solid #6ee7b7;border-radius:12px;padding:22px 24px;font-size:16px;line-height:1.8;color:#064e3b;white-space:pre-wrap;word-break:break-word;font-weight:600">
<?= htmlspecialchars($task['result'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php else: ?>
                    <div style="background:#f8fafc;border:1.5px dashed #a7f3d0;border-radius:12px;padding:24px;text-align:center">
                        <p style="color:#1e293b;font-size:15px;margin:0 0 12px;font-style:italic;font-weight:500">Aucun livrable formalisé pour le moment.</p>
                        <?php if ($canManage && !$isDone): ?>
                        <a href="/gtm-tracker/tasks/finish.php?id=<?= $task['id'] ?>" class="btn btn-success btn-sm" style="background:#10b981;color:#fff;font-weight:700">
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
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#f8fafc;border-radius:10px;border:1.5px solid #cbd5e1">
                            <div style="display:flex;align-items:center;gap:12px">
                                <span style="font-size:22px"><?= $isImg ? '🖼️' : '📄' ?></span>
                                <div>
                                    <div style="font-weight:700;color:#000000;font-size:14px">
                                        <?= htmlspecialchars($att['original_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div style="font-size:12px;color:#1e293b;font-weight:500">
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
        <div class="card" style="border:1.5px solid #cbd5e1;box-shadow:var(--w-shadow-sm)">
            <div class="card-header" style="background:#f8fafc;border-bottom:1.5px solid #cbd5e1;padding:16px 20px">
                <h4 class="card-title" style="font-size:15px;font-weight:800;color:#000000;margin:0">Informations clés</h4>
            </div>
            <div class="card-body" style="padding:18px 20px">
                <div style="display:flex;flex-direction:column;gap:16px">
                    <div>
                        <div style="font-size:12px;color:#000000;text-transform:uppercase;font-weight:800;letter-spacing:0.04em">Responsable</div>
                        <div style="display:flex;align-items:center;gap:8px;margin-top:5px">
                            <div class="user-avatar" style="width:30px;height:30px;font-size:12px;font-weight:700">
                                <?= strtoupper(substr($task['user_name'], 0, 1)) ?>
                            </div>
                            <span style="font-weight:800;font-size:14.5px;color:#000000"><?= htmlspecialchars($task['user_name'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>

                    <?php if (!empty($task['assigned_by_name'])): ?>
                    <div>
                        <div style="font-size:12px;color:#000000;text-transform:uppercase;font-weight:800;letter-spacing:0.04em">Donnée / Assignée par</div>
                        <div style="font-weight:700;font-size:14px;color:#4338ca;margin-top:3px">
                            👤 <?= htmlspecialchars($task['assigned_by_name'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div>
                        <div style="font-size:12px;color:#000000;text-transform:uppercase;font-weight:800;letter-spacing:0.04em">Catégorie</div>
                        <div style="margin-top:5px">
                            <span class="badge <?= $catBadge ?>">
                                <?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                    </div>

                    <div>
                        <div style="font-size:12px;color:#000000;text-transform:uppercase;font-weight:800;letter-spacing:0.04em">Date d'attribution</div>
                        <div style="font-weight:700;font-size:14px;color:#000000;margin-top:4px">
                            📅 <?= date('d/m/Y', strtotime($task['task_date'])) ?>
                        </div>
                    </div>

                    <?php if (!empty($task['due_date'])): ?>
                    <div>
                        <div style="font-size:12px;color:#000000;text-transform:uppercase;font-weight:800;letter-spacing:0.04em">Échéance / Date limite</div>
                        <div style="font-weight:700;font-size:14px;color:#b45309;margin-top:4px">
                            ⏳ <?= date('d/m/Y', strtotime($task['due_date'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div style="background:#f8fafc;padding:14px 16px;border-radius:10px;border:1.5px solid #cbd5e1">
                        <div style="font-size:12px;color:#000000;text-transform:uppercase;font-weight:800;letter-spacing:0.04em;margin-bottom:10px">⏱️ Suivi Début / Fin</div>
                        
                        <div style="margin-bottom:8px">
                            <div style="font-size:12px;color:#1e293b;font-weight:600">Commencée le :</div>
                            <div style="font-weight:700;font-size:13.5px;color:<?= !empty($task['started_at']) ? '#0369a1' : '#64748b' ?>;margin-top:2px">
                                <?= !empty($task['started_at']) ? '▶️ ' . date('d/m/Y à H:i', strtotime($task['started_at'])) : '⏳ Pas encore commencée' ?>
                            </div>
                        </div>

                        <div style="margin-bottom:8px">
                            <div style="font-size:12px;color:#1e293b;font-weight:600">Terminée le :</div>
                            <div style="font-weight:700;font-size:13.5px;color:<?= !empty($task['completed_at']) ? '#047857' : '#64748b' ?>;margin-top:2px">
                                <?= !empty($task['completed_at']) ? '🏁 ' . date('d/m/Y à H:i', strtotime($task['completed_at'])) : '— En attente de fin' ?>
                            </div>
                        </div>

                        <div>
                            <div style="font-size:12px;color:#1e293b;font-weight:600">Temps effectif total :</div>
                            <div style="font-size:16px;font-weight:800;color:#000000;margin-top:2px">
                                <?= ((float)$task['hours_spent'] > 0) ? number_format((float)$task['hours_spent'], 1, ',', ' ') . ' h' : '—' ?>
                            </div>
                        </div>

                        <?php if ($isValidated): ?>
                        <div style="margin-top:10px;padding-top:10px;border-top:1.5px dashed #cbd5e1">
                            <div style="font-size:12px;color:#047857;font-weight:800">✅ VALIDÉE PAR L'ADMIN :</div>
                            <div style="font-weight:800;font-size:13.5px;color:#047857;margin-top:2px">
                                <?= !empty($task['validated_at']) ? date('d/m/Y à H:i', strtotime($task['validated_at'])) : 'Validée' ?>
                            </div>
                            <?php if (!empty($task['validated_by_name'])): ?>
                            <div style="font-size:12px;color:#1e293b;font-weight:600;margin-top:2px">
                                Par <?= htmlspecialchars($task['validated_by_name'], ENT_QUOTES, 'UTF-8') ?> (Admin)
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php elseif ($isDone): ?>
                        <div style="margin-top:10px;padding-top:10px;border-top:1.5px dashed #cbd5e1">
                            <div style="font-size:12px;color:#b45309;font-weight:800">VALIDATION ADMIN :</div>
                            <div style="font-weight:700;font-size:13px;color:#b45309;margin-top:2px">
                                ⏳ En attente de validation
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <div style="font-size:12px;color:#000000;text-transform:uppercase;font-weight:800;letter-spacing:0.04em">Statut actuel</div>
                        <div style="margin-top:5px">
                            <span class="badge <?= statusBadge($task['status']) ?>">
                                <?= htmlspecialchars($task['status'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                    </div>

                    <div>
                        <div style="font-size:12px;color:#000000;text-transform:uppercase;font-weight:800;letter-spacing:0.04em">Niveau de priorité</div>
                        <div style="margin-top:5px">
                            <span class="badge <?= priorityBadge($task['priority']) ?>">
                                <?= htmlspecialchars($task['priority'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                    </div>

                    <div style="border-top:1.5px solid #cbd5e1;padding-top:12px;font-size:12.5px;color:#1e293b;font-weight:600">
                        Créée le <?= date('d/m/Y à H:i', strtotime($task['created_at'])) ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($isAdm): ?>
        <a href="/gtm-tracker/tasks/create.php" class="btn btn-primary" style="width:100%;text-align:center;font-weight:700">
            + Donner une autre tâche
        </a>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
