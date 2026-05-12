<?php
require_once __DIR__ . '/admin-functions.php';

requireAdminLogin();
$admin = getAdminUser();
$bookings = getAllBookings();
$stats = getBookingStats();
$stylists = getAllStylists();

// Filter bookings
$statusFilter = $_GET['status'] ?? '';
$filteredBookings = $bookings;

if ($statusFilter !== '') {
    $filteredBookings = array_filter($bookings, function($b) use ($statusFilter) {
        return $b['status'] === $statusFilter;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - DIOS CRM</title>
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
        
        .admin-nav li {
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
            padding-left: 1.2rem;
        }
        
        .admin-main {
            flex: 1;
            margin-left: 250px;
        }
        
        .admin-header {
            background: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .admin-header h1 {
            margin: 0;
            font-size: 1.5rem;
            color: #1a1a1a;
        }
        
        .admin-user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .admin-user-info p {
            margin: 0;
            font-size: 0.9rem;
        }
        
        .admin-user-info .name {
            font-weight: 600;
            color: #333;
        }
        
        .admin-user-info .role {
            color: #888;
            font-size: 0.85rem;
        }
        
        .btn-logout {
            padding: 0.5rem 1rem;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            text-decoration: none;
        }
        
        .btn-logout:hover {
            background: #c0392b;
        }
        
        .admin-content {
            padding: 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #c9a961;
        }
        
        .stat-card h3 {
            margin: 0 0 0.5rem 0;
            color: #888;
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card .number {
            font-size: 2rem;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0;
        }
        
        .stat-card .currency {
            font-size: 1.5rem;
            color: #c9a961;
        }
        
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .filters label {
            margin: 0;
            font-weight: 500;
            color: #333;
        }
        
        .filters select {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .bookings-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        
        table thead {
            background: #f9f9f9;
            border-bottom: 2px solid #eee;
        }
        
        table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        table td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            font-size: 0.95rem;
        }
        
        table tbody tr:hover {
            background: #f9f9f9;
        }
        
        .badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-pending {
            background: #ffeaa7;
            color: #d63031;
        }
        
        .badge-confirmed {
            background: #a8dadc;
            color: #1d3557;
        }
        
        .badge-paid {
            background: #c7f0d8;
            color: #06a77d;
        }
        
        .badge-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-small {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        
        .btn-edit {
            background: #3498db;
            color: white;
        }
        
        .btn-edit:hover {
            background: #2980b9;
        }
        
        .btn-cancel {
            background: #e74c3c;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #c0392b;
        }
        
        .btn-view {
            background: #95a5a6;
            color: white;
        }
        
        .btn-view:hover {
            background: #7f8c8d;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #888;
        }
        
        .empty-state p {
            margin: 0;
            font-size: 1.1rem;
        }
        
        @media (max-width: 768px) {
            .admin-wrapper {
                flex-direction: column;
            }
            
            .admin-sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .admin-main {
                margin-left: 0;
            }
            
            .admin-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filters {
                flex-direction: column;
            }
            
            table {
                font-size: 0.85rem;
            }
            
            table th, table td {
                padding: 0.75rem 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <h2>DIOS CRM</h2>
            <ul class="admin-nav">
                <li><a href="admin-dashboard.php" class="active">Dashboard</a></li>
                <li><a href="admin-dashboard.php?status=pending">Pending</a></li>
                <li><a href="admin-dashboard.php?status=confirmed">Confirmed</a></li>
                <li><a href="admin-dashboard.php?status=paid">Paid</a></li>
                <li><a href="admin-dashboard.php?status=completed">Completed</a></li>
                <li><a href="admin-dashboard.php?status=cancelled">Cancelled</a></li>
                <li><a href="admin-change-password.php" style="margin-top: 2rem;">Change Password</a></li>
                <li><a href="admin-logout.php" style="border-left-color: #e74c3c;">Logout</a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <div class="admin-main">
            <header class="admin-header">
                <h1>Dashboard</h1>
                <div class="admin-user-info">
                    <div>
                        <p class="name"><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="role"><?php echo ucfirst($admin['role']); ?></p>
                    </div>
                    <a href="admin-change-password.php" style="padding: 0.5rem 1rem; background: #2980b9; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem; text-decoration: none; display: inline-block; transition: all 0.2s; margin-right: 0.5rem;">Change Password</a>
                    <a href="admin-logout.php" class="btn-logout">Logout</a>
                </div>
            </header>
            
            <div class="admin-content">
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Bookings</h3>
                        <p class="number"><?php echo $stats['total_bookings']; ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Pending Confirmation</h3>
                        <p class="number"><?php echo $stats['pending_bookings']; ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Today's Bookings</h3>
                        <p class="number"><?php echo $stats['today_bookings']; ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Total Revenue</h3>
                        <p class="number">
                            <span class="currency">R</span><?php echo number_format($stats['total_revenue'], 2); ?>
                        </p>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="filters">
                    <label for="statusFilter">Filter by Status:</label>
                    <select id="statusFilter" onchange="window.location.href = this.value;">
                        <option value="admin-dashboard.php" <?php echo $statusFilter === '' ? 'selected' : ''; ?>>All</option>
                        <option value="admin-dashboard.php?status=pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="admin-dashboard.php?status=confirmed" <?php echo $statusFilter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="admin-dashboard.php?status=paid" <?php echo $statusFilter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="admin-dashboard.php?status=completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="admin-dashboard.php?status=cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <!-- Bookings Table -->
                <div class="bookings-table">
                    <?php if (count($filteredBookings) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client Name</th>
                                    <th>Date & Time</th>
                                    <th>Service</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filteredBookings as $booking): ?>
                                    <tr>
                                        <td>#<?php echo $booking['id']; ?></td>
                                        <td><?php echo htmlspecialchars($booking['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($booking['appointment_date'])); ?>
                                            <br>
                                            <?php echo date('g:i A', strtotime($booking['appointment_time'])); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($booking['service'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>R<?php echo number_format($booking['amount'], 2); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $booking['status']; ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="admin-booking-detail.php?id=<?php echo $booking['id']; ?>" class="btn-small btn-view">View</a>
                                                <?php if ($booking['status'] !== 'cancelled' && $booking['status'] !== 'completed'): ?>
                                                    <a href="admin-booking-edit.php?id=<?php echo $booking['id']; ?>" class="btn-small btn-edit">Edit</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>No bookings found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
