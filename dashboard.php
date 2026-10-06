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




?>
<div class="animate-in">

<div class="page-header">
    <div>
        <h2 class="page-header-title">Tableau de bord GTM</h2>
        <p class="page-header-sub">Vue d'ensemble et pilotage de la strategie Go-To-Market</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <?php if ($isAdm): ?>
        <a href="/gtm-tracker/admin/users.php" class="btn btn-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
            Inscrire un collaborateur
        </a>
        <?php endif; ?><?php if ($isAdm): ?>
        <a href="/gtm-tracker/actions/create.php" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nouvelle action
        </a><?php endif; ?>
    </div>
</div>

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




