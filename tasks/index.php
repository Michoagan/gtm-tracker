<?php
$pageTitle = 'Gestion des Tâches';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$uid = (int)$_SESSION['user_id'];
$isAdm = isAdmin();

// Filters
$fUser     = isset($_GET['user']) ? $_GET['user'] : '';
$fCategory = isset($_GET['category']) ? trim($_GET['category']) : '';
$fStatus   = isset($_GET['status']) ? trim($_GET['status']) : '';
$fPriority = isset($_GET['priority']) ? trim($_GET['priority']) : '';
$fDateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$fDateTo   = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$fSearch   = isset($_GET['search']) ? trim($_GET['search']) : '';
$fView     = isset($_GET['view']) ? trim($_GET['view']) : 'all'; // 'all' or 'mine'

// Non-admin default or view toggle
if (!$isAdm && $fView === 'mine') {
    $fUser = $uid;
}

$query = "SELECT t.*, u.name as user_name, assigner.name as assigned_by_name,
                 (SELECT COUNT(*) FROM attachments att WHERE att.task_id = t.id) as attachments_count
          FROM tasks t
          JOIN users u ON u.id = t.user_id
          LEFT JOIN users assigner ON assigner.id = t.assigned_by
          WHERE 1=1";
$params = [];

if ($fUser !== '') {
    $query .= " AND t.user_id = ?";
    $params[] = (int)$fUser;
}
if ($fCategory !== '') {
    $query .= " AND t.category = ?";
    $params[] = $fCategory;
}
if ($fStatus !== '') {
    $query .= " AND t.status = ?";
    $params[] = $fStatus;
}
if ($fPriority !== '') {
    $query .= " AND t.priority = ?";
    $params[] = $fPriority;
}
if ($fDateFrom !== '') {
    $query .= " AND t.task_date >= ?";
    $params[] = $fDateFrom;
}
if ($fDateTo !== '') {
    $query .= " AND t.task_date <= ?";
    $params[] = $fDateTo;
}
if ($fSearch !== '') {
    $query .= " AND (t.title LIKE ? OR t.description LIKE ? OR t.result LIKE ? OR t.link_url LIKE ?)";
    $like = "%$fSearch%";
    $params = array_merge($params, [$like, $like, $like, $like]);
}

$query .= " ORDER BY t.task_date DESC, t.id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Global metrics calculation (unfiltered or user-filtered)
$kpiWhere = $isAdm ? "1=1" : ($fView === 'mine' ? "user_id = $uid" : "1=1");
$totalTasksCount = $db->query("SELECT COUNT(*) FROM tasks WHERE $kpiWhere")->fetchColumn();
$doneTasksCount  = $db->query("SELECT COUNT(*) FROM tasks WHERE $kpiWhere AND status IN ('Terminée','Terminee','termine','terminée')")->fetchColumn();
$techTasksCount  = $db->query("SELECT COUNT(*) FROM tasks WHERE $kpiWhere AND category = 'tech'")->fetchColumn();
$totalHoursSpent = $db->query("SELECT COALESCE(SUM(hours_spent), 0) FROM tasks WHERE $kpiWhere")->fetchColumn();
$completionRate  = $totalTasksCount > 0 ? round(($doneTasksCount / $totalTasksCount) * 100) : 0;

$allMembers = array_values(getMembers());
$categories = getTaskCategories();
$statuses   = ['Terminée', 'En cours', 'A faire', 'Bloquée'];
$priorities = ['Basse', 'Normale', 'Haute', 'Urgente'];
?>

<div class="page-header">
    <div>
        <h2 class="page-header-title">Gestion des Tâches</h2>
        <p class="page-header-sub">Suivez et consignez toutes les tâches effectuées (Développement Tech, GTM, Produit, etc.)</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <div style="display:inline-flex;background:var(--bg-elevated);border:1px solid var(--border);border-radius:var(--radius-sm);padding:2px">
            <a href="?view=all<?= $fCategory ? '&category='.urlencode($fCategory) : '' ?>" class="btn btn-sm <?= ($fView !== 'mine' && $fUser === '') ? 'btn-primary' : 'btn-secondary' ?>" style="border:none;padding:5px 12px">
                👥 Toute l'équipe
            </a>
            <a href="?view=mine&user=<?= $uid ?><?= $fCategory ? '&category='.urlencode($fCategory) : '' ?>" class="btn btn-sm <?= ($fView === 'mine' || (string)$fUser === (string)$uid) ? 'btn-primary' : 'btn-secondary' ?>" style="border:none;padding:5px 12px">
                👤 Mes tâches
            </a>
        </div>
        <a href="/gtm-tracker/tasks/create.php" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <?= $isAdm ? '+ Donner / Créer une tâche' : '+ Enregistrer une tâche' ?>
        </a>
    </div>
</div>

<?php if (isset($_SESSION['flash'])): ?>
    <div class="alert alert-<?= htmlspecialchars($_SESSION['flash']['type'], ENT_QUOTES, 'UTF-8') ?>" style="margin-bottom:20px">
        <?= htmlspecialchars($_SESSION['flash']['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<!-- Stats Grid -->
<div class="stats-grid" style="margin-bottom:24px">
    <div class="stat-card" style="--card-color:var(--primary)">
        <div class="stat-label">Total Tâches</div>
        <div class="stat-value"><?= (int)$totalTasksCount ?></div>
        <div class="stat-sub"><?= $fView === 'mine' ? 'Mes tâches' : 'Équipe complète' ?></div>
    </div>
    <div class="stat-card" style="--card-color:#10b981">
        <div class="stat-label">Terminées</div>
        <div class="stat-value"><?= (int)$doneTasksCount ?> <span style="font-size:14px;font-weight:500;color:var(--text-muted)">(<?= $completionRate ?>%)</span></div>
        <div class="stat-sub">Tâches achevées avec succès</div>
    </div>
    <div class="stat-card" style="--card-color:#a855f7">
        <div class="stat-label">Développement Tech</div>
        <div class="stat-value"><?= (int)$techTasksCount ?></div>
        <div class="stat-sub">Tech, Code &amp; Solutions</div>
    </div>
    <div class="stat-card" style="--card-color:#f59e0b">
        <div class="stat-label">Heures Investies</div>
        <div class="stat-value"><?= number_format((float)$totalHoursSpent, 1, ',', ' ') ?> h</div>
        <div class="stat-sub">Temps de travail cumulé</div>
    </div>
</div>

<!-- Category Tabs -->
<div style="display:flex;gap:8px;overflow-x:auto;padding-bottom:10px;margin-bottom:18px">
    <a href="?<?= http_build_query(array_merge($_GET, ['category' => ''])) ?>" class="badge <?= empty($fCategory) ? 'badge-accent' : 'badge-gray' ?>" style="font-size:12.5px;padding:6px 14px;cursor:pointer;text-decoration:none">
        ✨ Toutes les catégories
    </a>
    <?php foreach ($categories as $catKey => $catInfo): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['category' => $catKey])) ?>" class="badge <?= ($fCategory === $catKey) ? $catInfo['badge'] : 'badge-gray' ?>" style="font-size:12.5px;padding:6px 14px;cursor:pointer;text-decoration:none">
        <?= htmlspecialchars($catInfo['label'], ENT_QUOTES, 'UTF-8') ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Filters bar -->
<div class="card" style="margin-bottom:24px">
    <div class="card-body" style="padding:16px 20px">
        <form method="GET" action="" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:flex-end">
            <?php if ($fView !== 'all'): ?>
            <input type="hidden" name="view" value="<?= htmlspecialchars($fView, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
            
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label" style="font-size:12px">Recherche</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Titre, résultat, mot-clé..." value="<?= htmlspecialchars($fSearch, ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-group" style="margin-bottom:0">
                <label class="form-label" style="font-size:12px">Membre</label>
                <select name="user" class="form-control form-control-sm">
                    <option value="">Tous les membres</option>
                    <?php foreach ($allMembers as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= ((string)$fUser === (string)$m['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:0">
                <label class="form-label" style="font-size:12px">Statut</label>
                <select name="status" class="form-control form-control-sm">
                    <option value="">Tous les statuts</option>
                    <?php foreach ($statuses as $st): ?>
                    <option value="<?= $st ?>" <?= ($fStatus === $st) ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:0">
                <label class="form-label" style="font-size:12px">Priorité</label>
                <select name="priority" class="form-control form-control-sm">
                    <option value="">Toutes priorités</option>
                    <?php foreach ($priorities as $pr): ?>
                    <option value="<?= $pr ?>" <?= ($fPriority === $pr) ? 'selected' : '' ?>><?= $pr ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:0">
                <label class="form-label" style="font-size:12px">Période du</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($fDateFrom, ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-group" style="margin-bottom:0">
                <label class="form-label" style="font-size:12px">Au</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($fDateTo, ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div style="display:flex;gap:6px">
                <button type="submit" class="btn btn-primary btn-sm" style="flex:1">Filtrer</button>
                <a href="/gtm-tracker/tasks/index.php" class="btn btn-secondary btn-sm" title="Réinitialiser les filtres">↺</a>
            </div>
        </form>
    </div>
</div>

<!-- Task List -->
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <h3 class="card-title" style="margin-bottom:0">
            <?= count($tasks) ?> tâche(s) trouvée(s)
        </h3>
        <span style="font-size:12.5px;color:var(--text-muted)">
            <?= $fCategory ? 'Catégorie : <strong>'.htmlspecialchars(taskCategoryLabel($fCategory), ENT_QUOTES, 'UTF-8').'</strong>' : 'Toutes les catégories' ?>
        </span>
    </div>

    <?php if (empty($tasks)): ?>
        <div style="padding:48px 20px;text-align:center">
            <div style="font-size:40px;margin-bottom:12px">📝</div>
            <h4 style="color:var(--text-main);margin-bottom:6px">Aucune tâche trouvée</h4>
            <p style="color:var(--text-muted);font-size:13.5px;max-width:450px;margin:0 auto 18px">
                <?= ($fSearch || $fCategory || $fStatus || $fUser) ? 'Aucune tâche ne correspond à vos filtres actuels.' : 'Commencez à consigner vos réalisations (développement tech, commercial, support...).' ?>
            </p>
            <a href="/gtm-tracker/tasks/create.php" class="btn btn-primary">
                + Enregistrer ma première tâche
            </a>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:105px">Date</th>
                        <th>Membre</th>
                        <th>Catégorie</th>
                        <th>Tâche &amp; Détails</th>
                        <th style="width:90px">Durée</th>
                        <th style="width:110px">Statut</th>
                        <th style="width:95px">Priorité</th>
                        <th style="width:120px;text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $t): 
                        $isOwner = ((int)$t['user_id'] === $uid);
                        $canManage = ($isOwner || $isAdm);
                        $catBadge = taskCategoryBadge($t['category']);
                        $catLabel = taskCategoryLabel($t['category']);
                        $isDone = in_array(mb_strtolower($t['status'], 'UTF-8'), ['terminée', 'terminee', 'termine']);
                    ?>
                    <tr>
                        <td style="white-space:nowrap;color:var(--text-dim);font-size:12.5px">
                            <?= date('d/m/Y', strtotime($t['task_date'])) ?>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px">
                                <div class="user-avatar" style="width:28px;height:28px;font-size:11px">
                                    <?= strtoupper(substr($t['user_name'], 0, 1)) ?>
                                </div>
                                <span style="font-weight:600;font-size:13px;color:var(--text-main)">
                                    <?= htmlspecialchars(explode(' ', $t['user_name'])[0], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <span class="badge <?= $catBadge ?>">
                                <?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-weight:600;color:var(--text-main);font-size:13.5px;margin-bottom:2px">
                                <a href="/gtm-tracker/tasks/view.php?id=<?= $t['id'] ?>" style="color:var(--text-main);text-decoration:none">
                                    <?= htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </div>
                            <?php if (!empty($t['description'])): ?>
                                <div style="font-size:12px;color:var(--text-muted);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">
                                    <?= htmlspecialchars($t['description'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($t['result'])): ?>
                                <div style="font-size:11.5px;color:#10b981;margin-top:2px">
                                    🎯 <em>Livrable : <?= htmlspecialchars(mb_substr($t['result'], 0, 80), ENT_QUOTES, 'UTF-8') ?><?= mb_strlen($t['result']) > 80 ? '...' : '' ?></em>
                                </div>
                            <?php endif; ?>
                            <div style="display:flex;gap:12px;margin-top:4px;font-size:11.5px;color:var(--text-dim);flex-wrap:wrap">
                                <?php if (!empty($t['assigned_by_name'])): ?>
                                    <span style="color:var(--primary);font-weight:500">👤 Donnée par <?= htmlspecialchars($t['assigned_by_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                                <?php if (!empty($t['due_date'])): ?>
                                    <span style="color:#f59e0b;font-weight:500">⏳ Échéance : <?= date('d/m/Y', strtotime($t['due_date'])) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($t['started_at'])): ?>
                                    <span style="color:var(--info)">▶️ Début : <?= date('d/m à H:i', strtotime($t['started_at'])) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($t['completed_at'])): ?>
                                    <span style="color:#10b981">🏁 Fin : <?= date('d/m à H:i', strtotime($t['completed_at'])) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($t['link_url'])): ?>
                                    <a href="<?= htmlspecialchars($t['link_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" style="color:var(--info);display:inline-flex;align-items:center;gap:3px">
                                        🔗 Lien externe ↗
                                    </a>
                                <?php endif; ?>
                                <?php if ($t['attachments_count'] > 0): ?>
                                    <span style="color:var(--text-muted)">📎 <?= $t['attachments_count'] ?> pièce(s)</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="white-space:nowrap;font-weight:600;font-size:12.5px;color:var(--text-muted)">
                            <?= ((float)$t['hours_spent'] > 0) ? number_format((float)$t['hours_spent'], 1, ',', ' ') . ' h' : '—' ?>
                        </td>
                        <td>
                            <?php 
                                $stNorm = mb_strtolower($t['status'], 'UTF-8');
                            ?>
                            <div style="display:flex;flex-direction:column;gap:5px;align-items:flex-start">
                                <span class="badge <?= statusBadge($t['status']) ?>">
                                    <?= htmlspecialchars($t['status'], ENT_QUOTES, 'UTF-8') ?>
                                </span>

                                <?php if ($canManage): ?>
                                    <?php if ($stNorm === 'a faire' || $stNorm === 'à faire'): ?>
                                        <form method="POST" action="/gtm-tracker/tasks/start.php" style="margin:0">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                            <button type="submit" class="btn btn-sm" style="background:#6366f1;color:#fff;padding:3px 9px;font-size:11px;font-weight:700;border-radius:4px" title="Marquer le début de la tâche maintenant">
                                                ▶️ Commencer
                                            </button>
                                        </form>
                                    <?php elseif ($stNorm === 'en cours'): ?>
                                        <a href="/gtm-tracker/tasks/finish.php?id=<?= $t['id'] ?>" class="btn btn-sm" style="background:#10b981;color:#fff;padding:3px 9px;font-size:11px;font-weight:700;border-radius:4px" title="Marquer la fin du travail et saisir le livrable">
                                            🏁 Terminer
                                        </a>
                                    <?php else: ?>
                                        <form method="POST" action="/gtm-tracker/tasks/toggle-status.php" style="margin:0">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                            <button type="submit" style="background:transparent;border:none;cursor:pointer;padding:0;font-size:11px;color:var(--text-dim)" title="Remettre en cours">
                                                ↩️ Réouvrir
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge <?= priorityBadge($t['priority']) ?>">
                                <?= htmlspecialchars($t['priority'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td style="text-align:right;white-space:nowrap">
                            <a href="/gtm-tracker/tasks/view.php?id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm" style="padding:4px 8px" title="Voir les détails">
                                👁️
                            </a>
                            <?php if ($canManage): ?>
                            <a href="/gtm-tracker/tasks/edit.php?id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm" style="padding:4px 8px" title="Modifier">
                                ✏️
                            </a>
                            <form method="POST" action="/gtm-tracker/tasks/delete.php" style="display:inline" onsubmit="return confirm('Confirmer la suppression de cette tâche ?');">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm" style="padding:4px 8px;color:var(--danger)" title="Supprimer">
                                    🗑️
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
