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
        <button type="button" class="btn btn-primary" onclick="openModal('modal-conversation')" style="display:inline-flex;align-items:center;gap:6px">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            + Noter un échange
        </button>
        <button type="button" class="btn btn-warning" onclick="openModal('modal-followup')" style="display:inline-flex;align-items:center;gap:6px">
            🔄 Gérer la relance
        </button>
        <?php if ($isAdm): ?>
        <a href="/gtm-tracker/actions/create.php?prospect_id=<?= $id ?>" class="btn btn-secondary">
            + Action liée
        </a>
        <?php endif; ?>
        <a href="/gtm-tracker/help/create.php?prospect_id=<?= $id ?>" class="btn btn-secondary" title="Bloqué avec ce prospect ? Demandez de l'aide à l'équipe">🆘 SOS</a>
        <?php if ($isAdm || $prospect['assigned_to'] == $uid): ?>
        <a href="/gtm-tracker/prospects/edit.php?id=<?= $id ?>" class="btn btn-secondary">Modifier</a>
        <?php endif; ?>
        <a href="/gtm-tracker/prospects/index.php" class="btn btn-secondary">Retour</a>
    </div>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?>" style="margin-bottom:20px"><?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<!-- Fiche synthétique d'information prospect -->
<div class="card" style="margin-bottom:20px">
    <div class="card-body" style="padding:16px 20px">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px">
            <div style="display:flex;align-items:center;gap:22px;flex-wrap:wrap">
                <div>
                    <span style="font-size:11px;text-transform:uppercase;color:var(--text-dim);font-weight:600;display:block">Entreprise</span>
                    <strong style="color:var(--text-main);font-size:14px"><?= htmlspecialchars($prospect['company'] ?: 'Non renseigné', ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
                <div>
                    <span style="font-size:11px;text-transform:uppercase;color:var(--text-dim);font-weight:600;display:block">Contact</span>
                    <span style="color:var(--text-main);font-size:14px"><?= htmlspecialchars($prospect['contact'] ?: '—', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div>
                    <span style="font-size:11px;text-transform:uppercase;color:var(--text-dim);font-weight:600;display:block">Canal d'origine</span>
                    <span class="badge badge-gray"><?= htmlspecialchars($prospect['channel_name'] ?: 'Direct', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div>
                    <span style="font-size:11px;text-transform:uppercase;color:var(--text-dim);font-weight:600;display:block">Assigné à</span>
                    <span style="color:var(--text-main);font-size:14px"><?= htmlspecialchars($prospect['assigned_name'] ?: 'Non assigné', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div>
                    <span style="font-size:11px;text-transform:uppercase;color:var(--text-dim);font-weight:600;display:block">Statut Actuel</span>
                    <span class="badge <?= statusBadge($prospect['status']) ?>"><?= htmlspecialchars($prospect['status'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div>
                    <span style="font-size:11px;text-transform:uppercase;color:var(--text-dim);font-weight:600;display:block">Relance / Onboarding</span>
                    <span class="badge badge-accent">🔄 <?= htmlspecialchars($prospect['follow_up_status'] ?: 'A relancer', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php if (!empty($prospect['next_followup_date'])): ?>
                <div>
                    <span style="font-size:11px;text-transform:uppercase;color:var(--text-dim);font-weight:600;display:block">Prochaine relance</span>
                    <span style="color:var(--warning);font-weight:600;font-size:13.5px">📅 <?= date('d/m/Y', strtotime($prospect['next_followup_date'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div style="display:flex;gap:8px">
                <button type="button" class="btn btn-warning btn-sm" onclick="openModal('modal-followup')" title="Modifier la relance">
                    🔄 Modifier la relance
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="openModal('modal-conversation')" title="Noter un nouvel échange">
                    💬 + Noter un échange
                </button>
            </div>
        </div>

        <?php if (!empty($prospect['notes'])): ?>
        <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--border);font-size:13px;color:var(--text-muted)">
            <strong style="color:var(--text-main)">Notes &amp; contexte :</strong> <?= nl2br(htmlspecialchars($prospect['notes'], ENT_QUOTES, 'UTF-8')) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Zone Principale : Pleine visibilité accordée aux Historiques -->
<div class="grid-2" style="grid-template-columns: 1.35fr 0.65fr; gap:20px; align-items:start">
    
    <!-- Historique des Conversations & Échanges -->
    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <div>
                <span class="card-title">💬 Historique des Échanges (<?= count($convs) ?>)</span>
                <span style="font-size:12px;color:var(--text-dim);margin-left:6px">Chronologie des discussions</span>
            </div>
            <button type="button" class="btn btn-primary btn-sm" onclick="openModal('modal-conversation')">
                + Nouvel échange
            </button>
        </div>
        <div class="card-body">
            <?php if (empty($convs)): ?>
                <div class="empty-state" style="padding:40px 20px;text-align:center">
                    <div style="font-size:36px;margin-bottom:10px">💬</div>
                    <h3 style="margin-bottom:6px">Aucun échange consigné pour ce prospect</h3>
                    <p style="color:var(--text-muted);font-size:13.5px;margin-bottom:16px">Consignez les emails, appels, messages WhatsApp ou réunions pour garder l'historique complet.</p>
                    <button type="button" class="btn btn-primary" onclick="openModal('modal-conversation')">
                        ➕ Enregistrer le premier échange
                    </button>
                </div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:16px">
                    <?php foreach ($convs as $c): ?>
                    <div style="background:var(--bg-base);border:1px solid var(--border);border-radius:var(--radius-md);padding:16px;box-shadow:var(--shadow-sm);transition:border-color 0.2s" onmouseover="this.style.borderColor='var(--border-strong)'" onmouseout="this.style.borderColor='var(--border)'">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px">
                            <div style="display:flex;align-items:center;gap:8px">
                                <span class="badge badge-gray" style="font-weight:600"><?= htmlspecialchars($c['channel_name'] ?? 'Canal', ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="badge <?= statusBadge($c['status']) ?>"><?= htmlspecialchars($c['status'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if (!empty($c['user_name'])): ?>
                                    <span style="font-size:12px;color:var(--text-muted)">par <strong><?= htmlspecialchars($c['user_name'], ENT_QUOTES, 'UTF-8') ?></strong></span>
                                <?php endif; ?>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px">
                                <span style="font-size:12.5px;color:var(--text-dim);font-weight:500">📅 <?= date('d/m/Y', strtotime($c['conversation_date'])) ?></span>
                                <a href="/gtm-tracker/conversations/edit.php?id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm" style="padding:2px 8px;font-size:11px" title="Modifier cet échange">✏️</a>
                            </div>
                        </div>

                        <?php if (!empty($c['message_sent'])): ?>
                            <div style="background:rgba(255, 255, 255, 0.02);border-left:3px solid var(--primary);padding:10px 14px;border-radius:0 var(--radius-sm) var(--radius-sm) 0;margin-bottom:8px">
                                <div style="font-size:11.5px;font-weight:600;text-transform:uppercase;color:var(--primary);margin-bottom:4px">Message envoyé / Notes de l'échange</div>
                                <div style="font-size:13.5px;line-height:1.5;color:var(--text-main);white-space:pre-wrap"><?= htmlspecialchars($c['message_sent'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($c['response_received'])): ?>
                            <div style="background:rgba(16, 185, 129, 0.05);border-left:3px solid var(--success);padding:10px 14px;border-radius:0 var(--radius-sm) var(--radius-sm) 0;margin-bottom:8px">
                                <div style="font-size:11.5px;font-weight:600;text-transform:uppercase;color:var(--success);margin-bottom:4px">Réponse reçue du prospect</div>
                                <div style="font-size:13.5px;line-height:1.5;color:var(--text-main);white-space:pre-wrap"><?= htmlspecialchars($c['response_received'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($c['next_action'])): ?>
                            <div style="display:flex;align-items:center;gap:8px;font-size:12.5px;background:rgba(245, 158, 11, 0.1);padding:8px 12px;border-radius:var(--radius-sm);color:#fbbf24;margin-top:6px">
                                <span>⏰</span>
                                <div><strong>Prochaine étape prévue :</strong> <?= htmlspecialchars($c['next_action'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Actions GTM liées à ce prospect -->
    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <span class="card-title">🎯 Actions GTM liées (<?= count($actions) ?>)</span>
            <?php if ($isAdm): ?>
                <a href="/gtm-tracker/actions/create.php?prospect_id=<?= $id ?>" class="btn btn-primary btn-sm">+ Action</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($actions)): ?>
                <div class="empty-state" style="padding:28px 16px;text-align:center">
                    <p style="color:var(--text-muted);font-size:13px;margin-bottom:12px">Aucune action GTM enregistrée pour ce prospect.</p>
                    <?php if ($isAdm): ?>
                        <a href="/gtm-tracker/actions/create.php?prospect_id=<?= $id ?>" class="btn btn-secondary btn-sm">+ Créer une action</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Statut</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($actions as $a): ?>
                        <tr>
                            <td>
                                <a href="/gtm-tracker/actions/view.php?id=<?= $a['id'] ?>" style="font-weight:500;display:block">
                                    <?= htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                                <?php if ($isAdm && !empty($a['user_name'])): ?>
                                    <span style="font-size:11px;color:var(--text-dim)"><?= htmlspecialchars($a['user_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= statusBadge($a['status']) ?>"><?= htmlspecialchars($a['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td style="font-size:12px;color:var(--text-muted);white-space:nowrap"><?= htmlspecialchars($a['action_date'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL POPUP 1 : Noter un nouvel échange (Conversation)                    -->
<!-- ========================================================================= -->
<div class="modal-backdrop" id="modal-conversation" onclick="if(event.target===this) closeModal('modal-conversation')">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">
                <span>💬</span>
                Noter un nouvel échange avec <?= htmlspecialchars($prospect['name'], ENT_QUOTES, 'UTF-8') ?>
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('modal-conversation')" aria-label="Fermer">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="form_action" value="add_conversation">
            
            <div class="modal-body">
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
                        <label class="form-label">Statut du prospect</label>
                        <select name="conv_status" class="form-control">
                            <?php foreach ($statuses as $st): ?>
                            <option value="<?= $st ?>" <?= $prospect['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:16px">
                    <label class="form-label">Message envoyé / Notes de la discussion <span style="color:var(--danger)">*</span></label>
                    <textarea name="message_sent" class="form-control" rows="3" placeholder="Ce qui a été présenté, envoyé ou discuté..." required></textarea>
                </div>

                <div class="form-group" style="margin-bottom:16px">
                    <label class="form-label">Réponse reçue (retour du prospect, questions, objections...)</label>
                    <textarea name="response_received" class="form-control" rows="2" placeholder="Réponse ou intérêt manifesté par le prospect..."></textarea>
                </div>

                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Prochaine action / Suite à donner</label>
                    <textarea name="next_action" class="form-control" rows="2" placeholder="Ex: Envoyer la proposition chiffrée jeudi, planifier une démo..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-conversation')">Annuler</button>
                <button type="submit" class="btn btn-primary">➕ Enregistrer l'échange</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL POPUP 2 : Gestion de Relance & Onboarding                           -->
<!-- ========================================================================= -->
<div class="modal-backdrop" id="modal-followup" onclick="if(event.target===this) closeModal('modal-followup')">
    <div class="modal-dialog" style="max-width:520px">
        <div class="modal-header">
            <h3 class="modal-title">
                <span>🔄</span>
                Suivi de la Relance &amp; Onboarding
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('modal-followup')" aria-label="Fermer">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="form_action" value="update_followup">

            <div class="modal-body">
                <div class="form-group" style="margin-bottom:16px">
                    <label class="form-label">État d'avancement de la relance</label>
                    <select name="follow_up_status" class="form-control" style="font-weight:600">
                        <?php foreach ($followUpStatuses as $fs): ?>
                        <option value="<?= $fs ?>" <?= ($prospect['follow_up_status'] ?? 'A relancer') === $fs ? 'selected' : '' ?>><?= $fs ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:16px">
                    <label class="form-label">Date de la prochaine relance</label>
                    <input type="date" name="next_followup_date" class="form-control" value="<?= htmlspecialchars($prospect['next_followup_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Statut global du prospect</label>
                    <select name="prospect_status" class="form-control">
                        <?php foreach ($statuses as $st): ?>
                        <option value="<?= $st ?>" <?= $prospect['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-followup')">Annuler</button>
                <button type="submit" class="btn btn-warning">💾 Mettre à jour</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    const m = document.getElementById(id);
    if (m) {
        m.classList.add('active');
        document.body.style.overflow = 'hidden';
        const input = m.querySelector('input:not([type=hidden]):not([readonly]), textarea, select');
        if (input) setTimeout(() => input.focus(), 60);
    }
}

function closeModal(id) {
    const m = document.getElementById(id);
    if (m) {
        m.classList.remove('active');
        document.body.style.overflow = '';
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-backdrop.active').forEach(m => {
            m.classList.remove('active');
        });
        document.body.style.overflow = '';
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
