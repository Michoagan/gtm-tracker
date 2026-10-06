<?php
require_once __DIR__ . "/../config/database.php";

// Membres du personnel définis statiquement (sans base de données)
function getMembers(): array {
    return [
        0 => ['id' => 0, 'name' => 'Admin',          'email' => 'onspecial@gmail.com',      'role' => 'admin'],
        1 => ['id' => 1, 'name' => 'GABIN SOKINDJI',  'email' => 'gabin.sokindji@getspecial.com',  'role' => 'collaborator'],
        2 => ['id' => 2, 'name' => 'TRESOR NEKOUA',   'email' => 'tresor.nekoua@getspecial.com',   'role' => 'collaborator'],
        3 => ['id' => 3, 'name' => 'ADJIBI DAHLIA',   'email' => 'adjibi.dahlia@getspecial.com',   'role' => 'collaborator'],
        4 => ['id' => 4, 'name' => 'MARIO MITCHOAGAN', 'email' => 'mario.mitchoagan@getspecial.com', 'role' => 'collaborator'],
    ];
}

function requireLogin(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION["user_id"]) && $_SESSION["user_id"] !== 0) {
        header("Location: /gtm-tracker/login.php");
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if ($_SESSION["user_role"] !== "admin") {
        header("Location: /gtm-tracker/dashboard.php?error=access_denied");
        exit;
    }
}

function getCurrentUser(): ?array {
    if (!isset($_SESSION["user_id"])) return null;
    return [
        "id"    => $_SESSION["user_id"],
        "name"  => $_SESSION["user_name"],
        "email" => $_SESSION["user_email"],
        "role"  => $_SESSION["user_role"],
    ];
}

function isAdmin(): bool {
    return ($_SESSION["user_role"] ?? "") === "admin";
}

