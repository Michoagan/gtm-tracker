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
?>

<div class="page-header">
    <div>
        <h2 class="page-header-title">Mes Activites GTM</h2>
        <p class="page-header-subtitle">Espace personnel de <?= htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div style="display:flex;gap:10px"><?php if (isAdmin()): ?>
        <a href="/gtm-tracker/actions/create.php" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nouvelle action
        </a><?php endif; ?>
        
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
