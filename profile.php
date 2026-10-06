<?php
$pageTitle = 'Mon Profil';
require_once __DIR__ . '/includes/header.php';
$db = getDB();
$userId = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($email)) {
        $errors[] = 'Le nom et l\'adresse email sont obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'adresse email n\'est pas valide.';
    } else {
        // Vérifier si email déjà utilisé par un autre
        $check = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$email, $userId]);
        if ($check->fetch()) {
            $errors[] = 'Cette adresse email est déjà utilisée par un autre compte.';
        } else {
            // Mise à jour de base
            if (!empty($newPass)) {
                if (strlen($newPass) < 6) {
                    $errors[] = 'Le nouveau mot de passe doit comporter au moins 6 caractères.';
                } elseif ($newPass !== $confirmPass) {
                    $errors[] = 'Les deux mots de passe ne correspondent pas.';
                } else {
                    $hash = password_hash($newPass, PASSWORD_BCRYPT);
                    $up = $db->prepare("UPDATE users SET name = ?, email = ?, password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $up->execute([$name, $email, $hash, $userId]);
                    logActivity($userId, 'changement_mot_de_passe', 'Mot de passe mis à jour');
                    $success = 'Votre profil et mot de passe ont été mis à jour avec succès !';
                }
            } else {
                $up = $db->prepare("UPDATE users SET name = ?, email = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $up->execute([$name, $email, $userId]);
                logActivity($userId, 'mise_a_jour_profil', 'Profil mis à jour');
                $success = 'Votre profil a été mis à jour avec succès !';
            }

            if (empty($errors)) {
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $stmt->execute([$userId]);
                $currentUser = $stmt->fetch();
            }
        }
    }
}
?>
<div class="page-header">
    <div>
        <h2 class="page-header-title">Mon Profil &amp; Sécurité</h2>
        <p class="page-header-sub">Gérez vos informations personnelles et votre mot de passe</p>
    </div>
    <div>
        <a href="/gtm-tracker/dashboard.php" class="btn btn-secondary btn-sm">← Retour au dashboard</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
<div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="grid-2">
    <!-- Formulaire d'édition de profil -->
    <div class="card">
        <div class="card-header"><span class="card-title">Informations du compte</span></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <div class="form-group">
                    <label class="form-label">Nom complet *</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Adresse email professionnelle *</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($currentUser['email'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Rôle sur la plateforme</label>
                    <input type="text" class="form-control" value="<?= $currentUser['role'] === 'admin' ? 'Administrateur' : 'Collaborateur' ?>" disabled style="opacity:0.7">
                    <span style="font-size:11px;color:var(--text-dim)">Attribué par l'administrateur</span>
                </div>

                <div style="margin:24px 0 16px;padding-top:16px;border-top:1px solid var(--border)">
                    <strong style="font-size:13px;display:block;margin-bottom:12px">Modifier mon mot de passe (optionnel)</strong>
                    <div class="form-group">
                        <label class="form-label">Nouveau mot de passe (laisser vide pour ne pas changer)</label>
                        <input type="password" name="new_password" class="form-control" placeholder="••••••••">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirmer le nouveau mot de passe</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="••••••••">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-full">Enregistrer les modifications</button>
            </form>
        </div>
    </div>

    <!-- Récapitulatif personnel -->
    <div class="card">
        <div class="card-header"><span class="card-title">Carte d'accès</span></div>
        <div class="card-body">
            <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px">
                <div class="user-avatar" style="width:56px;height:56px;font-size:22px">
                    <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
                </div>
                <div>
                    <h3 style="font-size:16px;color:var(--text-main)"><?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <p style="font-size:12.5px;color:var(--text-muted)"><?= htmlspecialchars($currentUser['email'], ENT_QUOTES, 'UTF-8') ?></p>
                    <span class="badge <?= $currentUser['role'] === 'admin' ? 'badge-accent' : 'badge-info' ?>" style="margin-top:6px">
                        <?= $currentUser['role'] === 'admin' ? '👑 Administrateur' : '👤 Collaborateur' ?>
                    </span>
                </div>
            </div>

            <div style="background:var(--bg-base);padding:14px;border-radius:var(--radius-sm);border:1px solid var(--border);display:flex;flex-direction:column;gap:8px">
                <div style="display:flex;justify-content:space-between;font-size:12.5px">
                    <span style="color:var(--text-muted)">Identifiant unique :</span>
                    <strong>#<?= $currentUser['id'] ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:12.5px">
                    <span style="color:var(--text-muted)">Date d'inscription :</span>
                    <span><?= date('d/m/Y à H:i', strtotime($currentUser['created_at'])) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:12.5px">
                    <span style="color:var(--text-muted)">Base de données :</span>
                    <span style="color:#10b981;font-weight:600">PostgreSQL Aiven (Cloud)</span>
                </div>
            </div>

            <?php if (isAdmin()): ?><div style="margin-top:20px">
                <a href="/gtm-tracker/my-space.php" class="btn btn-secondary btn-full">
                    Accéder à mon espace collaborateur
                </a>
            </div><?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
