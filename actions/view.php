<?php
$pageTitle = 'Detail action';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$uid = $_SESSION['user_id'];
$isAdm = isAdmin();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT a.*,u.name as user_name, assigner.name as assigned_by_name, s.name as strategy_name,ch.name as channel_name,p.name as prospect_name,p.company as prospect_company,p.id as prospect_id_val FROM actions a JOIN users u ON u.id=a.user_id LEFT JOIN users assigner ON assigner.id=a.assigned_by LEFT JOIN strategies s ON s.id=a.strategy_id LEFT JOIN channels ch ON ch.id=a.channel_id LEFT JOIN prospects p ON p.id=a.prospect_id WHERE a.id=?");
$stmt->execute([$id]);
$action = $stmt->fetch();
if (!$action || (!$isAdm && $action['user_id']!=$uid)) { header('Location: /gtm-tracker/actions/index.php'); exit; }

$attachments = $db->prepare("SELECT * FROM attachments WHERE action_id=?");
$attachments->execute([$id]);
$attachments = $attachments->fetchAll();

// If linked to a prospect, fetch related conversations
$prospectConvs = [];
if (!empty($action['prospect_id_val'])) {
    $stmtC = $db->prepare("SELECT c.*,ch.name as channel_name FROM conversations c LEFT JOIN channels ch ON ch.id=c.channel_id WHERE c.prospect_id=? ORDER BY c.conversation_date DESC LIMIT 5");
    $stmtC->execute([$action['prospect_id_val']]);
    $prospectConvs = $stmtC->fetchAll();
}

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

if (!function_exists('statusBadge')) {
function statusBadge($s): string {
    $map = ['A faire'=>'badge-gray','En cours'=>'badge-info','Terminee'=>'badge-success','Bloquee'=>'badge-danger'];
    return $map[$s] ?? 'badge-gray';
}
}
if (!function_exists('priorityBadge')) {
function priorityBadge($p): string {
    $map = ['Basse'=>'priority-basse','Normale'=>'priority-normale','Haute'=>'priority-haute','Urgente'=>'priority-urgente'];
    return $map[$p] ?? 'badge-gray';
}
}
?>
<div class="page-header">
    <div>
        <h2 class="page-header-title">Detail de l'action</h2>
        <p class="page-header-sub">Enregistree le <?= htmlspecialchars($action['created_at'],ENT_QUOTES,'UTF-8') ?></p>
    </div>
    <div style="display:flex;gap:8px">
        <?php if($isAdm || $action['user_id']==$uid): ?>
        <a href="/gtm-tracker/actions/edit.php?id=<?= $id ?>" class="btn btn-warning">Modifier</a>
        <a href="/gtm-tracker/actions/delete.php?id=<?= $id ?>" class="btn btn-danger" data-confirm="Supprimer cette action ?">Supprimer</a>
        <?php endif; ?>
        <?php if (isAdmin()): ?><a href="/gtm-tracker/help/create.php?action_id=<?= $id ?>" class="btn btn-warning" title="Bloqué sur cette action ? Demandez de l'aide à l'équipe">🆘 Demander de l'aide</a><?php endif; ?> <a href="/gtm-tracker/actions/index.php" class="btn btn-secondary">Retour</a>
    </div>
</div>

<div class="grid-2" style="margin-bottom:20px">
    <div class="card">
        <div class="card-header"><span class="card-title">Informations</span></div>
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-item"><label>Titre</label><p><?= htmlspecialchars($action['title'],ENT_QUOTES,'UTF-8') ?></p></div>
                <div class="detail-item"><label>Responsable assigné</label><p><?= htmlspecialchars($action['user_name'],ENT_QUOTES,'UTF-8') ?><?php if(!empty($action['assigned_by_name'])): ?><br><span style="font-size:11.5px;color:var(--text-muted)">Assigné par : <strong><?= htmlspecialchars($action['assigned_by_name'],ENT_QUOTES,'UTF-8') ?> (Admin)</strong></span><?php endif; ?></p></div>
                <div class="detail-item"><label>Strategie</label><p><?= htmlspecialchars($action['strategy_name']??'—',ENT_QUOTES,'UTF-8') ?></p></div>
                <div class="detail-item"><label>Canal</label><p><?= htmlspecialchars($action['channel_name']??'—',ENT_QUOTES,'UTF-8') ?></p></div>
                <div class="detail-item"><label>Statut</label><p><span class="badge <?= statusBadge($action['status']) ?>"><?= htmlspecialchars($action['status'],ENT_QUOTES,'UTF-8') ?></span></p></div>
                <div class="detail-item"><label>Priorite</label><p><span class="badge <?= priorityBadge($action['priority']) ?>"><?= htmlspecialchars($action['priority'],ENT_QUOTES,'UTF-8') ?></span></p></div>
                <div class="detail-item"><label>Date</label><p><?= htmlspecialchars($action['action_date'],ENT_QUOTES,'UTF-8') ?></p></div>
                <div class="detail-item">
                    <label>Prospect lié</label>
                    <p>
                    <?php if(!empty($action['prospect_id_val'])): ?>
                        <a href="/gtm-tracker/prospects/view.php?id=<?= $action['prospect_id_val'] ?>" style="font-weight:600;color:var(--primary)">
                            <?= htmlspecialchars($action['prospect_name'],ENT_QUOTES,'UTF-8') ?>
                            <?php if(!empty($action['prospect_company'])): ?>
                                <span style="color:var(--text-muted);font-weight:400"> — <?= htmlspecialchars($action['prospect_company'],ENT_QUOTES,'UTF-8') ?></span>
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
                        <span style="color:var(--text-dim)">Aucun prospect lié</span>
                    <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Description & Resultat</span></div>
        <div class="card-body">
            <?php if(!empty($action['description'])): ?>
            <div class="detail-item" style="margin-bottom:16px"><label>Description</label><p><?= nl2br(htmlspecialchars($action['description'],ENT_QUOTES,'UTF-8')) ?></p></div>
            <?php endif; ?>
            <?php if(!empty($action['result'])): ?>
            <div class="detail-item" style="margin-bottom:16px"><label>Resultat</label><p style="color:var(--success)"><?= nl2br(htmlspecialchars($action['result'],ENT_QUOTES,'UTF-8')) ?></p></div>
            <?php endif; ?>
            <?php if(!empty($action['comment'])): ?>
            <div class="detail-item"><label>Commentaire</label><p><?= nl2br(htmlspecialchars($action['comment'],ENT_QUOTES,'UTF-8')) ?></p></div>
            <?php endif; ?>
            <?php if(empty($action['description']) && empty($action['result']) && empty($action['comment'])): ?>
            <p class="text-muted">Aucune description</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if(!empty($prospectConvs)): ?>
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <span class="card-title">Conversations avec <?= htmlspecialchars($action['prospect_name'],ENT_QUOTES,'UTF-8') ?></span>
        <a href="/gtm-tracker/conversations/create.php?prospect_id=<?= $action['prospect_id_val'] ?>" class="btn btn-primary btn-sm">+ Nouvelle conversation</a>
    </div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Date</th><th>Canal</th><th>Statut</th><th>Prochaine action</th><th></th></tr></thead>
            <tbody>
            <?php foreach($prospectConvs as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['conversation_date'],ENT_QUOTES,'UTF-8') ?></td>
                <td><?= htmlspecialchars($c['channel_name']??'—',ENT_QUOTES,'UTF-8') ?></td>
                <td><span class="badge <?= statusBadge($c['status']) ?>"><?= htmlspecialchars($c['status'],ENT_QUOTES,'UTF-8') ?></span></td>
                <td style="color:#fbbf24;font-size:12px"><?= !empty($c['next_action'])?htmlspecialchars(substr($c['next_action'],0,50),ENT_QUOTES,'UTF-8'):'—' ?></td>
                <td><a href="/gtm-tracker/conversations/view.php?id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm btn-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif(!empty($action['prospect_id_val'])): ?>
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <span class="card-title">Conversations avec <?= htmlspecialchars($action['prospect_name'],ENT_QUOTES,'UTF-8') ?></span>
        <a href="/gtm-tracker/conversations/create.php?prospect_id=<?= $action['prospect_id_val'] ?>" class="btn btn-primary btn-sm">+ Nouvelle conversation</a>
    </div>
    <div class="empty-state" style="padding:30px"><p>Aucune conversation enregistree pour ce prospect.</p></div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Pieces jointes</span>
        <?php if($isAdm || $action['user_id']==$uid): ?>
        <form method="POST" action="/gtm-tracker/actions/upload.php" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center">
            <input type="hidden" name="action_id" value="<?= $id ?>">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="file" name="attachment" class="form-control" style="padding:4px;width:auto" accept=".png,.jpg,.jpeg,.pdf">
            <button type="submit" class="btn btn-primary btn-sm">Ajouter</button>
        </form>
        <?php endif; ?>
    </div>
    <?php if(empty($attachments)): ?>
    <div class="empty-state" style="padding:30px"><p>Aucune piece jointe</p></div>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Fichier</th><th>Type</th><th>Taille</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach($attachments as $att): ?>
            <tr>
                <td><a href="/gtm-tracker/<?= htmlspecialchars($att['file_path'],ENT_QUOTES,'UTF-8') ?>" target="_blank"><?= htmlspecialchars($att['original_name'],ENT_QUOTES,'UTF-8') ?></a></td>
                <td><?= htmlspecialchars($att['file_type'],ENT_QUOTES,'UTF-8') ?></td>
                <td><?= round($att['file_size']/1024,1) ?> Ko</td>
                <td><?= htmlspecialchars($att['created_at'],ENT_QUOTES,'UTF-8') ?></td>
                <td></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>