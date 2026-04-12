<?php
// forgot_password.php
include 'db_connect.php';
include 'send_email.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);

    // Check if email exists
    $stmt = $conn->prepare("SELECT user_id, sap_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Generate 6-digit OTP and Expiry (10 minutes)
        $otp = rand(100000, 999999);
        $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));

        // Save to Database
        $update_stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE email = ?");
        $update_stmt->bind_param("sss", $otp, $expiry, $email);
        
        if ($update_stmt->execute()) {
            // Send Email
            $subject = "Password Reset OTP - NMIMS Issue Tracker";
            $body = "
                <h3>Password Reset Request</h3>
                <p>Your One-Time Password (OTP) to reset your password is:</p>
                <h2 style='color: #8B0000;'>$otp</h2>
                <p>This code is valid for 10 minutes. If you did not request this, please ignore this email.</p>
            ";

            if (sendTicketEmail($email, $subject, $body)) {
                // Store email in session for the next step
                $_SESSION['reset_email'] = $email;
                header("Location: verify_otp.php");
                exit;
            } else {
                $message = "Error sending email. Please try again later.";
            }
        }
    } else {
        // For security, don't explicitly say "Email not found". 
        // Just say "If that email exists, we sent an OTP."
        // But for this MVP, we can be more direct:
        $message = "Email address not found in our system.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - NMIMS Issue Tracker</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .forgot-container {
            width: 100%;
            max-width: 450px;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(196, 30, 58, 0.15);
            text-align: center;
            animation: slideUp 0.7s ease-out;
        }
        
        .logo {
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        
        .logo img {
            width: 100px;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.1));
            transition: transform 0.3s ease;
            display: block;
        }
        
        .logo img:hover {
            transform: scale(1.05);
        }
        
        .forgot-container h2 {
            color: #c41e3a;
            margin-bottom: 10px;
            font-size: 1.8em;
        }
        
        .forgot-container p {
            color: #666;
            margin-bottom: 30px;
            font-size: 0.95em;
            line-height: 1.5;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            color: #c41e3a;
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .error-message {
            color: #d32f2f;
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            border-left: 4px solid #d32f2f;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .btn {
            width: 100%;
            padding: 14px 20px;
            font-size: 1.05em;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #666;
            text-decoration: none;
            transition: color 0.3s ease;
            font-weight: 500;
        }
        
        .back-link:hover {
            color: #c41e3a;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .forgot-container {
                padding: 25px;
                box-shadow: 0 6px 20px rgba(196, 30, 58, 0.1);
            }
            
            .forgot-container h2 {
                font-size: 1.5em;
            }
            
            .logo img {
                width: 80px;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 15px;
            }
            
            .forgot-container {
                padding: 20px;
            }
            
            .forgot-container h2 {
                font-size: 1.3em;
            }
            
            .forgot-container p {
                font-size: 0.9em;
            }
            
            .logo img {
                width: 70px;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="logo">
            <img src="<?php echo file_exists('NMIMS Logo.jpg') ? 'NMIMS Logo.jpg' : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjQzQxRTNBIi8+Cjx0ZXh0IHg9IjUwIiB5PSI1NSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0id2hpdGUiIGZvbnQtc2l6ZT0iMTQiPk5NSUpTVDwvdGV4dD4KPC9zdmc+'; ?>" alt="NMIMS Logo">
        </div>
        <h2>Forgot Password?</h2>
        <p>Enter your registered email address to receive a password reset code.</p>
        <?php if ($message): ?><div class="error-message"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="your.email@nmims.edu" required>
            </div>
            <button type="submit" class="btn">Send Reset Code</button>
        </form>
        <a href="login.php" class="back-link">Back to Login</a>
    </div>
</body>
</html>