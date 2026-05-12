<?php
require_once __DIR__ . '/config.php';

$errors = [];
$formData = [
    'firstName' => '',
    'lastName' => '',
    'phone' => '',
    'email' => '',
    'service' => '',
    'location' => '',
    'stylist' => '',
    'subType' => '',
    'hairLength' => '',
    'preferredDate' => '',
    'preferredTime' => '',
    'notes' => '',
    'depositAgree' => ''
];

// Keep original UI values while mapping to valid TIME values for MySQL storage.
$timeSlotMap = [
    '08:00' => ['label' => '08:00 AM', 'db' => '08:00:00'],
    '09:00' => ['label' => '09:00 AM', 'db' => '09:00:00'],
    '10:00' => ['label' => '10:00 AM', 'db' => '10:00:00'],
    '11:00' => ['label' => '11:00 AM', 'db' => '11:00:00'],
    '12:00' => ['label' => '12:00 PM', 'db' => '12:00:00'],
    '13:00' => ['label' => '01:00 PM', 'db' => '13:00:00'],
    '14:00' => ['label' => '02:00 PM', 'db' => '14:00:00'],
    '15:00' => ['label' => '03:00 PM', 'db' => '15:00:00'],
    '16:00' => ['label' => '04:00 PM', 'db' => '16:00:00'],
    '17:00' => ['label' => '05:00 PM', 'db' => '17:00:00'],
    'before-hours' => ['label' => 'Before Hours (extra R200)', 'db' => '07:00:00'],
    'after-hours' => ['label' => 'After Hours (extra R200)', 'db' => '18:00:00']
];

$dbTimeToUiKey = [];
foreach ($timeSlotMap as $uiKey => $meta) {
    $dbTimeToUiKey[$meta['db']] = $uiKey;
}

$mysqli = getDbConnection();
$paidSlots = [];
$paidStatus = 'paid';

$paidStmt = $mysqli->prepare(
    'SELECT appointment_date, appointment_time FROM salon_bookings WHERE status = ?'
);
$paidStmt->bind_param('s', $paidStatus);
$paidStmt->execute();
$paidResult = $paidStmt->get_result();

while ($row = $paidResult->fetch_assoc()) {
    $date = $row['appointment_date'];
    $dbTime = $row['appointment_time'];
    $uiKey = $dbTimeToUiKey[$dbTime] ?? substr($dbTime, 0, 5);

    if (!isset($paidSlots[$date])) {
        $paidSlots[$date] = [];
    }
    $paidSlots[$date][] = $uiKey;
}
$paidStmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $paymentConfigIssues = getPaymentConfigIssues();
  if ($paymentConfigIssues) {
    $errors = array_merge($errors, $paymentConfigIssues);
  }

    foreach ($formData as $key => $_value) {
        if ($key === 'depositAgree') {
            $formData[$key] = isset($_POST[$key]) ? '1' : '';
            continue;
        }
        $formData[$key] = trim($_POST[$key] ?? '');
    }

    if ($formData['firstName'] === '') {
        $errors[] = 'First name is required.';
    }

    if ($formData['lastName'] === '') {
        $errors[] = 'Last name is required.';
    }

    if ($formData['phone'] === '') {
        $errors[] = 'Phone number is required.';
    }

    if ($formData['email'] !== '' && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($formData['service'] === '') {
        $errors[] = 'Service is required.';
    }

    if ($formData['location'] === '') {
        $errors[] = 'Location is required.';
    }

    if ($formData['stylist'] === '') {
        $errors[] = 'Please select a preferred stylist.';
    }

    if ($formData['preferredDate'] === '') {
        $errors[] = 'Appointment date is required.';
    }

    if (!isset($timeSlotMap[$formData['preferredTime']])) {
        $errors[] = 'Please choose a valid appointment time.';
    }

    if ($formData['depositAgree'] !== '1') {
        $errors[] = 'You must agree to the booking policy and deposit terms.';
    }

    $appointmentTimeForDb = isset($timeSlotMap[$formData['preferredTime']])
        ? $timeSlotMap[$formData['preferredTime']]['db']
        : '';

    if (!$errors) {
        $slotCheckStmt = $mysqli->prepare(
            'SELECT id FROM salon_bookings WHERE appointment_date = ? AND appointment_time = ? AND status = ? LIMIT 1'
        );
        $slotCheckStmt->bind_param('sss', $formData['preferredDate'], $appointmentTimeForDb, $paidStatus);
        $slotCheckStmt->execute();
        $slotCheckResult = $slotCheckStmt->get_result();

        if ($slotCheckResult->num_rows > 0) {
            $errors[] = 'That time slot has already been booked and paid for. Please choose another slot.';
        }
        $slotCheckStmt->close();
    }

    if (!$errors) {
        $mPaymentId = 'DIOS-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $pendingStatus = 'pending';
      $amountValue = getBookingDepositAmount($formData['service']);
        $fullName = trim($formData['firstName'] . ' ' . $formData['lastName']);

        $insertStmt = $mysqli->prepare(
            'INSERT INTO salon_bookings (name, email, phone, appointment_date, appointment_time, amount, status, m_payment_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insertStmt->bind_param(
            'sssssdss',
            $fullName,
            $formData['email'],
            $formData['phone'],
            $formData['preferredDate'],
            $appointmentTimeForDb,
            $amountValue,
            $pendingStatus,
            $mPaymentId
        );

        if (!$insertStmt->execute()) {
            $errors[] = 'Unable to save booking. Please try again.';
        } else {
            // Get booking ID
            $bookingId = $mysqli->insert_id;
            
            // Send confirmation email to client
            if (SEND_CLIENT_EMAILS && $formData['email'] !== '') {
                require_once __DIR__ . '/mail-functions.php';
                $bookingData = [
                    'id' => $bookingId,
                    'name' => $fullName,
                    'email' => $formData['email'],
                    'phone' => $formData['phone'],
                    'appointment_date' => $formData['preferredDate'],
                    'appointment_time' => $appointmentTimeForDb,
                    'service' => $formData['service'],
                    'amount' => $amountValue,
                    'm_payment_id' => $mPaymentId,
                    'status' => 'pending'
                ];
                sendBookingConfirmation($bookingData);
            }
            
            // Send admin notification
            if (SEND_ADMIN_EMAILS) {
                require_once __DIR__ . '/mail-functions.php';
                $bookingData = [
                    'id' => $bookingId,
                    'name' => $fullName,
                    'email' => $formData['email'],
                    'phone' => $formData['phone'],
                    'appointment_date' => $formData['preferredDate'],
                    'appointment_time' => $appointmentTimeForDb,
                    'service' => $formData['service'],
                    'amount' => $amountValue,
                    'm_payment_id' => $mPaymentId,
                    'status' => 'pending'
                ];
                sendAdminNewBookingNotification($bookingData);
            }
        }
        $insertStmt->close();

        if (!$errors) {
            $serviceLabel = ucfirst(str_replace('-', ' ', $formData['service']));
            $itemName = 'DIOS Booking - ' . $serviceLabel;

            $bookingSummary = implode(' | ', array_filter([
                'Service: ' . $serviceLabel,
                $formData['subType'] !== '' ? 'Type: ' . $formData['subType'] : '',
                $formData['hairLength'] !== '' ? 'Length: ' . $formData['hairLength'] : '',
                'Location: ' . $formData['location'],
                'Date: ' . $formData['preferredDate'],
                'Time: ' . $formData['preferredTime']
            ]));

            $payfastData = [
                'merchant_id' => PAYFAST_MERCHANT_ID,
                'merchant_key' => PAYFAST_MERCHANT_KEY,
              'return_url' => getPayFastReturnUrl(),
              'cancel_url' => getPayFastCancelUrl(),
              'notify_url' => getPayFastNotifyUrl(),
                'name_first' => $formData['firstName'],
                'name_last' => $formData['lastName'],
                'email_address' => $formData['email'],
                'm_payment_id' => $mPaymentId,
                'amount' => $amountValue,
                'item_name' => $itemName,
                'custom_str1' => $formData['preferredDate'],
                'custom_str2' => $formData['preferredTime'],
                'custom_str3' => $formData['service'],
                'custom_str4' => $formData['location'],
                'custom_str5' => $bookingSummary
            ];

            $payfastData['signature'] = buildPayFastSignature($payfastData, PAYFAST_PASSPHRASE);
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
              <meta charset="UTF-8">
              <meta name="viewport" content="width=device-width, initial-scale=1.0">
              <title>Redirecting to PayFast...</title>
              <link rel="stylesheet" href="css/style.css">
            </head>
            <body>
              <section class="page-hero section-dark" style="padding:8rem 0 4rem;text-align:center;min-height:100vh;display:flex;align-items:center;justify-content:center;">
                <div>
                  <p class="section-eyebrow light">Payment</p>
                  <h1 class="section-title light">Redirecting to <em>PayFast</em></h1>
                  <p style="color:#aaa;">Please wait while we redirect you to secure payment.</p>
                </div>
              </section>

              <form id="payfastForm" action="<?php echo htmlspecialchars(getPayFastProcessUrl(), ENT_QUOTES, 'UTF-8'); ?>" method="post">
                <?php foreach ($payfastData as $key => $value): ?>
                  <input type="hidden" name="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endforeach; ?>
              </form>
              <script>
                document.getElementById('payfastForm').submit();
              </script>
            </body>
            </html>
            <?php
            $mysqli->close();
            exit;
        }
    }
}

$mysqli->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Book your appointment at DIOS Hair & Makeup — Midrand & Copperleaf luxury beauty studio." />
  <title>Book Now — DIOS Hair | Makeup</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/style.css" />
</head>
<body>

  <header class="site-header scrolled" id="header">
    <nav class="nav-container">
      <a href="index.html" class="nav-logo">
        <div class="nav-logo-img"><img src="images/logo.jpeg" alt="DIOS Hair Makeup logo" /></div>
        <div>
          <span class="logo-dios">DIOS</span>
          <span class="logo-sub">Hair | Make up</span>
        </div>
      </a>
      <button class="nav-toggle" id="navToggle" aria-label="Toggle menu">
        <span></span><span></span><span></span>
      </button>
      <ul class="nav-links" id="navLinks">
        <li><a href="index.html">Home</a></li>
        <li><a href="about.html">About</a></li>
        <li><a href="services.html">Services &amp; Pricing</a></li>
        <li><a href="policy.html">Policy</a></li>
        <li><a href="booking.php" class="nav-cta page-active">Book Now</a></li>
      </ul>
    </nav>
  </header>

  <section class="page-hero section-dark" style="padding:8rem 0 4rem;text-align:center;">
    <p class="section-eyebrow light">Reserve Your Slot</p>
    <h1 class="section-title light">Book an <em>Appointment</em></h1>
    <p style="color:#aaa;font-size:0.95rem;max-width:520px;margin:0 auto;">Complete all booking details and pay your deposit securely via PayFast.</p>
  </section>

  <div class="notice-banner">
    <div class="notice-inner">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      <p>A <strong>non-refundable 50% deposit</strong> is required to confirm all bookings. Deposit will be calculated based on your selected service.</p>
    </div>
  </div>

  <section class="booking section" id="booking">
    <div class="container booking-grid">
      <div class="booking-info">
        <h2 class="section-title">How <em>it works</em></h2>
        <ul class="booking-steps">
          <li><div class="step-num">1</div><div><strong>Submit this form</strong><br>Tell us the service, date and time you prefer.</div></li>
          <li><div class="step-num">2</div><div><strong>Pay your deposit</strong><br>You are redirected to PayFast immediately after submitting.</div></li>
          <li><div class="step-num">3</div><div><strong>Booking confirms automatically</strong><br>ITN updates your booking from pending to paid.</div></li>
        </ul>
      </div>

      <div class="booking-form-wrap">
        <form class="booking-form" id="bookingForm" data-server-submit="1" method="post" action="booking.php" novalidate>
          <h3>Request a Booking</h3>

          <?php if ($errors): ?>
            <div class="form-notice" style="margin-bottom:1rem;border-left:3px solid #d9534f;">
              <div>
                <?php foreach ($errors as $error): ?>
                  <p style="margin:0 0 0.35rem 0;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <div class="form-row">
            <div class="form-group">
              <label for="firstName">First Name *</label>
              <input type="text" id="firstName" name="firstName" placeholder="Your first name" required value="<?php echo htmlspecialchars($formData['firstName'], ENT_QUOTES, 'UTF-8'); ?>" />
              <span class="field-error" id="firstNameError"></span>
            </div>
            <div class="form-group">
              <label for="lastName">Last Name *</label>
              <input type="text" id="lastName" name="lastName" placeholder="Your last name" required value="<?php echo htmlspecialchars($formData['lastName'], ENT_QUOTES, 'UTF-8'); ?>" />
              <span class="field-error" id="lastNameError"></span>
            </div>
          </div>

          <div class="form-group">
            <label for="phone">WhatsApp / Phone Number *</label>
            <input type="tel" id="phone" name="phone" placeholder="e.g. 073 266 8348" required value="<?php echo htmlspecialchars($formData['phone'], ENT_QUOTES, 'UTF-8'); ?>" />
            <span class="field-error" id="phoneError"></span>
          </div>

          <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="Optional — for confirmation email" value="<?php echo htmlspecialchars($formData['email'], ENT_QUOTES, 'UTF-8'); ?>" />
            <span class="field-error" id="emailError"></span>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="service">Service Required *</label>
              <select id="service" name="service" required>
                <option value="">Select a service...</option>
                <optgroup label="Braiding Services">
                  <option value="braids" <?php echo $formData['service'] === 'braids' ? 'selected' : ''; ?>>Braids</option>
                  <option value="cornrows" <?php echo $formData['service'] === 'cornrows' ? 'selected' : ''; ?>>Cornrows</option>
                </optgroup>
                <optgroup label="Hair Styling">
                  <option value="ponytail" <?php echo $formData['service'] === 'ponytail' ? 'selected' : ''; ?>>Ponytail</option>
                  <option value="wig-installation" <?php echo $formData['service'] === 'wig-installation' ? 'selected' : ''; ?>>Wig Installation</option>
                  <option value="hair-colour" <?php echo $formData['service'] === 'hair-colour' ? 'selected' : ''; ?>>Hair Colour</option>
                  <option value="other-styling" <?php echo $formData['service'] === 'other-styling' ? 'selected' : ''; ?>>Other Hair Styling</option>
                </optgroup>
                <optgroup label="Makeup">
                  <option value="makeup" <?php echo $formData['service'] === 'makeup' ? 'selected' : ''; ?>>Makeup Artistry</option>
                </optgroup>
                <optgroup label="Other">
                  <option value="mobile" <?php echo $formData['service'] === 'mobile' ? 'selected' : ''; ?>>Mobile Service</option>
                  <option value="other" <?php echo $formData['service'] === 'other' ? 'selected' : ''; ?>>Other (specify below)</option>
                </optgroup>
              </select>
              <span class="field-error" id="serviceError"></span>
            </div>
            <div class="form-group">
              <label for="location">Preferred Location *</label>
              <select id="location" name="location" required>
                <option value="">Select location...</option>
                <option value="midrand" <?php echo $formData['location'] === 'midrand' ? 'selected' : ''; ?>>Midrand Studio</option>
                <option value="copperleaf" <?php echo $formData['location'] === 'copperleaf' ? 'selected' : ''; ?>>Copperleaf Studio</option>
                <option value="mobile" <?php echo $formData['location'] === 'mobile' ? 'selected' : ''; ?>>Mobile (come to me)</option>
              </select>
              <span class="field-error" id="locationError"></span>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="stylist">Preferred Stylist *</label>
              <select id="stylist" name="stylist" required>
                <option value="">Select a stylist...</option>
                <option value="no-preference" <?php echo $formData['stylist'] === 'no-preference' ? 'selected' : ''; ?>>No Preference</option>
                <option value="amara" <?php echo $formData['stylist'] === 'amara' ? 'selected' : ''; ?>>Amara</option>
                <option value="zara" <?php echo $formData['stylist'] === 'zara' ? 'selected' : ''; ?>>Zara</option>
                <option value="thandeka" <?php echo $formData['stylist'] === 'thandeka' ? 'selected' : ''; ?>>Thandeka</option>
              </select>
              <span class="field-error" id="stylistError"></span>
            </div>
          </div>

          <div class="service-info-banner" id="serviceInfoBanner" hidden>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <p id="serviceInfoText"></p>
          </div>

          <div class="form-row" id="subTypeRow" hidden>
            <div class="form-group">
              <label for="subType" id="subTypeLabel">Style *</label>
              <select id="subType" name="subType">
                <option value="">Select...</option>
              </select>
              <span class="field-error" id="subTypeError"></span>
            </div>
            <div class="form-group" id="lengthGroup" hidden>
              <label for="hairLength">Braid Length *</label>
              <select id="hairLength" name="hairLength">
                <option value="">Select length...</option>
                <option value="short" <?php echo $formData['hairLength'] === 'short' ? 'selected' : ''; ?>>Short - up to shoulder</option>
                <option value="medium" <?php echo $formData['hairLength'] === 'medium' ? 'selected' : ''; ?>>Medium - armpit length</option>
                <option value="long" <?php echo $formData['hairLength'] === 'long' ? 'selected' : ''; ?>>Long - waist length</option>
                <option value="extra-long" <?php echo $formData['hairLength'] === 'extra-long' ? 'selected' : ''; ?>>Extra Long - below waist</option>
              </select>
              <span class="field-error" id="hairLengthError"></span>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="preferredDate">Preferred Date *</label>
              <input type="date" id="preferredDate" name="preferredDate" required value="<?php echo htmlspecialchars($formData['preferredDate'], ENT_QUOTES, 'UTF-8'); ?>" />
              <span class="field-error" id="preferredDateError"></span>
            </div>
            <div class="form-group">
              <label for="preferredTime">Preferred Time *</label>
              <select id="preferredTime" name="preferredTime" required>
                <option value="">Select time...</option>
                <?php foreach ($timeSlotMap as $timeValue => $meta): ?>
                  <option value="<?php echo htmlspecialchars($timeValue, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $formData['preferredTime'] === $timeValue ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <span class="field-error" id="preferredTimeError"></span>
            </div>
          </div>

          <div class="form-group">
            <label for="notes">Additional Notes</label>
            <textarea id="notes" name="notes" rows="3" placeholder="Any special requests, references or details about your preferred style..."><?php echo htmlspecialchars($formData['notes'], ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>

          <div class="form-notice" style="margin-bottom:1rem;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span id="depositAmountDisplay" style="display:none;">Service-specific deposit charged at checkout: <strong>R<span id="depositValue">0.00</span></strong>.</span>
            <span id="depositPlaceholder">Select a service to see deposit amount.</span>
          </div>

          <div class="form-group form-checkbox">
            <label class="checkbox-label">
              <input type="checkbox" id="depositAgree" name="depositAgree" value="1" <?php echo $formData['depositAgree'] === '1' ? 'checked' : ''; ?> required />
              <span class="checkmark"></span>
              <span class="checkbox-copy">I have read and agree to the <a href="policy.html" class="policy-link">Booking Policy</a>. I understand a <strong>non-refundable 50% deposit</strong> is required to confirm my booking. *</span>
            </label>
            <span class="field-error" id="depositAgreeError"></span>
          </div>

          <div class="form-notice">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Your details are kept confidential and used only for booking purposes.
          </div>

          <button type="submit" class="btn btn-gold btn-full" id="submitBtn">Proceed To Secure Payment</button>
        </form>
      </div>
    </div>
  </section>

  <footer class="site-footer">
    <div class="container footer-grid">
      <div class="footer-brand">
        <span class="logo-dios">DIOS</span>
        <span class="logo-sub">Hair | Make up</span>
        <p>Luxury hair and makeup studio serving Midrand &amp; Copperleaf, Gauteng.</p>
      </div>

      <div class="footer-links">
        <h4>Quick Links</h4>
        <ul>
          <li><a href="index.html">Home</a></li>
          <li><a href="about.html">About</a></li>
          <li><a href="services.html">Services &amp; Pricing</a></li>
          <li><a href="policy.html">Booking Policy</a></li>
        </ul>
      </div>

      <div class="footer-booking-next">
        <h4>What Happens Next</h4>
        <ul style="font-size:0.82rem;line-height:1.7;color:#999;display:flex;flex-direction:column;gap:0.8rem;">
          <li><strong style="color:#daa;display:block;margin-bottom:0.2rem;">1. Complete this form</strong>Submit your booking details and preferred stylist.</li>
          <li><strong style="color:#daa;display:block;margin-bottom:0.2rem;">2. Secure payment</strong>You'll be redirected to PayFast for secure 50% deposit payment.</li>
          <li><strong style="color:#daa;display:block;margin-bottom:0.2rem;">3. Confirmation</strong>We'll WhatsApp/email you confirmation within 2 hours with all details.</li>
          <li><strong style="color:#daa;display:block;margin-bottom:0.2rem;">4. Arrive early</strong>Please arrive 5-10 mins early on appointment day.</li>
        </ul>
      </div>

      <div class="footer-contact">
        <h4>Contact &amp; Hours</h4>
        <ul>
          <li style="margin-bottom:1rem;">
            <strong style="color:var(--gold);display:block;margin-bottom:0.3rem;">Midrand Studio</strong>
            <a href="tel:+27732668348" style="color:#aaa;">073 266 8348</a><br>
            <span style="font-size:0.78rem;color:#666;">Mon–Sat: 5am–6pm<br>Sun: Closed</span>
          </li>
          <li>
            <strong style="color:var(--gold);display:block;margin-bottom:0.3rem;">Copperleaf Studio</strong>
            <a href="tel:+27732668348" style="color:#aaa;">073 266 8348</a><br>
            <span style="font-size:0.78rem;color:#666;">Tue–Sat: 5am–6pm<br>Sun: Closed</span>
          </li>
        </ul>
        <ul style="margin-top:1.2rem;">
          <li><a href="https://wa.me/27732668348" target="_blank" rel="noopener">WhatsApp Support</a></li>
          <li><a href="policy.html">View Full Policy</a></li>
        </ul>
      </div>
    </div>

    <div style="border-top:1px solid rgba(255,255,255,0.06);padding:2rem 0;margin:0 auto;max-width:100%;">
      <div class="container" style="font-size:0.82rem;color:#888;">
        <h4 style="font-family:var(--font-sans);font-size:0.7rem;letter-spacing:0.2em;text-transform:uppercase;color:var(--gold);margin-bottom:1rem;">FAQ</h4>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;">
          <div>
            <p><strong style="color:#aaa;">What's the deposit?</strong><br>50% of your service cost. Amount shown after service selection.</p>
            <p style="margin-top:0.8rem;"><strong style="color:#aaa;">Can I reschedule?</strong><br>Yes, with 48 hours notice. Contact us immediately.</p>
          </div>
          <div>
            <p><strong style="color:#aaa;">Are deposits refundable?</strong><br>No. Deposits are non-refundable, but can be rescheduled in emergencies.</p>
            <p style="margin-top:0.8rem;"><strong style="color:#aaa;">Need urgent support?</strong><br>WhatsApp us anytime or call 073 266 8348.</p>
          </div>
        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <p>&copy; 2026 DIOS Hair | Makeup. All rights reserved.</p>
    </div>
  </footer>

  <div class="nav-backdrop" id="navBackdrop"></div>

  <script>
    const paidSlotsByDate = <?php echo json_encode($paidSlots, JSON_UNESCAPED_SLASHES); ?>;
    const dateInput = document.getElementById('preferredDate');
    const timeSelect = document.getElementById('preferredTime');

    function refreshDisabledSlots() {
      const selectedDate = dateInput.value;
      const paidTimes = paidSlotsByDate[selectedDate] || [];

      Array.from(timeSelect.options).forEach((option) => {
        if (!option.value) {
          option.disabled = false;
          return;
        }

        const isPaid = paidTimes.includes(option.value);
        option.disabled = isPaid;

        if (isPaid && option.selected) {
          option.selected = false;
          timeSelect.value = '';
        }
      });
    }

    if (dateInput && timeSelect) {
      const today = new Date();
      const tomorrow = new Date(today);
      tomorrow.setDate(today.getDate() + 1);
      dateInput.min = tomorrow.toISOString().split('T')[0];

      const maxDate = new Date(today);
      maxDate.setDate(today.getDate() + 90);
      dateInput.max = maxDate.toISOString().split('T')[0];

      dateInput.addEventListener('change', refreshDisabledSlots);
      refreshDisabledSlots();
    }

    const savedService = <?php echo json_encode($formData['service'], JSON_UNESCAPED_SLASHES); ?>;
    const savedSubType = <?php echo json_encode($formData['subType'], JSON_UNESCAPED_SLASHES); ?>;
    const savedHairLength = <?php echo json_encode($formData['hairLength'], JSON_UNESCAPED_SLASHES); ?>;

    window.DIOS_SERVER_BOOKING_DEFAULTS = {
      service: savedService,
      subType: savedSubType,
      hairLength: savedHairLength
    };

    // Service-specific deposit pricing
    const servicePrices = {
      'braids': '800.00',
      'cornrows': '600.00',
      'ponytail': '400.00',
      'wig-installation': '750.00',
      'hair-colour': '500.00',
      'other-styling': '500.00',
      'makeup': '400.00',
      'mobile': '200.00',
      'other': '500.00'
    };

    const serviceSelect = document.getElementById('service');
    const depositDisplay = document.getElementById('depositAmountDisplay');
    const depositValue = document.getElementById('depositValue');
    const depositPlaceholder = document.getElementById('depositPlaceholder');

    function updateDepositDisplay() {
      const selectedService = serviceSelect.value;
      if (selectedService && servicePrices[selectedService]) {
        depositValue.textContent = servicePrices[selectedService];
        depositDisplay.style.display = 'flex';
        depositPlaceholder.style.display = 'none';
      } else {
        depositDisplay.style.display = 'none';
        depositPlaceholder.style.display = 'block';
      }
    }

    if (serviceSelect) {
      serviceSelect.addEventListener('change', updateDepositDisplay);
      // Update on page load if service was already selected
      updateDepositDisplay();
    }
  </script>
  <script src="js/main.js"></script>
</body>
</html>
