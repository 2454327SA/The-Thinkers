<?php
require_once 'includes/auth.php';
$auth = new Auth();
if(!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];

// Fetch user data
$query = "SELECT * FROM users WHERE id = :id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch wallet balance
$wallet_query = "SELECT balance FROM wallet WHERE user_id = :id";
$wallet_stmt = $conn->prepare($wallet_query);
$wallet_stmt->bindParam(':id', $user_id);
$wallet_stmt->execute();
$wallet = $wallet_stmt->fetch(PDO::FETCH_ASSOC);
$balance = $wallet ? $wallet['balance'] : 0;

// Fetch unread notifications count
$notif_count_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = :id AND is_read = 0";
$notif_count_stmt = $conn->prepare($notif_count_query);
$notif_count_stmt->bindParam(':id', $user_id);
$notif_count_stmt->execute();
$notif_count = $notif_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Fetch recent transactions
$trans_query = "SELECT * FROM transactions WHERE user_id = :id ORDER BY created_at DESC LIMIT 5";
$trans_stmt = $conn->prepare($trans_query);
$trans_stmt->bindParam(':id', $user_id);
$trans_stmt->execute();
$transactions = $trans_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent notifications
$notif_query = "SELECT * FROM notifications WHERE user_id = :id ORDER BY created_at DESC LIMIT 5";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->bindParam(':id', $user_id);
$notif_stmt->execute();
$notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch attendance stats
$attendance_query = "SELECT COUNT(*) as total, SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present 
                     FROM attendance WHERE user_id = :id AND attendance_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$attendance_stmt = $conn->prepare($attendance_query);
$attendance_stmt->bindParam(':id', $user_id);
$attendance_stmt->execute();
$attendance = $attendance_stmt->fetch(PDO::FETCH_ASSOC);
$attendance_percentage = $attendance['total'] > 0 ? round(($attendance['present'] / $attendance['total']) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Student ID Wallet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
        }
        .greeting-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
        }
        .quick-action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .quick-action-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .quick-action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .quick-action-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea20 0%, #764ba220 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }
        .quick-action-icon i {
            font-size: 28px;
            color: #667eea;
        }
        .activity-timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 20px;
            border-left: 2px solid #e0e0e0;
            padding-left: 20px;
            margin-left: 10px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -8px;
            top: 0;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #667eea;
        }
        .balance-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 25px;
            color: white;
            margin-bottom: 20px;
        }
        .balance-amount {
            font-size: 48px;
            font-weight: bold;
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        .animated-card {
            animation: slideIn 0.5s ease forwards;
        }
    </style>
</head>
<body>
    <div class="loader" id="loader">
        <div class="loader-spinner"></div>
    </div>

    <nav class="navbar navbar-custom navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-id-card me-2"></i>Student ID Wallet
            </a>
            <div class="d-flex align-items-center">
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-bell me-2"></i>
                        <?php if($notif_count > 0): ?>
                            <span class="badge bg-danger"><?php echo $notif_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php foreach($notifications as $notif): ?>
                            <li><a class="dropdown-item" href="#">
                                <strong><?php echo htmlspecialchars($notif['title']); ?></strong><br>
                                <small><?php echo htmlspecialchars($notif['message']); ?></small>
                            </a></li>
                        <?php endforeach; ?>
                        <?php if(count($notifications) == 0): ?>
                            <li><a class="dropdown-item" href="#">No new notifications</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="dropdown ms-3">
                    <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-2"></i><?php echo htmlspecialchars($user['full_name']); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Welcome Section -->
        <div class="dashboard-header animated-card">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-2">Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>!</h2>
                    <p class="mb-0">Here's what's happening with your student ID wallet today.</p>
                    <div class="greeting-badge mt-2">
                        <i class="fas fa-graduation-cap me-1"></i>
                        <?php echo htmlspecialchars($user['course']); ?> • Year <?php echo $user['year_of_study']; ?>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="balance-card p-3 mb-0">
                        <small>Wallet Balance</small>
                        <div class="balance-amount">£<?php echo number_format($balance, 2); ?></div>
                        <button class="btn btn-sm btn-light mt-2" onclick="location.href='wallet.php'">
                            <i class="fas fa-plus me-1"></i>Top Up
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-action-grid">
            <div class="quick-action-card" onclick="location.href='digital_id.php'">
                <div class="quick-action-icon">
                    <i class="fas fa-id-card"></i>
                </div>
                <h6>Digital ID Card</h6>
                <small class="text-muted">View & share</small>
            </div>
            <div class="quick-action-card" onclick="location.href='qr_scanner.php'">
                <div class="quick-action-icon">
                    <i class="fas fa-qrcode"></i>
                </div>
                <h6>Scan QR Code</h6>
                <small class="text-muted">Attendance & payments</small>
            </div>
            <div class="quick-action-card" onclick="location.href='wallet.php'">
                <div class="quick-action-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <h6>Top Up Wallet</h6>
                <small class="text-muted">Add funds</small>
            </div>
            <div class="quick-action-card" onclick="location.href='transactions.php'">
                <div class="quick-action-icon">
                    <i class="fas fa-history"></i>
                </div>
                <h6>Transactions</h6>
                <small class="text-muted">View history</small>
            </div>
        </div>

        <div class="row">
            <!-- Statistics Cards -->
            <div class="col-md-3 mb-4">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stats-number"><?php echo $attendance_percentage; ?>%</div>
                    <div class="stats-label">Attendance Rate</div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="stats-number"><?php echo count($transactions); ?></div>
                    <div class="stats-label">This Month</div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stats-number"><?php echo date('d M Y'); ?></div>
                    <div class="stats-label">Last Active</div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stats-number" id="currentTime">--:--</div>
                    <div class="stats-label">Current Time</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Transactions -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Transactions</h5>
                    </div>
                    <div class="card-body">
                        <?php if(count($transactions) > 0): ?>
                            <?php foreach($transactions as $trans): ?>
                                <div class="transaction-item transaction-<?php echo $trans['transaction_type']; ?>">
                                    <div class="transaction-icon">
                                        <?php if($trans['transaction_type'] == 'deposit'): ?>
                                            <i class="fas fa-arrow-down text-success"></i>
                                        <?php else: ?>
                                            <i class="fas fa-arrow-up text-danger"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="transaction-details">
                                        <strong><?php echo htmlspecialchars($trans['description']); ?></strong><br>
                                        <small><?php echo date('d M Y, h:i A', strtotime($trans['created_at'])); ?></small>
                                    </div>
                                    <div class="transaction-amount <?php echo $trans['transaction_type'] == 'deposit' ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $trans['transaction_type'] == 'deposit' ? '+' : '-'; ?>£<?php echo number_format($trans['amount'], 2); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center mb-0">No transactions yet</p>
                        <?php endif; ?>
                        <div class="text-center mt-3">
                            <a href="transactions.php" class="btn btn-outline-gradient btn-sm">View All Transactions</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Timeline -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <div class="activity-timeline">
                            <?php foreach($notifications as $notif): ?>
                                <div class="timeline-item">
                                    <strong><?php echo htmlspecialchars($notif['title']); ?></strong>
                                    <p class="mb-0 small"><?php echo htmlspecialchars($notif['message']); ?></p>
                                    <small class="text-muted"><?php echo time_elapsed_string($notif['created_at']); ?></small>
                                </div>
                            <?php endforeach; ?>
                            <?php if(count($notifications) == 0): ?>
                                <p class="text-muted text-center">No recent activity</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Digital ID Preview -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="digital-id-card" onclick="location.href='digital_id.php'">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center">
                            <div class="id-photo">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="id-info">
                                <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                                <p>Student No: <?php echo htmlspecialchars($user['student_number']); ?></p>
                                <p><?php echo htmlspecialchars($user['course']); ?> | <?php echo htmlspecialchars($user['department']); ?></p>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <div id="mini-qr"></div>
                            <small class="text-muted">Tap to view full card</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs2-fix/qrcode.min.js"></script>
    <script>
        // Update current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
            document.getElementById('currentTime').textContent = timeString;
        }
        updateTime();
        setInterval(updateTime, 1000);

        // Generate mini QR code
        new QRCode(document.getElementById("mini-qr"), {
            text: "<?php echo $user['student_number']; ?>",
            width: 100,
            height: 100,
            colorDark: "#667eea",
            colorLight: "#ffffff"
        });

        // Show loader on page load
        window.addEventListener('load', function() {
            document.getElementById('loader').classList.remove('active');
        });
    </script>
</body>
</html>

<?php
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;
    
    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }
    
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>