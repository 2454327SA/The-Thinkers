/**
 * Admin Panel JavaScript
 */

let currentChart = null;

// Check admin authentication
$(document).ready(function() {
    checkAdminAuth();
    loadDashboard();
});

function checkAdminAuth() {
    const adminLoggedIn = sessionStorage.getItem('adminLoggedIn');
    if (!adminLoggedIn) {
        const password = prompt('Enter admin password:');
        if (password === 'admin123') {
            sessionStorage.setItem('adminLoggedIn', 'true');
        } else {
            window.location.href = '../index.html';
        }
    }
}

function toggleSidebar() {
    $('#sidebar').toggleClass('active');
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        sessionStorage.removeItem('adminLoggedIn');
        window.location.href = '../index.html';
    }
}

function showLoading() {
    $('body').append(`
        <div class="loading-overlay">
            <div class="loading-spinner"></div>
        </div>
    `);
}

function hideLoading() {
    $('.loading-overlay').remove();
}

function showNotification(message, type) {
    const toast = $(`
        <div class="toast align-items-center text-white bg-${type} border-0 position-fixed top-0 end-0 m-3" 
             style="z-index: 9999;" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `);
    
    $('body').append(toast);
    const bsToast = new bootstrap.Toast(toast[0], { autohide: true, delay: 3000 });
    bsToast.show();
    
    toast.on('hidden.bs.toast', function() {
        toast.remove();
    });
}

function loadDashboard() {
    $('#pageTitle').text('Dashboard');
    showLoading();
    
    $.ajax({
        url: '../api/admin.php?action=dashboard',
        method: 'GET',
        headers: { 'Authorization': 'Bearer admin123' },
        success: function(response) {
            hideLoading();
            if (response.success) {
                const data = response.data;
                
                let html = `
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon primary">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-value">${data.total_users}</div>
                                <div class="stat-label">Total Users</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon success">
                                    <i class="fas fa-wallet"></i>
                                </div>
                                <div class="stat-value">£${data.total_balance}</div>
                                <div class="stat-label">Total Balance</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon warning">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                                <div class="stat-value">${data.today_transactions}</div>
                                <div class="stat-label">Today's Transactions</div>
                                <small>£${data.today_volume}</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon info">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="stat-value">${data.total_events}</div>
                                <div class="stat-label">Active Events</div>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-4">
                        <div class="col-md-8">
                            <div class="data-table p-3">
                                <h5>Recent Users</h5>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Student Number</th>
                                            <th>Registered</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                `;
                
                data.recent_users.forEach(user => {
                    html += `
                        <tr>
                            <td>${escapeHtml(user.full_name)}</td>
                            <td>${escapeHtml(user.email)}</td>
                            <td>${user.student_number}</td>
                            <td>${formatDate(user.created_at)}</td>
                        </tr>
                    `;
                });
                
                html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="data-table p-3">
                                <h5>Quick Actions</h5>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-primary" onclick="loadUsers()">
                                        <i class="fas fa-users"></i> Manage Users
                                    </button>
                                    <button class="btn btn-success" onclick="loadTransactions()">
                                        <i class="fas fa-chart-line"></i> View Transactions
                                    </button>
                                    <button class="btn btn-info" onclick="loadEvents()">
                                        <i class="fas fa-calendar-plus"></i> Create Event
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                $('#contentArea').html(html);
            } else {
                showNotification('Failed to load dashboard', 'danger');
            }
        },
        error: function() {
            hideLoading();
            showNotification('Network error', 'danger');
        }
    });
}

function loadUsers() {
    $('#pageTitle').text('Manage Users');
    showLoading();
    
    $.ajax({
        url: '../api/admin.php?action=users',
        method: 'GET',
        headers: { 'Authorization': 'Bearer admin123' },
        success: function(response) {
            hideLoading();
            if (response.success) {
                let html = `
                    <div class="data-table">
                        <div class="p-3 bg-white border-bottom">
                            <h5>All Users</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Student Number</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;
                
                response.data.forEach(user => {
                    html += `
                        <tr>
                            <td>${user.id}</td>
                            <td>${escapeHtml(user.full_name)}</td>
                            <td>${escapeHtml(user.email)}</td>
                            <td>${user.student_number}</td>
                            <td>£${parseFloat(user.balance).toFixed(2)}</td>
                            <td>
                                <span class="badge bg-${user.is_active ? 'success' : 'danger'}">
                                    ${user.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary btn-action" onclick="editUserBalance(${user.id})">
                                    <i class="fas fa-money-bill"></i>
                                </button>
                                <button class="btn btn-sm btn-warning btn-action" onclick="toggleUserStatus(${user.id})">
                                    <i class="fas ${user.is_active ? 'fa-ban' : 'fa-check'}"></i>
                                </button>
                                <button class="btn btn-sm btn-danger btn-action" onclick="deleteUser(${user.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
                
                $('#contentArea').html(html);
            } else {
                showNotification('Failed to load users', 'danger');
            }
        },
        error: function() {
            hideLoading();
            showNotification('Network error', 'danger');
        }
    });
}

function editUserBalance(userId) {
    const newBalance = prompt('Enter new balance amount (in GBP):', '0');
    if (newBalance !== null && !isNaN(newBalance) && parseFloat(newBalance) >= 0) {
        showLoading();
        
        $.ajax({
            url: '../api/admin.php?action=update_balance',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'Authorization': 'Bearer admin123' },
            data: JSON.stringify({
                user_id: userId,
                balance: parseFloat(newBalance)
            }),
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showNotification('Balance updated successfully', 'success');
                    loadUsers();
                } else {
                    showNotification('Update failed', 'danger');
                }
            },
            error: function() {
                hideLoading();
                showNotification('Network error', 'danger');
            }
        });
    }
}

function toggleUserStatus(userId) {
    showLoading();
    
    $.ajax({
        url: '../api/admin.php?action=toggle_status',
        method: 'POST',
        contentType: 'application/json',
        headers: { 'Authorization': 'Bearer admin123' },
        data: JSON.stringify({ user_id: userId }),
        success: function(response) {
            hideLoading();
            if (response.success) {
                showNotification('User status toggled', 'success');
                loadUsers();
            } else {
                showNotification('Operation failed', 'danger');
            }
        },
        error: function() {
            hideLoading();
            showNotification('Network error', 'danger');
        }
    });
}

function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        showLoading();
        
        $.ajax({
            url: '../api/admin.php?action=delete_user',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'Authorization': 'Bearer admin123' },
            data: JSON.stringify({ user_id: userId }),
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showNotification('User deleted successfully', 'success');
                    loadUsers();
                } else {
                    showNotification('Delete failed', 'danger');
                }
            },
            error: function() {
                hideLoading();
                showNotification('Network error', 'danger');
            }
        });
    }
}

function loadTransactions() {
    $('#pageTitle').text('Transactions');
    showLoading();
    
    $.ajax({
        url: '../api/admin.php?action=transactions&limit=100',
        method: 'GET',
        headers: { 'Authorization': 'Bearer admin123' },
        success: function(response) {
            hideLoading();
            if (response.success) {
                let html = `
                    <div class="data-table">
                        <div class="p-3 bg-white border-bottom">
                            <h5>Recent Transactions</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Amount</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;
                
                response.data.forEach(trans => {
                    const amountClass = trans.transaction_type === 'deposit' ? 'text-success' : 'text-danger';
                    const amountSign = trans.transaction_type === 'deposit' ? '+' : '-';
                    html += `
                        <tr>
                            <td>${trans.id}</td>
                            <td>${escapeHtml(trans.user_name || trans.user_id)}</td>
                            <td class="${amountClass}">
                                ${amountSign}£${Math.abs(trans.amount).toFixed(2)}
                            </td>
                            <td>
                                <span class="badge bg-${trans.transaction_type === 'deposit' ? 'success' : 'info'}">
                                    ${trans.transaction_type}
                                </span>
                            </td>
                            <td>${escapeHtml(trans.description || '-')}</td>
                            <td>${formatDateTime(trans.created_at)}</td>
                        </tr>
                    `;
                });
                
                html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
                
                $('#contentArea').html(html);
            } else {
                showNotification('Failed to load transactions', 'danger');
            }
        },
        error: function() {
            hideLoading();
            showNotification('Network error', 'danger');
        }
    });
}

function loadEvents() {
    $('#pageTitle').text('Manage Events');
    showLoading();
    
    $.ajax({
        url: '../api/admin.php?action=events',
        method: 'GET',
        headers: { 'Authorization': 'Bearer admin123' },
        success: function(response) {
            hideLoading();
            if (response.success) {
                let html = `
                    <div class="d-flex justify-content-between mb-3">
                        <h5>All Events</h5>
                        <button class="btn btn-primary" onclick="createEvent()">
                            <i class="fas fa-plus"></i> Create New Event
                        </button>
                    </div>
                    <div class="data-table">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Location</th>
                                        <th>Attendees</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;
                
                response.data.forEach(event => {
                    html += `
                        <tr>
                            <td>${event.id}</td>
                            <td><strong>${escapeHtml(event.title)}</strong></td>
                            <td>${formatDate(event.event_date)}</td>
                            <td>${event.event_time || 'TBD'}</td>
                            <td>${escapeHtml(event.location)}</td>
                            <td>${event.current_attendees}/${event.max_attendees}</td>
                            <td>
                                <button class="btn btn-sm btn-danger btn-action" onclick="deleteEvent(${event.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
                
                $('#contentArea').html(html);
            } else {
                showNotification('Failed to load events', 'danger');
            }
        },
        error: function() {
            hideLoading();
            showNotification('Network error', 'danger');
        }
    });
}

function createEvent() {
    const title = prompt('Event Title:');
    if (!title) return;
    
    const description = prompt('Description:');
    const date = prompt('Event Date (YYYY-MM-DD):');
    const time = prompt('Event Time (HH:MM:SS):');
    const location = prompt('Location:');
    const maxAttendees = prompt('Maximum Attendees:');
    
    if (title && date && location) {
        showLoading();
        
        $.ajax({
            url: '../api/admin.php?action=create_event',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'Authorization': 'Bearer admin123' },
            data: JSON.stringify({
                title: title,
                description: description,
                event_date: date,
                event_time: time,
                location: location,
                max_attendees: parseInt(maxAttendees) || 100
            }),
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showNotification('Event created successfully', 'success');
                    loadEvents();
                } else {
                    showNotification('Creation failed', 'danger');
                }
            },
            error: function() {
                hideLoading();
                showNotification('Network error', 'danger');
            }
        });
    }
}

function deleteEvent(eventId) {
    if (confirm('Are you sure you want to delete this event?')) {
        showLoading();
        
        $.ajax({
            url: '../api/admin.php?action=delete_event',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'Authorization': 'Bearer admin123' },
            data: JSON.stringify({ event_id: eventId }),
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showNotification('Event deleted successfully', 'success');
                    loadEvents();
                } else {
                    showNotification('Delete failed', 'danger');
                }
            },
            error: function() {
                hideLoading();
                showNotification('Network error', 'danger');
            }
        });
    }
}

function loadReports() {
    $('#pageTitle').text('Reports');
    
    const html = `
        <div class="row">
            <div class="col-md-6">
                <div class="data-table p-4">
                    <h5>Generate Report</h5>
                    <form id="reportForm">
                        <div class="mb-3">
                            <label class="form-label">Report Type</label>
                            <select class="form-control" id="reportType">
                                <option value="users">User Statistics</option>
                                <option value="transactions">Transaction Report</option>
                                <option value="financial">Financial Summary</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="startDate" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" id="endDate" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-download"></i> Generate Report
                        </button>
                    </form>
                </div>
            </div>
            <div class="col-md-6">
                <div class="data-table p-4" id="reportResult">
                    <h5>Report Preview</h5>
                    <p class="text-muted">Select report parameters and click generate</p>
                </div>
            </div>
        </div>
    `;
    
    $('#contentArea').html(html);
    
    $('#reportForm').submit(function(e) {
        e.preventDefault();
        generateReport();
    });
}

function generateReport() {
    const reportType = $('#reportType').val();
    const startDate = $('#startDate').val();
    const endDate = $('#endDate').val();
    
    if (!startDate || !endDate) {
        showNotification('Please select both start and end dates', 'warning');
        return;
    }
    
    showLoading();
    
    $.ajax({
        url: '../api/admin.php?action=report',
        method: 'POST',
        contentType: 'application/json',
        headers: { 'Authorization': 'Bearer admin123' },
        data: JSON.stringify({
            type: reportType,
            start_date: startDate,
            end_date: endDate
        }),
        success: function(response) {
            hideLoading();
            if (response.success && response.data) {
                displayReport(reportType, response.data);
            } else {
                showNotification('No data found for selected period', 'warning');
            }
        },
        error: function() {
            hideLoading();
            showNotification('Failed to generate report', 'danger');
        }
    });
}

function displayReport(type, data) {
    let html = `<h5>Report Results</h5>`;
    
    if (type === 'users') {
        if (data.length > 0) {
            html += `
                <table class="table table-sm">
                    <thead>
                        <tr><th>Date</th><th>New Users</th></tr>
                    </thead>
                    <tbody>
            `;
            data.forEach(item => {
                html += `<tr><td>${item.date}</td><td>${item.count}</td></tr>`;
            });
            html += `</tbody></table>`;
        } else {
            html += `<p class="text-muted">No user registrations in this period</p>`;
        }
    }
    else if (type === 'transactions') {
        if (data.length > 0) {
            html += `
                <table class="table table-sm">
                    <thead>
                        <tr><th>Date</th><th>Type</th><th>Count</th><th>Total</th></tr>
                    </thead>
                    <tbody>
            `;
            data.forEach(item => {
                html += `
                    <tr>
                        <td>${item.date}</td>
                        <td>${item.transaction_type}</td>
                        <td>${item.count}</td>
                        <td>£${parseFloat(item.total).toFixed(2)}</td>
                    </tr>
                `;
            });
            html += `</tbody></table>`;
        } else {
            html += `<p class="text-muted">No transactions in this period</p>`;
        }
    }
    else if (type === 'financial') {
        html += `
            <div class="mt-3">
                <p><strong>Total Deposits:</strong> £${parseFloat(data.total_deposits || 0).toFixed(2)}</p>
                <p><strong>Total Payments:</strong> £${parseFloat(data.total_payments || 0).toFixed(2)}</p>
                <p><strong>Net Flow:</strong> £${parseFloat(data.net_flow || 0).toFixed(2)}</p>
            </div>
        `;
    }
    
    $('#reportResult').html(html);
}

// Utility functions
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB');
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('en-GB');
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}