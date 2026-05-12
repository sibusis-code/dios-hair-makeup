<?php
require_once __DIR__ . '/admin-functions.php';

requireAdminLogin();
$admin = getAdminUser();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if ($currentPassword === '') {
        $messageType = 'error';
        $message = 'Current password is required.';
    } elseif ($newPassword === '') {
        $messageType = 'error';
        $message = 'New password is required.';
    } elseif ($newPassword !== $confirmPassword) {
        $messageType = 'error';
        $message = 'New passwords do not match.';
    } elseif (strlen($newPassword) < 6) {
        $messageType = 'error';
        $message = 'New password must be at least 6 characters long.';
    } else {
        // Change password
        $result = changeAdminPassword($_SESSION['admin_id'], $currentPassword, $newPassword);
        
        if ($result['success']) {
            $messageType = 'success';
            $message = $result['message'];
        } else {
            $messageType = 'error';
            $message = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - DIOS CRM</title>
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
        
        .form-card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            max-width: 500px;
            margin: 0 auto;
        }
        
        .form-card h2 {
            margin: 0 0 1.5rem 0;
            color: #1a1a1a;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin: 0 0 0.5rem 0;
            color: #333;
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
            font-family: 'Montserrat', sans-serif;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #c9a961;
            box-shadow: 0 0 0 3px rgba(201, 169, 97, 0.1);
        }
        
        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn {
            flex: 1;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #c9a961;
            color: white;
        }
        
        .btn-primary:hover {
            background: #b89957;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .message {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            display: none;
        }
        
        .message.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            display: block;
        }
        
        .message.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            display: block;
        }
        
        .back-link {
            margin-top: 1.5rem;
            text-align: center;
        }
        
        .back-link a {
            color: #c9a961;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .back-link a:hover {
            text-decoration: underline;
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
            
            .form-card {
                max-width: 100%;
            }
            
            .button-group {
                flex-direction: column;
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
                <li><a href="admin-dashboard.php">Dashboard</a></li>
                <li><a href="admin-dashboard.php?status=pending">Pending</a></li>
                <li><a href="admin-dashboard.php?status=confirmed">Confirmed</a></li>
                <li><a href="admin-dashboard.php?status=paid">Paid</a></li>
                <li><a href="admin-dashboard.php?status=completed">Completed</a></li>
                <li><a href="admin-dashboard.php?status=cancelled">Cancelled</a></li>
                <li><a href="admin-change-password.php" class="active" style="margin-top: 2rem; border-left-color: #c9a961;">Change Password</a></li>
                <li><a href="admin-logout.php" style="border-left-color: #e74c3c;">Logout</a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <div class="admin-main">
            <header class="admin-header">
                <h1>Change Password</h1>
                <div class="admin-user-info">
                    <div>
                        <p class="name"><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="role"><?php echo ucfirst($admin['role']); ?></p>
                    </div>
                    <a href="admin-logout.php" class="btn-logout">Logout</a>
                </div>
            </header>
            
            <div class="admin-content">
                <div class="form-card">
                    <h2>Update Your Password</h2>
                    
                    <?php if ($message): ?>
                        <div class="message <?php echo $messageType; ?>">
                            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" minlength="6" required>
                            <small style="color: #888; font-size: 0.85rem;">Minimum 6 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" minlength="6" required>
                        </div>
                        
                        <div class="button-group">
                            <button type="submit" class="btn btn-primary">Update Password</button>
                            <a href="admin-dashboard.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                    
                    <div class="back-link">
                        <a href="admin-dashboard.php">← Back to Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
