<?php
require_once 'includes/auth.php';
$auth = new Auth();

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once 'config/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    $email = $_POST['email'];
    $student_number = $_POST['student_number'];
    $phone = $_POST['phone'];
    $full_name = $_POST['full_name'];
    $course = $_POST['course'];
    $department = $_POST['department'];
    $year = $_POST['year'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // Validate university email
    if(!preg_match('/@wlv\.ac\.uk$/', $email)) {
        $error = "Email must be university domain (@wlv.ac.uk)";
    } else {
        // Check if student exists
        $check = "SELECT id FROM users WHERE student_email = :email OR student_number = :number";
        $stmt = $conn->prepare($check);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':number', $student_number);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $error = "Student already registered";
        } else {
            // Generate OTP
            $otp = $auth->generateOTP(null, $email, $phone, 'registration');
            
            // Insert user
            $insert = "INSERT INTO users (student_email, student_number, phone_number, password, full_name, course, department, year_of_study) 
                      VALUES (:email, :number, :phone, :pass, :name, :course, :dept, :year)";
            $insStmt = $conn->prepare($insert);
            $insStmt->bindParam(':email', $email);
            $insStmt->bindParam(':number', $student_number);
            $insStmt->bindParam(':phone', $phone);
            $insStmt->bindParam(':pass', $password);
            $insStmt->bindParam(':name', $full_name);
            $insStmt->bindParam(':course', $course);
            $insStmt->bindParam(':dept', $department);
            $insStmt->bindParam(':year', $year);
            
            if($insStmt->execute()) {
                $user_id = $conn->lastInsertId();
                
                // Create wallet
                $wallet = "INSERT INTO wallet (user_id, balance) VALUES (:id, 0)";
                $walletStmt = $conn->prepare($wallet);
                $walletStmt->bindParam(':id', $user_id);
                $walletStmt->execute();
                
                // Create digital ID
                $digital = "INSERT INTO digital_ids (user_id, expiry_date) VALUES (:id, DATE_ADD(CURRENT_DATE, INTERVAL 4 YEAR))";
                $digStmt = $conn->prepare($digital);
                $digStmt->bindParam(':id', $user_id);
                $digStmt->execute();
                
                // Store in session for verification
                $_SESSION['temp_user'] = $user_id;
                $_SESSION['temp_email'] = $email;
                $_SESSION['temp_phone'] = $phone;
                
                header("Location: verify.php?email=" . urlencode($email));
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Student ID Wallet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 50px 0;
        }
        .register-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            margin: 0 auto;
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
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 50px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-card">
            <div class="text-center mb-4">
                <i class="fas fa-user-plus fa-4x" style="color: #667eea;"></i>
                <h3 class="mt-2">Register Student ID</h3>
            </div>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Student Number</label>
                        <input type="text" name="student_number" class="form-control" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Student Email</label>
                        <input type="email" name="email" class="form-control" placeholder="student@wlv.ac.uk" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-control" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Course</label>
                        <input type="text" name="course" class="form-control" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Department</label>
                        <input type="text" name="department" class="form-control" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Year of Study</label>
                        <select name="year" class="form-select" required>
                            <option value="1">Year 1</option>
                            <option value="2">Year 2</option>
                            <option value="3">Year 3</option>
                            <option value="4">Year 4</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-register mb-3">
                    <i class="fas fa-paper-plane me-2"></i>Send Verification Code
                </button>
                
                <div class="text-center">
                    <small class="text-muted">
                        By continuing you agree to our Privacy Policy and Data Usage Terms
                    </small>
                </div>
                
                <hr>
                
                <div class="text-center">
                    <p class="small mb-0">Already have an account? <a href="login.php" class="text-decoration-none">Login Here</a></p>
                </div>
            </form>
        </div>
    </div>
</body>
</html>