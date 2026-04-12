<?php
include 'db_connect.php';
include 'send_email.php';
include 'nav_helper.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$sap_id = $_SESSION['sap_id'];

// Auto-close tickets older than 50 hours (if not already resolved/closed)
$hours_threshold = 50;
$auto_close_sql = "UPDATE tickets SET status = 'Closed' WHERE status NOT IN ('Resolved', 'Closed', 'Rejected') AND TIMESTAMPDIFF(HOUR, created_at, NOW()) > ?";
$auto_close_stmt = $conn->prepare($auto_close_sql);
$auto_close_stmt->bind_param("i", $hours_threshold);
$auto_close_stmt->execute();
$auto_close_stmt->close();

// Get notification count
$notifq = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$notifq->bind_param("i", $user_id); $notifq->execute(); $notifq->bind_result($unread_count); $notifq->fetch(); $notifq->close();

// Fetch staff members for assignment dropdown
$staff_query = $conn->query("SELECT user_id, sap_id, role FROM users WHERE role IN ('staff', 'maintenance', 'warden', 'rector', 'security', 'house_keeping', 'network/it_team', 'admin') ORDER BY role, sap_id");
$staff_members = $staff_query->fetch_all(MYSQLI_ASSOC);

function old($field) { return isset($_POST[$field]) ? htmlspecialchars($_POST[$field]) : ''; }

// --- Ticket Creation Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        header("Location: index.php?message=Invalid CSRF token");
        exit;
    }
    $title = sanitize_input($_POST['title']);
    $category = sanitize_input($_POST['category']);
    $description = sanitize_input($_POST['description']);
    $location = sanitize_input($_POST['location']);
    $priority = 'medium';
    $status = 'Received';
    $attachment_id = null;
    
    // Get the assigned_to value from form
    $assigned_to = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : NULL;

    if (empty($location)) {
        header("Location: index.php?message=Location cannot be empty");
        exit;
    }

    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['file']['tmp_name'];
        $file_name = basename($_FILES['file']['name']);
        $file_size = $_FILES['file']['size'];
        $file_type = $_FILES['file']['type'];
        $allowed_types = ['image/jpeg', 'image/png', 'video/mp4', 'application/pdf'];
        $max_size = 10 * 1024 * 1024;
        if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $file_path = $upload_dir . time() . '_' . preg_replace('/[^A-Za-z0-9\-\.]/', '_', $file_name);
            if (move_uploaded_file($file_tmp, $file_path)) {
                $sql = "INSERT INTO attachments (file_path, file_type, file_size) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $file_path, $file_type, $file_size);
                if ($stmt->execute()) $attachment_id = $conn->insert_id;
                $stmt->close();
            } else { header("Location: index.php?message=Error uploading file"); exit; }
        } else { header("Location: index.php?message=Invalid file type or size. Max size: 10MB"); exit; }
    }

    // Insert ticket with assigned_to field
    $sql = "INSERT INTO tickets (user_id, title, category, description, priority, location, status, attachment_id, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssssii", $user_id, $title, $category, $description, $priority, $location, $status, $attachment_id, $assigned_to);
    
    if ($stmt->execute()) {
        $ticket_id = $conn->insert_id;
        
        // Record the initial "Received" status in StatusHistory
        $received_status = 'Received';
        $history_stmt = $conn->prepare("INSERT INTO statushistory (ticket_id, status, timestamp) VALUES (?, ?, NOW())");
        $history_stmt->bind_param("is", $ticket_id, $received_status);
        $history_stmt->execute();
        $history_stmt->close();
        
        // Email and notification preparation
        $email_subject = "New Ticket Created: #{$ticket_id}";
        $email_body = "<h2>New Ticket Received</h2><p>A new ticket has been created.</p><p><strong>Ticket ID:</strong> #{$ticket_id}</p><p><strong>Title:</strong> " . htmlspecialchars($title) . "</p><p><strong>Category:</strong> " . htmlspecialchars($category) . "</p><p><strong>Location:</strong> " . htmlspecialchars($location) . "</p><p>Please log in to the dashboard to view details.</p>";
        
        // Notify admins
        $admin_query = $conn->query("SELECT user_id, email FROM users WHERE role = 'admin'");
        $msg = "New ticket #$ticket_id created by user $sap_id.";
        while ($admin = $admin_query->fetch_assoc()) {
            $notif_sql = $conn->prepare("INSERT INTO notifications (user_id, ticket_id, message) VALUES (?, ?, ?)");
            $notif_sql->bind_param("iis", $admin['user_id'], $ticket_id, $msg);
            $notif_sql->execute();
            $notif_sql->close();
            sendTicketEmail($admin['email'], $email_subject, $email_body);
        }
        
        // If assigned to someone, notify them
        if ($assigned_to) {
            $assigned_user_query = $conn->prepare("SELECT email, sap_id FROM users WHERE user_id = ?");
            $assigned_user_query->bind_param("i", $assigned_to);
            $assigned_user_query->execute();
            $assigned_user_result = $assigned_user_query->get_result();
            if ($assigned_user = $assigned_user_result->fetch_assoc()) {
                $assigned_msg = "Ticket #$ticket_id has been assigned to you by user $sap_id.";
                $assigned_email_subject = "Ticket Assigned: #{$ticket_id}";
                $assigned_email_body = "<h2>Ticket Assigned to You</h2><p>A ticket has been assigned to you.</p><p><strong>Ticket ID:</strong> #{$ticket_id}</p><p><strong>Title:</strong> " . htmlspecialchars($title) . "</p><p><strong>Category:</strong> " . htmlspecialchars($category) . "</p><p><strong>Description:</strong> " . htmlspecialchars($description) . "</p><p>Please log in to view and manage this ticket.</p>";
                
                $notif_assigned = $conn->prepare("INSERT INTO notifications (user_id, ticket_id, message) VALUES (?, ?, ?)");
                $notif_assigned->bind_param("iis", $assigned_to, $ticket_id, $assigned_msg);
                $notif_assigned->execute();
                $notif_assigned->close();
                
                sendTicketEmail($assigned_user['email'], $assigned_email_subject, $assigned_email_body);
            }
            $assigned_user_query->close();
        }
        
        $stmt->close();
        header("Location: index.php?message=Ticket created successfully" . ($assigned_to ? " and assigned" : ""));
        exit;
    } else {
        header("Location: index.php?message=Error creating ticket: " . $conn->error);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Dashboard - NMIMS Issue Tracker</title>
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
    
    .ticket-form-container { 
        background: white; 
        padding: 50px; 
        max-width: 800px; 
        margin: 0 auto; 
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        animation: fadeInUp 0.7s ease-out 0.2s both;
    }
    
    .ticket-form-container h2 { 
        text-align: center; 
        margin-bottom: 40px; 
        color: #c41e3a; 
        font-weight: 700;
        font-size: 2em;
        letter-spacing: -0.5px;
    }
    
    /* ===== FORM GROUPS ===== */
    .form-group { 
        margin-bottom: 25px;
    }
    
    .form-group label { 
        display: block; 
        margin-bottom: 10px; 
        color: #c41e3a; 
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.9em;
        letter-spacing: 0.5px;
    }
    
    .form-group input, 
    .form-group select, 
    .form-group textarea {
        width: 100%;
        padding: 14px 16px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 1em;
        font-family: 'Segoe UI', sans-serif;
        box-sizing: border-box;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        color: #2c3e50;
        font-weight: 500;
    }
    
    .form-group textarea { 
        min-height: 140px; 
        resize: vertical;
        line-height: 1.6;
    }
    
    .form-group input:focus, 
    .form-group select:focus, 
    .form-group textarea:focus {
        border-color: #c41e3a;
        outline: none;
        box-shadow: 0 0 0 4px rgba(196, 30, 58, 0.1);
    }
    
    .form-group input::placeholder,
    .form-group textarea::placeholder {
        color: #999;
        opacity: 0.7;
    }

    /* ===== UPLOAD INFO ===== */
    .upload-info { 
        font-size: 0.9em; 
        color: #666; 
        margin-top: 8px;
        font-weight: 500;
    }
    
    .char-count { 
        font-size: 0.85em; 
        color: #c41e3a;
        text-align: right; 
        margin-top: 8px;
        font-weight: 600;
    }
    
    /* ===== FORM ACTIONS ===== */
    .form-actions { 
        text-align: center; 
        margin-top: 35px;
    }
    
    .btn {
        background: linear-gradient(135deg, #c41e3a 0%, #8B0000 100%);
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 14px 40px;
        font-size: 1.1em;
        font-weight: 700;
        cursor: pointer;
        width: 100%;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 6px 20px rgba(196, 30, 58, 0.25);
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .btn:hover { 
        background: linear-gradient(135deg, #8B0000 0%, #6B0000 100%);
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(196, 30, 58, 0.35);
    }
    
    .btn:active {
        transform: translateY(-1px);
    }

    /* ===== MESSAGES ===== */
    .message { 
        padding: 18px 25px; 
        border-radius: 8px; 
        margin-bottom: 30px; 
        text-align: center;
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
        .ticket-form-container { padding: 30px; max-width: 700px; }
        .ticket-form-container h2 { font-size: 1.8em; }
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
            padding: 20px 15px;
        }
        .ticket-form-container { 
            padding: 20px; 
            max-width: 100%;
            margin: 0;
        }
        .ticket-form-container h2 { 
            font-size: 1.5em; 
            margin-bottom: 30px;
        }
        .form-group label { font-size: 0.85em; }
        .form-group input, 
        .form-group select, 
        .form-group textarea {
            padding: 12px;
            font-size: 16px; /* Prevents zoom on iOS */
        }
        .form-actions .btn { 
            width: 100%; 
            padding: 15px;
            font-size: 1.1em;
        }
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
        .main-content { padding: 15px 10px; }
        .ticket-form-container { padding: 15px; }
        .ticket-form-container h2 { font-size: 1.3em; }
        .form-group { margin-bottom: 20px; }
        .nav-item { 
            min-width: 80px; 
            padding: 8px 10px;
        }
        .nav-item i { font-size: 1.4em; }
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
        <h4><?= htmlspecialchars($role) ?></h4>
        <p><?= htmlspecialchars($sap_id) ?></p>
    </div>
    <nav class="nav-menu" role="navigation" aria-label="Main navigation">
        <a href="index.php" class="nav-item active" aria-label="Go to Dashboard" aria-current="page"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
        <a href="view_tickets.php" class="nav-item" aria-label="View My Own Tickets"><i class="fas fa-ticket-alt"></i><span>My Own Tickets</span></a>
        <?php if (strpos($role, 'sub_') === 0): ?>
        <a href="sub_staff_dashboard.php" class="nav-item" aria-label="View My Tasks"><i class="fas fa-tasks"></i><span>My Tasks</span></a>
        <?php elseif (in_array($role, ['staff', 'maintenance', 'warden', 'security', 'house_keeping'])): ?>
        <a href="staff_tickets_with_sub.php" class="nav-item" aria-label="Manage Assigned Tickets"><i class="fas fa-tasks"></i><span>Assigned Tickets</span></a>
        <?php elseif (in_array($role, ['rector', 'network/it_team'])): ?>
        <a href="staff_tickets.php" class="nav-item" aria-label="View Assigned Tickets"><i class="fas fa-tasks"></i><span>Assigned Tickets</span></a>
        <?php endif; ?>
        <?php if (in_array($role,['admin','super_visor'])): ?>
        <a href="admin_dashboard.php" class="nav-item" aria-label="Access Admin Dashboard"><i class="fas fa-user-shield"></i><span>Admin Dashboard</span></a>
        <?php endif; ?>
        <?php if ($role === 'admin'): ?>
        <a href="bulk_import.php" class="nav-item" aria-label="Bulk Import Users"><i class="fas fa-user-plus"></i><span>Add Users</span></a>
        <?php endif; ?>
        <a href="notifications.php" class="nav-item" aria-label="View Notifications<?php if($unread_count>0): ?> (<?= $unread_count ?> unread)<?php endif; ?>"><i class="fas fa-bell"></i><span>Notifications</span><?php if($unread_count>0): ?><span class="notif-badge" aria-label="<?= $unread_count ?> unread notifications"><?= $unread_count ?></span><?php endif;?></a>
        <a href="logout.php" class="nav-item" aria-label="Logout from system"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
    </nav>
</div>
<div class="main-content">
    <div class="ticket-form-container">
        <h2>Create New Ticket</h2>
        <?php if (isset($_GET['message'])): ?>
            <div class="message <?= strpos($_GET['message'], 'Error') !== false ? 'error-message' : 'success-message' ?>">
                <?= htmlspecialchars($_GET['message']) ?>
            </div>
        <?php endif; ?>
        <form action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="form-group"><label for="title">Issue Title</label><input type="text" name="title" id="title" required value="<?= old('title') ?>" placeholder="e.g., Wi-Fi not working in library"></div>
            <div class="form-group"><label for="category">Category</label>
                <select name="category" id="category" required>
                    <option value="">--Select Category--</option>
                    <option value="infrastructure">Infrastructure</option>
                    <option value="hygiene">Hygiene</option>
                    <option value="security">Security</option>
                    <option value="hostel">Hostel</option>
                    <option value="other">Other</option>
                </select></div>
            <div class="form-group"><label for="location">Location</label>
                <input type="text" name="location" id="location" required value="<?= old('location') ?>" placeholder="e.g., ACD block, 2nd floor, near canteen">
            </div>
            <div class="form-group"><label for="description">Description</label>
                <textarea name="description" id="description" required maxlength="1000" oninput="updateCharCount(this)" placeholder="Provide a detailed description of the issue..."><?= old('description') ?></textarea>
                <div id="charCount" class="char-count">1000 characters remaining</div>
            </div>
            
            <!-- NEW: Assign To Dropdown -->
            <div class="form-group">
                <label for="assigned_to">Assign To (Optional)</label>
                <select name="assigned_to" id="assigned_to">
                    <option value="">--Auto-Assign to Admin--</option>
                    <?php foreach ($staff_members as $staff): ?>
                        <option value="<?= $staff['user_id'] ?>">
                            <?= htmlspecialchars($staff['sap_id'] . ' (' . ucfirst(str_replace('_', ' ', $staff['role'])) . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group"><label for="file">Attachment (Optional)</label>
                <input type="file" name="file" id="file" accept="image/jpeg,image/png,video/mp4,application/pdf">
                <div class="upload-info">Supported: JPG, PNG, MP4, PDF &bull; Max 10MB</div>
            </div>
            <div class="form-actions"><button type="submit" class="btn">Submit Ticket</button></div>
        </form>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loading-overlay" class="loading-overlay" style="display: none;">
    <div style="text-align: center;">
        <div class="loading-spinner"></div>
        <div class="loading-text">Creating your ticket...</div>
    </div>
</div>

<script>
    function updateCharCount(textarea) {
        const maxLength = textarea.maxLength;
        const currentLength = textarea.value.length;
        const remaining = maxLength - currentLength;
        document.getElementById('charCount').textContent = remaining + ' characters remaining';
    }
    document.addEventListener('DOMContentLoaded', function() {
        updateCharCount(document.getElementById('description'));
        
        // Add loading animation for form submission
        const form = document.querySelector('form');
        const loadingOverlay = document.getElementById('loading-overlay');
        
        form.addEventListener('submit', function(e) {
            // Show loading overlay
            loadingOverlay.style.display = 'flex';
            
            // Disable the submit button to prevent double submission
            const submitBtn = document.querySelector('.btn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
            
            // The form will submit normally, loading will show until redirect
        });
    });
</script>
</body>
</html>