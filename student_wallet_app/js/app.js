/**
 * Student ID Wallet Application
 * Main JavaScript File
 */

// Global variables
let currentUser = null;
let currentToken = null;
let sessionTimer = null;
let inactivityTimer = null;

// Initialize application
$(document).ready(function() {
    initializeApp();
    setupEventListeners();
    checkStoredSession();
});

// Initialize app
function initializeApp() {
    console.log('Student ID Wallet App initialized');
    loadFontAwesome();
}

// Load Font Awesome if not already loaded
function loadFontAwesome() {
    if (!document.querySelector('link[href*="font-awesome"]')) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css';
        document.head.appendChild(link);
    }
}

// Check for stored session
function checkStoredSession() {
    const storedUser = localStorage.getItem('studentWalletUser');
    const storedToken = localStorage.getItem('studentWalletToken');
    const storedTime = localStorage.getItem('studentWalletLoginTime');
    
    if (storedUser && storedToken && storedTime) {
        const loginTime = new Date(parseInt(storedTime));
        const now = new Date();
        const hoursDiff = (now - loginTime) / (1000 * 60 * 60);
        
        // Check if session is still valid (24 hours)
        if (hoursDiff < 24) {
            currentUser = JSON.parse(storedUser);
            currentToken = storedToken;
            showDashboard();
            startInactivityTimer();
            loadDashboardData();
        } else {
            // Session expired
            localStorage.removeItem('studentWalletUser');
            localStorage.removeItem('studentWalletToken');
            localStorage.removeItem('studentWalletLoginTime');
        }
    }
}

// Setup event listeners
function setupEventListeners() {
    $('#loginNavBtn').click(() => showLogin());
    $('#registerNavBtn').click(() => showRegister());
    $('#homeLink').click(() => showWelcome());
    
    $('#loginForm').submit((e) => {
        e.preventDefault();
        login();
    });
    
    $('#registerForm').submit((e) => {
        e.preventDefault();
        register();
    });
    
    // Add event listener for Enter key
    $(document).keypress(function(e) {
        if (e.which === 13) {
            if ($('#loginScreen').is(':visible')) {
                login();
            } else if ($('#registerScreen').is(':visible')) {
                register();
            }
        }
    });
}

// Show welcome screen
function showWelcome() {
    if (currentUser) {
        showDashboard();
    } else {
        $('#welcomeScreen').fadeIn();
        $('#loginScreen').hide();
        $('#registerScreen').hide();
        $('#dashboardScreen').hide();
    }
}

// Show login screen
function showLogin() {
    $('#welcomeScreen').hide();
    $('#loginScreen').fadeIn();
    $('#registerScreen').hide();
    $('#dashboardScreen').hide();
    $('#loginEmail').focus();
}

// Show register screen
function showRegister() {
    $('#welcomeScreen').hide();
    $('#loginScreen').hide();
    $('#registerScreen').fadeIn();
    $('#dashboardScreen').hide();
    $('#regName').focus();
}

// Login function
function login() {
    const email = $('#loginEmail').val().trim();
    const password = $('#loginPassword').val();
    
    if (!email || !password) {
        showNotification('Please fill in all fields', 'error');
        return;
    }
    
    if (!email.endsWith('@wlv.ac.uk')) {
        showNotification('Please use your university email (@wlv.ac.uk)', 'error');
        return;
    }
    
    showLoading();
    
    $.ajax({
        url: 'api/auth.php?action=login',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ email: email, password: password }),
        success: function(response) {
            hideLoading();
            if (response.success) {
                currentUser = response.user;
                currentToken = response.token;
                
                if ($('#rememberMe').is(':checked')) {
                    localStorage.setItem('studentWalletUser', JSON.stringify(currentUser));
                    localStorage.setItem('studentWalletToken', currentToken);
                    localStorage.setItem('studentWalletLoginTime', Date.now().toString());
                }
                
                showNotification('Login successful! Welcome back!', 'success');
                showDashboard();
                startInactivityTimer();
                loadDashboardData();
            } else {
                showNotification(response.message || 'Invalid credentials', 'error');
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            console.error('Login error:', error);
            showNotification('Network error. Please check your connection.', 'error');
        }
    });
}

// Register function
function register() {
    const name = $('#regName').val().trim();
    const email = $('#regEmail').val().trim();
    const studentNumber = $('#regStudentNumber').val().trim();
    const phone = $('#regPhone').val().trim();
    const password = $('#regPassword').val();
    const confirmPassword = $('#regConfirmPassword').val();
    
    // Validation
    if (!name || !email || !studentNumber || !phone || !password) {
        showNotification('Please fill in all fields', 'error');
        return;
    }
    
    if (!email.endsWith('@wlv.ac.uk')) {
        showNotification('Must use university email (@wlv.ac.uk)', 'error');
        return;
    }
    
    if (password !== confirmPassword) {
        showNotification('Passwords do not match', 'error');
        return;
    }
    
    if (password.length < 6) {
        showNotification('Password must be at least 6 characters', 'error');
        return;
    }
    
    if (!/^[0-9]{7,8}$/.test(studentNumber)) {
        showNotification('Invalid student number format', 'error');
        return;
    }
    
    if (!/^[+]{0,1}[0-9]{10,15}$/.test(phone)) {
        showNotification('Invalid phone number format', 'error');
        return;
    }
    
    showLoading();
    
    $.ajax({
        url: 'api/auth.php?action=register',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            full_name: name,
            email: email,
            student_number: studentNumber,
            phone_number: phone,
            password: password
        }),
        success: function(response) {
            hideLoading();
            if (response.success) {
                showNotification('Registration successful! Please login.', 'success');
                showLogin();
                $('#registerForm')[0].reset();
            } else {
                showNotification(response.message || 'Registration failed', 'error');
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            console.error('Registration error:', error);
            showNotification('Registration failed. Please try again.', 'error');
        }
    });
}

// Show dashboard
function showDashboard() {
    $('#welcomeScreen').hide();
    $('#loginScreen').hide();
    $('#registerScreen').hide();
    $('#dashboardScreen').fadeIn();
    
    // Update navigation buttons
    $('#navButtons').html(`
        <div class="dropdown">
            <button class="btn btn-link nav-link dropdown-toggle text-white" type="button" 
                    id="userDropdown" data-bs-toggle="dropdown">
                <i class="fas fa-user-circle"></i> ${escapeHtml(currentUser.full_name.split(' ')[0])}
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#" onclick="viewProfile()">
                    <i class="fas fa-user"></i> Profile
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="changePassword()">
                    <i class="fas fa-key"></i> Change Password
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="#" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a></li>
            </ul>
        </div>
    `);
}

// Load all dashboard data
function loadDashboardData() {
    loadUserInfo();
    loadWalletBalance();
    loadTransactions();
    loadNotifications();
    loadAttendanceSummary();
    loadUpcomingEvents();
}

// Load user information
function loadUserInfo() {
    $.ajax({
        url: `api/student.php?action=info&user_id=${currentUser.id}`,
        method: 'GET',
        headers: { 'Authorization': `Bearer ${currentToken}` },
        success: function(response) {
            if (response.success) {
                const student = response.student;
                $('#userName').text(student.full_name);
                $('#userStudentNumber').text(`Student No: ${student.student_number}`);
                $('#userUniversity').text('University of Wolverhampton');
                $('#cardName').text(student.full_name);
                $('#cardStudentNumber').text(student.student_number);
                $('#cardCourse').text(student.course || 'Computer Science');
                $('#cardExpiry').text(student.expiry_date || '2028-12-31');
                
                // Generate QR code
                if (student.qr_data) {
                    generateQRCode(student.qr_data);
                }
                
                // Update profile photo if available
                if (student.profile_photo) {
                    $('#profilePhoto').attr('src', student.profile_photo);
                    $('#cardPhoto').attr('src', student.profile_photo);
                }
            }
        },
        error: function(xhr) {
            console.error('Failed to load user info:', xhr);
        }
    });
}

// Load wallet balance
function loadWalletBalance() {
    $.ajax({
        url: `api/wallet.php?action=balance&user_id=${currentUser.id}`,
        method: 'GET',
        headers: { 'Authorization': `Bearer ${currentToken}` },
        success: function(response) {
            if (response.success) {
                const balance = response.balance;
                $('#walletBalance').text(`£${balance.toFixed(2)}`);
                $('#walletBalanceDisplay').text(`£${balance.toFixed(2)}`);
            }
        },
        error: function(xhr) {
            console.error('Failed to load balance:', xhr);
        }
    });
}

// Load transactions
function loadTransactions() {
    $.ajax({
        url: `api/wallet.php?action=transactions&user_id=${currentUser.id}`,
        method: 'GET',
        headers: { 'Authorization': `Bearer ${currentToken}` },
        success: function(response) {
            if (response.success && response.transactions.length > 0) {
                let html = '<div class="list-group list-group-flush">';
                response.transactions.slice(0, 5).forEach(trans => {
                    const amountClass = trans.transaction_type === 'deposit' ? 'positive' : 'negative';
                    const amountSign = trans.transaction_type === 'deposit' ? '+' : '-';
                    const icon = trans.transaction_type === 'deposit' ? 'fa-arrow-down' : 'fa-arrow-up';
                    html += `
                        <div class="list-group-item transaction-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas ${icon} me-2 ${amountClass}"></i>
                                    <strong>${escapeHtml(trans.description || trans.transaction_type)}</strong>
                                    <br>
                                    <small class="text-muted">${formatDate(trans.created_at)}</small>
                                </div>
                                <div class="transaction-amount ${amountClass}">
                                    ${amountSign}£${Math.abs(trans.amount).toFixed(2)}
                                </div>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                $('#transactionsList').html(html);
            } else {
                $('#transactionsList').html(`
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-receipt fa-2x mb-2"></i>
                        <p>No transactions yet</p>
                    </div>
                `);
            }
        },
        error: function(xhr) {
            console.error('Failed to load transactions:', xhr);
        }
    });
}

// Load notifications
function loadNotifications() {
    $.ajax({
        url: `api/student.php?action=notifications&user_id=${currentUser.id}`,
        method: 'GET',
        headers: { 'Authorization': `Bearer ${currentToken}` },
        success: function(response) {
            if (response.success && response.notifications.length > 0) {
                const unreadCount = response.notifications.filter(n => !n.is_read).length;
                if (unreadCount > 0) {
                    $('#notificationBadge').text(unreadCount).show();
                }
            }
        }
    });
}

// Load attendance summary
function loadAttendanceSummary() {
    $.ajax({
        url: `api/student.php?action=attendance&user_id=${currentUser.id}`,
        method: 'GET',
        headers: { 'Authorization': `Bearer ${currentToken}` },
        success: function(response) {
            if (response.success && response.attendance.length > 0) {
                const present = response.attendance.filter(a => a.status === 'present').length;
                const total = response.attendance.length;
                const percentage = (present / total * 100).toFixed(1);
                $('#attendancePercentage').text(`${percentage}%`);
                $('#attendanceProgress').css('width', `${percentage}%`);
            }
        }
    });
}

// Load upcoming events
function loadUpcomingEvents() {
    $.ajax({
        url: 'api/student.php?action=events',
        method: 'GET',
        headers: { 'Authorization': `Bearer ${currentToken}` },
        success: function(response) {
            if (response.success && response.events.length > 0) {
                let html = '<div class="list-group">';
                response.events.slice(0, 3).forEach(event => {
                    html += `
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-calendar-alt text-primary me-2"></i>
                                    <strong>${escapeHtml(event.title)}</strong>
                                    <br>
                                    <small class="text-muted">
                                        ${formatDate(event.event_date)} at ${event.event_time || 'TBD'}
                                    </small>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="registerForEvent(${event.id})">
                                    Register
                                </button>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                $('#upcomingEvents').html(html);
            } else {
                $('#upcomingEvents').html(`
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                        <p>No upcoming events</p>
                    </div>
                `);
            }
        }
    });
}

// Add balance to wallet
function addBalance() {
    const amount = prompt('Enter amount to add (minimum £5):', '10');
    
    if (amount && !isNaN(amount) && amount >= 5) {
        showLoading();
        
        $.ajax({
            url: 'api/wallet.php?action=add',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'Authorization': `Bearer ${currentToken}` },
            data: JSON.stringify({
                user_id: currentUser.id,
                amount: parseFloat(amount)
            }),
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showNotification(`£${amount} added successfully!`, 'success');
                    loadWalletBalance();
                    loadTransactions();
                } else {
                    showNotification(response.message || 'Failed to add balance', 'error');
                }
            },
            error: function() {
                hideLoading();
                showNotification('Network error', 'error');
            }
        });
    } else if (amount) {
        showNotification('Minimum amount is £5', 'error');
    }
}

// Make payment
function makePayment() {
    const amount = prompt('Enter payment amount:', '');
    const description = prompt('Payment description:', 'Campus Purchase');
    
    if (amount && !isNaN(amount) && amount > 0) {
        showLoading();
        
        $.ajax({
            url: 'api/wallet.php?action=pay',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'Authorization': `Bearer ${currentToken}` },
            data: JSON.stringify({
                user_id: currentUser.id,
                amount: parseFloat(amount),
                description: description
            }),
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showNotification(`Payment of £${amount} successful!`, 'success');
                    loadWalletBalance();
                    loadTransactions();
                } else {
                    showNotification(response.message || 'Insufficient balance', 'error');
                }
            },
            error: function() {
                hideLoading();
                showNotification('Payment failed', 'error');
            }
        });
    }
}

// View full ID card
function viewFullCard() {
    const modal = new bootstrap.Modal(document.getElementById('idCardModal'));
    modal.show();
}

// Show QR scanner
function showQRScanner() {
    showNotification('QR Scanner coming soon!', 'info');
}

// Show library books
function showLibrary() {
    $.ajax({
        url: `api/student.php?action=library&user_id=${currentUser.id}`,
        method: 'GET',
        headers: { 'Authorization': `Bearer ${currentToken}` },
        success: function(response) {
            if (response.success && response.books.length > 0) {
                let message = '📚 Your Borrowed Books:\n\n';
                response.books.forEach(book => {
                    message += `${book.book_title}\n`;
                    message += `Due: ${formatDate(book.due_date)}\n`;
                    message += `Status: ${book.status}\n`;
                    if (book.fine_amount > 0) {
                        message += `Fine: £${book.fine_amount}\n`;
                    }
                    message += '\n';
                });
                alert(message);
            } else {
                showNotification('No books borrowed', 'info');
            }
        }
    });
}

// Show events
function showEvents() {
    $.ajax({
        url: 'api/student.php?action=events',
        method: 'GET',
        headers: { 'Authorization': `Bearer ${currentToken}` },
        success: function(response) {
            if (response.success && response.events.length > 0) {
                let message = '🎉 Upcoming Events:\n\n';
                response.events.forEach(event => {
                    message += `${event.title}\n`;
                    message += `📅 ${formatDate(event.event_date)}\n`;
                    message += `📍 ${event.location}\n`;
                    message += `👥 ${event.current_attendees}/${event.max_attendees} registered\n\n`;
                });
                alert(message);
            } else {
                showNotification('No upcoming events', 'info');
            }
        }
    });
}

// Register for event
function registerForEvent(eventId) {
    if (confirm('Are you sure you want to register for this event?')) {
        showLoading();
        
        $.ajax({
            url: 'api/student.php?action=register_event',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'Authorization': `Bearer ${currentToken}` },
            data: JSON.stringify({
                user_id: currentUser.id,
                event_id: eventId
            }),
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showNotification('Successfully registered for event!', 'success');
                    loadUpcomingEvents();
                } else {
                    showNotification(response.message || 'Registration failed', 'error');
                }
            },
            error: function() {
                hideLoading();
                showNotification('Registration failed', 'error');
            }
        });
    }
}

// Generate QR code
function generateQRCode(data) {
    const qrCanvas = document.getElementById('qrCanvas');
    if (qrCanvas) {
        try {
            // Simple QR code generation using canvas
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            canvas.width = 128;
            canvas.height = 128;
            
            // Draw simple QR-like pattern (for demo)
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, 128, 128);
            ctx.fillStyle = '#000000';
            
            const dataStr = JSON.stringify(data);
            for (let i = 0; i < dataStr.length; i++) {
                const x = (i % 16) * 8;
                const y = Math.floor(i / 16) * 8;
                if (dataStr.charCodeAt(i) % 2 === 0) {
                    ctx.fillRect(x, y, 6, 6);
                }
            }
            
            qrCanvas.parentNode.replaceChild(canvas, qrCanvas);
            canvas.id = 'qrCanvas';
        } catch(e) {
            console.error('QR Code generation failed:', e);
        }
    }
}

// View profile
function viewProfile() {
    // Implement profile view
    alert('Profile view coming soon!');
}

// Change password
function changePassword() {
    const oldPassword = prompt('Enter current password:');
    if (!oldPassword) return;
    
    const newPassword = prompt('Enter new password (min 6 characters):');
    if (!newPassword || newPassword.length < 6) {
        showNotification('Password must be at least 6 characters', 'error');
        return;
    }
    
    const confirmPassword = prompt('Confirm new password:');
    if (newPassword !== confirmPassword) {
        showNotification('Passwords do not match', 'error');
        return;
    }
    
    showLoading();
    
    $.ajax({
        url: 'api/student.php?action=change_password',
        method: 'POST',
        contentType: 'application/json',
        headers: { 'Authorization': `Bearer ${currentToken}` },
        data: JSON.stringify({
            user_id: currentUser.id,
            old_password: oldPassword,
            new_password: newPassword
        }),
        success: function(response) {
            hideLoading();
            if (response.success) {
                showNotification('Password changed successfully!', 'success');
            } else {
                showNotification(response.message || 'Password change failed', 'error');
            }
        },
        error: function() {
            hideLoading();
            showNotification('Failed to change password', 'error');
        }
    });
}

// Logout function
function logout() {
    if (confirm('Are you sure you want to logout?')) {
        localStorage.removeItem('studentWalletUser');
        localStorage.removeItem('studentWalletToken');
        localStorage.removeItem('studentWalletLoginTime');
        currentUser = null;
        currentToken = null;
        
        if (inactivityTimer) {
            clearTimeout(inactivityTimer);
        }
        
        showNotification('Logged out successfully', 'success');
        showWelcome();
        
        $('#navButtons').html(`
            <a class="nav-link" href="#" id="loginNavBtn">Login</a>
            <a class="nav-link" href="#" id="registerNavBtn">Register</a>
        `);
        
        setupEventListeners();
    }
}

// Start inactivity timer (5 minutes)
function startInactivityTimer() {
    if (inactivityTimer) {
        clearTimeout(inactivityTimer);
    }
    
    let inactivityTimeout = 5 * 60 * 1000; // 5 minutes
    
    const resetTimer = () => {
        clearTimeout(inactivityTimer);
        inactivityTimer = setTimeout(() => {
            showNotification('Session timeout due to inactivity', 'warning');
            logout();
        }, inactivityTimeout);
    };
    
    const events = ['mousemove', 'keypress', 'click', 'scroll', 'touchstart'];
    events.forEach(event => {
        document.removeEventListener(event, resetTimer);
        document.addEventListener(event, resetTimer);
    });
    
    resetTimer();
}

// Show notification
function showNotification(message, type) {
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    const toast = $(`
        <div class="toast align-items-center text-white bg-${type === 'error' ? 'danger' : type} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas ${icons[type] || 'fa-info-circle'} me-2"></i>
                    ${escapeHtml(message)}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `);
    
    $('.toast-container').remove();
    $('body').append('<div class="toast-container"></div>');
    $('.toast-container').append(toast);
    
    const bsToast = new bootstrap.Toast(toast[0], { autohide: true, delay: 3000 });
    bsToast.show();
    
    toast.on('hidden.bs.toast', function() {
        toast.remove();
        if ($('.toast-container').children().length === 0) {
            $('.toast-container').remove();
        }
    });
}

// Show loading spinner
function showLoading() {
    $('body').append(`
        <div class="loading-overlay">
            <div class="loading-spinner"></div>
        </div>
    `);
}

// Hide loading spinner
function hideLoading() {
    $('.loading-overlay').remove();
}

// Utility: Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
}

// Utility: Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Utility: Format currency
function formatCurrency(amount, currency = 'GBP') {
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: currency
    }).format(amount);
}