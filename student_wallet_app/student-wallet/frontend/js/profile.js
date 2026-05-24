const token = localStorage.getItem('token');
if (!token) window.location.href = 'index.html';

async function loadProfile() {
    try {
        const response = await fetch('http://localhost/student-wallet/backend/api/profile.php', {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await response.json();
        
        if (data.success) {
            const user = data.user;
            document.getElementById('profileName').value = user.name;
            document.getElementById('profileEmail').value = user.email;
            document.getElementById('profileStudentId').value = user.student_id;
            document.getElementById('profilePhone').value = user.phone || '';
            document.getElementById('profileDepartment').value = user.department || 'Computer Science';
            document.getElementById('profileSemester').value = user.semester || '3';
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

document.getElementById('profileForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const updatedData = {
        name: document.getElementById('profileName').value,
        phone: document.getElementById('profilePhone').value,
        department: document.getElementById('profileDepartment').value,
        semester: document.getElementById('profileSemester').value
    };
    
    try {
        const response = await fetch('http://localhost/student-wallet/backend/api/update-profile.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify(updatedData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Profile updated successfully!');
            loadProfile();
        } else {
            alert('Update failed: ' + data.error);
        }
    } catch (error) {
        alert('Network error. Please try again.');
    }
});

function logout() {
    localStorage.removeItem('token');
    window.location.href = 'index.html';
}

loadProfile();