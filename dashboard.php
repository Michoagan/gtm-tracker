<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
$db = getDB();
$uid = $_SESSION['user_id'];
$isAdm = isAdmin();

// === Statistiques globales ===
$where = $isAdm ? '' : "WHERE user_id = $uid";
$whereAnd = $isAdm ? 'WHERE' : "WHERE user_id = $uid AND";

// Total actions
$totalActions = $db->query("SELECT COUNT(*) FROM actions $where")->fetchColumn();
// Today
$today = date('Y-m-d');
$todayActions = $db->query("SELECT COUNT(*) FROM actions $whereAnd action_date = '$today'")->fetchColumn();
// This week
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekActions = $db->query("SELECT COUNT(*) FROM actions $whereAnd action_date >= '$weekStart'")->fetchColumn();
// Conversations
$whereConv = $isAdm ? '' : "WHERE user_id = $uid";
$whereConvAnd = $isAdm ? 'WHERE' : "WHERE user_id = $uid AND";
$totalConv = $db->query("SELECT COUNT(*) FROM conversations $whereConv")->fetchColumn();
$totalResponses = $db->query("SELECT COUNT(*) FROM conversations $whereConvAnd response_received IS NOT NULL AND response_received != ''")->fetchColumn();
$totalInterested = $db->query("SELECT COUNT(*) FROM conversations $whereConvAnd status IN ('Interesse','Interesse','Rendez-vous')")->fetchColumn();
$totalConverted = $db->query("SELECT COUNT(*) FROM conversations $whereConvAnd status = 'Converti'")->fetchColumn();

// Activity by user (admin only) - utilise membres statiques
$userActivity = [];
if ($isAdm) {
    foreach (getMembers() as $m) {
        $mid = $m['id'];
        $actCount  = $db->query("SELECT COUNT(DISTINCT id) FROM actions WHERE user_id = $mid")->fetchColumn();
        $convCount = $db->query("SELECT COUNT(DISTINCT id) FROM conversations WHERE user_id = $mid")->fetchColumn();
        $resCount  = $db->query("SELECT COUNT(*) FROM actions WHERE user_id = $mid AND result IS NOT NULL AND result != " . chr(39) . chr(39) . "")->fetchColumn();
        $userActivity[] = ['name' => $m['name'], 'actions' => $actCount, 'convs' => $convCount, 'results' => $resCount];
    }
    usort($userActivity, fn($a,$b) => $b['actions'] - $a['actions']);
}

// By channel
$byChannel = $db->query("
    SELECT ch.name, COUNT(a.id) as actions,
           SUM(CASE WHEN a.result IS NOT NULL AND a.result != '' THEN 1 ELSE 0 END) as results
    FROM channels ch
    LEFT JOIN actions a ON a.channel_id = ch.id " . ($isAdm ? '' : "AND a.user_id = $uid") . "
    GROUP BY ch.id, ch.name HAVING COUNT(a.id) > 0 ORDER BY actions DESC LIMIT 8
")->fetchAll();

// By strategy
$byStrategy = $db->query("
    SELECT s.name, COUNT(a.id) as actions,
           SUM(CASE WHEN a.result IS NOT NULL AND a.result != '' THEN 1 ELSE 0 END) as results,
           SUM(CASE WHEN c.status = 'Converti' THEN 1 ELSE 0 END) as convs
    FROM strategies s
    LEFT JOIN actions a ON a.strategy_id = s.id " . ($isAdm ? '' : "AND a.user_id = $uid") . "
    LEFT JOIN conversations c ON c.strategy_id = s.id " . ($isAdm ? '' : "AND c.user_id = $uid") . "
    GROUP BY s.id, s.name HAVING COUNT(a.id) > 0 ORDER BY actions DESC LIMIT 8
")->fetchAll();

// Recent actions
$recentActions = $db->query("
    SELECT a.*, u.name as user_name, s.name as strategy_name, ch.name as channel_name
    FROM actions a
    JOIN users u ON u.id = a.user_id
    LEFT JOIN strategies s ON s.id = a.strategy_id
    LEFT JOIN channels ch ON ch.id = a.channel_id
    " . ($isAdm ? '' : "WHERE a.user_id = $uid") . "
    ORDER BY a.created_at DESC LIMIT 8
")->fetchAll();

// Tasks metrics
$whereTask = $isAdm ? '' : "WHERE user_id = $uid";
$totalTasks = $db->query("SELECT COUNT(*) FROM tasks $whereTask")->fetchColumn();
$techTasks  = $db->query("SELECT COUNT(*) FROM tasks " . ($isAdm ? "WHERE category = 'tech'" : "WHERE user_id = $uid AND category = 'tech'"))->fetchColumn();
$doneTasks  = $db->query("SELECT COUNT(*) FROM tasks " . ($isAdm ? "WHERE status IN ('Terminée','Terminee','termine','terminée','Validée','Validee')" : "WHERE user_id = $uid AND status IN ('Terminée','Terminee','termine','terminée','Validée','Validee')"))->fetchColumn();
$pendingValidation = $isAdm ? (int)$db->query("SELECT COUNT(*) FROM tasks WHERE status IN ('Terminée','Terminee','termine','terminée') AND validated_at IS NULL")->fetchColumn() : 0;
$totalHours = $db->query("SELECT COALESCE(SUM(hours_spent), 0) FROM tasks $whereTask")->fetchColumn();

// Recent tasks
$recentTasks = $db->query("
    SELECT t.*, u.name as user_name
    FROM tasks t
    JOIN users u ON u.id = t.user_id
    " . ($isAdm ? '' : "WHERE t.user_id = $uid") . "
    ORDER BY t.task_date DESC, t.id DESC LIMIT 6
")->fetchAll();
?>
<div class="animate-in">

<div class="page-header">
    <div>
        <h2 class="page-header-title">Tableau de bord Général</h2>
        <p class="page-header-sub">Pilotage de l'équipe : Tâches &amp; Développements Tech, Actions &amp; Stratégie GTM</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <?php if ($isAdm): ?>
        <a href="/gtm-tracker/tasks/create.php" class="btn btn-primary" style="background:#8b5cf6">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            + Donner une tâche
        </a>
        <?php endif; ?>
        <a href="/gtm-tracker/tasks/index.php" class="btn btn-secondary">
            📋 Toutes les tâches
        </a>
        <?php if ($isAdm): ?>
        <a href="/gtm-tracker/admin/users.php" class="btn btn-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
            Personnel
        </a>
        <a href="/gtm-tracker/actions/create.php" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nouvelle action GTM
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($pendingValidation > 0 && $isAdm): ?>
<div class="card" style="margin-bottom:24px;background:#ecfdf5;border:2px solid #10b981;border-radius:14px;padding:16px 20px;box-shadow:0 4px 16px rgba(16,185,129,0.15)">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:12px">
            <span style="font-size:28px">👑</span>
            <div>
                <strong style="color:#064e3b;font-size:15px"><?= $pendingValidation ?> tâche(s) terminée(s) en attente de votre validation</strong>
                <div style="color:#065f46;font-size:13px;font-weight:600">Des collaborateurs ont finalisé leurs missions et attendent votre validation officielle.</div>
            </div>
        </div>
        <a href="/gtm-tracker/tasks/index.php?status=Termin%C3%A9e" class="btn btn-sm btn-primary" style="background:#059669;font-weight:800;border:none">
            Voir &amp; Valider les tâches →
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Stats grid -->
<div class="stats-grid">
    <div class="stat-card" style="--card-color:#6366f1">
        <div class="stat-label">Actions totales</div>
        <div class="stat-value"><?= $totalActions ?></div>
        <svg class="stat-icon" xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        <div class="stat-sub">Toutes les actions</div>
    </div>
    <div class="stat-card" style="--card-color:#22c55e">
        <div class="stat-label">Aujourd'hui</div>
        <div class="stat-value"><?= $todayActions ?></div>
        <svg class="stat-icon" xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <div class="stat-sub"><?= date('d/m/Y') ?></div>
    </div>
    <div class="stat-card" style="--card-color:#f59e0b">
        <div class="stat-label">Cette semaine</div>
        <div class="stat-value"><?= $weekActions ?></div>
        <svg class="stat-icon" xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <div class="stat-sub">Depuis lundi</div>
    </div>
    <div class="stat-card" style="--card-color:#3b82f6">
        <div class="stat-label">Conversations</div>
        <div class="stat-value"><?= $totalConv ?></div>
        <svg class="stat-icon" xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <div class="stat-sub">Total</div>
    </div>
    <div class="stat-card" style="--card-color:#8b5cf6">
        <div class="stat-label">Reponses</div>
        <div class="stat-value"><?= $totalResponses ?></div>
        <svg class="stat-icon" xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
        <div class="stat-sub">Reponses recues</div>
    </div>
    <div class="stat-card" style="--card-color:#ec4899">
        <div class="stat-label">Conversions</div>
        <div class="stat-value"><?= $totalConverted ?></div>
        <svg class="stat-icon" xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        <div class="stat-sub">Prospects convertis</div>
    </div>
</div>

<!-- Section Tâches & Réalisations (Tech, GTM, etc.) -->
<div style="margin-bottom:24px;background:linear-gradient(135deg, rgba(99,102,241,0.06), rgba(168,85,247,0.06));border:1px solid rgba(168,85,247,0.2);border-radius:var(--radius-lg);padding:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
        <div style="display:flex;align-items:center;gap:10px">
            <span style="font-size:22px">💻</span>
            <div>
                <h3 style="font-size:16px;font-weight:700;color:var(--text-main);margin:0">
                    Tâches Effectuées &amp; Développements Solutions
                </h3>
                <span style="font-size:12px;color:var(--text-muted)">Suivi opérationnel des réalisations techniques et commerciales</span>
            </div>
        </div>
        <div style="display:flex;gap:8px">
            <?php if ($isAdm): ?>
            <a href="/gtm-tracker/tasks/create.php" class="btn btn-primary btn-sm" style="background:#8b5cf6">
                + Donner une tâche
            </a>
            <?php endif; ?>
            <a href="/gtm-tracker/tasks/index.php" class="btn btn-secondary btn-sm">
                Consulter les tâches →
            </a>
        </div>
    </div>

    <div class="stats-grid" style="margin-bottom:0">
        <div class="stat-card" style="--card-color:#8b5cf6">
            <div class="stat-label">Tâches totales</div>
            <div class="stat-value"><?= (int)$totalTasks ?></div>
            <div class="stat-sub"><?= $isAdm ? 'Équipe complète' : 'Mes tâches' ?></div>
        </div>
        <div class="stat-card" style="--card-color:#10b981">
            <div class="stat-label">Tâches terminées</div>
            <div class="stat-value"><?= (int)$doneTasks ?></div>
            <div class="stat-sub">Réalisations accomplies</div>
        </div>
        <div class="stat-card" style="--card-color:#a855f7">
            <div class="stat-label">Développement Tech</div>
            <div class="stat-value"><?= (int)$techTasks ?></div>
            <div class="stat-sub">Code &amp; Architecture</div>
        </div>
        <div class="stat-card" style="--card-color:#f59e0b">
            <div class="stat-label">Heures investies</div>
            <div class="stat-value"><?= number_format((float)$totalHours, 1, ',', ' ') ?> h</div>
            <div class="stat-sub">Temps cumulé</div>
        </div>
    </div>
</div>

<!-- Charts + Tables row -->
<div class="grid-2" style="margin-bottom:24px">
    <!-- By Channel -->
    <?php if (!empty($byChannel)): ?>
    <div class="card">
        <div class="card-header">
            <span class="card-title">Actions par canal</span>
        </div>
        <div style="padding:16px 20px">
            <canvas id="channelChart"></canvas>
        </div>
    </div>
    <?php endif; ?>
    <!-- By Strategy -->
    <?php if (!empty($byStrategy)): ?>
    <div class="card">
        <div class="card-header">
            <span class="card-title">Actions par strategie</span>
        </div>
        <div style="padding:16px 20px">
            <canvas id="strategyChart"></canvas>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Activity by user (admin only) -->
<?php if ($isAdm && !empty($userActivity)): ?>
<div class="card" style="margin-bottom:24px">
    <div class="card-header">
        <span class="card-title">Activite par utilisateur</span>
    </div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Utilisateur</th><th>Actions</th><th>Conversations</th><th>Resultats</th><th>Performance</th><th style="text-align:right">Gestion</th></tr></thead>
            <tbody>
            <?php foreach($userActivity as $u): $maxActions = max(array_column($userActivity,'actions')) ?: 1; ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="user-avatar" style="width:30px;height:30px;font-size:12px"><?= strtoupper(substr($u['name'],0,1)) ?></div>
                            <?= htmlspecialchars($u['name'],ENT_QUOTES,'UTF-8') ?>
                        </div>
                    </td>
                    <td><strong><?= $u['actions'] ?></strong></td>
                    <td><?= $u['convs'] ?></td>
                    <td><?= $u['results'] ?></td>
                    <td style="min-width:120px">
                        <div class="progress-bar"><div class="progress-fill" style="width:<?= $u['actions'] ? round(($u['actions']/$maxActions)*100) : 0 ?>%"></div></div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Dernières Tâches Effectuées (Tech & Équipe) -->
<div class="card" style="margin-bottom:24px">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <div style="display:flex;align-items:center;gap:10px">
            <span class="card-title" style="margin-bottom:0">💻 Dernières Tâches &amp; Développements</span>
            <span class="badge badge-purple"><?= count($recentTasks) ?> récentes</span>
        </div>
        <div style="display:flex;gap:8px">
            <?php if ($isAdm): ?>
            <a href="/gtm-tracker/tasks/create.php" class="btn btn-primary btn-sm">+ Donner une tâche</a>
            <?php endif; ?>
            <a href="/gtm-tracker/tasks/index.php" class="btn btn-secondary btn-sm">Voir tout</a>
        </div>
    </div>
    <?php if (empty($recentTasks)): ?>
    <div class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="m9 14 2 2 4-4"/></svg>
        <h3>Aucune tâche enregistrée</h3>
        <p>
            <?php if ($isAdm): ?>
                <a href="/gtm-tracker/tasks/create.php">Donner une première tâche à un collaborateur</a>
            <?php else: ?>
                Aucune tâche ne vous a encore été attribuée par l'administrateur.
            <?php endif; ?>
        </p>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <?php if($isAdm): ?><th>Membre</th><?php endif; ?>
                    <th>Catégorie</th>
                    <th>Intitulé de la tâche</th>
                    <th>Temps</th>
                    <th>Statut</th>
                    <th style="text-align:right">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($recentTasks as $rt): 
                $catB = taskCategoryBadge($rt['category']);
                $catL = taskCategoryLabel($rt['category']);
            ?>
                <tr>
                    <td style="white-space:nowrap;color:#000000;font-size:13.5px;font-weight:600"><?= date('d/m/Y', strtotime($rt['task_date'])) ?></td>
                    <?php if($isAdm): ?>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px">
                            <div class="user-avatar" style="width:28px;height:28px;font-size:12px;font-weight:700"><?= strtoupper(substr($rt['user_name'],0,1)) ?></div>
                            <span style="font-weight:700;font-size:13.5px;color:#000000"><?= htmlspecialchars(explode(' ',$rt['user_name'])[0],ENT_QUOTES,'UTF-8') ?></span>
                        </div>
                    </td>
                    <?php endif; ?>
                    <td><span class="badge <?= $catB ?>"><?= htmlspecialchars($catL,ENT_QUOTES,'UTF-8') ?></span></td>
                    <td>
                        <a href="/gtm-tracker/tasks/view.php?id=<?= $rt['id'] ?>" style="font-weight:800;font-size:14px;color:#000000;text-decoration:none">
                            <?= htmlspecialchars($rt['title'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <div style="font-size:12.5px;color:#1e293b;margin-top:3px;font-weight:500">
                            <?php if(!empty($rt['due_date'])): ?>
                                <span style="color:#b45309;font-weight:700;margin-right:8px">⏳ Échéance : <?= date('d/m', strtotime($rt['due_date'])) ?></span>
                            <?php endif; ?>
                            <?php if(!empty($rt['started_at'])): ?>
                                <span style="color:#0369a1;font-weight:700;margin-right:8px">▶️ Début : <?= date('d/m H:i', strtotime($rt['started_at'])) ?></span>
                            <?php endif; ?>
                            <?php if(!empty($rt['link_url'])): ?>
                                <a href="<?= htmlspecialchars($rt['link_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" style="color:#2563eb;font-weight:700" title="Lien externe">🔗 Lien</a>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="color:#000000;font-weight:800;font-size:14px"><?= ((float)$rt['hours_spent']>0)?number_format((float)$rt['hours_spent'],1,',',' ').' h':'—' ?></td>
                    <td style="min-width:145px">
                        <?php 
                            $rtNorm = mb_strtolower($rt['status'], 'UTF-8');
                            $rtValidated = ($rtNorm === 'validée' || $rtNorm === 'validee' || !empty($rt['validated_at']));
                            $rtDone = in_array($rtNorm, ['terminée', 'terminee', 'termine', 'validée', 'validee']);
                            $rtFinishedPending = in_array($rtNorm, ['terminée', 'terminee', 'termine']) && !$rtValidated;
                            $rtCanManage = ($isAdm || (int)$rt['user_id'] === $uid);
                        ?>
                        <?php if ($rtCanManage): ?>
                        <form method="POST" action="/gtm-tracker/tasks/toggle-status.php" style="margin:0">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="task_id" value="<?= $rt['id'] ?>">
                            <input type="hidden" name="redirect" value="/gtm-tracker/dashboard.php">
                            <select name="new_status" onchange="this.form.submit()" class="form-control" style="padding:4px 24px 4px 8px;font-size:12px;font-weight:700;border-radius:16px;height:auto;cursor:pointer;background-color:#ffffff;border:2px solid <?= ($rtValidated ? '#059669' : ($rtFinishedPending ? '#d97706' : ($rtNorm === 'en cours' ? '#4f46e5' : ($rtNorm === 'bloquée' || $rtNorm === 'bloquee' ? '#dc2626' : '#cbd5e1')))) ?>;color:#000000" title="Changer le statut en 1 clic">
                                <option value="A faire" <?= ($rtNorm === 'a faire' || $rtNorm === 'à faire') ? 'selected' : '' ?>>⏳ À faire</option>
                                <option value="En cours" <?= ($rtNorm === 'en cours') ? 'selected' : '' ?>>⚡ En cours</option>
                                <option value="Terminée" <?= ($rtFinishedPending) ? 'selected' : '' ?>>🏁 Terminée</option>
                                <?php if ($isAdm): ?>
                                <option value="Validée" <?= ($rtValidated) ? 'selected' : '' ?>>✅ Validée (Admin)</option>
                                <?php endif; ?>
                                <option value="Bloquée" <?= ($rtNorm === 'bloquée' || $rtNorm === 'bloquee') ? 'selected' : '' ?>>🛑 Bloquée</option>
                            </select>
                        </form>
                        <?php else: ?>
                        <span class="badge <?= statusBadge($rt['status']) ?>"><?= htmlspecialchars($rt['status'],ENT_QUOTES,'UTF-8') ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;white-space:nowrap">
                        <?php if ($rtCanManage): ?>
                            <?php if ($isAdm && $rtFinishedPending): ?>
                                <form method="POST" action="/gtm-tracker/tasks/validate.php" style="display:inline;margin:0">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="task_id" value="<?= $rt['id'] ?>">
                                    <input type="hidden" name="action" value="validate">
                                    <input type="hidden" name="redirect" value="/gtm-tracker/dashboard.php">
                                    <button type="submit" class="btn btn-sm" style="background:#059669;color:#fff;padding:3px 9px;font-size:11.5px;font-weight:800;border-radius:5px;border:none" title="Valider la tâche">
                                        ✅ Valider
                                    </button>
                                </form>
                            <?php elseif ($rtNorm === 'a faire' || $rtNorm === 'à faire'): ?>
                                <form method="POST" action="/gtm-tracker/tasks/start.php" style="display:inline;margin:0">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="task_id" value="<?= $rt['id'] ?>">
                                    <input type="hidden" name="redirect" value="/gtm-tracker/dashboard.php">
                                    <button type="submit" class="btn btn-sm" style="background:#6366f1;color:#fff;padding:3px 9px;font-size:11.5px;font-weight:700;border-radius:5px" title="Commencer cette tâche">
                                        ▶️ Démarrer
                                    </button>
                                </form>
                            <?php elseif ($rtNorm === 'en cours'): ?>
                                <a href="/gtm-tracker/tasks/finish.php?id=<?= $rt['id'] ?>" class="btn btn-sm" style="background:#10b981;color:#fff;padding:3px 9px;font-size:11.5px;font-weight:700;border-radius:5px;text-decoration:none" title="Terminer cette tâche">
                                    🏁 Terminer
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a href="/gtm-tracker/tasks/view.php?id=<?= $rt['id'] ?>" class="btn btn-secondary btn-sm" style="padding:4px 8px" title="Voir la tâche">👁️</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Recent Actions -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Actions recentes</span>
        <?php if (isAdmin()): ?><a href="/gtm-tracker/actions/index.php" class="btn btn-secondary btn-sm">Voir tout</a><?php endif; ?>
    </div>
    <?php if (empty($recentActions)): ?>
    <div class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        <h3>Aucune action enregistree</h3>
        <?php if ($isAdm): ?><p>Commencez par <a href="/gtm-tracker/actions/create.php">ajouter une action</a></p><?php else: ?><p style="color:var(--text-muted)">Aucune action enregistrée.</p><?php endif; ?>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead><tr><?php if($isAdm): ?><th>Utilisateur</th><?php endif; ?><th>Titre</th><th>Strategie</th><th>Canal</th><th>Statut</th><th>Priorite</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach($recentActions as $a): ?>
                <tr>
                    <?php if($isAdm): ?><td><?= htmlspecialchars($a['user_name'],ENT_QUOTES,'UTF-8') ?></td><?php endif; ?>
                    <td><a href="/gtm-tracker/actions/view.php?id=<?= $a['id'] ?>"><?= htmlspecialchars($a['title'],ENT_QUOTES,'UTF-8') ?></a></td>
                    <td><?= htmlspecialchars($a['strategy_name'] ?? '—',ENT_QUOTES,'UTF-8') ?></td>
                    <td><?= htmlspecialchars($a['channel_name'] ?? '—',ENT_QUOTES,'UTF-8') ?></td>
                    <td><span class="badge <?= statusBadge($a['status']) ?>"><?= htmlspecialchars($a['status'],ENT_QUOTES,'UTF-8') ?></span></td>
                    <td><span class="badge <?= priorityBadge($a['priority']) ?>"><?= htmlspecialchars($a['priority'],ENT_QUOTES,'UTF-8') ?></span></td>
                    <td><?= htmlspecialchars($a['action_date'],ENT_QUOTES,'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

</div><!-- end animate-in -->

<?php
// Pass chart data to JS
$channelLabels = json_encode(array_column($byChannel, 'name'));
$channelData   = json_encode(array_column($byChannel, 'actions'));
$stratLabels   = json_encode(array_column($byStrategy, 'name'));
$stratData     = json_encode(array_column($byStrategy, 'actions'));
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    renderBarChart('channelChart', <?= $channelLabels ?>, <?= $channelData ?>, '#6366f1');
    renderBarChart('strategyChart', <?= $stratLabels ?>, <?= $stratData ?>, '#22c55e');
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>




