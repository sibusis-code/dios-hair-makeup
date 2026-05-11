<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Capture PayFast POST exactly as received.
$rawPost = file_get_contents('php://input');
parse_str($rawPost, $pfData);

if (empty($pfData) || empty($pfData['m_payment_id']) || empty($pfData['signature'])) {
    http_response_code(400);
    exit;
}

$postedSignature = $pfData['signature'];
unset($pfData['signature']);

$generatedSignature = buildPayFastSignature($pfData, PAYFAST_PASSPHRASE);
if (!hash_equals($generatedSignature, $postedSignature)) {
    http_response_code(400);
    exit;
}

// Validate ITN payload with PayFast.
$validateUrl = getPayFastValidateUrl();
$payload = $rawPost;
if (PAYFAST_PASSPHRASE !== '' && strpos($payload, 'passphrase=') === false) {
    $payload .= '&passphrase=' . urlencode(PAYFAST_PASSPHRASE);
}

$ch = curl_init($validateUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => false,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
]);

$pfResponse = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($pfResponse === false || stripos((string)$pfResponse, 'VALID') === false) {
    error_log('PayFast ITN validation failed: ' . $curlError);
    http_response_code(400);
    exit;
}

$paymentStatus = $pfData['payment_status'] ?? '';
$mPaymentId = $pfData['m_payment_id'];
$grossAmount = isset($pfData['amount_gross']) ? (float)$pfData['amount_gross'] : 0.00;

if ($paymentStatus !== 'COMPLETE') {
    http_response_code(200);
    exit;
}

$mysqli = getDbConnection();

$bookingStmt = $mysqli->prepare('SELECT id, amount, status FROM salon_bookings WHERE m_payment_id = ? LIMIT 1');
$bookingStmt->bind_param('s', $mPaymentId);
$bookingStmt->execute();
$bookingResult = $bookingStmt->get_result();
$booking = $bookingResult->fetch_assoc();
$bookingStmt->close();

if (!$booking) {
    $mysqli->close();
    http_response_code(404);
    exit;
}

$dbAmount = (float)$booking['amount'];
if (abs($dbAmount - $grossAmount) > 0.01) {
    error_log('Amount mismatch for m_payment_id ' . $mPaymentId);
    $mysqli->close();
    http_response_code(400);
    exit;
}

if ($booking['status'] !== 'paid') {
    $paidStatus = 'paid';
    $pendingStatus = 'pending';

    $updateStmt = $mysqli->prepare('UPDATE salon_bookings SET status = ? WHERE m_payment_id = ? AND status = ?');
    $updateStmt->bind_param('sss', $paidStatus, $mPaymentId, $pendingStatus);
    $updateStmt->execute();
    $updateStmt->close();
}

$mysqli->close();
http_response_code(200);
echo 'OK';
