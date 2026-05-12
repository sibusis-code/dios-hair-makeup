<?php
// Email functions for booking notifications
require_once __DIR__ . '/config.php';

// Email configuration
define('EMAIL_FROM_NAME', envOrDefault('EMAIL_FROM_NAME', 'DIOS Hair & Makeup'));
define('EMAIL_FROM_ADDRESS', envOrDefault('EMAIL_FROM_ADDRESS', 'bookings@dios.local'));
define('EMAIL_ADMIN_ADDRESS', envOrDefault('EMAIL_ADMIN_ADDRESS', 'admin@dios.local'));
define('EMAIL_USE_SMTP', envToBool('EMAIL_USE_SMTP', false));
define('EMAIL_SMTP_HOST', envOrDefault('EMAIL_SMTP_HOST', 'smtp.gmail.com'));
define('EMAIL_SMTP_PORT', (int)envOrDefault('EMAIL_SMTP_PORT', '587'));
define('EMAIL_SMTP_USER', envOrDefault('EMAIL_SMTP_USER', ''));
define('EMAIL_SMTP_PASS', envOrDefault('EMAIL_SMTP_PASS', ''));

/**
 * Send email using PHP mail() or SMTP
 */
function sendEmail(string $to, string $subject, string $htmlBody, string $plainBody = ''): bool
{
    $plainBody = $plainBody ?: strip_tags($htmlBody);
    
    if (!EMAIL_USE_SMTP) {
        return mailViaPhp($to, $subject, $htmlBody, $plainBody);
    }
    
    return mailViaSMTP($to, $subject, $htmlBody, $plainBody);
}

/**
 * Send email via PHP mail() function
 */
function mailViaPhp(string $to, string $subject, string $htmlBody, string $plainBody): bool
{
    $headers = [
        'From: ' . EMAIL_FROM_NAME . ' <' . EMAIL_FROM_ADDRESS . '>',
        'Reply-To: ' . EMAIL_FROM_ADDRESS,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: DIOS-CRM/1.0'
    ];
    
    return mail($to, $subject, $htmlBody, implode("\r\n", $headers));
}

/**
 * Send email via SMTP (using mail() with SMTP settings)
 * Note: For production, consider using PHPMailer or SwiftMailer
 */
function mailViaSMTP(string $to, string $subject, string $htmlBody, string $plainBody): bool
{
    // For basic PHP, SMTP is configured via php.ini
    // This is a fallback to PHP mail with SMTP headers
    
    if (EMAIL_SMTP_USER === '' || EMAIL_SMTP_PASS === '') {
        return mailViaPhp($to, $subject, $htmlBody, $plainBody);
    }
    
    // For proper SMTP, use this approach or install PHPMailer
    $headers = [
        'From: ' . EMAIL_FROM_NAME . ' <' . EMAIL_FROM_ADDRESS . '>',
        'Reply-To: ' . EMAIL_FROM_ADDRESS,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8'
    ];
    
    return mail($to, $subject, $htmlBody, implode("\r\n", $headers));
}

/**
 * Send booking confirmation email to client
 */
function sendBookingConfirmation(array $booking): bool
{
    $clientName = htmlspecialchars($booking['name'], ENT_QUOTES, 'UTF-8');
    $clientEmail = htmlspecialchars($booking['email'], ENT_QUOTES, 'UTF-8');
    $appointmentDate = date('l, F d, Y', strtotime($booking['appointment_date']));
    $appointmentTime = date('g:i A', strtotime($booking['appointment_time']));
    $service = htmlspecialchars($booking['service'] ?? 'Styling', ENT_QUOTES, 'UTF-8');
    $amount = number_format($booking['amount'], 2);
    $paymentId = htmlspecialchars($booking['m_payment_id'], ENT_QUOTES, 'UTF-8');
    
    $subject = 'Booking Confirmation - DIOS Hair & Makeup';
    
    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: #1a1a1a; color: white; padding: 20px; text-align: center; }
        .header h1 { margin: 0; color: #c9a961; }
        .content { padding: 20px; background: #f9f9f9; }
        .booking-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #c9a961; }
        .detail-row { display: grid; grid-template-columns: 150px 1fr; gap: 10px; margin-bottom: 10px; }
        .detail-label { font-weight: bold; color: #666; }
        .detail-value { color: #333; }
        .footer { padding: 15px; text-align: center; color: #999; font-size: 12px; }
        .button { display: inline-block; padding: 10px 20px; background: #c9a961; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>DIOS Hair & Makeup</h1>
            <p>Your booking is confirmed!</p>
        </div>
        
        <div class="content">
            <p>Hi $clientName,</p>
            
            <p>Thank you for booking with us! We're excited to see you. Here are your appointment details:</p>
            
            <div class="booking-details">
                <div class="detail-row">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value">$appointmentDate</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Time:</span>
                    <span class="detail-value">$appointmentTime</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Service:</span>
                    <span class="detail-value">$service</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Deposit Paid:</span>
                    <span class="detail-value">R$amount</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Reference:</span>
                    <span class="detail-value">$paymentId</span>
                </div>
            </div>
            
            <p><strong>Important:</strong></p>
            <ul>
                <li>Please arrive 10 minutes early</li>
                <li>If you need to reschedule, contact us at least 24 hours in advance</li>
                <li>Cancellations made less than 24 hours before may forfeit your deposit</li>
            </ul>
            
            <p>If you have any questions, please don't hesitate to contact us.</p>
            
            <p>We look forward to seeing you!</p>
            
            <p>Best regards,<br>The DIOS Team</p>
        </div>
        
        <div class="footer">
            <p>DIOS Hair & Makeup | Midrand & Copperleaf, Gauteng<br>
            Questions? Contact us: <a href="mailto:" . EMAIL_FROM_ADDRESS . ">bookings@dios.local</a></p>
        </div>
    </div>
</body>
</html>
HTML;

    $plainBody = "DIOS Hair & Makeup - Booking Confirmation\n\n";
    $plainBody .= "Hi $clientName,\n\n";
    $plainBody .= "Thank you for booking with us!\n\n";
    $plainBody .= "Your Appointment:\n";
    $plainBody .= "Date: $appointmentDate\n";
    $plainBody .= "Time: $appointmentTime\n";
    $plainBody .= "Service: $service\n";
    $plainBody .= "Deposit Paid: R$amount\n";
    $plainBody .= "Reference: $paymentId\n\n";
    $plainBody .= "Important:\n";
    $plainBody .= "- Please arrive 10 minutes early\n";
    $plainBody .= "- Reschedule requests need 24 hours notice\n";
    $plainBody .= "- Cancellations within 24 hours may forfeit your deposit\n\n";
    $plainBody .= "We look forward to seeing you!\n";
    $plainBody .= "The DIOS Team";
    
    return sendEmail($clientEmail, $subject, $htmlBody, $plainBody);
}

/**
 * Send status update email to client
 */
function sendStatusUpdateEmail(array $booking, string $newStatus): bool
{
    $clientName = htmlspecialchars($booking['name'], ENT_QUOTES, 'UTF-8');
    $clientEmail = htmlspecialchars($booking['email'], ENT_QUOTES, 'UTF-8');
    $appointmentDate = date('l, F d, Y', strtotime($booking['appointment_date']));
    $appointmentTime = date('g:i A', strtotime($booking['appointment_time']));
    
    $statusMessages = [
        'confirmed' => 'Your appointment has been confirmed by our team.',
        'paid' => 'Payment received! Your appointment is confirmed.',
        'completed' => 'Thank you for visiting DIOS! We hope you loved your experience.',
        'cancelled' => 'Your appointment has been cancelled. Please contact us for rescheduling.'
    ];
    
    $message = $statusMessages[$newStatus] ?? 'Your booking status has been updated.';
    
    $subject = 'Booking Status Update - DIOS Hair & Makeup';
    
    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: #1a1a1a; color: white; padding: 20px; text-align: center; }
        .header h1 { margin: 0; color: #c9a961; }
        .content { padding: 20px; background: #f9f9f9; }
        .status-box { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #c9a961; }
        .status-label { font-weight: bold; color: #c9a961; text-transform: uppercase; }
        .footer { padding: 15px; text-align: center; color: #999; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>DIOS Hair & Makeup</h1>
        </div>
        
        <div class="content">
            <p>Hi $clientName,</p>
            
            <div class="status-box">
                <p class="status-label">Status: " . ucfirst($newStatus) . "</p>
                <p>$message</p>
            </div>
            
            <p><strong>Appointment Details:</strong></p>
            <p>Date: $appointmentDate<br>
            Time: $appointmentTime</p>
            
            <p>If you have any questions, please contact us.</p>
            
            <p>Best regards,<br>The DIOS Team</p>
        </div>
        
        <div class="footer">
            <p>DIOS Hair & Makeup | Midrand & Copperleaf, Gauteng</p>
        </div>
    </div>
</body>
</html>
HTML;

    $plainBody = "DIOS Hair & Makeup - Status Update\n\n";
    $plainBody .= "Hi $clientName,\n\n";
    $plainBody .= "Status: " . ucfirst($newStatus) . "\n";
    $plainBody .= "$message\n\n";
    $plainBody .= "Appointment: $appointmentDate at $appointmentTime\n\n";
    $plainBody .= "The DIOS Team";
    
    return sendEmail($clientEmail, $subject, $htmlBody, $plainBody);
}

/**
 * Send cancellation email to client
 */
function sendCancellationEmail(array $booking, string $reason): bool
{
    $clientName = htmlspecialchars($booking['name'], ENT_QUOTES, 'UTF-8');
    $clientEmail = htmlspecialchars($booking['email'], ENT_QUOTES, 'UTF-8');
    $appointmentDate = date('l, F d, Y', strtotime($booking['appointment_date']));
    $appointmentTime = date('g:i A', strtotime($booking['appointment_time']));
    $reasonText = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
    
    $subject = 'Booking Cancelled - DIOS Hair & Makeup';
    
    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: #1a1a1a; color: white; padding: 20px; text-align: center; }
        .header h1 { margin: 0; color: #c9a961; }
        .content { padding: 20px; background: #f9f9f9; }
        .alert { background: #fff3cd; padding: 15px; margin: 15px 0; border-left: 4px solid #ffc107; }
        .footer { padding: 15px; text-align: center; color: #999; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>DIOS Hair & Makeup</h1>
        </div>
        
        <div class="content">
            <p>Hi $clientName,</p>
            
            <div class="alert">
                <p><strong>Your appointment has been cancelled.</strong></p>
                <p><strong>Reason:</strong> $reasonText</p>
            </div>
            
            <p><strong>Original Appointment:</strong></p>
            <p>Date: $appointmentDate<br>
            Time: $appointmentTime</p>
            
            <p>If you would like to reschedule or have questions, please contact us.</p>
            
            <p>Best regards,<br>The DIOS Team</p>
        </div>
        
        <div class="footer">
            <p>DIOS Hair & Makeup | Midrand & Copperleaf, Gauteng</p>
        </div>
    </div>
</body>
</html>
HTML;

    $plainBody = "DIOS Hair & Makeup - Booking Cancelled\n\n";
    $plainBody .= "Hi $clientName,\n\n";
    $plainBody .= "Your appointment has been cancelled.\n\n";
    $plainBody .= "Reason: $reasonText\n\n";
    $plainBody .= "Original Appointment:\n";
    $plainBody .= "Date: $appointmentDate\n";
    $plainBody .= "Time: $appointmentTime\n\n";
    $plainBody .= "Contact us if you'd like to reschedule.\n";
    $plainBody .= "The DIOS Team";
    
    return sendEmail($clientEmail, $subject, $htmlBody, $plainBody);
}

/**
 * Send admin notification of new booking
 */
function sendAdminNewBookingNotification(array $booking): bool
{
    $subject = 'New Booking - ' . htmlspecialchars($booking['name'], ENT_QUOTES, 'UTF-8') . ' - DIOS CRM';
    
    $clientName = htmlspecialchars($booking['name'], ENT_QUOTES, 'UTF-8');
    $clientEmail = htmlspecialchars($booking['email'], ENT_QUOTES, 'UTF-8');
    $clientPhone = htmlspecialchars($booking['phone'], ENT_QUOTES, 'UTF-8');
    $appointmentDate = date('l, F d, Y', strtotime($booking['appointment_date']));
    $appointmentTime = date('g:i A', strtotime($booking['appointment_time']));
    $service = htmlspecialchars($booking['service'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $amount = number_format($booking['amount'], 2);
    $paymentId = htmlspecialchars($booking['m_payment_id'], ENT_QUOTES, 'UTF-8');
    $status = htmlspecialchars($booking['status'], ENT_QUOTES, 'UTF-8');
    
    $siteUrl = getSiteBaseUrl();
    $bookingUrl = $siteUrl . '/admin-booking-detail.php?id=' . $booking['id'];
    
    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: #c9a961; color: white; padding: 15px; text-align: center; }
        .content { padding: 15px; }
        .details { background: #f9f9f9; padding: 10px; margin: 10px 0; }
        .detail { margin: 5px 0; }
        .button { display: inline-block; padding: 8px 15px; background: #c9a961; color: white; text-decoration: none; border-radius: 4px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>New Booking - $clientName</h1>
        </div>
        
        <div class="content">
            <p>A new booking has been received. Please review and confirm with the client.</p>
            
            <div class="details">
                <div class="detail"><strong>Client:</strong> $clientName</div>
                <div class="detail"><strong>Email:</strong> $clientEmail</div>
                <div class="detail"><strong>Phone:</strong> $clientPhone</div>
                <div class="detail"><strong>Date:</strong> $appointmentDate</div>
                <div class="detail"><strong>Time:</strong> $appointmentTime</div>
                <div class="detail"><strong>Service:</strong> $service</div>
                <div class="detail"><strong>Deposit:</strong> R$amount</div>
                <div class="detail"><strong>Status:</strong> $status</div>
                <div class="detail"><strong>Payment ID:</strong> $paymentId</div>
            </div>
            
            <a href="$bookingUrl" class="button">View in CRM</a>
        </div>
    </div>
</body>
</html>
HTML;

    $plainBody = "NEW BOOKING\n\n";
    $plainBody .= "Client: $clientName\n";
    $plainBody .= "Email: $clientEmail\n";
    $plainBody .= "Phone: $clientPhone\n";
    $plainBody .= "Date: $appointmentDate\n";
    $plainBody .= "Time: $appointmentTime\n";
    $plainBody .= "Service: $service\n";
    $plainBody .= "Deposit: R$amount\n";
    $plainBody .= "Status: $status\n";
    $plainBody .= "Payment ID: $paymentId\n\n";
    $plainBody .= "View in CRM: $bookingUrl";
    
    return sendEmail(EMAIL_ADMIN_ADDRESS, $subject, $htmlBody, $plainBody);
}

/**
 * Send reschedule notification to client
 */
function sendRescheduleEmail(array $booking, string $oldDate, string $oldTime): bool
{
    $clientName = htmlspecialchars($booking['name'], ENT_QUOTES, 'UTF-8');
    $clientEmail = htmlspecialchars($booking['email'], ENT_QUOTES, 'UTF-8');
    $oldDateFormatted = date('l, F d, Y', strtotime($oldDate));
    $oldTimeFormatted = date('g:i A', strtotime($oldTime));
    $newDate = date('l, F d, Y', strtotime($booking['appointment_date']));
    $newTime = date('g:i A', strtotime($booking['appointment_time']));
    
    $subject = 'Appointment Rescheduled - DIOS Hair & Makeup';
    
    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: #1a1a1a; color: white; padding: 20px; text-align: center; }
        .header h1 { margin: 0; color: #c9a961; }
        .content { padding: 20px; background: #f9f9f9; }
        .booking-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #c9a961; }
        .detail-row { margin-bottom: 10px; }
        .label { font-weight: bold; color: #666; }
        .footer { padding: 15px; text-align: center; color: #999; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>DIOS Hair & Makeup</h1>
            <p>Your appointment has been rescheduled</p>
        </div>
        
        <div class="content">
            <p>Hi $clientName,</p>
            
            <p>Your appointment has been rescheduled. Here are your updated details:</p>
            
            <div class="booking-details">
                <div class="detail-row">
                    <span class="label">Previous Appointment:</span><br>
                    $oldDateFormatted at $oldTimeFormatted
                </div>
                <div class="detail-row">
                    <span class="label">New Appointment:</span><br>
                    $newDate at $newTime
                </div>
            </div>
            
            <p>If you have any questions about the new appointment time, please let us know as soon as possible.</p>
            
            <p>We look forward to seeing you!</p>
            
            <p>Best regards,<br>The DIOS Team</p>
        </div>
        
        <div class="footer">
            <p>DIOS Hair & Makeup | Midrand & Copperleaf, Gauteng</p>
        </div>
    </div>
</body>
</html>
HTML;

    $plainBody = "DIOS Hair & Makeup - Appointment Rescheduled\n\n";
    $plainBody .= "Hi $clientName,\n\n";
    $plainBody .= "Your appointment has been rescheduled.\n\n";
    $plainBody .= "Previous: $oldDateFormatted at $oldTimeFormatted\n";
    $plainBody .= "New: $newDate at $newTime\n\n";
    $plainBody .= "Please confirm if this time works for you.\n";
    $plainBody .= "The DIOS Team";
    
    return sendEmail($clientEmail, $subject, $htmlBody, $plainBody);
}
