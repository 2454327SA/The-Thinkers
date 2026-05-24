// API Configuration
const API_BASE = 'http://localhost/student-wallet/backend/api';

// Check if already logged in
if (localStorage.getItem('token')) {
    window.location.href = 'dashboard.html';
}

// Tab Switching
document.getElementById('loginTab').addEventListener('click', () => {
    document.getElementById('loginForm').style.display = 'block';
    document.getElementById('registerForm').style.display = 'none';
    document.getElementById('loginTab').classList.add('text-purple-600', 'border-purple-600');
    document.getElementById('registerTab').classList.remove('text-purple-600', 'border-purple-600');
    document.getElementById('registerTab').classList.add('text-gray-500');
});

document.getElementById('registerTab').addEventListener('click', () => {
    document.getElementById('loginForm').style.display = 'none';
    document.getElementById('registerForm').style.display = 'block';
    document.getElementById('registerTab').classList.add('text-purple-600', 'border-purple-600');
    document.getElementById('loginTab').classList.remove('text-purple-600', 'border-purple-600');
    document.getElementById('loginTab').classList.add('text-gray-500');
});

// Login Handler
document.getElementById('loginFormElement').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const email = document.getElementById('loginEmail').value;
    const password = document.getElementById('loginPassword').value;
    
    try {
        const response = await fetch(`${API_BASE}/login.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ email, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            localStorage.setItem('token', data.token);
            localStorage.setItem('user', JSON.stringify(data.user));
            showNotification('Login successful! Redirecting...', 'success');
            setTimeout(() => {
                if (data.user.role === 'admin') {
                    window.location.href = 'admin.html';
                } else {
                    window.location.href = 'dashboard.html';
                }
            }, 1000);
        } else {
            showNotification(data.error || 'Login failed', 'error');
        }
    } catch (error) {
        showNotification('Network error. Please try again.', 'error');
    }
});

// Register Handler
document.getElementById('registerFormElement').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const name = document.getElementById('regName').value;
    const email = document.getElementById('regEmail').value;
    const student_id = document.getElementById('regStudentId').value;
    const password = document.getElementById('regPassword').value;
    
    try {
        const response = await fetch(`${API_BASE}/register.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ name, email, student_id, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Registration successful! Please login.', 'success');
            document.getElementById('loginTab').click();
            document.getElementById('loginFormElement').reset();
        } else {
            showNotification(data.error || 'Registration failed', 'error');
        }
    } catch (error) {
        showNotification('Network error. Please try again.', 'error');
    }
});

// Notification Function
function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 animate-fade-in-up ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    } text-white`;
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);
}