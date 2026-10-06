<?php
$pageTitle = 'Demander de l\'aide (SOS)';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$uid = $_SESSION['user_id'];
$isAdm = isAdmin();

// Paramètres pré-remplis
$preActionId   = (int)($_GET['action_id'] ?? 0);
$preProspectId = (int)($_GET['prospect_id'] ?? 0);

// Traitement POST
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $title      = trim($_POST['title'] ?? '');
    $message    = trim($_POST['message'] ?? '');
    $actionId   = !empty($_POST['action_id'])   ? (int)$_POST['action_id']   : null;
    $prospectId = !empty($_POST['prospect_id']) ? (int)$_POST['prospect_id'] : null;

    if (empty($title))   $errors[] = "Le sujet ou le titre du problème est obligatoire.";
    if (empty($message)) $errors[] = "Veuillez expliquer ce qui vous bloque.";

    if (empty($errors)) {
        // Enregistrer la demande d'aide
        $stmt = $db->prepare("INSERT INTO help_requests (user_id, action_id, prospect_id, title, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$uid, $actionId, $prospectId, $title, $message]);
        $reqId = $db->lastInsertId();

        // Optionnel : passer l'action en statut 'Bloquee' si une action était ciblée
        if ($actionId) {
            $db->prepare("UPDATE actions SET status = 'Bloquee', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$actionId]);
        }

        logActivity($uid, 'demande_aide_sos', "SOS: $title (ID: $reqId)");
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Votre appel à l\'aide a été publié avec succès. Vos collègues et l\'administrateur ont été alertés !'];
        header("Location: /gtm-tracker/help/view.php?id=$reqId");
        exit;
    }
}

// Récupérer les actions du collaborateur (ou toutes si admin)
if ($isAdm) {
    $myActions = $db->query("SELECT id, title FROM actions ORDER BY action_date DESC LIMIT 30")->fetchAll();
    $myProspects = $db->query("SELECT id, name, company FROM prospects ORDER BY name ASC")->fetchAll();
} else {
    $stmtAct = $db->prepare("SELECT id, title FROM actions WHERE user_id = ? ORDER BY action_date DESC LIMIT 30");
    $stmtAct->execute([$uid]);
    $myActions = $stmtAct->fetchAll();

    $stmtPros = $db->prepare("SELECT id, name, company FROM prospects WHERE assigned_to = ? ORDER BY name ASC");
    $stmtPros->execute([$uid]);
    $myProspects = $stmtPros->fetchAll();
}
?>

<div class="page-header">
    <div>
        <h2 class="page-header-title">🆘 Signaler un blocage / Demander de l'aide</h2>
        <p class="page-header-sub">Bloqué sur une objection client, un canal ou une mission ? Partagez-le avec l'équipe pour trouver une solution ensemble.</p>
    </div>
    <a href="/gtm-tracker/help/index.php" class="btn btn-secondary">Retour à l'entraide</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
<?php endif; ?>

<div class="card" style="max-width:800px;margin:0 auto">
    <div class="card-header">
        <span class="card-title">Formulaire de demande de coup de main</span>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            
            <div class="form-group" style="margin-bottom:18px">
                <label class="form-label">Sujet de votre blocage *</label>
                <input type="text" name="title" class="form-control" placeholder="Ex: Prospect ne répond pas après 3 relances LinkedIn / Objection sur le prix..." required autofocus>
                <span class="form-hint">Soyez clair et précis pour que l'équipe comprenne immédiatement l'enjeu.</span>
            </div>

            <div class="grid-2" style="margin-bottom:18px">
                <div class="form-group">
                    <label class="form-label">Action liée (optionnel)</label>
                    <select name="action_id" class="form-control">
                        <option value="">-- Aucune action spécifique --</option>
                        <?php foreach ($myActions as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= ($preActionId == $a['id']) ? 'selected' : '' ?>>
                            🎯 <?= htmlspecialchars(substr($a['title'], 0, 45), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint">Si vous sélectionnez une action, son statut passera automatiquement en "Bloquée".</span>
                </div>

                <div class="form-group">
                    <label class="form-label">Prospect lié (optionnel)</label>
                    <select name="prospect_id" class="form-control">
                        <option value="">-- Aucun prospect spécifique --</option>
                        <?php foreach ($myProspects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($preProspectId == $p['id']) ? 'selected' : '' ?>>
                            👤 <?= htmlspecialchars($p['name'] . (!empty($p['company']) ? ' (' . $p['company'] . ')' : ''), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:24px">
                <label class="form-label">Description détaillée du problème *</label>
                <textarea name="message" class="form-control" rows="6" placeholder="Expliquez la situation : qu'avez-vous déjà tenté ? Quel message avez-vous envoyé ? Quelle est la réaction du prospect ou la difficulté rencontrée ?" required></textarea>
            </div>

            <div class="form-actions" style="display:flex;gap:12px;justify-content:flex-end">
                <a href="/gtm-tracker/help/index.php" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-warning" style="font-weight:600">
                    🚀 Publier l'appel à l'aide
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
