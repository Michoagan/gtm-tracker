<?php
$pageTitle = 'Gestion du Personnel';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();
$db = getDB();
$members = array_values(getMembers());
?>

<div class="page-header">
    <div>
        <h2 class="page-header-title">Équipe commerciale</h2>
        <p class="page-header-sub">Membres du personnel enregistrés dans le système (<?= count($members) ?> membres)</p>
    </div>
</div>

<!-- Cartes membres -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-bottom:32px">
<?php
$colors = ['#6366f1','#22c55e','#f59e0b','#ef4444'];
foreach ($members as $i => $m):
    $mid   = $m['id'];
    $color = $colors[$i % count($colors)];
    $actCount  = $db->query("SELECT COUNT(*) FROM actions WHERE user_id = $mid")->fetchColumn();
    $convCount = $db->query("SELECT COUNT(*) FROM conversations WHERE user_id = $mid")->fetchColumn();
    $prospCount = $db->query("SELECT COUNT(*) FROM prospects WHERE assigned_to = $mid")->fetchColumn();
    $taskCount  = $db->query("SELECT COUNT(*) FROM tasks WHERE user_id = $mid")->fetchColumn();
?>
<div class="card" style="border-top:3px solid <?= $color ?>;padding:24px">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px">
        <div class="user-avatar" style="width:52px;height:52px;font-size:18px;background:<?= $color ?>">
            <?= strtoupper(substr($m['name'],0,1)) ?>
        </div>
        <div>
            <div style="font-size:15px;font-weight:700;color:var(--text-main)"><?= htmlspecialchars($m['name'],ENT_QUOTES,'UTF-8') ?></div>
            <div style="font-size:12px;color:var(--text-dim);margin-top:2px">
                <?= $m['role']==='admin' ? '<span class="badge badge-accent">Administrateur</span>' : '<span class="badge badge-gray">Collaborateur</span>' ?>
            </div>
        </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;text-align:center;margin-bottom:20px">
        <div style="background:#f8fafc;padding:8px 4px;border-radius:8px;border:1px solid #cbd5e1">
            <div style="font-size:18px;font-weight:800;color:#000000"><?= $actCount ?></div>
            <div style="font-size:10px;color:#1e293b;font-weight:700;text-transform:uppercase">Actions</div>
        </div>
        <div style="background:#f8fafc;padding:8px 4px;border-radius:8px;border:1px solid #cbd5e1">
            <div style="font-size:18px;font-weight:800;color:#000000"><?= $convCount ?></div>
            <div style="font-size:10px;color:#1e293b;font-weight:700;text-transform:uppercase">Convs</div>
        </div>
        <div style="background:#f8fafc;padding:8px 4px;border-radius:8px;border:1px solid #cbd5e1">
            <div style="font-size:18px;font-weight:800;color:#000000"><?= $prospCount ?></div>
            <div style="font-size:10px;color:#1e293b;font-weight:700;text-transform:uppercase">Prospects</div>
        </div>
        <div style="background:#f8fafc;padding:8px 4px;border-radius:8px;border:1.5px solid rgba(139,92,246,0.4)">
            <div style="font-size:18px;font-weight:800;color:#7c3aed"><?= $taskCount ?></div>
            <div style="font-size:10px;color:#6d28d9;text-transform:uppercase;font-weight:700">Tâches</div>
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-direction:column">
        <a href="/gtm-tracker/tasks/create.php?user_id=<?= $mid ?>" class="btn btn-primary btn-sm" style="text-align:center;background:#8b5cf6">
            📋 Donner une tâche à <?= htmlspecialchars(explode(' ',$m['name'])[0],ENT_QUOTES,'UTF-8') ?>
        </a>
        <a href="/gtm-tracker/my-space.php?user_id=<?= $mid ?>" class="btn btn-secondary btn-sm" style="text-align:center">
            👁️ Voir l'espace de <?= htmlspecialchars(explode(' ',$m['name'])[0],ENT_QUOTES,'UTF-8') ?>
        </a>
        <?php if ($m['role'] !== 'admin'): ?>
        <a href="/gtm-tracker/actions/create.php?user_id=<?= $mid ?>" class="btn btn-secondary btn-sm" style="text-align:center">
            ➕ Assigner une action GTM
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- Tableau récapitulatif -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Activité détaillée par membre</span>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Membre</th>
                    <th>Rôle</th>
                    <th>Actions</th>
                    <th>Conversations</th>
                    <th>Prospects</th>
                    <th style="text-align:right">Accès rapide</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($members as $i => $m):
                $mid = $m['id'];
                $actCount   = $db->query("SELECT COUNT(*) FROM actions WHERE user_id = $mid")->fetchColumn();
                $convCount  = $db->query("SELECT COUNT(*) FROM conversations WHERE user_id = $mid")->fetchColumn();
                $prospCount = $db->query("SELECT COUNT(*) FROM prospects WHERE assigned_to = $mid")->fetchColumn();
                $color = $colors[$i % count($colors)];
            ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div class="user-avatar" style="width:32px;height:32px;font-size:12px;background:<?= $color ?>">
                            <?= strtoupper(substr($m['name'],0,1)) ?>
                        </div>
                        <strong><?= htmlspecialchars($m['name'],ENT_QUOTES,'UTF-8') ?></strong>
                    </div>
                </td>
                <td>
                    <span class="badge <?= $m['role']==='admin'?'badge-accent':'badge-gray' ?>">
                        <?= $m['role']==='admin' ? 'Administrateur' : 'Commercial' ?>
                    </span>
                </td>
                <td><span class="badge badge-info"><?= $actCount ?></span></td>
                <td><?= $convCount ?></td>
                <td><?= $prospCount ?></td>
                <td style="text-align:right">
                    <div style="display:inline-flex;gap:6px">
                        <a href="/gtm-tracker/my-space.php?user_id=<?= $mid ?>" class="btn btn-secondary btn-sm">Espace dédié</a>
                        <?php if ($m['role'] !== 'admin'): ?>
                        <a href="/gtm-tracker/actions/create.php?user_id=<?= $mid ?>" class="btn btn-primary btn-sm">+ Action</a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
