<?php
include 'db_connect.php';
$allowed_dashboard_roles = ['admin', 'super_visor'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_dashboard_roles)) {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$sap_id = $_SESSION['sap_id'] ?? 'Unknown';

// Auto-close tickets older than 50 hours (if not already resolved/closed)
$hours_threshold = 50;
$auto_close_sql = "UPDATE tickets SET status = 'Closed' WHERE status NOT IN ('Resolved', 'Closed', 'Rejected') AND TIMESTAMPDIFF(HOUR, created_at, NOW()) > ?";
$auto_close_stmt = $conn->prepare($auto_close_sql);
$auto_close_stmt->bind_param("i", $hours_threshold);
$auto_close_stmt->execute();
$auto_close_stmt->close();

// Get notification count
$notifq = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$notifq->bind_param("i", $user_id);
$notifq->execute();
$notifq->bind_result($unread_count);
$notifq->fetch();
$notifq->close();

// Fetch ONLY closed tickets with their latest feedback and rating
$sql = "SELECT t.ticket_id, t.title, t.category, t.status, u.sap_id as creator_sap_id,
        (SELECT feedback_text FROM feedback WHERE ticket_id = t.ticket_id AND feedback_text IS NOT NULL ORDER BY created_at DESC LIMIT 1) AS feedback_text,
        (SELECT rating FROM feedback WHERE ticket_id = t.ticket_id AND rating IS NOT NULL ORDER BY created_at DESC LIMIT 1) AS rating
        FROM tickets t
        LEFT JOIN users u ON t.user_id = u.user_id
        WHERE t.status = 'Closed'
        ORDER BY t.created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Closed Tickets - NMIMS Issue Tracker</title>
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
        
        /* ===== SIDEBAR STYLING ===== */
        .sidebar { 
            width: 260px; 
            background: linear-gradient(180deg, #c41e3a 0%, #8B0000 100%);
            min-height: 100vh; 
            color: white; 
            position: fixed; 
            box-sizing: border-box;
            box-shadow: 4px 0 20px rgba(196, 30, 58, 0.3);
            z-index: 100;
            display: flex;
            flex-direction: column;
            padding: 0;
            animation: slideInLeft 0.6s ease-out;
        }
        
        .logo-container { 
            padding: 25px 20px; 
            text-align: center; 
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
            animation: slideDown 0.6s ease-out 0.1s both;
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
        
        .logo-container img:hover { 
            transform: scale(1.05); 
        }
        
        .user-profile { 
            padding: 25px 20px; 
            text-align: center; 
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
            animation: slideDown 0.6s ease-out 0.2s both;
        }
        
        .user-profile img { 
            width: 60px; 
            height: 60px; 
            border-radius: 50%; 
            margin-bottom: 12px; 
            border: 3px solid rgba(255, 255, 255, 0.4);
            transition: all 0.3s ease;
        }
        
        .user-profile img:hover {
            transform: scale(1.1);
            border-color: #ffffff;
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
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 40px; 
            gap: 15px;
            animation: slideDown 0.6s ease-out 0.1s both;
        }
        
        .page-header h1 { 
            color: #c41e3a;
            margin: 0; 
            font-size: 2.2em;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        
        .header-actions { 
            display: flex; 
            gap: 15px;
            animation: slideDown 0.6s ease-out 0.15s both;
        }
        
        .header-btn { 
            padding: 12px 28px; 
            color: white; 
            text-decoration: none; 
            border-radius: 8px; 
            font-weight: 700;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .back-btn { 
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
        }
        
        .back-btn:hover { 
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(108, 117, 125, 0.3);
        }
        
        .download-btn { 
            background: linear-gradient(135deg, #c41e3a 0%, #8B0000 100%);
        }
        
        .download-btn:hover { 
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(196, 30, 58, 0.3);
        }
        
        /* ===== TICKET TABLE ===== */
        .ticket-table { 
            width: 100%; 
            border-collapse: collapse; 
            background: white; 
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            overflow: hidden;
            animation: fadeInUp 0.7s ease-out 0.2s both;
        }
        
        .ticket-table th { 
            background: linear-gradient(135deg, #c41e3a 0%, #8B0000 100%);
            color: white;
            padding: 20px;
            text-align: left;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9em;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 8px rgba(196, 30, 58, 0.15);
        }
        
        .ticket-table td { 
            padding: 18px 20px; 
            border-bottom: 1px solid #f0f0f0; 
            text-align: left;
            transition: all 0.3s ease;
            color: #2c3e50;
        }
        
        .ticket-table tbody tr:hover {
            background: linear-gradient(135deg, #fff5f7 0%, #ffffff 100%);
            box-shadow: inset 0 4px 12px rgba(196, 30, 58, 0.05);
        }
        
        .ticket-table tr:last-child td { 
            border-bottom: none;
        }
        
        .ticket-table td:first-child {
            font-weight: 700;
            color: #c41e3a;
        }
        
        .status-closed { 
            color: white;
            background: linear-gradient(135deg, #8B0000 0%, #600a0a 100%);
            padding: 8px 16px; 
            border-radius: 8px;
            font-weight: 700; 
            font-size: 0.9em;
            display: inline-block;
            box-shadow: 0 4px 12px rgba(139, 0, 0, 0.2);
            letter-spacing: 0.5px;
        }
        
        .rating-stars { 
            color: #ffc107; 
            font-size: 1.3em;
            font-weight: 700;
            letter-spacing: 2px;
        }
        
        /* ===== NO TICKETS MESSAGE ===== */
        .main-content > p {
            background: white;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            color: #999;
            font-size: 1.1em;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            animation: fadeInUp 0.7s ease-out 0.2s both;
        }
        
        /* ===== ANIMATIONS ===== */
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
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
    </style>
</head>
<body>
    <div class="sidebar">
        </div>
    <div class="main-content">
        <div class="page-header">
            <h1>Closed Tickets</h1>
            <div class="header-actions">
                <a href="admin_dashboard.php" class="header-btn back-btn">Back to Dashboard</a>
                <a href="download_closed_tickets.php" class="header-btn download-btn">Download as Excel</a>
            </div>
        </div>
        <?php if ($result && $result->num_rows > 0): ?>
            <table class="ticket-table">
                <thead>
                    <tr>
                        <th>Ticket ID</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Feedback</th>
                        <th>Rating</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['ticket_id']) ?></td>
                            <td><?= htmlspecialchars($row['title']) ?></td>
                            <td><span class="status-closed"><?= htmlspecialchars($row['status']) ?></span></td>
                            <td><?= htmlspecialchars($row['creator_sap_id']) ?></td>
                            <td><?= htmlspecialchars($row['feedback_text']) ?></td>
                            <td class="rating-stars">
                                <?php if (!empty($row['rating'])): ?>
                                    <?php for ($i = 0; $i < $row['rating']; $i++): echo '★'; endfor; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No closed tickets found.</p>
        <?php endif; ?>
    </div>
</body>
</html>