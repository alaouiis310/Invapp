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



        // Add keyboard accessibility for browse buttons
        document.querySelectorAll('.btn-browse').forEach(button => {
            button.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
        });
});


function openDirectoryPicker(inputId) {
    // Create a hidden file input element
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.webkitdirectory = true;
    fileInput.directory = true;
    fileInput.mozdirectory = true;
    fileInput.msdirectory = true;
    fileInput.odirectory = true;
    
    fileInput.addEventListener('change', function(e) {
        if (this.files && this.files.length > 0) {
            // Get the directory path
            const path = this.files[0].path || (this.files[0].webkitRelativePath && 
                        this.files[0].webkitRelativePath.split('/')[0]);
            
            // Update the corresponding input field
            document.getElementById(inputId).value = path + '/';
        }
    });
    
    // Trigger the file dialog
    fileInput.click();
}


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

document.addEventListener('DOMContentLoaded', function() {
    // Update the example numbers when inputs change
    const invoiceInput = document.getElementById('invoice_start');
    const deliveryInput = document.getElementById('delivery_start');
    
    if (invoiceInput) {
        invoiceInput.addEventListener('input', function() {
            const nextNumber = this.value ? this.value.padStart(5, '0') : '00000';
            this.nextElementSibling.textContent = `The next invoice will be: INV${nextNumber}`;
        });
    }
    
    if (deliveryInput) {
        deliveryInput.addEventListener('input', function() {
            const nextNumber = this.value ? this.value.padStart(5, '0') : '00000';
            this.nextElementSibling.textContent = `The next delivery note will be: BLV${nextNumber}`;
        });
    }
});



// Add this to your JavaScript
function openDirectoryPicker(inputId) {
    // Check if directory selection is supported
    const isDirectorySelectionSupported = 
        'webkitdirectory' in document.createElement('input') ||
        'directory' in document.createElement('input') ||
        'mozdirectory' in document.createElement('input') ||
        'msdirectory' in document.createElement('input') ||
        'odirectory' in document.createElement('input');
    
    if (!isDirectorySelectionSupported) {
        // Fallback for browsers without directory selection support
        const path = prompt("Please enter the directory path:");
        if (path) {
            document.getElementById(inputId).value = path.endsWith('/') ? path : path + '/';
        }
        return;
    }
}


