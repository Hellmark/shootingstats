<?php
/**
 * geocode.php — background geocoding endpoint
 * Processes ONE location per call. The JS polls repeatedly with a 1.2s delay.
 * Processing one at a time means a single timeout never stalls the whole batch,
 * and progress is visible per-location.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

// Verify the session token set by heatmap.php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.cookie_secure', '1');
    session_start();
}
$token = $_GET['token'] ?? '';
if (empty($_SESSION['geocode_token']) || !hash_equals($_SESSION['geocode_token'], $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$pdo = get_pdo();

// Retry previously-failed entries if ?retry=1
$retry = isset($_GET['retry']);

$sql = $retry
    ? 'SELECT DISTINCT i.city, i.state
       FROM incidents i
       INNER JOIN geocode_cache g ON g.city = i.city AND g.state = i.state
       WHERE g.failed = 1 LIMIT 1'
    : 'SELECT DISTINCT i.city, i.state
       FROM incidents i
       LEFT JOIN geocode_cache g ON g.city = i.city AND g.state = i.state
       WHERE g.id IS NULL AND i.city != "" AND i.state != ""
       LIMIT 1';

$loc = $pdo->query($sql)->fetch();

if (!$loc) {
    echo json_encode([
        'done'     => true,
        'location' => null,
        'progress' => get_geocode_progress(),
    ]);
    exit;
}

// Try several query forms before giving up
$coords  = null;
$tried   = [];
$queries = [
    "{$loc['city']}, {$loc['state']}, USA",
    "{$loc['city']}, United States",
    "{$loc['city']} {$loc['state']}",
];

foreach ($queries as $q) {
    $tried[] = $q;
    $coords  = nominatim_geocode($q);
    if ($coords) break;
    usleep(400000); // 0.4s between fallback attempts
}

cache_geocode($loc['city'], $loc['state'], $coords);

$progress  = get_geocode_progress();
$remaining = $progress['total'] - $progress['cached'] - $progress['failed'];

echo json_encode([
    'done'      => $remaining <= 0,
    'location'  => "{$loc['city']}, {$loc['state']}",
    'success'   => $coords !== null,
    'coords'    => $coords,
    'progress'  => $progress,
    'remaining' => $remaining,
]);

// ── Geocode via Nominatim ─────────────────────────────────────────────────

function nominatim_geocode(string $query): ?array {
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q'              => $query,
        'format'         => 'json',
        'limit'          => 1,
        'countrycodes'   => 'us',
        'addressdetails' => 0,
    ]);

    $ctx  = stream_context_create(['http' => [
        'header'        => "User-Agent: SchoolShootingStats/1.0\r\nAccept: application/json\r\n",
        'timeout'       => 8,
        'ignore_errors' => true,
    ]]);

    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false || $raw === '') return null;

    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data[0])) return null;

    $lat = (float)($data[0]['lat'] ?? 0);
    $lng = (float)($data[0]['lon'] ?? 0);
    if (!$lat || !$lng) return null;

    // Sanity-check: must be within the US + territories bounding box
    // (includes Alaska, Hawaii, Puerto Rico, USVI, Guam)
    $inUS = ($lat >= 17.0 && $lat <= 72.0 && $lng >= -180.0 && $lng <= -60.0)
         || ($lat >= 13.0 && $lat <= 21.0 && $lng >= 144.0 && $lng <= 146.0); // Guam/CNMI
    if (!$inUS) return null;

    return ['lat' => $lat, 'lng' => $lng];
}
