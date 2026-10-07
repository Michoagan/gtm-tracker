<?php
require_once __DIR__ . '/config/database.php';

function initDatabase(): void {
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'user',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS strategies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT,
        created_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS channels (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS prospects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        company TEXT,
        contact TEXT,
        channel_id INTEGER,
        status TEXT DEFAULT 'Nouveau',
        assigned_to INTEGER,
        follow_up_status TEXT DEFAULT 'A relancer',
        next_followup_date DATE,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(channel_id) REFERENCES channels(id) ON DELETE SET NULL,
        FOREIGN KEY(assigned_to) REFERENCES users(id) ON DELETE SET NULL
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS actions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        assigned_by INTEGER,
        strategy_id INTEGER,
        channel_id INTEGER,
        prospect_id INTEGER,
        title TEXT NOT NULL,
        description TEXT,
        status TEXT DEFAULT 'A faire',
        priority TEXT DEFAULT 'Normale',
        result TEXT,
        comment TEXT,
        action_date DATE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY(assigned_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY(strategy_id) REFERENCES strategies(id) ON DELETE SET NULL,
        FOREIGN KEY(channel_id) REFERENCES channels(id) ON DELETE SET NULL,
        FOREIGN KEY(prospect_id) REFERENCES prospects(id) ON DELETE SET NULL
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS conversations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        prospect_id INTEGER,
        user_id INTEGER NOT NULL,
        strategy_id INTEGER,
        channel_id INTEGER,
        message_sent TEXT,
        response_received TEXT,
        next_action TEXT,
        status TEXT DEFAULT 'Nouveau',
        conversation_date DATE NOT NULL,
        comment TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(prospect_id) REFERENCES prospects(id) ON DELETE SET NULL,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY(strategy_id) REFERENCES strategies(id) ON DELETE SET NULL,
        FOREIGN KEY(channel_id) REFERENCES channels(id) ON DELETE SET NULL
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS attachments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        action_id INTEGER,
        conversation_id INTEGER,
        original_name TEXT NOT NULL,
        file_path TEXT NOT NULL,
        file_type TEXT NOT NULL,
        file_size INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY(action_id) REFERENCES actions(id) ON DELETE CASCADE,
        FOREIGN KEY(conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS activity_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action TEXT NOT NULL,
        details TEXT,
        ip_address TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS help_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        action_id INTEGER,
        prospect_id INTEGER,
        title TEXT NOT NULL,
        message TEXT NOT NULL,
        status TEXT DEFAULT 'Ouvert',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY(action_id) REFERENCES actions(id) ON DELETE SET NULL,
        FOREIGN KEY(prospect_id) REFERENCES prospects(id) ON DELETE SET NULL
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS help_comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        request_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        comment TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(request_id) REFERENCES help_requests(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Seed admin
    $admin = $db->query("SELECT id FROM users WHERE email='admin@gtmtracker.com'")->fetch();
    if (!$admin) {
        $hash = password_hash('Admin@2024!', PASSWORD_BCRYPT);
        $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)")
           ->execute(['Administrateur', 'admin@gtmtracker.com', $hash, 'admin']);
        echo "Admin cree.<br>";
    }
    $adminId = $db->query("SELECT id FROM users WHERE role='admin'")->fetch()['id'];

    // Seed strategies
    $strats = $db->query("SELECT COUNT(*) as c FROM strategies")->fetch();
    if ($strats['c'] == 0) {
        $rows = [
            ['Acquisition','Attirer de nouveaux prospects et clients'],
            ['Prospection','Contacter directement des prospects cibles'],
            ['Contenu','Creer du contenu pour generer des leads'],
            ['Partenariat','Developper des alliances strategiques'],
            ['Fidelisation','Retenir et satisfaire les clients existants'],
            ['Reseaux sociaux','Actions sur les plateformes sociales'],
            ['Referral','Programme de recommandation et parrainage'],
            ['Conversion','Transformer les prospects en clients'],
        ];
        $stmt = $db->prepare("INSERT INTO strategies (name,description,created_by) VALUES (?,?,?)");
        foreach ($rows as $r) $stmt->execute([$r[0],$r[1],$adminId]);
        echo "Strategies inserees.<br>";
    }
    // Seed channels
    $chans = $db->query("SELECT COUNT(*) as c FROM channels")->fetch();
    if ($chans['c'] == 0) {
        $chs = ['LinkedIn','WhatsApp','Email','Telephone','Facebook','Instagram','TikTok','Rencontre physique','Evenement','Autre'];
        $stmt = $db->prepare("INSERT INTO channels (name) VALUES (?)");
        foreach ($chs as $c) $stmt->execute([$c]);
        echo "Canaux inseres.<br>";
    }
    echo "<br><strong>Base de donnees initialisee avec succes!</strong>";
}

initDatabase();