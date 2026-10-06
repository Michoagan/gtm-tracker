<?php
$pageTitle = 'Conversations';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$uid = $_SESSION['user_id'];
$isAdm = isAdmin();

$fStatus = $_GET['status'] ?? '';
$fChannel = $_GET['channel'] ?? '';
$fSearch = $_GET['search'] ?? '';

$query = "SELECT c.*,u.name as user_name,p.name as prospect_name,p.company,ch.name as channel_name,s.name as strategy_name
           FROM conversations c
           JOIN users u ON u.id=c.user_id
           LEFT JOIN prospects p ON p.id=c.prospect_id
           LEFT JOIN channels ch ON ch.id=c.channel_id
           LEFT JOIN strategies s ON s.id=c.strategy_id
           WHERE 1=1";
$params = [];
if (!$isAdm) { $query .= " AND c.user_id=?"; $params[] = $uid; }
if ($fStatus)  { $query .= " AND c.status=?"; $params[] = $fStatus; }
if ($fChannel) { $query .= " AND c.channel_id=?"; $params[] = $fChannel; }
if ($fSearch)  { $query .= " AND (p.name LIKE ? OR p.company LIKE ? OR c.message_sent LIKE ?)"; $params = array_merge($params, ["%$fSearch%","%$fSearch%","%$fSearch%"]); }
$query .= " ORDER BY c.conversation_date DESC, c.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$conversations = $stmt->fetchAll();
$channels = $db->query("SELECT id,name FROM channels ORDER BY name")->fetchAll();
$statuses = ['Nouveau','Contacte','En discussion','Interesse','Rendez-vous','Converti','Perdu','A relancer'];

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

if (!function_exists('statusBadge')) {
function statusBadge($s): string {
    $map = ['Nouveau'=>'badge-gray','Contacte'=>'badge-info','En discussion'=>'badge-accent','Interesse'=>'badge-warning','Rendez-vous'=>'badge-purple','Converti'=>'badge-success','Perdu'=>'badge-danger','A relancer'=>'badge-warning'];
    return $map[$s] ?? 'badge-gray';
}
}
?>
<div class="page-header">
    <div>
        <h2 class="page-header-title">Conversations</h2>
        <p class="page-header-sub"><?= count($conversations) ?> conversation(s)</p>
    </div>
    <a href="/gtm-tracker/conversations/create.php" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nouvelle conversation
    </a>
</div>

<div class="filter-bar">
    <form method="GET" class="filter-grid">
        <div class="form-group">
            <label class="form-label">Statut</label>
            <select name="status" class="form-control">
                <option value="">Tous</option>
                <?php foreach($statuses as $st): ?>
                <option value="<?= $st ?>" <?= $fStatus===$st?'selected':'' ?>><?= $st ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Canal</label>
            <select name="channel" class="form-control">
                <option value="">Tous</option>
                <?php foreach($channels as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $fChannel==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name'],ENT_QUOTES,'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Recherche</label>
            <input type="text" name="search" class="form-control" placeholder="Prospect, entreprise..." value="<?= htmlspecialchars($fSearch,ENT_QUOTES,'UTF-8') ?>">
        </div>
        <div class="form-group" style="flex-direction:row;align-items:flex-end;gap:8px">
            <button type="submit" class="btn btn-primary">Filtrer</button>
            <a href="/gtm-tracker/conversations/index.php" class="btn btn-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <?php if(empty($conversations)): ?>
    <div class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <h3>Aucune conversation</h3>
        <p><a href="/gtm-tracker/conversations/create.php">Enregistrer une conversation</a></p>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <?php if($isAdm): ?><th>Utilisateur</th><?php endif; ?>
                    <th>Prospect</th><th>Entreprise</th><th>Canal</th><th>Strategie</th><th>Statut</th><th>Date</th><th>Prochaine action</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($conversations as $c): ?>
                <tr>
                    <?php if($isAdm): ?><td><?= htmlspecialchars($c['user_name'],ENT_QUOTES,'UTF-8') ?></td><?php endif; ?>
                    <td><a href="/gtm-tracker/conversations/view.php?id=<?= $c['id'] ?>" style="font-weight:500"><?= htmlspecialchars($c['prospect_name']??'—',ENT_QUOTES,'UTF-8') ?></a></td>
                    <td><?= htmlspecialchars($c['company']??'—',ENT_QUOTES,'UTF-8') ?></td>
                    <td><?= htmlspecialchars($c['channel_name']??'—',ENT_QUOTES,'UTF-8') ?></td>
                    <td><?= htmlspecialchars($c['strategy_name']??'—',ENT_QUOTES,'UTF-8') ?></td>
                    <td><span class="badge <?= statusBadge($c['status']) ?>"><?= htmlspecialchars($c['status'],ENT_QUOTES,'UTF-8') ?></span></td>
                    <td><?= htmlspecialchars($c['conversation_date'],ENT_QUOTES,'UTF-8') ?></td>
                    <td><?= !empty($c['next_action'])?htmlspecialchars(substr($c['next_action'],0,40),ENT_QUOTES,'UTF-8').'...' : '—' ?></td>
                    <td>
                        <div style="display:flex;gap:5px">
                            <a href="/gtm-tracker/conversations/view.php?id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm btn-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </a>
                            <?php if($isAdm || $c['user_id']==$uid): ?>
                            <a href="/gtm-tracker/conversations/edit.php?id=<?= $c['id'] ?>" class="btn btn-warning btn-sm btn-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            <a href="/gtm-tracker/conversations/delete.php?id=<?= $c['id'] ?>" class="btn btn-danger btn-sm btn-icon" data-confirm="Supprimer cette conversation ?">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
