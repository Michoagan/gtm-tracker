<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
$user = getCurrentUser();
$csrf = generateCsrfToken();
$currentPage = basename(dirname($_SERVER['PHP_SELF']));
if ($currentPage === 'gtm-tracker') $currentPage = 'home';
$currentFile = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle,ENT_QUOTES,'UTF-8') . ' — ' : '' ?>GTM Tracker</title>
    <meta name="description" content="GTM Tracker — Suivez toutes vos actions Go-To-Market en equipe">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <!-- PWA Configuration -->
    <link rel="manifest" href="/gtm-tracker/manifest.json">
    <meta name="theme-color" content="#ffffff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="GTM Tracker">
    <link rel="apple-touch-icon" href="/gtm-tracker/icon-192.png">
    <link rel="icon" type="image/svg+xml" href="/gtm-tracker/icon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="/gtm-tracker/icon-192.png">
    <link rel="stylesheet" href="/gtm-tracker/css/style.css">
</head>
<body>
<div class="app-layout">
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        </div>
        <span class="brand-text">GTM Tracker</span>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">
            <span class="nav-label">Principal</span>
            <a href="/gtm-tracker/dashboard.php" class="nav-link <?php echo ($currentFile==='dashboard.php')?'active':'' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Dashboard
            </a>
            <?php if (isAdmin()): ?>
            <a href="/gtm-tracker/my-space.php" class="nav-link <?php echo ($currentFile==='my-space.php')?'active':'' ?>" style="border-left: 2px solid var(--accent); background: rgba(99,102,241,0.06)">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Mon Espace Dédié
            </a>
            <a href="/gtm-tracker/actions/index.php" class="nav-link <?php echo ($currentPage==='actions')?'active':'' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                Actions
            </a>
            
            <a href="/gtm-tracker/help/index.php" class="nav-link <?php echo ($currentPage==='help')?'active':'' ?>" style="position:relative">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                SOS &amp; Entraide
            </a>
            <?php endif; ?>
            <a href="/gtm-tracker/prospects/index.php" class="nav-link <?php echo ($currentPage==='prospects')?'active':'' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Prospects
            </a>
        </div>
        <div class="nav-section">
            <span class="nav-label">Configuration</span>
            <a href="/gtm-tracker/strategies/index.php" class="nav-link <?php echo ($currentPage==='strategies')?'active':'' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                Strategies
            </a>
            <a href="/gtm-tracker/channels/index.php" class="nav-link <?php echo ($currentPage==='channels')?'active':'' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.65 3.43 2 2 0 0 1 3.62 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.75a16 16 0 0 0 6.29 6.29l1.61-1.61a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                Canaux
            </a>
        </div>
        <div class="nav-section">
            <span class="nav-label">Analyse</span>
            <a href="/gtm-tracker/reports/report.php" class="nav-link <?php echo ($currentPage==='reports')?'active':'' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                Rapports &amp; Bilan
            </a>
            <a href="/gtm-tracker/my-activities.php" class="nav-link <?php echo ($currentFile==='my-activities.php')?'active':'' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Mes activites
            </a>
        </div>
        <?php if (isAdmin()): ?>
        <div class="nav-section">
            <span class="nav-label">Administration</span>
            <a href="/gtm-tracker/admin/users.php" class="nav-link <?php echo ($currentPage==='admin'&&$currentFile==='users.php')?'active':'' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Inscrire le personnel
            </a>
            <a href="/gtm-tracker/admin/logs.php" class="nav-link <?php echo ($currentPage==='admin'&&$currentFile==='logs.php')?'active':'' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                Journal
            </a>
        </div>
        <?php endif; ?>
    </nav>
        <div style="padding: 10px 14px 0;">
        <button id="pwaManualInstallBtn" style="
            display: none;
            width: 100%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 9px 12px;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px -2px rgba(99, 102, 241, 0.4);
            transition: all 0.2s ease;
        ">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Installer l'app
        </button>
    </div>
    <div class="sidebar-footer">
        <a href="/gtm-tracker/profile.php" class="user-info" style="text-decoration:none;cursor:pointer" title="Voir mon profil">
            <div class="user-avatar"><?= strtoupper(substr($user['name'],0,1)) ?></div>
            <div class="user-details">
                <span class="user-name"><?= htmlspecialchars($user['name'],ENT_QUOTES,'UTF-8') ?></span>
                <span class="user-role"><?= $user['role']==='admin'?'Administrateur':'Collaborateur' ?> • Profil</span>
            </div>
        </a>
        <a href="/gtm-tracker/logout.php" class="logout-btn" title="Deconnexion">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </a>
    </div>
</aside>
<main class="main-content">
    <div class="topbar">
        <button class="menu-toggle" id="menuToggle">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <h1 class="page-title"><?= isset($pageTitle)?htmlspecialchars($pageTitle,ENT_QUOTES,'UTF-8'):'Dashboard' ?></h1>
        <div class="topbar-right">
            <span class="topbar-date"><?= date('d/m/Y') ?></span>
            <div class="topbar-user">
                <div class="user-avatar sm"><?= strtoupper(substr($user['name'],0,1)) ?></div>
                <span><?= htmlspecialchars($user['name'],ENT_QUOTES,'UTF-8') ?></span>
            </div>
        </div>
    </div>
    <div class="page-content">

