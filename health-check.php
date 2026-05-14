<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$tokenRequired = envOrDefault('HEALTHCHECK_TOKEN', '');
$providedToken = trim((string)($_GET['token'] ?? ''));

if ($tokenRequired !== '' && !hash_equals($tokenRequired, $providedToken)) {
    http_response_code(403);
    echo json_encode([
        'status' => 'forbidden',
        'message' => 'Token required.',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$checks = [];
$overallOk = true;

$checks[] = [
    'name' => 'php_version',
    'ok' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'detail' => PHP_VERSION,
];

$checks[] = [
    'name' => 'timezone',
    'ok' => (date_default_timezone_get() === APP_TIMEZONE),
    'detail' => date_default_timezone_get(),
];

$checks[] = [
    'name' => 'curl_extension',
    'ok' => extension_loaded('curl'),
    'detail' => extension_loaded('curl') ? 'loaded' : 'missing',
];

$dbOk = false;
$mysqli = null;
try {
    $mysqli = getDbConnection();
    $dbOk = true;
} catch (Throwable $e) {
    $dbOk = false;
}

$checks[] = [
    'name' => 'database_connection',
    'ok' => $dbOk,
    'detail' => $dbOk ? 'connected' : 'failed',
];

$requiredTables = [
    'salon_bookings',
    'booking_payment_attempts',
    'booking_services',
    'booking_service_subtypes',
    'booking_locations',
    'booking_stylists',
    'booking_time_slots',
    'booking_service_stylists',
    'business_settings',
];

if ($dbOk && $mysqli instanceof mysqli) {
    foreach ($requiredTables as $table) {
        $exists = tableExists($mysqli, $table);
        $checks[] = [
            'name' => 'table_' . $table,
            'ok' => $exists,
            'detail' => $exists ? 'exists' : 'missing',
        ];
    }

    $paidStatus = 'initiated';
    $countStale = 0;
    $staleStmt = $mysqli->prepare('SELECT COUNT(*) AS total FROM booking_payment_attempts WHERE status = ? AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)');
    if ($staleStmt) {
        $staleStmt->bind_param('s', $paidStatus);
        $staleStmt->execute();
        $row = $staleStmt->get_result()->fetch_assoc();
        $countStale = (int)($row['total'] ?? 0);
        $staleStmt->close();
    }

    $checks[] = [
        'name' => 'stale_initiated_attempts_24h',
        'ok' => ($countStale < 50),
        'detail' => $countStale,
    ];

    $mysqli->close();
}

$paymentIssues = getPaymentConfigIssues();
$checks[] = [
    'name' => 'payfast_configuration',
    'ok' => empty($paymentIssues),
    'detail' => empty($paymentIssues) ? 'ok' : $paymentIssues,
];

foreach ($checks as $check) {
    if (empty($check['ok'])) {
        $overallOk = false;
        break;
    }
}

$response = [
    'status' => $overallOk ? 'ok' : 'degraded',
    'timestamp' => gmdate('c'),
    'app_timezone' => APP_TIMEZONE,
    'checks' => $checks,
];

http_response_code($overallOk ? 200 : 503);
echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
