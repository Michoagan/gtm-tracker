<?php
$pageTitle = 'Entraide & SOS Collaborateurs';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$uid = $_SESSION['user_id'];
$isAdm = isAdmin();

// Filtres
$fStatus = $_GET['status'] ?? 'all';
$fSearch = trim($_GET['search'] ?? '');

$query = "
    SELECT hr.*, 
           u.name as author_name,
           a.title as action_title,
           p.name as prospect_name,
           p.company as prospect_company,
           COUNT(hc.id) as comments_count
    FROM help_requests hr
    JOIN users u ON u.id = hr.user_id
    LEFT JOIN actions a ON a.id = hr.action_id
    LEFT JOIN prospects p ON p.id = hr.prospect_id
    LEFT JOIN help_comments hc ON hc.request_id = hr.id
    WHERE 1=1
";
$params = [];

if ($fStatus === 'open') {
    $query .= " AND hr.status = 'Ouvert'";
} elseif ($fStatus === 'resolved') {
    $query .= " AND hr.status = 'Resolu'";
}

if ($fSearch !== '') {
    $likeOp = (($GLOBALS['CURRENT_DB_DRIVER'] ?? '') === 'pgsql') ? 'ILIKE' : 'LIKE';
    $query .= " AND (hr.title $likeOp ? OR hr.message $likeOp ? OR u.name $likeOp ?)";
    $params[] = "%$fSearch%";
    $params[] = "%$fSearch%";
    $params[] = "%$fSearch%";
}

$query .= " GROUP BY hr.id, u.name, a.title, p.name, p.company ORDER BY hr.status ASC, hr.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Stats
$openCount = $db->query("SELECT COUNT(*) FROM help_requests WHERE status = 'Ouvert'")->fetchColumn();
$resolvedCount = $db->query("SELECT COUNT(*) FROM help_requests WHERE status = 'Resolu'")->fetchColumn();
?>

<div class="page-header">
    <div>
        <h2 class="page-header-title">🤝 Entraide &amp; SOS Équipe</h2>
        <p class="page-header-sub">Un collaborateur est bloqué sur une action ou un prospect ? Demandez conseil à l'équipe !</p>
    </div>
    <a href="/gtm-tracker/help/create.php" class="btn btn-warning" style="font-weight:600;box-shadow:0 2px 10px rgba(245,158,11,0.25)">
        🆘 Lancer un appel à l'aide (SOS)
    </a>
</div>

<!-- Cartes résumées -->
<div class="stats-grid" style="margin-bottom:24px">
    <div class="stat-card" style="--card-color:#f59e0b">
        <div class="stat-label">Demandes en attente d'aide</div>
        <div class="stat-value"><?= $openCount ?></div>
        <div class="stat-sub">Collaborateurs actuellement bloqués</div>
    </div>
    <div class="stat-card" style="--card-color:#10b981">
        <div class="stat-label">Débloqués &amp; Résolus</div>
        <div class="stat-value"><?= $resolvedCount ?></div>
        <div class="stat-sub">Problèmes solutionnés collectivement</div>
    </div>
</div>

<!-- Filtres -->
<div class="filter-bar" style="margin-bottom:20px">
    <form method="GET" class="filter-form" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <div class="form-group">
            <label class="form-label">État</label>
            <select name="status" class="form-control" onchange="this.form.submit()">
                <option value="all" <?= $fStatus==='all'?'selected':'' ?>>Toutes les demandes</option>
                <option value="open" <?= $fStatus==='open'?'selected':'' ?>>⚠️ En cours / Bloqués uniquement</option>
                <option value="resolved" <?= $fStatus==='resolved'?'selected':'' ?>>✅ Résolus</option>
            </select>
        </div>
        <div class="form-group" style="flex:1">
            <label class="form-label">Recherche</label>
            <input type="text" name="search" class="form-control" placeholder="Rechercher par titre, message, collaborateur..." value="<?= htmlspecialchars($fSearch, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group" style="flex-direction:row;gap:6px">
            <button type="submit" class="btn btn-primary">Filtrer</button>
            <a href="/gtm-tracker/help/index.php" class="btn btn-secondary">Réinitialiser</a>
        </div>
    </form>
</div>

<!-- Liste des demandes -->
<div class="card">
    <?php if (empty($requests)): ?>
        <div class="empty-state" style="padding:40px 20px">
            <div style="font-size:42px;margin-bottom:12px">🎉</div>
            <h3>Aucun collaborateur n'est bloqué !</h3>
            <p style="color:var(--text-muted);margin-bottom:16px">Toute l'équipe avance sereinement. Si vous rencontrez un obstacle ou avez un doute, lancez un SOS.</p>
            <a href="/gtm-tracker/help/create.php" class="btn btn-warning">🆘 Poser une question / Signaler un blocage</a>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Statut</th>
                        <th>Sujet du blocage</th>
                        <th>Collaborateur</th>
                        <th>Lié à</th>
                        <th>Réponses</th>
                        <th>Date</th>
                        <th style="text-align:right">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($requests as $r): ?>
                    <tr style="<?= $r['status']==='Ouvert' ? 'background:rgba(245,158,11,0.03)' : '' ?>">
                        <td>
                            <?php if ($r['status'] === 'Ouvert'): ?>
                                <span class="badge badge-warning" style="display:inline-flex;align-items:center;gap:4px">
                                    ⚠️ Bloqué (Ouvert)
                                </span>
                            <?php else: ?>
                                <span class="badge badge-success" style="display:inline-flex;align-items:center;gap:4px">
                                    ✅ Résolu
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="/gtm-tracker/help/view.php?id=<?= $r['id'] ?>" style="font-weight:600;font-size:14px;color:var(--text-main)">
                                <?= htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                            <div style="font-size:12px;color:var(--text-muted);margin-top:3px;max-width:480px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                <?= htmlspecialchars(substr($r['message'], 0, 100), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px">
                                <div class="user-avatar" style="width:26px;height:26px;font-size:11px">
                                    <?= strtoupper(substr($r['author_name'], 0, 1)) ?>
                                </div>
                                <span><?= htmlspecialchars($r['author_name'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($r['action_title'])): ?>
                                <a href="/gtm-tracker/actions/view.php?id=<?= $r['action_id'] ?>" style="font-size:12px" class="badge badge-info" title="Action liée">
                                    🎯 <?= htmlspecialchars(substr($r['action_title'], 0, 24), ENT_QUOTES, 'UTF-8') ?>...
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($r['prospect_name'])): ?>
                                <a href="/gtm-tracker/prospects/view.php?id=<?= $r['prospect_id'] ?>" style="font-size:12px;margin-top:2px;display:inline-block" class="badge badge-purple" title="Prospect lié">
                                    👤 <?= htmlspecialchars($r['prospect_name'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            <?php endif; ?>
                            <?php if (empty($r['action_title']) && empty($r['prospect_name'])): ?>
                                <span style="color:var(--text-dim);font-size:12px">Général</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $r['comments_count'] > 0 ? 'badge-accent' : 'badge-gray' ?>">
                                💬 <?= $r['comments_count'] ?> conseil(s)
                            </span>
                        </td>
                        <td style="font-size:12px;color:var(--text-dim)">
                            <?= date('d/m H:i', strtotime($r['created_at'])) ?>
                        </td>
                        <td style="text-align:right">
                            <a href="/gtm-tracker/help/view.php?id=<?= $r['id'] ?>" class="btn btn-secondary btn-sm">
                                <?= $r['status'] === 'Ouvert' ? '💡 Aider / Répondre' : 'Voir l\'échange' ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
