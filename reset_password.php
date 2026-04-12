<?php
// reset_password.php
include 'db_connect.php';

if (!isset($_SESSION['reset_email']) || !isset($_SESSION['otp_verified'])) {
    header("Location: login.php");
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass1 = $_POST['pass1'];
    $pass2 = $_POST['pass2'];
    $email = $_SESSION['reset_email'];

    if ($pass1 === $pass2) {
        $hashed_password = password_hash($pass1, PASSWORD_DEFAULT);

        // Update password and clear OTP
        $stmt = $conn->prepare("UPDATE users SET password = ?, otp_code = NULL, otp_expiry = NULL WHERE email = ?");
        $stmt->bind_param("ss", $hashed_password, $email);
        
        if ($stmt->execute()) {
            // Clear session and redirect
            session_destroy();
            header("Location: login.php?message=Password reset successful! Please login.");
            exit;
        } else {
            $message = "Error resetting password.";
        }
    } else {
        $message = "Passwords do not match.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - NMIMS Issue Tracker</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .reset-container {
            width: 100%;
            max-width: 450px;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(196, 30, 58, 0.15);
            text-align: center;
            animation: slideUp 0.7s ease-out;
        }
        
        .reset-container h2 {
            color: #c41e3a;
            margin-bottom: 10px;
            font-size: 1.8em;
        }
        
        .reset-container p {
            color: #666;
            margin-bottom: 30px;
            font-size: 0.95em;
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
            margin-top: 10px;
            font-size: 1.05em;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #666;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .back-link:hover {
            color: #c41e3a;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .reset-container {
                padding: 25px;
                box-shadow: 0 6px 20px rgba(196, 30, 58, 0.1);
            }
            
            .reset-container h2 {
                font-size: 1.5em;
            }
            
            .form-group {
                margin-bottom: 18px;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 15px;
            }
            
            .reset-container {
                padding: 20px;
            }
            
            .reset-container h2 {
                font-size: 1.3em;
            }
            
            .reset-container p {
                font-size: 0.9em;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <h2>Reset Password</h2>
        <p>Enter your new password to reset your account</p>
        <?php if ($message): ?><div class="error-message"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="pass1">New Password</label>
                <input type="password" id="pass1" name="pass1" placeholder="Enter new password" required minlength="6">
            </div>
            <div class="form-group">
                <label for="pass2">Confirm Password</label>
                <input type="password" id="pass2" name="pass2" placeholder="Confirm password" required minlength="6">
            </div>
            <button type="submit" class="btn">Update Password</button>
        </form>
        <a href="login.php" class="back-link">Back to Login</a>
    </div>
</body>
</html>