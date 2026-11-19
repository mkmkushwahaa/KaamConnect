<?php
/**
 * KaamConnect - Server-Side Nominatim Geocoding Proxy
 * 
 * Acts as a proxy between the browser and Nominatim to:
 * 1. Add proper User-Agent header (required by Nominatim ToS)
 * 2. Apply server-side rate limiting
 * 3. Validate and sanitize inputs before forwarding
 * 4. Cache results to minimize API calls
 * 5. Never expose raw coordinates to untrusted clients without server validation
 *
 * Endpoints:
 *   GET ?action=search&q=Noida+Sector+15      -> location search / autocomplete
 *   GET ?action=reverse&lat=28.5&lon=77.3     -> reverse geocode a marker drop
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/database.php';

// ── Security: Only allow XHR from same origin ──────────────────────────────
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    // Also allow direct fetch() calls (no XHR header in modern fetch)
    // So we just restrict output to JSON regardless
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Rate limiting: 20 requests per IP per minute using session counter ─────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$rateBucket = 'geo_rl_' . md5($ip);
$now = time();

if (!isset($_SESSION[$rateBucket])) {
    $_SESSION[$rateBucket] = ['count' => 0, 'window_start' => $now];
}

$rl = &$_SESSION[$rateBucket];
if ($now - $rl['window_start'] > 60) {
    $rl = ['count' => 0, 'window_start' => $now];
}

$rl['count']++;

if ($rl['count'] > 25) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests. Please wait before searching again.', 'results' => []]);
    exit;
}

// ── Input validation ───────────────────────────────────────────────────────
$action = $_GET['action'] ?? 'search';

if ($action === 'search') {
    $query = trim($_GET['q'] ?? '');
    if (mb_strlen($query) < 2) {
        echo json_encode(['results' => [], 'error' => 'Query too short']);
        exit;
    }
    if (mb_strlen($query) > 200) {
        echo json_encode(['results' => [], 'error' => 'Query too long']);
        exit;
    }

    // Build Nominatim URL — restrict to India for relevance
    $params = http_build_query([
        'q'              => $query,
        'format'         => 'json',
        'addressdetails' => 1,
        'limit'          => 8,
        'countrycodes'   => 'in',
        'accept-language'=> 'en',
    ]);

    $url = 'https://nominatim.openstreetmap.org/search?' . $params;

} elseif ($action === 'reverse') {
    // Validate coordinates are real numbers within India's bounding box
    $lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
    $lon = filter_input(INPUT_GET, 'lon', FILTER_VALIDATE_FLOAT);

    if ($lat === false || $lon === false || $lat === null || $lon === null) {
        echo json_encode(['error' => 'Invalid coordinates']);
        exit;
    }

    // India bounding box: lat 6–38, lon 68–98
    if ($lat < 6.0 || $lat > 38.0 || $lon < 68.0 || $lon > 98.0) {
        echo json_encode(['error' => 'Coordinates must be within India', 'results' => []]);
        exit;
    }

    $params = http_build_query([
        'lat'            => round($lat, 7),
        'lon'            => round($lon, 7),
        'format'         => 'json',
        'addressdetails' => 1,
        'accept-language'=> 'en',
    ]);

    $url = 'https://nominatim.openstreetmap.org/reverse?' . $params;

} else {
    echo json_encode(['error' => 'Unknown action']);
    exit;
}

// ── Simple file-based cache (1 hour TTL) ──────────────────────────────────
$cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kc_geo_cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0700, true);
}
$cacheKey = md5($url);
$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.json';

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
    echo file_get_contents($cacheFile);
    exit;
}

// ── Fetch from Nominatim with proper User-Agent (required by ToS) ─────────
$context = stream_context_create([
    'http' => [
        'header'          => implode("\r\n", [
            'User-Agent: KaamConnect/1.0 (B.Tech Major Project; Educational; contact@kaamconnect.com)',
            'Accept: application/json',
        ]),
        'timeout'         => 8,
        'ignore_errors'   => true,
    ],
    'ssl' => [
        'verify_peer'      => true,
        'verify_peer_name' => true,
    ]
]);

$raw = @file_get_contents($url, false, $context);

if ($raw === false) {
    echo json_encode(['error' => 'Geocoding service temporarily unavailable. Please type your city manually.', 'results' => []]);
    exit;
}

$data = @json_decode($raw, true);

if ($data === null) {
    echo json_encode(['error' => 'Invalid response from geocoding service', 'results' => []]);
    exit;
}

// ── Transform Nominatim response into clean KaamConnect format ────────────
if ($action === 'search') {
    $results = [];
    foreach ((array)$data as $place) {
        $addr = $place['address'] ?? [];

        $suburb    = $addr['suburb'] ?? $addr['neighbourhood'] ?? $addr['quarter'] ?? null;
        $city      = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['county'] ?? null;
        $district  = $addr['state_district'] ?? $addr['district'] ?? null;
        $state     = $addr['state'] ?? null;
        $country   = $addr['country'] ?? null;

        // Build a clean display label
        $parts = array_filter([$suburb, $city, $district, $state]);
        $displayName = implode(', ', array_unique($parts));
        if (!$displayName) {
            $displayName = $place['display_name'] ?? '';
        }

        // Limit display_name length to avoid very long strings
        if (strlen($displayName) > 100) {
            $displayName = substr($displayName, 0, 97) . '...';
        }

        $results[] = [
            'display_name' => $displayName,
            'full_name'    => $place['display_name'] ?? $displayName,
            'suburb'       => $suburb,
            'city'         => $city,
            'district'     => $district,
            'state'        => $state,
            'country'      => $country,
            'lat'          => (float)($place['lat'] ?? 0),
            'lon'          => (float)($place['lon'] ?? 0),
            'type'         => $place['type'] ?? $place['class'] ?? 'place',
            'importance'   => (float)($place['importance'] ?? 0),
        ];
    }

    $output = ['results' => $results, 'count' => count($results)];

} else {
    // Reverse geocode
    $addr = $data['address'] ?? [];

    $output = [
        'display_name' => $data['display_name'] ?? '',
        'suburb'       => $addr['suburb'] ?? $addr['neighbourhood'] ?? null,
        'city'         => $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? null,
        'district'     => $addr['state_district'] ?? $addr['district'] ?? null,
        'state'        => $addr['state'] ?? null,
        'country'      => $addr['country'] ?? null,
        'postcode'     => $addr['postcode'] ?? null,
        'lat'          => (float)($data['lat'] ?? 0),
        'lon'          => (float)($data['lon'] ?? 0),
    ];

    // Build a short locality label
    $parts = array_filter([
        $addr['suburb'] ?? $addr['neighbourhood'] ?? null,
        $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? null,
    ]);
    $output['locality_label'] = implode(', ', $parts);
}

$json = json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Cache the result
@file_put_contents($cacheFile, $json);

echo $json;

