<?php
// hakikatmekanigi.com/feam/relay.php — FEAM Mobile Command Relay
// Telefon → komut kuyruğu → FEAM lokal agent → sonuç → telefon
//
// Actions:
//   POST ?action=command  (X-API-Key: RELAY_KEY)  — telefon komut gönderir
//   GET  ?action=poll     (X-Agent-Key: AGENT_KEY) — FEAM agent komut çeker
//   POST ?action=result   (X-Agent-Key: AGENT_KEY) — FEAM agent sonuç yazar
//   GET  ?action=result&session_id=... (X-API-Key)  — telefon sonuç okur
//   GET  ?action=status                             — genel durum (auth yok)

define('RELAY_KEY', getenv('FEAM_RELAY_KEY') ?: 'feam-relay-2026');
define('AGENT_KEY', getenv('FEAM_AGENT_KEY') ?: 'feam-agent-2026');
define('CMD_FILE',  __DIR__ . '/commands.json');
define('RES_FILE',  __DIR__ . '/results.json');
define('MAX_CMDS',  50);
define('MAX_RES',   100);
define('CMD_TTL',   300); // saniye

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: X-API-Key, X-Agent-Key, Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$action = $_GET['action'] ?? '';

$ALLOWED_CMDS = [
    'status.get', 'risk.summary', 'approval.list',
    'invoice.stats', 'project.summary', 'shahada.recent',
    'copilot.ask', 'workspace.run',
];

// ── Yardımcılar ──────────────────────────────────────────────────
function readJson($file) {
    if (!file_exists($file) || filesize($file) === 0) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}
function authPhone() {
    $k = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($k !== RELAY_KEY) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }
}
function authAgent() {
    $k = $_SERVER['HTTP_X_AGENT_KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($k !== AGENT_KEY) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }
}
function atomicWrite($file, $data) {
    $tmp = $file . '.tmp';
    file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    rename($tmp, $file);
}
function purgeExpired($items) {
    return array_values(array_filter($items, fn($i) => ($i['expires'] ?? 0) > time()));
}

// ── 1. Telefon: komut gönder ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'command') {
    authPhone();
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body || empty($body['cmd'])) {
        http_response_code(400); echo json_encode(['error' => 'cmd gerekli']); exit;
    }
    if (!in_array($body['cmd'], $ALLOWED_CMDS)) {
        http_response_code(403);
        echo json_encode(['error' => 'Komut izin listesinde değil', 'allowed' => $ALLOWED_CMDS]); exit;
    }

    $sid = uniqid('s_', true);
    $cmd = [
        'session_id' => $sid,
        'ts'         => date('c'),
        'cmd'        => $body['cmd'],
        'payload'    => $body['payload'] ?? null,
        'status'     => 'pending',
        'expires'    => time() + CMD_TTL,
    ];

    $fp = fopen(CMD_FILE, 'c+');
    flock($fp, LOCK_EX);
    $cmds = readJson(CMD_FILE);
    $cmds = purgeExpired($cmds);
    $cmds[] = $cmd;
    if (count($cmds) > MAX_CMDS) $cmds = array_slice($cmds, -MAX_CMDS);
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($cmds, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN); fclose($fp);

    echo json_encode(['ok' => true, 'session_id' => $sid, 'poll_after_sec' => 3]);
    exit;
}

// ── 2. FEAM Agent: bekleyen komutları çek ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'poll') {
    authAgent();
    $cmds = readJson(CMD_FILE);
    $pending = array_values(array_filter($cmds,
        fn($c) => $c['status'] === 'pending' && ($c['expires'] ?? 0) > time()
    ));
    echo json_encode(['commands' => $pending, 'count' => count($pending)]);
    exit;
}

// ── 3. FEAM Agent: sonuç yaz ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'result') {
    authAgent();
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body || empty($body['session_id'])) {
        http_response_code(400); echo json_encode(['error' => 'session_id gerekli']); exit;
    }

    // Komutu 'done' yap
    $fp = fopen(CMD_FILE, 'c+');
    flock($fp, LOCK_EX);
    $cmds = readJson(CMD_FILE);
    foreach ($cmds as &$c) {
        if ($c['session_id'] === $body['session_id']) $c['status'] = 'done';
    }
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($cmds, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN); fclose($fp);

    // Sonucu kaydet
    $fp2 = fopen(RES_FILE, 'c+');
    flock($fp2, LOCK_EX);
    $results = readJson(RES_FILE);
    $results = purgeExpired($results);
    $results[] = [
        'session_id' => $body['session_id'],
        'ts'         => date('c'),
        'ok'         => $body['ok'] ?? true,
        'cmd'        => $body['cmd'] ?? null,
        'data'       => $body['data'] ?? null,
        'error'      => $body['error'] ?? null,
        'expires'    => time() + 600,
    ];
    if (count($results) > MAX_RES) $results = array_slice($results, -MAX_RES);
    ftruncate($fp2, 0); rewind($fp2);
    fwrite($fp2, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp2, LOCK_UN); fclose($fp2);

    echo json_encode(['ok' => true]);
    exit;
}

// ── 4. Telefon: sonuç oku ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'result') {
    authPhone();
    $sid = $_GET['session_id'] ?? '';
    if (!$sid) { http_response_code(400); echo json_encode(['error' => 'session_id gerekli']); exit; }
    $results = readJson(RES_FILE);
    foreach ($results as $r) {
        if ($r['session_id'] === $sid) { echo json_encode($r); exit; }
    }
    echo json_encode(['status' => 'pending']);
    exit;
}

// ── 5. Durum (auth yok) ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'status') {
    $cmds    = readJson(CMD_FILE);
    $results = readJson(RES_FILE);
    $pending = count(array_filter($cmds,
        fn($c) => $c['status'] === 'pending' && ($c['expires'] ?? 0) > time()
    ));
    echo json_encode([
        'relay'            => 'online',
        'pending_commands' => $pending,
        'cached_results'   => count(purgeExpired($results)),
        'ts'               => date('c'),
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Geçersiz action', 'available' => ['command','poll','result','status']]);
