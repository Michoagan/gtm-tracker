<?php
$pageTitle = 'Prospects';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$uid = $_SESSION['user_id'];
$isAdm = isAdmin();
$fStatus = $_GET['status'] ?? '';
$fSearch = $_GET['search'] ?? '';
$query = "SELECT p.*,u.name as assigned_name,ch.name as channel_name FROM prospects p LEFT JOIN users u ON u.id=p.assigned_to LEFT JOIN channels ch ON ch.id=p.channel_id WHERE 1=1";
$params = [];
if (!$isAdm) { $query .= " AND p.assigned_to=?"; $params[] = $uid; }
if ($fStatus) { $query .= " AND p.status=?"; $params[] = $fStatus; }
if ($fSearch) { $query .= " AND (p.name LIKE ? OR p.company LIKE ? OR p.contact LIKE ?)"; $params = array_merge($params, ["%$fSearch%","%$fSearch%","%$fSearch%"]); }
$query .= " ORDER BY p.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$prospects = $stmt->fetchAll();
$statuses = ['Nouveau','Contacte','En discussion','Interesse','Rendez-vous','Converti','Perdu','A relancer'];
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

?>
<div class="page-header">
    <div>
        <h2 class="page-header-title">Prospects</h2>
        <p class="page-header-sub"><?= count($prospects) ?> prospect(s)</p>
    </div>
    <a href="/gtm-tracker/prospects/create.php" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nouveau prospect
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
            <label class="form-label">Recherche</label>
            <input type="text" name="search" class="form-control" placeholder="Nom, entreprise..." value="<?= htmlspecialchars($fSearch,ENT_QUOTES,'UTF-8') ?>">
        </div>
        <div class="form-group" style="flex-direction:row;align-items:flex-end;gap:8px">
            <button type="submit" class="btn btn-primary">Filtrer</button>
            <a href="/gtm-tracker/prospects/index.php" class="btn btn-secondary">Reset</a>
        </div>
    </form>
</div>
<div class="card">
    <?php if(empty($prospects)): ?>
    <div class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        <h3>Aucun prospect</h3>
        <p><a href="/gtm-tracker/prospects/create.php">Ajouter un prospect</a></p>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Nom</th><th>Entreprise</th><th>Canal</th><th>Statut</th><th>Suivi &amp; Relance</th><?php if($isAdm): ?><th>Assigne a</th><?php endif; ?><th style="text-align:right">Action</th></tr></thead>
            <tbody>
            <?php foreach($prospects as $p): ?>
            <tr>
                <td>
                    <a href="/gtm-tracker/prospects/view.php?id=<?= $p['id'] ?>" style="font-weight:600">
                        <?= htmlspecialchars($p['name'],ENT_QUOTES,'UTF-8') ?>
                    </a>
                    <?php if(!empty($p['contact'])): ?>
                    <div style="font-size:11.5px;color:var(--text-muted)"><?= htmlspecialchars($p['contact'],ENT_QUOTES,'UTF-8') ?></div>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($p['company']??'—',ENT_QUOTES,'UTF-8') ?></td>
                <td><span class="badge badge-gray"><?= htmlspecialchars($p['channel_name']??'—',ENT_QUOTES,'UTF-8') ?></span></td>
                <td><span class="badge <?= statusBadge($p['status']) ?>"><?= htmlspecialchars($p['status'],ENT_QUOTES,'UTF-8') ?></span></td>
                <td>
                    <span class="badge badge-accent">🔄 <?= htmlspecialchars($p['follow_up_status'] ?? 'A relancer', ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if(!empty($p['next_followup_date'])): ?>
                    <div style="font-size:11px;color:var(--text-dim);margin-top:3px">📅 <?= date('d/m/Y', strtotime($p['next_followup_date'])) ?></div>
                    <?php endif; ?>
                </td>
                <?php if($isAdm): ?><td><?= htmlspecialchars($p['assigned_name']??'—',ENT_QUOTES,'UTF-8') ?></td><?php endif; ?>
                <td style="text-align:right">
                    <div style="display:inline-flex;gap:5px;align-items:center">
                        <a href="/gtm-tracker/prospects/view.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm" title="Ouvrir fiche & noter échange">
                            👁️ Fiche &amp; Échange
                        </a>
                        <?php if($isAdm || $p['assigned_to']==$uid): ?>
                        <a href="/gtm-tracker/prospects/edit.php?id=<?= $p['id'] ?>" class="btn btn-warning btn-sm btn-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </a>
                        <a href="/gtm-tracker/prospects/delete.php?id=<?= $p['id'] ?>" class="btn btn-danger btn-sm btn-icon" data-confirm="Supprimer ce prospect ?">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
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

