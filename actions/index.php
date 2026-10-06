<?php
$pageTitle = 'Actions';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$uid = $_SESSION['user_id'];
$isAdm = isAdmin();

// Filters
$fUser     = $_GET['user'] ?? '';
$fStrategy = $_GET['strategy'] ?? '';
$fChannel  = $_GET['channel'] ?? '';
$fStatus   = $_GET['status'] ?? '';
$fDateFrom = $_GET['date_from'] ?? '';
$fDateTo   = $_GET['date_to'] ?? '';
$fSearch   = $_GET['search'] ?? '';

$query = "SELECT a.*, u.name as user_name, assigner.name as assigned_by_name, s.name as strategy_name, ch.name as channel_name
           FROM actions a
           JOIN users u ON u.id = a.user_id
           LEFT JOIN users assigner ON assigner.id = a.assigned_by
           LEFT JOIN strategies s ON s.id = a.strategy_id
           LEFT JOIN channels ch ON ch.id = a.channel_id
           WHERE 1=1";
$params = [];

if (!$isAdm) { $query .= " AND a.user_id = ?"; $params[] = $uid; }
elseif ($fUser)  { $query .= " AND a.user_id = ?"; $params[] = $fUser; }

if ($fStrategy) { $query .= " AND a.strategy_id = ?"; $params[] = $fStrategy; }
if ($fChannel)  { $query .= " AND a.channel_id = ?";  $params[] = $fChannel; }
if ($fStatus)   { $query .= " AND a.status = ?";       $params[] = $fStatus; }
if ($fDateFrom)  { $query .= " AND a.action_date >= ?";  $params[] = $fDateFrom; }
if ($fDateTo)    { $query .= " AND a.action_date <= ?";  $params[] = $fDateTo; }
if ($fSearch)   { $query .= " AND (a.title LIKE ? OR a.description LIKE ? OR a.result LIKE ?)"; $params = array_merge($params, ["%$fSearch%","%$fSearch%","%$fSearch%"]); }

$query .= " ORDER BY a.action_date DESC, a.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$actions = $stmt->fetchAll();

$users      = array_values(getMembers());
$strategies = $db->query("SELECT id,name FROM strategies ORDER BY name")->fetchAll();
$channels   = $db->query("SELECT id,name FROM channels ORDER BY name")->fetchAll();
$statuses   = ['A faire','En cours','Terminee','Bloquee'];



?>
<div class="page-header">
    <div>
        <h2 class="page-header-title">Actions GTM</h2>
        <p class="page-header-sub"><?= count($actions) ?> action(s) trouvee(s)</p>
    </div>
    <?php if ($isAdm): ?>
    <a href="/gtm-tracker/actions/create.php" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nouvelle action
    </a>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="filter-bar">
    <form method="GET" class="filter-grid">
        <?php if($isAdm): ?>
        <div class="form-group">
            <label class="form-label">Utilisateur</label>
            <select name="user" class="form-control">
                <option value="">Tous</option>
                <?php foreach($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $fUser==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['name'],ENT_QUOTES,'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="form-group">
            <label class="form-label">Strategie</label>
            <select name="strategy" class="form-control">
                <option value="">Toutes</option>
                <?php foreach($strategies as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $fStrategy==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['name'],ENT_QUOTES,'UTF-8') ?></option>
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
            <label class="form-label">Statut</label>
            <select name="status" class="form-control">
                <option value="">Tous</option>
                <?php foreach($statuses as $st): ?>
                <option value="<?= $st ?>" <?= $fStatus===$st?'selected':'' ?>><?= $st ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Du</label>
            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($fDateFrom,ENT_QUOTES,'UTF-8') ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Au</label>
            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($fDateTo,ENT_QUOTES,'UTF-8') ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Recherche</label>
            <input type="text" name="search" class="form-control" placeholder="Titre, description..." value="<?= htmlspecialchars($fSearch,ENT_QUOTES,'UTF-8') ?>">
        </div>
        <div class="form-group" style="justify-content:flex-end;flex-direction:row;align-items:flex-end;gap:8px">
            <button type="submit" class="btn btn-primary">Filtrer</button>
            <a href="/gtm-tracker/actions/index.php" class="btn btn-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <?php if(empty($actions)): ?>
    <div class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        <h3>Aucune action trouvee</h3>
        <?php if ($isAdm): ?><p><a href="/gtm-tracker/actions/create.php">Creer une action</a></p><?php else: ?><p style="color:var(--text-muted)">Les actions sont assignées par l'administrateur.</p><?php endif; ?>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <?php if($isAdm): ?><th>Utilisateur</th><?php endif; ?>
                    <th>Titre</th><th>Strategie</th><th>Canal</th>
                    <th>Statut</th><th>Priorite</th><th>Date</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($actions as $a): ?>
                <tr>
                    <?php if($isAdm): ?><td>
                        <strong><?= htmlspecialchars($a['user_name'],ENT_QUOTES,'UTF-8') ?></strong>
                        <?php if(!empty($a['assigned_by_name'])): ?>
                        <div style="font-size:10.5px;color:var(--text-muted)">par <?= htmlspecialchars($a['assigned_by_name'],ENT_QUOTES,'UTF-8') ?></div>
                        <?php endif; ?>
                    </td><?php endif; ?>
                    <td>
                        <a href="/gtm-tracker/actions/view.php?id=<?= $a['id'] ?>" style="font-weight:500">
                            <?= htmlspecialchars($a['title'],ENT_QUOTES,'UTF-8') ?>
                        </a>
                        <?php if(!empty($a['result'])): ?>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:2px"><?= htmlspecialchars(substr($a['result'],0,50),ENT_QUOTES,'UTF-8') ?><?= strlen($a['result'])>50?'...':'' ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($a['strategy_name']??'—',ENT_QUOTES,'UTF-8') ?></td>
                    <td><?= htmlspecialchars($a['channel_name']??'—',ENT_QUOTES,'UTF-8') ?></td>
                    <td><span class="badge <?= statusBadge($a['status']) ?>"><?= htmlspecialchars($a['status'],ENT_QUOTES,'UTF-8') ?></span></td>
                    <td><span class="badge <?= priorityBadge($a['priority']) ?>"><?= htmlspecialchars($a['priority'],ENT_QUOTES,'UTF-8') ?></span></td>
                    <td><?= htmlspecialchars($a['action_date'],ENT_QUOTES,'UTF-8') ?></td>
                    <td>
                        <div style="display:flex;gap:6px">
                            <a href="/gtm-tracker/actions/view.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Voir">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </a>
                            <?php if($isAdm || $a['user_id']==$uid): ?>
                            <a href="/gtm-tracker/actions/edit.php?id=<?= $a['id'] ?>" class="btn btn-warning btn-sm btn-icon" title="Modifier">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            <a href="/gtm-tracker/actions/delete.php?id=<?= $a['id'] ?>" class="btn btn-danger btn-sm btn-icon" title="Supprimer" data-confirm="Supprimer cette action ?">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
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


