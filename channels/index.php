<?php
$pageTitle = 'Canaux';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$isAdm = isAdmin();
$channels = $db->query("SELECT ch.*,COUNT(a.id) as actions FROM channels ch LEFT JOIN actions a ON a.channel_id=ch.id GROUP BY ch.id,ch.name ORDER BY ch.name")->fetchAll();
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
?>
<div class="page-header">
    <h2 class="page-header-title">Canaux de communication</h2>
    <?php if($isAdm): ?>
    <a href="/gtm-tracker/channels/create.php" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nouveau canal
    </a>
    <?php endif; ?>
</div>
<div class="card">
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Canal</th><th>Actions associees</th><th>Date creation</th><?php if($isAdm): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach($channels as $c): ?>
            <tr>
                <td style="font-weight:500"><?= htmlspecialchars($c['name'],ENT_QUOTES,'UTF-8') ?></td>
                <td><?= $c['actions'] ?></td>
                <td><?= date('d/m/Y',strtotime($c['created_at'])) ?></td>
                <?php if($isAdm): ?>
                <td>
                    <div style="display:flex;gap:5px">
                        <a href="/gtm-tracker/channels/edit.php?id=<?= $c['id'] ?>" class="btn btn-warning btn-sm btn-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </a>
                        <a href="/gtm-tracker/channels/delete.php?id=<?= $c['id'] ?>" class="btn btn-danger btn-sm btn-icon" data-confirm="Supprimer ce canal ?">
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
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>