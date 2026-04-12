<?php
// File: send_email.php

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load the PHPMailer files
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

/**
 * A centralized function to send emails for different ticket events.
 *
 * @param string $toEmail The recipient's email address.
 * @param string $subject The subject of the email.
 * @param string $body The HTML content of the email.
 * @return bool True on success, false on failure.
 */
function sendTicketEmail($toEmail, $subject, $body) {
    $mail = new PHPMailer(true);

    try {
        // --- Server settings for your Organization's Email ---
        $mail->isSMTP();
        
        // ** IMPORTANT: You must get these details from your IT department **
        // Common settings for Microsoft/Outlook: smtp.office365.com, Port 587, SMTPSecure 'tls'
        // Common settings for Google Workspace: smtp.gmail.com, Port 587, SMTPSecure 'tls'

        $mail->Host       = 'smtp.office365.com'; // <-- REPLACE with your organization's SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'sai.rishitha15@nmims.in';    // <-- REPLACE with the full email address you are sending from
        $mail->Password   = 'Tunki@08';    // <-- REPLACE with the actual password for that email account
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Usually 'tls' for Port 587
        $mail->Port       = 587;                        // Usually 587 for TLS, or 465 for SSL

        // --- Recipients ---
        $mail->setFrom('sai.rishitha15@nmims.in', 'NMIMS Issue Tracker'); // Should be the same as your Username
        $mail->addAddress($toEmail);

        // --- Content ---
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        // For debugging, you can log the error. Do not show it to the end-user.
        // error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

?>
