<?php
$pageTitle = 'Modifier strategie';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();
$db = getDB();
$id = (int)($_GET['id']??0);
$strat = $db->prepare("SELECT * FROM strategies WHERE id=?");
$strat->execute([$id]); $strat = $strat->fetch();
if (!$strat) { header('Location: /gtm-tracker/strategies/index.php'); exit; }
?>
<div class="page-header">
    <h2 class="page-header-title">Modifier strategie</h2>
    <a href="/gtm-tracker/strategies/index.php" class="btn btn-secondary">Retour</a>
</div>
<div class="card">
    <div class="card-body">
        <form method="POST" action="/gtm-tracker/strategies/update.php">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="form-grid">
                <div class="form-group form-full">
                    <label class="form-label">Nom *</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($strat['name'],ENT_QUOTES,'UTF-8') ?>" required>
                </div>
                <div class="form-group form-full">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control"><?= htmlspecialchars($strat['description']??'',ENT_QUOTES,'UTF-8') ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>