<?php
$pageTitle = 'Mes activites';
require_once __DIR__ . '/includes/header.php';
$db = getDB();
$uid = $_SESSION['user_id'];

// Personal metrics
$myTotalActions = $db->query("SELECT COUNT(*) FROM actions WHERE user_id = $uid")->fetchColumn();
$myDoneActions  = $db->query("SELECT COUNT(*) FROM actions WHERE user_id = $uid AND status = 'Terminee'")->fetchColumn();
$myPendingActions = $db->query("SELECT COUNT(*) FROM actions WHERE user_id = $uid AND status NOT IN ('Terminee', 'Bloquee')")->fetchColumn();
$myTotalConvs   = $db->query("SELECT COUNT(*) FROM conversations WHERE user_id = $uid")->fetchColumn();
$myProspects    = $db->query("SELECT COUNT(*) FROM prospects WHERE assigned_to = $uid")->fetchColumn();

// My recent actions
$stmt = $db->prepare("
    SELECT a.*, s.name as strategy_name, ch.name as channel_name 
    FROM actions a 
    LEFT JOIN strategies s ON s.id = a.strategy_id 
    LEFT JOIN channels ch ON ch.id = a.channel_id 
    WHERE a.user_id = ? 
    ORDER BY a.action_date DESC, a.id DESC 
    LIMIT 10
");
$stmt->execute([$uid]);
$myActions = $stmt->fetchAll();

// My conversations with next action
$stmtConv = $db->prepare("
    SELECT c.*, p.name as prospect_name, p.company, ch.name as channel_name 
    FROM conversations c 
    LEFT JOIN prospects p ON p.id = c.prospect_id 
    LEFT JOIN channels ch ON ch.id = c.channel_id 
    WHERE c.user_id = ? 
    ORDER BY c.conversation_date DESC 
    LIMIT 8
");
$stmtConv->execute([$uid]);
$myConvs = $stmtConv->fetchAll();

// Personal tasks metrics
$myTotalTasks = $db->query("SELECT COUNT(*) FROM tasks WHERE user_id = $uid")->fetchColumn();
$myDoneTasks  = $db->query("SELECT COUNT(*) FROM tasks WHERE user_id = $uid AND status IN ('Terminée','Terminee','termine','terminée')")->fetchColumn();
$myTechTasks  = $db->query("SELECT COUNT(*) FROM tasks WHERE user_id = $uid AND category = 'tech'")->fetchColumn();
$myHoursTasks = $db->query("SELECT COALESCE(SUM(hours_spent), 0) FROM tasks WHERE user_id = $uid")->fetchColumn();

// My recent tasks
$stmtMyTasks = $db->prepare("
    SELECT * FROM tasks 
    WHERE user_id = ? 
    ORDER BY task_date DESC, id DESC 
    LIMIT 6
");
$stmtMyTasks->execute([$uid]);
$myTasks = $stmtMyTasks->fetchAll();
?>

<div class="page-header">
    <div>
        <h2 class="page-header-title">Mes Activités &amp; Tâches</h2>
        <p class="page-header-subtitle">Espace personnel de <?= htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="/gtm-tracker/tasks/create.php" class="btn btn-primary" style="background:#8b5cf6">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            + Enregistrer une tâche
        </a>
        <a href="/gtm-tracker/tasks/index.php?view=mine" class="btn btn-secondary">
            📋 Mes tâches
        </a>
        <?php if (isAdmin()): ?>
        <a href="/gtm-tracker/actions/create.php" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nouvelle action GTM
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card" style="--card-color:#6366f1">
        <div class="stat-label">Mes actions</div>
        <div class="stat-value"><?= $myTotalActions ?></div>
        <div class="stat-sub">Au total</div>
    </div>
    <div class="stat-card" style="--card-color:#22c55e">
        <div class="stat-label">Terminees</div>
        <div class="stat-value"><?= $myDoneActions ?></div>
        <div class="stat-sub">Actions accomplies</div>
    </div>
    <div class="stat-card" style="--card-color:#f59e0b">
        <div class="stat-label">En cours / A faire</div>
        <div class="stat-value"><?= $myPendingActions ?></div>
        <div class="stat-sub">A finaliser</div>
    </div>
    <div class="stat-card" style="--card-color:#3b82f6">
        <div class="stat-label">Mes prospects</div>
        <div class="stat-value"><?= $myProspects ?></div>
        <div class="stat-sub">Qui me sont assignes</div>
    </div>
    <div class="stat-card" style="--card-color:#ec4899">
        <div class="stat-label">Mes conversations</div>
        <div class="stat-value"><?= $myTotalConvs ?></div>
        <div class="stat-sub">Echanges traces</div>
    </div>
</div>

<!-- Mes Tâches & Développements Tech -->
<div class="card" style="margin-top:24px">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <div style="display:flex;align-items:center;gap:10px">
            <span class="card-title" style="margin-bottom:0">💻 Mes Tâches Effectuées &amp; Développements</span>
            <span class="badge badge-purple"><?= (int)$myTotalTasks ?> tâche(s) au total • <?= number_format((float)$myHoursTasks, 1, ',', ' ') ?> h</span>
        </div>
        <div style="display:flex;gap:8px">
            <a href="/gtm-tracker/tasks/create.php" class="btn btn-primary btn-sm">+ Ajouter une tâche</a>
            <a href="/gtm-tracker/tasks/index.php?view=mine" class="btn btn-secondary btn-sm">Voir tout</a>
        </div>
    </div>
    <?php if (empty($myTasks)): ?>
        <div class="empty-state">
            <p>Vous n'avez pas encore consigné de tâches personnelles.</p>
            <a href="/gtm-tracker/tasks/create.php" class="btn btn-primary btn-sm" style="margin-top:10px">+ Enregistrer ma première tâche (Tech, GTM...)</a>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width:100px">Date</th>
                        <th>Catégorie</th>
                        <th>Intitulé de la tâche</th>
                        <th>Temps</th>
                        <th>Statut</th>
                        <th style="text-align:right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($myTasks as $mt): 
                        $catB = taskCategoryBadge($mt['category']);
                        $catL = taskCategoryLabel($mt['category']);
                        $stNorm = mb_strtolower($mt['status'], 'UTF-8');
                    ?>
                    <tr>
                        <td style="color:#e2e8f0;font-size:13.5px;font-weight:500;white-space:nowrap">
                            <?= date('d/m/Y', strtotime($mt['task_date'])) ?>
                            <?php if (!empty($mt['due_date'])): ?>
                                <div style="font-size:12px;color:#fbbf24;font-weight:600">⏳ Échéance : <?= date('d/m', strtotime($mt['due_date'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= $catB ?>"><?= htmlspecialchars($catL, ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td>
                            <a href="/gtm-tracker/tasks/view.php?id=<?= $mt['id'] ?>" style="font-weight:700;font-size:14px;color:#ffffff;text-decoration:none">
                                <?= htmlspecialchars($mt['title'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                            <div style="font-size:12.5px;color:#cbd5e1;margin-top:3px">
                                <?php if (!empty($mt['started_at'])): ?>
                                    <span style="color:#38bdf8;font-weight:600;margin-right:8px">▶️ Début : <?= date('d/m H:i', strtotime($mt['started_at'])) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($mt['completed_at'])): ?>
                                    <span style="color:#34d399;font-weight:600;margin-right:8px">🏁 Fin : <?= date('d/m H:i', strtotime($mt['completed_at'])) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($mt['link_url'])): ?>
                                    <a href="<?= htmlspecialchars($mt['link_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" style="color:#38bdf8;font-weight:600" title="Lien externe">🔗 Lien</a>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="font-weight:700;color:#ffffff;font-size:13.5px"><?= ((float)$mt['hours_spent'] > 0) ? number_format((float)$mt['hours_spent'], 1, ',', ' ') . ' h' : '—' ?></td>
                        <td style="min-width:145px">
                            <?php $mtDone = in_array($stNorm, ['terminée', 'terminee', 'termine']); ?>
                            <form method="POST" action="/gtm-tracker/tasks/toggle-status.php" style="margin:0">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="task_id" value="<?= $mt['id'] ?>">
                                <input type="hidden" name="redirect" value="/gtm-tracker/my-activities.php">
                                <select name="new_status" onchange="this.form.submit()" class="form-control" style="padding:4px 24px 4px 8px;font-size:12px;font-weight:700;border-radius:16px;height:auto;cursor:pointer;background-color:#111827;border:1.5px solid <?= ($mtDone ? '#10b981' : ($stNorm === 'en cours' ? '#6366f1' : ($stNorm === 'bloquée' || $stNorm === 'bloquee' ? '#ef4444' : '#94a3b8'))) ?>;color:#ffffff" title="Changer le statut en 1 clic">
                                    <option value="A faire" <?= ($stNorm === 'a faire' || $stNorm === 'à faire') ? 'selected' : '' ?>>⏳ À faire</option>
                                    <option value="En cours" <?= ($stNorm === 'en cours') ? 'selected' : '' ?>>⚡ En cours</option>
                                    <option value="Terminée" <?= ($mtDone) ? 'selected' : '' ?>>🏁 Terminée</option>
                                    <option value="Bloquée" <?= ($stNorm === 'bloquée' || $stNorm === 'bloquee') ? 'selected' : '' ?>>🛑 Bloquée</option>
                                </select>
                            </form>
                        </td>
                        <td style="text-align:right;white-space:nowrap">
                            <?php if ($stNorm === 'a faire' || $stNorm === 'à faire'): ?>
                                <form method="POST" action="/gtm-tracker/tasks/start.php" style="display:inline;margin:0">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="task_id" value="<?= $mt['id'] ?>">
                                    <input type="hidden" name="redirect" value="/gtm-tracker/my-activities.php">
                                    <button type="submit" class="btn btn-sm" style="background:#6366f1;color:#fff;padding:3px 9px;font-size:11.5px;font-weight:700;border-radius:5px" title="Commencer cette tâche">
                                        ▶️ Démarrer
                                    </button>
                                </form>
                            <?php elseif ($stNorm === 'en cours'): ?>
                                <a href="/gtm-tracker/tasks/finish.php?id=<?= $mt['id'] ?>" class="btn btn-sm" style="background:#10b981;color:#fff;padding:3px 9px;font-size:11.5px;font-weight:700;border-radius:5px;text-decoration:none" title="Terminer cette tâche">
                                    🏁 Terminer
                                </a>
                            <?php endif; ?>
                            <a href="/gtm-tracker/tasks/view.php?id=<?= $mt['id'] ?>" class="btn btn-secondary btn-sm" style="padding:4px 8px" title="Voir les détails">👁️</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="grid-2" style="margin-top:24px">
    <!-- Mes actions recentes -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Mes dernieres actions</span>
            <?php if (isAdmin()): ?><a href="/gtm-tracker/actions/index.php?my=1" class="btn btn-secondary btn-sm">Voir tout</a><?php endif; ?>
        </div>
        <?php if (empty($myActions)): ?>
            <div class="empty-state">
                <p>Aucune action enregistree pour l instant.</p>
                <?php if (isAdmin()): ?><a href="/gtm-tracker/actions/create.php" class="btn btn-primary btn-sm" style="margin-top:10px">Creer une action</a><?php else: ?><p style="font-size:12px;color:var(--text-muted);margin-top:8px">Vos actions vous sont assignées par l'administrateur.</p><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Canal</th>
                            <th>Statut</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myActions as $a): ?>
                        <tr>
                            <td><a href="/gtm-tracker/actions/view.php?id=<?= $a['id'] ?>"><strong><?= htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8') ?></strong></a></td>
                            <td><?= htmlspecialchars($a['channel_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="badge <?= statusBadge($a['status']) ?>"><?= htmlspecialchars($a['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars(date('d/m/Y', strtotime($a['action_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Mes conversations & Next steps -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Mes prochains suivis ("Next Actions")</span>
            <a href="/gtm-tracker/conversations/index.php" class="btn btn-secondary btn-sm">Voir tout</a>
        </div>
        <?php if (empty($myConvs)): ?>
            <div class="empty-state">
                <p>Aucun echange prospect note.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Prospect</th>
                            <th>Prochaine action</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myConvs as $c): ?>
                        <tr>
                            <td>
                                <a href="/gtm-tracker/conversations/view.php?id=<?= $c['id'] ?>">
                                    <strong><?= htmlspecialchars($c['prospect_name'] ?? 'Inconnu', ENT_QUOTES, 'UTF-8') ?></strong>
                                </a>
                                <?php if (!empty($c['company'])): ?>
                                    <div style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($c['company'], ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($c['next_action'])): ?>
                                    <span style="color:#f59e0b;font-weight:500"><?= htmlspecialchars($c['next_action'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted)">-</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= statusBadge($c['status']) ?>"><?= htmlspecialchars($c['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
