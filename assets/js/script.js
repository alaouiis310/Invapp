// Document ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize any necessary JavaScript functionality
    
    // Confirm before deleting
    const deleteButtons = document.querySelectorAll('.btn-danger');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Êtes-vous sûr de vouloir effectuer cette action?')) {
                e.preventDefault();
            }
        });
    });
    
    // Toggle admin features based on settings
    if (localStorage.getItem('adminEnabled') === 'true') {
        document.body.classList.add('admin-mode');
    }
    
    // Any other global JavaScript functionality
});

// Helper function to format numbers
function formatNumber(number, decimals = 2) {
    return parseFloat(number).toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// Helper function for AJAX requests
function makeRequest(url, method = 'GET', data = null) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                resolve(xhr.responseText);
            } else {
                reject(new Error(xhr.statusText));
            }
        };
        
        xhr.onerror = function() {
            reject(new Error('Network Error'));
        };
        
        xhr.send(data);
    });
}

// Modal functions
function openEditModal(userId, username, isAdmin) {
    const modal = document.getElementById('editUserModal');
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_is_admin').checked = isAdmin;
    modal.style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editUserModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editUserModal');
    if (event.target === modal) {
        closeEditModal();
    }
}