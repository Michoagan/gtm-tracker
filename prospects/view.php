<?php
$pageTitle = 'Fiche prospect';
require_once __DIR__ . '/../includes/header.php';
$db    = getDB();
$uid   = $_SESSION['user_id'];
$isAdm = isAdmin();
$id    = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT p.*, u.name as assigned_name, ch.name as channel_name FROM prospects p LEFT JOIN users u ON u.id=p.assigned_to LEFT JOIN channels ch ON ch.id=p.channel_id WHERE p.id=?");
$stmt->execute([$id]);
$prospect = $stmt->fetch();
if (!$prospect || (!$isAdm && $prospect['assigned_to'] != $uid)) { 
    header('Location: /gtm-tracker/prospects/index.php'); 
    exit; 
}

// Traitement POST : Ajout rapide d'un échange ou mise à jour de relance
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $formAction = $_POST['form_action'] ?? '';

    // Mise à jour rapide du statut de relance / onboarding
    if ($formAction === 'update_followup') {
        $newFollowUp = $_POST['follow_up_status'] ?? 'A relancer';
        $newNextDate = !empty($_POST['next_followup_date']) ? $_POST['next_followup_date'] : null;
        $newProspectStatus = $_POST['prospect_status'] ?? $prospect['status'];
        
        $db->prepare("UPDATE prospects SET follow_up_status=?, next_followup_date=?, status=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")
           ->execute([$newFollowUp, $newNextDate, $newProspectStatus, $id]);
        
        logActivity($uid, 'maj_relance_prospect', "Prospect ID:$id ($newFollowUp)");
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Statut de relance mis à jour !'];
        header("Location: /gtm-tracker/prospects/view.php?id=$id");
        exit;
    }

    // Ajout d'un nouvel échange / conversation directement depuis la fiche
    if ($formAction === 'add_conversation') {
        $cDate    = $_POST['conversation_date'] ?? date('Y-m-d');
        $cMsg     = trim($_POST['message_sent'] ?? '');
        $cResp    = trim($_POST['response_received'] ?? '');
        $cNext    = trim($_POST['next_action'] ?? '');
        $cStatus  = $_POST['conv_status'] ?? $prospect['status'];
        $cChannel = !empty($_POST['channel_id']) ? (int)$_POST['channel_id'] : $prospect['channel_id'];

        if (!empty($cMsg) || !empty($cResp) || !empty($cNext)) {
            $stmtC = $db->prepare("INSERT INTO conversations (prospect_id, user_id, channel_id, status, conversation_date, message_sent, response_received, next_action) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtC->execute([$id, $uid, $cChannel, $cStatus, $cDate, $cMsg, $cResp, $cNext]);
            
            // Mettre à jour aussi le prospect
            $db->prepare("UPDATE prospects SET status=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$cStatus, $id]);

            logActivity($uid, 'ajout_echange_prospect', "Échange noté pour le prospect ID:$id");
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Échange enregistré dans l\'historique du prospect !'];
            header("Location: /gtm-tracker/prospects/view.php?id=$id");
            exit;
        }
    }
}

// Actions liées à ce prospect
$actions = $db->prepare("SELECT a.*, s.name as strategy_name, ch.name as channel_name, u.name as user_name FROM actions a LEFT JOIN strategies s ON s.id=a.strategy_id LEFT JOIN channels ch ON ch.id=a.channel_id LEFT JOIN users u ON u.id=a.user_id WHERE a.prospect_id=? ORDER BY a.action_date DESC");
$actions->execute([$id]);
$actions = $actions->fetchAll();

// Conversations liées à ce prospect
$convs = $db->prepare("SELECT c.*, ch.name as channel_name, s.name as strategy_name, u.name as user_name FROM conversations c LEFT JOIN channels ch ON ch.id=c.channel_id LEFT JOIN strategies s ON s.id=c.strategy_id LEFT JOIN users u ON u.id=c.user_id WHERE c.prospect_id=? ORDER BY c.conversation_date DESC, c.id DESC");
$convs->execute([$id]);
$convs = $convs->fetchAll();

$channels = $db->query("SELECT id, name FROM channels ORDER BY name")->fetchAll();
$statuses = ['Nouveau','Contacte','En discussion','Interesse','Rendez-vous','Converti','Perdu','A relancer'];
$followUpStatuses = ['A relancer', 'En cours de discussion', 'Rendez-vous planifie', 'A modifier / En attente', 'Relance arretee / Perdu', 'Onboarde / Signe'];
?>

<div class="page-header">
    <div>
        <h2 class="page-header-title"><?= htmlspecialchars($prospect['name'], ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="page-header-sub">
            <?php if (!empty($prospect['company'])): ?>
                <strong><?= htmlspecialchars($prospect['company'], ENT_QUOTES, 'UTF-8') ?></strong> &nbsp;·&nbsp;
            <?php endif; ?>
            <span class="badge <?= statusBadge($prospect['status']) ?>"><?= htmlspecialchars($prospect['status'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php if (!empty($prospect['follow_up_status'])): ?>
                &nbsp;·&nbsp; <span class="badge badge-accent">🔄 <?= htmlspecialchars($prospect['follow_up_status'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php if ($isAdm): ?>
        <a href="/gtm-tracker/actions/create.php?prospect_id=<?= $id ?>" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nouvelle action
        </a>
        <?php endif; ?>
        <a href="/gtm-tracker/help/create.php?prospect_id=<?= $id ?>" class="btn btn-warning" title="Bloqué avec ce prospect ? Demandez de l'aide à l'équipe">🆘 Demander de l'aide</a>
        <?php if ($isAdm || $prospect['assigned_to'] == $uid): ?>
        <a href="/gtm-tracker/prospects/edit.php?id=<?= $id ?>" class="btn btn-secondary">Modifier prospect</a>
        <?php endif; ?>
        <a href="/gtm-tracker/prospects/index.php" class="btn btn-secondary">Retour</a>
    </div>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<!-- Panneau de Pilotage Relance / Onboarding -->
<div class="card" style="margin-bottom:24px;border-left:4px solid #f59e0b">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span class="card-title">🔄 Suivi &amp; Boutons de Relance ("Onboarding")</span>
        <span style="font-size:12px;color:var(--text-muted)">Gérez l'état d'avancement du prospect</span>
    </div>
    <div class="card-body">
        <form method="POST" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="form_action" value="update_followup">
            
            <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0">
                <label class="form-label">État de la relance</label>
                <select name="follow_up_status" class="form-control" style="font-weight:600">
                    <?php foreach ($followUpStatuses as $fs): ?>
                    <option value="<?= $fs ?>" <?= ($prospect['follow_up_status'] ?? 'A relancer') === $fs ? 'selected' : '' ?>><?= $fs ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="flex:1;min-width:180px;margin-bottom:0">
                <label class="form-label">Prochaine relance le</label>
                <input type="date" name="next_followup_date" class="form-control" value="<?= htmlspecialchars($prospect['next_followup_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-group" style="flex:1;min-width:180px;margin-bottom:0">
                <label class="form-label">Statut du prospect</label>
                <select name="prospect_status" class="form-control">
                    <?php foreach ($statuses as $st): ?>
                    <option value="<?= $st ?>" <?= $prospect['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-warning" style="font-weight:600;min-height:42px">
                💾 Mettre à jour la relance
            </button>
        </form>
    </div>
</div>

<!-- Fiche prospect Informations -->
<div class="card" style="margin-bottom:24px">
    <div class="card-header"><span class="card-title">Informations du prospect</span></div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item"><label>Nom</label><p><?= htmlspecialchars($prospect['name'], ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="detail-item"><label>Société</label><p><?= htmlspecialchars($prospect['company'] ?? '—', ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="detail-item"><label>Coordonnées</label><p><?= htmlspecialchars($prospect['contact'] ?? '—', ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="detail-item"><label>Canal d'origine</label><p><?= htmlspecialchars($prospect['channel_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="detail-item"><label>Statut</label><p><span class="badge <?= statusBadge($prospect['status']) ?>"><?= htmlspecialchars($prospect['status'], ENT_QUOTES, 'UTF-8') ?></span></p></div>
            <div class="detail-item"><label>Assigné à</label><p><?= htmlspecialchars($prospect['assigned_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="detail-item"><label>Ajouté le</label><p><?= date('d/m/Y', strtotime($prospect['created_at'])) ?></p></div>
        </div>
    </div>
</div>

<!-- Section Formulaire Intégré pour Noter un Échange (Conversation) -->
<div class="card" style="margin-bottom:24px;border:1px solid rgba(56, 189, 248, 0.3)">
    <div class="card-header" style="background:rgba(56, 189, 248, 0.05)">
        <span class="card-title" style="color:#38bdf8">💬 Noter un nouvel échange avec ce prospect</span>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="form_action" value="add_conversation">
            
            <div class="form-grid" style="margin-bottom:16px">
                <div class="form-group">
                    <label class="form-label">Date de l'échange</label>
                    <input type="date" name="conversation_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Canal utilisé</label>
                    <select name="channel_id" class="form-control">
                        <?php foreach ($channels as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $prospect['channel_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Nouveau statut</label>
                    <select name="conv_status" class="form-control">
                        <?php foreach ($statuses as $st): ?>
                        <option value="<?= $st ?>" <?= $prospect['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:14px">
                <label class="form-label">Message envoyé / Notes de l'échange</label>
                <textarea name="message_sent" class="form-control" rows="2" placeholder="Ce qui a été dit ou envoyé au prospect..." required></textarea>
            </div>

            <div class="grid-2" style="margin-bottom:16px">
                <div class="form-group">
                    <label class="form-label">Réponse reçue (objection, accord, question...)</label>
                    <textarea name="response_received" class="form-control" rows="2" placeholder="Réponse du prospect..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Prochaine action / Suite à donner</label>
                    <textarea name="next_action" class="form-control" rows="2" placeholder="Ex: Envoyer proposition, caler un rappel..."></textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="font-weight:600">
                ➕ Enregistrer cet échange
            </button>
        </form>
    </div>
</div>

<div class="grid-2">
    <!-- Historique des Conversations -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Historique des Échanges &amp; Conversations (<?= count($convs) ?>)</span>
        </div>
        <?php if (empty($convs)): ?>
            <div class="empty-state" style="padding:24px">
                <p>Aucun échange consigné pour le moment. Utilisez le formulaire ci-dessus pour noter le premier contact.</p>
            </div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:14px">
                <?php foreach ($convs as $c): ?>
                <div style="background:var(--bg-base);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                        <div style="display:flex;align-items:center;gap:8px">
                            <span class="badge badge-gray"><?= htmlspecialchars($c['channel_name'] ?? 'Canal', ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="badge <?= statusBadge($c['status']) ?>"><?= htmlspecialchars($c['status'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <span style="font-size:12px;color:var(--text-dim)"><?= date('d/m/Y', strtotime($c['conversation_date'])) ?></span>
                    </div>
                    <?php if (!empty($c['message_sent'])): ?>
                        <div style="font-size:13px;line-height:1.5;color:var(--text-main);margin-bottom:6px">
                            <strong>Envoyé :</strong> <?= nl2br(htmlspecialchars($c['message_sent'], ENT_QUOTES, 'UTF-8')) ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($c['response_received'])): ?>
                        <div style="font-size:13px;line-height:1.5;color:var(--success);margin-bottom:6px">
                            <strong>Réponse :</strong> <?= nl2br(htmlspecialchars($c['response_received'], ENT_QUOTES, 'UTF-8')) ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($c['next_action'])): ?>
                        <div style="font-size:12px;background:rgba(245, 158, 11, 0.1);padding:6px 10px;border-radius:4px;color:#fbbf24;margin-top:6px">
                            ⏰ <strong>Prochaine étape :</strong> <?= htmlspecialchars($c['next_action'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Actions liées à ce prospect -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Actions GTM liées (<?= count($actions) ?>)</span>
            <?php if ($isAdm): ?>
                <a href="/gtm-tracker/actions/create.php?prospect_id=<?= $id ?>" class="btn btn-primary btn-sm">+ Action</a>
            <?php endif; ?>
        </div>
        <?php if (empty($actions)): ?>
            <div class="empty-state" style="padding:24px">
                <p>Aucune action liée à ce prospect.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <?php if ($isAdm): ?><th>Par</th><?php endif; ?>
                            <th>Titre</th>
                            <th>Statut</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($actions as $a): ?>
                    <tr>
                        <?php if ($isAdm): ?><td style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($a['user_name'], ENT_QUOTES, 'UTF-8') ?></td><?php endif; ?>
                        <td>
                            <a href="/gtm-tracker/actions/view.php?id=<?= $a['id'] ?>" style="font-weight:500">
                                <?= htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </td>
                        <td><span class="badge <?= statusBadge($a['status']) ?>"><?= htmlspecialchars($a['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td style="font-size:12px"><?= htmlspecialchars($a['action_date'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
