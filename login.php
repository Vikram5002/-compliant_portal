<?php
include 'db_connect.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sap_id = sanitize_input($_POST['sap_id']);
    $password = $_POST['password'];

    $sql = "SELECT user_id, role, password FROM users WHERE sap_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) die("Prepare failed: " . $conn->error);
    $stmt->bind_param("s", $sap_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['sap_id'] = $sap_id;
            header("Location: index.php");
            exit;
        } else {
            $error_message = "Authentication Failed!!! Please check Username or Password";
        }
    } else {
        $error_message = "Authentication Failed!!! Please check Username or Password";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NMIMS Issue Tracker</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', 'Trebuchet MS', sans-serif;
            margin: 0;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        
        /* ===== LEFT PANE ===== */
        .left-pane {
            flex: 1.2;
            background: linear-gradient(135deg, rgba(196, 30, 58, 0.8) 0%, rgba(139, 0, 0, 0.9) 100%), 
                        url('https://images.collegedunia.com/public/college_data/images/campusimage/1488448834ccc.jpg') no-repeat center center;
            background-size: cover;
            color: white;
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
            animation: slideInLeft 0.8s ease-out;
        }
        
        .left-pane::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            pointer-events: none;
        }
        
        .left-pane h1 {
            font-size: 2.8em;
            margin-bottom: 25px;
            font-weight: 700;
            letter-spacing: -0.5px;
            position: relative;
            z-index: 2;
            animation: slideDown 0.8s ease-out 0.2s both;
            text-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .left-pane p {
            font-size: 1.1em;
            line-height: 1.8;
            max-width: 500px;
            position: relative;
            z-index: 2;
            opacity: 0.95;
            animation: fadeInUp 0.8s ease-out 0.3s both;
            margin-bottom: 15px;
        }
        
        .left-pane p strong {
            display: block;
            margin-top: 20px;
            font-weight: 700;
            font-size: 1.15em;
        }
        
        /* ===== RIGHT PANE ===== */
        .right-pane {
            flex: 1;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            animation: fadeIn 0.8s ease-out;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
            background: white;
            padding: 50px;
            border-radius: 16px;
            box-shadow: 0 15px 50px rgba(196, 30, 58, 0.15);
            animation: slideUp 0.8s ease-out 0.1s both;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 35px;
            animation: zoomIn 0.8s ease-out 0.2s both;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo img {
            width: 160px;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.1));
            transition: transform 0.3s ease;
            display: block;
        }
        
        .logo img:hover {
            transform: scale(1.05);
        }
        
        .login-container h2 {
            display: none;
        }
        
        /* ===== FORM ELEMENTS ===== */
        .form-group {
            margin-bottom: 22px;
            position: relative;
            animation: slideDown 0.6s ease-out;
        }
        
        .form-group label {
            display: none;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 14px 14px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            box-sizing: border-box;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: #f9f9f9;
            color: #2c3e50;
            font-weight: 500;
        }
        
        .form-group input::placeholder {
            color: #999;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #c41e3a;
            background: white;
            box-shadow: 0 0 0 4px rgba(196, 30, 58, 0.1);
            transform: translateY(-2px);
        }
        
        .form-group i {
            position: absolute;
            left: 16px;
            top: 16px;
            color: #c41e3a;
            font-size: 1.1em;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        
        .form-group input:focus ~ i {
            color: #8B0000;
            transform: scale(1.2);
        }
        
        /* ===== PASSWORD OPTIONS ===== */
        .password-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9em;
            color: #666;
            margin-bottom: 25px;
            animation: fadeIn 0.8s ease-out 0.3s both;
        }
        
        .password-options input[type="checkbox"] {
            cursor: pointer;
            margin-right: 6px;
            accent-color: #c41e3a;
        }
        
        .password-options label {
            cursor: pointer;
            font-weight: 500;
            display: inline;
        }
        
        .password-options a {
            color: #c41e3a;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .password-options a:hover {
            color: #8B0000;
            text-decoration: underline;
        }
        
        /* ===== ERROR MESSAGE ===== */
        .error-message {
            color: #d32f2f;
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            border-left: 4px solid #d32f2f;
            padding: 16px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
            font-weight: 600;
            animation: slideDown 0.6s ease-out;
            box-shadow: 0 4px 12px rgba(211, 47, 47, 0.1);
        }
        
        /* ===== BUTTON ===== */
        .btn {
            width: 100%;
            padding: 14px 20px;
            border-radius: 10px;
            font-size: 1.05em;
            font-weight: 700;
            cursor: pointer;
            border: none;
            background: linear-gradient(135deg, #c41e3a 0%, #8B0000 100%);
            color: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 6px 20px rgba(196, 30, 58, 0.3);
            letter-spacing: 0.5px;
            animation: slideUp 0.8s ease-out 0.4s both;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(196, 30, 58, 0.4);
        }
        
        .btn:active {
            transform: translateY(-1px);
        }
        
        /* ===== SIGNUP LINK ===== */
        .signup-link {
            text-align: center;
            margin-top: 25px;
            animation: fadeIn 0.8s ease-out 0.5s both;
        }
        
        .signup-link p {
            color: #666;
            font-weight: 500;
            margin: 0;
        }
        
        .signup-link a {
            color: #c41e3a;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .signup-link a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(135deg, #c41e3a 0%, #8B0000 100%);
            transition: width 0.3s ease;
        }
        
        .signup-link a:hover::after {
            width: 100%;
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
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
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
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes zoomIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
    </style>

    <style>
    /* ===== RESPONSIVE DESIGN ===== */
    @media (max-width: 768px) {
        body { flex-direction: column; min-height: 100vh; }
        .left-pane { 
            flex: none; 
            padding: 30px 20px; 
            text-align: center;
            min-height: auto;
        }
        .left-pane h1 { font-size: 1.8em; }
        .left-pane p { font-size: 0.9em; }
        .right-pane { 
            flex: 1; 
            padding: 20px;
        }
        .login-container { 
            width: 100%; 
            max-width: 400px;
            padding: 25px;
        }
        .login-container h2 { font-size: 1.5em; }
        .form-group input { 
            font-size: 16px; /* Prevents zoom on iOS */
            padding: 12px;
        }
        .btn { 
            width: 100%; 
            padding: 12px;
            font-size: 1.1em;
        }
    }

    @media (max-width: 480px) {
        .left-pane { padding: 20px 15px; }
        .left-pane h1 { font-size: 1.5em; }
        .right-pane { padding: 15px; }
        .login-container { padding: 20px; }
        .login-container h2 { font-size: 1.3em; }
        .form-group { margin-bottom: 15px; }
    }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="left-pane">
        <h1>Welcome to NMIMS University</h1>
        <p>You are about to log in to the world of Online Learning at NMIMS.</p>
        <p>With this Issue Tracker Portal, we hope to provide you all the support you need during your enrollment with the Program offered by the University. It will be our endeavour to keep improving your experience with this Portal as we go along.</p>
        <p><strong>Happy Learning! - Team</strong></p>
    </div>
    <div class="right-pane">
        <div class="login-container">
            <div class="logo">
                <img src="<?php echo file_exists('NMIMS Logo.jpg') ? 'NMIMS Logo.jpg' : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYwIiBoZWlnaHQ9IjE2MCIgdmlld0JveD0iMCAwIDE2MCAxNjAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxNjAiIGhlaWdodD0iMTYwIiBmaWxsPSIjQzQxRTNBIi8+Cjx0ZXh0IHg9IjgwIiB5PSI4NSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0id2hpdGUiIGZvbnQtc2l6ZT0iMTgiPk5NSlNUPC90ZXh0Pgo8L3N2Zz4='; ?>" alt="NMIMS Logo">
            </div>
            <?php if ($error_message): ?>
                <p class="error-message"><?php echo $error_message; ?></p>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="form-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="sap_id" id="sap_id" required autofocus placeholder="Username">
                </div>
                <div class="form-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" required placeholder="Password">
                </div>
                <div class="password-options">
                    <div>
                        <input type="checkbox" id="show_password" onclick="togglePassword()">
                        <label for="show_password">Show Password</label>
                    </div>
                    <a href="forgot_password.php">Forgot Password?</a>
                </div>
                <button type="submit" class="btn">Login</button>
            </form>
            
            <!-- <div class="signup-link">
                <p>Don't have an account? <a href="signup.php">Sign up</a></p>
            </div> -->
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay" style="display: none;">
        <div style="text-align: center;">
            <div class="loading-spinner"></div>
            <div class="loading-text">Logging you in...</div>
        </div>
    </div>

    <script>
        function togglePassword() {
            var passField = document.getElementById("password");
            if (passField.type === "password") {
                passField.type = "text";
            } else {
                passField.type = "password";
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const loadingOverlay = document.getElementById('loading-overlay');
            
            form.addEventListener('submit', function(e) {
                // Show loading overlay
                loadingOverlay.style.display = 'flex';
                
                // Disable the submit button to prevent double submission
                const submitBtn = document.querySelector('.btn');
                submitBtn.disabled = true;
                submitBtn.textContent = 'Logging in...';
                
                // The form will submit normally, loading will show until redirect
            });
        });
    </script>
</body>
</html>