<?php
$pageTitle = 'Detail conversation';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$uid = $_SESSION['user_id'];
$isAdm = isAdmin();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT c.*,u.name as user_name,p.name as prospect_name,p.company,p.contact,ch.name as channel_name,s.name as strategy_name FROM conversations c JOIN users u ON u.id=c.user_id LEFT JOIN prospects p ON p.id=c.prospect_id LEFT JOIN channels ch ON ch.id=c.channel_id LEFT JOIN strategies s ON s.id=c.strategy_id WHERE c.id=?");
$stmt->execute([$id]);
$conv = $stmt->fetch();
if (!$conv || (!$isAdm && $conv['user_id']!=$uid)) { header('Location: /gtm-tracker/conversations/index.php'); exit; }
$attachments = $db->prepare("SELECT * FROM attachments WHERE conversation_id=?");
$attachments->execute([$id]);
$attachments = $attachments->fetchAll();

?>
<div class="page-header">
    <h2 class="page-header-title">Detail de la conversation</h2>
    <div style="display:flex;gap:8px">
        <?php if($isAdm || $conv['user_id']==$uid): ?>
        <a href="/gtm-tracker/conversations/edit.php?id=<?= $id ?>" class="btn btn-warning">Modifier</a>
        <a href="/gtm-tracker/conversations/delete.php?id=<?= $id ?>" class="btn btn-danger" data-confirm="Supprimer cette conversation ?">Supprimer</a>
        <?php endif; ?>
        <a href="/gtm-tracker/conversations/index.php" class="btn btn-secondary">Retour</a>
    </div>
</div>
<div class="grid-2" style="margin-bottom:20px">
    <div class="card">
        <div class="card-header"><span class="card-title">Informations</span></div>
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-item"><label>Prospect</label><p><?= htmlspecialchars($conv['prospect_name']??'—',ENT_QUOTES,'UTF-8') ?></p></div>
                <div class="detail-item"><label>Entreprise</label><p><?= htmlspecialchars($conv['company']??'—',ENT_QUOTES,'UTF-8') ?></p></div>
                <div class="detail-item"><label>Contact</label><p><?= htmlspecialchars($conv['contact']??'—',ENT_QUOTES,'UTF-8') ?></p></div>
                <div class="detail-item"><label>Responsable</label><p><?= htmlspecialchars($conv['user_name'],ENT_QUOTES,'UTF-8') ?></p></div>
                <div class="detail-item"><label>Canal</label><p><?= htmlspecialchars($conv['channel_name']??'—',ENT_QUOTES,'UTF-8') ?></p></div>
                <div class="detail-item"><label>Strategie</label><p><?= htmlspecialchars($conv['strategy_name']??'—',ENT_QUOTES,'UTF-8') ?></p></div>
                <div class="detail-item"><label>Statut</label><p><span class="badge <?= statusBadge($conv['status']) ?>"><?= htmlspecialchars($conv['status'],ENT_QUOTES,'UTF-8') ?></span></p></div>
                <div class="detail-item"><label>Date</label><p><?= htmlspecialchars($conv['conversation_date'],ENT_QUOTES,'UTF-8') ?></p></div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Echanges</span></div>
        <div class="card-body">
            <?php if(!empty($conv['message_sent'])): ?>
            <div class="detail-item" style="margin-bottom:16px"><label>Message envoye</label><p><?= nl2br(htmlspecialchars($conv['message_sent'],ENT_QUOTES,'UTF-8')) ?></p></div>
            <?php endif; ?>
            <?php if(!empty($conv['response_received'])): ?>
            <div class="detail-item" style="margin-bottom:16px"><label>Reponse recue</label><p style="color:var(--success)"><?= nl2br(htmlspecialchars($conv['response_received'],ENT_QUOTES,'UTF-8')) ?></p></div>
            <?php endif; ?>
            <?php if(!empty($conv['next_action'])): ?>
            <div class="detail-item" style="margin-bottom:16px"><label>Prochaine action</label><p style="color:var(--warning)"><?= nl2br(htmlspecialchars($conv['next_action'],ENT_QUOTES,'UTF-8')) ?></p></div>
            <?php endif; ?>
            <?php if(!empty($conv['comment'])): ?>
            <div class="detail-item"><label>Commentaire</label><p><?= nl2br(htmlspecialchars($conv['comment'],ENT_QUOTES,'UTF-8')) ?></p></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php if(!empty($attachments)): ?>
<div class="card">
    <div class="card-header"><span class="card-title">Pieces jointes</span></div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Fichier</th><th>Type</th><th>Taille</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach($attachments as $att): ?>
            <tr>
                <td><a href="/gtm-tracker/<?= htmlspecialchars($att['file_path'],ENT_QUOTES,'UTF-8') ?>" target="_blank"><?= htmlspecialchars($att['original_name'],ENT_QUOTES,'UTF-8') ?></a></td>
                <td><?= htmlspecialchars($att['file_type'],ENT_QUOTES,'UTF-8') ?></td>
                <td><?= round($att['file_size']/1024,1) ?> Ko</td>
                <td><?= htmlspecialchars($att['created_at'],ENT_QUOTES,'UTF-8') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

