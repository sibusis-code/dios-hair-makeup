<?php
// Admin functions for authentication and CRM operations
require_once __DIR__ . '/config.php';

define('ADMIN_SESSION_TIMEOUT', 3600); // 1 hour

function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
        session_start();
    }
}

function isAdminLoggedIn(): bool
{
    startAdminSession();
    
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
        return false;
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > ADMIN_SESSION_TIMEOUT) {
        adminLogout();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

function requireAdminLogin(): void
{
    if (!isAdminLoggedIn()) {
        http_response_code(302);
        header('Location: admin-login.php');
        exit;
    }
}

function getAdminUser(): ?array
{
    if (!isAdminLoggedIn()) {
        return null;
    }
    
    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare('SELECT id, username, email, first_name, last_name, role FROM admin_users WHERE id = ? AND is_active = 1');
    $adminId = $_SESSION['admin_id'];
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $mysqli->close();
    
    return $user ?: null;
}

function adminLogin(string $username, string $password): bool
{
    startAdminSession();

    // Brute force protection: max 10 attempts per session within 15 minutes.
    $now = time();
    $windowSeconds = 900; // 15 minutes
    $maxAttempts = 10;

    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }

    // Remove attempts older than the window.
    $_SESSION['login_attempts'] = array_filter(
        $_SESSION['login_attempts'],
        static fn(int $t): bool => ($now - $t) < $windowSeconds
    );

    if (count($_SESSION['login_attempts']) >= $maxAttempts) {
        return false; // Locked out — too many attempts.
    }

    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare('SELECT id, username, email, password_hash, first_name, last_name, role FROM admin_users WHERE username = ? AND is_active = 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $mysqli->close();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $_SESSION['login_attempts'][] = $now; // Record failed attempt.
        return false;
    }

    // Successful login — regenerate session ID to prevent session fixation.
    session_regenerate_id(true);
    $_SESSION['login_attempts'] = []; // Clear failed attempts on success.
    $_SESSION['admin_id'] = (int)$user['id'];
    $_SESSION['admin_username'] = $user['username'];
    $_SESSION['admin_email'] = $user['email'];
    $_SESSION['admin_role'] = $user['role'];
    $_SESSION['last_activity'] = time();

    return true;
}

function adminLogout(): void
{
    startAdminSession();
    session_destroy();
}

function changeAdminPassword(int $adminId, string $currentPassword, string $newPassword): array
{
    $mysqli = getDbConnection();
    
    // Get current password hash
    $stmt = $mysqli->prepare('SELECT password_hash FROM admin_users WHERE id = ? AND is_active = 1');
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        $mysqli->close();
        return ['success' => false, 'message' => 'Admin user not found.'];
    }
    
    // Verify current password
    if (!password_verify($currentPassword, $user['password_hash'])) {
        $mysqli->close();
        return ['success' => false, 'message' => 'Current password is incorrect.'];
    }
    
    // Validate new password
    if (strlen($newPassword) < 6) {
        $mysqli->close();
        return ['success' => false, 'message' => 'New password must be at least 6 characters long.'];
    }
    
    // Hash and update new password
    $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt = $mysqli->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
    $stmt->bind_param('si', $newPasswordHash, $adminId);
    $success = $stmt->execute();
    $stmt->close();
    $mysqli->close();
    
    if ($success) {
        return ['success' => true, 'message' => 'Password changed successfully.'];
    }
    return ['success' => false, 'message' => 'Failed to update password. Please try again.'];
}

function countActiveAdminUsers(): int
{
    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare('SELECT COUNT(*) AS total FROM admin_users WHERE is_active = 1');
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    $mysqli->close();

    return $count;
}

function getAdminUsers(): array
{
    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare('SELECT id, username, email, first_name, last_name, role, is_active, created_at FROM admin_users ORDER BY created_at DESC');
    $stmt->execute();
    $result = $stmt->get_result();
    $users = [];

    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    $stmt->close();
    $mysqli->close();

    return $users;
}

function createAdminUser(array $payload): array
{
    $firstName = trim((string)($payload['first_name'] ?? ''));
    $lastName = trim((string)($payload['last_name'] ?? ''));
    $username = trim((string)($payload['username'] ?? ''));
    $email = trim((string)($payload['email'] ?? ''));
    $role = trim((string)($payload['role'] ?? 'staff'));
    $password = (string)($payload['password'] ?? '');

    if ($firstName === '' || $lastName === '' || $username === '' || $email === '' || $password === '') {
        return ['success' => false, 'message' => 'All user fields are required.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Email address is not valid.'];
    }

    if (strlen($password) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters long.'];
    }

    $allowedRoles = ['admin', 'manager', 'staff'];
    if (!in_array($role, $allowedRoles, true)) {
        $role = 'staff';
    }

    $activeUsers = countActiveAdminUsers();
    if ($activeUsers >= getMaxAdminUsers()) {
        return ['success' => false, 'message' => 'User limit reached. Maximum ' . getMaxAdminUsers() . ' users allowed.'];
    }

    $mysqli = getDbConnection();
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $mysqli->prepare('INSERT INTO admin_users (username, email, password_hash, first_name, last_name, role, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)');
    $stmt->bind_param('ssssss', $username, $email, $passwordHash, $firstName, $lastName, $role);
    $success = $stmt->execute();

    if (!$success) {
        $errorCode = $stmt->errno;
        $stmt->close();
        $mysqli->close();

        if ($errorCode === 1062) {
            return ['success' => false, 'message' => 'Username or email already exists.'];
        }

        return ['success' => false, 'message' => 'Failed to create user.'];
    }

    $stmt->close();
    $mysqli->close();
    return ['success' => true, 'message' => 'User created successfully.'];
}

function getAllBookings(string $status = '', string $sortBy = 'appointment_date'): array
{
    $mysqli = getDbConnection();
    
    $query = 'SELECT b.*, s.name as stylist_name FROM salon_bookings b LEFT JOIN stylists s ON b.stylist_id = s.id';
    $params = [];
    $types = '';
    
    if ($status !== '') {
        $query .= ' WHERE b.status = ?';
        $params[] = $status;
        $types .= 's';
    }
    
    $allowedSort = ['appointment_date', 'created_at', 'amount', 'status', 'name'];
    if (in_array($sortBy, $allowedSort, true)) {
        $query .= ' ORDER BY b.' . $sortBy . ' DESC';
    } else {
        $query .= ' ORDER BY b.appointment_date DESC';
    }
    
    $stmt = $mysqli->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $bookings = [];
    
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    
    $stmt->close();
    $mysqli->close();
    
    return $bookings;
}

function getBookingById(int $bookingId): ?array
{
    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare('SELECT b.*, s.name as stylist_name FROM salon_bookings b LEFT JOIN stylists s ON b.stylist_id = s.id WHERE b.id = ?');
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();
    $stmt->close();
    $mysqli->close();
    
    return $booking ?: null;
}

function updateBookingStatus(int $bookingId, string $newStatus): bool
{
    $validStatuses = ['pending', 'confirmed', 'paid', 'completed', 'cancelled'];
    if (!in_array($newStatus, $validStatuses, true)) {
        return false;
    }
    
    // Get booking details before update
    $booking = getBookingById($bookingId);
    if (!$booking) {
        return false;
    }
    
    $oldStatus = $booking['status'];
    
    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare('UPDATE salon_bookings SET status = ?, status_updated_at = NOW() WHERE id = ?');
    $stmt->bind_param('si', $newStatus, $bookingId);
    $success = $stmt->execute();
    $stmt->close();
    $mysqli->close();
    
    if ($success && SEND_CLIENT_EMAILS && $newStatus !== $oldStatus) {
        require_once __DIR__ . '/mail-functions.php';
        sendStatusUpdateEmail($booking, $newStatus);
    }
    
    return $success;
}

function rescheduleBooking(int $bookingId, string $newDate, string $newTime): bool
{
    $mysqli = getDbConnection();

    $booking = getBookingById($bookingId);
    if (!$booking) {
        $mysqli->close();
        return false;
    }

    // Check total slot capacity
    $stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM salon_bookings WHERE appointment_date = ? AND appointment_time = ? AND id != ? AND status = "paid"');
    $stmt->bind_param('ssi', $newDate, $newTime, $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ((int)$row['count'] >= getMaxStylistsPerSlot()) {
        $mysqli->close();
        return false; // Slot not available
    }

    // Check stylist-specific conflict if stylist selected
    $preferredStylist = trim((string)($booking['preferred_stylist'] ?? ''));
    if ($preferredStylist !== '' && $preferredStylist !== 'no-preference') {
        $stylistStmt = $mysqli->prepare('SELECT COUNT(*) AS count FROM salon_bookings WHERE appointment_date = ? AND appointment_time = ? AND preferred_stylist = ? AND id != ? AND status = "paid"');
        $stylistStmt->bind_param('sssi', $newDate, $newTime, $preferredStylist, $bookingId);
        $stylistStmt->execute();
        $stylistResult = $stylistStmt->get_result();
        $stylistRow = $stylistResult->fetch_assoc();
        $stylistStmt->close();

        if ((int)$stylistRow['count'] > 0) {
            $mysqli->close();
            return false;
        }
    }

    // Get old booking details before update
    $oldDate = $booking['appointment_date'];
    $oldTime = $booking['appointment_time'];
    
    // Update booking
    $stmt = $mysqli->prepare('UPDATE salon_bookings SET appointment_date = ?, appointment_time = ?, status_updated_at = NOW() WHERE id = ?');
    $stmt->bind_param('ssi', $newDate, $newTime, $bookingId);
    $success = $stmt->execute();
    $stmt->close();
    $mysqli->close();
    
    if ($success && SEND_CLIENT_EMAILS) {
        require_once __DIR__ . '/mail-functions.php';
        sendRescheduleEmail($booking, $oldDate, $oldTime);
    }
    
    return $success;
}

function cancelBooking(int $bookingId, string $reason = ''): bool
{
    // Get booking details before cancelling
    $booking = getBookingById($bookingId);
    if (!$booking) {
        return false;
    }
    
    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare('UPDATE salon_bookings SET status = "cancelled", cancellation_reason = ?, status_updated_at = NOW() WHERE id = ?');
    $stmt->bind_param('si', $reason, $bookingId);
    $success = $stmt->execute();
    $stmt->close();
    $mysqli->close();
    
    if ($success && SEND_CLIENT_EMAILS) {
        require_once __DIR__ . '/mail-functions.php';
        sendCancellationEmail($booking, $reason);
    }
    
    return $success;
}

function assignStylist(int $bookingId, int $stylistId): bool
{
    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare('UPDATE salon_bookings SET stylist_id = ? WHERE id = ?');
    $stmt->bind_param('ii', $stylistId, $bookingId);
    $success = $stmt->execute();
    $stmt->close();
    $mysqli->close();
    
    return $success;
}

function addBookingNote(int $bookingId, string $note): bool
{
    $adminId = $_SESSION['admin_id'] ?? null;
    if (!$adminId) {
        return false;
    }
    
    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare('INSERT INTO booking_notes (booking_id, admin_id, note) VALUES (?, ?, ?)');
    $stmt->bind_param('iis', $bookingId, $adminId, $note);
    $success = $stmt->execute();
    $stmt->close();
    $mysqli->close();
    
    return $success;
}

function getBookingNotes(int $bookingId): array
{
    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare('SELECT n.*, a.first_name, a.last_name FROM booking_notes n LEFT JOIN admin_users a ON n.admin_id = a.id WHERE n.booking_id = ? ORDER BY n.created_at DESC');
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $notes = [];
    
    while ($row = $result->fetch_assoc()) {
        $notes[] = $row;
    }
    
    $stmt->close();
    $mysqli->close();
    
    return $notes;
}

function getAllStylists(): array
{
    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare('SELECT * FROM stylists WHERE is_active = 1 ORDER BY name ASC');
    $stmt->execute();
    $result = $stmt->get_result();
    $stylists = [];
    
    while ($row = $result->fetch_assoc()) {
        $stylists[] = $row;
    }
    
    $stmt->close();
    $mysqli->close();
    
    return $stylists;
}

function getBookingStats(): array
{
    $mysqli = getDbConnection();
    $stats = [];

    // Total bookings ever
    $stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM salon_bookings');
    $stmt->execute();
    $stats['total_bookings'] = (int)$stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    // Today's appointments (any status)
    $stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM salon_bookings WHERE DATE(appointment_date) = CURDATE()');
    $stmt->execute();
    $stats['today_bookings'] = (int)$stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    // Upcoming paid bookings (future dates, status = paid)
    $stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM salon_bookings WHERE status = "paid" AND appointment_date >= CURDATE()');
    $stmt->execute();
    $stats['upcoming_bookings'] = (int)$stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    // Completed bookings
    $stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM salon_bookings WHERE status = "completed"');
    $stmt->execute();
    $stats['completed_bookings'] = (int)$stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    // Total deposits collected (paid + completed)
    $stmt = $mysqli->prepare('SELECT COALESCE(SUM(amount), 0) as total FROM salon_bookings WHERE status IN ("paid", "completed")');
    $stmt->execute();
    $stats['total_revenue'] = (float)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // This week's paid bookings (Mon–Sun)
    $stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM salon_bookings WHERE status IN ("paid","completed") AND YEARWEEK(appointment_date, 1) = YEARWEEK(CURDATE(), 1)');
    $stmt->execute();
    $stats['this_week_bookings'] = (int)$stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    $mysqli->close();
    return $stats;
}

function getTodaysBookings(): array
{
    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare(
        'SELECT b.*, s.name as stylist_name FROM salon_bookings b
         LEFT JOIN stylists s ON b.stylist_id = s.id
         WHERE DATE(b.appointment_date) = CURDATE()
         ORDER BY b.appointment_time ASC'
    );
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    $mysqli->close();
    return $rows;
}

function getSlotOverviewForDate(string $date): array
{
    $slotMap = [
        '07:30:00' => '07:30 AM',
        '08:00:00' => '08:00 AM',
        '09:00:00' => '09:00 AM',
        '10:00:00' => '10:00 AM',
        '11:00:00' => '11:00 AM',
        '11:30:00' => '11:30 AM',
        '12:00:00' => '12:00 PM',
        '13:00:00' => '01:00 PM',
        '14:00:00' => '02:00 PM',
        '14:30:00' => '02:30 PM',
        '15:00:00' => '03:00 PM',
        '16:00:00' => '04:00 PM',
        '17:00:00' => '05:00 PM',
        '18:00:00' => '06:00 PM'
    ];

    $overview = [];
    foreach ($slotMap as $dbTime => $label) {
        $overview[$dbTime] = [
            'time_db' => $dbTime,
            'time_label' => $label,
            'booked' => 0,
            'open' => getMaxStylistsPerSlot(),
            'stylists' => []
        ];
    }

    $mysqli = getDbConnection();
    $status = 'paid';
    $stmt = $mysqli->prepare('SELECT appointment_time, preferred_stylist FROM salon_bookings WHERE appointment_date = ? AND status = ? ORDER BY appointment_time ASC');
    $stmt->bind_param('ss', $date, $status);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $time = (string)$row['appointment_time'];
        if (!isset($overview[$time])) {
            continue;
        }

        $overview[$time]['booked']++;
        $overview[$time]['open'] = max(0, getMaxStylistsPerSlot() - $overview[$time]['booked']);

        $stylist = trim((string)($row['preferred_stylist'] ?? ''));
        if ($stylist !== '') {
            $overview[$time]['stylists'][] = $stylist;
        }
    }

    $stmt->close();
    $mysqli->close();

    return array_values($overview);
}
