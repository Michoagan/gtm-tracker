<?php
$pageTitle = "Journal d'activite";
require_once __DIR__ . '/../includes/header.php';
requireAdmin();
$db = getDB();

$logs = $db->query("
    SELECT l.*, u.name as user_name, u.email as user_email 
    FROM activity_log l 
    LEFT JOIN users u ON u.id = l.user_id 
    ORDER BY l.created_at DESC 
    LIMIT 100
")->fetchAll();
?>
<div class="page-header">
    <div>
        <h2 class="page-header-title">Journal d'activite</h2>
        <p class="page-header-sub">Historique des 100 dernieres actions realisees sur la plateforme</p>
    </div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">Evenements systeme</span></div>
    <?php if (empty($logs)): ?>
        <div class="empty-state">
            <p>Aucun evenement enregistre pour le moment.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Date &amp; Heure</th>
                        <th>Membre</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>Adresse IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $l): ?>
                    <tr>
                        <td><span style="font-family:monospace;font-size:12px"><?= date('d/m/Y H:i', strtotime($l['created_at'])) ?></span></td>
                        <td>
                            <?php if ($l['user_name']): ?>
                                <strong><?= htmlspecialchars($l['user_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <span style="font-size:11px;color:var(--text-muted);display:block"><?= htmlspecialchars($l['user_email'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <span style="color:var(--text-muted)">Systeme</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-accent"><?= htmlspecialchars($l['action'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><?= htmlspecialchars($l['details'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($l['ip_address'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
