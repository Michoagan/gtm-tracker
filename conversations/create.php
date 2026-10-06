<?php
$pageTitle = 'Nouvelle conversation';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$strategies = $db->query("SELECT id,name FROM strategies ORDER BY name")->fetchAll();
$channels   = $db->query("SELECT id,name FROM channels ORDER BY name")->fetchAll();
$prospects  = $db->query("SELECT id,name,company FROM prospects ORDER BY name")->fetchAll();
$statuses   = ['Nouveau','Contacte','En discussion','Interesse','Rendez-vous','Converti','Perdu','A relancer'];
?>
<div class="page-header">
    <h2 class="page-header-title">Nouvelle conversation</h2>
    <a href="/gtm-tracker/conversations/index.php" class="btn btn-secondary">Retour</a>
</div>
<div class="card">
    <div class="card-body">
        <form method="POST" action="/gtm-tracker/conversations/store.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Prospect / Contact</label>
                    <select name="prospect_id" class="form-control">
                        <option value="">-- Choisir ou creer --</option>
                        <?php foreach($prospects as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name'].(!empty($p['company'])?' ('.(string)$p['company'].')':''),ENT_QUOTES,'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint">Ou <a href="/gtm-tracker/prospects/create.php">creer un nouveau prospect</a></span>
                </div>
                <div class="form-group">
                    <label class="form-label">Strategie</label>
                    <select name="strategy_id" class="form-control">
                        <option value="">-- Choisir --</option>
                        <?php foreach($strategies as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name'],ENT_QUOTES,'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Canal</label>
                    <select name="channel_id" class="form-control">
                        <option value="">-- Choisir --</option>
                        <?php foreach($channels as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name'],ENT_QUOTES,'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Statut</label>
                    <select name="status" class="form-control">
                        <?php foreach($statuses as $st): ?>
                        <option value="<?= $st ?>"><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Date de la conversation *</label>
                    <input type="date" name="conversation_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group form-full">
                    <label class="form-label">Message envoye</label>
                    <textarea name="message_sent" class="form-control" placeholder="Contenu du message envoye..."></textarea>
                </div>
                <div class="form-group form-full">
                    <label class="form-label">Reponse recue</label>
                    <textarea name="response_received" class="form-control" placeholder="Reponse du prospect..."></textarea>
                </div>
                <div class="form-group form-full">
                    <label class="form-label">Prochaine action prevue</label>
                    <textarea name="next_action" class="form-control" placeholder="Ex: Rappeler la semaine prochaine, envoyer une proposition..."></textarea>
                </div>
                <div class="form-group form-full">
                    <label class="form-label">Commentaire</label>
                    <textarea name="comment" class="form-control" placeholder="Notes supplementaires..."></textarea>
                </div>
                <div class="form-group form-full">
                    <label class="form-label">Piece jointe (capture, PDF...)</label>
                    <input type="file" name="attachment" class="form-control" accept=".png,.jpg,.jpeg,.pdf">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="/gtm-tracker/conversations/index.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>