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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>QR Scanner - Student ID Wallet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://unpkg.com/html5-qrcode/minified/html5-qrcode.min.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .scanner-wrapper {
            max-width: 600px;
            margin: 50px auto;
        }
        .scanner-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            animation: fadeIn 0.5s ease;
        }
        .scanner-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .scanner-header i {
            font-size: 50px;
            color: #667eea;
            animation: pulse 2s infinite;
        }
        #qr-reader {
            width: 100%;
            border-radius: 15px;
            overflow: hidden;
            background: #000;
        }
        #qr-reader video {
            width: 100%;
            height: auto;
        }
        .result-card {
            margin-top: 20px;
            padding: 15px;
            border-radius: 10px;
            display: none;
            animation: slideIn 0.3s ease;
        }
        .result-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-left: 4px solid #28a745;
        }
        .result-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border-left: 4px solid #dc3545;
        }
        .result-info {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            border-left: 4px solid #17a2b8;
        }
        .scan-history {
            margin-top: 20px;
            max-height: 300px;
            overflow-y: auto;
        }
        .history-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        .history-item:hover {
            transform: translateX(5px);
            background: #e9ecef;
        }
        .flash {
            animation: flash 0.5s ease;
        }
        @keyframes flash {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; background: yellow; }
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .camera-selector {
            margin-bottom: 15px;
        }
        .torch-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1000;
            transition: all 0.3s;
        }
        .torch-btn:hover {
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <div class="loader" id="loader">
        <div class="loader-spinner"></div>
    </div>

    <div class="scanner-wrapper">
        <div class="scanner-card">
            <div class="scanner-header">
                <i class="fas fa-qrcode"></i>
                <h3 class="mt-2">QR Code Scanner</h3>
                <p class="text-muted">Scan QR codes for attendance, payments, and verification</p>
            </div>
            
            <div class="camera-selector">
                <label class="form-label">Select Camera</label>
                <select id="camera-select" class="form-select">
                    <option value="">Loading cameras...</option>
                </select>
            </div>
            
            <div id="qr-reader"></div>
            
            <div id="result" class="result-card"></div>
            
            <div class="scan-history">
                <h6><i class="fas fa-history me-2"></i>Recent Scans</h6>
                <div id="history-list"></div>
            </div>
            
            <div class="mt-3 text-center">
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
                <button onclick="restartScanner()" class="btn btn-primary">
                    <i class="fas fa-sync-alt me-2"></i>Restart Scanner
                </button>
            </div>
        </div>
    </div>
    
    <div class="torch-btn" id="torch-btn" style="display: none;">
        <i class="fas fa-lightbulb"></i>
    </div>
    
    <script>
        let html5QrCode;
        let currentCameraId;
        let torchAvailable = false;
        let torchEnabled = false;
        
        // Load saved scans from localStorage
        let scanHistory = JSON.parse(localStorage.getItem('scanHistory') || '[]');
        
        function updateHistoryDisplay() {
            const historyList = document.getElementById('history-list');
            if(scanHistory.length === 0) {
                historyList.innerHTML = '<p class="text-muted text-center">No scans yet</p>';
                return;
            }
            
            historyList.innerHTML = scanHistory.slice(0, 10).map(scan => `
                <div class="history-item" onclick="showScanDetails('${scan.data}')">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-qrcode me-2"></i>
                            <strong>${scan.type}</strong><br>
                            <small>${scan.timestamp}</small>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </div>
                </div>
            `).join('');
        }
        
        function saveToHistory(type, data, details) {
            scanHistory.unshift({
                type: type,
                data: data,
                details: details,
                timestamp: new Date().toLocaleString()
            });
            if(scanHistory.length > 20) scanHistory.pop();
            localStorage.setItem('scanHistory', JSON.stringify(scanHistory));
            updateHistoryDisplay();
        }
        
        function showScanDetails(data) {
            showResult(`<strong>Scanned Data:</strong><br>${data}`, 'info');
        }
        
        async function loadCameras() {
            try {
                const cameras = await Html5Qrcode.getCameras();
                const cameraSelect = document.getElementById('camera-select');
                
                if(cameras && cameras.length) {
                    cameraSelect.innerHTML = cameras.map((camera, index) => 
                        `<option value="${camera.id}">${camera.label || `Camera ${index + 1}`}</option>`
                    ).join('');
                    
                    currentCameraId = cameras[0].id;
                    startScanner(currentCameraId);
                    
                    cameraSelect.addEventListener('change', (e) => {
                        currentCameraId = e.target.value;
                        restartScanner();
                    });
                } else {
                    cameraSelect.innerHTML = '<option value="">No cameras found</option>';
                    showResult('No cameras found on this device', 'error');
                }
            } catch(err) {
                console.error("Error loading cameras:", err);
                showResult('Error accessing camera. Please ensure you have granted camera permissions.', 'error');
            }
        }
        
        function startScanner(cameraId) {
            if(html5QrCode) {
                html5QrCode.stop().catch(() => {});
            }
            
            html5QrCode = new Html5Qrcode("qr-reader");
            const config = {
                fps: 10,
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.0,
                videoConstraints: {
                    deviceId: cameraId ? { exact: cameraId } : undefined,
                    facingMode: "environment"
                }
            };
            
            html5QrCode.start(
                cameraId ? { deviceId: { exact: cameraId } } : { facingMode: "environment" },
                config,
                (decodedText, decodedResult) => {
                    handleScanResult(decodedText);
                    
                    // Flash effect
                    const readerDiv = document.getElementById('qr-reader');
                    readerDiv.classList.add('flash');
                    setTimeout(() => readerDiv.classList.remove('flash'), 500);
                    
                    // Vibrate if supported
                    if(navigator.vibrate) navigator.vibrate(200);
                },
                (errorMessage) => {
                    // Silent fail for continuous scanning
                }
            ).catch(err => {
                console.error("Failed to start scanner", err);
                showResult('Failed to start camera. Please check permissions.', 'error');
            });
        }
        
        function restartScanner() {
            if(html5QrCode) {
                html5QrCode.stop().then(() => {
                    startScanner(currentCameraId);
                }).catch(() => {
                    startScanner(currentCameraId);
                });
            } else {
                startScanner(currentCameraId);
            }
        }
        
        async function handleScanResult(data) {
            document.getElementById('loader').classList.add('active');
            
            try {
                // Try to parse as JSON
                let parsed;
                try {
                    parsed = JSON.parse(atob(data));
                } catch(e) {
                    // Try direct JSON
                    try {
                        parsed = JSON.parse(data);
                    } catch(e2) {
                        parsed = { raw: data };
                    }
                }
                
                // Send to server for verification
                const response = await fetch('api/verify_scan.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        data: parsed,
                        user_id: <?php echo $user_id; ?>,
                        scan_time: new Date().toISOString()
                    })
                });
                
                const result = await response.json();
                
                if(result.success) {
                    showResult(`
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle fa-2x me-3 text-success"></i>
                            <div>
                                <strong>${result.type || 'Scan Successful'}</strong><br>
                                ${result.message}<br>
                                ${result.details ? `<small>${result.details}</small>` : ''}
                            </div>
                        </div>
                    `, 'success');
                    saveToHistory(result.type || 'Verification', data, result.message);
                    
                    // Play success sound
                    playBeep(true);
                } else {
                    showResult(`
                        <div class="d-flex align-items-center">
                            <i class="fas fa-times-circle fa-2x me-3 text-danger"></i>
                            <div>
                                <strong>Invalid Scan</strong><br>
                                ${result.message}
                            </div>
                        </div>
                    `, 'error');
                    saveToHistory('Invalid', data, result.message);
                    
                    // Play error sound
                    playBeep(false);
                }
            } catch(e) {
                console.error('Error processing scan:', e);
                showResult(`
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle fa-2x me-3 text-warning"></i>
                        <div>
                            <strong>Invalid QR Code</strong><br>
                            The scanned QR code format is not recognized.
                        </div>
                    </div>
                `, 'error');
                saveToHistory('Invalid', data, 'Unrecognized QR format');
                playBeep(false);
            }
            
            document.getElementById('loader').classList.remove('active');
        }
        
        function showResult(message, type) {
            const resultDiv = document.getElementById('result');
            resultDiv.className = `result-card result-${type}`;
            resultDiv.innerHTML = message;
            resultDiv.style.display = 'block';
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                if(resultDiv.style.display !== 'none') {
                    resultDiv.style.opacity = '0';
                    setTimeout(() => {
                        resultDiv.style.display = 'none';
                        resultDiv.style.opacity = '1';
                    }, 300);
                }
            }, 5000);
        }
        
        function playBeep(success) {
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator.type = 'sine';
                oscillator.frequency.value = success ? 880 : 440;
                gainNode.gain.value = 0.1;
                
                oscillator.start();
                setTimeout(() => {
                    oscillator.stop();
                    audioContext.close();
                }, 200);
            } catch(e) {
                // Audio not supported
            }
        }
        
        // Torch functionality
        async function toggleTorch() {
            if(!torchAvailable) return;
            
            torchEnabled = !torchEnabled;
            const torchBtn = document.getElementById('torch-btn');
            const icon = torchBtn.querySelector('i');
            
            try {
                await html5QrCode.applyVideoConstraints({
                    advanced: [{ torch: torchEnabled }]
                });
                icon.className = torchEnabled ? 'fas fa-lightbulb' : 'fas fa-lightbulb';
                icon.style.color = torchEnabled ? '#ffc107' : '#6c757d';
            } catch(e) {
                console.error('Torch not supported:', e);
                torchAvailable = false;
                torchBtn.style.display = 'none';
            }
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadCameras();
            updateHistoryDisplay();
            
            const torchBtn = document.getElementById('torch-btn');
            torchBtn.addEventListener('click', toggleTorch);
            
            // Check for torch support after scanner starts
            setTimeout(() => {
                if(html5QrCode && html5QrCode.isTorchSupported) {
                    torchAvailable = true;
                    torchBtn.style.display = 'flex';
                }
            }, 2000);
        });
    </script>
</body>
</html>