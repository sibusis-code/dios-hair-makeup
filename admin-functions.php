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
    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare('SELECT id, username, email, password_hash, first_name, last_name, role FROM admin_users WHERE username = ? AND is_active = 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $mysqli->close();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    
    startAdminSession();
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
    
    // Check if slot is available
    $stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM salon_bookings WHERE appointment_date = ? AND appointment_time = ? AND id != ? AND status = "paid"');
    $stmt->bind_param('ssi', $newDate, $newTime, $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row['count'] > 0) {
        $mysqli->close();
        return false; // Slot not available
    }
    
    // Get old booking details before update
    $booking = getBookingById($bookingId);
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
    
    // Total bookings
    $stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM salon_bookings');
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_bookings'] = $result->fetch_assoc()['count'];
    $stmt->close();
    
    // Pending bookings
    $stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM salon_bookings WHERE status IN ("pending", "confirmed")');
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['pending_bookings'] = $result->fetch_assoc()['count'];
    $stmt->close();
    
    // Total revenue
    $stmt = $mysqli->prepare('SELECT SUM(amount) as total FROM salon_bookings WHERE status = "paid"');
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_revenue'] = (float)($result->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    
    // Today's bookings
    $stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM salon_bookings WHERE DATE(appointment_date) = CURDATE()');
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['today_bookings'] = $result->fetch_assoc()['count'];
    $stmt->close();
    
    $mysqli->close();
    
    return $stats;
}
