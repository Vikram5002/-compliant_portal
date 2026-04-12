<?php
include 'db_connect.php';
include 'send_email.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'maintenance', 'warden', 'rector', 'security', 'house_keeping', 'network/it_team' ])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$sap_id = $_SESSION['sap_id'] ?? 'Unknown';

$message = "";
$allowed_statuses = ['Received' => 'Received', 'In Progress' => 'In Progress', 'Solution Proposed' => 'Solution Proposed', 'Resolved' => 'Resolved'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $message = "Invalid CSRF token.";
    } else {
        $ticket_id = intval($_POST['ticket_id']);
        $new_status = $_POST['status'] ?? '';

        if (in_array($new_status, array_keys($allowed_statuses))) {
            $update_sql = "UPDATE tickets SET status = ? WHERE ticket_id = ? AND assigned_to = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("sii", $new_status, $ticket_id, $user_id);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    // Record the status change in StatusHistory
                    $history_sql = "INSERT INTO statushistory (ticket_id, status, timestamp) VALUES (?, ?, NOW())";
                    $history_stmt = $conn->prepare($history_sql);
                    $history_stmt->bind_param("is", $ticket_id, $new_status);
                    $history_stmt->execute();
                    $history_stmt->close();
                    
                    $message = "Ticket #" . $ticket_id . " status updated successfully.";
                    if ($new_status === 'Resolved') {
                        // Email logic...
                    }
                } else {
                    $message = "Status is already set to '" . htmlspecialchars($new_status) . "'. No changes made.";
                }
            } else {
                $message = "Error updating status.";
            }
            $stmt->close();
        } else {
            $message = "Invalid status selected.";
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

// Fetch tickets assigned to the logged-in user
$sql = "SELECT t.ticket_id, t.title, t.category, t.priority, t.status, t.created_at, u.sap_id as creator_sap,
        (SELECT feedback_text FROM feedback WHERE ticket_id = t.ticket_id ORDER BY created_at DESC LIMIT 1) AS feedback_text
        FROM tickets t
        JOIN users u ON t.user_id = u.user_id
        WHERE t.assigned_to = ?
        ORDER BY t.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Assigned Tickets - NMIMS Issue Tracker</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
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
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap;
    }
    
    .page-header h1 { 
        color: #c41e3a;
        margin: 0; 
        font-size: 2.2em;
        font-weight: 700;
        letter-spacing: -0.5px;
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

    /* ===== MESSAGES ===== */
    .message { 
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
    
    .ticket-table td strong {
        color: #2c3e50;
        font-weight: 700;
    }
    
    .ticket-table small {
        color: #666;
        font-weight: 500;
    }
    
    /* ===== FEEDBACK TEXT ===== */
    .feedback-text { 
        color: #c41e3a;
        font-style: italic; 
        font-size: 0.95em;
        font-weight: 500;
    }

    /* ===== UPDATE FORM ===== */
    .update-form {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .update-form select { 
        padding: 10px 15px; 
        border: 2px solid #e0e0e0;
        border-radius: 6px; 
        font-size: 1em;
        font-weight: 500;
        color: #2c3e50;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
    }
    
    .update-form select:hover {
        border-color: #c41e3a;
        box-shadow: 0 4px 12px rgba(196, 30, 58, 0.1);
    }
    
    .update-form select:focus {
        border-color: #c41e3a;
        box-shadow: 0 0 0 4px rgba(196, 30, 58, 0.1);
        outline: none;
    }
    
    .update-form button { 
        background: linear-gradient(135deg, #c41e3a 0%, #8B0000 100%);
        color: white; 
        border: none; 
        padding: 10px 20px; 
        border-radius: 6px; 
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(196, 30, 58, 0.2);
    }
    
    .update-form button:hover { 
        background: linear-gradient(135deg, #8B0000 0%, #6B0000 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(196, 30, 58, 0.3);
    }
    
    .update-form button:active {
        transform: translateY(0);
    }
    
    /* ===== VIEW BUTTON ===== */
    .btn-view { 
        background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
        color: white; 
        padding: 10px 18px; 
        text-decoration: none; 
        border-radius: 6px; 
        display: inline-block;
        font-weight: 600;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .btn-view:hover {
        background: linear-gradient(135deg, #5a6268 0%, #495057 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
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

    /* ===== RESPONSIVE DESIGN ===== */
    @media (max-width: 1024px) {
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
        .page-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }
        .button-group {
            width: 100%;
            justify-content: flex-start;
        }
        .btn-view-closed {
            width: 100%;
            justify-content: center;
        }
        .page-header h1 { font-size: 1.8em; }
    }

    @media (max-width: 480px) {
        .page-header h1 { font-size: 1.5em; }
        .btn-view-closed {
            padding: 10px 20px;
            font-size: 0.9em;
        }
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
            <a href="staff_tickets.php" class="nav-item active" aria-label="View Assigned Tickets" aria-current="page"><i class="fas fa-tasks"></i><span>Assigned Tickets</span></a>
            <?php if (in_array($role, ['admin', 'network/it_team'])): ?><a href="bulk_import.php" class="nav-item" aria-label="Bulk Import Users"><i class="fas fa-user-plus"></i><span>Add Users</span></a><?php endif; ?>
            <a href="notifications.php" class="nav-item" aria-label="View Notifications"><i class="fas fa-bell"></i><span>Notifications</span></a>
            <a href="logout.php" class="nav-item" aria-label="Logout from system"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </nav>
    </div>
    <div class="main-content">
        <div class="page-header">
            <h1>My Assigned Tickets</h1>
            <div class="button-group">
                <a href="staff_tickets_with_sub.php" class="btn-view-closed" title="Access tickets with sub-staff management">
                    <i class="fas fa-external-link-alt"></i> Direct Access with Sub-Staff
                </a>
            </div>
        </div>
        <?php if ($message): ?>
            <div class="message <?= (strpos($message, 'Error') !== false || strpos($message, 'No changes') !== false) ? 'error-message' : 'success-message' ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <div class="tickets-container">
            <table class="ticket-table">
                <thead><tr><th>ID</th><th>Title</th><th>Created By</th><th>Feedback</th><th>Update Status</th></tr></thead>
                <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= htmlspecialchars($row['ticket_id']) ?></td>
                        <td><strong><?= htmlspecialchars($row['title']) ?></strong><br><small><?= htmlspecialchars($row['category']) ?></small></td>
                        <td><?= htmlspecialchars($row['creator_sap']) ?></td>
                        <td class="feedback-text"><?= htmlspecialchars($row['feedback_text']) ?></td>
                        <td>
                            <form method="POST" action="" class="update-form">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="ticket_id" value="<?= $row['ticket_id'] ?>">
                                <a href="ticket_details.php?ticket_id=<?= $row['ticket_id'] ?>" class="btn-view">View</a>
                                <select name="status" required>
                                    <?php foreach ($allowed_statuses as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= ($row['status'] == $key) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="update_status">Update</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align: center; padding: 50px;">No tickets are currently assigned to you.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>