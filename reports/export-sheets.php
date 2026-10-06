<?php
/**
 * Export du bilan au format .xlsx (compatible Google Sheets) — réservé à l'Admin.
 * Google Sheets ouvre nativement les .xlsx : onglets, en-têtes et largeurs conservés.
 */
ob_start(); // neutralise tout BOM / sortie parasite des includes avant l'envoi du fichier
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../auth/auth.php';
requireAdmin();

if (!class_exists('ZipArchive')) {
    ob_end_clean();
    http_response_code(500);
    exit("L'extension PHP 'zip' est requise pour l'export Google Sheets.");
}

$db = getDB();
$isDate = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
$dateFrom = $isDate($_GET['date_from'] ?? null) ? $_GET['date_from'] : date('Y-m-01');
$dateTo   = $isDate($_GET['date_to']   ?? null) ? $_GET['date_to']   : date('Y-m-d');

$members = getMembers();
$memberName = fn($id) => $members[(int)$id]['name'] ?? ('#' . $id);

$q = function (string $sql, array $p = []) use ($db) {
    $st = $db->prepare($sql);
    $st->execute($p);
    return $st->fetchAll(PDO::FETCH_ASSOC);
};
$range = [$dateFrom, $dateTo];

// ---------- Données ----------
$actions = $q("SELECT a.id, a.action_date, a.user_id, a.title, a.description, a.status, a.priority, a.result, a.comment,
                      s.name AS strategy, ch.name AS channel
               FROM actions a
               LEFT JOIN strategies s ON s.id = a.strategy_id
               LEFT JOIN channels ch ON ch.id = a.channel_id
               WHERE a.action_date BETWEEN ? AND ?
               ORDER BY a.action_date DESC, a.id DESC", $range);

$convs = $q("SELECT c.id, c.conversation_date, c.user_id, p.name AS prospect, p.company, s.name AS strategy, ch.name AS channel,
                    c.message_sent, c.response_received, c.status, c.next_action, c.comment
             FROM conversations c
             LEFT JOIN prospects p ON p.id = c.prospect_id
             LEFT JOIN strategies s ON s.id = c.strategy_id
             LEFT JOIN channels ch ON ch.id = c.channel_id
             WHERE c.conversation_date BETWEEN ? AND ?
             ORDER BY c.conversation_date DESC, c.id DESC", $range);

$prospects = $q("SELECT p.id, p.name, p.company, p.contact, ch.name AS channel, p.status, p.assigned_to, p.created_at
                 FROM prospects p LEFT JOIN channels ch ON ch.id = p.channel_id
                 ORDER BY p.created_at DESC");

// ---------- Agrégats ----------
$hasResp = fn($c) => trim((string)$c['response_received']) !== '';
$pct = fn($a, $b) => $b > 0 ? round($a / $b, 4) : 0;

$byMember = [];
foreach ($members as $id => $m) {
    $byMember[$id] = ['name' => $m['name'], 'actions' => 0, 'done' => 0, 'convs' => 0, 'resps' => 0, 'interest' => 0, 'rdv' => 0, 'convts' => 0];
}
foreach ($actions as $a) {
    $id = (int)$a['user_id'];
    if (!isset($byMember[$id])) continue;
    $byMember[$id]['actions']++;
    if ($a['status'] === 'Terminee') $byMember[$id]['done']++;
}
foreach ($convs as $c) {
    $id = (int)$c['user_id'];
    if (!isset($byMember[$id])) continue;
    $byMember[$id]['convs']++;
    if ($hasResp($c)) $byMember[$id]['resps']++;
    if (in_array($c['status'], ['Interesse', 'Rendez-vous', 'Converti'], true)) $byMember[$id]['interest']++;
    if ($c['status'] === 'Rendez-vous') $byMember[$id]['rdv']++;
    if ($c['status'] === 'Converti') $byMember[$id]['convts']++;
}

$tot = ['actions' => 0, 'done' => 0, 'convs' => 0, 'resps' => 0, 'interest' => 0, 'rdv' => 0, 'convts' => 0];
foreach ($byMember as $m) foreach ($tot as $k => $_) $tot[$k] += $m[$k];

$group = function (array $rows, string $key, ?callable $extra = null) {
    $out = [];
    foreach ($rows as $r) {
        $k = $r[$key] ?: '(non défini)';
        $out[$k] ??= ['actions' => 0, 'results' => 0];
        $out[$k]['actions']++;
        if (trim((string)($r['result'] ?? '')) !== '') $out[$k]['results']++;
    }
    uasort($out, fn($a, $b) => $b['actions'] <=> $a['actions']);
    return $out;
};
$byStrat = $group($actions, 'strategy');
$byChan  = $group($actions, 'channel');

// ---------- Construction des onglets ----------
// Chaque cellule : valeur brute, ou ['v' => valeur, 's' => style]. Styles : 1=en-tête, 2=titre, 3=pourcentage, 4=gras
$H = fn(array $cols) => array_map(fn($c) => ['v' => $c, 's' => 1], $cols);
$sheets = [];

$sheets[] = ['name' => 'Résumé', 'widths' => [34, 18], 'freeze' => false, 'rows' => [
    [['v' => 'GTM Tracker — Bilan équipe', 's' => 2]],
    ['Période', "Du $dateFrom au $dateTo"],
    ['Généré le', date('Y-m-d H:i')],
    [],
    $H(['Indicateur', 'Valeur']),
    ['Actions totales', $tot['actions']],
    ['Actions terminées', $tot['done']],
    ['Conversations', $tot['convs']],
    ['Réponses reçues', $tot['resps']],
    ['Intéressés', $tot['interest']],
    ['Rendez-vous', $tot['rdv']],
    ['Conversions', $tot['convts']],
    ['Taux de réponse', ['v' => $pct($tot['resps'], $tot['convs']), 's' => 3]],
    ['Taux de conversion', ['v' => $pct($tot['convts'], $tot['convs']), 's' => 3]],
]];

$rows = [$H(['Membre', 'Actions', 'Terminées', 'Conversations', 'Réponses', 'Intéressés', 'Rendez-vous', 'Conversions', 'Taux réponse', 'Taux conversion'])];
foreach ($byMember as $m) {
    $rows[] = [$m['name'], $m['actions'], $m['done'], $m['convs'], $m['resps'], $m['interest'], $m['rdv'], $m['convts'],
        ['v' => $pct($m['resps'], $m['convs']), 's' => 3], ['v' => $pct($m['convts'], $m['convs']), 's' => 3]];
}
$rows[] = [['v' => 'TOTAL', 's' => 4], ['v' => $tot['actions'], 's' => 4], ['v' => $tot['done'], 's' => 4], ['v' => $tot['convs'], 's' => 4],
    ['v' => $tot['resps'], 's' => 4], ['v' => $tot['interest'], 's' => 4], ['v' => $tot['rdv'], 's' => 4], ['v' => $tot['convts'], 's' => 4],
    ['v' => $pct($tot['resps'], $tot['convs']), 's' => 3], ['v' => $pct($tot['convts'], $tot['convs']), 's' => 3]];
$sheets[] = ['name' => 'Par membre', 'widths' => [24, 10, 11, 15, 11, 11, 13, 13, 13, 15], 'freeze' => true, 'rows' => $rows];

$rows = [$H(['Date', 'Membre', 'Titre', 'Stratégie', 'Canal', 'Statut', 'Priorité', 'Résultat', 'Description', 'Commentaire'])];
foreach ($actions as $a) {
    $rows[] = [$a['action_date'], $memberName($a['user_id']), $a['title'], $a['strategy'], $a['channel'], $a['status'], $a['priority'], $a['result'], $a['description'], $a['comment']];
}
$sheets[] = ['name' => 'Actions', 'widths' => [12, 20, 32, 18, 16, 13, 11, 28, 40, 30], 'freeze' => true, 'rows' => $rows];

$rows = [$H(['Date', 'Membre', 'Prospect', 'Entreprise', 'Stratégie', 'Canal', 'Statut', 'Message envoyé', 'Réponse reçue', 'Prochaine action', 'Commentaire'])];
foreach ($convs as $c) {
    $rows[] = [$c['conversation_date'], $memberName($c['user_id']), $c['prospect'], $c['company'], $c['strategy'], $c['channel'], $c['status'], $c['message_sent'], $c['response_received'], $c['next_action'], $c['comment']];
}
$sheets[] = ['name' => 'Conversations', 'widths' => [12, 20, 22, 20, 18, 16, 13, 36, 36, 24, 30], 'freeze' => true, 'rows' => $rows];

$rows = [$H(['Nom', 'Entreprise', 'Contact', 'Canal', 'Statut', 'Assigné à', 'Créé le'])];
foreach ($prospects as $p) {
    $rows[] = [$p['name'], $p['company'], $p['contact'], $p['channel'], $p['status'], $p['assigned_to'] !== null && $p['assigned_to'] !== '' ? $memberName($p['assigned_to']) : '', $p['created_at']];
}
$sheets[] = ['name' => 'Prospects', 'widths' => [24, 22, 26, 16, 14, 20, 18], 'freeze' => true, 'rows' => $rows];

$rows = [$H(['Stratégie', 'Actions', 'Avec résultat'])];
foreach ($byStrat as $k => $v) $rows[] = [$k, $v['actions'], $v['results']];
$rows[] = [];
$rows[] = $H(['Canal', 'Actions', 'Avec résultat']);
foreach ($byChan as $k => $v) $rows[] = [$k, $v['actions'], $v['results']];
$sheets[] = ['name' => 'Stratégies & canaux', 'widths' => [28, 12, 15], 'freeze' => false, 'rows' => $rows];

// ---------- Écriture XLSX ----------
function xlsxEsc($s): string {
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string)$s);
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}
function xlsxCol(int $i): string {
    $s = '';
    for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s;
    return $s;
}
function xlsxSheet(array $sheet): string {
    $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
       . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    if ($sheet['freeze']) {
        $x .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
    }
    $x .= '<cols>';
    foreach ($sheet['widths'] as $i => $w) $x .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
    $x .= '</cols><sheetData>';
    foreach ($sheet['rows'] as $r => $row) {
        $x .= '<row r="' . ($r + 1) . '">';
        foreach (array_values($row) as $c => $cell) {
            $v = is_array($cell) ? $cell['v'] : $cell;
            $s = is_array($cell) ? (int)$cell['s'] : 0;
            if ($v === null || $v === '') continue;
            $ref = xlsxCol($c) . ($r + 1);
            $sa = $s ? ' s="' . $s . '"' : '';
            if (is_int($v) || is_float($v)) {
                $x .= '<c r="' . $ref . '"' . $sa . '><v>' . $v . '</v></c>';
            } else {
                $x .= '<c r="' . $ref . '"' . $sa . ' t="inlineStr"><is><t xml:space="preserve">' . xlsxEsc($v) . '</t></is></c>';
            }
        }
        $x .= '</row>';
    }
    return $x . '</sheetData></worksheet>';
}

$tmp = tempnam(sys_get_temp_dir(), 'gtm');
$zip = new ZipArchive();
$zip->open($tmp, ZipArchive::OVERWRITE);

$ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
    . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
$wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
$wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
foreach ($sheets as $i => $sh) {
    $n = $i + 1;
    $zip->addFromString("xl/worksheets/sheet$n.xml", xlsxSheet($sh));
    $ct .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    $wb .= '<sheet name="' . xlsxEsc(mb_substr($sh['name'], 0, 31)) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
    $wbRels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
}
$wbRels .= '<Relationship Id="rId' . (count($sheets) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';

$zip->addFromString('[Content_Types].xml', $ct . '</Types>');
$zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
$zip->addFromString('xl/workbook.xml', $wb . '</sheets></workbook>');
$zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
$zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<numFmts count="1"><numFmt numFmtId="164" formatCode="0.0%"/></numFmts>'
    . '<fonts count="4"><font><sz val="11"/><name val="Arial"/></font>'
    . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>'
    . '<font><b/><sz val="16"/><color rgb="FF4F46E5"/><name val="Arial"/></font>'
    . '<font><b/><sz val="11"/><name val="Arial"/></font></fonts>'
    . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
    . '<fill><patternFill patternType="solid"><fgColor rgb="FF4F46E5"/><bgColor indexed="64"/></patternFill></fill></fills>'
    . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
    . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
    . '<cellXfs count="5">'
    . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
    . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
    . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
    . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
    . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
    . '</cellXfs></styleSheet>');
$zip->close();

logActivity($_SESSION['user_id'], 'export_sheets', "Export Google Sheets $dateFrom → $dateTo");

while (ob_get_level() > 0) ob_end_clean(); // vide aussi les tampons amont (routeur, BOM d'includes)
$filename = "GTM-Bilan_{$dateFrom}_au_{$dateTo}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: no-store');
readfile($tmp);
unlink($tmp);
exit;
