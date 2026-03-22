<?php
session_start();
require_once 'includes/auth.php';
$auth = new Auth();

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $result = $auth->login($email, $password);
    if($result === true) {
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid credentials or account not verified";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Student ID Wallet</title>
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
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            animation: fadeInUp 0.6s ease;
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
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 50px;
            width: 100%;
            transition: transform 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            color: white;
        }
        .biometric-btn {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 10px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .biometric-btn:hover {
            background: #e9ecef;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-4">
            <i class="fas fa-id-card fa-4x" style="color: #667eea;"></i>
            <h3 class="mt-2">Student ID Wallet</h3>
            <p class="text-muted">Login to access your digital ID</p>
        </div>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label">Student Email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input type="email" name="email" class="form-control" placeholder="student@university.ac.uk" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-login mb-3">
                <i class="fas fa-sign-in-alt me-2"></i>Login
            </button>
            
            <div class="text-center mb-3">
                <a href="#" class="text-decoration-none small">Forgot Password?</a>
            </div>
            
            <hr>
            
            <div class="text-center">
                <p class="small text-muted mb-2">Or login with</p>
                <div class="row g-2">
                    <div class="col-6">
                        <div class="biometric-btn text-center">
                            <i class="fas fa-fingerprint fa-2x"></i>
                            <div class="small">Fingerprint</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="biometric-btn text-center">
                            <i class="fas fa-face-smile fa-2x"></i>
                            <div class="small">Face ID</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <div class="text-center">
                <p class="small mb-0">Don't have an account? <a href="register.php" class="text-decoration-none">Register Here</a></p>
            </div>
        </form>
    </div>
</body>
</html>