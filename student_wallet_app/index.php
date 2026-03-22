<?php
session_start();
if(isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student ID Wallet - Welcome</title>
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
        .welcome-card {
            background: rgba(255,255,255,0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            text-align: center;
            max-width: 450px;
            width: 100%;
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
        .university-logo {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .university-logo i {
            font-size: 50px;
            color: white;
        }
        .btn-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            margin: 10px;
            transition: transform 0.3s;
        }
        .btn-custom:hover {
            transform: translateY(-2px);
            color: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .btn-outline-custom {
            border: 2px solid #667eea;
            color: #667eea;
            background: transparent;
            padding: 12px 30px;
            border-radius: 50px;
            margin: 10px;
            transition: all 0.3s;
        }
        .btn-outline-custom:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="welcome-card">
            <div class="university-logo">
                <i class="fas fa-university"></i>
            </div>
            <h2 class="mb-3">Student ID Wallet</h2>
            <p class="text-muted mb-4">Store and access your student ID securely on your phone</p>
            
            <div class="d-grid gap-2">
                <a href="login.php" class="btn btn-custom btn-lg">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </a>
                <a href="register.php" class="btn btn-outline-custom btn-lg">
                    <i class="fas fa-id-card me-2"></i>Register New Student ID
                </a>
            </div>
            
            <hr class="my-4">
            <div class="small text-muted">
                <a href="#" class="text-decoration-none me-3">Privacy Policy</a>
                <a href="#" class="text-decoration-none">Terms of Use</a>
            </div>
        </div>
    </div>
</body>
</html>