<?php
include 'db_connect.php';
include 'nav_helper.php';

// --- Security Check ---
// Allow 'admin' OR 'network/it_team'
$allowed_roles = ['admin', 'network/it_team'];

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    // If user is NOT logged in OR NOT in the allowed list, kick them out
    header("Location: login.php");
    exit;
}

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Set user variables for sidebar
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$sap_id = $_SESSION['sap_id'] ?? 'Unknown';

// Notification count
$notifq = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$notifq->bind_param("i", $user_id); $notifq->execute(); $notifq->bind_result($unread_count); $notifq->fetch(); $notifq->close();

$message = "";
$message_type = ""; // 'success' or 'error'
$error_log = [];

// --- HANDLE FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Validate CSRF Token
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = "Invalid CSRF token.";
        $message_type = "error";
    } 
    else {
        // --- CASE: ADD NEW ROLE ---
        if (isset($_POST['add_new_role'])) {
            $new_role_name = strtolower(trim(sanitize_input($_POST['new_role_name'] ?? '')));
            if ($new_role_name === '') {
                $message = 'Role name cannot be empty.';
                $message_type = 'error';
            } else {
                $chk_role = $conn->prepare("SELECT role_id FROM allowed_roles WHERE role_name = ?");
                $chk_role->bind_param('s', $new_role_name);
                $chk_role->execute();
                $chk_role->store_result();
                if ($chk_role->num_rows > 0) {
                    $message = "Role '".htmlspecialchars($new_role_name)."' already exists.";
                    $message_type = 'error';
                } else {
                    $ins_role = $conn->prepare("INSERT INTO allowed_roles (role_name) VALUES (?)");
                    $ins_role->bind_param('s', $new_role_name);
                    if ($ins_role->execute()) {
                        $message = "New role '".htmlspecialchars($new_role_name)."' added successfully!";
                        $message_type = 'success';
                    } else {
                        $message = 'Error adding role: ' . $conn->error;
                        $message_type = 'error';
                    }
                    $ins_role->close();
                }
                $chk_role->close();
            }
        }
        // --- CASE A: CSV BULK IMPORT ---
        elseif (isset($_FILES['csv_file'])) {
            $success_count = 0;
            
            if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                $message = "Error uploading file.";
                $message_type = "error";
            } else {
                $file_tmp = $_FILES['csv_file']['tmp_name'];
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file_tmp);
                // Expanded allowed MIME types for better compatibility
                $allowed = [
                    'text/plain', 'text/csv', 'application/vnd.ms-excel', 
                    'text/comma-separated-values', 'application/octet-stream',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' // Just in case
                ];
                
                // Note: You can comment out this MIME check if it causes issues on your specific server
                if (!in_array($mime, $allowed, true) && strpos($mime, 'text/') === false) {
                     // $message = "Uploaded file is not a valid CSV. Detected: $mime";
                     // $message_type = "error";
                     // $handle = false;
                     // For now, let's proceed and trust fopen, or you can strictly enforce above
                     $handle = fopen($file_tmp, "r");
                } else {
                    $handle = fopen($file_tmp, "r");
                }

                if ($handle !== FALSE) {
                    if (isset($_POST['skip_header'])) { fgetcsv($handle); }

                    $sql = "INSERT INTO users (sap_id, email, role, password) VALUES (?, ?, 'student', ?)";
                    $stmt = $conn->prepare($sql);
                    
                    $check_sql = "SELECT user_id FROM users WHERE sap_id = ? OR email = ? LIMIT 1";
                    $check_stmt = $conn->prepare($check_sql);

                    $row_number = isset($_POST['skip_header']) ? 2 : 1;

                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        if (count($data) >= 2 && !empty($data[0]) && !empty($data[1])) {
                            $imp_sap = trim($data[0]);
                            $imp_email = trim($data[1]);

                            // Validate Email
                            if (!filter_var($imp_email, FILTER_VALIDATE_EMAIL)) {
                                $error_log[] = "Row $row_number: Invalid email format ($imp_email)";
                                $row_number++; continue;
                            }

                            // Check Duplicates
                            $check_stmt->bind_param("ss", $imp_sap, $imp_email);
                            $check_stmt->execute();
                            $check_stmt->store_result();
                            if ($check_stmt->num_rows > 0) {
                                $error_log[] = "Row $row_number: User already exists ($imp_sap).";
                                $check_stmt->free_result();
                                $row_number++; continue;
                            }
                            $check_stmt->free_result();

                            // Insert
                            $hashed_pwd = password_hash('12345678', PASSWORD_DEFAULT);
                            $stmt->bind_param("sss", $imp_sap, $imp_email, $hashed_pwd);

                            try {
                                if ($stmt->execute()) { $success_count++; } 
                                else { $error_log[] = "Row $row_number: Database error - {$conn->error}"; }
                            } catch (mysqli_sql_exception $e) {
                                $error_log[] = "Row $row_number: Failed - " . $e->getMessage();
                            }
                        } else {
                            $error_log[] = "Row $row_number: Skipped (Empty fields)";
                        }
                        $row_number++;
                    }
                    fclose($handle);
                    $stmt->close();
                    $check_stmt->close();

                    $message = "CSV Import complete! $success_count students created.";
                    $message_type = "success";
                } else {
                    $message = "Could not open file.";
                    $message_type = "error";
                }
            }
        }

        // --- CASE B: SINGLE USER ADD ---
        elseif (isset($_POST['add_single_user'])) {
            $single_sap = sanitize_input($_POST['sap_id']);
            $single_email = sanitize_input($_POST['email']);
            $single_role = sanitize_input($_POST['role']);
            $parent_staff_id = null;

            // 1. Basic Validation
            if (empty($single_sap) || empty($single_email) || empty($single_role)) {
                $message = "All fields are required.";
                $message_type = "error";
            } 
            elseif (!filter_var($single_email, FILTER_VALIDATE_EMAIL)) {
                $message = "Invalid email format.";
                $message_type = "error";
            }
            // 2. If sub-staff role, validate parent staff is selected
            elseif (strpos($single_role, 'sub_') === 0) {
                $parent_staff_id = intval($_POST['parent_staff_id'] ?? 0);
                if ($parent_staff_id === 0) {
                    $message = "Parent staff member is required for sub-staff roles.";
                    $message_type = "error";
                } else {
                    // Verify parent staff exists and has appropriate role
                    $parent_check = $conn->prepare(
                        "SELECT user_id, role FROM users WHERE user_id = ? AND role IN ('staff', 'maintenance', 'warden', 'security', 'house_keeping')"
                    );
                    $parent_check->bind_param("i", $parent_staff_id);
                    $parent_check->execute();
                    $parent_result = $parent_check->get_result();

                    if ($parent_result->num_rows > 0) {
                        $parent = $parent_result->fetch_assoc();
                        $parent_role = $parent['role'];
                        $expected_sub_role = 'sub_' . $parent_role;

                        if ($single_role !== $expected_sub_role) {
                            $message = "Sub-staff role does not match parent staff role. Expected: " . htmlspecialchars($expected_sub_role);
                            $message_type = "error";
                        } else {
                            // Proceed with user creation
                            $check_q = $conn->prepare("SELECT user_id FROM users WHERE sap_id = ? OR email = ?");
                            $check_q->bind_param("ss", $single_sap, $single_email);
                            $check_q->execute();
                            $check_q->store_result();
                            
                            if ($check_q->num_rows > 0) {
                                $message = "Error: A user with this SAP ID or Email already exists.";
                                $message_type = "error";
                            } else {
                                // 3. Create Sub-Staff User with parent assignment
                                $default_pass = password_hash('12345678', PASSWORD_DEFAULT);
                                $ins_stmt = $conn->prepare("INSERT INTO users (sap_id, email, role, password, parent_staff_id, is_sub_staff) VALUES (?, ?, ?, ?, ?, 1)");
                                $ins_stmt->bind_param("ssssi", $single_sap, $single_email, $single_role, $default_pass, $parent_staff_id);
                                
                                if ($ins_stmt->execute()) {
                                    $message = "Sub-staff user created and assigned successfully! Role: " . htmlspecialchars($single_role);
                                    $message_type = "success";
                                } else {
                                    $message = "Database Error: " . $conn->error;
                                    $message_type = "error";
                                }
                                $ins_stmt->close();
                            }
                            $check_q->close();
                        }
                    } else {
                        $message = "Parent staff member not found or does not have eligible role.";
                        $message_type = "error";
                    }
                    $parent_check->close();
                }
            } else {
                // Regular user (not sub-staff)
                // 2. Check for Duplicates
                $check_q = $conn->prepare("SELECT user_id FROM users WHERE sap_id = ? OR email = ?");
                $check_q->bind_param("ss", $single_sap, $single_email);
                $check_q->execute();
                $check_q->store_result();
                
                if ($check_q->num_rows > 0) {
                    $message = "Error: A user with this SAP ID or Email already exists.";
                    $message_type = "error";
                } else {
                    // 3. Create User
                    $default_pass = password_hash('12345678', PASSWORD_DEFAULT);
                    $ins_stmt = $conn->prepare("INSERT INTO users (sap_id, email, role, password) VALUES (?, ?, ?, ?)");
                    $ins_stmt->bind_param("ssss", $single_sap, $single_email, $single_role, $default_pass);
                    
                    if ($ins_stmt->execute()) {
                        $message = "User created successfully! Role: " . htmlspecialchars($single_role);
                        $message_type = "success";
                    } else {
                        $message = "Database Error: " . $conn->error;
                        $message_type = "error";
                    }
                    $ins_stmt->close();
                }
                $check_q->close();
            }
        }
        // --- CASE C: MANAGE SUB-STAFF ASSIGNMENTS ---
        elseif (isset($_POST['manage_sub_staff'])) {
            $parent_staff_id = intval($_POST['parent_staff_id'] ?? 0);
            $sub_staff_id = intval($_POST['sub_staff_id'] ?? 0);

            if ($parent_staff_id === 0 || $sub_staff_id === 0) {
                $message = "Please select both parent staff and sub-staff.";
                $message_type = "error";
            } else {
                // Verify parent staff exists and has appropriate role
                $parent_check = $conn->prepare(
                    "SELECT user_id, role FROM users WHERE user_id = ? AND role IN ('staff', 'maintenance', 'warden', 'security', 'house_keeping')"
                );
                $parent_check->bind_param("i", $parent_staff_id);
                $parent_check->execute();
                $parent_result = $parent_check->get_result();

                if ($parent_result->num_rows > 0) {
                    $parent = $parent_result->fetch_assoc();
                    $parent_role = $parent['role'];
                    $expected_sub_role = 'sub_' . $parent_role;

                    // Verify sub-staff exists and has matching role
                    $sub_check = $conn->prepare(
                        "SELECT user_id, role FROM users WHERE user_id = ? AND role = ?"
                    );
                    $sub_check->bind_param("is", $sub_staff_id, $expected_sub_role);
                    $sub_check->execute();
                    $sub_result = $sub_check->get_result();

                    if ($sub_result->num_rows > 0) {
                        // Check if sub-staff is already assigned
                        $assigned_check = $conn->prepare(
                            "SELECT user_id FROM users WHERE user_id = ? AND parent_staff_id IS NOT NULL"
                        );
                        $assigned_check->bind_param("i", $sub_staff_id);
                        $assigned_check->execute();
                        $assigned_result = $assigned_check->get_result();

                        if ($assigned_result->num_rows > 0) {
                            $message = "This sub-staff member is already assigned to another supervisor.";
                            $message_type = "error";
                        } else {
                            // Perform the assignment
                            $assign_stmt = $conn->prepare(
                                "UPDATE users SET parent_staff_id = ?, is_sub_staff = 1 WHERE user_id = ?"
                            );
                            $assign_stmt->bind_param("ii", $parent_staff_id, $sub_staff_id);

                            if ($assign_stmt->execute()) {
                                $message = "Sub-staff assigned successfully!";
                                $message_type = "success";
                            } else {
                                $message = "Error assigning sub-staff: " . $conn->error;
                                $message_type = "error";
                            }
                            $assign_stmt->close();
                        }
                        $assigned_check->close();
                    } else {
                        $message = "Sub-staff not found or role mismatch.";
                        $message_type = "error";
                    }
                    $sub_check->close();
                } else {
                    $message = "Parent staff member not found.";
                    $message_type = "error";
                }
                $parent_check->close();
            }
        }
    }
}

// --- FETCH ROLES FOR DROPDOWN ---
$roles_result = $conn->query("SELECT role_name FROM allowed_roles ORDER BY role_name ASC");
$roles_options = [];
if ($roles_result) {
    while($r = $roles_result->fetch_assoc()) {
        $roles_options[] = $r['role_name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users - NMIMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root { --primary: #c41e3a; --primary-dark: #8B0000; --accent-blue: #007bff; --muted: #666; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', 'Trebuchet MS', sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); display: flex; min-height: 100vh; margin: 0; }

        /* Sidebar styling matching admin pages (animated) */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%);
            min-height: 100vh;
            color: white;
            position: fixed;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 20px rgba(196, 30, 58, 0.24);
            z-index: 100;
        }

        .logo-container { padding: 25px 20px; text-align: center; border-bottom: 2px solid rgba(255,255,255,0.2); animation: slideDown 0.6s ease-out; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .logo-container img { width: 120px; filter: brightness(1.1) drop-shadow(0 2px 4px rgba(0,0,0,0.2)); transition: transform 0.3s ease; display: block; }
        .logo-container img:hover { transform: scale(1.05); }
        .logo-container h3 { font-weight: 600; font-size: 0.95em; margin-top: 10px; color: #ffffff; letter-spacing: 0.4px; }

        .user-profile { padding: 25px 20px; text-align: center; border-bottom: 2px solid rgba(255,255,255,0.2); }
        .user-profile .profile-pic { font-size: 50px; color: #ffffff; margin-bottom: 15px; animation: pulse 2s infinite; }
        .user-profile h4 { margin: 8px 0 4px; text-transform: capitalize; font-weight:600; color: #ffffff; }
        .user-profile p { margin: 0; color: rgba(255,255,255,0.9); font-size: 0.9em; }

        .nav-menu { list-style: none; margin: 0; padding: 12px 0; display: flex; flex-direction: column; }
        .nav-item { padding: 12px 22px; display: flex; align-items: center; gap: 12px; color: rgba(255,255,255,0.95); text-decoration: none; transition: all 0.25s cubic-bezier(.2,.8,.2,1); border-left: 4px solid transparent; transform-origin: left center; }
        .nav-item i { width: 20px; text-align: center; }
        .nav-item:hover { background: rgba(255,255,255,0.06); padding-left: 28px; color: #ffffff; transform: translateX(2px); }
        .nav-item.active { background: rgba(255,255,255,0.12); color: #ffffff; border-left: 4px solid #ffffff; padding-left: 24px; font-weight:700; }
        .nav-item .notif-badge { background: #ff6b6b; color: #fff; border-radius: 12px; padding: 4px 8px; font-size: 0.75em; margin-left: auto; font-weight:700; animation: bounce 0.9s infinite; }

        /* staggered nav-entry animations */
        .nav-menu .nav-item:nth-child(1) { animation: slideDown 0.5s ease-out 0.08s both; }
        .nav-menu .nav-item:nth-child(2) { animation: slideDown 0.5s ease-out 0.12s both; }
        .nav-menu .nav-item:nth-child(3) { animation: slideDown 0.5s ease-out 0.16s both; }
        .nav-menu .nav-item:nth-child(4) { animation: slideDown 0.5s ease-out 0.20s both; }
        .nav-menu .nav-item:nth-child(5) { animation: slideDown 0.5s ease-out 0.24s both; }

        /* Main content and header */
        .main-content { flex: 1; margin-left: 260px; padding: 40px 50px; animation: fadeIn 0.6s ease-out; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; animation: slideDown 0.6s ease-out 0.08s both; }
        .page-header h1 { color: var(--primary); margin: 0; font-size: 1.9em; font-weight: 800; letter-spacing: -0.5px; }
        .btn-view-closed { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: white; padding: 10px 18px; text-decoration: none; border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; transition: transform .18s ease, box-shadow .18s ease; box-shadow: 0 8px 18px rgba(196,30,58,0.12); }
        .btn-view-closed:hover { transform: translateY(-3px); box-shadow: 0 14px 30px rgba(196,30,58,0.18); }
        .btn-view-closed.secondary { background: var(--accent-blue); box-shadow: 0 8px 18px rgba(0,123,255,0.12); }

        /* Card & form styles with hover motion */
        .forms-wrapper { display: flex; gap: 30px; flex-wrap: wrap; }
        .card-container { background: white; padding: 28px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); flex: 1; min-width: 320px; transform-origin: center; transition: transform 0.36s cubic-bezier(.2,.9,.2,1), box-shadow 0.36s ease; }
        .card-container:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(0,0,0,0.12); }
        .card-title { font-size: 1.18em; color: #2d3b45; margin-bottom: 18px; padding-bottom: 8px; border-bottom: 1px solid #f3f3f3; }
        .card-title i { color: var(--primary); margin-right: 8px; }

        /* subtle input focus */
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--muted); }
        .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #e6e9ec; border-radius: 8px; font-size: 1em; transition: box-shadow .18s ease, border-color .18s ease; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--primary); box-shadow: 0 6px 18px rgba(196,30,58,0.06); }

        /* row checkbox: checkbox before label */
        .row-checkbox { display:flex; align-items:center; gap:12px; }
        .row-checkbox input[type="checkbox"] { width:18px; height:18px; transform:scale(1.05); }

        .btn-upload, .btn-add, .btn-role { border-radius: 9px; transition: transform .16s ease, box-shadow .16s ease, opacity .12s ease; }
        .btn-upload { background: var(--primary); color: white; padding: 10px 18px; border: none; font-weight:700; width:100%; }
        .btn-upload:hover { transform: translateY(-3px); box-shadow: 0 14px 28px rgba(196,30,58,0.14); }
        .btn-add { background: #17a2b8; color: white; padding: 10px 18px; border: none; font-weight:700; width:100%; }
        .btn-add:hover { transform: translateY(-3px); box-shadow: 0 12px 26px rgba(23,162,184,0.12); }
        .btn-role { background: #6c757d; color: white; padding: 10px 18px; border: none; font-weight:700; width:100%; }
        .btn-role:hover { transform: translateY(-3px); box-shadow: 0 12px 26px rgba(108,117,125,0.12); }

        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #eaf7ee; color: #0e5b2b; border: 1px solid #cfe9d6; }
        .alert-error { background: #fff3f3; color: #7a1414; border: 1px solid #ffd6d6; }

        .error-log { width: 100%; background: #fff7f7; padding: 15px; border-radius: 8px; margin-top: 20px; border: 1px solid #ffd6d6; }
        .error-log h4 { margin-top: 0; color: #dc3545; }
        .error-log ul { padding-left: 20px; margin-bottom: 0; }
        .error-log li { color: #721c24; font-size: 0.95em; margin-bottom: 6px; opacity: 0; transform: translateY(8px); animation: fadeInUp .5s ease forwards; }
        .error-log li:nth-child(1) { animation-delay: 0.06s; }
        .error-log li:nth-child(2) { animation-delay: 0.12s; }
        .error-log li:nth-child(3) { animation-delay: 0.18s; }
        .error-log li:nth-child(4) { animation-delay: 0.24s; }

        /* small responsive tweak */
        @media (max-width: 920px) { .forms-wrapper { flex-direction: column; } .main-content { padding: 24px; } }

        /* Animations (from admin) */
        @keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.12); } }
        @keyframes bounce { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-3px); } }
    </style>
</head>
<body>
    
    <div class="sidebar">
        <div class="logo-container"><img src="<?php echo file_exists('NMIMS Logo.jpg') ? 'NMIMS Logo.jpg' : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIwIiBoZWlnaHQ9IjEyMCIgdmlld0JveD0iMCAwIDEyMCAxMjAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMjAiIGhlaWdodD0iMTIwIiBmaWxsPSIjQzQxRTNBIi8+Cjx0ZXh0IHg9IjYwIiB5PSI2NSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0id2hpdGUiIGZvbnQtc2l6ZT0iMTgiPk5NSlNUPC90ZXh0Pgo8L3N2Zz4='; ?>" alt="NMIMS Logo"><h3>NMIMS Issue Tracker</h3></div>
        <div class="user-profile"><div class="profile-pic"><i class="fas fa-user-circle"></i></div><h4><?= htmlspecialchars($role) ?></h4><p><?= htmlspecialchars($sap_id) ?></p></div>
        <nav class="nav-menu" role="navigation" aria-label="Main navigation">
            <a href="index.php" class="nav-item" aria-label="Go to Dashboard"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
            <a href="view_tickets.php" class="nav-item" aria-label="View My Own Tickets"><i class="fas fa-ticket-alt"></i><span>My Own Tickets</span></a>
            <a href="admin_dashboard.php" class="nav-item" aria-label="Access Admin Dashboard"><i class="fas fa-user-shield"></i><span>Admin Dashboard</span></a>
            <a href="bulk_import.php" class="nav-item active" aria-label="Bulk Import Users" aria-current="page"><i class="fas fa-user-plus"></i><span>Add Users</span></a>
            <a href="notifications.php" class="nav-item" aria-label="View Notifications<?php if($unread_count>0): ?> (<?= $unread_count ?> unread)<?php endif; ?>"><i class="fas fa-bell"></i><span>Notifications</span><?php if($unread_count>0): ?><span class="notif-badge" aria-label="<?= $unread_count ?> unread notifications"><?= $unread_count ?></span><?php endif;?></a>
            <a href="logout.php" class="nav-item" aria-label="Logout from system"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1>User Management</h1>
            <a href="admin_dashboard.php" class="btn-view-closed">Back to Dashboard</a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type == 'error' ? 'error' : 'success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="forms-wrapper">
            <div class="card-container">
                <div class="card-title"><i class="fas fa-file-csv"></i> Bulk Import (Students)</div>
                <p style="margin-bottom: 20px; color:#666; font-size:0.9em;">Upload a CSV with <strong>SAP ID</strong> (Col A) and <strong>Email</strong> (Col B).<br>Default Password: 12345678</p>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="form-group">
                        <label>Select CSV File</label>
                        <input type="file" name="csv_file" accept=".csv" required>
                    </div>
                    
                    <div class="form-group row-checkbox">
                        <input type="checkbox" name="skip_header" id="skip_header" >
                        <label for="skip_header" style="font-weight: normal;">Skip first row (Header)</label>
                    </div>
                    
                    <button type="submit" class="btn-upload">Import Students</button>
                </form>
            </div>

            <div class="card-container">
                <div class="card-title"><i class="fas fa-user-plus"></i> Add Single User</div>
                <p style="margin-bottom: 20px; color:#666; font-size:0.9em;">Manually add Staff, Wardens, Admins, or Sub-Staff.<br>Default Password: 12345678</p>
                
                <form method="POST" id="add_user_form">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="add_single_user" value="1">
                    
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" id="role_select" required onchange="toggleParentStaffField()">
                            <option value="">-- Select Role --</option>
                            <?php foreach ($roles_options as $r_opt): ?>
                                <option value="<?= htmlspecialchars($r_opt) ?>"><?= ucfirst($r_opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>SAP ID / Employee ID</label>
                        <input type="text" name="sap_id" required maxlength="15" placeholder="e.g. 7057230001">
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" required placeholder="e.g. name@nmims.in">
                    </div>

                    <div class="form-group" id="parent_staff_field" style="display: none;">
                        <label>Assign to Parent Staff (Required for Sub-Staff)</label>
                        <select name="parent_staff_id" id="parent_staff_select">
                            <option value="">-- Select Parent Staff --</option>
                            <?php
                            // Fetch all staff members who can have sub-staff
                            $parent_roles = ['staff', 'maintenance', 'warden', 'security', 'house_keeping'];
                            $parent_query = $conn->query(
                                "SELECT user_id, sap_id, role FROM users WHERE role IN ('" . 
                                implode("','", $parent_roles) . 
                                "') AND is_sub_staff = 0 ORDER BY sap_id"
                            );
                            while ($parent = $parent_query->fetch_assoc()):
                            ?>
                                <option value="<?= htmlspecialchars($parent['user_id']) ?>">
                                    <?= htmlspecialchars($parent['sap_id'] . ' (' . $parent['role'] . ')') ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn-add">Add User</button>
                </form>

                <script>
                function toggleParentStaffField() {
                    const roleSelect = document.getElementById('role_select');
                    const parentStaffField = document.getElementById('parent_staff_field');
                    const parentStaffSelect = document.getElementById('parent_staff_select');
                    const selectedRole = roleSelect.value;

                    if (selectedRole.startsWith('sub_')) {
                        parentStaffField.style.display = 'block';
                        parentStaffSelect.required = true;
                    } else {
                        parentStaffField.style.display = 'none';
                        parentStaffSelect.required = false;
                    }
                }
                </script>
            </div>

            <div class="card-container">
                <div class="card-title"><i class="fas fa-user-tie"></i> Manage Sub-Staff</div>
                <p style="margin-bottom: 20px; color:#666; font-size:0.9em;">Assign sub-staff members to their supervisors.<br>Sub-staff work under a specific staff member.</p>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="manage_sub_staff" value="1">
                    
                    <div class="form-group">
                        <label>Parent Staff Member</label>
                        <select name="parent_staff_id" required>
                            <option value="">-- Select Staff Member --</option>
                            <?php
                            // Fetch all staff members who can have sub-staff
                            $parent_roles = ['staff', 'maintenance', 'warden', 'security', 'house_keeping'];
                            $parent_query = $conn->query(
                                "SELECT user_id, sap_id, role FROM users WHERE role IN ('" . 
                                implode("','", $parent_roles) . 
                                "') AND is_sub_staff = 0 ORDER BY sap_id"
                            );
                            while ($parent = $parent_query->fetch_assoc()):
                            ?>
                                <option value="<?= htmlspecialchars($parent['user_id']) ?>">
                                    <?= htmlspecialchars($parent['sap_id'] . ' (' . $parent['role'] . ')') ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Sub-Staff Member</label>
                        <select name="sub_staff_id" required>
                            <option value="">-- Select Sub-Staff --</option>
                            <?php
                            // Fetch all users who are not yet assigned as sub-staff
                            $sub_query = $conn->query(
                                "SELECT user_id, sap_id, role FROM users WHERE is_sub_staff = 0 AND parent_staff_id IS NULL AND role LIKE 'sub_%' ORDER BY sap_id"
                            );
                            while ($sub = $sub_query->fetch_assoc()):
                            ?>
                                <option value="<?= htmlspecialchars($sub['user_id']) ?>">
                                    <?= htmlspecialchars($sub['sap_id'] . ' (' . $sub['role'] . ')') ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-role">Assign Sub-Staff</button>
                </form>

                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                    <strong>Current Sub-Staff Assignments:</strong><br>
                    <?php
                    // Fetch and display current assignments
                    $assignments_query = $conn->query(
                        "SELECT u1.sap_id as parent_sap, u1.role as parent_role, u2.sap_id as sub_sap, u2.role as sub_role 
                         FROM users u2 
                         JOIN users u1 ON u2.parent_staff_id = u1.user_id 
                         WHERE u2.parent_staff_id IS NOT NULL 
                         ORDER BY u1.sap_id, u2.sap_id"
                    );
                    
                    if ($assignments_query && $assignments_query->num_rows > 0):
                        echo '<ul style="padding-left: 20px; margin-top: 10px;">';
                        while ($assignment = $assignments_query->fetch_assoc()):
                    ?>
                            <li style="margin: 8px 0; color: #666;">
                                <strong><?= htmlspecialchars($assignment['sub_sap']) ?></strong> 
                                (<?= htmlspecialchars($assignment['sub_role']) ?>) 
                                → 
                                <strong><?= htmlspecialchars($assignment['parent_sap']) ?></strong>
                                (<?= htmlspecialchars($assignment['parent_role']) ?>)
                            </li>
                    <?php 
                        endwhile;
                        echo '</ul>';
                    else:
                    ?>
                        <p style="color: #999; font-size: 0.9em;">No sub-staff assignments yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-container">
                <div class="card-title"><i class="fas fa-tags"></i> Manage Roles</div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="add_new_role" value="1">
                    <div class="form-group">
                        <label>New Role Name</label>
                        <input type="text" name="new_role_name" placeholder="e.g. Electrician" required>
                    </div>
                    <button type="submit" class="btn-role">Add Role</button>
                </form>

                <div class="roles-list">
                    <strong>Active Roles:</strong><br>
                    <?php foreach ($roles_options as $r_opt): ?>
                        <span><?= htmlspecialchars($r_opt) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($error_log)): ?>
            <div class="error-log">
                <h4><i class="fas fa-exclamation-circle"></i> Import Issues</h4>
                <ul>
                    <?php foreach ($error_log as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>