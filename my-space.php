<?php
$pageTitle = 'Espace Personnel Collaborateur';
require_once __DIR__ . '/includes/header.php';
$db = getDB();
$currentUserId = $_SESSION['user_id'];
$isAdm = isAdmin();

// Si admin, il peut voir l'espace de n'importe quel membre du personnel via ?user_id=X
$targetUserId = $currentUserId;
if ($isAdm && isset($_GET['user_id'])) {
    $targetUserId = (int)$_GET['user_id'];
}

// Charger les infos du personnel cible depuis la liste statique
$members = getMembers();
$staffMember = $members[$targetUserId] ?? null;

if (!$staffMember) {
    echo "<div class='alert alert-error'>Membre du personnel introuvable.</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$isViewingSelf = ($targetUserId === $currentUserId);

// Liste de tous les membres pour le sélecteur rapide de l'admin
$allStaff = $isAdm ? array_values(getMembers()) : [];

// Statistiques spécifiques à ce membre du personnel
$pTotalActions = $db->query("SELECT COUNT(*) FROM actions WHERE user_id = $targetUserId")->fetchColumn();
$pDoneActions  = $db->query("SELECT COUNT(*) FROM actions WHERE user_id = $targetUserId AND status IN ('termine', 'Terminee', 'Terminé')")->fetchColumn();
$pPendingActions = $db->query("SELECT COUNT(*) FROM actions WHERE user_id = $targetUserId AND status NOT IN ('termine', 'Terminee', 'Terminé', 'annule', 'Annulé')")->fetchColumn();
$pConvsCount   = $db->query("SELECT COUNT(*) FROM conversations WHERE user_id = $targetUserId")->fetchColumn();
$pProspectsCount = $db->query("SELECT COUNT(*) FROM prospects WHERE assigned_to = $targetUserId")->fetchColumn();

// Relances prioritaires (Conversations avec next_action non vide)
$stmtNext = $db->prepare("
    SELECT c.*, p.name as prospect_name, p.company, ch.name as channel_name 
    FROM conversations c 
    LEFT JOIN prospects p ON p.id = c.prospect_id 
    LEFT JOIN channels ch ON ch.id = c.channel_id 
    WHERE c.user_id = ? AND c.next_action IS NOT NULL AND c.next_action != ''
    ORDER BY c.conversation_date DESC 
    LIMIT 6
");
$stmtNext->execute([$targetUserId]);
$pendingFollowups = $stmtNext->fetchAll();

// Actions récentes du membre
$stmtActions = $db->prepare("
    SELECT a.*, s.name as strategy_name, ch.name as channel_name, assigner.name as assigned_by_name 
    FROM actions a 
    LEFT JOIN users assigner ON assigner.id = a.assigned_by
    LEFT JOIN strategies s ON s.id = a.strategy_id 
    LEFT JOIN channels ch ON ch.id = a.channel_id 
    WHERE a.user_id = ? 
    ORDER BY a.action_date DESC, a.id DESC 
    LIMIT 10
");
$stmtActions->execute([$targetUserId]);
$staffActions = $stmtActions->fetchAll();

// Prospects attribués à ce membre
$stmtPros = $db->prepare("
    SELECT p.*, ch.name as channel_name 
    FROM prospects p 
    LEFT JOIN channels ch ON ch.id = p.channel_id 
    WHERE p.assigned_to = ? 
    ORDER BY p.updated_at DESC 
    LIMIT 6
");
$stmtPros->execute([$targetUserId]);
$staffProspects = $stmtPros->fetchAll();
?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px">
        <div class="user-avatar" style="width:52px;height:52px;font-size:20px;box-shadow:0 0 16px var(--primary-glow)">
            <?= strtoupper(substr($staffMember['name'], 0, 1)) ?>
        </div>
        <div>
            <h2 class="page-header-title" style="margin:0">
                <?= $isViewingSelf ? "Mon Espace Dédié" : "Espace de " . htmlspecialchars($staffMember['name'], ENT_QUOTES, 'UTF-8') ?>
            </h2>
            <div style="display:flex;align-items:center;gap:10px;margin-top:4px">
                <span class="badge <?= $staffMember['role'] === 'admin' ? 'badge-accent' : 'badge-info' ?>">
                    <?= $staffMember['role'] === 'admin' ? '👑 Administrateur' : '👤 Collaborateur' ?>
                </span>
                <span style="font-size:12.5px;color:var(--text-muted)"><?= htmlspecialchars($staffMember['email'], ENT_QUOTES, 'UTF-8') ?></span>
                <span style="font-size:11.5px;color:var(--text-dim)">• Inscrit le <?= date('d/m/Y', strtotime($staffMember['created_at'])) ?></span>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <?php if ($isAdm && count($allStaff) > 1): ?>
        <form method="GET" action="/gtm-tracker/my-space.php" style="display:flex;gap:8px;align-items:center">
            <span style="font-size:12px;color:var(--text-muted)">Changer d'espace :</span>
            <select name="user_id" class="form-control" style="width:auto;padding:6px 30px 6px 12px;font-size:12.5px" onchange="this.form.submit()">
                <?php foreach ($allStaff as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $s['id'] == $targetUserId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?> (<?= $s['role'] ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?><?php if ($isAdm): ?>

        <a href="/gtm-tracker/actions/create.php?user_id=<?= $targetUserId ?>" class="btn btn-primary btn-sm" title="Donner une action à ce collaborateur">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <?= ($isAdm && !$isViewingSelf) ? "+ Assigner une action" : "+ Nouvelle action" ?>
        </a><?php endif; ?>
            
        
        <?php if ($isViewingSelf): ?>
        <a href="/gtm-tracker/profile.php" class="btn btn-secondary btn-sm" title="Modifier mes accès">
            ⚙️ Mon Profil
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- KPIs spécifiques à ce collaborateur -->
<div class="stats-grid">
    <div class="stat-card" style="--card-color:#f59e0b">
        <div class="stat-label">À Faire / En cours</div>
        <div class="stat-value"><?= $pPendingActions ?></div>
        <div class="stat-sub">Actions prioritaires à traiter</div>
    </div>
    <div class="stat-card" style="--card-color:#10b981">
        <div class="stat-label">Actions Terminées</div>
        <div class="stat-value"><?= $pDoneActions ?></div>
        <div class="stat-sub">Sur <?= $pTotalActions ?> action(s) au total</div>
    </div>
    <div class="stat-card" style="--card-color:#38bdf8">
        <div class="stat-label">Prospects Attribués</div>
        <div class="stat-value"><?= $pProspectsCount ?></div>
        <div class="stat-sub">Portefeuille de contacts</div>
    </div>
    <div class="stat-card" style="--card-color:#a855f7">
        <div class="stat-label">Échanges Conduits</div>
        <div class="stat-value"><?= $pConvsCount ?></div>
        <div class="stat-sub">Conversations enregistrées</div>
    </div>
</div>

<!-- Grille 2 colonnes : Relances & Actions -->
<div class="grid-2" style="margin-bottom:24px">
    <!-- Relances et Next Actions -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">⏰ Mes Prochaines Relances ("Next Steps")</span>
            <a href="/gtm-tracker/prospects/index.php" class="btn btn-secondary btn-sm">Voir prospects</a>
        </div>
        <?php if (empty($pendingFollowups)): ?>
            <div class="empty-state">
                <p>Aucune relance en attente pour le moment.</p>
                <a href="/gtm-tracker/prospects/create.php" class="btn btn-primary btn-sm" style="margin-top:10px">+ Créer un prospect</a>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Prospect</th>
                            <th>Prochaine action à mener</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingFollowups as $f): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($f['prospect_name'] ?? 'Contact', ENT_QUOTES, 'UTF-8') ?></strong>
                                <?php if ($f['company']): ?>
                                    <span style="font-size:11px;color:var(--text-muted);display:block"><?= htmlspecialchars($f['company'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="color:#fbbf24;font-weight:600;font-size:12.5px">
                                    👉 <?= htmlspecialchars($f['next_action'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td><span style="font-size:12px;color:var(--text-dim)"><?= date('d/m/Y', strtotime($f['conversation_date'])) ?></span></td>
                            <td style="text-align:right">
                                <a href="/gtm-tracker/conversations/view.php?id=<?= $f['id'] ?>" class="btn btn-secondary btn-sm">Détail</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Mes Actions GTM récentes -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">🎯 Mes Dernières Actions Réalisées</span>
            <a href="/gtm-tracker/actions/index.php" class="btn btn-secondary btn-sm">Voir tout</a>
        </div>
        <?php if (empty($staffActions)): ?>
            <div class="empty-state">
                <p>Aucune action enregistrée pour le moment.</p>
                <?php if ($isAdm): ?><a href="/gtm-tracker/actions/create.php?user_id=<?= $targetUserId ?>" class="btn btn-primary btn-sm" style="margin-top:10px">+ Assigner une action</a><?php else: ?><span style="font-size:12px;color:var(--text-muted)">Aucune action assignée pour le moment.</span><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Canal</th>
                            <th>Priorité</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staffActions as $a): ?>
                        <tr>
                            <td>
                                <a href="/gtm-tracker/actions/view.php?id=<?= $a['id'] ?>">
                                    <strong><?= htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                </a>
                                <?php if (!empty($a['assigned_by_name'])): ?>
                                    <span class="badge badge-purple" style="font-size:10px;padding:1px 6px;margin-left:6px">Donnée par Admin (<?= htmlspecialchars($a['assigned_by_name'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                <?php endif; ?>
                                <span style="font-size:11px;color:var(--text-dim);display:block"><?= date('d/m/Y', strtotime($a['action_date'])) ?></span>
                            </td>
                            <td><?= htmlspecialchars($a['channel_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="badge <?= priorityBadge($a['priority']) ?>"><?= htmlspecialchars($a['priority'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><span class="badge <?= statusBadge($a['status']) ?>"><?= htmlspecialchars($a['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Prospects attribués à ce collaborateur -->
<div class="card">
    <div class="card-header">
        <span class="card-title">👥 Mon Portefeuille de Prospects Attribués (<?= count($staffProspects) ?>)</span>
        <a href="/gtm-tracker/prospects/create.php" class="btn btn-primary btn-sm">+ Nouveau prospect</a>
    </div>
    <?php if (empty($staffProspects)): ?>
        <div class="empty-state">
            <p>Aucun prospect n'est actuellement attribué à ce membre du personnel.</p>
            <a href="/gtm-tracker/prospects/create.php" class="btn btn-secondary btn-sm" style="margin-top:10px">Ajouter un prospect</a>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Nom du prospect</th>
                        <th>Société</th>
                        <th>Canal d'origine</th>
                        <th>Coordonnées</th>
                        <th>Statut de qualification</th>
                        <th style="text-align:right">Action rapide</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staffProspects as $p): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= htmlspecialchars($p['company'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="badge badge-gray"><?= htmlspecialchars($p['channel_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><span style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($p['contact'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><span class="badge <?= statusBadge($p['status']) ?>"><?= htmlspecialchars($p['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td style="text-align:right">
                            <a href="/gtm-tracker/prospects/view.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">
                                💬 Noter un échange
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

