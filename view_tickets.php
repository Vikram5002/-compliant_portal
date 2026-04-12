<?php
include 'db_connect.php';
include 'send_email.php'; 

// --- Security and Session Management ---
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$sap_id = $_SESSION['sap_id'] ?? 'Unknown';

// Generate a CSRF token if one doesn't exist to prevent cross-site attacks
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get notification count for sidebar badge
$notifq = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$notifq->bind_param("i", $user_id);
$notifq->execute();
$notifq->bind_result($unread_count);
$notifq->fetch();
$notifq->close();

// --- Handle Form Submissions ---

// A. Handle Ticket Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ticket_id'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header("Location: view_tickets.php?message=Invalid CSRF token");
        exit;
    }
    $tid = intval($_POST['delete_ticket_id']);
    $del = $conn->prepare("DELETE FROM tickets WHERE ticket_id = ? AND user_id = ?");
    $del->bind_param("ii", $tid, $user_id);
    if ($del->execute() && $del->affected_rows > 0) {
        header("Location: view_tickets.php?message=Ticket deleted successfully");
    } else {
        header("Location: view_tickets.php?message=Error deleting ticket or not authorized");
    }
    $del->close();
    exit;
}

// B. Handle 'Satisfied' Rating Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rating_ticket_id'], $_POST['rating'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header("Location: view_tickets.php?message=Invalid CSRF token");
        exit;
    }
    $tid = intval($_POST['rating_ticket_id']);
    $rating = intval($_POST['rating']);

    // 1. Update the ticket status to 'Closed'
    $update_stmt = $conn->prepare("UPDATE tickets SET status = 'Closed' WHERE ticket_id = ? AND user_id = ?");
    $update_stmt->bind_param("ii", $tid, $user_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    // Record the status change in StatusHistory
    $closed_status = 'Closed';
    $history_stmt = $conn->prepare("INSERT INTO statushistory (ticket_id, status, timestamp) VALUES (?, ?, NOW())");
    $history_stmt->bind_param("is", $tid, $closed_status);
    $history_stmt->execute();
    $history_stmt->close();
    
    // 2. Insert the rating (and optional feedback) into the Feedback table
    $rating_feedback = isset($_POST['rating_feedback_text']) ? trim($_POST['rating_feedback_text']) : '';
    $feedback_stmt = $conn->prepare("INSERT INTO feedback (ticket_id, user_id, rating, feedback_text, created_at) VALUES (?, ?, ?, ?, NOW())");
    $feedback_stmt->bind_param("iiis", $tid, $user_id, $rating, $rating_feedback);
    $feedback_stmt->execute();
    $feedback_stmt->close();

    header("Location: view_tickets.php?message=Thank you for your rating!");
    exit;
}

// C. Handle 'Unsatisfied' Feedback Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unsatisfied_ticket_id'], $_POST['feedback_text'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header("Location: view_tickets.php?message=Invalid CSRF token");
        exit;
    }
    $tid = intval($_POST['unsatisfied_ticket_id']);
    $feedback = trim($_POST['feedback_text']);

    if ($feedback !== '') {
        // Insert the feedback text
        $stmt = $conn->prepare("INSERT INTO feedback (ticket_id, user_id, feedback_text, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $tid, $user_id, $feedback);
        if ($stmt->execute()) {
            // Re-open the ticket by setting its status back to 'In Progress'
            $update_status_stmt = $conn->prepare("UPDATE tickets SET status = 'In Progress' WHERE ticket_id = ?");
            $update_status_stmt->bind_param("i", $tid);
            $update_status_stmt->execute();
            $update_status_stmt->close();
            
            // Record the status change in StatusHistory
            $inprogress_status = 'In Progress';
            $history_stmt = $conn->prepare("INSERT INTO statushistory (ticket_id, status, timestamp) VALUES (?, ?, NOW())");
            $history_stmt->bind_param("is", $tid, $inprogress_status);
            $history_stmt->execute();
            $history_stmt->close();
            
            // Get ticket info for sending notifications/emails
            $ticket_info_q = $conn->prepare(
                "SELECT t.title, t.assigned_to, u.sap_id, u_assigned.email AS assigned_email 
                 FROM tickets t 
                 JOIN users u ON t.user_id = u.user_id 
                 LEFT JOIN users u_assigned ON t.assigned_to = u_assigned.user_id 
                 WHERE t.ticket_id = ?"
            );
            $ticket_info_q->bind_param("i", $tid);
            $ticket_info_q->execute();
            $ticket_info = $ticket_info_q->get_result()->fetch_assoc();
            $assigned_user_id = $ticket_info['assigned_to'];
            $assigned_user_email = $ticket_info['assigned_email'];
            $ticket_title = $ticket_info['title'];
            $creator_sap_id = $ticket_info['sap_id'];
            $ticket_info_q->close();
            
            // Prepare the message content
            $notification_message = "Unsatisfied feedback on Ticket #$tid from user $creator_sap_id: \"" . htmlspecialchars($feedback) . "\"";
            $email_subject = "Action Required: Unsatisfied Feedback on Ticket #{$tid}";
            $email_body = "
                <h2>Ticket Re-Opened Due to Unsatisfied Feedback</h2>
                <p>A user has submitted feedback and their ticket has been automatically re-opened.</p>
                <p><strong>Ticket ID:</strong> #{$tid}</p>
                <p><strong>Title:</strong> " . htmlspecialchars($ticket_title) . "</p>
                <p><strong>User's Feedback:</strong></p>
                <blockquote style='border-left: 4px solid #ccc; padding-left: 15px; margin-left: 5px;'>" . nl2br(htmlspecialchars($feedback)) . "</blockquote>
                <p>Please review the feedback and take the necessary action.</p>
            ";

            // Notify and email the assigned staff member (if any)
            if ($assigned_user_id && $assigned_user_email) {
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, ticket_id, message) VALUES (?, ?, ?)");
                $notif_stmt->bind_param("iis", $assigned_user_id, $tid, $notification_message);
                $notif_stmt->execute();
                $notif_stmt->close();
                sendTicketEmail($assigned_user_email, $email_subject, $email_body);
            }

            // Notify and email all admins
            $admin_query = $conn->query("SELECT user_id, email FROM users WHERE role = 'admin'");
            while ($admin = $admin_query->fetch_assoc()) {
                $notif_stmt_admin = $conn->prepare("INSERT INTO notifications (user_id, ticket_id, message) VALUES (?, ?, ?)");
                $notif_stmt_admin->bind_param("iis", $admin['user_id'], $tid, $notification_message);
                $notif_stmt_admin->execute();
                $notif_stmt_admin->close();
                sendTicketEmail($admin['email'], $email_subject, $email_body);
            }
            
            header("Location: view_tickets.php?message=Thank you for your feedback! The ticket has been reopened.");
        } else {
            header("Location: view_tickets.php?message=Error submitting feedback: " . htmlspecialchars($stmt->error));
        }
        $stmt->close();
        exit;
    } else {
        header("Location: view_tickets.php?message=Feedback cannot be empty");
        exit;
    }
}


// --- Fetch Data for Display ---

// Auto-close tickets older than 50 hours (if not already resolved/closed)
$hours_threshold = 50;
$auto_close_sql = "UPDATE tickets SET status = 'Closed' WHERE status NOT IN ('Resolved', 'Closed', 'Rejected') AND TIMESTAMPDIFF(HOUR, created_at, NOW()) > ?";
$auto_close_stmt = $conn->prepare($auto_close_sql);
$auto_close_stmt->bind_param("i", $hours_threshold);
$auto_close_stmt->execute();
$auto_close_stmt->close();

$sql = "SELECT t.ticket_id, t.title, t.category, t.status FROM tickets t WHERE t.user_id = ? ORDER BY t.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Tickets - NMIMS Issue Tracker</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body { 
        font-family: 'Segoe UI', 'Trebuchet MS', sans-serif; 
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); 
        display: flex; 
        min-height: 100vh; 
        margin: 0;
    }
    
    /* ===== SIDEBAR STYLING ===== */
    .sidebar { 
        width: 260px; 
        background: linear-gradient(180deg, #c41e3a 0%, #8B0000 100%);
        min-height: 100vh; 
        color: white; 
        position: fixed; 
        box-sizing: border-box; 
        display: flex; 
        flex-direction: column;
        box-shadow: 4px 0 20px rgba(196, 30, 58, 0.3);
        z-index: 100;
    }
    
    .logo-container { 
        padding: 25px 20px; 
        text-align: center; 
        border-bottom: 2px solid rgba(255, 255, 255, 0.2);
        animation: slideDown 0.6s ease-out;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    
    .logo-container img { 
        width: 120px; 
        filter: brightness(1.1) drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        transition: transform 0.3s ease;
        display: block;
    }
    
    .logo-container img:hover { transform: scale(1.05); }
    
    .logo-container h3 { 
        font-weight: 600; 
        font-size: 1em; 
        margin-top: 10px; 
        color: #ffffff;
        letter-spacing: 0.5px;
    }
    
    .user-profile { 
        padding: 25px 20px; 
        text-align: center; 
        border-bottom: 2px solid rgba(255, 255, 255, 0.2);
    }
    
    .user-profile .profile-pic { 
        font-size: 50px; 
        color: #ffffff; 
        margin-bottom: 15px;
        animation: pulse 2s infinite;
    }
    
    .user-profile h4 { 
        margin: 10px 0 5px 0; 
        text-transform: capitalize;
        font-weight: 600;
        color: #ffffff;
    }
    
    .user-profile p { 
        margin: 0; 
        color: rgba(255, 255, 255, 0.8); 
        font-size: 0.85em;
    }
    
    .nav-menu { 
        list-style: none; 
        margin: 0; 
        padding: 15px 0;
        flex: 1;
    }
    
    .nav-item { 
        padding: 15px 25px; 
        display: flex; 
        align-items: center; 
        gap: 15px; 
        color: rgba(255, 255, 255, 0.85); 
        text-decoration: none; 
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        border-left: 4px solid transparent;
    }
    
    .nav-item i { 
        width: 20px; 
        text-align: center;
        font-size: 1.1em;
    }
    
    .nav-item:hover { 
        background: rgba(255, 255, 255, 0.1);
        padding-left: 30px;
        color: #ffffff;
    }
    
    .nav-item:focus {
        outline: 2px solid #ffffff;
        outline-offset: 2px;
    }
    
    .nav-item.active { 
        background: rgba(255, 255, 255, 0.15);
        color: #ffffff;
        border-left: 4px solid #ffffff;
        padding-left: 21px;
        font-weight: 600;
    }
    
    .nav-item .notif-badge { 
        background: #ff6b6b;
        color: #fff; 
        border-radius: 12px; 
        padding: 4px 10px; 
        font-size: 0.75em;
        margin-left: auto;
        font-weight: 700;
        animation: bounce 0.6s infinite;
    }

    /* ===== MAIN CONTENT STYLING ===== */
    .main-content { 
        flex: 1; 
        margin-left: 260px; 
        padding: 40px 50px;
        animation: fadeIn 0.6s ease-out;
    }
    
    .page-header { 
        margin-bottom: 40px;
        animation: slideDown 0.6s ease-out 0.1s both;
    }
    
    .page-header h1 { 
        color: #c41e3a;
        margin: 0; 
        font-size: 2.2em;
        font-weight: 700;
        letter-spacing: -0.5px;
    }

    /* ===== TICKETS CONTAINER & TABLE ===== */
    .tickets-container { 
        background: white; 
        border-radius: 12px; 
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        animation: fadeInUp 0.7s ease-out 0.2s both;
    }
    
    .ticket-table { 
        width: 100%; 
        border-collapse: collapse;
    }
    
    .ticket-table th, .ticket-table td { 
        padding: 20px 25px; 
        border-bottom: 1px solid #e0e0e0; 
        text-align: left; 
        vertical-align: middle;
    }
    
    .ticket-table th { 
        background: linear-gradient(135deg, #c41e3a 0%, #8B0000 100%);
        font-weight: 700; 
        color: #ffffff; 
        text-transform: uppercase; 
        font-size: 0.9em; 
        letter-spacing: 1px;
    }
    
    .ticket-table tr:last-child td { 
        border-bottom: none;
    }
    
    .ticket-table tbody tr { 
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .ticket-table tbody tr:hover { 
        background: linear-gradient(135deg, #fff5f7 0%, #ffffff 100%);
        box-shadow: inset 0 4px 12px rgba(196, 30, 58, 0.05);
    }
    
    /* ===== STATUS CHIPS ===== */
    .status-chip { 
        padding: 8px 16px; 
        border-radius: 25px; 
        font-weight: 700; 
        font-size: 0.85em; 
        display: inline-block;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }
    
    .status-chip:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
    }
    
    .status-resolved { 
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
    }
    
    .status-in-progress { 
        background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
        color: #856404;
    }
    
    .status-received { 
        background: linear-gradient(135deg, #e2e3e5 0%, #d3d4d6 100%);
        color: #383d41;
    }
    
    .status-solution-proposed { 
        background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
        color: #0c5460;
    }
    
    .status-closed { 
        background: linear-gradient(135deg, #c41e3a 0%, #8B0000 100%);
        color: #ffffff;
    }
    
    /* ===== ACTION BUTTONS ===== */
    .action-links { 
        display: flex; 
        gap: 8px; 
        flex-wrap: wrap;
    }
    
    .action-links .btn { 
        display: inline-flex; 
        align-items: center; 
        gap: 6px; 
        text-decoration: none; 
        border: none; 
        padding: 10px 16px; 
        border-radius: 6px; 
        font-weight: 600; 
        cursor: pointer; 
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
        font-size: 0.9em;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .action-links .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    }
    
    .action-links .btn:focus {
        outline: 2px solid #c41e3a;
        outline-offset: 2px;
    }
    
    .btn-view { 
        background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
        color: white;
    }
    
    .btn-view:hover { 
        background: linear-gradient(135deg, #5a6268 0%, #495057 100%);
    }
    
    .btn-delete { 
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        color: white;
    }
    
    .btn-delete:hover { 
        background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
    }
    
    .btn-satisfied { 
        background: linear-gradient(135deg, #28a745 0%, #218838 100%);
        color: white;
    }
    
    .btn-satisfied:hover { 
        background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
    }
    
    .btn-unsatisfied { 
        background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
        color: #212529;
    }
    
    .btn-unsatisfied:hover { 
        background: linear-gradient(135deg, #e0a800 0%, #d39e00 100%);
    }
    
    /* ===== FEEDBACK & RATING SECTIONS ===== */
    .feedback-row, .star-rating-row { 
        padding: 30px; 
        background: linear-gradient(135deg, #fff5f7 0%, #ffffff 100%);
        border-top: 2px solid #c41e3a; 
        animation: slideDown 0.5s ease-out;
        display: none;
    }
    
    .star-rating-row h4 {
        color: #c41e3a;
        font-weight: 700;
        margin-bottom: 20px;
    }
    
    .feedback-box textarea { 
        width: 100%; 
        min-height: 100px; 
        border: 2px solid #e0e0e0;
        border-radius: 8px; 
        padding: 15px; 
        font-family: 'Segoe UI', sans-serif;
        font-size: 1em;
        transition: all 0.3s ease;
    }
    
    .feedback-box textarea:focus {
        border-color: #c41e3a;
        box-shadow: 0 0 0 4px rgba(196, 30, 58, 0.1);
        outline: none;
    }
    
    .feedback-box button, .star-rating-submit { 
        background: linear-gradient(135deg, #c41e3a 0%, #8B0000 100%);
        color: #fff; 
        border: none; 
        border-radius: 6px; 
        padding: 12px 28px; 
        font-size: 1em; 
        font-weight: 600; 
        cursor: pointer;
        margin-top: 15px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 12px rgba(196, 30, 58, 0.2);
    }
    
    .feedback-box button:hover, .star-rating-submit:hover { 
        background: linear-gradient(135deg, #8B0000 0%, #6B0000 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(196, 30, 58, 0.3);
    }
    
    .feedback-box button:focus, .star-rating-submit:focus {
        outline: 2px solid #c41e3a;
        outline-offset: 2px;
    }
    
    /* ===== STAR RATING ===== */
    .star-rating { 
        border: none; 
        display: inline-block;
        direction: rtl;
        font-size: 2.2em;
    }
    
    .star-rating > input { 
        display: none;
    }
    
    .star-rating > label { 
        color: #ddd; 
        float: right; 
        padding: 0 5px;
        cursor: pointer; 
        transition: color 0.2s cubic-bezier(0.4, 0, 0.2, 1), text-shadow 0.2s ease;
    }
    
    .star-rating > label::before { 
        content: "★";
    }
    
    .star-rating > input:checked ~ label { 
        color: #fbc02d;
        text-shadow: 0 0 10px rgba(251, 192, 45, 0.5);
    }
    
    .star-rating:not(:checked) > label:hover,
    .star-rating:not(:checked) > label:hover ~ label { 
        color: #fbc02d;
    }

    /* ===== MESSAGES ===== */
    .success-message, .error-message { 
        padding: 18px 25px; 
        border-radius: 8px; 
        margin-bottom: 25px; 
        font-weight: 600;
        animation: slideDown 0.5s ease-out;
        border-left: 4px solid;
    }
    
    .success-message { 
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724; 
        border-left-color: #28a745;
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.15);
    }
    
    .error-message { 
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
        border-left-color: #dc3545;
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.15);
    }

    /* ===== ANIMATIONS ===== */
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-3px); }
    }

    /* ===== RESPONSIVE DESIGN ===== */
    @media (max-width: 1024px) {
        .sidebar { width: 240px; }
        .main-content { margin-left: 240px; padding: 30px 30px; }
        .tickets-container { overflow-x: auto; }
        .ticket-table th, .ticket-table td { padding: 15px 20px; }
    }

    @media (max-width: 768px) {
        .sidebar { 
            width: 100%; 
            height: auto; 
            position: relative; 
            min-height: auto;
            box-shadow: none;
        }
        .main-content { 
            margin-left: 0; 
            padding: 20px 10px;
        }
        .page-header h1 { font-size: 1.8em; }
        
        /* Table responsive */
        .tickets-container { 
            overflow-x: auto; 
            -webkit-overflow-scrolling: touch;
        }
        .ticket-table { 
            min-width: 600px; 
            font-size: 0.9em;
        }
        .ticket-table th, .ticket-table td { 
            padding: 12px 15px; 
            white-space: nowrap;
        }
        
        /* Action buttons stack vertically on small screens */
        .action-links { 
            flex-direction: column; 
            gap: 4px;
        }
        .action-links .btn { 
            width: 100%; 
            justify-content: center;
            padding: 8px 12px;
            font-size: 0.85em;
        }
        
        /* Star rating responsive */
        .star-rating { font-size: 1.8em; }
        
        /* Modal/popups responsive */
        .feedback-box textarea { 
            font-size: 16px; /* Prevents zoom on iOS */
        }
        
        /* Navigation responsive */
        .nav-menu { 
            display: flex; 
            flex-wrap: wrap; 
            padding: 10px;
            gap: 5px;
        }
        .nav-item { 
            padding: 10px 15px; 
            flex: 1 1 auto; 
            min-width: 120px;
            justify-content: center;
        }
        .nav-item span { display: none; }
        .nav-item i { font-size: 1.2em; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 15px 5px; }
        .page-header { margin-bottom: 20px; }
        .page-header h1 { font-size: 1.5em; }
        .ticket-table { font-size: 0.8em; }
        .ticket-table th, .ticket-table td { padding: 8px 10px; }
        .nav-item { 
            min-width: 70px; 
            padding: 8px 10px;
        }
        .nav-item i { font-size: 1.5em; }
        .logo-container img { width: 80px; }
        .logo-container h3 { font-size: 0.9em; }
        .user-profile { padding: 15px 10px; }
        .user-profile .profile-pic { font-size: 35px; }
        .user-profile h4 { font-size: 1em; }
        
        /* Hide category column on very small screens */
        .ticket-table th:nth-child(2), 
        .ticket-table td:nth-child(2) { display: none; }
        
        /* Adjust status badges */
        .status-badge { 
            font-size: 0.75em; 
            padding: 4px 8px;
        }
    }
</style>
<script>
    function showFeedbackBox(ticketId) {
        var fb = document.getElementById('feedback_'+ticketId);
        var rating = document.getElementById('rating_'+ticketId);
        if (fb) fb.style.display='block';
        if (rating) rating.style.display='none';
    }
    function showRatingBox(ticketId) {
        var ratingEl = document.getElementById('rating_'+ticketId);
        var feedbackEl = document.getElementById('feedback_'+ticketId);
        var ratingFeedback = document.getElementById('rating_feedback_'+ticketId);
        if (ratingEl) {
            ratingEl.style.display='block';
            if (ratingFeedback) ratingFeedback.style.display='none';
            // attach change listeners to stars inside this ratingEl
            var stars = ratingEl.querySelectorAll('input[name="rating"]');
            stars.forEach(function(star) {
                // avoid adding duplicate listeners
                if (!star._listenerAdded) {
                    star.addEventListener('change', function() {
                        if (ratingFeedback) {
                            ratingFeedback.style.display = '';
                            var ta = ratingFeedback.querySelector('textarea');
                            if (ta) ta.focus();
                        }
                    });
                    star._listenerAdded = true;
                }
            });
        }
        if (feedbackEl) feedbackEl.style.display='none';
    }
    function confirmDelete(tId) {
        return confirm('Are you sure you want to permanently delete ticket #' + tId + '? This action cannot be undone.');
    }
</script>
</head>
<body>
<div class="sidebar">
    <div class="logo-container"><img src="<?php echo file_exists('NMIMS Logo.jpg') ? 'NMIMS Logo.jpg' : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIwIiBoZWlnaHQ9IjEyMCIgdmlld0JveD0iMCAwIDEyMCAxMjAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMjAiIGhlaWdodD0iMTIwIiBmaWxsPSIjQzQxRTNBIi8+Cjx0ZXh0IHg9IjYwIiB5PSI2NSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0id2hpdGUiIGZvbnQtc2l6ZT0iMTgiPk5NSlNUPC90ZXh0Pgo8L3N2Zz4='; ?>" alt="NMIMS Logo"><h3>NMIMS Issue Tracker</h3></div>
    <div class="user-profile"><div class="profile-pic"><i class="fas fa-user-circle"></i></div><h4><?= htmlspecialchars($role) ?></h4><p><?= htmlspecialchars($sap_id) ?></p></div>
    <nav class="nav-menu" role="navigation" aria-label="Main navigation">
        <a href="index.php" class="nav-item" aria-label="Go to Dashboard"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
        <a href="view_tickets.php" class="nav-item active" aria-label="View My Own Tickets" aria-current="page"><i class="fas fa-ticket-alt"></i><span>My Own Tickets</span></a>
        <?php if (strpos($role, 'sub_') === 0): ?>
        <a href="sub_staff_dashboard.php" class="nav-item" aria-label="View My Tasks"><i class="fas fa-tasks"></i><span>My Tasks</span></a>
        <?php elseif (in_array($role,['staff','maintenance','warden','security','house_keeping'])): ?><a href="staff_tickets_with_sub.php" class="nav-item" aria-label="Manage Assigned Tickets"><i class="fas fa-tasks"></i><span>Assigned Tickets</span></a><?php endif; ?>
        <?php if (in_array($role,['rector','network/it_team'])): ?><a href="staff_tickets.php" class="nav-item" aria-label="View Assigned Tickets"><i class="fas fa-tasks"></i><span>Assigned Tickets</span></a><?php endif; ?>
        <?php if (in_array($role,['admin','super_visor'])): ?><a href="admin_dashboard.php" class="nav-item" aria-label="Access Admin Dashboard"><i class="fas fa-user-shield"></i><span>Admin Dashboard</span></a><?php endif;?>
        <?php if (in_array($role,['admin','network/it_team'])): ?><a href="bulk_import.php" class="nav-item" aria-label="Bulk Import Users"><i class="fas fa-user-plus"></i><span>Add Users</span></a><?php endif;?>
        <a href="notifications.php" class="nav-item" aria-label="View Notifications<?php if($unread_count>0): ?> (<?= $unread_count ?> unread)<?php endif; ?>"><i class="fas fa-bell"></i><span>Notifications</span><?php if($unread_count>0): ?><span class="notif-badge" aria-label="<?= $unread_count ?> unread notifications"><?= $unread_count ?></span><?php endif;?></a>
        <a href="logout.php" class="nav-item" aria-label="Logout from system"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
    </nav>
</div>
<div class="main-content">
    <div class="page-header"><h1>My Tickets</h1></div>
    <?php if (isset($_GET['message'])): ?>
        <div class="<?= strpos($_GET['message'], 'Error') !== false || strpos($_GET['message'], 'Invalid') !== false ? 'error-message' : 'success-message' ?>">
            <?= htmlspecialchars($_GET['message']); ?>
        </div>
    <?php endif; ?>
    <div class="tickets-container">
        <table class="ticket-table">
            <thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['title']) ?></strong></td>
                <td><?= htmlspecialchars($row['category']) ?></td>
                <td><span class="status-chip status-<?= strtolower(str_replace(' ','-',$row['status'])) ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                <td class="action-links">
                    <a href="ticket_details.php?ticket_id=<?= $row['ticket_id'] ?>" class="btn btn-view"><i class="fas fa-eye"></i> View</a>
                    <form method="POST" onsubmit="return confirmDelete(<?= $row['ticket_id'] ?>);" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="delete_ticket_id" value="<?= $row['ticket_id'] ?>">
                        <button type="submit" class="btn btn-delete"><i class="fas fa-trash-alt"></i> Delete</button>
                    </form>
                    <?php if (strtolower($row['status']) === 'resolved'): ?>
                        <button type="button" class="btn btn-satisfied" onclick="showRatingBox(<?= $row['ticket_id'] ?>)"><i class="fas fa-smile"></i> Satisfied</button>
                        <button type="button" class="btn btn-unsatisfied" onclick="showFeedbackBox(<?= $row['ticket_id'] ?>)"><i class="fas fa-frown"></i> Re-open Ticket</button>
                    <?php endif; ?>
                </td>
            </tr>
            <tr class="feedback-container-row">
                <td colspan="4">
                    <div class="star-rating-row" id="rating_<?= $row['ticket_id'] ?>">
                        <h4 style="margin-top:0; margin-bottom:15px;">Please rate the service:</h4>
                        <form method="POST" action="view_tickets.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="rating_ticket_id" value="<?= $row['ticket_id'] ?>">
                            <fieldset class="star-rating">
                                <input type="radio" id="star5_<?= $row['ticket_id'] ?>" name="rating" value="5" required/><label for="star5_<?= $row['ticket_id'] ?>"></label>
                                <input type="radio" id="star4_<?= $row['ticket_id'] ?>" name="rating" value="4" /><label for="star4_<?= $row['ticket_id'] ?>"></label>
                                <input type="radio" id="star3_<?= $row['ticket_id'] ?>" name="rating" value="3" /><label for="star3_<?= $row['ticket_id'] ?>"></label>
                                <input type="radio" id="star2_<?= $row['ticket_id'] ?>" name="rating" value="2" /><label for="star2_<?= $row['ticket_id'] ?>"></label>
                                <input type="radio" id="star1_<?= $row['ticket_id'] ?>" name="rating" value="1" /><label for="star1_<?= $row['ticket_id'] ?>"></label>
                            </fieldset>
                            <div id="rating_feedback_<?= $row['ticket_id'] ?>" class="rating-feedback" style="display:none; margin-top:15px;">
                                <label for="rating_feedback_text_<?= $row['ticket_id'] ?>" style="font-weight:600; color:#6c757d; display:block; margin-bottom:8px;">Additional feedback (optional):</label>
                                <textarea id="rating_feedback_text_<?= $row['ticket_id'] ?>" name="rating_feedback_text" placeholder="Share any comments..." style="width:100%; min-height:80px; border:2px solid #e0e0e0; border-radius:8px; padding:12px; resize:vertical;"></textarea>
                            </div>
                            <br><button type="submit" class="star-rating-submit">Submit Rating</button>
                        </form>
                    </div>
                    <div class="feedback-row" id="feedback_<?= $row['ticket_id'] ?>">
                        <form method="POST" class="feedback-box" action="view_tickets.php" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="unsatisfied_ticket_id" value="<?= $row['ticket_id'] ?>">
                            <textarea name="feedback_text" placeholder="Please tell us what was unsatisfactory so we can fix it..." required></textarea>
                            <br><button type="submit">Re-open Ticket</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php else: ?>
            <tr><td colspan="4" style="text-align: center; padding: 50px;">You have not created any tickets yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>