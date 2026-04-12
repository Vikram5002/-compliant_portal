<?php
include 'db_connect.php';
include 'send_email.php';
include 'nav_helper.php';

// Security check - only sub-staff can access
$sub_staff_roles = ['sub_security', 'sub_maintenance', 'sub_house_keeping', 'sub_warden', 'sub_staff', 'sub_rector'];

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $sub_staff_roles)) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$sap_id = $_SESSION['sap_id'] ?? 'Unknown';

// Get parent staff info
$parent_query = $conn->prepare("SELECT user_id, sap_id, email FROM users WHERE user_id = (SELECT parent_staff_id FROM users WHERE user_id = ?)");
$parent_query->bind_param("i", $user_id);
$parent_query->execute();
$parent_result = $parent_query->get_result();
$parent_staff = $parent_result->fetch_assoc();
$parent_staff_id = $parent_staff['user_id'] ?? null;
$parent_query->close();

$message = "";
$message_type = "";

// Handle approval request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_approval'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $message = "Invalid CSRF token.";
        $message_type = "error";
    } else {
        $ticket_id = intval($_POST['ticket_id']);
        $approval_notes = sanitize_input($_POST['approval_notes'] ?? '');

        // Verify ticket is assigned to this sub-staff
        $check_stmt = $conn->prepare("SELECT ticket_id FROM tickets WHERE ticket_id = ? AND reassigned_to = ?");
        $check_stmt->bind_param("ii", $ticket_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            // Create approval request
            $approval_stmt = $conn->prepare("INSERT INTO substaffapprovals (ticket_id, sub_staff_id, parent_staff_id, status) VALUES (?, ?, ?, 'pending')");
            $approval_stmt->bind_param("iii", $ticket_id, $user_id, $parent_staff_id);

            if ($approval_stmt->execute()) {
                // Update ticket status
                $update_ticket = $conn->prepare("UPDATE tickets SET sub_staff_status = 'pending_approval' WHERE ticket_id = ?");
                $update_ticket->bind_param("i", $ticket_id);
                $update_ticket->execute();
                $update_ticket->close();

                // Send email notification to parent staff
                $email_subject = "Approval Request: Ticket #$ticket_id";
                $email_body = "
                    <h2>Work Approval Request</h2>
                    <p>Sub-staff member " . htmlspecialchars($sap_id) . " has completed their work on ticket #$ticket_id and requests your approval.</p>
                    <p>Please log in to review and approve/reject this submission.</p>
                ";
                sendTicketEmail($parent_staff['email'], $email_subject, $email_body);

                $message = "Approval request submitted successfully.";
                $message_type = "success";
            } else {
                $message = "Error submitting approval request.";
                $message_type = "error";
            }
            $approval_stmt->close();
        } else {
            $message = "Ticket not found or you are not assigned to it.";
            $message_type = "error";
        }
        $check_stmt->close();
    }
}

// Handle status update for tickets assigned to sub-staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $message = "Invalid CSRF token.";
        $message_type = "error";
    } else {
        $ticket_id = intval($_POST['ticket_id']);
        $new_status = sanitize_input($_POST['status']);
        // Sub-staff can only update to these statuses, NOT directly to Resolved
        $allowed_statuses = ['Received', 'In Progress', 'Solution Proposed'];

        if (in_array($new_status, $allowed_statuses)) {
            // Verify ownership
            $verify_stmt = $conn->prepare("SELECT ticket_id FROM tickets WHERE ticket_id = ? AND reassigned_to = ?");
            $verify_stmt->bind_param("ii", $ticket_id, $user_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();

            if ($verify_result->num_rows > 0) {
                $update_stmt = $conn->prepare("UPDATE tickets SET status = ? WHERE ticket_id = ?");
                $update_stmt->bind_param("si", $new_status, $ticket_id);

                if ($update_stmt->execute()) {
                    // Record in StatusHistory
                    $history_stmt = $conn->prepare("INSERT INTO statushistory (ticket_id, status, timestamp) VALUES (?, ?, NOW())");
                    $history_stmt->bind_param("is", $ticket_id, $new_status);
                    $history_stmt->execute();
                    $history_stmt->close();

                    $message = "Ticket #$ticket_id status updated to $new_status.";
                    $message_type = "success";
                } else {
                    $message = "Error updating ticket status.";
                    $message_type = "error";
                }
                $update_stmt->close();
            }
            $verify_stmt->close();
        } else {
            $message = "Invalid status selected.";
            $message_type = "error";
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

// Fetch tickets assigned to this sub-staff
$sql = "SELECT t.ticket_id, t.title, t.category, t.priority, t.status, t.created_at, t.sub_staff_status,
        u.sap_id as creator_sap, u_parent.sap_id as parent_sap,
        sa.parent_notes as rejection_reason
        FROM tickets t
        JOIN users u ON t.user_id = u.user_id
        JOIN users u_parent ON t.assigned_to = u_parent.user_id
        LEFT JOIN substaffapprovals sa ON t.ticket_id = sa.ticket_id AND sa.status = 'rejected' AND sa.sub_staff_id = ?
        WHERE t.reassigned_to = ? AND t.status != 'Resolved'
        ORDER BY t.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$tickets_result = $stmt->get_result();

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
    <title>My Tasks - NMIMS Issue Tracker</title>
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
            margin: 0;
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
        
        .parent-info {
            padding: 15px 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            margin: 15px 15px;
            font-size: 0.9em;
            border-left: 3px solid #ffc107;
        }
        
        .parent-info strong { color: #ffc107; }
        
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
        
        .page-header h1 { 
            color: #c41e3a;
            font-size: 2.2em;
            font-weight: 700;
            margin-bottom: 10px;
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
        
        .tickets-container { 
            background: white; 
            border-radius: 12px; 
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .ticket-table { 
            width: 100%; 
            border-collapse: collapse;
        }
        
        .ticket-table th { 
            background: linear-gradient(135deg, #c41e3a 0%, #8B0000 100%);
            color: white;
            font-weight: 700; 
            padding: 20px;
            text-align: left;
        }
        
        .ticket-table td { 
            padding: 20px; 
            border-bottom: 1px solid #f0f0f0; 
        }
        
        .ticket-table tbody tr:hover {
            background: #f5f5f5;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            display: inline-block;
        }
        
        .action-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .action-form select,
        .action-form textarea {
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.9em;
        }
        
        .action-form select:focus,
        .action-form textarea:focus {
            border-color: #c41e3a;
            outline: none;
        }
        
        .action-form button {
            background: linear-gradient(135deg, #c41e3a 0%, #8B0000 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .action-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(196, 30, 58, 0.3);
        }
        
        .no-tickets {
            text-align: center;
            padding: 60px 40px;
            color: #999;
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
        <?php if ($parent_staff): ?>
        <div class="parent-info">
            <strong>Reports To:</strong><br>
            <?= htmlspecialchars($parent_staff['sap_id']) ?>
        </div>
        <?php endif; ?>
        <nav class="nav-menu" role="navigation" aria-label="Main navigation">
            <a href="index.php" class="nav-item" aria-label="Go to Dashboard"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
            <a href="view_tickets.php" class="nav-item" aria-label="View My Own Tickets"><i class="fas fa-ticket-alt"></i><span>My Own Tickets</span></a>
            <a href="sub_staff_dashboard.php" class="nav-item active" aria-label="View My Tasks" aria-current="page"><i class="fas fa-tasks"></i><span>My Tasks</span></a>
            <a href="notifications.php" class="nav-item" aria-label="View Notifications<?php if($unread_count>0): ?> (<?= $unread_count ?> unread)<?php endif; ?>"><i class="fas fa-bell"></i><span>Notifications</span><?php if($unread_count>0): ?><span class="notif-badge" aria-label="<?= $unread_count ?> unread notifications"><?= $unread_count ?></span><?php endif;?></a>
            <a href="logout.php" class="nav-item" aria-label="Logout from system"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="page-header">
            <h1>My Assigned Tasks</h1>
            <p style="color: #666; margin-top: 5px;">Work on tickets assigned by <?= htmlspecialchars($parent_staff['sap_id'] ?? 'Admin') ?></p>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?= $message_type === 'error' ? 'error-message' : 'success-message' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <div class="tickets-container">
            <?php if ($tickets_result->num_rows > 0): ?>
            <table class="ticket-table">
                <thead>
                    <tr>
                        <th>Ticket ID</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($ticket = $tickets_result->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#<?= htmlspecialchars($ticket['ticket_id']) ?></strong></td>
                        <td>
                            <?= htmlspecialchars($ticket['title']) ?>
                            <?php if ($ticket['rejection_reason']): ?>
                            <div style="margin-top: 10px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                                <strong style="color: #856404;">⚠️ Revision Required:</strong>
                                <p style="margin: 5px 0 0 0; color: #856404; font-size: 0.9em;"><?= nl2br(htmlspecialchars($ticket['rejection_reason'])) ?></p>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($ticket['sub_staff_status'] === 'pending_approval'): ?>
                                <span class="status-pending">Pending Approval</span>
                            <?php else: ?>
                                <?= htmlspecialchars($ticket['status']) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars(ucfirst($ticket['priority'])) ?></td>
                        <td>
                            <form method="POST" class="action-form" style="display: flex; gap: 8px; flex-wrap: wrap; margin: 0;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="ticket_id" value="<?= $ticket['ticket_id'] ?>">
                                
                                <?php if ($ticket['sub_staff_status'] !== 'pending_approval'): ?>
                                    <?php if ($ticket['status'] !== 'Solution Proposed'): ?>
                                    <!-- Status update dropdown - cannot change to Resolved directly -->
                                    <select name="status" required style="padding: 8px 10px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 0.85em;">
                                        <option value="">Update Status...</option>
                                        <option value="In Progress" <?= $ticket['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                        <option value="Solution Proposed" <?= $ticket['status'] === 'Solution Proposed' ? 'selected' : '' ?>>Solution Proposed</option>
                                    </select>
                                    <button type="submit" name="update_status" style="background: #c41e3a; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85em;">Update</button>
                                    <?php else: ?>
                                    <!-- When solution proposed, show approval button instead of status dropdown -->
                                    <button type="submit" name="request_approval" style="background: linear-gradient(135deg, #28a745 0%, #218838 100%); color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85em; width: 100%;"><i class="fas fa-check-circle"></i> Request Approval for Resolution</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </form>
                            <a href="ticket_details.php?ticket_id=<?= $ticket['ticket_id'] ?>" style="color: #c41e3a; text-decoration: none; font-weight: 600; font-size: 0.9em; display: inline-block; margin-top: 8px;">📋 View Details</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-tickets">
                <i class="fas fa-inbox" style="font-size: 3em; color: #ddd; margin-bottom: 20px; display: block;"></i>
                <p>No tasks assigned to you yet.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
