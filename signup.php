<?php
include 'db_connect.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sap_id = sanitize_input($_POST['sap_id']);
    $email = sanitize_input($_POST['email']);
    $role = sanitize_input($_POST['role']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (sap_id, email, role, password) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) die("Prepare failed: " . $conn->error);
    $stmt->bind_param("ssss", $sap_id, $email, $role, $password);
    if ($stmt->execute()) {
        header("Location: login.php?message=Signup successful! Please log in.");
        exit;
    } else {
        $error_message = "Error: " . $conn->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signup - NMIMS Issue Tracker</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Segoe UI','Trebuchet MS',sans-serif;background:linear-gradient(135deg,#f5f7fa 0%,#f0f2f5 100%);display:flex;align-items:center;justify-content:center;min-height:100vh}
        .login-container{width:100%;max-width:520px;background:#fff;border-radius:16px;padding:36px;box-shadow:0 20px 60px rgba(196,30,58,0.12);animation:fadeInUp .7s ease-out}
        .logo{text-align:center;margin-bottom:22px}
        .logo img{width:140px;filter:drop-shadow(0 6px 20px rgba(0,0,0,0.08));transition:transform .25s}
        .logo img:hover{transform:scale(1.03)}
        h2{color:#c41e3a;text-align:center;margin-bottom:14px;font-size:1.6em}
        .form-group{margin-bottom:16px}
        .form-group label{display:block;color:#2c3e50;font-weight:600;margin-bottom:6px}
        .form-group input,.form-group select{width:100%;padding:12px 14px;border-radius:10px;border:2px solid #f0f0f0;background:#fbfbfb;font-size:15px;transition:all .25s}
        .form-group input:focus,.form-group select:focus{outline:none;border-color:#c41e3a;box-shadow:0 8px 28px rgba(196,30,58,0.08);transform:translateY(-2px);background:#fff}
        .error-message{color:#b71c1c;background:linear-gradient(90deg,#fff0f0,#fff6f6);border-left:4px solid #d32f2f;padding:12px;border-radius:8px;margin-bottom:14px;font-weight:700}
        .btn{width:100%;padding:14px;border-radius:10px;font-size:1em;font-weight:800;cursor:pointer;border:none;background:linear-gradient(135deg,#c41e3a 0%,#8b0000 100%);color:#fff;box-shadow:0 12px 36px rgba(196,30,58,0.18);transition:all .28s}
        .btn:hover{transform:translateY(-4px);box-shadow:0 18px 48px rgba(196,30,58,0.28)}
        .login-link{text-align:center;margin-top:16px}
        .login-link a{color:#c41e3a;text-decoration:none;font-weight:700}
        .login-link a:hover{text-decoration:underline}
        @keyframes fadeInUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <img src="<?php echo file_exists('NMIMS Logo.jpg') ? 'NMIMS Logo.jpg' : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYwIiBoZWlnaHQ9IjE2MCIgdmlld0JveD0iMCAwIDE2MCAxNjAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxNjAiIGhlaWdodD0iMTYwIiBmaWxsPSIjQzQxRTNBIi8+Cjx0ZXh0IHg9IjgwIiB5PSI4NSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0id2hpdGUiIGZvbnQtc2l6ZT0iMTgiPk5NSlNUPC90ZXh0Pgo8L3N2Zz4='; ?>" alt="NMIMS Logo">
        </div>
        <h2>Signup</h2>
        <?php if ($error_message): ?>
            <p class="error-message"><?php echo $error_message; ?></p>
        <?php elseif (isset($_GET['message'])): ?>
            <p class="error-message"><?php echo htmlspecialchars($_GET['message']); ?></p>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="sap_id">SAP ID</label>
                <input type="text" name="sap_id" id="sap_id" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <!-- UPDATE: Added placeholder -->
                <input type="email" name="email" id="email" required placeholder="username@nmims.in">
            </div>
            <div class="form-group">
                <label for="role">Role</label>
                <select name="role" id="role" required>
                    <option value="student">Student</option>
                    <option value="faculty">Faculty</option>
                    <option value="staff">Staff</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="warden">Warden</option>
                    <option value="rector">Rector</option>
                    <option value="admin">Admin</option>
                    <option value="super_visor">Super Visor</option>
                    <option value="security">Security</option>
                    <option value="house_keeping">House Keeping</option>
                    <option value="network/it_team">Network/IT Team</option>
                </select>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required minlength="6">
            </div>
            <button type="submit" class="btn">Signup</button>
        </form>
        <div class="login-link">
            <p>Already have an account? <a href="login.php">Login</a></p>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay" style="display: none;">
        <div style="text-align: center;">
            <div class="loading-spinner"></div>
            <div class="loading-text">Creating your account...</div>
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
                submitBtn.textContent = 'Signing up...';
                
                // The form will submit normally, loading will show until redirect
            });
        });
    </script>

</body>
</html>
