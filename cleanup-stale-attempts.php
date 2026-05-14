<?php
require_once __DIR__ . '/config.php';

function parseCliArgs(array $argv): array
{
    $out = [];
    foreach ($argv as $arg) {
        if (strpos($arg, '--') !== 0) {
            continue;
        }
        $parts = explode('=', substr($arg, 2), 2);
        $key = $parts[0] ?? '';
        $value = $parts[1] ?? '1';
        if ($key !== '') {
            $out[$key] = $value;
        }
    }
    return $out;
}

$isCli = (PHP_SAPI === 'cli');
$params = $isCli ? parseCliArgs($argv ?? []) : $_GET;

$tokenRequired = envOrDefault('CLEANUP_TOKEN', '');
$providedToken = trim((string)($params['token'] ?? ''));

if (!$isCli && $tokenRequired !== '' && !hash_equals($tokenRequired, $providedToken)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$hours = max(1, (int)envOrDefault('STALE_ATTEMPTS_HOURS', '24'));
if (isset($params['hours']) && is_numeric((string)$params['hours'])) {
    $hours = max(1, (int)$params['hours']);
}

$dryRun = false;
$dryRaw = strtolower(trim((string)($params['dry_run'] ?? '')));
if (in_array($dryRaw, ['1', 'true', 'yes'], true)) {
    $dryRun = true;
}

$statuses = ['initiated', 'failed', 'expired'];

$mysqli = getDbConnection();
if (!ensurePaymentAttemptsTable($mysqli)) {
    http_response_code(500);
    echo 'Unable to ensure booking_payment_attempts table.';
    $mysqli->close();
    exit;
}

$statusPlaceholders = implode(',', array_fill(0, count($statuses), '?'));
$types = str_repeat('s', count($statuses));

$countSql = 'SELECT COUNT(*) AS total FROM booking_payment_attempts WHERE status IN (' . $statusPlaceholders . ') AND created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)';
$countStmt = $mysqli->prepare($countSql);
if (!$countStmt) {
    http_response_code(500);
    echo 'Failed to prepare cleanup count statement.';
    $mysqli->close();
    exit;
}

$countTypes = $types . 'i';
$countParams = array_merge($statuses, [$hours]);
$countStmt->bind_param($countTypes, ...$countParams);
$countStmt->execute();
$countResult = $countStmt->get_result()->fetch_assoc();
$total = (int)($countResult['total'] ?? 0);
$countStmt->close();

$deleted = 0;
if (!$dryRun && $total > 0) {
    $deleteSql = 'DELETE FROM booking_payment_attempts WHERE status IN (' . $statusPlaceholders . ') AND created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)';
    $deleteStmt = $mysqli->prepare($deleteSql);
    if (!$deleteStmt) {
        http_response_code(500);
        echo 'Failed to prepare cleanup delete statement.';
        $mysqli->close();
        exit;
    }

    $deleteTypes = $types . 'i';
    $deleteParams = array_merge($statuses, [$hours]);
    $deleteStmt->bind_param($deleteTypes, ...$deleteParams);
    $deleteStmt->execute();
    $deleted = (int)$deleteStmt->affected_rows;
    $deleteStmt->close();
}

$mysqli->close();

$payload = [
    'ok' => true,
    'dry_run' => $dryRun,
    'threshold_hours' => $hours,
    'matched_rows' => $total,
    'deleted_rows' => $deleted,
    'timestamp' => gmdate('c'),
];

if ($isCli) {
    echo json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($payload, JSON_UNESCAPED_SLASHES);
