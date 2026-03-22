<?php
session_start();
require_once '../includes/auth.php';
$auth = new Auth();

// Admin check - you should implement proper admin role check
if(!isset($_SESSION['user_id']) || $_SESSION['user_email'] != 'admin@wlv.ac.uk') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Get statistics
$stats = [];

// Total students
$query = "SELECT COUNT(*) as total FROM users WHERE is_verified = 1";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['students'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total transactions today
$query = "SELECT COUNT(*) as total, SUM(amount) as amount FROM transactions WHERE DATE(created_at) = CURDATE()";
$stmt = $conn->prepare($query);
$stmt->execute();
$today = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['transactions_today'] = $today['total'];
$stats['amount_today'] = $today['amount'] ?? 0;

// Total wallet balance
$query = "SELECT SUM(balance) as total FROM wallet";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_balance'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Recent registrations
$query = "SELECT * FROM users ORDER BY created_at DESC LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->execute();
$recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Student ID Wallet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .admin-sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
        }
        .admin-sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            transition: all 0.3s;
        }
        .admin-sidebar .nav-link:hover,
        .admin-sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .admin-sidebar .nav-link i {
            width: 25px;
            margin-right: 10px;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0 admin-sidebar">
                <div class="p-3">
                    <h4 class="mb-4"><i class="fas fa-crown me-2"></i>Admin Panel</h4>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="index.php">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                    <a class="nav-link" href="manage_students.php">
                        <i class="fas fa-users"></i>Manage Students
                    </a>
                    <a class="nav-link" href="manage_wallet.php">
                        <i class="fas fa-wallet"></i>Wallet Management
                    </a>
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar"></i>Reports
                    </a>
                    <a class="nav-link" href="events.php">
                        <i class="fas fa-calendar"></i>Events
                    </a>
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog"></i>Settings
                    </a>
                    <hr class="bg-light">
                    <a class="nav-link" href="../dashboard.php">
                        <i class="fas fa-arrow-left"></i>Back to App
                    </a>
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i>Logout
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <h2 class="mb-4">Admin Dashboard</h2>
                
                <!-- Statistics -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted">Total Students</h6>
                                    <h3 class="mb-0"><?php echo number_format($stats['students']); ?></h3>
                                </div>
                                <div class="stat-icon bg-primary bg-opacity-10">
                                    <i class="fas fa-users text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted">Today's Transactions</h6>
                                    <h3 class="mb-0"><?php echo number_format($stats['transactions_today']); ?></h3>
                                </div>
                                <div class="stat-icon bg-success bg-opacity-10">
                                    <i class="fas fa-exchange-alt text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted">Today's Volume</h6>
                                    <h3 class="mb-0">£<?php echo number_format($stats['amount_today'], 2); ?></h3>
                                </div>
                                <div class="stat-icon bg-info bg-opacity-10">
                                    <i class="fas fa-pound-sign text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted">Total Wallet Balance</h6>
                                    <h3 class="mb-0">£<?php echo number_format($stats['total_balance'], 2); ?></h3>
                                </div>
                                <div class="stat-icon bg-warning bg-opacity-10">
                                    <i class="fas fa-wallet text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Registrations -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Recent Registrations</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Student Number</th>
                                        <th>Email</th>
                                        <th>Course</th>
                                        <th>Registered</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recent_users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['student_number']); ?></td>
                                            <td><?php echo htmlspecialchars($user['student_email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['course']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <?php if($user['is_verified']): ?>
                                                    <span class="badge bg-success">Verified</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>