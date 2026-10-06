<?php
session_start();
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$isAdm = isAdmin();
$uid = $_SESSION['user_id'];

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');
$uidFilter = $isAdm ? null : $uid;

$baseWhereA = "WHERE action_date BETWEEN '$dateFrom' AND '$dateTo'" . ($uidFilter ? " AND user_id=$uidFilter" : "");
$baseWhereC = "WHERE conversation_date BETWEEN '$dateFrom' AND '$dateTo'" . ($uidFilter ? " AND user_id=$uidFilter" : "");

$totalActions = $db->query("SELECT COUNT(*) FROM actions $baseWhereA")->fetchColumn();
$totalConv    = $db->query("SELECT COUNT(*) FROM conversations $baseWhereC")->fetchColumn();
$totalResp    = $db->query("SELECT COUNT(*) FROM conversations $baseWhereC AND response_received IS NOT NULL AND response_received!=''")->fetchColumn();
$totalInterest= $db->query("SELECT COUNT(*) FROM conversations $baseWhereC AND status IN ('Interesse','Rendez-vous','Converti')")->fetchColumn();
$totalRdv     = $db->query("SELECT COUNT(*) FROM conversations $baseWhereC AND status='Rendez-vous'")->fetchColumn();
$totalConvert = $db->query("SELECT COUNT(*) FROM conversations $baseWhereC AND status='Converti'")->fetchColumn();
$rateResp = $totalConv>0 ? round(($totalResp/$totalConv)*100,1):0;
$rateConv = $totalConv>0 ? round(($totalConvert/$totalConv)*100,1):0;

$byUser = [];
if ($isAdm) {
    $byUser = $db->query("
        SELECT u.name,COUNT(DISTINCT a.id) as actions,COUNT(DISTINCT c.id) as convs,
               SUM(CASE WHEN c.status='Converti' THEN 1 ELSE 0 END) as convts
        FROM users u
        LEFT JOIN actions a ON a.user_id=u.id AND a.action_date BETWEEN '$dateFrom' AND '$dateTo'
        LEFT JOIN conversations c ON c.user_id=u.id AND c.conversation_date BETWEEN '$dateFrom' AND '$dateTo'
        WHERE u.role IN ('admin','user') GROUP BY u.id,u.name ORDER BY actions DESC
    ")->fetchAll();
}

$byStrat = $db->query("
    SELECT s.name,COUNT(DISTINCT a.id) as actions
    FROM strategies s LEFT JOIN actions a ON a.strategy_id=s.id AND a.action_date BETWEEN '$dateFrom' AND '$dateTo' ".($uidFilter?"AND a.user_id=$uidFilter":"")."
    GROUP BY s.id,s.name HAVING COUNT(a.id) > 0 ORDER BY actions DESC
")->fetchAll();

$byChan = $db->query("
    SELECT ch.name,COUNT(DISTINCT a.id) as actions
    FROM channels ch LEFT JOIN actions a ON a.channel_id=ch.id AND a.action_date BETWEEN '$dateFrom' AND '$dateTo' ".($uidFilter?"AND a.user_id=$uidFilter":"")."
    GROUP BY ch.id,ch.name HAVING COUNT(a.id) > 0 ORDER BY actions DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport GTM — <?= htmlspecialchars($dateFrom,ENT_QUOTES,'UTF-8') ?> au <?= htmlspecialchars($dateTo,ENT_QUOTES,'UTF-8') ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #1a1a2e; background: #fff; padding: 30px; }
        .report-header { text-align: center; border-bottom: 3px solid #6366f1; padding-bottom: 20px; margin-bottom: 30px; }
        .report-title { font-size: 28px; font-weight: 700; color: #6366f1; }
        .report-sub { font-size: 14px; color: #666; margin-top: 6px; }
        .report-period { font-size: 13px; color: #444; margin-top: 8px; background: #f0f0ff; padding: 6px 16px; border-radius: 20px; display: inline-block; }
        h2 { font-size: 16px; color: #6366f1; border-bottom: 1px solid #e0e0ff; padding-bottom: 6px; margin: 24px 0 12px; }
        .stats-row { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
        .stat-box { flex: 1; min-width: 120px; border: 1px solid #e0e0ff; border-radius: 8px; padding: 14px 16px; text-align: center; }
        .stat-box .val { font-size: 28px; font-weight: 700; color: #6366f1; }
        .stat-box .lbl { font-size: 10px; text-transform: uppercase; color: #888; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #6366f1; color: white; padding: 8px 12px; text-align: left; font-size: 11px; }
        td { padding: 7px 12px; border-bottom: 1px solid #f0f0f0; font-size: 12px; }
        tr:nth-child(even) td { background: #fafaff; }
        .footer { text-align: center; margin-top: 40px; font-size: 10px; color: #888; border-top: 1px solid #e0e0ff; padding-top: 12px; }
        @media print {
            body { padding: 10px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
<div class="no-print" style="margin-bottom:20px;text-align:center">
    <button onclick="window.print()" style="background:#6366f1;color:white;border:none;padding:10px 24px;border-radius:6px;font-size:14px;cursor:pointer">Imprimer / Sauvegarder PDF</button>
    <a href="javascript:history.back()" style="margin-left:12px;color:#6366f1">Retour</a>
</div>

<div class="report-header">
    <div class="report-title">GTM Tracker — Rapport d'activite</div>
    <div class="report-sub">Tableau de bord Go-To-Market</div>
    <div class="report-period">Periode: du <?= htmlspecialchars(date('d/m/Y',strtotime($dateFrom)),ENT_QUOTES,'UTF-8') ?> au <?= htmlspecialchars(date('d/m/Y',strtotime($dateTo)),ENT_QUOTES,'UTF-8') ?></div>
    <div class="report-sub" style="margin-top:8px">Genere le <?= date('d/m/Y a H:i') ?></div>
</div>

<h2>Statistiques globales</h2>
<div class="stats-row">
    <div class="stat-box"><div class="val"><?= $totalActions ?></div><div class="lbl">Actions</div></div>
    <div class="stat-box"><div class="val"><?= $totalConv ?></div><div class="lbl">Conversations</div></div>
    <div class="stat-box"><div class="val"><?= $totalResp ?></div><div class="lbl">Reponses</div></div>
    <div class="stat-box"><div class="val"><?= $rateResp ?>%</div><div class="lbl">Taux reponse</div></div>
    <div class="stat-box"><div class="val"><?= $totalInterest ?></div><div class="lbl">Interesses</div></div>
    <div class="stat-box"><div class="val"><?= $totalRdv ?></div><div class="lbl">Rendez-vous</div></div>
    <div class="stat-box"><div class="val"><?= $totalConvert ?></div><div class="lbl">Conversions</div></div>
    <div class="stat-box"><div class="val"><?= $rateConv ?>%</div><div class="lbl">Taux conv.</div></div>
</div>

<?php if(!empty($byUser)): ?>
<h2>Activite par utilisateur</h2>
<table>
    <thead><tr><th>Utilisateur</th><th>Actions</th><th>Conversations</th><th>Conversions</th></tr></thead>
    <tbody>
    <?php foreach($byUser as $u): ?>
    <tr><td><?= htmlspecialchars($u['name'],ENT_QUOTES,'UTF-8') ?></td><td><?= $u['actions'] ?></td><td><?= $u['convs'] ?></td><td><?= $u['convts'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<h2>Activite par strategie</h2>
<table>
    <thead><tr><th>Strategie</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($byStrat as $s): ?>
    <tr><td><?= htmlspecialchars($s['name'],ENT_QUOTES,'UTF-8') ?></td><td><?= $s['actions'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2>Activite par canal</h2>
<table>
    <thead><tr><th>Canal</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($byChan as $c): ?>
    <tr><td><?= htmlspecialchars($c['name'],ENT_QUOTES,'UTF-8') ?></td><td><?= $c['actions'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div class="footer">GTM Tracker v1.0 — Rapport genere automatiquement</div>
</body>
</html>
