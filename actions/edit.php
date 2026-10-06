<?php
$pageTitle = 'Modifier action';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$uid = $_SESSION['user_id'];
$isAdm = isAdmin();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM actions WHERE id=?");
$stmt->execute([$id]); $action = $stmt->fetch();
if (!$action || (!$isAdm && $action['user_id']!=$uid)) { header('Location: /gtm-tracker/actions/index.php'); exit; }
$strategies = $db->query("SELECT id,name FROM strategies ORDER BY name")->fetchAll();
$channels   = $db->query("SELECT id,name FROM channels ORDER BY name")->fetchAll();
$prospects  = $db->query("SELECT id,name,company FROM prospects ORDER BY name")->fetchAll();
$allUsers   = $isAdm ? array_values(getMembers()) : [];
$statuses   = ['A faire','En cours','Terminee','Bloquee'];
$priorities = ['Basse','Normale','Haute','Urgente'];
?>
<div class="page-header">
    <h2 class="page-header-title">Modifier l'action</h2>
    <a href="/gtm-tracker/actions/view.php?id=<?= $id ?>" class="btn btn-secondary">Retour</a>
</div>
<div class="card">
    <div class="card-body">
        <form method="POST" action="/gtm-tracker/actions/update.php">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="form-grid">
                <div class="form-group form-full">
                    <label class="form-label">Titre de l'action *</label>
                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($action['title'],ENT_QUOTES,'UTF-8') ?>" required>
                </div>
                <?php if ($isAdm): ?>
                <div class="form-group form-full" style="background:rgba(99,102,241,0.06);padding:14px;border-radius:var(--radius-sm);border:1px solid rgba(99,102,241,0.2)">
                    <label class="form-label" style="color:var(--primary);font-weight:600">
                        👤 Collaborateur assigné *
                    </label>
                    <select name="user_id" class="form-control" style="font-weight:600">
                        <?php foreach($allUsers as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($action['user_id'] == $u['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?> (<?= $u['role'] === 'admin' ? 'Admin' : 'Collaborateur' ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <label class="form-label">Prospect lié</label>
                    <select name="prospect_id" class="form-control">
                        <option value="">-- Aucun prospect --</option>
                        <?php foreach($prospects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($action['prospect_id']==$p['id'])?'selected':'' ?>>
                            <?= htmlspecialchars($p['name'].(!empty($p['company'])?' ('.$p['company'].')':''),ENT_QUOTES,'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Strategie</label>
                    <select name="strategy_id" class="form-control">
                        <option value="">-- Choisir --</option>
                        <?php foreach($strategies as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $action['strategy_id']==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['name'],ENT_QUOTES,'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Canal</label>
                    <select name="channel_id" class="form-control">
                        <option value="">-- Choisir --</option>
                        <?php foreach($channels as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $action['channel_id']==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name'],ENT_QUOTES,'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Statut</label>
                    <select name="status" class="form-control">
                        <?php foreach($statuses as $st): ?>
                        <option value="<?= $st ?>" <?= $action['status']===$st?'selected':'' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Priorite</label>
                    <select name="priority" class="form-control">
                        <?php foreach($priorities as $p): ?>
                        <option value="<?= $p ?>" <?= $action['priority']===$p?'selected':'' ?>><?= $p ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" name="action_date" class="form-control" value="<?= htmlspecialchars($action['action_date'],ENT_QUOTES,'UTF-8') ?>">
                </div>
                <div class="form-group form-full">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control"><?= htmlspecialchars($action['description']??'',ENT_QUOTES,'UTF-8') ?></textarea>
                </div>
                <div class="form-group form-full">
                    <label class="form-label">Resultat</label>
                    <textarea name="result" class="form-control"><?= htmlspecialchars($action['result']??'',ENT_QUOTES,'UTF-8') ?></textarea>
                </div>
                <div class="form-group form-full">
                    <label class="form-label">Commentaire</label>
                    <textarea name="comment" class="form-control"><?= htmlspecialchars($action['comment']??'',ENT_QUOTES,'UTF-8') ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="/gtm-tracker/actions/view.php?id=<?= $id ?>" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
