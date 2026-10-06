<?php
// Configuration base de donnees (PostgreSQL Aiven + fallback SQLite)
define("PG_HOST", "pg-1e5f9d3c-onspecialtech-a4ba.f.aivencloud.com");
define("PG_PORT", "20822");
define("PG_DB", "defaultdb");
define("PG_USER", "avnadmin");
define("PG_PASS", getenv("PG_PASS") ?: "");
define("PG_SSLMODE", "require");
define("PG_SCHEMA", "gtm");

define("DB_PATH", __DIR__ . "/../database/gtm.sqlite");
define("UPLOAD_DIR", __DIR__ . "/../uploads/");
define("UPLOAD_URL", "uploads/");
define("APP_NAME", "GTM Tracker");
define("APP_VERSION", "1.0.0");

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        // 1. Tenter la connexion a PostgreSQL Aiven en ligne
        try {
            $dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s;sslmode=%s", PG_HOST, PG_PORT, PG_DB, PG_SSLMODE);
            $pdo = new PDO($dsn, PG_USER, PG_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5
            ]);
            $pdo->exec("SET search_path TO " . PG_SCHEMA . ", public;");
            $GLOBALS['CURRENT_DB_DRIVER'] = 'pgsql';
        } catch (Throwable $e) {
            // 2. Repli automatique sur SQLite local
            try {
                $pdo = new PDO("sqlite:" . DB_PATH);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $pdo->exec("PRAGMA journal_mode=WAL");
                $pdo->exec("PRAGMA foreign_keys=ON");
                $GLOBALS['CURRENT_DB_DRIVER'] = 'sqlite';
            } catch (PDOException $sqle) {
                die("Erreur base de donnees : " . $sqle->getMessage());
            }
        }
    }
    return $pdo;
}

function logActivity($userId, string $action = "", string $details = ""): void {
    try {
        $db = getDB();
        $ip = $_SERVER["REMOTE_ADDR"] ?? "0.0.0.0";
        $db->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)")
           ->execute([$userId, $action, $details, $ip]);
    } catch (Throwable $e) { /* silent */ }
}

function generateCsrfToken(): string {
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function verifyCsrfToken(string $token): bool {
    return isset($_SESSION["csrf_token"]) && hash_equals($_SESSION["csrf_token"], $token);
}

function e($str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, "UTF-8");
}

if (!function_exists('statusBadge')) {
    function statusBadge($s): string {
        $map = [
            'A faire'=>'badge-gray','En cours'=>'badge-info',
            'Terminee'=>'badge-success','Bloquee'=>'badge-danger',
            'Nouveau'=>'badge-gray','Contacte'=>'badge-info',
            'En discussion'=>'badge-accent','Interesse'=>'badge-warning',
            'Rendez-vous'=>'badge-purple','Converti'=>'badge-success',
            'Perdu'=>'badge-danger','A relancer'=>'badge-warning',
            'qualifie'=>'badge-purple','en cours'=>'badge-info',
            'termine'=>'badge-success','annule'=>'badge-danger',
            'converti'=>'badge-success','repondu'=>'badge-success',
            'en attente'=>'badge-warning','closing'=>'badge-purple',
        ];
        return $map[$s] ?? $map[strtolower($s)] ?? 'badge-gray';
    }
}

if (!function_exists('priorityBadge')) {
    function priorityBadge($p): string {
        $map = [
            'Basse'=>'priority-basse','Normale'=>'priority-normale',
            'Haute'=>'priority-haute','Urgente'=>'priority-urgente',
            'basse'=>'priority-basse','normale'=>'priority-normale',
            'haute'=>'priority-haute','urgente'=>'priority-urgente',
        ];
        return $map[$p] ?? 'badge-gray';
    }
}
