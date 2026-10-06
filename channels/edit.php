<?php
$pageTitle = 'Modifier canal';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();
$db = getDB();
$id = (int)($_GET['id']??0);
$chan = $db->prepare("SELECT * FROM channels WHERE id=?");
$chan->execute([$id]); $chan = $chan->fetch();
if (!$chan) { header('Location: /gtm-tracker/channels/index.php'); exit; }
?>
<div class="page-header">
    <h2 class="page-header-title">Modifier canal</h2>
    <a href="/gtm-tracker/channels/index.php" class="btn btn-secondary">Retour</a>
</div>
<div class="card">
    <div class="card-body">
        <form method="POST" action="/gtm-tracker/channels/update.php">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="form-group" style="max-width:400px">
                <label class="form-label">Nom *</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($chan['name'],ENT_QUOTES,'UTF-8') ?>" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>