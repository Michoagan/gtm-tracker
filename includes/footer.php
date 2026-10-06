    </div><!-- end page-content -->
</main>
</div><!-- end app-layout -->
<!-- Barre de navigation tactile mobile (PWA Friendly) -->
<nav class="mobile-bottom-nav">
    <a href="/gtm-tracker/dashboard.php" class="<?= (basename($_SERVER['PHP_SELF'])==='dashboard.php')?'active':'' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        <span>Accueil</span>
    </a>
    <?php if (isAdmin()): ?>
    <a href="/gtm-tracker/my-space.php" class="<?= (basename($_SERVER['PHP_SELF'])==='my-space.php')?'active':'' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <span>Mon Espace</span>
    </a>
    <a href="/gtm-tracker/actions/index.php" class="<?= (basename(dirname($_SERVER['PHP_SELF']))==='actions')?'active':'' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        <span>Actions</span>
    </a>
    <?php endif; ?>
    <a href="/gtm-tracker/prospects/index.php" class="<?= (basename(dirname($_SERVER['PHP_SELF']))==='prospects')?'active':'' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span>Prospects</span>
    </a>
    <?php if (isAdmin()): ?>
    <a href="/gtm-tracker/help/index.php" class="<?= (basename(dirname($_SERVER['PHP_SELF']))==='help')?'active':'' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <span>SOS</span>
    </a>
    <?php endif; ?>
</nav>

<?php if (isset($flash)): ?>
<div class="flash-message flash-<?= htmlspecialchars($flash['type'],ENT_QUOTES,'UTF-8') ?>" id="flashMsg">
    <?= htmlspecialchars($flash['message'],ENT_QUOTES,'UTF-8') ?>
    <button onclick="this.parentElement.style.display='none'" class="flash-close">&times;</button>
</div>
<?php endif; ?>
<script src="/gtm-tracker/js/app.js"></script>
</body>
</html>
