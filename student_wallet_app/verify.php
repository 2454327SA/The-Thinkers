<?php
session_start();
require_once 'includes/auth.php';
$auth = new Auth();

$email = $_GET['email'] ?? $_SESSION['temp_email'] ?? '';
$phone = $_SESSION['temp_phone'] ?? '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $otp = $_POST['otp'];
    
    if($auth->verifyOTP($email, $phone, $otp, 'registration')) {
        // Update user as verified
        require_once 'config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        
        $update = "UPDATE users SET is_verified = 1 WHERE id = :id";
        $stmt = $conn->prepare($update);
        $stmt->bindParam(':id', $_SESSION['temp_user']);
        $stmt->execute();
        
        // Send welcome notification
        $notif = "INSERT INTO notifications (user_id, title, message, type) 
                  VALUES (:id, 'Welcome!', 'Your account has been successfully verified. You can now use your digital ID card.', 'success')";
        $notifStmt = $conn->prepare($notif);
        $notifStmt->bindParam(':id', $_SESSION['temp_user']);
        $notifStmt->execute();
        
        unset($_SESSION['temp_user'], $_SESSION['temp_email'], $_SESSION['temp_phone']);
        header("Location: login.php?verified=1");
        exit();
    } else {
        $error = "Invalid OTP code. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Account - Student ID Wallet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .verify-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 450px;
            width: 100%;
            text-align: center;
        }
        .otp-input {
            width: 60px;
            height: 60px;
            margin: 0 5px;
            text-align: center;
            font-size: 24px;
            border: 2px solid #dee2e6;
            border-radius: 10px;
        }
        .otp-input:focus {
            border-color: #667eea;
            outline: none;
        }
    </style>
</head>
<body>
    <div class="verify-card">
        <i class="fas fa-envelope-open-text fa-4x mb-3" style="color: #667eea;"></i>
        <h3>Verify Your Account</h3>
        <p class="text-muted">A verification code was sent to:</p>
        <p><strong><?php echo htmlspecialchars($email); ?></strong><br>
        <small><?php echo htmlspecialchars($phone); ?></small></p>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" id="otpForm">
            <div class="mb-4">
                <div class="d-flex justify-content-center">
                    <input type="text" class="otp-input" maxlength="1" autofocus>
                    <input type="text" class="otp-input" maxlength="1">
                    <input type="text" class="otp-input" maxlength="1">
                    <input type="text" class="otp-input" maxlength="1">
                    <input type="text" class="otp-input" maxlength="1">
                    <input type="text" class="otp-input" maxlength="1">
                </div>
                <input type="hidden" name="otp" id="otp">
            </div>
            
            <button type="submit" class="btn btn-primary w-100 mb-3">
                <i class="fas fa-check-circle me-2"></i>Verify
            </button>
            
            <div class="text-center">
                <a href="#" id="resendCode" class="text-decoration-none">Resend Code</a>
                <span id="timer" class="text-muted ms-2">(30s)</span>
            </div>
        </form>
    </div>
    
    <script>
        // OTP input handling
        const inputs = document.querySelectorAll('.otp-input');
        const otpField = document.getElementById('otp');
        
        inputs.forEach((input, index) => {
            input.addEventListener('keyup', function(e) {
                if(e.key >= '0' && e.key <= '9') {
                    if(index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                } else if(e.key === 'Backspace') {
                    if(index > 0) {
                        inputs[index - 1].focus();
                    }
                }
                
                let otp = '';
                inputs.forEach(input => otp += input.value);
                otpField.value = otp;
            });
        });
        
        // Resend code with timer
        let timer = 30;
        const timerElement = document.getElementById('timer');
        const resendLink = document.getElementById('resendCode');
        
        resendLink.addEventListener('click', function(e) {
            e.preventDefault();
            if(timer === 0) {
                // AJAX call to resend OTP
                fetch('api/resend_otp.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: '<?php echo $email; ?>',
                        phone: '<?php echo $phone; ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        timer = 30;
                        startTimer();
                        alert('New verification code sent!');
                    }
                });
            }
        });
        
        function startTimer() {
            const interval = setInterval(() => {
                timer--;
                timerElement.textContent = `(${timer}s)`;
                
                if(timer <= 0) {
                    clearInterval(interval);
                    timerElement.textContent = '(available)';
                }
            }, 1000);
        }
        
        startTimer();
    </script>
</body>
</html>