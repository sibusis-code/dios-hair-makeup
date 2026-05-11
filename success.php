<?php
require_once __DIR__ . '/config.php';

$statusTitle = 'Payment Received';
$statusMessage = 'Thank you. Your payment was received. We are finalizing your booking confirmation.';
$statusVariant = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['m_payment_id'], $_GET['signature'])) {
    $pfData = $_GET;
    $postedSignature = (string)($pfData['signature'] ?? '');

    unset($pfData['signature']);
    $generatedSignature = buildPayFastSignature($pfData, PAYFAST_PASSPHRASE);

    if (!hash_equals($generatedSignature, $postedSignature)) {
        $statusTitle = 'Payment Verification Pending';
        $statusMessage = 'We received your return but could not verify the payment signature. Please contact support with your reference.';
        $statusVariant = 'warn';
    } else {
        // Validate returned data with PayFast before updating status.
        $validatePayload = $_GET;
        if (PAYFAST_PASSPHRASE !== '') {
            $validatePayload['passphrase'] = PAYFAST_PASSPHRASE;
        }

        $ch = curl_init(getPayFastValidateUrl());
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($validatePayload),
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
            error_log('PayFast success-page validation failed: ' . $curlError);
            $statusTitle = 'Payment Verification Pending';
            $statusMessage = 'Payment return received, but online verification is still pending. Your booking remains pending until verification completes.';
            $statusVariant = 'warn';
        } else {
            $paymentStatus = (string)($_GET['payment_status'] ?? '');
            $mPaymentId = (string)$_GET['m_payment_id'];
            $grossAmount = isset($_GET['amount_gross']) ? (float)$_GET['amount_gross'] : null;

            if ($paymentStatus === 'COMPLETE') {
                $mysqli = getDbConnection();

                $bookingStmt = $mysqli->prepare('SELECT amount, status FROM salon_bookings WHERE m_payment_id = ? LIMIT 1');
                $bookingStmt->bind_param('s', $mPaymentId);
                $bookingStmt->execute();
                $bookingResult = $bookingStmt->get_result();
                $booking = $bookingResult->fetch_assoc();
                $bookingStmt->close();

                if ($booking) {
                    $amountMatches = true;
                    if ($grossAmount !== null) {
                        $dbAmount = (float)$booking['amount'];
                        $amountMatches = abs($dbAmount - $grossAmount) <= 0.01;
                    }

                    if ($amountMatches) {
                        if ($booking['status'] !== 'paid') {
                            $paidStatus = 'paid';
                            $pendingStatus = 'pending';

                            $updateStmt = $mysqli->prepare('UPDATE salon_bookings SET status = ? WHERE m_payment_id = ? AND status = ?');
                            $updateStmt->bind_param('sss', $paidStatus, $mPaymentId, $pendingStatus);
                            $updateStmt->execute();
                            $updateStmt->close();
                        }

                        $statusTitle = 'Payment Confirmed';
                        $statusMessage = 'Your payment is verified and your booking has been marked as paid.';
                        $statusVariant = 'ok';
                    } else {
                        error_log('PayFast success amount mismatch for m_payment_id ' . $mPaymentId);
                        $statusTitle = 'Payment Verification Pending';
                        $statusMessage = 'Payment returned, but amount verification failed. Please contact support with your booking reference.';
                        $statusVariant = 'warn';
                    }
                } else {
                    $statusTitle = 'Booking Record Not Found';
                    $statusMessage = 'Payment return received, but no booking record was found. Please contact support with your payment reference.';
                    $statusVariant = 'warn';
                }

                $mysqli->close();
            } else {
                $statusTitle = 'Payment Not Completed';
                $statusMessage = 'You returned from PayFast, but the transaction is not marked complete yet.';
                $statusVariant = 'warn';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Status - DIOS Hair | Makeup</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <section class="page-hero section-dark" style="padding:8rem 0 4rem;text-align:center;min-height:100vh;display:flex;align-items:center;justify-content:center;">
    <div>
      <p class="section-eyebrow light">Payment Status</p>
      <h1 class="section-title light"><?php echo htmlspecialchars($statusTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
      <p style="color:<?php echo $statusVariant === 'ok' ? '#aaa' : '#f0c36d'; ?>;max-width:620px;margin:0 auto 1.25rem;">
        <?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?>
      </p>
      <?php if (isset($_GET['m_payment_id'])): ?>
        <p style="color:#aaa;font-size:0.9rem;margin-bottom:1.25rem;">Reference: <?php echo htmlspecialchars((string)$_GET['m_payment_id'], ENT_QUOTES, 'UTF-8'); ?></p>
      <?php endif; ?>
      <a href="booking.php" class="btn btn-gold">Back to Booking</a>
    </div>
  </section>
</body>
</html>
