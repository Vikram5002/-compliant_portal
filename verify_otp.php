<?php
// verify_otp.php
include 'db_connect.php';

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit;
}

$message = '';

// Find this block in verify_otp.php and ensure it matches:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp_input = $_POST['otp'];
    $email = $_SESSION['reset_email'];

    // Ensure we use "ss" (string, string) for binding
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND otp_code = ? AND otp_expiry > NOW()");
    $stmt->bind_param("ss", $email, $otp_input); 
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $_SESSION['otp_verified'] = true;
        header("Location: reset_password.php");
        exit;
    } else {
        $message = "Invalid or expired OTP.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - NMIMS Issue Tracker</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .otp-container {
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
        
        .otp-container h2 {
            color: #c41e3a;
            margin-bottom: 10px;
            font-size: 1.8em;
        }
        
        .otp-container p {
            color: #666;
            margin-bottom: 30px;
            font-size: 0.95em;
            line-height: 1.5;
        }
        
        .otp-container strong {
            color: #c41e3a;
            word-break: break-all;
        }
        
        .form-group {
            margin-bottom: 25px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            color: #c41e3a;
            font-weight: 700;
            margin-bottom: 10px;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group input[type="text"] {
            text-align: center;
            letter-spacing: 8px;
            font-size: 1.8em;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            padding: 16px;
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
        
        .info-text {
            color: #666;
            font-size: 0.85em;
            margin-top: 15px;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .otp-container {
                padding: 25px;
                box-shadow: 0 6px 20px rgba(196, 30, 58, 0.1);
            }
            
            .otp-container h2 {
                font-size: 1.5em;
            }
            
            .logo img {
                width: 80px;
            }
            
            .form-group input[type="text"] {
                font-size: 1.5em;
                letter-spacing: 5px;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 15px;
            }
            
            .otp-container {
                padding: 20px;
            }
            
            .otp-container h2 {
                font-size: 1.3em;
            }
            
            .otp-container p {
                font-size: 0.9em;
            }
            
            .logo img {
                width: 70px;
            }
            
            .form-group input[type="text"] {
                font-size: 1.3em;
                letter-spacing: 3px;
            }
        }
    </style>
</head>
<body>
    <div class="otp-container">
        <div class="logo">
            <img src="<?php echo file_exists('NMIMS Logo.jpg') ? 'NMIMS Logo.jpg' : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjQzQxRTNBIi8+Cjx0ZXh0IHg9IjUwIiB5PSI1NSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0id2hpdGUiIGZvbnQtc2l6ZT0iMTQiPk5NSUpTVDwvdGV4dD4KPC9zdmc+'; ?>" alt="NMIMS Logo">
        </div>
        <h2>Verify OTP</h2>
        <p>An OTP has been sent to <strong><?= htmlspecialchars($_SESSION['reset_email']) ?></strong>.</p>
        <?php if ($message): ?><div class="error-message"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="otp">Enter OTP Code</label>
                <input type="text" id="otp" name="otp" placeholder="------" maxlength="6" inputmode="numeric" pattern="[0-9]*" required>
            </div>
            <button type="submit" class="btn">Verify & Continue</button>
        </form>
        <p class="info-text">⏱️ OTP expires in 10 minutes. Please check your spam folder if you don't see the email.</p>
        <a href="forgot_password.php" class="back-link">Request New OTP</a>
    </div>
</body>
</html>