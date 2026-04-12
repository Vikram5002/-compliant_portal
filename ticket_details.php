<?php
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$sap_id = $_SESSION['sap_id'] ?? 'Unknown';

// Notification count for sidebar
$notifq = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$notifq->bind_param("i", $user_id);
$notifq->execute();
$notifq->bind_result($unread_count);
$notifq->fetch();
$notifq->close();

$ticket = null;
if (isset($_GET['ticket_id'])) {
    $ticket_id = intval($_GET['ticket_id']);
    $sql = "SELECT t.*, a.file_path, u.sap_id as creator_sap FROM tickets t
            LEFT JOIN attachments a ON t.attachment_id = a.attachment_id
            LEFT JOIN users u ON t.user_id = u.user_id
            WHERE t.ticket_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows === 1) {
        $ticket = $result->fetch_assoc();
        // Fetch status history for timeline
        $history_sql = "SELECT status, timestamp FROM statushistory WHERE ticket_id = ? ORDER BY timestamp ASC";
        $history_stmt = $conn->prepare($history_sql);
        $history_stmt->bind_param("i", $ticket_id);
        $history_stmt->execute();
        $history_result = $history_stmt->get_result();
        $ticket['status_history'] = [];
        while ($row = $history_result->fetch_assoc()) {
            $ticket['status_history'][$row['status']] = $row['timestamp'];
        }
        $history_stmt->close();
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ticket #<?= htmlspecialchars($ticket['ticket_id'] ?? '') ?> - NMIMS Issue Tracker</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
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
        
        .btn-back { 
            background: linear-gradient(135deg, #ffffff 0%, #f0f0f0 100%);
            color: #c41e3a; 
            padding: 12px 28px; 
            text-decoration: none; 
            border-radius: 8px; 
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 2px solid #c41e3a;
        }
        
        .btn-back:hover { 
            background: #c41e3a;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(196, 30, 58, 0.3);
        }

        /* ===== TICKET DETAILS CONTAINER ===== */
        .ticket-details-container { 
            background: white;
            padding: 50px; 
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            animation: fadeInUp 0.7s ease-out 0.2s both;
        }
        
        /* ===== DETAILS GRID ===== */
        .details-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 30px; 
            margin-bottom: 50px;
        }
        
        .detail-item {
            padding: 20px;
            border-radius: 10px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-left: 4px solid #c41e3a;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .detail-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(196, 30, 58, 0.15);
        }
        
        .detail-item .label { 
            font-weight: 700; 
            color: #c41e3a; 
            display: block; 
            margin-bottom: 10px; 
            font-size: 0.85em; 
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .detail-item .value { 
            font-size: 1.1em; 
            color: #2c3e50;
            font-weight: 500;
        }
        
        .detail-item .value.description { 
            white-space: pre-wrap; 
            line-height: 1.8;
            color: #555;
        }
        
        .attachment-link { 
            color: #c41e3a;
            text-decoration: none; 
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .attachment-link:hover {
            transform: translateX(3px);
            text-decoration: underline;
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

        /* ===== TRACKER SECTION ===== */
        .tracker-section { 
            margin-top: 60px;
            padding-top: 40px;
            border-top: 2px solid #f0f0f0;
            animation: fadeInUp 0.8s ease-out 0.4s both;
        }
        
        .tracker-title { 
            font-size: 1.8em; 
            font-weight: 700; 
            color: #c41e3a; 
            margin-bottom: 40px; 
            letter-spacing: -0.5px;
        }
        
        .status-timeline { 
            display: flex; 
            justify-content: space-between; 
            position: relative; 
            padding: 20px 5%;
        }
        
        .status-timeline::before { 
            content: ''; 
            position: absolute; 
            top: 40px; 
            left: 10%; 
            right: 10%; 
            height: 3px; 
            background: linear-gradient(90deg, #c41e3a 0%, #c41e3a 50%, #e0e0e0 50%, #e0e0e0 100%);
            z-index: 1;
        }
        
        .timeline-step { 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            text-align: center; 
            flex: 1; 
            position: relative; 
            z-index: 2;
            transition: all 0.4s ease;
        }
        
        .timeline-circle { 
            width: 40px; 
            height: 40px; 
            border-radius: 50%; 
            background: #ffffff;
            border: 3px solid #e0e0e0;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .timeline-text { 
            margin-top: 20px; 
            font-size: 0.95em; 
            font-weight: 500; 
            color: #666;
            line-height: 1.4;
        }
        
        /* ===== TIMELINE STATES ===== */
        .timeline-step.completed .timeline-circle { 
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border-color: #2e7d32;
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.3);
        }
        
        .timeline-step.active .timeline-circle { 
            background: linear-gradient(135deg, #c41e3a 0%, #8B0000 100%);
            border-color: #c41e3a;
            transform: scale(1.3);
            box-shadow: 0 0 0 10px rgba(196, 30, 58, 0.15);
            animation: pulse 2s infinite;
        }
        
        .timeline-step.completed .timeline-text, 
        .timeline-step.active .timeline-text { 
            color: #2c3e50; 
            font-weight: 700;
        }
        
        .no-ticket { 
            text-align: center; 
            color: #999; 
            padding: 80px 50px; 
            font-size: 1.3em;
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
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(196, 30, 58, 0.4);
            }
            50% {
                box-shadow: 0 0 0 10px rgba(196, 30, 58, 0);
            }
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }

        /* ===== RESPONSIVE DESIGN ===== */
        @media (max-width: 1024px) {
            .sidebar { width: 240px; }
            .main-content { margin-left: 240px; padding: 30px 30px; }
            .ticket-details { padding: 25px; }
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
            .ticket-details { 
                padding: 15px; 
                margin-bottom: 20px;
            }
            .detail-item { 
                flex-direction: column; 
                align-items: flex-start; 
                gap: 5px;
            }
            .label { font-size: 0.9em; }
            .value { font-size: 0.95em; }
            
            /* Status history responsive */
            .status-history { padding: 15px; }
            .status-item { 
                padding: 10px; 
                font-size: 0.9em;
            }
            
            /* Comments responsive */
            .comments-section { padding: 15px; }
            .comment { 
                padding: 12px; 
                margin-bottom: 12px;
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
            .page-header { margin-bottom: 15px; }
            .page-header h1 { font-size: 1.5em; }
            .ticket-details { padding: 12px; }
            .status-history { padding: 12px; }
            .comments-section { padding: 12px; }
            .comment { padding: 10px; }
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
    <div class="logo-container"><img src="<?php echo file_exists('NMIMS Logo.jpg') ? 'NMIMS Logo.jpg' : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIwIiBoZWlnaHQ9IjEyMCIgdmlld0JveD0iMCAwIDEyMCAxMjAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMjAiIGhlaWdodD0iMTIwIiBmaWxsPSIjQzQxRTNBIi8+Cjx0ZXh0IHg9IjYwIiB5PSI2NSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0id2hpdGUiIGZvbnQtc2l6ZT0iMTgiPk5NSlNUPC90ZXh0Pgo8L3N2Zz4='; ?>" alt="NMIMS Logo"><h3>NMIMS Issue Tracker</h3></div>
    <div class="user-profile"><div class="profile-pic"><i class="fas fa-user-circle"></i></div><h4><?= htmlspecialchars($role) ?></h4><p><?= htmlspecialchars($sap_id) ?></p></div>
    <nav class="nav-menu" role="navigation" aria-label="Main navigation">
        <a href="index.php" class="nav-item" aria-label="Go to Dashboard"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
        <a href="view_tickets.php" class="nav-item" aria-label="View My Own Tickets"><i class="fas fa-ticket-alt"></i><span>My Own Tickets</span></a>
        <?php if (strpos($role, 'sub_') === 0): ?>
        <a href="sub_staff_dashboard.php" class="nav-item" aria-label="View My Tasks"><i class="fas fa-tasks"></i><span>My Tasks</span></a>
        <?php elseif (in_array($role,['staff','maintenance','warden','security','house_keeping'])): ?><a href="staff_tickets_with_sub.php" class="nav-item" aria-label="Manage Assigned Tickets"><i class="fas fa-tasks"></i><span>Assigned Tickets</span></a><?php endif; ?>
        <?php if (in_array($role,['rector','network/it_team'])): ?><a href="staff_tickets.php" class="nav-item" aria-label="View Assigned Tickets"><i class="fas fa-tasks"></i><span>Assigned Tickets</span></a><?php endif; ?>
        <?php if (in_array($role, ['admin','super_visor'])): ?><a href="admin_dashboard.php" class="nav-item" aria-label="Access Admin Dashboard"><i class="fas fa-user-shield"></i><span>Admin Dashboard</span></a><?php endif; ?>
        <?php if (in_array($role, ['admin','network/it_team'])): ?><a href="bulk_import.php" class="nav-item" aria-label="Bulk Import Users"><i class="fas fa-user-plus"></i><span>Add Users</span></a><?php endif; ?>
        <a href="notifications.php" class="nav-item" aria-label="View Notifications<?php if($unread_count > 0): ?> (<?= $unread_count ?> unread)<?php endif; ?>"><i class="fas fa-bell"></i><span>Notifications</span><?php if($unread_count > 0): ?><span class="notif-badge" aria-label="<?= $unread_count ?> unread notifications"><?= $unread_count ?></span><?php endif;?></a>
        <a href="logout.php" class="nav-item" aria-label="Logout from system"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
    </nav>
</div>
<div class="main-content">
    <?php if (!$ticket): ?>
        <div class="no-ticket">Ticket not found or you do not have permission to view it.</div>
    <?php else: ?>
        <div class="page-header">
            <h1>Ticket #<?= htmlspecialchars($ticket['ticket_id']) ?></h1>
            <a href="javascript:history.back()" class="btn-back">← Back</a>
        </div>
        <div class="ticket-details-container">
            <div class="details-grid">
                <div class="detail-item"><span class="label">Title</span><span class="value"><?= htmlspecialchars($ticket['title']) ?></span></div>
                <div class="detail-item"><span class="label">Submitted By</span><span class="value"><?= htmlspecialchars($ticket['creator_sap']) ?></span></div>
                <div class="detail-item"><span class="label">Category</span><span class="value"><?= htmlspecialchars($ticket['category']) ?></span></div>
                <div class="detail-item"><span class="label">Location</span><span class="value"><?= htmlspecialchars($ticket['location']) ?></span></div>
                <div class="detail-item"><span class="label">Priority</span><span class="value"><?= htmlspecialchars(ucfirst($ticket['priority'])) ?></span></div>
                <div class="detail-item"><span class="label">Created At</span><span class="value"><?= htmlspecialchars(date('d M Y, h:i A', strtotime($ticket['created_at']))) ?></span></div>
                <div class="detail-item" style="grid-column: 1 / -1;"><span class="label">Description</span><span class="value description"><?= nl2br(htmlspecialchars($ticket['description'])) ?></span></div>
                <?php if (!empty($ticket['file_path'])): ?>
                <div class="detail-item"><span class="label">Attachment</span><span class="value">
                    <a href="<?= htmlspecialchars($ticket['file_path']) ?>" target="_blank" class="attachment-link"><i class="fas fa-paperclip"></i> View Attached File</a>
                </span></div>
                <?php endif; ?>
                 <div class="detail-item" style="grid-column: 1 / -1;"><span class="label">Current Status</span><span class="value">
                    <span class="status-chip status-<?= strtolower(str_replace(' ', '-', $ticket['status'])) ?>"><?= htmlspecialchars($ticket['status']) ?></span>
                </span></div>
            </div>

            <div class="tracker-section">
                <div class="tracker-title">Ticket Progress</div>
                <div class="status-timeline">
                    <?php
                    $statuses = ['Received', 'In Progress', 'Solution Proposed', 'Resolved', 'Closed'];
                    $current_status = $ticket['status'];
                    $currentIndex = array_search($current_status, $statuses);
                    if ($currentIndex === false) { $currentIndex = -1; }

                    foreach ($statuses as $index => $status) {
                        $step_class = 'timeline-step';
                        $timestamp = '';
                        
                        if ($index < $currentIndex) { 
                            $step_class .= ' completed';
                            // Show timestamp for completed status
                            if ($status === 'Received') {
                                // "Received" is always the creation time
                                $completed_time = date('d M Y, h:i A', strtotime($ticket['created_at']));
                            } elseif (isset($ticket['status_history'][$status])) {
                                $completed_time = date('d M Y, h:i A', strtotime($ticket['status_history'][$status]));
                            } else {
                                $completed_time = 'Completed';
                            }
                            $timestamp = '<div style="font-size: 0.8em; color: #6c757d; margin-top: 5px;">' . htmlspecialchars($completed_time) . '</div>';
                        } 
                        elseif ($index === $currentIndex) { 
                            $step_class .= ' active';
                            // Show timestamp for current status
                            if ($status === 'Received') {
                                $current_time = date('d M Y, h:i A', strtotime($ticket['created_at']));
                            } elseif (isset($ticket['status_history'][$status])) {
                                $current_time = date('d M Y, h:i A', strtotime($ticket['status_history'][$status]));
                            } else {
                                $current_time = date('d M Y, h:i A', strtotime($ticket['created_at']));
                            }
                            $timestamp = '<div style="font-size: 0.8em; color: #8B0000; margin-top: 5px; font-weight: 600;">' . htmlspecialchars($current_time) . '</div>';
                        }
                        else {
                            $timestamp = '<div style="font-size: 0.8em; color: #adb5bd; margin-top: 5px;">Pending</div>';
                        }
                        
                        echo "<div class='{$step_class}'>";
                        echo "<div class='timeline-circle'></div>";
                        echo "<div class='timeline-text'>" . htmlspecialchars($status) . $timestamp . "</div>";
                        echo "</div>";
                    }
                    ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
