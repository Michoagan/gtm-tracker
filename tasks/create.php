<?php
$pageTitle = 'Enregistrer une tâche';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$uid = (int)$_SESSION['user_id'];
$isAdm = isAdmin();

$allMembers = array_values(getMembers());
$categories = getTaskCategories();
$statuses   = ['Terminée', 'En cours', 'A faire', 'Bloquée'];
$priorities = ['Normale', 'Haute', 'Urgente', 'Basse'];

$targetUser = isset($_GET['user_id']) && $isAdm ? (int)$_GET['user_id'] : $uid;
$preCategory = isset($_GET['category']) ? trim($_GET['category']) : 'tech';
?>

<div class="page-header">
    <div>
        <h2 class="page-header-title">Enregistrer une tâche</h2>
        <p class="page-header-sub">Consignez vos réalisations (développement tech, commercial, support, etc.)</p>
    </div>
    <a href="/gtm-tracker/tasks/index.php" class="btn btn-secondary">
        ← Retour aux tâches
    </a>
</div>

<div class="card" style="max-width:850px;margin:0 auto">
    <div class="card-body" style="padding:28px">
        <form method="POST" action="/gtm-tracker/tasks/store.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <div class="form-grid">
                <!-- Titre -->
                <div class="form-group form-full">
                    <label class="form-label" style="font-size:13.5px;font-weight:600">
                        Intitulé de la tâche / Mission *
                    </label>
                    <input type="text" name="title" class="form-control" style="font-size:14.5px" placeholder="Ex: Développement du module de facturation, Refonte API REST, Résolution bug d'authentification..." required autofocus>
                    <span class="form-hint">Décrivez la tâche que le collaborateur doit réaliser.</span>
                </div>

                <!-- Utilisateur assigné (Mise en avant pour l'Admin) -->
                <?php if ($isAdm): ?>
                <div class="form-group form-full" style="background:rgba(99,102,241,0.08);padding:16px;border-radius:var(--radius-sm);border:1px solid rgba(99,102,241,0.25)">
                    <label class="form-label" style="color:var(--primary);font-weight:700;font-size:14px">
                        👤 Membre assigné à cette tâche *
                    </label>
                    <select name="user_id" class="form-control" style="font-weight:600;font-size:14px">
                        <?php foreach ($allMembers as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= ($targetUser === (int)$m['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?> (<?= $m['role'] === 'admin' ? 'Admin' : 'Collaborateur' ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint" style="color:var(--text-muted)">Le collaborateur verra cette tâche dans sa liste, cliquera sur <strong>« Commencer »</strong> à son démarrage puis sur <strong>« Terminer »</strong> une fois le travail fini.</span>
                </div>
                <?php else: ?>
                <input type="hidden" name="user_id" value="<?= $uid ?>">
                <?php endif; ?>

                <!-- Catégorie -->
                <div class="form-group">
                    <label class="form-label" style="font-weight:600">Domaine / Catégorie *</label>
                    <select name="category" class="form-control" required style="font-weight:600">
                        <?php foreach ($categories as $catKey => $catInfo): ?>
                        <option value="<?= $catKey ?>" <?= ($preCategory === $catKey) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($catInfo['label'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Statut initial -->
                <div class="form-group">
                    <label class="form-label" style="font-weight:600">Statut initial</label>
                    <select name="status" class="form-control">
                        <option value="A faire" selected>⏳ À faire (Attente démarrage)</option>
                        <option value="En cours">⚡ En cours (Déjà commencée)</option>
                        <option value="Terminée">✅ Terminée (Déjà effectuée)</option>
                        <option value="Bloquée">🛑 Bloquée</option>
                    </select>
                    <span class="form-hint">Par défaut « À faire » : le collaborateur cliquera pour commencer.</span>
                </div>

                <!-- Date d'attribution -->
                <div class="form-group">
                    <label class="form-label" style="font-weight:600">Date d'attribution / Prévue *</label>
                    <input type="date" name="task_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <!-- Date d'échéance / Date limite -->
                <div class="form-group">
                    <label class="form-label" style="font-weight:600">Échéance / Date limite (optionnel)</label>
                    <input type="date" name="due_date" class="form-control" value="">
                    <span class="form-hint">Date limite fixée par l'admin.</span>
                </div>

                <!-- Priorité -->
                <div class="form-group">
                    <label class="form-label" style="font-weight:600">Priorité / Urgence</label>
                    <select name="priority" class="form-control">
                        <?php foreach ($priorities as $pr): ?>
                        <option value="<?= $pr ?>" <?= ($pr === 'Normale') ? 'selected' : '' ?>><?= $pr ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Temps estimé ou passé -->
                <div class="form-group">
                    <label class="form-label" style="font-weight:600">Temps estimé / passé (heures)</label>
                    <input type="number" name="hours_spent" class="form-control" step="0.25" min="0" max="100" placeholder="Ex: 2.0">
                    <span class="form-hint">Calculé automatiquement lors du clic sur « Terminer ».</span>
                </div>

                <!-- Lien externe (GitHub / PR / Docs / Figma) -->
                <div class="form-group form-full">
                    <label class="form-label" style="font-weight:600">Lien externe / Référence (optionnel)</label>
                    <input type="url" name="link_url" class="form-control" placeholder="https://github.com/... ou lien documentation, commit, Figma, ticket...">
                    <span class="form-hint">Lien direct vers la Pull Request, le commit, la doc technique, etc.</span>
                </div>

                <!-- Description / Détails techniques -->
                <div class="form-group form-full">
                    <label class="form-label" style="font-size:14px;font-weight:700;color:#ffffff">
                        📋 Détails du travail à effectuer / Spécifications techniques
                    </label>
                    <textarea name="description" class="form-control" rows="5" style="background:#090d16;color:#ffffff;font-size:15px;line-height:1.6;border:1.5px solid rgba(255,255,255,0.25);border-radius:8px;padding:14px 16px" placeholder="Détaillez le travail : technologies utilisées, modules modifiés, étapes à réaliser, architecture, consignes..."></textarea>
                    <span class="form-hint" style="font-size:13px;color:#cbd5e1">Consignes et spécifications précises pour le collaborateur.</span>
                </div>

                <!-- Résultat / Livrable -->
                <div class="form-group form-full">
                    <label class="form-label" style="font-size:14px;font-weight:700;color:#ffffff">
                        🎯 Résultat attendu / Livrable final
                    </label>
                    <textarea name="result" class="form-control" rows="3" style="background:#090d16;color:#ffffff;font-size:15px;line-height:1.6;border:1.5px solid rgba(255,255,255,0.25);border-radius:8px;padding:14px 16px" placeholder="Ex: PR GitHub validée, écran responsive livré sur staging, base migrée..."></textarea>
                    <span class="form-hint" style="font-size:13px;color:#cbd5e1">Ce qui confirmera que la mission est complétée avec succès.</span>
                </div>

                <!-- Pièce jointe / Capture d'écran -->
                <div class="form-group form-full">
                    <label class="form-label" style="font-weight:600">Pièce jointe / Capture d'écran (optionnel)</label>
                    <input type="file" name="attachment" class="form-control" accept="image/*,.pdf,.zip,.txt">
                    <span class="form-hint">Formats acceptés : PNG, JPG, PDF, ZIP. Max 10 Mo.</span>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:24px;border-top:1px solid var(--border);padding-top:18px">
                <a href="/gtm-tracker/tasks/index.php" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-primary" style="padding:10px 24px;font-size:14px">
                    💾 Enregistrer la tâche
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
