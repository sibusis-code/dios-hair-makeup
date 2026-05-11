<?php
// Database and PayFast configuration.
// Update these values to match your xneelo + PayFast account.

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

define('DB_HOST', envOrDefault('DB_HOST', '127.0.0.1'));
define('DB_NAME', envOrDefault('DB_NAME', 'dios_salon'));
define('DB_USER', envOrDefault('DB_USER', 'root'));
define('DB_PASS', envOrDefault('DB_PASS', 'Dartcom@2025'));

define('PAYFAST_MERCHANT_ID', envOrDefault('PAYFAST_MERCHANT_ID', ''));
define('PAYFAST_MERCHANT_KEY', envOrDefault('PAYFAST_MERCHANT_KEY', ''));

// Keep true while testing in sandbox. Set to false for live payments.
define('PAYFAST_SANDBOX', envToBool('PAYFAST_SANDBOX', true));

// Optional: set your PayFast passphrase if configured in PayFast dashboard.
define('PAYFAST_PASSPHRASE', envOrDefault('PAYFAST_PASSPHRASE', ''));

// Optional override. Leave blank to auto-detect host and subfolder path.
define('SITE_URL', envOrDefault('SITE_URL', ''));

// Optional explicit notify URL for ITN (use a public HTTPS URL, not localhost).
define('PAYFAST_NOTIFY_URL_OVERRIDE', envOrDefault('PAYFAST_NOTIFY_URL_OVERRIDE', ''));

// Fixed deposit amount charged at checkout (server-calculated, not user-controlled).
define('BOOKING_DEPOSIT_AMOUNT', envToMoney('BOOKING_DEPOSIT_AMOUNT', 500.00));

function getDbConnection(): mysqli
{
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

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
        return 'http://localhost';
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
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
        if ($value === '') {
            continue;
        }
        $payload[] = $key . '=' . urlencode(trim((string)$value));
    }

    if ($passphrase !== '') {
        $payload[] = 'passphrase=' . urlencode(trim($passphrase));
    }

    return md5(implode('&', $payload));
}

function getServicePriceMap(): array
{
    return [
        'braids' => 800.00,
        'cornrows' => 600.00,
        'ponytail' => 400.00,
        'wig-installation' => 750.00,
        'hair-colour' => 500.00,
        'other-styling' => 500.00,
        'makeup' => 400.00,
        'mobile' => 200.00,
        'other' => 500.00
    ];
}

function getBookingDepositAmount(string $service = ''): string
{
    $priceMap = getServicePriceMap();
    $normalizedService = strtolower(trim($service));
    
    if ($normalizedService !== '' && isset($priceMap[$normalizedService])) {
        $amount = $priceMap[$normalizedService];
    } else {
        $amount = BOOKING_DEPOSIT_AMOUNT;
    }
    
    return number_format($amount, 2, '.', '');
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
