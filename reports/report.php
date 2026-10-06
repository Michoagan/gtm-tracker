<?php
$pageTitle = 'Bilan & Rapports';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$isAdm = isAdmin();
$uid = $_SESSION['user_id'];

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');
$generated = isset($_GET['generate']) && ($_GET['generate']==='1');


?>
<div class="page-header">
    <div>
        <h2 class="page-header-title">Bilan & Rapports</h2>
        <p class="page-header-sub">Analysez les performances de l'equipe</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if($isAdm): ?>
    <a href="/gtm-tracker/reports/export-sheets.php?date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>" class="btn btn-primary" id="btnExportSheets" title="Fichier .xlsx multi-onglets, s'ouvre directement dans Google Sheets">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
        Télécharger (Google Sheets)
    </a>
    <a href="https://docs.google.com/spreadsheets/u/0/" target="_blank" rel="noopener" class="btn btn-secondary" title="Ouvrir Google Sheets puis Fichier → Importer le .xlsx">Ouvrir Google Sheets ↗</a>
    <?php endif; ?>
    <?php if($generated): ?>
    <a href="/gtm-tracker/reports/export.php?date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?><?= !$isAdm ? '&user_id='.(int)$uid : '' ?>" class="btn btn-success" target="_blank">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Exporter PDF (impression)
    </a>
    <?php endif; ?>
    </div>
</div>

<!-- Filter -->
<div class="filter-bar" style="margin-bottom:24px">
    <form method="GET" class="filter-grid">
        <div class="form-group">
            <label class="form-label">Date debut</label>
            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom,ENT_QUOTES,'UTF-8') ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Date fin</label>
            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo,ENT_QUOTES,'UTF-8') ?>">
        </div>
        <div class="form-group" style="flex-direction:row;align-items:flex-end;gap:8px">
            <button type="submit" name="generate" value="1" class="btn btn-primary">Generer le bilan</button>
        </div>
    </form>
</div>

<?php if($generated):
$uidFilter = $isAdm ? null : $uid;
$baseWhere = $uidFilter ? "WHERE user_id=$uidFilter AND" : "WHERE";
$baseWhereA = "WHERE action_date BETWEEN '$dateFrom' AND '$dateTo'" . ($uidFilter ? " AND user_id=$uidFilter" : "");
$baseWhereC = "WHERE conversation_date BETWEEN '$dateFrom' AND '$dateTo'" . ($uidFilter ? " AND user_id=$uidFilter" : "");

$totalActions  = $db->query("SELECT COUNT(*) FROM actions $baseWhereA")->fetchColumn();
$totalConv     = $db->query("SELECT COUNT(*) FROM conversations $baseWhereC")->fetchColumn();
$totalResp     = $db->query("SELECT COUNT(*) FROM conversations $baseWhereC AND response_received IS NOT NULL AND response_received!=''")->fetchColumn();
$totalInterest = $db->query("SELECT COUNT(*) FROM conversations $baseWhereC AND status IN ('Interesse','Rendez-vous','Converti')")->fetchColumn();
$totalRdv      = $db->query("SELECT COUNT(*) FROM conversations $baseWhereC AND status='Rendez-vous'")->fetchColumn();
$totalConvert  = $db->query("SELECT COUNT(*) FROM conversations $baseWhereC AND status='Converti'")->fetchColumn();
$totalDone     = $db->query("SELECT COUNT(*) FROM actions $baseWhereA AND status='Terminee'")->fetchColumn();

$rateResp = $totalConv > 0 ? round(($totalResp/$totalConv)*100,1) : 0;
$rateConv = $totalConv > 0 ? round(($totalConvert/$totalConv)*100,1) : 0;
?>

<!-- Global stats -->
<div class="stats-grid">
    <div class="stat-card" style="--card-color:#6366f1"><div class="stat-label">Actions totales</div><div class="stat-value"><?= $totalActions ?></div><div class="stat-sub"><?= $totalDone ?> terminees</div></div>
    <div class="stat-card" style="--card-color:#3b82f6"><div class="stat-label">Conversations</div><div class="stat-value"><?= $totalConv ?></div></div>
    <div class="stat-card" style="--card-color:#8b5cf6"><div class="stat-label">Reponses</div><div class="stat-value"><?= $totalResp ?></div><div class="stat-sub">Taux: <?= $rateResp ?>%</div></div>
    <div class="stat-card" style="--card-color:#f59e0b"><div class="stat-label">Interesses</div><div class="stat-value"><?= $totalInterest ?></div></div>
    <div class="stat-card" style="--card-color:#ec4899"><div class="stat-label">Rendez-vous</div><div class="stat-value"><?= $totalRdv ?></div></div>
    <div class="stat-card" style="--card-color:#22c55e"><div class="stat-label">Conversions</div><div class="stat-value"><?= $totalConvert ?></div><div class="stat-sub">Taux: <?= $rateConv ?>%</div></div>
</div>

<?php if($isAdm): ?>
<!-- By user -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header"><span class="card-title">Performance par utilisateur</span></div>
    <div class="table-wrapper">
    <?php
    $byUser = $db->query("
        SELECT u.name,
               COUNT(DISTINCT a.id) as actions,
               COUNT(DISTINCT c.id) as convs,
               SUM(CASE WHEN c.response_received IS NOT NULL AND c.response_received!='' THEN 1 ELSE 0 END) as resps,
               SUM(CASE WHEN c.status='Converti' THEN 1 ELSE 0 END) as convts
        FROM users u
        LEFT JOIN actions a ON a.user_id=u.id AND a.action_date BETWEEN '$dateFrom' AND '$dateTo'
        LEFT JOIN conversations c ON c.user_id=u.id AND c.conversation_date BETWEEN '$dateFrom' AND '$dateTo'
        WHERE u.role IN ('admin','user')
        GROUP BY u.id,u.name ORDER BY actions DESC
    ")->fetchAll();
    ?>
        <table>
            <thead><tr><th>Utilisateur</th><th>Actions</th><th>Conversations</th><th>Reponses</th><th>Conversions</th><th>Taux conv.</th></tr></thead>
            <tbody>
            <?php foreach($byUser as $u): $tc = $u['convs']>0 ? round(($u['convts']/$u['convs'])*100,1):0; ?>
            <tr>
                <td><div style="display:flex;align-items:center;gap:8px"><div class="user-avatar" style="width:28px;height:28px;font-size:11px"><?= strtoupper(substr($u['name'],0,1)) ?></div><?= htmlspecialchars($u['name'],ENT_QUOTES,'UTF-8') ?></div></td>
                <td><strong><?= $u['actions'] ?></strong></td>
                <td><?= $u['convs'] ?></td>
                <td><?= $u['resps'] ?></td>
                <td><?= $u['convts'] ?></td>
                <td><span class="badge <?= $tc>=10?'badge-success':($tc>=5?'badge-warning':'badge-gray') ?>"><?= $tc ?>%</span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- By strategy -->
<div class="grid-2" style="margin-bottom:20px">
    <div class="card">
        <div class="card-header"><span class="card-title">Par strategie</span></div>
        <div class="table-wrapper">
        <?php
        $byStrat = $db->query("
            SELECT s.name,COUNT(DISTINCT a.id) as actions,SUM(CASE WHEN a.result!='' AND a.result IS NOT NULL THEN 1 ELSE 0 END) as results
            FROM strategies s
            LEFT JOIN actions a ON a.strategy_id=s.id AND a.action_date BETWEEN '$dateFrom' AND '$dateTo' " .($uidFilter ? "AND a.user_id=$uidFilter":"")."
            GROUP BY s.id,s.name HAVING COUNT(a.id) > 0 ORDER BY actions DESC
        ")->fetchAll();
        ?>
            <table>
                <thead><tr><th>Strategie</th><th>Actions</th><th>Resultats</th></tr></thead>
                <tbody>
                <?php foreach($byStrat as $s): ?>
                <tr><td><?= htmlspecialchars($s['name'],ENT_QUOTES,'UTF-8') ?></td><td><?= $s['actions'] ?></td><td><?= $s['results'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- By channel -->
    <div class="card">
        <div class="card-header"><span class="card-title">Par canal</span></div>
        <div class="table-wrapper">
        <?php
        $byChan = $db->query("
            SELECT ch.name,COUNT(DISTINCT a.id) as actions
            FROM channels ch
            LEFT JOIN actions a ON a.channel_id=ch.id AND a.action_date BETWEEN '$dateFrom' AND '$dateTo' ".($uidFilter ? "AND a.user_id=$uidFilter":"")."
            GROUP BY ch.id,ch.name HAVING COUNT(a.id) > 0 ORDER BY actions DESC
        ")->fetchAll();
        ?>
            <table>
                <thead><tr><th>Canal</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach($byChan as $c): ?>
                <tr><td><?= htmlspecialchars($c['name'],ENT_QUOTES,'UTF-8') ?></td><td><?= $c['actions'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; // end generated ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


