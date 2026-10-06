<?php
$pageTitle = 'Détail de la demande d\'aide';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$uid = $_SESSION['user_id'];
$isAdm = isAdmin();
$id = (int)($_GET['id'] ?? 0);

// Récupérer la demande
$stmt = $db->prepare("
    SELECT hr.*, 
           u.name as author_name, u.role as author_role, u.email as author_email,
           a.title as action_title, a.status as action_status,
           p.name as prospect_name, p.company as prospect_company
    FROM help_requests hr
    JOIN users u ON u.id = hr.user_id
    LEFT JOIN actions a ON a.id = hr.action_id
    LEFT JOIN prospects p ON p.id = hr.prospect_id
    WHERE hr.id = ?
");
$stmt->execute([$id]);
$req = $stmt->fetch();

if (!$req) {
    echo "<div class='alert alert-error'>Demande d'aide introuvable.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$canManage = ($isAdm || $req['user_id'] == $uid);

// Traitement POST : Nouveau conseil/commentaire ou changement de statut
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $postAction = $_POST['form_action'] ?? '';

    // 1. Clôturer / Marquer comme résolu
    if ($postAction === 'toggle_status' && $canManage) {
        $newStatus = ($req['status'] === 'Ouvert') ? 'Resolu' : 'Ouvert';
        $db->prepare("UPDATE help_requests SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$newStatus, $id]);
        
        // Si résolu et qu'une action était liée, remettre l'action en 'En cours' si elle était 'Bloquee'
        if ($newStatus === 'Resolu' && !empty($req['action_id'])) {
            $db->prepare("UPDATE actions SET status = 'En cours', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'Bloquee'")->execute([$req['action_id']]);
        }
        
        logActivity($uid, 'statut_sos', "Demande SOS #$id passée à: $newStatus");
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Statut mis à jour : " . ($newStatus==='Resolu' ? 'Problème marqué comme résolu ! 🎉' : 'Demande réouverte.')];
        header("Location: /gtm-tracker/help/view.php?id=$id");
        exit;
    }

    // 2. Ajouter un conseil ou une réponse
    if ($postAction === 'add_comment') {
        $comment = trim($_POST['comment'] ?? '');
        if (!empty($comment)) {
            $db->prepare("INSERT INTO help_comments (request_id, user_id, comment) VALUES (?, ?, ?)")->execute([$id, $uid, $comment]);
            logActivity($uid, 'reponse_sos', "Conseil apporté sur le SOS #$id");
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Votre conseil a été envoyé à l'équipe !"];
            header("Location: /gtm-tracker/help/view.php?id=$id");
            exit;
        }
    }
}

// Récupérer les conseils / commentaires
$stmtComm = $db->prepare("
    SELECT hc.*, u.name as commenter_name, u.role as commenter_role 
    FROM help_comments hc
    JOIN users u ON u.id = hc.user_id
    WHERE hc.request_id = ?
    ORDER BY hc.created_at ASC
");
$stmtComm->execute([$id]);
$comments = $stmtComm->fetchAll();
?>

<div class="page-header">
    <div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
            <?php if ($req['status'] === 'Ouvert'): ?>
                <span class="badge badge-warning" style="font-size:13px;padding:4px 10px">⚠️ Bloqué / Besoin d'aide</span>
            <?php else: ?>
                <span class="badge badge-success" style="font-size:13px;padding:4px 10px">✅ Problème Résolu</span>
            <?php endif; ?>
            <span style="font-size:12.5px;color:var(--text-muted)">Publié le <?= date('d/m/Y à H:i', strtotime($req['created_at'])) ?></span>
        </div>
        <h2 class="page-header-title"><?= htmlspecialchars($req['title'], ENT_QUOTES, 'UTF-8') ?></h2>
    </div>
    <div style="display:flex;gap:10px;align-items:center">
        <?php if ($canManage): ?>
        <form method="POST" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="form_action" value="toggle_status">
            <?php if ($req['status'] === 'Ouvert'): ?>
                <button type="submit" class="btn btn-success">
                    ✅ Marquer comme Débloqué / Résolu
                </button>
            <?php else: ?>
                <button type="submit" class="btn btn-secondary">
                    🔄 Réouvrir la demande
                </button>
            <?php endif; ?>
        </form>
        <?php endif; ?>
        <a href="/gtm-tracker/help/index.php" class="btn btn-secondary">Retour à la liste</a>
    </div>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="grid-2" style="margin-bottom:24px">
    <!-- Message du collaborateur -->
    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <span class="card-title">Description du blocage</span>
            <div style="display:flex;align-items:center;gap:8px">
                <div class="user-avatar" style="width:28px;height:28px;font-size:11px">
                    <?= strtoupper(substr($req['author_name'], 0, 1)) ?>
                </div>
                <div>
                    <strong style="font-size:13px"><?= htmlspecialchars($req['author_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span style="font-size:11px;color:var(--text-muted)">(<?= $req['author_role'] === 'admin' ? 'Admin' : 'Collaborateur' ?>)</span>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div style="background:var(--bg-base);padding:16px;border-radius:var(--radius-sm);border:1px solid var(--border);font-size:14px;line-height:1.6;white-space:pre-wrap;color:var(--text-main)"><?= htmlspecialchars($req['message'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>

    <!-- Contexte lié (Action & Prospect) -->
    <div class="card">
        <div class="card-header"><span class="card-title">Contexte lié</span></div>
        <div class="card-body">
            <div style="display:flex;flex-direction:column;gap:14px">
                <div style="background:var(--bg-base);padding:12px;border-radius:var(--radius-sm);border:1px solid var(--border)">
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:600">Action concernée</div>
                    <?php if (!empty($req['action_title'])): ?>
                        <div style="margin-top:4px">
                            <a href="/gtm-tracker/actions/view.php?id=<?= $req['action_id'] ?>" style="font-weight:600;font-size:13.5px">
                                🎯 <?= htmlspecialchars($req['action_title'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                            <div style="margin-top:4px">
                                <span class="badge <?= statusBadge($req['action_status']) ?>"><?= htmlspecialchars($req['action_status'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="color:var(--text-dim);font-size:13px;margin-top:2px">Aucune action spécifique liée</div>
                    <?php endif; ?>
                </div>

                <div style="background:var(--bg-base);padding:12px;border-radius:var(--radius-sm);border:1px solid var(--border)">
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:600">Prospect concerné</div>
                    <?php if (!empty($req['prospect_name'])): ?>
                        <div style="margin-top:4px">
                            <a href="/gtm-tracker/prospects/view.php?id=<?= $req['prospect_id'] ?>" style="font-weight:600;font-size:13.5px">
                                👤 <?= htmlspecialchars($req['prospect_name'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($req['prospect_company'])): ?>
                                    <span style="font-weight:normal;color:var(--text-muted)">— <?= htmlspecialchars($req['prospect_company'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <div style="color:var(--text-dim);font-size:13px;margin-top:2px">Aucun prospect spécifique lié</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Espace d'entraide collective / Conseils apportés -->
<div class="card">
    <div class="card-header">
        <span class="card-title">💡 Conseils et Solutions de l'Équipe (<?= count($comments) ?>)</span>
    </div>
    <div class="card-body">
        <?php if (empty($comments)): ?>
            <div class="empty-state" style="padding:24px">
                <p>Aucun conseil proposé pour l'instant. Soyez le premier à débloquer votre collègue !</p>
            </div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:16px;margin-bottom:24px">
                <?php foreach ($comments as $c): ?>
                <div style="background:var(--bg-base);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                        <div style="display:flex;align-items:center;gap:8px">
                            <div class="user-avatar" style="width:26px;height:26px;font-size:11px">
                                <?= strtoupper(substr($c['commenter_name'], 0, 1)) ?>
                            </div>
                            <strong style="font-size:13px"><?= htmlspecialchars($c['commenter_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <span class="badge <?= $c['commenter_role']==='admin'?'badge-accent':'badge-gray' ?>" style="font-size:10.5px">
                                <?= $c['commenter_role']==='admin'?'👑 Admin':'👤 Équipe' ?>
                            </span>
                        </div>
                        <span style="font-size:11.5px;color:var(--text-dim)"><?= date('d/m/Y à H:i', strtotime($c['created_at'])) ?></span>
                    </div>
                    <div style="font-size:13.5px;line-height:1.5;color:var(--text-main);white-space:pre-wrap"><?= htmlspecialchars($c['comment'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire de réponse -->
        <div style="border-top:1px solid var(--border);padding-top:20px">
            <h4 style="margin-bottom:10px;font-size:14px">Donner un conseil ou proposer une idée pour débloquer :</h4>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="form_action" value="add_comment">
                <div class="form-group" style="margin-bottom:12px">
                    <textarea name="comment" class="form-control" rows="3" placeholder="Ex: Essaie plutôt de relancer par email avec cette accroche... / On peut faire le point ensemble à 14h..." required></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">
                    💬 Envoyer mon conseil
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
