<?php
// =============================================================================
// Migration SQLite -> PostgreSQL (Aiven)
// =============================================================================

echo "========================================================\n";
echo "   Migration GTM Tracker : SQLite -> PostgreSQL (Aiven)\n";
echo "========================================================\n\n";

$sqlitePath = __DIR__ . '/database/gtm.sqlite';
if (!file_exists($sqlitePath)) {
    die("Fichier SQLite introuvable : $sqlitePath\n");
}

$sqlite = new PDO("sqlite:" . $sqlitePath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);
echo "[1/4] Connecte a SQLite locale ($sqlitePath)\n";

$pgHost = 'pg-1e5f9d3c-onspecialtech-a4ba.f.aivencloud.com';
$pgPort = '20822';
$pgDb   = 'defaultdb';
$pgUser = 'avnadmin';
$pgPass = getenv('PG_PASS') ?: '';

$pgDsn = "pgsql:host=$pgHost;port=$pgPort;dbname=$pgDb;sslmode=require";
$pg = new PDO($pgDsn, $pgUser, $pgPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);
echo "[2/4] Connecte a PostgreSQL Aiven ($pgHost)\n";

// 1. Creation du schema gtm
$pg->exec("CREATE SCHEMA IF NOT EXISTS gtm;");
$pg->exec("SET search_path TO gtm, public;");
echo "      Schema 'gtm' initialise.\n\n";

// 2. Creation des tables
echo "[3/4] Creation des tables dans le schema gtm...\n";

$tables = [
    'users' => "CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    'strategies' => "CREATE TABLE IF NOT EXISTS strategies (
        id SERIAL PRIMARY KEY,
        name TEXT NOT NULL,
        description TEXT,
        created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    'channels' => "CREATE TABLE IF NOT EXISTS channels (
        id SERIAL PRIMARY KEY,
        name TEXT NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    'prospects' => "CREATE TABLE IF NOT EXISTS prospects (
        id SERIAL PRIMARY KEY,
        name TEXT NOT NULL,
        company TEXT,
        contact TEXT,
        channel_id INTEGER REFERENCES channels(id) ON DELETE SET NULL,
        status TEXT DEFAULT 'Nouveau',
        assigned_to INTEGER REFERENCES users(id) ON DELETE SET NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    'actions' => "CREATE TABLE IF NOT EXISTS actions (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        strategy_id INTEGER REFERENCES strategies(id) ON DELETE SET NULL,
        channel_id INTEGER REFERENCES channels(id) ON DELETE SET NULL,
        title TEXT NOT NULL,
        description TEXT,
        status TEXT DEFAULT 'A faire',
        priority TEXT DEFAULT 'Normale',
        result TEXT,
        comment TEXT,
        action_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    'conversations' => "CREATE TABLE IF NOT EXISTS conversations (
        id SERIAL PRIMARY KEY,
        prospect_id INTEGER REFERENCES prospects(id) ON DELETE SET NULL,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        strategy_id INTEGER REFERENCES strategies(id) ON DELETE SET NULL,
        channel_id INTEGER REFERENCES channels(id) ON DELETE SET NULL,
        message_sent TEXT,
        response_received TEXT,
        next_action TEXT,
        status TEXT DEFAULT 'Nouveau',
        conversation_date DATE NOT NULL,
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    'attachments' => "CREATE TABLE IF NOT EXISTS attachments (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        action_id INTEGER REFERENCES actions(id) ON DELETE CASCADE,
        conversation_id INTEGER REFERENCES conversations(id) ON DELETE CASCADE,
        original_name TEXT NOT NULL,
        file_path TEXT NOT NULL,
        file_type TEXT NOT NULL,
        file_size INTEGER NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    'activity_log' => "CREATE TABLE IF NOT EXISTS activity_log (
        id SERIAL PRIMARY KEY,
        user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
        action TEXT NOT NULL,
        details TEXT,
        ip_address TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($tables as $name => $sql) {
    $pg->exec($sql);
    echo "  + Table '$name' prete.\n";
}

echo "\n[4/4] Migration des donnees...\n";

// Ordre d'insertion pour respecter les cles etrangeres
$order = ['users', 'strategies', 'channels', 'prospects', 'actions', 'conversations', 'attachments', 'activity_log'];

foreach ($order as $table) {
    // Vider la table distante en cas de re-execution
    $pg->exec("TRUNCATE TABLE $table CASCADE");

    $rows = $sqlite->query("SELECT * FROM $table")->fetchAll();
    if (empty($rows)) {
        echo "  - $table : 0 enregistrement a transferer.\n";
        continue;
    }

    $columns = array_keys($rows[0]);
    $colsStr = implode(', ', $columns);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));

    $stmt = $pg->prepare("INSERT INTO $table ($colsStr) VALUES ($placeholders)");

    $count = 0;
    foreach ($rows as $row) {
        $stmt->execute(array_values($row));
        $count++;
    }

    // Mettre a jour la sequence PostgreSQL pour que les futurs auto-increments soient corrects
    $seq = "gtm.{$table}_id_seq";
    $pg->exec("SELECT setval('$seq', COALESCE((SELECT MAX(id) FROM $table), 1))");

    echo "  -> $table : $count enregistrement(s) migre(s) avec succes.\n";
}

echo "\n========================================================\n";
echo "   Migration terminee avec SUCCES !\n";
echo "========================================================\n";
