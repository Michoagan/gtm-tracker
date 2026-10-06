<?php
require_once __DIR__ . '/../auth/auth.php';
requireAdmin();
$pageTitle = 'Nouvelle action';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$uid = $_SESSION['user_id'];
$isAdm = isAdmin();
$strategies = $db->query("SELECT id,name FROM strategies ORDER BY name")->fetchAll();
$channels   = $db->query("SELECT id,name FROM channels ORDER BY name")->fetchAll();
$allUsers   = $isAdm ? array_values(getMembers()) : [];
$assignedToPre = isset($_GET["user_id"]) ? (int)$_GET["user_id"] : $uid;
// Only show own prospects unless admin
if ($isAdm) {
    $prospects = $db->query("SELECT id,name,company FROM prospects ORDER BY name")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id,name,company FROM prospects WHERE assigned_to=? ORDER BY name");
    $stmt->execute([$uid]);
    $prospects = $stmt->fetchAll();
}
$statuses   = ['A faire','En cours','Terminee','Bloquee'];
$priorities = ['Basse','Normale','Haute','Urgente'];
$preProspect = (int)($_GET['prospect_id'] ?? 0);
?>
<div class="page-header">
    <div>
        <h2 class="page-header-title">Nouvelle action</h2>
        <p class="page-header-sub">Enregistrez une action GTM</p>
    </div>
    <a href="/gtm-tracker/actions/index.php" class="btn btn-secondary">Retour</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/gtm-tracker/actions/store.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="form-grid">
                <div class="form-group form-full">
                    <label class="form-label">Titre de l'action / Mission *</label>
                    <input type="text" name="title" class="form-control" placeholder="Ex: Contacter 15 prospects sur les reseaux sociaux et fixer 5 rendez-vous" required>
                </div>
                <?php if ($isAdm): ?>
                <div class="form-group form-full" style="background:rgba(99,102,241,0.06);padding:14px;border-radius:var(--radius-sm);border:1px solid rgba(99,102,241,0.2)">
                    <label class="form-label" style="color:var(--primary);font-weight:600">
                        ðŸ‘¤ Attribuer cette action au collaborateur *
                    </label>
                    <select name="user_id" class="form-control" style="font-weight:600">
                        <?php foreach($allUsers as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($assignedToPre == $u['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?> (<?= $u['role'] === 'admin' ? 'Admin' : 'Collaborateur' ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint">En tant qu'administrateur, vous pouvez assigner cette tÃ¢che Ã  n'importe quel membre de l'Ã©quipe.</span>
                </div>
                <?php else: ?>
                <input type="hidden" name="user_id" value="<?= $uid ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label class="form-label">Prospect liÃ©</label>
                    <select name="prospect_id" class="form-control">
                        <option value="">-- Aucun prospect --</option>
                        <?php foreach($prospects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $preProspect==$p['id']?'selected':'' ?>>
                            <?= htmlspecialchars($p['name'].(!empty($p['company'])?' ('.$p['company'].')':''),ENT_QUOTES,'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint">Ou <a href="/gtm-tracker/prospects/create.php">crÃ©er un nouveau prospect</a></span>
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
                        <option value="<?= $st ?>" <?= $st==='A faire'?'selected':'' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Priorite</label>
                    <select name="priority" class="form-control">
                        <?php foreach($priorities as $p): ?>
                        <option value="<?= $p ?>" <?= $p==='Normale'?'selected':'' ?>><?= $p ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Date de l'action *</label>
                    <input type="date" name="action_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group form-full">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" placeholder="Decrivez l'action en detail..."></textarea>
                </div>
                <div class="form-group form-full">
                    <label class="form-label">Resultat</label>
                    <textarea name="result" class="form-control" placeholder="Ex: 5 personnes ont repondu, 2 rdv obtenus..."></textarea>
                </div>
                <div class="form-group form-full">
                    <label class="form-label">Commentaire</label>
                    <textarea name="comment" class="form-control" placeholder="Notes supplementaires..."></textarea>
                </div>
                <div class="form-group form-full">
                    <label class="form-label">Piece jointe (PNG, JPG, PDF)</label>
                    <input type="file" name="attachment" class="form-control" accept=".png,.jpg,.jpeg,.pdf">
                    <span class="form-hint">Taille max: 5 Mo</span>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enregistrer l'action</button>
                <a href="/gtm-tracker/actions/index.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
