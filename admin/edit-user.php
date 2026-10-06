<?php
$pageTitle = 'Modifier utilisateur';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();
$db = getDB();
$id = (int)($_GET['id']??0);
$u = $db->prepare("SELECT * FROM users WHERE id=?");
$u->execute([$id]); $u = $u->fetch();
if (!$u) { header('Location: /gtm-tracker/admin/users.php'); exit; }
$errors = [];
if ($_SERVER['REQUEST_METHOD']==='POST' && verifyCsrfToken($_POST['csrf_token']??'')) {
    $name  = trim($_POST['name']??'');
    $email = trim($_POST['email']??'');
    $role  = in_array($_POST['role']??'',['admin','user']) ? $_POST['role'] : 'user';
    $pass  = $_POST['password']??'';
    if (empty($name)||empty($email)) { $errors[] = 'Nom et email requis.'; }
    else {
        try {
            if (!empty($pass) && strlen($pass)>=8) {
                $hash = password_hash($pass,PASSWORD_BCRYPT);
                $db->prepare("UPDATE users SET name=?,email=?,password=?,role=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$name,$email,$hash,$role,$id]);
            } else {
                $db->prepare("UPDATE users SET name=?,email=?,role=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$name,$email,$role,$id]);
            }
            $_SESSION['flash']=['type'=>'success','message'=>'Utilisateur mis a jour.'];
            header('Location: /gtm-tracker/admin/users.php');
            exit;
        } catch (Exception $e) { $errors[]='Email deja utilise.'; }
    }
}
?>
<div class="page-header">
    <h2 class="page-header-title">Modifier utilisateur</h2>
    <a href="/gtm-tracker/admin/users.php" class="btn btn-secondary">Retour</a>
</div>
<?php if(!empty($errors)): ?><div class="alert alert-error"><?= implode('<br>',array_map(fn($e)=>htmlspecialchars($e,ENT_QUOTES,'UTF-8'),$errors)) ?></div><?php endif; ?>
<div class="card" style="max-width:500px">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="form-group" style="margin-bottom:12px"><label class="form-label">Nom *</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($u['name'],ENT_QUOTES,'UTF-8') ?>" required></div>
            <div class="form-group" style="margin-bottom:12px"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($u['email'],ENT_QUOTES,'UTF-8') ?>" required></div>
            <div class="form-group" style="margin-bottom:12px">
                <label class="form-label">Nouveau mot de passe (laisser vide pour ne pas changer)</label>
                <input type="password" name="password" class="form-control" placeholder="8 caracteres minimum">
            </div>
            <div class="form-group" style="margin-bottom:20px">
                <label class="form-label">Role</label>
                <select name="role" class="form-control">
                    <option value="user" <?= $u['role']==='user'?'selected':'' ?>>Utilisateur</option>
                    <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Administrateur</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>