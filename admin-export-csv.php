<?php
require_once __DIR__ . '/admin-functions.php';

requireAdminLogin();

$view = $_GET['view'] ?? 'all';
$search = trim((string)($_GET['q'] ?? ''));

$allowedViews = ['all', 'upcoming', 'today', 'completed', 'cancelled'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'all';
}

switch ($view) {
    case 'today':
        $bookings = getTodaysBookings();
        break;
    case 'upcoming':
        $all = getAllBookings('paid');
        $bookings = array_filter($all, static fn(array $b): bool => (string)$b['appointment_date'] >= date('Y-m-d'));
        break;
    case 'completed':
        $bookings = getAllBookings('completed');
        break;
    case 'cancelled':
        $bookings = getAllBookings('cancelled');
        break;
    default:
        $bookings = getAllBookings();
        break;
}

if ($search !== '') {
    $s = strtolower($search);
    $bookings = array_filter($bookings, static function (array $b) use ($s): bool {
        return str_contains(strtolower((string)($b['name'] ?? '')), $s)
            || str_contains(strtolower((string)($b['phone'] ?? '')), $s)
            || str_contains(strtolower((string)($b['email'] ?? '')), $s)
            || str_contains(strtolower((string)($b['m_payment_id'] ?? '')), $s);
    });
}

$filename = 'dios-bookings-' . $view . '-' . date('Ymd-His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
if ($output === false) {
    http_response_code(500);
    exit;
}

fputcsv($output, [
    'Booking ID',
    'Client Name',
    'Phone',
    'Email',
    'Service',
    'Location',
    'Preferred Stylist',
    'Assigned Stylist',
    'Date',
    'Time',
    'Deposit Amount',
    'Status',
    'Reference',
    'Created At'
]);

foreach ($bookings as $b) {
    fputcsv($output, [
        (string)($b['id'] ?? ''),
        (string)($b['name'] ?? ''),
        (string)($b['phone'] ?? ''),
        (string)($b['email'] ?? ''),
        (string)($b['service'] ?? ''),
        (string)($b['location'] ?? ''),
        (string)($b['preferred_stylist'] ?? ''),
        (string)($b['stylist_name'] ?? ''),
        (string)($b['appointment_date'] ?? ''),
        (string)($b['appointment_time'] ?? ''),
        (string)($b['amount'] ?? ''),
        (string)($b['status'] ?? ''),
        (string)($b['m_payment_id'] ?? ''),
        (string)($b['created_at'] ?? '')
    ]);
}

fclose($output);
exit;
