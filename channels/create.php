<?php
$pageTitle = 'Nouveau canal';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();
?>
<div class="page-header">
    <h2 class="page-header-title">Nouveau canal</h2>
    <a href="/gtm-tracker/channels/index.php" class="btn btn-secondary">Retour</a>
</div>
<div class="card">
    <div class="card-body">
        <form method="POST" action="/gtm-tracker/channels/store.php">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="form-group" style="max-width:400px">
                <label class="form-label">Nom du canal *</label>
                <input type="text" name="name" class="form-control" placeholder="Ex: LinkedIn" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="/gtm-tracker/channels/index.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>