<?php
session_start();
require_once __DIR__ . "/config/database.php";

if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== '') {
    header('Location: /gtm-tracker/dashboard.php');
    exit;
}

// Membres du personnel (pas de DB nécessaire)
$members = [
    ['id' => 1, 'name' => 'GABIN SOKINDJI',   'firstname' => 'Gabin',  'role' => 'collaborator', 'avatar' => 'GS', 'color' => '#6366f1'],
    ['id' => 2, 'name' => 'TRESOR NEKOUA',    'firstname' => 'Tresor', 'role' => 'collaborator', 'avatar' => 'TN', 'color' => '#22c55e'],
    ['id' => 3, 'name' => 'ADJIBI DAHLIA',    'firstname' => 'Dahlia', 'role' => 'collaborator', 'avatar' => 'AD', 'color' => '#f59e0b'],
    ['id' => 4, 'name' => 'MARIO MITCHOAGAN', 'firstname' => 'Mario',  'role' => 'collaborator', 'avatar' => 'MM', 'color' => '#0ea5e9'],
    ['id' => 0, 'name' => 'Admin',             'firstname' => 'Admin',  'role' => 'admin',        'avatar' => 'A',  'color' => '#ef4444'],
];

function cleanName(string $s): string {
    $s = trim(mb_strtolower($s, 'UTF-8'));
    $accents = ['é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','à'=>'a','â'=>'a','î'=>'i','ï'=>'i','ô'=>'o','ù'=>'u','û'=>'u','ç'=>'c'];
    return strtr($s, $accents);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_id = $_POST['member_id'] ?? null;
    $password    = trim($_POST['password'] ?? $_POST['pin'] ?? '');

    // Trouver le membre sélectionné
    $member = null;
    foreach ($members as $m) {
        if ((string)$m['id'] === (string)$selected_id) {
            $member = $m;
            break;
        }
    }

    if (!$member) {
        $error = 'Veuillez sélectionner un membre du personnel.';
    } elseif ($password === '') {
        $error = 'Veuillez saisir votre prénom comme mot de passe.';
    } else {
        // Le mot de passe de chaque utilisateur est son PRÉNOM
        $cleanInput = cleanName($password);
        $cleanFirst = cleanName($member['firstname']);
        $isValid    = false;

        if ($cleanInput === $cleanFirst) {
            $isValid = true;
        } elseif ($member['id'] == 3 && $cleanInput === 'adjibi') {
            // Pour ADJIBI DAHLIA, accepter également Adjibi
            $isValid = true;
        } elseif ($member['role'] === 'admin' && ($password === 'Onspecial001' || $cleanInput === 'administrateur')) {
            $isValid = true;
        }

        if (!$isValid) {
            $error = 'Mot de passe incorrect pour ' . htmlspecialchars($member['name'], ENT_QUOTES, 'UTF-8') . '. Votre mot de passe est votre prénom.';
        } else {
            if ($member['role'] === 'admin') {
                $_SESSION['user_id']    = 0;
                $_SESSION['user_name']  = 'Admin';
                $_SESSION['user_email'] = 'onspecial@gmail.com';
                $_SESSION['user_role']  = 'admin';
                logActivity(0, 'connexion', 'Connexion Admin');
            } else {
                $_SESSION['user_id']    = $member['id'];
                $_SESSION['user_name']  = $member['name'];
                $_SESSION['user_email'] = strtolower(str_replace(' ', '.', $member['name'])) . '@getspecial.com';
                $_SESSION['user_role']  = 'collaborator';
                logActivity($member['id'], 'connexion', 'Connexion ' . $member['name']);
            }
            header('Location: /gtm-tracker/dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — GTM Tracker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="manifest" href="/gtm-tracker/manifest.json">
    <meta name="theme-color" content="#ffffff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-touch-fullscreen" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="GTM Tracker">
    <link rel="apple-touch-icon" href="/gtm-tracker/icon-192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/gtm-tracker/icon-192.png">
    <link rel="apple-touch-icon" sizes="192x192" href="/gtm-tracker/icon-192.png">
    <link rel="apple-touch-icon" sizes="512x512" href="/gtm-tracker/icon-512.png">
    <link rel="icon" type="image/svg+xml" href="/gtm-tracker/icon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="/gtm-tracker/icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/gtm-tracker/icon-512.png">
    <link rel="stylesheet" href="/gtm-tracker/css/style.css">
    <style>
        .login-body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(ellipse at 20% 50%, rgba(99,102,241,0.15) 0%, transparent 60%),
                        radial-gradient(ellipse at 80% 20%, rgba(34,197,94,0.1) 0%, transparent 50%),
                        var(--bg-base);
            padding: 20px;
            font-family: 'Inter', sans-serif;
        }
        .login-wrap {
            width: 100%;
            max-width: 520px;
        }
        .login-logo-zone {
            text-align: center;
            margin-bottom: 36px;
        }
        .login-logo-icon {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 18px;
            display: inline-flex; align-items: center; justify-content: center;
            margin-bottom: 16px;
            box-shadow: 0 8px 32px rgba(99,102,241,0.4);
        }
        .login-logo-icon svg { color: white; }
        .login-app-name {
            font-size: 28px; font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, rgba(255,255,255,0.7) 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0 0 6px;
        }
        .login-app-sub {
            font-size: 14px; color: var(--text-dim); margin: 0;
        }
        .login-card-new {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        }
        .login-card-title {
            font-size: 16px; font-weight: 600; color: var(--text-main);
            margin: 0 0 6px;
        }
        .login-card-desc {
            font-size: 13px; color: var(--text-dim);
            margin: 0 0 24px;
        }
        .member-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 0;
        }
        .member-btn {
            position: relative;
            background: var(--bg-elevated);
            border: 2px solid var(--border);
            border-radius: 14px;
            padding: 20px 16px;
            cursor: pointer;
            text-align: center;
            transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
            text-decoration: none;
            display: flex; flex-direction: column; align-items: center; gap: 10px;
        }
        .member-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.3);
        }
        .member-btn.admin-btn {
            border-color: rgba(239,68,68,0.3);
            background: rgba(239,68,68,0.05);
        }
        .member-btn.admin-btn:hover {
            border-color: #ef4444;
            background: rgba(239,68,68,0.1);
            box-shadow: 0 12px 32px rgba(239,68,68,0.2);
        }
        .member-btn.active {
            border-color: var(--primary);
            background: rgba(99,102,241,0.1);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
        }
        .member-avatar-lg {
            width: 52px; height: 52px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; font-weight: 700; color: white;
            letter-spacing: 0.5px;
        }
        .member-name {
            font-size: 12px; font-weight: 600;
            color: var(--text-main);
            line-height: 1.3;
        }
        .member-role-tag {
            font-size: 10px;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .pin-section {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: rgba(99,102,241,0.06);
            border: 1px solid rgba(99,102,241,0.25);
            border-radius: 14px;
        }
        .pin-section.visible { display: block; }
        .pin-section label {
            display: block; font-size: 13px;
            font-weight: 600; color: var(--text-main);
            margin-bottom: 8px;
        }
        .pin-input {
            width: 100%; padding: 13px 16px;
            background: var(--bg-elevated);
            border: 1px solid var(--border-strong);
            border-radius: 10px;
            color: var(--text-main);
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            text-align: center;
            box-sizing: border-box;
            transition: var(--transition);
        }
        .pin-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }
        .divider { height: 1px; background: var(--border); margin: 24px 0; }
        .connect-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border: none; border-radius: 10px;
            color: white; font-size: 15px; font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            margin-top: 20px;
        }
        .connect-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(99,102,241,0.4);
        }
        .connect-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .alert-error-new {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
            border-radius: 10px;
            padding: 12px 16px;
            color: #f87171;
            font-size: 13.5px;
            margin-bottom: 16px;
            display: flex; align-items: center; gap: 10px;
        }
        .selected-info {
            display: none;
            margin-top: 16px;
            padding: 14px 18px;
            background: rgba(99,102,241,0.08);
            border: 1px solid rgba(99,102,241,0.2);
            border-radius: 10px;
            font-size: 14px; color: var(--text-muted);
            align-items: center; gap: 10px;
        }
        .selected-info.visible { display: flex; }
        .selected-info strong { color: var(--text-main); }
    </style>
</head>
<body class="login-body">
    <div class="login-wrap">
        <div class="login-logo-zone">
            <div class="login-logo-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <h1 class="login-app-name">GTM Tracker</h1>
            <p class="login-app-sub">Tableau de bord commercial</p>
        </div>

        <div class="login-card-new">
            <h2 class="login-card-title">Qui êtes-vous ?</h2>
            <p class="login-card-desc">Sélectionnez votre nom pour accéder à votre espace.</p>

            <?php if ($error): ?>
            <div class="alert-error-new">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <input type="hidden" name="member_id" id="member_id_input" value="">

                <div class="member-grid">
                    <?php foreach ($members as $m): ?>
                    <button type="button"
                        class="member-btn <?= $m['role']==='admin' ? 'admin-btn' : '' ?>"
                        data-id="<?= $m['id'] ?>"
                        data-name="<?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?>"
                        data-firstname="<?= htmlspecialchars($m['firstname'], ENT_QUOTES, 'UTF-8') ?>"
                        data-role="<?= $m['role'] ?>"
                        onclick="selectMember(this)">
                        <div class="member-avatar-lg" style="background:<?= $m['color'] ?>">
                            <?= $m['avatar'] ?>
                        </div>
                        <div>
                            <div class="member-name"><?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="member-role-tag"><?= $m['role']==='admin' ? '🔐 Administrateur' : '👤 Commercial' ?></div>
                        </div>
                    </button>
                    <?php endforeach; ?>
                </div>

                <div class="selected-info" id="selectedInfo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    Sélectionné : <strong id="selectedName"></strong>
                </div>

                <div class="pin-section" id="pinSection">
                    <label for="password" id="passwordLabel">🔑 Mot de passe (votre prénom)</label>
                    <input type="password" id="password" name="password" class="pin-input"
                           placeholder="Entrez votre prénom..." autocomplete="current-password" required>
                    <div id="passwordHint" style="font-size:12px;color:var(--text-muted);margin-top:8px;text-align:center">
                        💡 Votre mot de passe est votre prénom
                    </div>
                </div>

                <button type="submit" class="connect-btn" id="connectBtn" disabled>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    Accéder à mon espace
                </button>
            </form>
        </div>

        <p style="text-align:center;margin-top:20px;font-size:12px;color:var(--text-dim)">
            GTM Tracker • Accès réservé au personnel autorisé
        </p>
    </div>

    <script>
    function selectMember(btn) {
        // Désélectionner tous
        document.querySelectorAll('.member-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const id        = btn.dataset.id;
        const name      = btn.dataset.name;
        const firstname = btn.dataset.firstname;
        const role      = btn.dataset.role;

        document.getElementById('member_id_input').value = id;
        document.getElementById('selectedName').textContent = name;
        document.getElementById('selectedInfo').classList.add('visible');

        const pinSection = document.getElementById('pinSection');
        const pwdInput   = document.getElementById('password');
        const pwdHint    = document.getElementById('passwordHint');

        pinSection.classList.add('visible');
        pwdInput.value = '';
        pwdInput.placeholder = 'Entrez : ' + firstname;
        pwdHint.innerHTML = '💡 Votre mot de passe est votre prénom : <strong>' + firstname + '</strong>';
        document.getElementById('connectBtn').disabled = false;
        setTimeout(() => pwdInput.focus(), 60);
    }
    </script>

    <script>
    if ("serviceWorker" in navigator) {
        window.addEventListener("load", () => {
            navigator.serviceWorker.register("/gtm-tracker/sw.js", { scope: "/gtm-tracker/" });
        });
    }
    </script>
</body>
</html>

