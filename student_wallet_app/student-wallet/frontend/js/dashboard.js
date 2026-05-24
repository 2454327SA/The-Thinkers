// Check authentication
const token = localStorage.getItem('token');
if (!token) {
    window.location.href = 'index.html';
}

// Load user data
async function loadDashboard() {
    try {
        const response = await fetch('http://localhost/student-wallet/backend/api/profile.php', {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            const user = data.user;
            document.getElementById('welcomeMessage').innerHTML = `Welcome back, ${user.name}!`;
            document.getElementById('lastLogin').innerHTML = user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Today';
            document.getElementById('studentSince').innerHTML = new Date(user.created_at).getFullYear();
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
    }
}

function logout() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    window.location.href = 'index.html';
}

loadDashboard();