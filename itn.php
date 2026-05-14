<?php
require_once __DIR__ . '/config.php';

function itnLog(string $message): void
{
    error_log('[ITN] ' . $message);
}

function itnOk(): void
{
    http_response_code(200);
    echo 'OK';
    exit;
}

function updateLegacyPendingBooking(mysqli $mysqli, string $mPaymentId, float $grossAmount): void
{
    $bookingStmt = $mysqli->prepare('SELECT id, amount, status FROM salon_bookings WHERE m_payment_id = ? LIMIT 1');
    if (!$bookingStmt) {
        itnLog('Legacy booking lookup prepare failed for ' . $mPaymentId);
        return;
    }

    $bookingStmt->bind_param('s', $mPaymentId);
    $bookingStmt->execute();
    $bookingResult = $bookingStmt->get_result();
    $booking = $bookingResult->fetch_assoc();
    $bookingStmt->close();

    if (!$booking) {
        itnLog('Legacy booking not found for ' . $mPaymentId);
        return;
    }

    $dbAmount = (float)$booking['amount'];
    if ($grossAmount > 0 && abs($dbAmount - $grossAmount) > 0.01) {
        itnLog('Legacy amount mismatch for ' . $mPaymentId);
        return;
    }

    if ($booking['status'] !== 'paid') {
        $paidStatus = 'paid';
        $pendingStatus = 'pending';
        $updateStmt = $mysqli->prepare('UPDATE salon_bookings SET status = ? WHERE m_payment_id = ? AND status = ?');
        if ($updateStmt) {
            $updateStmt->bind_param('sss', $paidStatus, $mPaymentId, $pendingStatus);
            $updateStmt->execute();
            $updateStmt->close();
            itnLog('Legacy pending booking marked paid for ' . $mPaymentId);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    itnLog('Non-POST request method=' . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));
    itnOk();
}

$rawPost = file_get_contents('php://input');
$pfData = [];
if ($rawPost !== false && $rawPost !== '') {
    parse_str($rawPost, $pfData);
}

if (empty($pfData) && !empty($_POST)) {
    $pfData = $_POST;
    $rawPost = http_build_query($_POST);
}

itnLog('Received payload length=' . strlen((string)$rawPost));

if (empty($pfData) || empty($pfData['m_payment_id']) || empty($pfData['signature'])) {
    itnLog('Missing required fields. has_data=' . (!empty($pfData) ? '1' : '0'));
    itnOk();
}

$postedSignature = $pfData['signature'];
unset($pfData['signature']);

$generatedSignature = buildPayFastSignature($pfData, PAYFAST_PASSPHRASE);
if (!hash_equals($generatedSignature, $postedSignature)) {
    itnLog('Signature mismatch for m_payment_id=' . (string)($pfData['m_payment_id'] ?? 'unknown') . '. Continuing with PayFast validation.');
}

// Validate ITN payload with PayFast.
$validateUrl = getPayFastValidateUrl();
$payload = (string)$rawPost;
if ($payload === '') {
    $payload = http_build_query($pfData);
}
if (PAYFAST_PASSPHRASE !== '' && strpos((string)$payload, 'passphrase=') === false) {
    $payload .= ($payload === '' ? '' : '&') . 'passphrase=' . urlencode(PAYFAST_PASSPHRASE);
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
    itnLog('PayFast validation failed for ' . (string)($pfData['m_payment_id'] ?? 'unknown') . '. curl=' . $curlError . ' response=' . substr((string)$pfResponse, 0, 120));
    itnOk();
}

$paymentStatus = $pfData['payment_status'] ?? '';
$mPaymentId = $pfData['m_payment_id'];
$grossAmount = isset($pfData['amount_gross']) ? (float)$pfData['amount_gross'] : 0.00;

itnLog('Validated: m_payment_id=' . $mPaymentId . ', payment_status=' . $paymentStatus . ', amount_gross=' . (string)$grossAmount);

if ($paymentStatus !== 'COMPLETE') {
    itnLog('Ignoring non-COMPLETE status for ' . $mPaymentId . ': ' . $paymentStatus);
    itnOk();
}

try {
    $mysqli = getDbConnection();
} catch (Throwable $e) {
    itnLog('DB connection failed for ' . $mPaymentId . ': ' . $e->getMessage());
    itnOk();
}

if (!ensurePaymentAttemptsTable($mysqli)) {
    itnLog('Could not ensure booking_payment_attempts table for ' . $mPaymentId);
    $mysqli->close();
    itnOk();
}

$attemptStmt = $mysqli->prepare('SELECT * FROM booking_payment_attempts WHERE m_payment_id = ? ORDER BY id ASC');
if (!$attemptStmt) {
    itnLog('Attempt lookup prepare failed for ' . $mPaymentId);
    $mysqli->close();
    itnOk();
}

$attemptStmt->bind_param('s', $mPaymentId);
$attemptStmt->execute();
$attemptResult = $attemptStmt->get_result();
$attempts = [];
while ($attemptRow = $attemptResult->fetch_assoc()) {
    $attempts[] = $attemptRow;
}
$attemptStmt->close();

if (empty($attempts)) {
    // Backward compatibility for historical pending bookings.
    updateLegacyPendingBooking($mysqli, $mPaymentId, $grossAmount);
    $mysqli->close();
    itnOk();
}

$totalAttemptAmount = 0.0;
foreach ($attempts as $attempt) {
    $totalAttemptAmount += (float)$attempt['amount'];
}

if ($grossAmount > 0 && abs($totalAttemptAmount - $grossAmount) > 0.01) {
    itnLog('Amount mismatch in booking_payment_attempts for m_payment_id ' . $mPaymentId);
    $mysqli->close();
    itnOk();
}

// If all attempts are already marked paid with booking IDs, treat as idempotent callback.
$allAttemptsSettled = true;
foreach ($attempts as $attempt) {
    if (($attempt['status'] ?? '') !== 'paid' || empty($attempt['booking_id'])) {
        $allAttemptsSettled = false;
        break;
    }
}

if ($allAttemptsSettled) {
    itnLog('Idempotent hit: already paid for m_payment_id=' . $mPaymentId);
    $mysqli->close();
    itnOk();
}

$existingPaidStmt = $mysqli->prepare('SELECT COUNT(*) AS total FROM salon_bookings WHERE m_payment_id = ? AND status = ?');
$paidStatus = 'paid';
if (!$existingPaidStmt) {
    itnLog('Existing booking lookup prepare failed for ' . $mPaymentId);
    $mysqli->close();
    itnOk();
}

$existingPaidStmt->bind_param('ss', $mPaymentId, $paidStatus);
$existingPaidStmt->execute();
$existingPaidResult = $existingPaidStmt->get_result();
$existingPaidBooking = $existingPaidResult->fetch_assoc() ?: ['total' => 0];
$existingPaidStmt->close();

if ((int)$existingPaidBooking['total'] >= count($attempts)) {
    $paidAttemptStatus = 'paid';
    $syncAttemptStmt = $mysqli->prepare('UPDATE booking_payment_attempts SET status = ? WHERE m_payment_id = ?');
    if ($syncAttemptStmt) {
        $syncAttemptStmt->bind_param('ss', $paidAttemptStatus, $mPaymentId);
        $syncAttemptStmt->execute();
        $syncAttemptStmt->close();
    }

    $mysqli->close();
    itnOk();
}

// Validate capacity and stylist availability for each attempted slot.
$pendingBySlot = [];
$pendingByStylistSlot = [];
foreach ($attempts as $attempt) {
    $slotCountStmt = $mysqli->prepare('SELECT COUNT(*) AS total FROM salon_bookings WHERE appointment_date = ? AND appointment_time = ? AND status = ?');
    if (!$slotCountStmt) {
        itnLog('Slot count prepare failed for ' . $mPaymentId);
        $mysqli->close();
        itnOk();
    }

    $slotCountStmt->bind_param('sss', $attempt['preferred_date'], $attempt['preferred_time'], $paidStatus);
    $slotCountStmt->execute();
    $slotCount = (int)$slotCountStmt->get_result()->fetch_assoc()['total'];
    $slotCountStmt->close();

    $slotKey = $attempt['preferred_date'] . '|' . $attempt['preferred_time'];
    $pendingInSamePayment = (int)($pendingBySlot[$slotKey] ?? 0);

    if (($slotCount + $pendingInSamePayment) >= getMaxStylistsPerSlot()) {
        $failedStatus = 'failed';
        $markFailedStmt = $mysqli->prepare('UPDATE booking_payment_attempts SET status = ? WHERE m_payment_id = ?');
        if ($markFailedStmt) {
            $markFailedStmt->bind_param('ss', $failedStatus, $mPaymentId);
            $markFailedStmt->execute();
            $markFailedStmt->close();
        }

        itnLog('Paid ITN received for fully-booked slot, m_payment_id: ' . $mPaymentId);
        $mysqli->close();
        itnOk();
    }

    $pendingBySlot[$slotKey] = $pendingInSamePayment + 1;

    $preferredStylist = trim((string)($attempt['stylist'] ?? ''));
    if ($preferredStylist !== '' && $preferredStylist !== 'no-preference') {
        $stylistTakenStmt = $mysqli->prepare('SELECT id FROM salon_bookings WHERE appointment_date = ? AND appointment_time = ? AND preferred_stylist = ? AND status = ? LIMIT 1');
        if (!$stylistTakenStmt) {
            itnLog('Stylist slot check prepare failed for ' . $mPaymentId);
            $mysqli->close();
            itnOk();
        }

        $stylistTakenStmt->bind_param('ssss', $attempt['preferred_date'], $attempt['preferred_time'], $preferredStylist, $paidStatus);
        $stylistTakenStmt->execute();
        $stylistTakenResult = $stylistTakenStmt->get_result();
        $stylistTaken = $stylistTakenResult->fetch_assoc();
        $stylistTakenStmt->close();

        $stylistSlotKey = $slotKey . '|' . strtolower($preferredStylist);
        $pendingStylistInSamePayment = (int)($pendingByStylistSlot[$stylistSlotKey] ?? 0);

        if ($stylistTaken || $pendingStylistInSamePayment > 0) {
            $failedStatus = 'failed';
            $markFailedStylistStmt = $mysqli->prepare('UPDATE booking_payment_attempts SET status = ? WHERE m_payment_id = ?');
            if ($markFailedStylistStmt) {
                $markFailedStylistStmt->bind_param('ss', $failedStatus, $mPaymentId);
                $markFailedStylistStmt->execute();
                $markFailedStylistStmt->close();
            }

            itnLog('Paid ITN received for already-booked stylist slot, m_payment_id: ' . $mPaymentId);
            $mysqli->close();
            itnOk();
        }

        $pendingByStylistSlot[$stylistSlotKey] = $pendingStylistInSamePayment + 1;
    }
}

$mysqli->begin_transaction();

try {
    $newBookingIds = [];
    foreach ($attempts as $attempt) {
        $attemptAmount = (float)$attempt['amount'];
        $fullName = trim((string)$attempt['first_name'] . ' ' . (string)$attempt['last_name']);

        $insertBookingStmt = $mysqli->prepare(
            'INSERT INTO salon_bookings (name, email, phone, appointment_date, appointment_time, service, location, preferred_stylist, sub_type, hair_length, client_notes, amount, status, m_payment_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$insertBookingStmt) {
            throw new RuntimeException('Insert booking prepare failed');
        }

        $insertBookingStmt->bind_param(
            'sssssssssssdss',
            $fullName,
            $attempt['email'],
            $attempt['phone'],
            $attempt['preferred_date'],
            $attempt['preferred_time'],
            $attempt['service'],
            $attempt['location'],
            $attempt['stylist'],
            $attempt['sub_type'],
            $attempt['hair_length'],
            $attempt['notes'],
            $attemptAmount,
            $paidStatus,
            $mPaymentId
        );
        $insertBookingStmt->execute();
        $newBookingId = (int)$mysqli->insert_id;
        $insertBookingStmt->close();
        $newBookingIds[] = $newBookingId;

        $paidAttemptStatus = 'paid';
        $updateAttemptStmt = $mysqli->prepare('UPDATE booking_payment_attempts SET status = ?, booking_id = ? WHERE id = ?');
        if (!$updateAttemptStmt) {
            throw new RuntimeException('Update attempt prepare failed');
        }

        $attemptId = (int)$attempt['id'];
        $updateAttemptStmt->bind_param('sii', $paidAttemptStatus, $newBookingId, $attemptId);
        $updateAttemptStmt->execute();
        $updateAttemptStmt->close();

        $pfPaymentId = $pfData['pf_payment_id'] ?? null;
        if ($pfPaymentId) {
            $updatePfIdStmt = $mysqli->prepare('UPDATE salon_bookings SET pf_payment_id = ? WHERE id = ?');
            if ($updatePfIdStmt) {
                $updatePfIdStmt->bind_param('si', $pfPaymentId, $newBookingId);
                $updatePfIdStmt->execute();
                $updatePfIdStmt->close();
            }
        }
    }

    $mysqli->commit();
    itnLog('Finalized booking: m_payment_id=' . $mPaymentId . ', booking_count=' . (string)count($newBookingIds));
} catch (Throwable $e) {
    $mysqli->rollback();
    itnLog('Booking insert failed for ' . $mPaymentId . ': ' . $e->getMessage());
    $mysqli->close();
    itnOk();
}

if (SEND_CLIENT_EMAILS || SEND_ADMIN_EMAILS) {
    require_once __DIR__ . '/mail-functions.php';

    foreach ($attempts as $idx => $attempt) {
        $bookingData = [
            'id' => (int)($newBookingIds[$idx] ?? 0),
            'name' => trim((string)$attempt['first_name'] . ' ' . (string)$attempt['last_name']),
            'email' => (string)$attempt['email'],
            'phone' => (string)$attempt['phone'],
            'appointment_date' => (string)$attempt['preferred_date'],
            'appointment_time' => (string)$attempt['preferred_time'],
            'service' => (string)$attempt['service'],
            'amount' => (float)$attempt['amount'],
            'm_payment_id' => $mPaymentId,
            'status' => 'paid'
        ];

        if (SEND_CLIENT_EMAILS && $bookingData['email'] !== '') {
            sendBookingConfirmation($bookingData);
        }

        if (SEND_ADMIN_EMAILS) {
            sendAdminNewBookingNotification($bookingData);
        }
    }
}

$mysqli->close();
itnOk();
