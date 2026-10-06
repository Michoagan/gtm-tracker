<?php
$pageTitle = 'Modifier conversation';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$uid = $_SESSION['user_id'];
$isAdm = isAdmin();
$id = (int)($_GET['id']??0);
$stmt = $db->prepare("SELECT * FROM conversations WHERE id=?");
$stmt->execute([$id]); $conv = $stmt->fetch();
if (!$conv || (!$isAdm && $conv['user_id']!=$uid)) { header('Location: /gtm-tracker/conversations/index.php'); exit; }
$strategies = $db->query("SELECT id,name FROM strategies ORDER BY name")->fetchAll();
$channels   = $db->query("SELECT id,name FROM channels ORDER BY name")->fetchAll();
$prospects  = $db->query("SELECT id,name,company FROM prospects ORDER BY name")->fetchAll();
$statuses   = ['Nouveau','Contacte','En discussion','Interesse','Rendez-vous','Converti','Perdu','A relancer'];
?>
<div class="page-header">
    <h2 class="page-header-title">Modifier conversation</h2>
    <a href="/gtm-tracker/conversations/view.php?id=<?= $id ?>" class="btn btn-secondary">Retour</a>
</div>
<div class="card">
    <div class="card-body">
        <form method="POST" action="/gtm-tracker/conversations/update.php">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Prospect</label>
                    <select name="prospect_id" class="form-control">
                        <option value="">-- Choisir --</option>
                        <?php foreach($prospects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $conv['prospect_id']==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['name'],ENT_QUOTES,'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Strategie</label>
                    <select name="strategy_id" class="form-control">
                        <option value="">-- Choisir --</option>
                        <?php foreach($strategies as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $conv['strategy_id']==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['name'],ENT_QUOTES,'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Canal</label>
                    <select name="channel_id" class="form-control">
                        <option value="">-- Choisir --</option>
                        <?php foreach($channels as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $conv['channel_id']==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name'],ENT_QUOTES,'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Statut</label>
                    <select name="status" class="form-control">
                        <?php foreach($statuses as $st): ?>
                        <option value="<?= $st ?>" <?= $conv['status']===$st?'selected':'' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" name="conversation_date" class="form-control" value="<?= htmlspecialchars($conv['conversation_date'],ENT_QUOTES,'UTF-8') ?>">
                </div>
                <div class="form-group form-full"><label class="form-label">Message envoye</label><textarea name="message_sent" class="form-control"><?= htmlspecialchars($conv['message_sent']??'',ENT_QUOTES,'UTF-8') ?></textarea></div>
                <div class="form-group form-full"><label class="form-label">Reponse recue</label><textarea name="response_received" class="form-control"><?= htmlspecialchars($conv['response_received']??'',ENT_QUOTES,'UTF-8') ?></textarea></div>
                <div class="form-group form-full"><label class="form-label">Prochaine action</label><textarea name="next_action" class="form-control"><?= htmlspecialchars($conv['next_action']??'',ENT_QUOTES,'UTF-8') ?></textarea></div>
                <div class="form-group form-full"><label class="form-label">Commentaire</label><textarea name="comment" class="form-control"><?= htmlspecialchars($conv['comment']??'',ENT_QUOTES,'UTF-8') ?></textarea></div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="/gtm-tracker/conversations/view.php?id=<?= $id ?>" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>