<?php
$pageTitle = 'Strategies';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$isAdm = isAdmin();
$strategies = $db->query("SELECT s.*,u.name as creator FROM strategies s LEFT JOIN users u ON u.id=s.created_by ORDER BY s.name")->fetchAll();
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
?>
<div class="page-header">
    <div>
        <h2 class="page-header-title">Strategies GTM</h2>
        <p class="page-header-sub"><?= count($strategies) ?> strategie(s)</p>
    </div>
    <?php if($isAdm): ?>
    <a href="/gtm-tracker/strategies/create.php" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nouvelle strategie
    </a>
    <?php endif; ?>
</div>
<div class="card">
    <?php if(empty($strategies)): ?>
    <div class="empty-state">
        <h3>Aucune strategie</h3>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Nom</th><th>Description</th><th>Creee par</th><th>Date</th><?php if($isAdm): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach($strategies as $s): ?>
            <tr>
                <td style="font-weight:600"><span class="badge badge-accent"><?= htmlspecialchars($s['name'],ENT_QUOTES,'UTF-8') ?></span></td>
                <td><?= htmlspecialchars($s['description']??'—',ENT_QUOTES,'UTF-8') ?></td>
                <td><?= htmlspecialchars($s['creator']??'—',ENT_QUOTES,'UTF-8') ?></td>
                <td><?= date('d/m/Y',strtotime($s['created_at'])) ?></td>
                <?php if($isAdm): ?>
                <td>
                    <div style="display:flex;gap:5px">
                        <a href="/gtm-tracker/strategies/edit.php?id=<?= $s['id'] ?>" class="btn btn-warning btn-sm btn-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </a>
                        <a href="/gtm-tracker/strategies/delete.php?id=<?= $s['id'] ?>" class="btn btn-danger btn-sm btn-icon" data-confirm="Supprimer cette strategie ?">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                        </a>
                    </div>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>