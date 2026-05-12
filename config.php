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

define('DB_HOST', envOrDefault('DB_HOST', '127.0.0.1'));
define('DB_NAME', envOrDefault('DB_NAME', 'dios_salon'));
define('DB_USER', envOrDefault('DB_USER', 'root'));
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

// Fixed deposit amount charged at checkout (server-calculated, not user-controlled).
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
