<?php
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Helper for old form value in case of failure
function old($name) {
    return isset($_POST[$name]) ? htmlspecialchars($_POST[$name]) : '';
}

// --- TICKET CREATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        header("Location: index.php?message=Invalid CSRF token");
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $title = sanitize_input($_POST['title']);
    $category = sanitize_input($_POST['category']);
    $description = sanitize_input($_POST['description']);
    $priority = sanitize_input($_POST['priority']);
    $location = sanitize_input($_POST['location']);
    $status = 'Received';
    $sap_id = $_SESSION['sap_id'];

    // Validate location
    if (empty($location) || $location === '0') {
        header("Location: create_ticket.php?message=Location cannot be empty or zero");
        exit;
    }

    // --- File Handling ---
    $attachment_id = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['file']['tmp_name'];
        $file_name = basename($_FILES['file']['name']);
        $file_size = $_FILES['file']['size'];
        $file_type = $_FILES['file']['type'];
        $allowed_types = ['image/jpeg', 'image/png', 'video/mp4', 'application/pdf'];
        $max_size = 10 * 1024 * 1024; // 10MB
        if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
            $upload_dir = 'Uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $file_path = $upload_dir . time() . '_' . preg_replace('/[^A-Za-z0-9\-.]/', '_', $file_name);
            if (move_uploaded_file($file_tmp, $file_path)) {
                $sql = "INSERT INTO attachments (file_path, file_type, file_size) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if ($stmt === false) die("Prepare failed: " . $conn->error);
                $stmt->bind_param("ssi", $file_path, $file_type, $file_size);
                if ($stmt->execute()) {
                    $attachment_id = $conn->insert_id;
                }
                $stmt->close();
            } else {
                header("Location: create_ticket.php?message=Error uploading file");
                exit;
            }
        } else {
            header("Location: create_ticket.php?message=Invalid file type or size. Max size: 10MB");
            exit;
        }
    }

    $sql = "INSERT INTO tickets (user_id, title, category, description, priority, location, status, attachment_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) die("Prepare failed: " . $conn->error);
    $stmt->bind_param("issssisi", $user_id, $title, $category, $description, $priority, $location, $status, $attachment_id);

    if ($stmt->execute()) {
        $ticket_id = $conn->insert_id;

        // Record the initial "Received" status in StatusHistory
        $received_status = 'Received';
        $history_stmt = $conn->prepare("INSERT INTO statushistory (ticket_id, status, timestamp) VALUES (?, ?, NOW())");
        $history_stmt->bind_param("is", $ticket_id, $received_status);
        $history_stmt->execute();
        $history_stmt->close();

        // ---- Notify all admins ----
        $admin_query = $conn->query("SELECT user_id FROM users WHERE role = 'admin'");
        $msg = "New ticket #$ticket_id created by user $sap_id.";
        while ($admin = $admin_query->fetch_assoc()) {
            $notify_admin_id = $admin['user_id'];
            $notif_sql = $conn->prepare("INSERT INTO notifications (user_id, ticket_id, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
            $notif_sql->bind_param("iis", $notify_admin_id, $ticket_id, $msg);
            $notif_sql->execute();
            $notif_sql->close();
        }
        // ---- End notify ----

        $stmt->close();
        header("Location: create_ticket.php?message=Ticket created successfully");
        exit;
    } else {
        header("Location: create_ticket.php?message=Error creating ticket: " . $conn->error);
        exit;
    }
}

// CSRF token is set in db_connect.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Ticket - NMIMS Issue Tracker</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f6f9; margin: 0; }
        .container { max-width: 480px; margin: 70px auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding:40px 32px; }
        h2 { text-align20px; margin-bottom: 24px; color: #2c3e50;}
        .form-group { margin-bottom: 18px;}
        .form-group label { display: block; margin-bottom: 7px; color: #333; font-weight: 500;}
        .form-group input, .form-group select, .form-group textarea {
            width:100%; padding: 12px; border: 2px solid #dce0e0; border-radius: 7px; font-size: 1em;
        }
        .form-group textarea { min-height: 80px;}
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #3498db; outline: none; }
        .form-actions { text-align: center; margin-top: 30px;}
        .btn { background: #3498db; color: #fff; border: none; border-radius: 8px; padding: 12px 30px; font-size: 1em; font-weight: 600; cursor: pointer;}
        .btn:hover { background: #2980b9;}
        .upload-info {font-size: .95em; color:#999; margin-top:3px;}
        .error-message { color: #e74c3c; text-align: center; margin-bottom: 20px; }
        .success-message { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px; text-align: center; }

        /* ===== RESPONSIVE DESIGN ===== */
        @media (max-width: 768px) {
            .container { 
                width: 95%; 
                max-width: 500px; 
                padding: 20px;
            }
            h2 { font-size: 1.5em; }
            .form-group input, 
            .form-group select, 
            .form-group textarea {
                font-size: 16px; /* Prevents zoom on iOS */
                padding: 12px;
            }
            .btn { 
                width: 100%; 
                padding: 15px;
                font-size: 1.1em;
            }
        }

        @media (max-width: 480px) {
            .container { 
                width: 100%; 
                padding: 15px;
            }
            h2 { font-size: 1.3em; }
            .form-group { margin-bottom: 15px; }
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Create New Ticket</h2>
    <?php if (isset($_GET['message'])): ?>
        <p class="<?php echo strpos($_GET['message'], 'Error') !== false || strpos($_GET['message'], 'Invalid') !== false ? 'error-message' : 'success-message'; ?>">
            <?php echo htmlspecialchars($_GET['message']); ?>
        </p>
    <?php endif; ?>
    <form action="" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <div class="form-group">
            <label for="title">Issue Title</label>
            <input type="text" name="title" id="title" required value="<?= old('title') ?>">
        </div>
        <div class="form-group">
            <label for="category">Category</label>
            <select name="category" id="category" required>
                <option value="">--Select--</option>
                <option value="infrastructure">Infrastructure</option>
                <option value="hygiene">Hygiene</option>
                <option value="security">Security</option>
                <option value="hostel">Hostel</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div class="form-group">
            <label for="priority">Priority</label>
            <select name="priority" id="priority" required>
                <option value="">--Select--</option>
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
            </select>
        </div>
        <div class="form-group">
            <label for="location">Location</label>
            <input type="text" name="location" id="location" required value="<?= old('location') ?>">
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea name="description" id="description" required><?= old('description') ?></textarea>
        </div>
        <div class="form-group">
            <label for="file">Attachment (optional)</label>
            <input type="file" name="file" id="file" accept="image/jpeg,image/png,video/mp4,application/pdf">
            <div class="upload-info">Supported: JPEG, PNG, MP4, PDF • Max 10MB</div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Submit Ticket</button>
        </div>
    </form>
</div>

<!-- Loading Overlay -->
<div id="loading-overlay" class="loading-overlay" style="display: none;">
    <div style="text-align: center;">
        <div class="loading-spinner"></div>
        <div class="loading-text">Creating your ticket...</div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
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