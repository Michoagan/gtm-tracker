<?php
$pageTitle = 'Modifier prospect';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$uid = $_SESSION['user_id'];
$isAdm = isAdmin();
$id = (int)($_GET['id']??0);
$stmt = $db->prepare("SELECT * FROM prospects WHERE id=?");
$stmt->execute([$id]); $prospect = $stmt->fetch();
if (!$prospect || (!$isAdm && $prospect['assigned_to']!=$uid)) { header('Location: /gtm-tracker/prospects/index.php'); exit; }
$channels = $db->query("SELECT id,name FROM channels ORDER BY name")->fetchAll();
$users    = array_values(getMembers());
$statuses = ['Nouveau','Contacte','En discussion','Interesse','Rendez-vous','Converti','Perdu','A relancer'];
?>
<div class="page-header">
    <h2 class="page-header-title">Modifier prospect</h2>
    <a href="/gtm-tracker/prospects/index.php" class="btn btn-secondary">Retour</a>
</div>
<div class="card">
    <div class="card-body">
        <form method="POST" action="/gtm-tracker/prospects/update.php">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="form-grid">
                <div class="form-group"><label class="form-label">Nom *</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($prospect['name'],ENT_QUOTES,'UTF-8') ?>" required></div>
                <div class="form-group"><label class="form-label">Entreprise</label><input type="text" name="company" class="form-control" value="<?= htmlspecialchars($prospect['company']??'',ENT_QUOTES,'UTF-8') ?>"></div>
                <div class="form-group"><label class="form-label">Contact</label><input type="text" name="contact" class="form-control" value="<?= htmlspecialchars($prospect['contact']??'',ENT_QUOTES,'UTF-8') ?>"></div>
                <div class="form-group">
                    <label class="form-label">Canal</label>
                    <select name="channel_id" class="form-control">
                        <option value="">-- Choisir --</option>
                        <?php foreach($channels as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $prospect['channel_id']==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name'],ENT_QUOTES,'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Statut</label>
                    <select name="status" class="form-control">
                        <?php foreach($statuses as $st): ?>
                        <option value="<?= $st ?>" <?= $prospect['status']===$st?'selected':'' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if($isAdm): ?>
                <div class="form-group">
                    <label class="form-label">Assigne a</label>
                    <select name="assigned_to" class="form-control">
                        <?php foreach($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $prospect['assigned_to']==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['name'],ENT_QUOTES,'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="/gtm-tracker/prospects/index.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
