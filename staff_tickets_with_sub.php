<?php
include 'db_connect.php';
include 'send_email.php';
include 'nav_helper.php';

// Staff member roles that can have sub-staff
$can_have_sub_staff_roles = ['staff', 'maintenance', 'warden', 'security', 'house_keeping'];

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'maintenance', 'warden', 'rector', 'security', 'house_keeping', 'network/it_team'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$sap_id = $_SESSION['sap_id'] ?? 'Unknown';
$can_have_subs = in_array($role, $can_have_sub_staff_roles);

$message = "";
$message_type = "";

// Handle reassignment to sub-staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reassign_to_sub'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $message = "Invalid CSRF token.";
        $message_type = "error";
    } else {
        $ticket_id = intval($_POST['ticket_id']);
        $sub_staff_id = intval($_POST['sub_staff_id']);

        // Verify ticket is assigned to this staff member
        $verify = $conn->prepare("SELECT ticket_id FROM tickets WHERE ticket_id = ? AND assigned_to = ?");
        $verify->bind_param("ii", $ticket_id, $user_id);
        $verify->execute();
        if ($verify->get_result()->num_rows > 0) {
            // Update ticket with sub-staff assignment
            $update = $conn->prepare("UPDATE tickets SET reassigned_to = ? WHERE ticket_id = ?");
            $update->bind_param("ii", $sub_staff_id, $ticket_id);
            
            if ($update->execute()) {
                // Send notification email to sub-staff
                $sub_query = $conn->prepare("SELECT email, sap_id FROM users WHERE user_id = ?");
                $sub_query->bind_param("i", $sub_staff_id);
                $sub_query->execute();
                $sub_user = $sub_query->get_result()->fetch_assoc();
                $sub_query->close();

                $email_subject = "New Task Assignment: Ticket #$ticket_id";
                $email_body = "
                    <h2>Task Assigned to You</h2>
                    <p>Your supervisor " . htmlspecialchars($sap_id) . " has assigned you a new task.</p>
                    <p><strong>Ticket #:</strong> $ticket_id</p>
                    <p>Please log in to view and work on this task.</p>
                ";
                sendTicketEmail($sub_user['email'], $email_subject, $email_body);

                $message = "Ticket reassigned to sub-staff successfully.";
                $message_type = "success";
            } else {
                $message = "Error reassigning ticket.";
                $message_type = "error";
            }
            $update->close();
        }
        $verify->close();
    }
}

// Handle approval of sub-staff work
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $message = "Invalid CSRF token.";
        $message_type = "error";
    } else {
        $approval_id = intval($_POST['approval_id']);
        $action = sanitize_input($_POST['action']); // 'approve' or 'reject'
        $parent_notes = sanitize_input($_POST['parent_notes'] ?? '');

        // Get approval details
        $approval_query = $conn->prepare("SELECT * FROM substaffapprovals WHERE approval_id = ? AND parent_staff_id = ?");
        $approval_query->bind_param("ii", $approval_id, $user_id);
        $approval_query->execute();
        $approval_data = $approval_query->get_result()->fetch_assoc();
        $approval_query->close();

        if ($approval_data) {
            $ticket_id = $approval_data['ticket_id'];
            $sub_staff_id = $approval_data['sub_staff_id'];

            if ($action === 'approve') {
                // Mark approval as approved
                $update_approval = $conn->prepare("UPDATE substaffapprovals SET status = 'approved', approved_at = NOW() WHERE approval_id = ?");
                $update_approval->bind_param("i", $approval_id);
                $update_approval->execute();
                $update_approval->close();

                // Update ticket status to 'Resolved' when approved
                $resolved_status = 'Resolved';
                $update_ticket = $conn->prepare("UPDATE tickets SET status = 'Resolved', sub_staff_status = 'approved_by_parent' WHERE ticket_id = ?");
                $update_ticket->bind_param("i", $ticket_id);
                $update_ticket->execute();
                $update_ticket->close();

                // Record the status change in StatusHistory
                $history_stmt = $conn->prepare("INSERT INTO statushistory (ticket_id, status, timestamp) VALUES (?, ?, NOW())");
                $history_stmt->bind_param("is", $ticket_id, $resolved_status);
                $history_stmt->execute();
                $history_stmt->close();

                $message = "Ticket #$ticket_id approved and marked as Resolved.";
                $message_type = "success";

                // Send approval notification email
                $sub_query = $conn->prepare("SELECT email, sap_id FROM users WHERE user_id = ?");
                $sub_query->bind_param("i", $sub_staff_id);
                $sub_query->execute();
                $sub_user = $sub_query->get_result()->fetch_assoc();
                $sub_query->close();

                $email_subject = "Work Approved: Ticket #$ticket_id";
                $email_body = "
                    <h2>Your Work Has Been Approved</h2>
                    <p>Congratulations! Your work on ticket #$ticket_id has been approved by your supervisor.</p>
                    <p>The ticket is now being processed further.</p>
                ";
                sendTicketEmail($sub_user['email'], $email_subject, $email_body);

            } elseif ($action === 'reject') {
                // Mark approval as rejected
                $update_approval = $conn->prepare("UPDATE substaffapprovals SET status = 'rejected', parent_notes = ? WHERE approval_id = ?");
                $update_approval->bind_param("si", $parent_notes, $approval_id);
                $update_approval->execute();
                $update_approval->close();

                // Revert ticket to previous status for rework
                $update_ticket = $conn->prepare("UPDATE tickets SET status = 'In Progress', sub_staff_status = NULL WHERE ticket_id = ?");
                $update_ticket->bind_param("i", $ticket_id);
                $update_ticket->execute();
                $update_ticket->close();

                // Record the status change in StatusHistory
                $rework_status = 'In Progress';
                $history_stmt = $conn->prepare("INSERT INTO statushistory (ticket_id, status, timestamp) VALUES (?, ?, NOW())");
                $history_stmt->bind_param("is", $ticket_id, $rework_status);
                $history_stmt->execute();
                $history_stmt->close();

                $message = "Ticket #$ticket_id rejected for revision.";
                $message_type = "success";

                // Send rejection notification email
                $sub_query = $conn->prepare("SELECT email, sap_id FROM users WHERE user_id = ?");
                $sub_query->bind_param("i", $sub_staff_id);
                $sub_query->execute();
                $sub_user = $sub_query->get_result()->fetch_assoc();
                $sub_query->close();

                $email_subject = "Revision Needed: Ticket #$ticket_id";
                $email_body = "
                    <h2>Work Needs Revision</h2>
                    <p>Your work on ticket #$ticket_id needs some revisions.</p>
                    <p><strong>Supervisor Notes:</strong></p>
                    <blockquote>" . nl2br(htmlspecialchars($parent_notes)) . "</blockquote>
                    <p>Please make the necessary changes and resubmit.</p>
                ";
                sendTicketEmail($sub_user['email'], $email_subject, $email_body);
            }
        }
    }
}

// Auto-close tickets older than 50 hours (if not already resolved/closed)
$hours_threshold = 50;
$auto_close_sql = "UPDATE tickets SET status = 'Closed' WHERE status NOT IN ('Resolved', 'Closed', 'Rejected') AND TIMESTAMPDIFF(HOUR, created_at, NOW()) > ?";
$auto_close_stmt = $conn->prepare($auto_close_sql);
$auto_close_stmt->bind_param("i", $hours_threshold);
$auto_close_stmt->execute();
$auto_close_stmt->close();

// Fetch direct assignments
$direct_sql = "SELECT t.ticket_id, t.title, t.category, t.status, t.created_at
        FROM tickets t
        WHERE t.assigned_to = ? AND t.reassigned_to IS NULL
        ORDER BY t.created_at DESC";
$direct_stmt = $conn->prepare($direct_sql);
$direct_stmt->bind_param("i", $user_id);
$direct_stmt->execute();
$direct_result = $direct_stmt->get_result();

// Fetch sub-staff assignments
$sub_assignments = null;
if ($can_have_subs) {
    $sub_sql = "SELECT t.ticket_id, t.title, t.status, u.sap_id as sub_staff_sap, t.sub_staff_status
            FROM tickets t
            JOIN users u ON t.reassigned_to = u.user_id
            WHERE t.assigned_to = ? AND t.reassigned_to IS NOT NULL
            ORDER BY t.created_at DESC";
    $sub_stmt = $conn->prepare($sub_sql);
    $sub_stmt->bind_param("i", $user_id);
    $sub_stmt->execute();
    $sub_assignments = $sub_stmt->get_result();
}

// Fetch pending approvals for this staff member
$approvals_sql = "SELECT sa.*, t.title, u.sap_id as sub_staff_sap
        FROM substaffapprovals sa
        JOIN tickets t ON sa.ticket_id = t.ticket_id
        JOIN users u ON sa.sub_staff_id = u.user_id
        WHERE sa.parent_staff_id = ? AND sa.status = 'pending'
        ORDER BY sa.submitted_at DESC";
$approvals_stmt = $conn->prepare($approvals_sql);
$approvals_stmt->bind_param("i", $user_id);
$approvals_stmt->execute();
$approvals_result = $approvals_stmt->get_result();

// Fetch available sub-staff for assignments
$sub_staff = [];
if ($can_have_subs) {
    $sub_role = 'sub_' . $role;
    $sub_query = $conn->prepare("SELECT user_id, sap_id FROM users WHERE role = ? AND parent_staff_id = ?");
    $sub_query->bind_param("si", $sub_role, $user_id);
    $sub_query->execute();
    $sub_staff = $sub_query->get_result()->fetch_all(MYSQLI_ASSOC);
    $sub_query->close();
}

// Get notification count
$notifq = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$notifq->bind_param("i", $user_id);
$notifq->execute();
$notifq->bind_result($unread_count);
$notifq->fetch();
$notifq->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Dashboard - NMIMS Issue Tracker</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Segoe UI', 'Trebuchet MS', sans-serif; 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); 
            display: flex; 
            min-height: 100vh;
        }
        
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
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .logo-container img { 
            width: 120px; 
            filter: brightness(1.1);
            transition: transform 0.3s ease;
            display: block;
        }
        
        .logo-container img:hover { transform: scale(1.05); }
        
        .logo-container h3 { 
            font-weight: 600; 
            font-size: 0.95em; 
            margin-top: 10px; 
            color: #ffffff;
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
        }
        
        .user-profile h4 { 
            margin: 10px 0 5px 0; 
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
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .nav-item:hover { 
            background: rgba(255, 255, 255, 0.1);
            padding-left: 30px;
        }
        
        .nav-item.active { 
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            border-left: 4px solid #ffc107;
            padding-left: 21px;
            font-weight: 600;
        }
        
        .main-content { 
            flex: 1; 
            margin-left: 260px; 
            padding: 40px 50px;
        }
        
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .page-header h1 { 
            color: #c41e3a;
            font-size: 2.2em;
            font-weight: 700;
            margin: 0;
            flex: 1;
        }

        .button-group {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .btn-view-closed {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            background: linear-gradient(135deg, #c41e3a 0%, #8B0000 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95em;
            transition: all 0.3s ease;
            border: 2px solid #c41e3a;
            box-shadow: 0 4px 15px rgba(196, 30, 58, 0.25);
            white-space: nowrap;
        }

        .btn-view-closed:hover {
            background: linear-gradient(135deg, #8B0000 0%, #c41e3a 100%);
            transform: translateY(-3px);
            box-shadow: 0 6px 25px rgba(196, 30, 58, 0.35);
            border-color: #8B0000;
        }

        .btn-view-closed:active {
            transform: translateY(-1px);
            box-shadow: 0 3px 12px rgba(196, 30, 58, 0.25);
        }

        .btn-view-closed::before {
            content: '';
            display: inline-block;
            width: 5px;
            height: 5px;
            background: white;
            border-radius: 50%;
            opacity: 0.8;
        }
        
        .message { 
            padding: 18px 25px; 
            border-radius: 8px; 
            margin-bottom: 25px; 
            border-left: 4px solid;
        }
        
        .success-message { 
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }
        
        .error-message { 
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }
        
        .section { 
            background: white; 
            border-radius: 12px; 
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .section-title { 
            background: linear-gradient(135deg, #c41e3a 0%, #8B0000 100%);
            color: white;
            padding: 20px 25px;
            font-weight: 700;
            font-size: 1.1em;
        }
        
        .section-content { 
            padding: 25px;
        }
        
        .ticket-table { 
            width: 100%; 
            border-collapse: collapse;
        }
        
        .ticket-table th { 
            background: #f8f9fa;
            font-weight: 700; 
            padding: 15px;
            text-align: left;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .ticket-table td { 
            padding: 15px; 
            border-bottom: 1px solid #f0f0f0;
        }
        
        .status-badge { 
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85em;
        }
        
        .status-pending-approval { 
            background: #fff3cd;
            color: #856404;
        }
        
        .approval-card { 
            background: #f8f9fa;
            border-left: 4px solid #ffc107;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .approval-actions { 
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .approval-actions button { 
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-approve { 
            background: #28a745;
            color: white;
        }
        
        .btn-approve:hover { 
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn-reject { 
            background: #dc3545;
            color: white;
        }
        
        .btn-reject:hover { 
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .no-items { 
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .reassign-form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .reassign-form select,
        .reassign-form button {
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.9em;
        }
        
        .reassign-form select:focus,
        .reassign-form button:focus {
            border-color: #c41e3a;
            outline: none;
        }
        
        .reassign-form button {
            background: #c41e3a;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
        }
        
        .reassign-form button:hover {
            background: #8B0000;
        }
    </style>

    <style>
    /* ===== RESPONSIVE DESIGN ===== */
    @media (max-width: 1024px) {
        .sidebar { width: 240px; }
        .main-content { margin-left: 240px; padding: 30px 30px; }
        .section { margin-bottom: 30px; }
        .ticket-card { padding: 20px; }
        .page-header {
            flex-wrap: wrap;
            gap: 15px;
        }
        .button-group {
            width: 100%;
            justify-content: flex-end;
        }
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
        .page-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }
        .page-header h1 { font-size: 1.8em; }
        .button-group {
            width: 100%;
            justify-content: flex-start;
        }
        .btn-view-closed {
            width: 100%;
            justify-content: center;
        }
        .section h2 { font-size: 1.4em; }
        .ticket-card { 
            padding: 15px; 
            margin-bottom: 15px;
        }
        .ticket-header { 
            flex-direction: column; 
            align-items: flex-start; 
            gap: 10px;
        }
        .ticket-actions { 
            flex-direction: column; 
            gap: 8px;
            width: 100%;
        }
        .ticket-actions .btn { 
            width: 100%; 
            padding: 10px;
        }
        
        /* Form responsive */
        .reassign-form { 
            flex-direction: column; 
            gap: 10px;
        }
        .reassign-form select { 
            font-size: 16px; /* Prevents zoom on iOS */
            padding: 8px;
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
        .btn-view-closed {
            padding: 10px 20px;
            font-size: 0.9em;
        }
        .section h2 { font-size: 1.2em; }
        .ticket-card { padding: 12px; }
        .ticket-title { font-size: 1.1em; }
        .ticket-meta { font-size: 0.85em; }
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
    }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo-container">
            <img src="<?php echo file_exists('NMIMS Logo.jpg') ? 'NMIMS Logo.jpg' : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIwIiBoZWlnaHQ9IjEyMCIgdmlld0JveD0iMCAwIDEyMCAxMjAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMjAiIGhlaWdodD0iMTIwIiBmaWxsPSIjQzQxRTNBIi8+Cjx0ZXh0IHg9IjYwIiB5PSI2NSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0id2hpdGUiIGZvbnQtc2l6ZT0iMTgiPk5NSlNUPC90ZXh0Pgo8L3N2Zz4='; ?>" alt="NMIMS Logo">
            <h3>NMIMS Issue Tracker</h3>
        </div>
        <div class="user-profile">
            <div class="profile-pic"><i class="fas fa-user-circle"></i></div>
            <h4><?= htmlspecialchars(str_replace('_', ' ', ucfirst($role))) ?></h4>
            <p><?= htmlspecialchars($sap_id) ?></p>
        </div>
        <nav class="nav-menu" role="navigation" aria-label="Main navigation">
            <a href="index.php" class="nav-item" aria-label="Go to Dashboard"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
            <a href="view_tickets.php" class="nav-item" aria-label="View My Own Tickets"><i class="fas fa-ticket-alt"></i><span>My Own Tickets</span></a>
            <a href="staff_tickets_with_sub.php" class="nav-item active" aria-label="Manage Assigned Tickets" aria-current="page"><i class="fas fa-tasks"></i><span>Assigned Tickets</span></a>
            <a href="notifications.php" class="nav-item" aria-label="View Notifications<?php if($unread_count>0): ?> (<?= $unread_count ?> unread)<?php endif; ?>"><i class="fas fa-bell"></i><span>Notifications</span><?php if($unread_count>0): ?><span class="notif-badge" aria-label="<?= $unread_count ?> unread notifications"><?= $unread_count ?></span><?php endif;?></a>
            <a href="logout.php" class="nav-item" aria-label="Logout from system"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="page-header">
            <h1>My Tickets & Assignments</h1>
            <div class="button-group">
                <a href="staff_tickets.php" class="btn-view-closed" title="Quick access to closed tickets">
                    <i class="fas fa-external-link-alt"></i> Direct Access
                </a>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?= $message_type === 'error' ? 'error-message' : 'success-message' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <!-- Pending Approvals Section -->
        <?php if ($approvals_result->num_rows > 0): ?>
        <div class="section">
            <div class="section-title">
                <i class="fas fa-hourglass-half"></i> Pending Approvals (<?= $approvals_result->num_rows ?>)
            </div>
            <div class="section-content">
                <?php while ($approval = $approvals_result->fetch_assoc()): ?>
                <div class="approval-card">
                    <h4>Ticket #<?= htmlspecialchars($approval['ticket_id']) ?> - <?= htmlspecialchars($approval['title']) ?></h4>
                    <p style="color: #666; margin: 10px 0;">Submitted by: <strong><?= htmlspecialchars($approval['sub_staff_sap']) ?></strong></p>
                    <p style="color: #999; font-size: 0.9em;">Submitted: <?= htmlspecialchars(date('d M Y, h:i A', strtotime($approval['submitted_at']))) ?></p>
                    
                    <form method="POST" class="approval-actions" onsubmit="return validateApproval(this)">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="approval_id" value="<?= $approval['approval_id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <div style="display: flex; gap: 8px; flex-wrap: wrap; width: 100%;">
                            <button type="button" class="btn-approve" onclick="approveRequest(this.form)"><i class="fas fa-check"></i> Approve</button>
                            <button type="button" class="btn-reject" onclick="showRejectForm(this.form)"><i class="fas fa-times"></i> Reject</button>
                        </div>
                        <textarea name="parent_notes" placeholder="Add revision notes (required for rejection)..." style="width: 100%; min-height: 80px; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 0.9em; display: none; margin-top: 10px;" id="notes_<?= $approval['approval_id'] ?>"></textarea>
                        <button type="submit" name="handle_approval" value="1" id="reject_btn_<?= $approval['approval_id'] ?>" style="display: none; background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; margin-top: 10px; width: 100%;">Confirm Rejection</button>
                    </form>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Direct Assignments -->
        <div class="section">
            <div class="section-title">
                <i class="fas fa-tasks"></i> My Assignments
            </div>
            <div class="section-content">
                <?php if ($direct_result->num_rows > 0): ?>
                <table class="ticket-table">
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>Title</th>
                            <th>Status</th>
                            <?php if ($can_have_subs): ?><th>Actions</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($ticket = $direct_result->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#<?= htmlspecialchars($ticket['ticket_id']) ?></strong></td>
                            <td><?= htmlspecialchars($ticket['title']) ?></td>
                            <td><?= htmlspecialchars($ticket['status']) ?></td>
                            <td>
                                <?php if ($can_have_subs && count($sub_staff) > 0): ?>
                                <form method="POST" class="reassign-form" style="margin-bottom: 8px;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="ticket_id" value="<?= $ticket['ticket_id'] ?>">
                                    <select name="sub_staff_id" required>
                                        <option value="">Assign to...</option>
                                        <?php foreach ($sub_staff as $sub): ?>
                                        <option value="<?= $sub['user_id'] ?>"><?= htmlspecialchars($sub['sap_id']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="reassign_to_sub"><i class="fas fa-arrow-right"></i> Assign</button>
                                </form>
                                <?php endif; ?>
                                <a href="ticket_details.php?ticket_id=<?= $ticket['ticket_id'] ?>" style="color: #c41e3a; text-decoration: none; font-weight: 600; font-size: 0.85em;">📋 View Details</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-items">
                    <i class="fas fa-inbox" style="font-size: 2em; color: #ddd; margin-bottom: 10px; display: block;"></i>
                    No direct assignments.
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Sub-Staff Assignments -->
        <?php if ($can_have_subs && $sub_assignments): ?>
        <div class="section">
            <div class="section-title">
                <i class="fas fa-users"></i> Sub-Staff Assignments
            </div>
            <div class="section-content">
                <?php if ($sub_assignments->num_rows > 0): ?>
                <table class="ticket-table">
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>Title</th>
                            <th>Assigned To</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($sub_ticket = $sub_assignments->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#<?= htmlspecialchars($sub_ticket['ticket_id']) ?></strong></td>
                            <td><?= htmlspecialchars($sub_ticket['title']) ?></td>
                            <td><?= htmlspecialchars($sub_ticket['sub_staff_sap']) ?></td>
                            <td>
                                <?php if ($sub_ticket['sub_staff_status'] === 'pending_approval'): ?>
                                    <span class="status-badge status-pending-approval">Pending Approval</span>
                                <?php else: ?>
                                    <?= htmlspecialchars($sub_ticket['status']) ?>
                                <?php endif; ?>
                            </td>
                            <td><a href="ticket_details.php?ticket_id=<?= $sub_ticket['ticket_id'] ?>" style="color: #c41e3a; text-decoration: none; font-weight: 600; font-size: 0.85em;">📋 View Details</a></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-items">
                    <p>No sub-staff assignments yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        function approveRequest(form) {
            document.querySelector('input[name=action]').value = 'approve';
            form.submit();
        }
        
        function showRejectForm(form) {
            const approvalId = form.querySelector('input[name=approval_id]').value;
            const textarea = document.getElementById('notes_' + approvalId);
            const rejectBtn = document.getElementById('reject_btn_' + approvalId);
            
            if (textarea.style.display === 'none') {
                textarea.style.display = 'block';
                rejectBtn.style.display = 'block';
                textarea.focus();
                form.querySelector('input[name=action]').value = 'reject';
            } else {
                textarea.style.display = 'none';
                rejectBtn.style.display = 'none';
                textarea.value = '';
                form.querySelector('input[name=action]').value = 'approve';
            }
        }
        
        function validateApproval(form) {
            const action = form.querySelector('input[name=action]').value;
            const approvalId = form.querySelector('input[name=approval_id]').value;
            const textarea = document.getElementById('notes_' + approvalId);
            
            if (action === 'reject') {
                if (textarea.value.trim() === '') {
                    alert('Please enter revision notes before rejecting.');
                    return false;
                }
            }
            return true;
        }
    </script>
</body>
</html>
