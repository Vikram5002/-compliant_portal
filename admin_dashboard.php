<?php
// Start session and include dependencies
include 'db_connect.php'; // Ensures session_start() is called
include 'send_email.php';
include 'nav_helper.php';

// --- Security and Session Management ---
// 1. Ensure the user is an admin or super_visor
$allowed_dashboard_roles = ['admin', 'super_visor'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_dashboard_roles)) {
    header("Location: login.php");
    exit;
}

// 2. Generate a CSRF token if one doesn't exist to prevent cross-site attacks
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 3. Get user details from session
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$sap_id = $_SESSION['sap_id'] ?? 'Unknown';

// --- Handle Form Submissions (The Fix) ---

// A. Handle Ticket Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_ticket_id'], $_POST['assigned_to'], $_POST['csrf_token'])) {
    // Verify CSRF token
    if (hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $ticket_id_to_assign = (int)$_POST['assign_ticket_id'];
        $assigned_to_user_id = $_POST['assigned_to'];

        // If "--Assign--" is selected, set assigned_to to NULL (un-assign)
        $assigned_to_value = empty($assigned_to_user_id) ? NULL : (int)$assigned_to_user_id;

        // Prepare and execute the update query
        $update_stmt = $conn->prepare("UPDATE tickets SET assigned_to = ? WHERE ticket_id = ?");
        $update_stmt->bind_param("ii", $assigned_to_value, $ticket_id_to_assign);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Redirect to prevent form resubmission on refresh
        header("Location: admin_dashboard.php");
        exit;
    }
}

// B. Handle Priority Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['priority_ticket_id'], $_POST['priority'], $_POST['csrf_token'])) {
     // Verify CSRF token
    if (hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $ticket_id_for_priority = (int)$_POST['priority_ticket_id'];
        $priority_value = empty($_POST['priority']) ? NULL : $_POST['priority'];
        
        $update_stmt = $conn->prepare("UPDATE tickets SET priority = ? WHERE ticket_id = ?");
        $update_stmt->bind_param("si", $priority_value, $ticket_id_for_priority);
        $update_stmt->execute();
        $update_stmt->close();

        // Redirect to prevent form resubmission on refresh
        header("Location: admin_dashboard.php");
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

// Notification count
$notifq = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$notifq->bind_param("i", $user_id); $notifq->execute(); $notifq->bind_result($unread_count); $notifq->fetch(); $notifq->close();

// Fetch active tickets
$sql = "SELECT t.*, u.sap_id as creator_sap_id,
        (SELECT feedback_text FROM feedback WHERE ticket_id = t.ticket_id ORDER BY created_at DESC LIMIT 1) AS feedback_text
        FROM tickets t
        LEFT JOIN users u ON t.user_id = u.user_id
        WHERE t.status != 'Closed'
        ORDER BY t.created_at DESC";
$result = $conn->query($sql);

// Fetch staff for assignment dropdown
$staff_query = $conn->query("SELECT user_id, sap_id, role FROM users WHERE role IN ('staff', 'maintenance', 'warden', 'rector', 'security', 'house_keeping', 'network/it_team')");
$staff = $staff_query->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard - NMIMS Issue Tracker</title>
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
        font-size: 0.95em; 
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
    
    .button-group {
        display: flex;
        gap: 2px;
        align-items: center;
    }
    
    .btn-view-closed { 
        background: linear-gradient(135deg, #c41e3a 0%, #8B0000 100%);
        color: white; 
        padding: 12px 28px; 
        text-decoration: none; 
        border-radius: 8px; 
        font-weight: 700; 
        transition: transform 250ms cubic-bezier(0.2, 0.9, 0.2, 1),
                    box-shadow 250ms cubic-bezier(0.2, 0.9, 0.2, 1),
                    background-color 220ms ease, filter 220ms ease;
        box-shadow: 0 6px 14px rgba(196, 30, 58, 0.12);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        letter-spacing: 0.5px;
        animation: slideDown 0.6s ease-out 0.1s both;
    }
    
    .btn-view-closed:hover, .btn-view-closed:focus {
        transform: translateY(-6px) scale(1.02);
        box-shadow: 0 18px 40px rgba(196, 30, 58, 0.18);
        filter: brightness(2.03);
        outline: none;
    }

    .btn-view-closed:active {
        transform: translateY(-2px) scale(0.995);
        box-shadow: 0 8px 18px rgba(196, 30, 58, 0.12);
    }

    /* Primary variant (blue) used for Bulk Import button */
    .btn-primary {
        background: linear-gradient(135deg, #007bff 0%, #0056d6 100%);
        box-shadow: 0 6px 14px rgba(0, 123, 255, 0.12);
    }

    .btn-primary:hover, .btn-primary:focus {
        box-shadow: 0 18px 40px rgba(0, 123, 255, 0.18);
        filter: brightness(1.8);
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
    
    .ticket-table th { 
        background: linear-gradient(135deg, #c41e3a 0%, #8B0000 100%);
        color: white;
        font-weight: 700; 
        padding: 20px;
        text-align: left; 
        vertical-align: middle;
        text-transform: uppercase;
        font-size: 0.9em;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 8px rgba(196, 30, 58, 0.15);
    }
    
    .ticket-table td { 
        padding: 20px; 
        border-bottom: 1px solid #f0f0f0; 
        text-align: left; 
        vertical-align: middle;
        transition: all 0.3s ease;
    }
    
    .ticket-table tr:last-child td { 
        border-bottom: none;
    }
    
    .ticket-table tbody tr:hover {
        background: linear-gradient(135deg, #fff5f7 0%, #ffffff 100%);
        box-shadow: inset 0 4px 12px rgba(196, 30, 58, 0.05);
    }
    
    .ticket-table tr td:first-child {
        font-weight: 700;
        color: #c41e3a;
    }
    
    .ticket-table select { 
        padding: 10px 14px; 
        border: 2px solid #e0e0e0;
        border-radius: 8px; 
        font-size: 0.95em;
        font-weight: 500;
        color: #2c3e50;
        background: #f9f9f9;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
    }
    
    .ticket-table select:focus {
        outline: none;
        border-color: #c41e3a;
        background: white;
        box-shadow: 0 0 0 4px rgba(196, 30, 58, 0.1);
    }
    
    .feedback-text { 
        color: #c41e3a;
        font-style: italic; 
        max-width: 250px; 
        white-space: normal;
        font-weight: 600;
    }
    
    .ticket-table a {
        color: #c41e3a;
        text-decoration: none;
        font-weight: 700;
        transition: all 0.3s ease;
        padding: 6px 12px;
        border-radius: 6px;
    }
    
    .ticket-table a:hover {
        background: linear-gradient(135deg, #c41e3a 0%, #8B0000 100%);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(196, 30, 58, 0.2);
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
        50% { transform: scale(1.15); }
    }
    
    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-3px); }
    }

    /* ===== RESPONSIVE DESIGN ===== */
    @media (max-width: 1024px) {
        .sidebar { width: 240px; }
        .main-content { margin-left: 240px; padding: 30px 30px; }
        .dashboard-grid { grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); }
        .stats-card { padding: 20px; }
        .stats-card .stat-number { font-size: 2em; }
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
        .dashboard-grid { 
            grid-template-columns: 1fr; 
            gap: 15px;
        }
        .stats-card { 
            padding: 15px; 
            text-align: center;
        }
        .stats-card .stat-number { font-size: 1.8em; }
        .stats-card .stat-label { font-size: 0.9em; }
        
        /* Table responsive */
        .tickets-table-container { 
            overflow-x: auto; 
            -webkit-overflow-scrolling: touch;
        }
        .tickets-table { 
            min-width: 800px; 
            font-size: 0.85em;
        }
        .tickets-table th, .tickets-table td { padding: 10px 12px; }
        
        /* Form responsive */
        .assignment-form select { 
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
        .dashboard-grid { gap: 10px; }
        .stats-card { padding: 12px; }
        .stats-card .stat-number { font-size: 1.5em; }
        .tickets-table { font-size: 0.8em; }
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
        <nav class="nav-menu">
            <a href="index.php" class="nav-item"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
            <a href="view_tickets.php" class="nav-item"><i class="fas fa-ticket-alt"></i><span>My Own tickets</span></a>
            <a href="admin_dashboard.php" class="nav-item active"><i class="fas fa-user-shield"></i><span>Admin Dashboard</span></a>
            <?php if (in_array($role, ['admin', 'network/it_team'])): ?><a href="bulk_import.php" class="nav-item"><i class="fas fa-user-plus"></i><span>Add users</span></a><?php endif; ?>
            <a href="notifications.php" class="nav-item"><i class="fas fa-bell"></i><span>notifications</span><?php if($unread_count>0): ?><span class="notif-badge"><?= $unread_count ?></span><?php endif;?></a>
            <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="page-header">
            <h1>Admin Dashboard</h1>
                <div class="button-group">
                <a href="closed_tickets.php" class="btn-view-closed">View Closed tickets</a>
                <?php if (in_array($role, ['admin', 'network/it_team'])): ?>
                <a href="bulk_import.php" class="btn-view-closed btn-primary">Bulk Import Students</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="tickets-container">
            <table class="ticket-table">
                <thead><tr><th>ID</th><th>Title</th><th>Status</th><th>Created By</th><th>Assigned To</th><th>Priority</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= htmlspecialchars($row['ticket_id']) ?></td>
                        <td><strong><?= htmlspecialchars($row['title']) ?></strong><br><small><?= htmlspecialchars($row['category']) ?></small></td>
                        <td><?= htmlspecialchars($row['status']) ?></td>
                        <td><?= htmlspecialchars($row['creator_sap_id']) ?></td>
                        <td>
                             <form method="POST" action="admin_dashboard.php">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="assign_ticket_id" value="<?= $row['ticket_id'] ?>">
                                <select name="assigned_to" onchange="this.form.submit()">
                                    <option value="">--Assign--</option>
                                    <?php foreach ($staff as $s): ?>
                                        <option value="<?= $s['user_id'] ?>" <?php if ($row['assigned_to'] == $s['user_id']) echo 'selected'; ?>>
                                            <?= htmlspecialchars($s['sap_id'] . ' (' . $s['role'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td>
                            <form method="POST" action="admin_dashboard.php">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="priority_ticket_id" value="<?= $row['ticket_id'] ?>">
                                <select name="priority" onchange="this.form.submit()">
                                    <option value="" <?php if (!$row['priority']) echo 'selected';?>>--Priority--</option>
                                    <option value="low" <?php if ($row['priority'] === 'low') echo 'selected'; ?>>Low</option>
                                    <option value="medium" <?php if ($row['priority'] === 'medium') echo 'selected'; ?>>Medium</option>
                                    <option value="high" <?php if ($row['priority'] === 'high') echo 'selected'; ?>>High</option>
                                </select>
                            </form>
                        </td>
                        <td><a href="ticket_details.php?ticket_id=<?= $row['ticket_id'] ?>">View Details</a></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align: center; padding: 50px;">No active tickets found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>