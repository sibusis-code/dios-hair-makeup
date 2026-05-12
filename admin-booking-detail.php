<?php
require_once __DIR__ . '/admin-functions.php';

requireAdminLogin();
$admin = getAdminUser();

$bookingId = (int)($_GET['id'] ?? 0);
if ($bookingId <= 0) {
    http_response_code(404);
    die('Booking not found.');
}

$booking = getBookingById($bookingId);
if (!$booking) {
    http_response_code(404);
    die('Booking not found.');
}

$notes = getBookingNotes($bookingId);
$stylists = getAllStylists();
$addNoteError = '';
$addNoteSuccess = '';

// Handle adding a note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $note = trim($_POST['note'] ?? '');
    if ($note !== '') {
        if (addBookingNote($bookingId, $note)) {
            $addNoteSuccess = 'Note added successfully.';
            $notes = getBookingNotes($bookingId);
        } else {
            $addNoteError = 'Failed to add note.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details - DIOS CRM</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background: #f5f5f5;
            font-family: 'Montserrat', sans-serif;
        }
        
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .admin-sidebar {
            width: 250px;
            background: #1a1a1a;
            color: white;
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .admin-sidebar h2 {
            padding: 0 1.5rem;
            font-size: 1.2rem;
            margin: 0 0 2rem 0;
            color: #c9a961;
        }
        
        .admin-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .admin-nav a {
            display: block;
            padding: 0.75rem 1.5rem;
            color: #ccc;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .admin-nav a:hover,
        .admin-nav a.active {
            background: #2d2d2d;
            color: #c9a961;
            border-left-color: #c9a961;
        }
        
        .admin-main {
            flex: 1;
            margin-left: 250px;
        }
        
        .admin-header {
            background: white;
            padding: 1.5rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .admin-header h1 {
            margin: 0;
            font-size: 1.5rem;
        }
        
        .admin-content {
            padding: 2rem;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .card h2 {
            margin: 0 0 1.5rem 0;
            font-size: 1.3rem;
            color: #1a1a1a;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 1rem;
        }
        
        .detail-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .detail-label {
            font-weight: 600;
            color: #666;
            font-size: 0.9rem;
        }
        
        .detail-value {
            color: #333;
        }
        
        .badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-pending { background: #ffeaa7; color: #d63031; }
        .badge-confirmed { background: #a8dadc; color: #1d3557; }
        .badge-paid { background: #c7f0d8; color: #06a77d; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }
        
        .action-buttons {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #c9a961;
            color: white;
        }
        
        .btn-primary:hover {
            background: #a88a4d;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        
        textarea, select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
            box-sizing: border-box;
        }
        
        textarea:focus, select:focus {
            outline: none;
            border-color: #c9a961;
            box-shadow: 0 0 0 3px rgba(201, 169, 97, 0.1);
        }
        
        .notes-section {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .note-item {
            padding: 1rem;
            background: #f9f9f9;
            border-left: 3px solid #c9a961;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        
        .note-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .note-author {
            font-weight: 600;
            color: #333;
        }
        
        .note-date {
            font-size: 0.85rem;
            color: #999;
        }
        
        .note-text {
            color: #555;
            line-height: 1.5;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #c7f0d8;
            border: 1px solid #06a77d;
            color: #06a77d;
        }
        
        .alert-error {
            background: #f8d7da;
            border: 1px solid #721c24;
            color: #721c24;
        }
        
        .back-link {
            color: #c9a961;
            text-decoration: none;
            margin-bottom: 1.5rem;
            display: inline-block;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .admin-wrapper { flex-direction: column; }
            .admin-sidebar { width: 100%; position: relative; height: auto; }
            .admin-main { margin-left: 0; }
            .detail-grid { grid-template-columns: 1fr; }
            .detail-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <aside class="admin-sidebar">
            <h2>DIOS CRM</h2>
            <ul class="admin-nav">
                <li><a href="admin-dashboard.php">Dashboard</a></li>
                <li><a href="admin-dashboard.php">Back</a></li>
            </ul>
        </aside>
        
        <div class="admin-main">
            <header class="admin-header">
                <h1>Booking #<?php echo $bookingId; ?></h1>
            </header>
            
            <div class="admin-content">
                <a href="admin-dashboard.php" class="back-link">← Back to Dashboard</a>
                
                <div class="detail-grid">
                    <div class="card">
                        <h2>Booking Details</h2>
                        
                        <div class="detail-row">
                            <span class="detail-label">Status</span>
                            <span class="detail-value">
                                <span class="badge badge-<?php echo $booking['status']; ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Client Name</span>
                            <span class="detail-value"><?php echo htmlspecialchars($booking['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Email</span>
                            <span class="detail-value"><a href="mailto:<?php echo htmlspecialchars($booking['email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($booking['email'], ENT_QUOTES, 'UTF-8'); ?></a></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Phone</span>
                            <span class="detail-value"><a href="tel:<?php echo htmlspecialchars($booking['phone'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($booking['phone'], ENT_QUOTES, 'UTF-8'); ?></a></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Date</span>
                            <span class="detail-value"><?php echo date('F d, Y', strtotime($booking['appointment_date'])); ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Time</span>
                            <span class="detail-value"><?php echo date('g:i A', strtotime($booking['appointment_time'])); ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Service</span>
                            <span class="detail-value"><?php echo htmlspecialchars($booking['service'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Amount</span>
                            <span class="detail-value">R<?php echo number_format($booking['amount'], 2); ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Payment ID</span>
                            <span class="detail-value" style="font-size: 0.85rem; word-break: break-all;"><?php echo htmlspecialchars($booking['m_payment_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        
                        <?php if ($booking['stylist_id']): ?>
                            <div class="detail-row">
                                <span class="detail-label">Stylist</span>
                                <span class="detail-value"><?php echo htmlspecialchars($booking['stylist_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($booking['cancellation_reason']): ?>
                            <div class="detail-row">
                                <span class="detail-label">Cancellation</span>
                                <span class="detail-value"><?php echo htmlspecialchars($booking['cancellation_reason'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="detail-row">
                            <span class="detail-label">Created</span>
                            <span class="detail-value"><?php echo date('F d, Y g:i A', strtotime($booking['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h2>Actions</h2>
                        
                        <div class="action-buttons" style="flex-direction: column;">
                            <?php if ($booking['status'] !== 'cancelled' && $booking['status'] !== 'completed'): ?>
                                <a href="admin-booking-edit.php?id=<?php echo $bookingId; ?>" class="btn btn-primary">Edit Booking</a>
                                <a href="admin-booking-reschedule.php?id=<?php echo $bookingId; ?>" class="btn btn-secondary">Reschedule</a>
                                <a href="admin-booking-cancel.php?id=<?php echo $bookingId; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel this booking?');">Cancel Booking</a>
                            <?php else: ?>
                                <p style="color: #999; margin: 0;">No actions available for this booking status.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Notes Section -->
                <div class="notes-section">
                    <h2>Notes & History</h2>
                    
                    <?php if ($addNoteSuccess): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($addNoteSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($addNoteError): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($addNoteError, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" style="margin-bottom: 2rem;">
                        <div class="form-group">
                            <label for="note">Add a Note</label>
                            <textarea id="note" name="note" rows="3" placeholder="Add internal notes about this booking..." required></textarea>
                        </div>
                        <button type="submit" name="add_note" class="btn btn-primary">Add Note</button>
                    </form>
                    
                    <?php if (count($notes) > 0): ?>
                        <div>
                            <h3 style="margin-top: 0; color: #666;">Recent Notes</h3>
                            <?php foreach ($notes as $note): ?>
                                <div class="note-item">
                                    <div class="note-header">
                                        <span class="note-author">
                                            <?php echo htmlspecialchars($note['first_name'] . ' ' . $note['last_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                        <span class="note-date">
                                            <?php echo date('F d, Y g:i A', strtotime($note['created_at'])); ?>
                                        </span>
                                    </div>
                                    <div class="note-text">
                                        <?php echo nl2br(htmlspecialchars($note['note'], ENT_QUOTES, 'UTF-8')); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #999;">No notes yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
