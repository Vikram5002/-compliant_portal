<?php
// Your existing PHP code for this page goes here.
// No changes are needed in the PHP logic.
// Just copy all the PHP code from your original notifications.php file and paste it here.
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Auto-close tickets older than 50 hours (if not already resolved/closed)
$hours_threshold = 50;
$auto_close_sql = "UPDATE tickets SET status = 'Closed' WHERE status NOT IN ('Resolved', 'Closed', 'Rejected') AND TIMESTAMPDIFF(HOUR, created_at, NOW()) > ?";
$auto_close_stmt = $conn->prepare($auto_close_sql);
$auto_close_stmt->bind_param("i", $hours_threshold);
$auto_close_stmt->execute();
$auto_close_stmt->close();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$sap_id = $_SESSION['sap_id'] ?? 'Unknown';

$message = "";

// Mark one as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_id'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = "Invalid CSRF token.";
    } else {
        $notification_id = intval($_POST['notification_id']);
        $sql = "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $notification_id, $user_id);
        $stmt->execute();
        $stmt->close();
        $message = "Notification marked as read.";
        header("Location: notifications.php?message=" . urlencode($message));
        exit;
    }
}

// Mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = "Invalid CSRF token.";
    } else {
        $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute(); $stmt->close();
        $message = "All notifications marked as read.";
        header("Location: notifications.php?message=" . urlencode($message));
        exit;
    }
}

// Fetch only unread notifications
$sql = "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC";
$stmt = $conn->prepare($sql); 
$stmt->bind_param("i", $user_id); 
$stmt->execute(); 
$result = $stmt->get_result();

// Get unread count for sidebar badge
$notifq = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$notifq->bind_param("i", $user_id); 
$notifq->execute(); 
$notifq->bind_result($unread_count); 
$notifq->fetch(); 
$notifq->close();

if (isset($_GET['message']) && !$message) $message = $_GET['message'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Notifications - NMIMS Issue Tracker</title>
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

    /* ===== MESSAGES ===== */
    .message { 
        padding: 18px 25px; 
        border-radius: 8px; 
        margin-bottom: 25px; 
        font-weight: 600;
        animation: slideDown 0.5s ease-out;
        border-left: 4px solid #28a745;
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.15);
    }
    
    /* ===== NOTIFICATIONS CONTAINER ===== */
    .notifications-container { 
        background: white; 
        border-radius: 12px; 
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        animation: fadeInUp 0.7s ease-out 0.2s both;
        overflow: hidden;
    }
    
    .notifications-header { 
        padding: 25px; 
        border-bottom: 2px solid #f0f0f0;
        background: linear-gradient(135deg, #c41e3a 0%, #8B0000 100%);
    }
    
    .btn-mark-all { 
        background: linear-gradient(135deg, #ffffff 0%, #f0f0f0 100%);
        color: #c41e3a; 
        border: 2px solid #c41e3a;
        padding: 12px 28px; 
        border-radius: 8px; 
        font-weight: 700;
        cursor: pointer; 
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 12px rgba(196, 30, 58, 0.1);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 0.95em;
        letter-spacing: 0.5px;
    }
    
    .btn-mark-all:hover { 
        background: #c41e3a;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(196, 30, 58, 0.3);
    }
    
    /* ===== NOTIFICATION LIST & ITEMS ===== */
    .notification-list { 
        list-style: none; 
        padding: 0; 
        margin: 0;
    }
    
    .notification-item { 
        display: flex; 
        align-items: center; 
        gap: 20px; 
        padding: 25px; 
        border-bottom: 1px solid #e0e0e0;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .notification-item:last-child { 
        border-bottom: none;
    }
    
    .notification-item:hover {
        background: linear-gradient(135deg, #fff5f7 0%, #ffffff 100%);
        box-shadow: inset 0 4px 12px rgba(196, 30, 58, 0.05);
    }
    
    .notification-item .icon { 
        font-size: 1.8em; 
        color: #c41e3a;
        animation: pulse 2s infinite;
    }
    
    .notification-item .content { 
        flex: 1;
    }
    
    .notification-item .content .message { 
        font-size: 1.05em; 
        color: #2c3e50;
        margin: 0 0 8px 0;
        font-weight: 600;
        line-height: 1.5;
    }
    
    .notification-item .content .timestamp { 
        font-size: 0.9em; 
        color: #999;
        font-weight: 500;
    }
    
    .notification-item .action { 
        margin-left: auto;
    }
    
    .btn-mark-read { 
        background: linear-gradient(135deg, #c41e3a 0%, #8B0000 100%);
        color: white;
        border: none; 
        cursor: pointer; 
        font-weight: 700; 
        font-size: 1em;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 12px rgba(196, 30, 58, 0.2);
    }
    
    .btn-mark-read:hover {
        transform: translateY(-2px) scale(1.1);
        box-shadow: 0 6px 20px rgba(196, 30, 58, 0.35);
    }
    
    /* ===== NO NOTIFICATIONS STATE ===== */
    .no-notifications { 
        text-align: center; 
        color: #999; 
        padding: 80px 40px;
    }
    
    .no-notifications i { 
        font-size: 4em; 
        display: block; 
        margin-bottom: 20px; 
        color: #ddd;
        animation: fadeIn 0.8s ease-out;
    }
    
    .no-notifications p {
        font-size: 1.2em;
        color: #666;
        font-weight: 500;
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
        .notifications-container { padding: 25px; }
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
        .notifications-container { 
            padding: 15px; 
            margin-bottom: 20px;
        }
        .notification-item { 
            padding: 15px; 
            margin-bottom: 15px;
            font-size: 0.95em;
        }
        .notification-time { font-size: 0.85em; }
        
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
        .notifications-container { padding: 12px; }
        .notification-item { 
            padding: 12px; 
            font-size: 0.9em;
        }
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
        <?php if (in_array($role,['admin','super_visor'])): ?><a href="admin_dashboard.php" class="nav-item" aria-label="Access Admin Dashboard"><i class="fas fa-user-shield"></i><span>Admin Dashboard</span></a><?php endif;?>
        <?php if (in_array($role,['admin','network/it_team'])): ?><a href="bulk_import.php" class="nav-item" aria-label="Bulk Import Users"><i class="fas fa-user-plus"></i><span>Add Users</span></a><?php endif;?>
        <a href="notifications.php" class="nav-item active" aria-label="View Notifications<?php if($unread_count > 0): ?> (<?= $unread_count ?> unread)<?php endif; ?>" aria-current="page"><i class="fas fa-bell"></i><span>Notifications</span><?php if($unread_count > 0): ?><span class="notif-badge" aria-label="<?= $unread_count ?> unread notifications"><?= $unread_count ?></span><?php endif;?></a>
        <a href="logout.php" class="nav-item" aria-label="Logout from system"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
    </nav>
</div>

<div class="main-content">
    <div class="page-header">
        <h1>Notifications</h1>
    </div>
    
    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="notifications-container">
        <?php if ($result->num_rows > 0): ?>
            <div class="notifications-header">
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                    <button class="btn-mark-all" type="submit" name="mark_all" value="1"><i class="fas fa-check-double"></i> Mark All as Read</button>
                </form>
            </div>
            <ul class="notification-list">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <li class="notification-item">
                        <div class="icon"><i class="fas fa-info-circle"></i></div>
                        <div class="content">
                            <p class="message"><?= htmlspecialchars($row['message']); ?></p>
                            <span class="timestamp"><?= htmlspecialchars(date('d M Y, h:i A', strtotime($row['created_at']))); ?></span>
                        </div>
                        <div class="action">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="notification_id" value="<?= $row['notification_id']; ?>">
                                <button type="submit" class="btn-mark-read" title="Mark as Read"><i class="fas fa-check"></i></button>
                            </form>
                        </div>
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <div class="no-notifications">
                <i class="fas fa-bell-slash"></i>
                <p>No new notifications.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>