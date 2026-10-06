<?php
$pageTitle = 'Nouvelle strategie';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();
?>
<div class="page-header">
    <h2 class="page-header-title">Nouvelle strategie</h2>
    <a href="/gtm-tracker/strategies/index.php" class="btn btn-secondary">Retour</a>
</div>
<div class="card">
    <div class="card-body">
        <form method="POST" action="/gtm-tracker/strategies/store.php">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="form-grid">
                <div class="form-group form-full">
                    <label class="form-label">Nom *</label>
                    <input type="text" name="name" class="form-control" placeholder="Ex: Acquisition" required>
                </div>
                <div class="form-group form-full">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" placeholder="Decrivez la strategie..."></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="/gtm-tracker/strategies/index.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>