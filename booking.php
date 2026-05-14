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
$formData['bookings'] = []; // Initialize booking slots as an empty array

$mysqli = getDbConnection();
$bookingCatalog = getBookingCatalog($mysqli);
$businessInfo = getBusinessInfo($mysqli);

$businessPhoneDisplay = (string)($businessInfo['phone_whatsapp'] ?? '073 266 8348');
$businessCallDisplay = (string)($businessInfo['phone_call'] ?? $businessPhoneDisplay);
$businessWhatsappUrl = (string)($businessInfo['whatsapp_url'] ?? 'https://wa.me/27732668348');
$hoursMidrand = (string)($businessInfo['hours_midrand'] ?? 'Mon-Sat: 5am-6pm | Sun: Closed');
$hoursCopperleaf = (string)($businessInfo['hours_copperleaf'] ?? 'Tue-Sat: 5am-6pm | Sun: Closed');

$businessPhoneTel = preg_replace('/[^0-9+]/', '', $businessPhoneDisplay);
if ($businessPhoneTel === '') {
  $businessPhoneTel = '+27732668348';
}

$depositPercentageLabel = getDepositPercentageLabel();
$serviceDepositMap = $bookingCatalog['serviceDepositMap'] ?? getServiceDepositMap($mysqli);
$timeSlotMap = $bookingCatalog['timeSlotMap'] ?? getDefaultBookingCatalog()['timeSlotMap'];
$servicesConfig = $bookingCatalog['services'] ?? getDefaultBookingCatalog()['services'];
$serviceOrder = $bookingCatalog['serviceOrder'] ?? array_keys($servicesConfig);
$locationsConfig = $bookingCatalog['locations'] ?? getDefaultBookingCatalog()['locations'];
$stylistsConfig = $bookingCatalog['stylists'] ?? getDefaultBookingCatalog()['stylists'];
$serviceLocationStylists = $bookingCatalog['serviceLocationStylists'] ?? getDefaultBookingCatalog()['serviceLocationStylists'];

$serviceGroupsForUi = [];
foreach ($serviceOrder as $serviceKey) {
  if (!isset($servicesConfig[$serviceKey])) {
    continue;
  }
  $category = trim((string)($servicesConfig[$serviceKey]['category'] ?? 'Services'));
  if ($category === '') {
    $category = 'Services';
  }
  if (!isset($serviceGroupsForUi[$category])) {
    $serviceGroupsForUi[$category] = [];
  }
  $serviceGroupsForUi[$category][] = $serviceKey;
}

$clientServiceConfig = [];
foreach ($servicesConfig as $serviceKey => $serviceMeta) {
  $subtypes = [];
  if (!empty($serviceMeta['subtypes']) && is_array($serviceMeta['subtypes'])) {
    foreach ($serviceMeta['subtypes'] as $subTypeMeta) {
      $subtypes[] = [
        'key' => (string)($subTypeMeta['key'] ?? ''),
        'label' => (string)($subTypeMeta['label'] ?? ''),
      ];
    }
  }

  $slots = [];
  if (!empty($serviceMeta['slot_keys']) && is_array($serviceMeta['slot_keys'])) {
    foreach ($serviceMeta['slot_keys'] as $slotKey) {
      if (isset($timeSlotMap[$slotKey])) {
        $slots[] = [
          'value' => $slotKey,
          'label' => (string)$timeSlotMap[$slotKey]['label'],
        ];
      }
    }
  }

  $clientServiceConfig[$serviceKey] = [
    'label' => (string)($serviceMeta['label'] ?? $serviceKey),
    'subTypeLabel' => (string)($serviceMeta['sub_type_label'] ?? 'Style'),
    'subtypes' => $subtypes,
    'showLength' => !empty($serviceMeta['requires_hair_length']),
    'requiresSubType' => !empty($serviceMeta['requires_sub_type']),
    'info' => (string)($serviceMeta['info'] ?? ''),
    'slots' => $slots,
  ];
}

$clientDefaultSlots = [];
foreach ($timeSlotMap as $slotKey => $slotMeta) {
  $clientDefaultSlots[] = [
    'value' => $slotKey,
    'label' => (string)($slotMeta['label'] ?? $slotKey),
  ];
}

$dbTimeToUiKey = [];
foreach ($timeSlotMap as $uiKey => $meta) {
  $dbTime = (string)($meta['db'] ?? '');
  if ($dbTime !== '') {
    $dbTimeToUiKey[$dbTime] = $uiKey;
  }
}

$slotStatusByDate = [];
$slotCapacity = getMaxStylistsPerSlot();
$paidStatus = 'paid';
$bookingTimezone = new DateTimeZone(APP_TIMEZONE);

$paidStmt = $mysqli->prepare(
  'SELECT appointment_date, appointment_time, preferred_stylist FROM salon_bookings WHERE status = ?'
);
$paidStmt->bind_param('s', $paidStatus);
$paidStmt->execute();
$paidResult = $paidStmt->get_result();

while ($row = $paidResult->fetch_assoc()) {
    $date = $row['appointment_date'];
    $dbTime = $row['appointment_time'];
    $uiKey = $dbTimeToUiKey[$dbTime] ?? substr($dbTime, 0, 5);
  $stylistValue = trim((string)($row['preferred_stylist'] ?? ''));

  if (!isset($slotStatusByDate[$date])) {
    $slotStatusByDate[$date] = [];
    }

  if (!isset($slotStatusByDate[$date][$uiKey])) {
    $slotStatusByDate[$date][$uiKey] = [
      'count' => 0,
      'stylists' => []
    ];
  }

  $slotStatusByDate[$date][$uiKey]['count']++;
  if ($stylistValue !== '') {
    $slotStatusByDate[$date][$uiKey]['stylists'][] = $stylistValue;
  }
}
$paidStmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $paymentConfigIssues = getPaymentConfigIssues();
  if ($paymentConfigIssues) {
    $errors = array_merge($errors, $paymentConfigIssues);
  }

    // Input sanitization - remove potentially malicious input
    foreach ($formData as $key => $_value) {
        if ($key === 'depositAgree') {
            $formData[$key] = isset($_POST[$key]) ? '1' : '';
            continue;
        }
      if ($key === 'bookings') {
        continue;
      }
        // Sanitize: trim whitespace, remove null bytes, limit length
        $raw = $_POST[$key] ?? '';
        if (($key === 'preferredDate' || $key === 'preferredTime') && is_array($raw)) {
          $raw = (string)($raw[0] ?? '');
        }
        $raw = str_replace("\0", '', $raw); // Remove null bytes
        $raw = trim($raw);
        
        // Length limits for text fields
        if ($key === 'firstName' || $key === 'lastName') {
            $formData[$key] = substr($raw, 0, 100);
        } elseif ($key === 'email') {
            $formData[$key] = substr($raw, 0, 150);
        } elseif ($key === 'phone') {
            $formData[$key] = substr($raw, 0, 30);
        } elseif ($key === 'notes') {
            $formData[$key] = substr($raw, 0, 1000); // Limit special requests
        } else {
            $formData[$key] = substr($raw, 0, 100);
        }
    }
    // Validation: First name
    if ($formData['firstName'] === '') {
        $errors[] = 'First name is required.';
    } elseif (!preg_match('/^[a-zA-Z\s\'-]{2,100}$/', $formData['firstName'])) {
        $errors[] = 'First name must contain only letters, spaces, hyphens, and apostrophes (2-100 characters).';
    }

    // Validation: Last name
    if ($formData['lastName'] === '') {
        $errors[] = 'Last name is required.';
    } elseif (!preg_match('/^[a-zA-Z\s\'-]{2,100}$/', $formData['lastName'])) {
        $errors[] = 'Last name must contain only letters, spaces, hyphens, and apostrophes (2-100 characters).';
    }

    // Validation: Phone - accept international format
    if ($formData['phone'] === '') {
        $errors[] = 'Phone number is required.';
    } elseif (!preg_match('/^[0-9+\s\-()]{7,30}$/', $formData['phone'])) {
        $errors[] = 'Please enter a valid phone number (7-30 characters, may include +, spaces, hyphens).';
    }

    // Validation: Email (optional but must be valid if provided)
    if ($formData['email'] !== '' && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif ($formData['email'] !== '' && strlen($formData['email']) > 150) {
        $errors[] = 'Email address is too long.';
    }

    // Build booking items: one primary item + optional additional items.
    $formData['bookings'] = [];
    $primaryBooking = [
      'service' => $formData['service'],
      'location' => $formData['location'],
      'stylist' => $formData['stylist'],
      'subType' => $formData['subType'],
      'hairLength' => $formData['hairLength'],
      'preferredDate' => $formData['preferredDate'],
      'preferredTime' => $formData['preferredTime'],
    ];

    if (
      $primaryBooking['service'] !== '' ||
      $primaryBooking['location'] !== '' ||
      $primaryBooking['stylist'] !== '' ||
      $primaryBooking['preferredDate'] !== '' ||
      $primaryBooking['preferredTime'] !== ''
    ) {
      $formData['bookings'][] = $primaryBooking;
    }

    $extraBookings = $_POST['bookings_extra'] ?? [];
    if (is_array($extraBookings)) {
      foreach ($extraBookings as $row) {
        if (!is_array($row)) {
          continue;
        }

        $rowSanitized = [
          'service' => substr(trim((string)($row['service'] ?? '')), 0, 100),
          'location' => substr(trim((string)($row['location'] ?? '')), 0, 100),
          'stylist' => substr(trim((string)($row['stylist'] ?? '')), 0, 100),
          'subType' => substr(trim((string)($row['subType'] ?? '')), 0, 100),
          'hairLength' => substr(trim((string)($row['hairLength'] ?? '')), 0, 100),
          'preferredDate' => substr(trim((string)($row['preferredDate'] ?? '')), 0, 100),
          'preferredTime' => substr(trim((string)($row['preferredTime'] ?? '')), 0, 100),
        ];

        if (
          $rowSanitized['service'] === '' &&
          $rowSanitized['location'] === '' &&
          $rowSanitized['stylist'] === '' &&
          $rowSanitized['preferredDate'] === '' &&
          $rowSanitized['preferredTime'] === ''
        ) {
          continue;
        }

        $formData['bookings'][] = $rowSanitized;
      }
    }

    // Keep first booking mapped to legacy fields for JS restore.
    if (!empty($formData['bookings'])) {
      $formData['service'] = $formData['bookings'][0]['service'];
      $formData['location'] = $formData['bookings'][0]['location'];
      $formData['stylist'] = $formData['bookings'][0]['stylist'];
      $formData['subType'] = $formData['bookings'][0]['subType'];
      $formData['hairLength'] = $formData['bookings'][0]['hairLength'];
      $formData['preferredDate'] = $formData['bookings'][0]['preferredDate'];
      $formData['preferredTime'] = $formData['bookings'][0]['preferredTime'];
    }

    $validServices = array_keys($servicesConfig);
    $validLocations = array_keys($locationsConfig);
    $servicesRequiringSubType = [];
    $servicesRequiringLength = [];
    $serviceSubTypesByService = [];
    foreach ($servicesConfig as $serviceKey => $serviceMeta) {
      if (!empty($serviceMeta['requires_sub_type'])) {
        $servicesRequiringSubType[] = $serviceKey;
      }
      if (!empty($serviceMeta['requires_hair_length'])) {
        $servicesRequiringLength[] = $serviceKey;
      }
      $serviceSubTypesByService[$serviceKey] = [];
      if (!empty($serviceMeta['subtypes']) && is_array($serviceMeta['subtypes'])) {
        foreach ($serviceMeta['subtypes'] as $subTypeMeta) {
          if (isset($subTypeMeta['key'])) {
            $serviceSubTypesByService[$serviceKey][] = (string)$subTypeMeta['key'];
          }
        }
      }
    }

    if (empty($formData['bookings'])) {
        $errors[] = 'Please add at least one booking slot.';
    }

    // Validation: Appointment slots (date/time with 1-hour buffer for today)
    foreach ($formData['bookings'] as $index => $slot) {
        $slotNumber = $index + 1;
      if ($slot['service'] === '' || !in_array($slot['service'], $validServices, true)) {
        $errors[] = 'Slot #' . $slotNumber . ': Please select a valid service.';
      }

      if ($slot['location'] === '' || !in_array($slot['location'], $validLocations, true)) {
        $errors[] = 'Slot #' . $slotNumber . ': Please select a valid location.';
      }

      if ($slot['stylist'] === '') {
        $errors[] = 'Slot #' . $slotNumber . ': Please select a stylist.';
      }

        if ($slot['preferredDate'] === '') {
            $errors[] = 'Slot #' . $slotNumber . ': Appointment date is required.';
            continue;
        }

        $chosenDate = DateTimeImmutable::createFromFormat('!Y-m-d', $slot['preferredDate'], $bookingTimezone);
        $today = new DateTimeImmutable('today', $bookingTimezone);
        if ($chosenDate === false || $chosenDate < $today) {
            $errors[] = 'Slot #' . $slotNumber . ': Appointment date must be today or in the future.';
            continue;
        }

        if (in_array($slot['service'], $servicesRequiringSubType, true) && trim((string)$slot['subType']) === '') {
          $errors[] = 'Slot #' . $slotNumber . ': Please select a style/type.';
        }

        if (!empty($serviceSubTypesByService[$slot['service']])) {
          if (!in_array((string)$slot['subType'], $serviceSubTypesByService[$slot['service']], true)) {
            $errors[] = 'Slot #' . $slotNumber . ': Please select a valid style/type for the selected service.';
          }
        }

        if (in_array($slot['service'], $servicesRequiringLength, true) && trim((string)$slot['hairLength']) === '') {
          $errors[] = 'Slot #' . $slotNumber . ': Please select braid length.';
        }

        $maxDate = $today->modify('+90 days');
        if ($chosenDate > $maxDate) {
            $errors[] = 'Slot #' . $slotNumber . ': Appointments can only be booked up to 90 days in advance.';
        }

        if (!isset($timeSlotMap[$slot['preferredTime']])) {
            $errors[] = 'Slot #' . $slotNumber . ': Please choose a valid appointment time.';
            continue;
        }

        if ($chosenDate == $today) {
            $slotMap = $timeSlotMap[$slot['preferredTime']] ?? null;
            if ($slotMap && isset($slotMap['db'])) {
                $slotTimeObj = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $today->format('Y-m-d') . ' ' . $slotMap['db'], $bookingTimezone);
                $now = new DateTimeImmutable('now', $bookingTimezone);
                $bufferedNow = $now->modify('+1 hour');
                if ($slotTimeObj === false || $slotTimeObj <= $bufferedNow) {
                    $errors[] = 'Slot #' . $slotNumber . ': You can only book slots at least 1 hour from now.';
                }
            }
        }
    }

    // Validation: Terms acceptance
    if ($formData['depositAgree'] !== '1') {
        $errors[] = 'You must agree to the booking policy and deposit terms.';
    }

    if (!$errors) {
        foreach ($formData['bookings'] as $index => $slot) {
            $slotNumber = $index + 1;
            $appointmentTimeForDb = isset($timeSlotMap[$slot['preferredTime']])
                ? $timeSlotMap[$slot['preferredTime']]['db']
                : '';

            $slotCountStmt = $mysqli->prepare(
              'SELECT COUNT(*) AS total FROM salon_bookings WHERE appointment_date = ? AND appointment_time = ? AND status = ?'
            );
            $slotCountStmt->bind_param('sss', $slot['preferredDate'], $appointmentTimeForDb, $paidStatus);
            $slotCountStmt->execute();
            $existingSlotCount = (int)$slotCountStmt->get_result()->fetch_assoc()['total'];
            $slotCountStmt->close();

            if ($existingSlotCount >= $slotCapacity) {
              $errors[] = 'Slot #' . $slotNumber . ': That time slot is fully booked. Please choose another slot.';
              continue;
            }

            if ($slot['stylist'] !== 'no-preference') {
              $stylistSlotStmt = $mysqli->prepare(
                'SELECT COUNT(*) AS total FROM salon_bookings WHERE appointment_date = ? AND appointment_time = ? AND preferred_stylist = ? AND status = ?'
              );
              $stylistSlotStmt->bind_param('ssss', $slot['preferredDate'], $appointmentTimeForDb, $slot['stylist'], $paidStatus);
              $stylistSlotStmt->execute();
              $stylistSlotCount = (int)$stylistSlotStmt->get_result()->fetch_assoc()['total'];
              $stylistSlotStmt->close();

              if ($stylistSlotCount > 0) {
                $errors[] = 'Slot #' . $slotNumber . ': Your selected stylist is already booked for that slot. Please choose another slot or stylist.';
              }
            }
        }
    }

    if (!$errors) {
        $mPaymentId = 'DIOS-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
      $amountValue = 0.0;
      foreach ($formData['bookings'] as $slot) {
        $amountValue += (float)getBookingDepositAmount($slot['service'], $serviceDepositMap);
      }

      if (!ensurePaymentAttemptsTable($mysqli)) {
        $errors[] = 'Unable to initialize payment processing. Please try again.';
      } else {
        $attemptStatus = 'initiated';
        foreach ($formData['bookings'] as $slot) {
          $appointmentTimeForDb = isset($timeSlotMap[$slot['preferredTime']])
              ? $timeSlotMap[$slot['preferredTime']]['db']
              : '';

          $insertAttemptStmt = $mysqli->prepare(
            'INSERT INTO booking_payment_attempts (m_payment_id, first_name, last_name, email, phone, service, location, stylist, sub_type, hair_length, preferred_date, preferred_time, notes, amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
          );

          $amountNumeric = (float)getBookingDepositAmount($slot['service'], $serviceDepositMap);
          $insertAttemptStmt->bind_param(
            'sssssssssssssds',
            $mPaymentId,
            $formData['firstName'],
            $formData['lastName'],
            $formData['email'],
            $formData['phone'],
            $slot['service'],
            $slot['location'],
            $slot['stylist'],
            $slot['subType'],
            $slot['hairLength'],
            $slot['preferredDate'],
            $appointmentTimeForDb,
            $formData['notes'],
            $amountNumeric,
            $attemptStatus
          );

          if (!$insertAttemptStmt->execute()) {
            $errors[] = 'Unable to start payment session. Please try again.';
          }

          $insertAttemptStmt->close();
        }
      }

        if (!$errors) {
            $itemName = count($formData['bookings']) > 1 ? 'DIOS Multi Booking' : 'DIOS Booking';

            $slotSummaries = [];
            foreach ($formData['bookings'] as $slot) {
              $slotSummaries[] = ucfirst(str_replace('-', ' ', (string)$slot['service']))
                . ' @ ' . ucfirst((string)$slot['location'])
                . ' (' . $slot['preferredDate'] . ' ' . ($slot['preferredTime'] ?? '') . ')';
            }

            $bookingSummary = implode(' | ', array_filter([
                'Items: ' . count($formData['bookings']),
                'Bookings: ' . implode(', ', $slotSummaries)
            ]));

            $firstSlot = $formData['bookings'][0];

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
                'amount' => number_format((float)$amountValue, 2, '.', ''),
                'item_name' => $itemName,
                'custom_str1' => $firstSlot['preferredDate'],
                'custom_str2' => $firstSlot['preferredTime'],
                'custom_str3' => $firstSlot['service'],
                'custom_str4' => $firstSlot['location'],
                'custom_str5' => $bookingSummary
            ];

              // PayFast signature must be generated from exactly the same
              // non-empty fields that are posted in the final form.
              $payfastData = array_filter(
                $payfastData,
                static function ($value): bool {
                  return trim((string)$value) !== '';
                }
              );

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
      <p>A <strong>non-refundable <?php echo htmlspecialchars($depositPercentageLabel, ENT_QUOTES, 'UTF-8'); ?> deposit</strong> is required to confirm all bookings. Deposit is always calculated from your selected service price.</p>
    </div>
  </div>

  <section class="booking section" id="booking">
    <div class="container booking-grid">
      <div class="booking-info">
        <h2 class="section-title">How <em>it works</em></h2>
        <ul class="booking-steps">
          <li><div class="step-num">1</div><div><strong>Submit this form</strong><br>Tell us the service, date and time you prefer.</div></li>
          <li><div class="step-num">2</div><div><strong>Pay your deposit</strong><br>You are redirected to PayFast immediately after submitting.</div></li>
          <li><div class="step-num">3</div><div><strong>Booking confirms automatically</strong><br>Your booking is created only after PayFast ITN confirms successful payment.</div></li>
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
                <?php foreach ($serviceGroupsForUi as $groupLabel => $serviceKeys): ?>
                  <optgroup label="<?php echo htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php foreach ($serviceKeys as $serviceKey): ?>
                      <option value="<?php echo htmlspecialchars($serviceKey, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $formData['service'] === $serviceKey ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars((string)$servicesConfig[$serviceKey]['label'], ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
              <span class="field-error" id="serviceError"></span>
            </div>
            <div class="form-group">
              <label for="location">Preferred Location *</label>
              <select id="location" name="location" required>
                <option value="">Select location...</option>
                <?php foreach ($locationsConfig as $locationKey => $locationLabel): ?>
                  <option value="<?php echo htmlspecialchars($locationKey, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $formData['location'] === $locationKey ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars((string)$locationLabel, ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <span class="field-error" id="locationError"></span>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="stylist">Preferred Stylist *</label>
              <select id="stylist" name="stylist" required>
                <option value="">Select a stylist…</option>
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
              <div id="slotAvailabilityHint" class="helper-text" style="font-size:0.8rem;color:#888;margin-top:0.4rem;">Open and booked slots update automatically based on stylist availability.</div>
            </div>
          </div>

          <div id="additionalSlotsWrap">
            <?php if (!empty($formData['bookings']) && count($formData['bookings']) > 1): ?>
              <?php foreach (array_slice($formData['bookings'], 1) as $extraBooking): ?>
                <div class="multi-booking-item" style="border:1px solid #ede7d7;border-radius:10px;padding:0.9rem;margin:0.8rem 0;">
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.55rem;">
                    <strong style="font-size:0.88rem;color:#333;">Additional Booking Item</strong>
                    <button type="button" class="btn" data-remove-slot style="background:#f5f5f5;">Remove</button>
                  </div>
                  <div class="form-row">
                    <div class="form-group">
                      <label>Service *</label>
                      <select name="bookings_extra[][service]" required>
                        <option value="">Select a service...</option>
                        <?php foreach ($serviceGroupsForUi as $groupLabel => $serviceKeys): ?>
                          <optgroup label="<?php echo htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php foreach ($serviceKeys as $serviceKey): ?>
                              <option value="<?php echo htmlspecialchars($serviceKey, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($extraBooking['service'] ?? '') === $serviceKey) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string)$servicesConfig[$serviceKey]['label'], ENT_QUOTES, 'UTF-8'); ?>
                              </option>
                            <?php endforeach; ?>
                          </optgroup>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="form-group">
                      <label>Location *</label>
                      <select name="bookings_extra[][location]" required>
                        <option value="">Select location...</option>
                        <?php foreach ($locationsConfig as $locationKey => $locationLabel): ?>
                          <option value="<?php echo htmlspecialchars($locationKey, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($extraBooking['location'] ?? '') === $locationKey) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$locationLabel, ENT_QUOTES, 'UTF-8'); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>

                  <div class="form-row">
                    <div class="form-group">
                      <label>Preferred Stylist *</label>
                      <select name="bookings_extra[][stylist]" required>
                        <option value="">Select a stylist...</option>
                        <option value="no-preference" <?php echo (($extraBooking['stylist'] ?? '') === 'no-preference') ? 'selected' : ''; ?>>No Preference</option>
                        <?php foreach ($stylistsConfig as $stylistKey => $stylistLabel): ?>
                          <option value="<?php echo htmlspecialchars($stylistKey, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($extraBooking['stylist'] ?? '') === $stylistKey) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$stylistLabel, ENT_QUOTES, 'UTF-8'); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="form-group">
                      <label>Style / Type</label>
                      <select name="bookings_extra[][subType]" data-extra-subtype data-selected="<?php echo htmlspecialchars((string)($extraBooking['subType'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        <option value="">Select...</option>
                      </select>
                    </div>
                    <div class="form-group" data-extra-length-wrap hidden>
                      <label>Braid Length</label>
                      <select name="bookings_extra[][hairLength]">
                        <option value="">Select length...</option>
                        <option value="short" <?php echo (($extraBooking['hairLength'] ?? '') === 'short') ? 'selected' : ''; ?>>Short - up to shoulder</option>
                        <option value="medium" <?php echo (($extraBooking['hairLength'] ?? '') === 'medium') ? 'selected' : ''; ?>>Medium - armpit length</option>
                        <option value="long" <?php echo (($extraBooking['hairLength'] ?? '') === 'long') ? 'selected' : ''; ?>>Long - waist length</option>
                        <option value="extra-long" <?php echo (($extraBooking['hairLength'] ?? '') === 'extra-long') ? 'selected' : ''; ?>>Extra Long - below waist</option>
                      </select>
                    </div>
                  </div>

                  <div class="form-row">
                    <div class="form-group">
                      <label>Preferred Date *</label>
                      <input type="date" name="bookings_extra[][preferredDate]" required value="<?php echo htmlspecialchars((string)($extraBooking['preferredDate'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>
                    <div class="form-group">
                      <label>Preferred Time *</label>
                      <select name="bookings_extra[][preferredTime]" required>
                        <option value="">Select time...</option>
                        <?php foreach ($timeSlotMap as $timeValue => $meta): ?>
                          <option value="<?php echo htmlspecialchars($timeValue, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($extraBooking['preferredTime'] ?? '') === $timeValue) ? 'selected' : ''; ?>><?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <button type="button" id="addSlotBtn" class="btn btn-gold" style="margin-bottom:1rem;">Add Another Booking Item</button>

          <div class="form-group">
            <label for="notes">Additional Notes</label>
            <textarea id="notes" name="notes" rows="3" placeholder="Any special requests, references or details about your preferred style..."><?php echo htmlspecialchars($formData['notes'], ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>

          <div class="form-notice" style="margin-bottom:1rem;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span id="depositAmountDisplay" style="display:none;">Service-specific <?php echo htmlspecialchars($depositPercentageLabel, ENT_QUOTES, 'UTF-8'); ?> deposit charged at checkout: <strong>R<span id="depositValue">0.00</span></strong>.</span>
            <span id="depositPlaceholder">Select a service to see deposit amount.</span>
          </div>

          <div class="form-group form-checkbox">
            <label class="checkbox-label">
              <input type="checkbox" id="depositAgree" name="depositAgree" value="1" <?php echo $formData['depositAgree'] === '1' ? 'checked' : ''; ?> required />
              <span class="checkmark"></span>
              <span class="checkbox-copy">I have read and agree to the <a href="policy.html" class="policy-link">Booking Policy</a>. I understand a <strong>non-refundable <?php echo htmlspecialchars($depositPercentageLabel, ENT_QUOTES, 'UTF-8'); ?> deposit</strong> is required to confirm my booking. *</span>
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
            <a href="tel:<?php echo htmlspecialchars($businessPhoneTel, ENT_QUOTES, 'UTF-8'); ?>" style="color:#aaa;"><?php echo htmlspecialchars($businessPhoneDisplay, ENT_QUOTES, 'UTF-8'); ?></a><br>
            <span style="font-size:0.78rem;color:#666;"><?php echo nl2br(htmlspecialchars(str_replace(' | ', "\n", $hoursMidrand), ENT_QUOTES, 'UTF-8')); ?></span>
          </li>
          <li>
            <strong style="color:var(--gold);display:block;margin-bottom:0.3rem;">Copperleaf Studio</strong>
            <a href="tel:<?php echo htmlspecialchars($businessPhoneTel, ENT_QUOTES, 'UTF-8'); ?>" style="color:#aaa;"><?php echo htmlspecialchars($businessPhoneDisplay, ENT_QUOTES, 'UTF-8'); ?></a><br>
            <span style="font-size:0.78rem;color:#666;"><?php echo nl2br(htmlspecialchars(str_replace(' | ', "\n", $hoursCopperleaf), ENT_QUOTES, 'UTF-8')); ?></span>
          </li>
        </ul>
        <ul style="margin-top:1.2rem;">
          <li><a href="<?php echo htmlspecialchars($businessWhatsappUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">WhatsApp Support</a></li>
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
            <p style="margin-top:0.8rem;"><strong style="color:#aaa;">Need urgent support?</strong><br>WhatsApp us anytime or call <?php echo htmlspecialchars($businessCallDisplay, ENT_QUOTES, 'UTF-8'); ?>.</p>
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
    const slotStatusByDate = <?php echo json_encode($slotStatusByDate, JSON_UNESCAPED_SLASHES); ?>;
    const slotCapacity = <?php echo (int)$slotCapacity; ?>;
    window.DIOS_BOOKING_CATALOG = <?php echo json_encode([
      'serviceConfig' => $clientServiceConfig,
      'defaultSlots' => $clientDefaultSlots,
      'locations' => $locationsConfig,
      'stylists' => $stylistsConfig,
      'serviceLocationStylists' => $serviceLocationStylists,
    ], JSON_UNESCAPED_SLASHES); ?>;

    window.DIOS_BUSINESS_INFO = <?php echo json_encode([
      'phoneWhatsapp' => $businessPhoneDisplay,
      'phoneCall' => $businessCallDisplay,
      'whatsappUrl' => $businessWhatsappUrl,
      'hoursMidrand' => $hoursMidrand,
      'hoursCopperleaf' => $hoursCopperleaf,
      'addressMidrand' => (string)($businessInfo['address_midrand'] ?? ''),
      'addressCopperleaf' => (string)($businessInfo['address_copperleaf'] ?? ''),
    ], JSON_UNESCAPED_SLASHES); ?>;

    const dateInput = document.getElementById('preferredDate');
    const timeSelect = document.getElementById('preferredTime');
    const stylistSelect = document.getElementById('stylist');
    const serviceSelectForSlots = document.getElementById('service');
    const locationSelectForSlots = document.getElementById('location');
    const slotAvailabilityHint = document.getElementById('slotAvailabilityHint');

    function refreshDisabledSlots() {
      const selectedDate = dateInput.value;
      const byTime = slotStatusByDate[selectedDate] || {};
      const selectedStylist = stylistSelect ? stylistSelect.value : '';
      let bookedCount = 0;
      let remainingCapacity = 0;

      Array.from(timeSelect.options).forEach((option) => {
        if (!option.value) {
          option.disabled = false;
          return;
        }

        const baseLabel = option.dataset.baseLabel || option.textContent.replace(/\s*\([^)]*\)\s*$/, '');
        option.dataset.baseLabel = baseLabel;

        const slotData = byTime[option.value] || { count: 0, stylists: [] };
        const count = Number(slotData.count || 0);
        const stylists = Array.isArray(slotData.stylists) ? slotData.stylists : [];
        const full = count >= slotCapacity;
        const stylistBusy = selectedStylist && selectedStylist !== 'no-preference' && stylists.includes(selectedStylist);
        const disabled = full || stylistBusy;

        if (count > 0) {
          bookedCount += count;
        }

        remainingCapacity += Math.max(0, slotCapacity - count);

        let stateText = 'open';
        if (stylistBusy) {
          stateText = 'stylist booked';
        } else if (full) {
          stateText = 'fully booked';
        } else if (count > 0) {
          stateText = count + '/' + slotCapacity + ' booked';
        }

        option.textContent = baseLabel + ' (' + stateText + ')';
        option.disabled = disabled;

        if (disabled && option.selected) {
          option.selected = false;
          timeSelect.value = '';
        }
      });

      if (slotAvailabilityHint) {
        slotAvailabilityHint.textContent = 'Booked capacity for selected date: ' + bookedCount + ' | Remaining capacity: ' + remainingCapacity + '.';
      }
    }

    if (dateInput && timeSelect) {
      const today = new Date();
      dateInput.min = today.toISOString().split('T')[0];

      const maxDate = new Date(today);
      maxDate.setDate(today.getDate() + 90);
      dateInput.max = maxDate.toISOString().split('T')[0];

      dateInput.addEventListener('change', refreshDisabledSlots);
      if (stylistSelect) {
        stylistSelect.addEventListener('change', refreshDisabledSlots);
      }
      if (serviceSelectForSlots) {
        serviceSelectForSlots.addEventListener('change', function () {
          window.setTimeout(refreshDisabledSlots, 0);
        });
      }
      if (locationSelectForSlots) {
        locationSelectForSlots.addEventListener('change', function () {
          window.setTimeout(refreshDisabledSlots, 0);
        });
      }
      refreshDisabledSlots();
    }

    // Add additional booking items (service/location/stylist/date/time per item).
    (function initMultiSlots() {
      const addBtn = document.getElementById('addSlotBtn');
      const wrap = document.getElementById('additionalSlotsWrap');
      if (!addBtn || !wrap) {
        return;
      }

      const bookingCatalog = window.DIOS_BOOKING_CATALOG || {};
      const EXTRA_SERVICE_CONFIG = bookingCatalog.serviceConfig || {};
      const EXTRA_LOCATIONS = bookingCatalog.locations || {};
      const EXTRA_STYLISTS = bookingCatalog.stylists || {};
      const EXTRA_SERVICE_LOCATION_STYLISTS = bookingCatalog.serviceLocationStylists || {};
      const EXTRA_DEFAULT_SLOTS = Array.isArray(bookingCatalog.defaultSlots) ? bookingCatalog.defaultSlots : [];

      function labelCase(value) {
        if (!value) {
          return '';
        }
        return value.charAt(0).toUpperCase() + value.slice(1);
      }

      function getStylistsFor(service, location) {
        const map = EXTRA_SERVICE_LOCATION_STYLISTS[service] || {};
        if (location && Array.isArray(map[location]) && map[location].length > 0) {
          return map[location].slice();
        }
        if (Array.isArray(map.all) && map.all.length > 0) {
          return map.all.slice();
        }
        return Object.keys(EXTRA_STYLISTS);
      }

      function toSlug(value) {
        return String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
      }

      function escapeHtml(value) {
        return String(value || '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#39;');
      }

      function applyExtraServiceFields(itemEl) {
        if (!itemEl) {
          return;
        }

        const serviceEl = itemEl.querySelector('select[name^="bookings_extra"][name$="[service]"]');
        const subtypeEl = itemEl.querySelector('select[name^="bookings_extra"][name$="[subType]"]');
        const lengthWrap = itemEl.querySelector('[data-extra-length-wrap]');
        const lengthEl = itemEl.querySelector('select[name^="bookings_extra"][name$="[hairLength]"]');

        if (!serviceEl || !subtypeEl) {
          return;
        }

        const service = serviceEl.value;
        const cfg = EXTRA_SERVICE_CONFIG[service] || null;
        const current = subtypeEl.getAttribute('data-selected') || subtypeEl.value || '';

        subtypeEl.innerHTML = '<option value="">Select...</option>';
        if (cfg && Array.isArray(cfg.subtypes)) {
          cfg.subtypes.forEach(function (item) {
            const opt = document.createElement('option');
            opt.value = item.key || toSlug(item.label || '');
            opt.textContent = item.label || '';
            subtypeEl.appendChild(opt);
          });
        }

        if (current && Array.from(subtypeEl.options).some(function (o) { return o.value === current; })) {
          subtypeEl.value = current;
        } else {
          subtypeEl.value = '';
        }
        subtypeEl.removeAttribute('data-selected');

        if (lengthWrap) {
          const showLength = !!(cfg && cfg.showLength);
          lengthWrap.hidden = !showLength;
          if (!showLength && lengthEl) {
            lengthEl.value = '';
          }
        }
      }

      function populateExtraStylistOptions(itemEl) {
        if (!itemEl) {
          return;
        }

        const serviceEl = itemEl.querySelector('select[name^="bookings_extra"][name$="[service]"]');
        const locationEl = itemEl.querySelector('select[name^="bookings_extra"][name$="[location]"]');
        const stylistEl = itemEl.querySelector('select[name^="bookings_extra"][name$="[stylist]"]');

        if (!serviceEl || !locationEl || !stylistEl) {
          return;
        }

        const previous = stylistEl.value;
        const service = serviceEl.value;
        const location = locationEl.value;
        const names = getStylistsFor(service, location);

        stylistEl.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = service ? 'Select a stylist...' : 'Choose service first';
        stylistEl.appendChild(placeholder);

        const noPref = document.createElement('option');
        noPref.value = 'no-preference';
        noPref.textContent = 'No Preference';
        stylistEl.appendChild(noPref);

        names.forEach(function (nameValue) {
          const opt = document.createElement('option');
          opt.value = nameValue;
          opt.textContent = EXTRA_STYLISTS[nameValue] || labelCase(nameValue);
          stylistEl.appendChild(opt);
        });

        if (previous && Array.from(stylistEl.options).some(function (o) { return o.value === previous; })) {
          stylistEl.value = previous;
        } else {
          stylistEl.value = '';
        }
      }

      function bindExtraItemEvents(itemEl) {
        if (!itemEl) {
          return;
        }

        const serviceEl = itemEl.querySelector('select[name^="bookings_extra"][name$="[service]"]');
        const locationEl = itemEl.querySelector('select[name^="bookings_extra"][name$="[location]"]');

        if (serviceEl) {
          serviceEl.addEventListener('change', function () {
            applyExtraServiceFields(itemEl);
            populateExtraStylistOptions(itemEl);
          });
        }

        if (locationEl) {
          locationEl.addEventListener('change', function () {
            populateExtraStylistOptions(itemEl);
          });
        }

        applyExtraServiceFields(itemEl);
        populateExtraStylistOptions(itemEl);
      }

      addBtn.addEventListener('click', function () {
        const serviceOptions = ['<option value="">Select a service...</option>'];
        Object.keys(EXTRA_SERVICE_CONFIG).forEach(function (serviceKey) {
          const svc = EXTRA_SERVICE_CONFIG[serviceKey] || {};
          serviceOptions.push('<option value="' + escapeHtml(serviceKey) + '">' + escapeHtml(svc.label || serviceKey) + '</option>');
        });

        const locationOptions = ['<option value="">Select location...</option>'];
        Object.keys(EXTRA_LOCATIONS).forEach(function (locationKey) {
          locationOptions.push('<option value="' + escapeHtml(locationKey) + '">' + escapeHtml(EXTRA_LOCATIONS[locationKey]) + '</option>');
        });

        const timeOptions = ['<option value="">Select time...</option>'];
        EXTRA_DEFAULT_SLOTS.forEach(function (slot) {
          if (slot && slot.value) {
            timeOptions.push('<option value="' + escapeHtml(slot.value) + '">' + escapeHtml(slot.label) + '</option>');
          }
        });

        const row = document.createElement('div');
        row.className = 'multi-booking-item';
        row.style.border = '1px solid #ede7d7';
        row.style.borderRadius = '10px';
        row.style.padding = '0.9rem';
        row.style.margin = '0.8rem 0';
        row.innerHTML =
          '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.55rem;">' +
          '<strong style="font-size:0.88rem;color:#333;">Additional Booking Item</strong>' +
          '<button type="button" class="btn" data-remove-slot style="background:#f5f5f5;">Remove</button>' +
          '</div>' +
          '<div class="form-row">' +
          '<div class="form-group">' +
          '<label>Service *</label>' +
          '<select name="bookings_extra[][service]" required>' +
          serviceOptions.join('') +
          '</select>' +
          '</div>' +
          '<div class="form-group">' +
          '<label>Location *</label>' +
          '<select name="bookings_extra[][location]" required>' +
          locationOptions.join('') +
          '</select>' +
          '</div>' +
          '</div>' +
          '<div class="form-row">' +
          '<div class="form-group">' +
          '<label>Preferred Stylist *</label>' +
          '<select name="bookings_extra[][stylist]" required>' +
          '<option value="">Select a stylist...</option>' +
          '</select>' +
          '</div>' +
          '<div class="form-group">' +
          '<label>Style / Type</label>' +
          '<select name="bookings_extra[][subType]" data-extra-subtype>' +
          '<option value="">Select...</option>' +
          '</select>' +
          '</div>' +
          '<div class="form-group" data-extra-length-wrap hidden>' +
          '<label>Braid Length</label>' +
          '<select name="bookings_extra[][hairLength]">' +
          '<option value="">Select length...</option>' +
          '<option value="short">Short - up to shoulder</option>' +
          '<option value="medium">Medium - armpit length</option>' +
          '<option value="long">Long - waist length</option>' +
          '<option value="extra-long">Extra Long - below waist</option>' +
          '</select>' +
          '</div>' +
          '</div>' +
          '<div class="form-row">' +
          '<div class="form-group">' +
          '<label>Preferred Date *</label>' +
          '<input type="date" name="bookings_extra[][preferredDate]" required />' +
          '</div>' +
          '<div class="form-group">' +
          '<label>Preferred Time *</label>' +
          '<select name="bookings_extra[][preferredTime]" required>' +
          timeOptions.join('') +
          '</select>' +
          '</div>' +
          '</div>';
        wrap.appendChild(row);
        bindExtraItemEvents(row);

        const dateFields = row.querySelectorAll('input[type="date"]');
        dateFields.forEach(function (dateField) {
          if (dateField && dateInput && dateInput.min) {
            dateField.min = dateInput.min;
            dateField.max = dateInput.max;
          }
        });

        updateDepositDisplay();
      });

      wrap.addEventListener('click', function (event) {
        const target = event.target;
        if (target && target.matches('[data-remove-slot]')) {
          const slotRow = target.closest('.multi-booking-item');
          if (slotRow) {
            slotRow.remove();
            updateDepositDisplay();
          }
        }
      });

      wrap.querySelectorAll('input[type="date"]').forEach(function (dateField) {
        if (dateField && dateInput && dateInput.min) {
          dateField.min = dateInput.min;
          dateField.max = dateInput.max;
        }
      });

      wrap.querySelectorAll('.multi-booking-item').forEach(function (itemEl) {
        bindExtraItemEvents(itemEl);
      });
    })();

    const savedService    = <?php echo json_encode($formData['service'],    JSON_UNESCAPED_SLASHES); ?>;
    const savedSubType    = <?php echo json_encode($formData['subType'],    JSON_UNESCAPED_SLASHES); ?>;
    const savedHairLength = <?php echo json_encode($formData['hairLength'], JSON_UNESCAPED_SLASHES); ?>;
    const savedStylist    = <?php echo json_encode($formData['stylist'],    JSON_UNESCAPED_SLASHES); ?>;
    const savedFirstName  = <?php echo json_encode($formData['firstName'],  JSON_UNESCAPED_SLASHES); ?>;
    const savedLastName   = <?php echo json_encode($formData['lastName'],   JSON_UNESCAPED_SLASHES); ?>;
    const savedPhone      = <?php echo json_encode($formData['phone'],      JSON_UNESCAPED_SLASHES); ?>;
    const savedEmail      = <?php echo json_encode($formData['email'],      JSON_UNESCAPED_SLASHES); ?>;
    const savedLocation   = <?php echo json_encode($formData['location'],   JSON_UNESCAPED_SLASHES); ?>;
    const savedDate       = <?php echo json_encode($formData['preferredDate'], JSON_UNESCAPED_SLASHES); ?>;
    const savedTime       = <?php echo json_encode($formData['preferredTime'], JSON_UNESCAPED_SLASHES); ?>;
    const savedNotes      = <?php echo json_encode($formData['notes'],      JSON_UNESCAPED_SLASHES); ?>;

    // Restore all form fields on page load (for validation error re-render)
    window.DIOS_SERVER_BOOKING_DEFAULTS = {
      firstName:    savedFirstName,
      lastName:     savedLastName,
      phone:        savedPhone,
      email:        savedEmail,
      service:      savedService,
      location:     savedLocation,
      preferredDate: savedDate,
      preferredTime: savedTime,
      subType:      savedSubType,
      hairLength:   savedHairLength,
      stylist:      savedStylist,
      notes:        savedNotes,
      depositAgree: <?php echo (!empty($formData['depositAgree']) ? 'true' : 'false'); ?>
    };

    // Pass server validation errors to JS so they can highlight fields inline
    window.DIOS_SERVER_ERRORS = <?php 
      if (!empty($errors)) {
        // Map generic error messages to field IDs
        $fieldErrors = [];
        $errorMap = [
          'first name' => 'firstName',
          'last name' => 'lastName',
          'phone' => 'phone',
          'email' => 'email',
          'service' => 'service',
          'location' => 'location',
          'date' => 'preferredDate',
          'time' => 'preferredTime',
          'stylist' => 'stylist',
          'deposit' => 'depositAgree',
          'style' => 'subType',
          'length' => 'hairLength'
        ];
        foreach ($errors as $err) {
          $errLower = strtolower($err);
          $fieldId = null;
          foreach ($errorMap as $keyword => $id) {
            if (strpos($errLower, $keyword) !== false) {
              $fieldId = $id;
              break;
            }
          }
          $fieldErrors[] = ['field' => $fieldId ?: 'form', 'message' => $err];
        }
        echo json_encode($fieldErrors);
      } else {
        echo '[]';
      }
    ?>;


    // Server-driven service deposits (always 50% of base service price)
    const servicePrices = <?php echo json_encode($serviceDepositMap, JSON_UNESCAPED_SLASHES); ?>;

    const serviceSelect = document.getElementById('service');
    const depositDisplay = document.getElementById('depositAmountDisplay');
    const depositValue = document.getElementById('depositValue');
    const depositPlaceholder = document.getElementById('depositPlaceholder');

    function updateDepositDisplay() {
      const selectedServices = [];
      if (serviceSelect && serviceSelect.value) {
        selectedServices.push(serviceSelect.value);
      }

      document.querySelectorAll('select[name^="bookings_extra"][name$="[service]"]').forEach(function (el) {
        if (el.value) {
          selectedServices.push(el.value);
        }
      });

      let total = 0;
      selectedServices.forEach(function (svc) {
        if (servicePrices[svc]) {
          total += Number(servicePrices[svc]);
        }
      });

      if (total > 0) {
        depositValue.textContent = total.toFixed(2);
        depositDisplay.style.display = 'flex';
        depositPlaceholder.style.display = 'none';
      } else {
        depositDisplay.style.display = 'none';
        depositPlaceholder.style.display = 'block';
      }
    }

    if (serviceSelect) {
      serviceSelect.addEventListener('change', updateDepositDisplay);
      document.addEventListener('change', function (event) {
        const target = event.target;
        if (target && target.matches('select[name^="bookings_extra"][name$="[service]"]')) {
          updateDepositDisplay();
        }
      });
      // Update on page load if service was already selected
      updateDepositDisplay();
    }
  </script>
  <script src="js/main.js"></script>
</body>
</html>
