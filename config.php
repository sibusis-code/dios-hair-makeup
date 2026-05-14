<?php
// Database and PayFast configuration.
// Update these values to match your xneelo + PayFast account.

function loadDotEnv(string $filePath): void
{
    if (!is_file($filePath) || !is_readable($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '#') === 0) {
            continue;
        }

        $separatorPos = strpos($trimmed, '=');
        if ($separatorPos === false) {
            continue;
        }

        $name = trim(substr($trimmed, 0, $separatorPos));
        $value = trim(substr($trimmed, $separatorPos + 1));

        if ($name === '') {
            continue;
        }

        if (
            (strlen($value) >= 2)
            && (
                ($value[0] === '"' && substr($value, -1) === '"')
                || ($value[0] === '\'' && substr($value, -1) === '\'')
            )
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($name) === false) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load local/server .env values if present.
loadDotEnv(__DIR__ . '/.env');

function envOrDefault(string $name, string $default): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }

    return (string)$value;
}

function envToBool(string $name, bool $default): bool
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }

    $normalized = strtolower(trim((string)$value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function envToMoney(string $name, float $default): float
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }

    if (!is_numeric($value)) {
        return $default;
    }

    $amount = (float)$value;
    return $amount > 0 ? $amount : $default;
}

define('DB_HOST', envOrDefault('DB_HOST', ''));
define('DB_NAME', envOrDefault('DB_NAME', ''));
define('DB_USER', envOrDefault('DB_USER', ''));
// SECURITY: Database password MUST be set via environment variable
// Never commit secrets to code. Set on production server via xneelo control panel.
define('DB_PASS', envOrDefault('DB_PASS', ''));

define('PAYFAST_MERCHANT_ID', envOrDefault('PAYFAST_MERCHANT_ID', ''));
define('PAYFAST_MERCHANT_KEY', envOrDefault('PAYFAST_MERCHANT_KEY', ''));

// Keep true while testing in sandbox. Set to false for live payments.
define('PAYFAST_SANDBOX', envToBool('PAYFAST_SANDBOX', true));

// Optional: set your PayFast passphrase if configured in PayFast dashboard.
define('PAYFAST_PASSPHRASE', envOrDefault('PAYFAST_PASSPHRASE', ''));

// Optional override. Leave blank to auto-detect host and subfolder path.
define('SITE_URL', envOrDefault('SITE_URL', 'https://development.mplai.co.za'));

// Optional explicit notify URL for ITN (use a public HTTPS URL).
define('PAYFAST_NOTIFY_URL_OVERRIDE', envOrDefault('PAYFAST_NOTIFY_URL_OVERRIDE', 'https://development.mplai.co.za/itn.php'));

// Business rules
define('MAX_ADMIN_USERS', max(1, (int)envOrDefault('MAX_ADMIN_USERS', '3')));
define('MAX_STYLISTS_PER_SLOT', max(1, (int)envOrDefault('MAX_STYLISTS_PER_SLOT', '2')));
define('BOOKING_DEPOSIT_PERCENTAGE', 0.50); // Always 50%

// Fallback deposit amount only for unknown service mappings.
define('BOOKING_DEPOSIT_AMOUNT', envToMoney('BOOKING_DEPOSIT_AMOUNT', 500.00));

// Email configuration
define('EMAIL_FROM_NAME', envOrDefault('EMAIL_FROM_NAME', 'DIOS Hair & Makeup'));
define('EMAIL_FROM_ADDRESS', envOrDefault('EMAIL_FROM_ADDRESS', 'bookings@dios.local'));
define('EMAIL_ADMIN_ADDRESS', envOrDefault('EMAIL_ADMIN_ADDRESS', 'admin@dios.local'));
define('EMAIL_USE_SMTP', envToBool('EMAIL_USE_SMTP', false));
define('EMAIL_SMTP_HOST', envOrDefault('EMAIL_SMTP_HOST', 'smtp.gmail.com'));
define('EMAIL_SMTP_PORT', (int)envOrDefault('EMAIL_SMTP_PORT', '587'));
define('EMAIL_SMTP_USER', envOrDefault('EMAIL_SMTP_USER', ''));
define('EMAIL_SMTP_PASS', envOrDefault('EMAIL_SMTP_PASS', ''));
define('SEND_CLIENT_EMAILS', envToBool('SEND_CLIENT_EMAILS', false));
define('SEND_ADMIN_EMAILS', envToBool('SEND_ADMIN_EMAILS', false));

define('APP_TIMEZONE', envOrDefault('APP_TIMEZONE', 'Africa/Johannesburg'));
date_default_timezone_set(APP_TIMEZONE);

function getDbConnection(): mysqli
{
    mysqli_report(MYSQLI_REPORT_OFF);

    try {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    } catch (Throwable $e) {
        http_response_code(500);
        die('Database connection failed.');
    }

    if ($mysqli->connect_errno) {
        http_response_code(500);
        die('Database connection failed.');
    }

    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}

function getPayFastProcessUrl(): string
{
    return PAYFAST_SANDBOX
        ? 'https://sandbox.payfast.co.za/eng/process'
        : 'https://www.payfast.co.za/eng/process';
}

function getPayFastValidateUrl(): string
{
    return PAYFAST_SANDBOX
        ? 'https://sandbox.payfast.co.za/eng/query/validate'
        : 'https://www.payfast.co.za/eng/query/validate';
}

function getSiteBaseUrl(): string
{
    if (SITE_URL !== '') {
        return rtrim(SITE_URL, '/');
    }

    if (PHP_SAPI === 'cli') {
        return 'https://development.mplai.co.za';
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'development.mplai.co.za';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
    $basePath = str_replace('\\', '/', dirname($scriptName));

    if ($basePath === '/' || $basePath === '.') {
        $basePath = '';
    }

    return $scheme . '://' . $host . rtrim($basePath, '/');
}

function getPayFastReturnUrl(): string
{
    return getSiteBaseUrl() . '/success.php';
}

function getPayFastCancelUrl(): string
{
    return getSiteBaseUrl() . '/cancel.php';
}

function getPayFastNotifyUrl(): string
{
    if (PAYFAST_NOTIFY_URL_OVERRIDE !== '') {
        return rtrim(PAYFAST_NOTIFY_URL_OVERRIDE, '/');
    }

    return getSiteBaseUrl() . '/itn.php';
}

function buildPayFastSignature(array $data, string $passphrase = ''): string
{
    $payload = [];
    foreach ($data as $key => $value) {
        if ($key === 'signature') {
            continue;
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            continue;
        }

        $payload[] = $key . '=' . urlencode($normalized);
    }

    $normalizedPassphrase = trim($passphrase);
    if ($normalizedPassphrase !== '') {
        $payload[] = 'passphrase=' . urlencode($normalizedPassphrase);
    }

    return md5(implode('&', $payload));
}

function getDefaultBookingCatalog(): array
{
    $timeSlotMap = [
        '07:30' => ['label' => '07:30 AM', 'db' => '07:30:00'],
        '08:00' => ['label' => '08:00 AM', 'db' => '08:00:00'],
        '09:00' => ['label' => '09:00 AM', 'db' => '09:00:00'],
        '10:00' => ['label' => '10:00 AM', 'db' => '10:00:00'],
        '11:00' => ['label' => '11:00 AM', 'db' => '11:00:00'],
        '11:30' => ['label' => '11:30 AM', 'db' => '11:30:00'],
        '12:00' => ['label' => '12:00 PM', 'db' => '12:00:00'],
        '13:00' => ['label' => '01:00 PM', 'db' => '13:00:00'],
        '14:00' => ['label' => '02:00 PM', 'db' => '14:00:00'],
        '14:30' => ['label' => '02:30 PM', 'db' => '14:30:00'],
        '15:00' => ['label' => '03:00 PM', 'db' => '15:00:00'],
        '16:00' => ['label' => '04:00 PM', 'db' => '16:00:00'],
        '17:00' => ['label' => '05:00 PM', 'db' => '17:00:00'],
        'before-hours' => ['label' => 'Before Hours (extra R200)', 'db' => '07:00:00'],
        'after-hours' => ['label' => 'After Hours (extra R200)', 'db' => '18:00:00'],
    ];

    $services = [
        'braids' => [
            'label' => 'Braids',
            'category' => 'Braiding Services',
            'base_price' => 1600.00,
            'requires_sub_type' => true,
            'requires_hair_length' => true,
            'sub_type_label' => 'Type of Braids',
            'subtypes' => [
                ['key' => 'knotless-braids', 'label' => 'Knotless Braids'],
                ['key' => 'box-braids', 'label' => 'Box Braids'],
                ['key' => 'feed-in-braids', 'label' => 'Feed-in Braids'],
                ['key' => 'goddess-braids', 'label' => 'Goddess Braids'],
                ['key' => 'faux-locs', 'label' => 'Faux Locs'],
            ],
            'info' => '<strong>Duration:</strong> 3-4 hours &nbsp;·&nbsp; <strong>Team:</strong> 1 client / 2 Braiders',
            'slot_keys' => ['07:30', '11:30', '14:30'],
        ],
        'cornrows' => [
            'label' => 'Cornrows',
            'category' => 'Braiding Services',
            'base_price' => 1200.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Cornrow Style',
            'subtypes' => [
                ['key' => 'straight-back-cornrows', 'label' => 'Straight-back Cornrows'],
                ['key' => 'stitch-cornrows', 'label' => 'Stitch Cornrows'],
                ['key' => 'fulani-cornrows', 'label' => 'Fulani Cornrows'],
                ['key' => 'cornrows-with-extensions', 'label' => 'Cornrows with Extensions'],
            ],
            'info' => '<strong>Duration:</strong> 2-3 hours &nbsp;·&nbsp; <strong>Team:</strong> 2 Stylists',
            'slot_keys' => [],
        ],
        'ponytail' => [
            'label' => 'Ponytail',
            'category' => 'Hair Styling',
            'base_price' => 800.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Ponytail Style',
            'subtypes' => [
                ['key' => 'sleek-ponytail', 'label' => 'Sleek Ponytail'],
                ['key' => 'curly-ponytail', 'label' => 'Curly Ponytail'],
                ['key' => 'braided-ponytail', 'label' => 'Braided Ponytail'],
            ],
            'info' => '',
            'slot_keys' => [],
        ],
        'frontal-ponytail' => [
            'label' => 'Frontal Ponytail',
            'category' => 'Hair Styling',
            'base_price' => 1350.00,
            'requires_sub_type' => false,
            'requires_hair_length' => false,
            'sub_type_label' => '',
            'subtypes' => [],
            'info' => 'Swiss frontal closure + synthetic bundles.',
            'slot_keys' => [],
        ],
        'relaxer' => [
            'label' => 'Relaxer',
            'category' => 'Hair Styling',
            'base_price' => 300.00,
            'requires_sub_type' => false,
            'requires_hair_length' => false,
            'sub_type_label' => '',
            'subtypes' => [],
            'info' => 'Dark n Lovely.',
            'slot_keys' => [],
        ],
        'wig-installation' => [
            'label' => 'Wig Installation',
            'category' => 'Hair Styling',
            'base_price' => 1500.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Wig Type',
            'subtypes' => [
                ['key' => 'closure-install', 'label' => 'Closure Install'],
                ['key' => 'frontal-install', 'label' => 'Frontal Install'],
                ['key' => 'glueless-install', 'label' => 'Glueless Install'],
            ],
            'info' => 'Pricing varies by wig type and customisation.',
            'slot_keys' => [],
        ],
        'hair-colour' => [
            'label' => 'Hair Colour',
            'category' => 'Hair Styling',
            'base_price' => 1000.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Hair Colour Service',
            'subtypes' => [
                ['key' => 'full-colour', 'label' => 'Full Colour'],
                ['key' => 'highlights', 'label' => 'Highlights'],
                ['key' => 'root-touch-up', 'label' => 'Root Touch-up'],
                ['key' => 'toner', 'label' => 'Toner'],
            ],
            'info' => '<strong>Duration:</strong> 1-2 hours',
            'slot_keys' => [],
        ],
        'other-styling' => [
            'label' => 'Other Hair Styling',
            'category' => 'Hair Styling',
            'base_price' => 1000.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Styling Type',
            'subtypes' => [
                ['key' => 'silk-press', 'label' => 'Silk Press'],
                ['key' => 'updo', 'label' => 'Updo'],
                ['key' => 'treatment-style', 'label' => 'Treatment & Style'],
                ['key' => 'custom-styling', 'label' => 'Custom Styling'],
            ],
            'info' => '',
            'slot_keys' => [],
        ],
        'makeup' => [
            'label' => 'Makeup Artistry',
            'category' => 'Makeup',
            'base_price' => 800.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Makeup Type',
            'subtypes' => [
                ['key' => 'soft-glam', 'label' => 'Soft Glam'],
                ['key' => 'full-glam', 'label' => 'Full Glam'],
                ['key' => 'photoshoot-makeup', 'label' => 'Photoshoot Makeup'],
            ],
            'info' => 'Makeup bookings are handled directly through our online booking form.',
            'slot_keys' => [],
        ],
        'bridal-makeup' => [
            'label' => 'Bridal Makeup',
            'category' => 'Makeup',
            'base_price' => 1350.00,
            'requires_sub_type' => false,
            'requires_hair_length' => false,
            'sub_type_label' => '',
            'subtypes' => [],
            'info' => '',
            'slot_keys' => [],
        ],
        'mobile' => [
            'label' => 'Mobile Service',
            'category' => 'Other',
            'base_price' => 400.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Mobile Service Type',
            'subtypes' => [
                ['key' => 'hair-service-at-home', 'label' => 'Hair Service at Home'],
                ['key' => 'makeup-at-home', 'label' => 'Makeup at Home'],
                ['key' => 'hair-makeup-at-home', 'label' => 'Hair + Makeup at Home'],
            ],
            'info' => '<strong>Travel fee:</strong> Additional R200',
            'slot_keys' => [],
        ],
        'other' => [
            'label' => 'Other',
            'category' => 'Other',
            'base_price' => 1000.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Other Service',
            'subtypes' => [
                ['key' => 'other-service', 'label' => 'Other Service'],
            ],
            'info' => '',
            'slot_keys' => [],
        ],
    ];

    $locations = [
        'midrand' => 'Midrand Studio',
        'copperleaf' => 'Copperleaf Studio',
        'mobile' => 'Mobile (come to me)',
    ];

    $stylists = [
        'caro' => 'Caro',
        'emma' => 'Emma',
        'patience' => 'Patience',
        'lincy' => 'Lincy',
        'charity' => 'Charity',
        'charmaine' => 'Charmaine',
        'pamela' => 'Pamela',
        'marlyn' => 'Marlyn',
        'ibongiwe' => 'Ibongiwe',
    ];

    $allStylists = array_keys($stylists);
    $serviceLocationStylists = [];
    foreach ($services as $serviceKey => $_serviceMeta) {
        $serviceLocationStylists[$serviceKey] = [
            'all' => $allStylists,
            'midrand' => ['caro', 'emma', 'patience'],
            'copperleaf' => ['lincy', 'charity'],
            'mobile' => $allStylists,
        ];

        if ($serviceKey === 'makeup') {
            $serviceLocationStylists[$serviceKey] = [
                'all' => ['charmaine', 'pamela'],
                'midrand' => ['charmaine', 'pamela'],
                'copperleaf' => ['charmaine', 'pamela'],
                'mobile' => ['charmaine', 'pamela'],
            ];
        }

        if ($serviceKey === 'wig-installation') {
            $serviceLocationStylists[$serviceKey] = [
                'all' => ['marlyn', 'ibongiwe'],
                'midrand' => ['marlyn', 'ibongiwe'],
                'copperleaf' => ['marlyn', 'ibongiwe'],
                'mobile' => ['marlyn', 'ibongiwe'],
            ];
        }
    }

    $serviceDepositMap = [];
    foreach ($services as $serviceKey => $serviceMeta) {
        $serviceDepositMap[$serviceKey] = round(((float)$serviceMeta['base_price']) * BOOKING_DEPOSIT_PERCENTAGE, 2);
    }

    return [
        'services' => $services,
        'serviceOrder' => array_keys($services),
        'locations' => $locations,
        'stylists' => $stylists,
        'serviceLocationStylists' => $serviceLocationStylists,
        'timeSlotMap' => $timeSlotMap,
        'serviceDepositMap' => $serviceDepositMap,
    ];
}

function tableExists(mysqli $mysqli, string $tableName): bool
{
    $safeName = $mysqli->real_escape_string($tableName);
    $sql = "SHOW TABLES LIKE '" . $safeName . "'";
    $result = $mysqli->query($sql);
    if (!$result instanceof mysqli_result) {
        return false;
    }
    return $result->num_rows > 0;
}

function getBookingCatalog(mysqli $mysqli): array
{
    $defaults = getDefaultBookingCatalog();

    $requiredTables = [
        'booking_services',
        'booking_service_subtypes',
        'booking_locations',
        'booking_stylists',
        'booking_time_slots',
        'booking_service_stylists',
    ];

    foreach ($requiredTables as $tableName) {
        if (!tableExists($mysqli, $tableName)) {
            return $defaults;
        }
    }

    $services = [];
    $serviceOrder = [];

    $serviceResult = $mysqli->query(
        "SELECT id, service_key, service_name, category_label, base_price, requires_sub_type, requires_hair_length, sub_type_label, info_text
         FROM booking_services
         WHERE is_active = 1
         ORDER BY sort_order ASC, service_name ASC"
    );

    if (!$serviceResult instanceof mysqli_result || $serviceResult->num_rows === 0) {
        return $defaults;
    }

    $serviceIdByKey = [];
    while ($row = $serviceResult->fetch_assoc()) {
        $serviceKey = (string)$row['service_key'];
        $serviceIdByKey[$serviceKey] = (int)$row['id'];
        $serviceOrder[] = $serviceKey;
        $services[$serviceKey] = [
            'label' => (string)$row['service_name'],
            'category' => (string)$row['category_label'],
            'base_price' => (float)$row['base_price'],
            'requires_sub_type' => (bool)$row['requires_sub_type'],
            'requires_hair_length' => (bool)$row['requires_hair_length'],
            'sub_type_label' => trim((string)$row['sub_type_label']) !== '' ? (string)$row['sub_type_label'] : 'Style',
            'subtypes' => [],
            'info' => (string)$row['info_text'],
            'slot_keys' => [],
        ];
    }

    $subtypeResult = $mysqli->query(
        "SELECT bs.service_key, s.subtype_key, s.subtype_label
         FROM booking_service_subtypes s
         INNER JOIN booking_services bs ON bs.id = s.service_id
         WHERE s.is_active = 1 AND bs.is_active = 1
         ORDER BY s.sort_order ASC, s.subtype_label ASC"
    );
    if ($subtypeResult instanceof mysqli_result) {
        while ($row = $subtypeResult->fetch_assoc()) {
            $serviceKey = (string)$row['service_key'];
            if (!isset($services[$serviceKey])) {
                continue;
            }
            $services[$serviceKey]['subtypes'][] = [
                'key' => (string)$row['subtype_key'],
                'label' => (string)$row['subtype_label'],
            ];
        }
    }

    $locations = [];
    $locationIdByKey = [];
    $locationResult = $mysqli->query(
        "SELECT id, location_key, location_name
         FROM booking_locations
         WHERE is_active = 1
         ORDER BY sort_order ASC, location_name ASC"
    );
    if ($locationResult instanceof mysqli_result) {
        while ($row = $locationResult->fetch_assoc()) {
            $locationKey = (string)$row['location_key'];
            $locationIdByKey[$locationKey] = (int)$row['id'];
            $locations[$locationKey] = (string)$row['location_name'];
        }
    }
    if (!$locations) {
        $locations = $defaults['locations'];
    }

    $stylists = [];
    $stylistResult = $mysqli->query(
        "SELECT id, stylist_key, stylist_name
         FROM booking_stylists
         WHERE is_active = 1
         ORDER BY sort_order ASC, stylist_name ASC"
    );
    if ($stylistResult instanceof mysqli_result) {
        while ($row = $stylistResult->fetch_assoc()) {
            $stylists[(string)$row['stylist_key']] = (string)$row['stylist_name'];
        }
    }
    if (!$stylists) {
        $stylists = $defaults['stylists'];
    }

    $timeSlotMap = [];
    $timeSlotIdByKey = [];
    $slotResult = $mysqli->query(
        "SELECT id, slot_key, slot_label, db_time
         FROM booking_time_slots
         WHERE is_active = 1
         ORDER BY sort_order ASC, db_time ASC"
    );
    if ($slotResult instanceof mysqli_result) {
        while ($row = $slotResult->fetch_assoc()) {
            $slotKey = (string)$row['slot_key'];
            $timeSlotIdByKey[$slotKey] = (int)$row['id'];
            $timeSlotMap[$slotKey] = [
                'label' => (string)$row['slot_label'],
                'db' => (string)$row['db_time'],
            ];
        }
    }
    if (!$timeSlotMap) {
        $timeSlotMap = $defaults['timeSlotMap'];
    }

    if (tableExists($mysqli, 'booking_service_slots')) {
        $slotMapResult = $mysqli->query(
            "SELECT bs.service_key, ts.slot_key
             FROM booking_service_slots bss
             INNER JOIN booking_services bs ON bs.id = bss.service_id
             INNER JOIN booking_time_slots ts ON ts.id = bss.slot_id
             WHERE bss.is_active = 1 AND bs.is_active = 1 AND ts.is_active = 1
             ORDER BY ts.sort_order ASC, ts.db_time ASC"
        );
        if ($slotMapResult instanceof mysqli_result) {
            while ($row = $slotMapResult->fetch_assoc()) {
                $serviceKey = (string)$row['service_key'];
                $slotKey = (string)$row['slot_key'];
                if (!isset($services[$serviceKey])) {
                    continue;
                }
                if (isset($timeSlotMap[$slotKey])) {
                    $services[$serviceKey]['slot_keys'][] = $slotKey;
                }
            }
        }
    }

    $serviceLocationStylists = [];
    $allStylistKeys = array_keys($stylists);
    foreach ($services as $serviceKey => $_meta) {
        $serviceLocationStylists[$serviceKey] = ['all' => []];
        foreach (array_keys($locations) as $locationKey) {
            $serviceLocationStylists[$serviceKey][$locationKey] = [];
        }
    }

    $serviceStylistResult = $mysqli->query(
        "SELECT bs.service_key, bl.location_key, bst.stylist_key
         FROM booking_service_stylists bss
         INNER JOIN booking_services bs ON bs.id = bss.service_id
         INNER JOIN booking_stylists bst ON bst.id = bss.stylist_id
         LEFT JOIN booking_locations bl ON bl.id = bss.location_id
         WHERE bss.is_active = 1 AND bs.is_active = 1 AND bst.is_active = 1"
    );
    if ($serviceStylistResult instanceof mysqli_result) {
        while ($row = $serviceStylistResult->fetch_assoc()) {
            $serviceKey = (string)$row['service_key'];
            $stylistKey = (string)$row['stylist_key'];
            $locationKey = trim((string)$row['location_key']);

            if (!isset($serviceLocationStylists[$serviceKey])) {
                continue;
            }

            if (!in_array($stylistKey, $serviceLocationStylists[$serviceKey]['all'], true)) {
                $serviceLocationStylists[$serviceKey]['all'][] = $stylistKey;
            }

            if ($locationKey !== '' && isset($serviceLocationStylists[$serviceKey][$locationKey])) {
                if (!in_array($stylistKey, $serviceLocationStylists[$serviceKey][$locationKey], true)) {
                    $serviceLocationStylists[$serviceKey][$locationKey][] = $stylistKey;
                }
            }
        }
    }

    foreach ($serviceLocationStylists as $serviceKey => $locationMap) {
        if (!$locationMap['all']) {
            $serviceLocationStylists[$serviceKey]['all'] = $allStylistKeys;
        }

        foreach (array_keys($locations) as $locationKey) {
            if (empty($serviceLocationStylists[$serviceKey][$locationKey])) {
                $serviceLocationStylists[$serviceKey][$locationKey] = $serviceLocationStylists[$serviceKey]['all'];
            }
        }
    }

    $serviceDepositMap = [];
    foreach ($services as $serviceKey => $serviceMeta) {
        $serviceDepositMap[$serviceKey] = round(((float)$serviceMeta['base_price']) * BOOKING_DEPOSIT_PERCENTAGE, 2);
    }

    return [
        'services' => $services,
        'serviceOrder' => $serviceOrder,
        'locations' => $locations,
        'stylists' => $stylists,
        'serviceLocationStylists' => $serviceLocationStylists,
        'timeSlotMap' => $timeSlotMap,
        'serviceDepositMap' => $serviceDepositMap,
    ];
}

function getBusinessInfo(mysqli $mysqli): array
{
    $defaults = [
        'brand_name' => 'DIOS Hair | Makeup',
        'phone_whatsapp' => '073 266 8348',
        'phone_call' => '073 266 8348',
        'whatsapp_url' => 'https://wa.me/27732668348',
        'hours_midrand' => 'Mon-Sat: 5am-6pm | Sun: Closed',
        'hours_copperleaf' => 'Tue-Sat: 5am-6pm | Sun: Closed',
        'address_midrand' => '5 Liebenberg Road, Noordwyk',
        'address_copperleaf' => 'Copperleaf Golf & Country Estate (Appointment only)',
    ];

    if (!tableExists($mysqli, 'business_settings')) {
        return $defaults;
    }

    $result = $mysqli->query("SELECT setting_key, setting_value FROM business_settings WHERE is_active = 1");
    if (!$result instanceof mysqli_result) {
        return $defaults;
    }

    while ($row = $result->fetch_assoc()) {
        $key = (string)$row['setting_key'];
        if (array_key_exists($key, $defaults)) {
            $defaults[$key] = (string)$row['setting_value'];
        }
    }

    return $defaults;
}

function getServicePriceMap(?mysqli $mysqli = null): array
{
    if ($mysqli instanceof mysqli) {
        $catalog = getBookingCatalog($mysqli);
        $map = [];
        foreach ($catalog['services'] as $serviceKey => $serviceMeta) {
            $map[$serviceKey] = (float)$serviceMeta['base_price'];
        }
        if ($map) {
            return $map;
        }
    }

    $defaults = getDefaultBookingCatalog();
    $map = [];
    foreach ($defaults['services'] as $serviceKey => $serviceMeta) {
        $map[$serviceKey] = (float)$serviceMeta['base_price'];
    }
    return $map;
}

function getServiceDepositMap(?mysqli $mysqli = null): array
{
    $deposits = [];
    foreach (getServicePriceMap($mysqli) as $service => $basePrice) {
        $deposits[$service] = round($basePrice * BOOKING_DEPOSIT_PERCENTAGE, 2);
    }
    return $deposits;
}

function getBookingDepositAmount(string $service = '', ?array $serviceDepositMap = null): string
{
    $priceMap = is_array($serviceDepositMap) ? $serviceDepositMap : getServiceDepositMap();
    $normalizedService = strtolower(trim($service));

    if ($normalizedService !== '' && isset($priceMap[$normalizedService])) {
        $amount = $priceMap[$normalizedService];
    } else {
        $amount = BOOKING_DEPOSIT_AMOUNT;
    }

    return number_format($amount, 2, '.', '');
}

function getDepositPercentageLabel(): string
{
    return (string)round(BOOKING_DEPOSIT_PERCENTAGE * 100) . '%';
}

function getMaxAdminUsers(): int
{
    return MAX_ADMIN_USERS;
}

function getMaxStylistsPerSlot(): int
{
    return MAX_STYLISTS_PER_SLOT;
}

function getPaymentConfigIssues(): array
{
    $issues = [];

    if (PAYFAST_MERCHANT_ID === '') {
        $issues[] = 'PayFast merchant ID is not configured.';
    }

    if (PAYFAST_MERCHANT_KEY === '') {
        $issues[] = 'PayFast merchant key is not configured.';
    }

    if (!extension_loaded('curl')) {
        $issues[] = 'PHP cURL extension is not enabled.';
    }

    return $issues;
}

function ensurePaymentAttemptsTable(mysqli $mysqli): bool
{
    $sql = "
        CREATE TABLE IF NOT EXISTS booking_payment_attempts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            m_payment_id VARCHAR(100) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NULL,
            phone VARCHAR(30) NOT NULL,
            service VARCHAR(100) NOT NULL,
            location VARCHAR(100) NOT NULL,
            stylist VARCHAR(100) NOT NULL,
            sub_type VARCHAR(100) NULL,
            hair_length VARCHAR(100) NULL,
            preferred_date DATE NOT NULL,
            preferred_time TIME NOT NULL,
            notes TEXT NULL,
            amount DECIMAL(10,2) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'initiated',
            booking_id INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_bpa_payment_id (m_payment_id),
            INDEX idx_bpa_status (status),
            INDEX idx_bpa_date_time (preferred_date, preferred_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    return $mysqli->query($sql) === true;
}
