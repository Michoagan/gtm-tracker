<?php
$pageTitle = 'Nouveau prospect';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$channels = $db->query("SELECT id,name FROM channels ORDER BY name")->fetchAll();
$strategies = $db->query("SELECT id,name FROM strategies ORDER BY name")->fetchAll();
$users    = array_values(getMembers());
$statuses = ['Nouveau','Contacte','En discussion','Interesse','Rendez-vous','Converti','Perdu','A relancer'];
$followUpStatuses = ['A relancer', 'En cours de discussion', 'Rendez-vous planifie', 'A modifier / En attente', 'Relance arretee / Perdu', 'Onboarde / Signe'];
?>
<div class="page-header">
    <div>
        <h2 class="page-header-title">Nouveau prospect</h2>
        <p class="page-header-sub">Créez la fiche prospect et notez immédiatement vos premiers échanges ou relances</p>
    </div>
    <a href="/gtm-tracker/prospects/index.php" class="btn btn-secondary">Retour</a>
</div>
<div class="card" style="max-width:900px;margin:0 auto">
    <div class="card-body">
        <form method="POST" action="/gtm-tracker/prospects/store.php">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            
            <h3 style="font-size:15px;margin-bottom:16px;color:var(--primary);display:flex;align-items:center;gap:8px">
                👤 Informations du Prospect
            </h3>
            <div class="form-grid" style="margin-bottom:24px">
                <div class="form-group">
                    <label class="form-label">Nom du contact / Prospect *</label>
                    <input type="text" name="name" class="form-control" placeholder="Jean Dupont" required autofocus>
                </div>
                <div class="form-group">
                    <label class="form-label">Entreprise</label>
                    <input type="text" name="company" class="form-control" placeholder="Nom de l'entreprise">
                </div>
                <div class="form-group">
                    <label class="form-label">Coordonnées (Email / Tél / LinkedIn)</label>
                    <input type="text" name="contact" class="form-control" placeholder="jean@example.com, 06..., URL profil">
                </div>
                <div class="form-group">
                    <label class="form-label">Canal d'acquisition</label>
                    <select name="channel_id" class="form-control">
                        <option value="">-- Choisir --</option>
                        <?php foreach($channels as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name'],ENT_QUOTES,'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Statut du prospect</label>
                    <select name="status" class="form-control">
                        <?php foreach($statuses as $st): ?>
                        <option value="<?= $st ?>"><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if(isAdmin()): ?>
                <div class="form-group">
                    <label class="form-label">Assigné au collaborateur</label>
                    <select name="assigned_to" class="form-control">
                        <?php foreach($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $u['id']==$_SESSION['user_id']?'selected':'' ?>><?= htmlspecialchars($u['name'],ENT_QUOTES,'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <hr style="border:0;border-top:1px solid var(--border);margin:24px 0">

            <h3 style="font-size:15px;margin-bottom:16px;color:#f59e0b;display:flex;align-items:center;gap:8px">
                🔄 Suivi &amp; Relance (Onboarding)
            </h3>
            <div class="form-grid" style="margin-bottom:24px">
                <div class="form-group">
                    <label class="form-label">État du suivi / Relance</label>
                    <select name="follow_up_status" class="form-control">
                        <?php foreach($followUpStatuses as $fs): ?>
                        <option value="<?= $fs ?>"><?= $fs ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Date prévue de la prochaine relance</label>
                    <input type="date" name="next_followup_date" class="form-control" value="<?= date('Y-m-d', strtotime('+3 days')) ?>">
                </div>
                <div class="form-group form-full">
                    <label class="form-label">Prochaine action / consigne de relance</label>
                    <input type="text" name="next_action" class="form-control" placeholder="Ex: Envoyer proposition tarifaire, relancer par message vocal, caler démo...">
                </div>
            </div>

            <hr style="border:0;border-top:1px solid var(--border);margin:24px 0">

            <h3 style="font-size:15px;margin-bottom:16px;color:#38bdf8;display:flex;align-items:center;gap:8px">
                💬 Historique du premier échange (Conversation)
            </h3>
            <div class="form-grid">
                <div class="form-group form-full">
                    <label class="form-label">Message envoyé / Échange réalisé</label>
                    <textarea name="message_sent" class="form-control" rows="3" placeholder="Notes sur le premier contact, message envoyé, premier retour..."></textarea>
                </div>
                <div class="form-group form-full">
                    <label class="form-label">Réponse reçue du prospect (si déjà obtenue)</label>
                    <textarea name="response_received" class="form-control" rows="2" placeholder="Réponse ou objection du prospect..."></textarea>
                </div>
            </div>

            <div class="form-actions" style="margin-top:24px">
                <button type="submit" class="btn btn-primary">Enregistrer le prospect</button>
                <a href="/gtm-tracker/prospects/index.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

