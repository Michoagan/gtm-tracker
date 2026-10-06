<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();

echo "Seeding additional team members...\n";
$users = [
    ['Sarah Conner', 'sarah@gtmtracker.com', 'user'],
    ['Thomas Dubois', 'thomas@gtmtracker.com', 'user'],
    ['Leila Benali', 'leila@gtmtracker.com', 'user'],
    ['Alexandre Mercier', 'alexandre@gtmtracker.com', 'user']
];

$passHash = password_hash('Password@123', PASSWORD_BCRYPT);
$userStmt = $db->prepare("INSERT OR IGNORE INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
foreach ($users as $u) {
    // Check if exists
    $exists = $db->prepare("SELECT id FROM users WHERE email = ?");
    $exists->execute([$u[1]]);
    if (!$exists->fetch()) {
        $userStmt->execute([$u[0], $u[1], $passHash, $u[2]]);
    }
}

$allUsers = $db->query("SELECT id, name FROM users")->fetchAll(PDO::FETCH_ASSOC);
$allChannels = $db->query("SELECT id, name FROM channels")->fetchAll(PDO::FETCH_ASSOC);
$allStrategies = $db->query("SELECT id, name FROM strategies")->fetchAll(PDO::FETCH_ASSOC);

echo "Users in system: " . count($allUsers) . "\n";
echo "Channels in system: " . count($allChannels) . "\n";
echo "Strategies in system: " . count($allStrategies) . "\n";

// Add prospects if none
$prosCount = $db->query("SELECT COUNT(*) FROM prospects")->fetchColumn();
if ($prosCount == 0) {
    echo "Seeding prospects...\n";
    $prospects = [
        ['Marc Laurent', 'FinTech Corp', '+33 6 12 34 56 78 / marc@fintech.io', 1, 'qualifie', $allUsers[0]['id']],
        ['Sophie Martin', 'DataFlow SAS', 'sophie.m@dataflow.fr', 2, 'en cours', $allUsers[1]['id'] ?? $allUsers[0]['id']],
        ['David Cohen', 'Apex Retail', 'd.cohen@apexretail.com', 3, 'converti', $allUsers[2]['id'] ?? $allUsers[0]['id']],
        ['Camille Leroy', 'GreenMobility', 'camille@greenmobility.eu', 1, 'nouveau', $allUsers[3]['id'] ?? $allUsers[0]['id']],
        ['Julien Petit', 'CloudScale Technologies', 'julien@cloudscale.net', 4, 'en cours', $allUsers[0]['id']],
        ['Nathalie Roux', 'BioHealth Solutions', 'n.roux@biohealth.org', 1, 'qualifie', $allUsers[1]['id'] ?? $allUsers[0]['id']],
        ['Karim Belkacem', 'LogiTrans Group', 'karim@logitrans.com', 8, 'converti', $allUsers[2]['id'] ?? $allUsers[0]['id']],
        ['Elena Rossi', 'OmniCommerce', 'elena@omnicommerce.it', 3, 'en cours', $allUsers[3]['id'] ?? $allUsers[0]['id']]
    ];

    $pStmt = $db->prepare("INSERT INTO prospects (name, company, contact, channel_id, status, assigned_to) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($prospects as $p) {
        $pStmt->execute($p);
    }
}

// Add actions if none
$actCount = $db->query("SELECT COUNT(*) FROM actions")->fetchColumn();
if ($actCount == 0) {
    echo "Seeding actions...\n";
    $actions = [
        [
            $allUsers[0]['id'], $allStrategies[0]['id'], $allChannels[0]['id'],
            'Prospection active CTOs FinTech sur LinkedIn',
            'Campagne de prise de contact ciblee avec pitch valeur MVP',
            'termine', 'haute', 'Succes',
            '12 reponses obtenues, 4 demos planifiees.',
            date('Y-m-d', strtotime('-5 days'))
        ],
        [
            $allUsers[1]['id'] ?? $allUsers[0]['id'], $allStrategies[1]['id'], $allChannels[2]['id'],
            'Cold Emailing sequence SDR Q4',
            'Sequence email personnalisee vers 45 comptes cibles B2B',
            'termine', 'haute', 'Partiel',
            'Taux d ouverture a 58%, 3 retours positifs a relancer.',
            date('Y-m-d', strtotime('-3 days'))
        ],
        [
            $allUsers[2]['id'] ?? $allUsers[0]['id'], $allStrategies[2]['id'], $allChannels[1]['id'],
            'Relance WhatsApp VIP Retail',
            'Partage de la derniere etude de cas ROI a nos contacts privilegies',
            'en cours', 'urgente', 'En attente',
            'Envoi effectue ce matin, en attente de retour de David Cohen.',
            date('Y-m-d', strtotime('-1 days'))
        ],
        [
            $allUsers[3]['id'] ?? $allUsers[0]['id'], $allStrategies[3]['id'], $allChannels[7]['id'],
            'Participation au salon Tech Summit Paris',
            'Rencontres 1-to-1 et networking avec les directeurs innovation',
            'termine', 'haute', 'Succes',
            '20 cartes de visite, 8 leads tres chauds a qualifier.',
            date('Y-m-d', strtotime('-7 days'))
        ],
        [
            $allUsers[0]['id'], $allStrategies[4]['id'], $allChannels[3]['id'],
            'Appels de qualification pipeline entrant',
            'Qualification telephonique des leads enregistres via formulaire web',
            'en cours', 'normale', 'En attente',
            '6 appels prevus aujourd hui.',
            date('Y-m-d')
        ],
        [
            $allUsers[1]['id'] ?? $allUsers[0]['id'], $allStrategies[5]['id'], $allChannels[0]['id'],
            'Publication Article thought leadership & Lead Magnet',
            'Post carousel sur les best practices GTM avec lien calendly',
            'termine', 'normale', 'Succes',
            '140 reactions et 5 demandes entrantes directes.',
            date('Y-m-d', strtotime('-2 days'))
        ]
    ];

    $aStmt = $db->prepare("INSERT INTO actions (user_id, strategy_id, channel_id, title, description, status, priority, result, comment, action_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($actions as $a) {
        $aStmt->execute($a);
    }
}

// Add conversations if none
$convCount = $db->query("SELECT COUNT(*) FROM conversations")->fetchColumn();
if ($convCount == 0) {
    echo "Seeding conversations...\n";
    $allPros = $db->query("SELECT id FROM prospects")->fetchAll(PDO::FETCH_COLUMN);
    $conversations = [
        [
            $allPros[0] ?? 1, $allUsers[0]['id'], $allStrategies[0]['id'], $allChannels[0]['id'],
            'Bonjour Marc, suite a vos actualites de levee de fonds, j aimerais echanger 15 min sur vos defis GTM.',
            'Bonjour ! Sujet tres pertinent en ce moment. Dispo jeudi a 14h ?',
            'Envoyer invitation Google Meet et deck de presentation synthetique',
            'repondu', date('Y-m-d', strtotime('-4 days')), 'Prospect tres receptif.'
        ],
        [
            $allPros[1] ?? 1, $allUsers[1]['id'] ?? $allUsers[0]['id'], $allStrategies[1]['id'], $allChannels[2]['id'],
            'Sophie, nous avons aide une entreprise similiaire a reduire son CAC de 35%. Seriez-vous ouverte a un echange ?',
            'Interessant. Pouvez-vous m envoyer une etude de cas detaillee avant qu on prenne RDV ?',
            'Transmettre le case study DataFlow & proposer 2 creneaux la semaine prochaine',
            'repondu', date('Y-m-d', strtotime('-2 days')), 'Besoin d elements concrets de preuve sociale.'
        ],
        [
            $allPros[2] ?? 1, $allUsers[2]['id'] ?? $allUsers[0]['id'], $allStrategies[2]['id'], $allChannels[1]['id'],
            'David, felicitations pour le deploiement Apex ! Avez-vous pu tester la fonctionnalite tracker ?',
            'Oui au top ! On valide le bon de commande cet apres-midi.',
            'Finaliser la signature electronique et planifier l onboarding',
            'closing', date('Y-m-d', strtotime('-1 days')), 'Deal quasi clos !'
        ],
        [
            $allPros[3] ?? 1, $allUsers[3]['id'] ?? $allUsers[0]['id'], $allStrategies[0]['id'], $allChannels[0]['id'],
            'Camille, j ai decouvert votre initiative GreenMobility, bravo pour l impact !',
            NULL,
            'Relancer a J+4 si pas de reponse',
            'en attente', date('Y-m-d', strtotime('-1 days')), 'Prise de contact initiate.'
        ]
    ];

    $cStmt = $db->prepare("INSERT INTO conversations (prospect_id, user_id, strategy_id, channel_id, message_sent, response_received, next_action, status, conversation_date, comment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($conversations as $c) {
        $cStmt->execute($c);
    }
}

echo "Seed completed successfully!\n";
