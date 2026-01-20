// users.js - JavaScript functionality for user management module

// Use window object to avoid redeclaration errors
window.currentUserId = window.currentUserId || null;
window.usersData = window.usersData || [];

function initUsersModule() {
    // Load users data
    fetchUsers();
    
    // Initialize event listeners
    document.getElementById('userSearch').addEventListener('input', filterUsers);
    document.getElementById('statusFilter').addEventListener('change', filterUsers);
    document.getElementById('retryUsersBtn').addEventListener('click', fetchUsers);
    
    // Modal event listeners
    document.getElementById('closeUserDetailsModal').addEventListener('click', closeUserDetailsModal);
    document.getElementById('cancelUserDetailsBtn').addEventListener('click', closeUserDetailsModal);
    document.getElementById('closeDeleteConfirmModal').addEventListener('click', closeDeleteConfirmModal);
    document.getElementById('cancelDeleteBtn').addEventListener('click', closeDeleteConfirmModal);
    document.getElementById('closeBlockConfirmModal').addEventListener('click', closeBlockConfirmModal);
    document.getElementById('cancelBlockBtn').addEventListener('click', closeBlockConfirmModal);
    
    document.getElementById('deleteUserBtn').addEventListener('click', showDeleteConfirmation);
    document.getElementById('confirmDeleteBtn').addEventListener('click', deleteUser);
    document.getElementById('toggleBlockBtn').addEventListener('click', showBlockConfirmation);
    document.getElementById('confirmBlockBtn').addEventListener('click', toggleUserBlock);
}

// Function to fetch users from API
function fetchUsers() {
    const loadingElement = document.getElementById('usersLoading');
    const errorElement = document.getElementById('usersError');
    const noUsersMessage = document.getElementById('noUsersMessage');
    const tableBody = document.querySelector('#usersTable tbody');
    
    // Show loading spinner
    loadingElement.style.display = 'flex';
    errorElement.style.display = 'none';
    noUsersMessage.style.display = 'none';
    tableBody.innerHTML = '';
    
    // Fetch users from network API (includes all users with proper status)
    apiCall('/network/users?include_all=true&limit=100')
        .then(data => {
            // Handle response from network API
            if (data.success && data.users) {
                // Store users data globally
                window.usersData = data.users.map(user => ({
                    ...user,
                    // Ensure we have boolean flags
                    is_blocked: Boolean(user.is_blocked),
                    is_deleted: Boolean(user.is_deleted)
                }));
            } else {
                window.usersData = [];
            }
            
            // Hide loading spinner
            loadingElement.style.display = 'none';
            
            // Check if we have users
            if (window.usersData.length === 0) {
                noUsersMessage.style.display = 'block';
                return;
            }
            
            // Apply current filters instead of rendering all users
            filterUsers();
        })
        .catch(error => {
            console.error('Error fetching users:', error);
            loadingElement.style.display = 'none';
            errorElement.style.display = 'block';
        });
}

// Function to render users table
function renderUsersTable(users) {
    const tableBody = document.querySelector('#usersTable tbody');
    tableBody.innerHTML = '';
    
    users.forEach((user, index) => {
        const row = document.createElement('tr');
        // Determine status from is_blocked and is_deleted fields
        let status;
        if (user.is_deleted) {
            status = 'deleted';
        } else if (user.is_blocked) {
            status = 'blocked';
        } else {
            status = 'active';
        }
        const role = user.role || 'user';
        // Create table cells with serial number
        row.innerHTML = `
            <td>${index + 1}</td>
            <td>${user.name || 'N/A'}</td>
            <td>${user.email}</td>
            <td><span class="user-role ${role.toLowerCase()}">${role}</span></td>
            <td><span class="user-status ${status.toLowerCase()}">${status}</span></td>
            <td class="action-buttons">
                <button class="btn btn-primary btn-sm" data-user-id="${user.id}">View</button>
            </td>
        `;
        // Add event listener to view button
        row.querySelector('button').addEventListener('click', () => showUserDetails(user.id));
        // Append row to table
        tableBody.appendChild(row);
    });
}

// Function to filter users
function filterUsers() {
    const searchTerm = document.getElementById('userSearch').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
    
    // Filter users based on search term and status
    const filteredUsers = window.usersData.filter(user => {
        const matchesSearch = 
            (user.name && user.name.toLowerCase().includes(searchTerm)) || 
            user.email.toLowerCase().includes(searchTerm);
        
        // Convert user flags to status for filtering
        let userStatus;
        if (user.is_deleted) {
            userStatus = 'deleted';
        } else if (user.is_blocked) {
            userStatus = 'blocked';
        } else {
            userStatus = 'active';
        }
        
        const matchesStatus = 
            statusFilter === '' || 
            userStatus.toLowerCase() === statusFilter;
        
        return matchesSearch && matchesStatus;
    });
    
    // Show/hide no users message
    const noUsersMessage = document.getElementById('noUsersMessage');
    noUsersMessage.style.display = filteredUsers.length === 0 ? 'block' : 'none';
    
    // Render filtered users
    renderUsersTable(filteredUsers);
}

// Function to show user details
function showUserDetails(userId) {
    window.currentUserId = userId;
    
    // Find user in our data
    const user = window.usersData.find(user => user.id === userId);
    
    if (!user) {
        showNotification('User not found', 'error');
        return;
    }
    
    // Determine status from user flags
    let status;
    if (user.is_deleted) {
        status = 'deleted';
    } else if (user.is_blocked) {
        status = 'blocked';
    } else {
        status = 'active';
    }
    
    const role = user.role || 'user';
    
    // Update modal content
    const userDetailsContent = document.getElementById('userDetailsContent');
    userDetailsContent.innerHTML = `
        <div class="user-details">
            <div class="user-details-label">ID:</div>
            <div class="user-details-value">${user.id}</div>
            
            <div class="user-details-label">Name:</div>
            <div class="user-details-value">${user.name || 'N/A'}</div>
            
            <div class="user-details-label">Email:</div>
            <div class="user-details-value">${user.email}</div>
            
            <div class="user-details-label">Role:</div>
            <div class="user-details-value">
                <span class="user-role ${role.toLowerCase()}">${role}</span>
            </div>
            
            <div class="user-details-label">Status:</div>
            <div class="user-details-value">
                <span class="user-status ${status.toLowerCase()}">${status}</span>
            </div>
            
            <div class="user-details-label">Company:</div>
            <div class="user-details-value">${user.company || 'N/A'}</div>
            
            <div class="user-details-label">Expertise:</div>
            <div class="user-details-value">${user.expertise || 'N/A'}</div>
            
            <div class="user-details-label">Experience:</div>
            <div class="user-details-value">${user.experience ? user.experience + ' years' : 'N/A'}</div>
            
            <div class="user-details-label">Created:</div>
            <div class="user-details-value">${user.created_at ? new Date(user.created_at).toLocaleString() : 'N/A'}</div>
            
            <div class="user-details-label">Blocked At:</div>
            <div class="user-details-value">${user.blocked_at ? new Date(user.blocked_at).toLocaleString() : 'Never'}</div>
            
            <div class="user-details-label">Deleted At:</div>
            <div class="user-details-value">${user.deleted_at ? new Date(user.deleted_at).toLocaleString() : 'Never'}</div>
        </div>
    `;
    
    // Update action buttons based on user status
    const deleteBtn = document.getElementById('deleteUserBtn');
    const toggleBlockBtn = document.getElementById('toggleBlockBtn');
    
    if (user.is_deleted) {
        // Hide action buttons for deleted users
        deleteBtn.style.display = 'none';
        toggleBlockBtn.style.display = 'none';
    } else {
        // Show action buttons for non-deleted users
        deleteBtn.style.display = 'inline-block';
        toggleBlockBtn.style.display = 'inline-block';
        
        // Update block/unblock button text
        toggleBlockBtn.textContent = user.is_blocked ? 'Unblock User' : 'Block User';
        toggleBlockBtn.classList.remove('btn-success', 'btn-warning');
        toggleBlockBtn.classList.add(user.is_blocked ? 'btn-success' : 'btn-warning');
    }
    
    // Show modal
    document.getElementById('userDetailsModal').classList.add('active');
}

// Function to close user details modal
function closeUserDetailsModal() {
    document.getElementById('userDetailsModal').classList.remove('active');
}

// Function to close delete confirmation modal
function closeDeleteConfirmModal() {
    document.getElementById('deleteConfirmModal').classList.remove('active');
}

// Function to show delete confirmation modal
function showDeleteConfirmation() {
    // Clear previous reason
    document.getElementById('deleteReason').value = '';
    document.getElementById('deleteConfirmModal').classList.add('active');
}

// Function to close block confirmation modal
function closeBlockConfirmModal() {
    document.getElementById('blockConfirmModal').classList.remove('active');
}

// Function to show block confirmation modal
function showBlockConfirmation() {
    if (!window.currentUserId) {
        showNotification('No user selected', 'error');
        return;
    }
    
    // Find user in our data
    const user = window.usersData.find(user => user.id === window.currentUserId);
    
    if (!user) {
        showNotification('User not found', 'error');
        return;
    }
    
    // Update modal content based on current status
    const isBlocked = user.is_blocked;
    const modalTitle = document.getElementById('blockModalTitle');
    const modalMessage = document.getElementById('blockModalMessage');
    const confirmBtn = document.getElementById('confirmBlockBtn');
    const reasonGroup = document.getElementById('blockReasonGroup');
    const reasonInput = document.getElementById('blockReason');
    
    if (isBlocked) {
        modalTitle.textContent = 'Confirm Unblock';
        modalMessage.textContent = 'Are you sure you want to unblock this user?';
        confirmBtn.textContent = 'Unblock';
        confirmBtn.className = 'btn btn-success';
        reasonGroup.style.display = 'none'; // No reason needed for unblock
    } else {
        modalTitle.textContent = 'Confirm Block';
        modalMessage.textContent = 'Are you sure you want to block this user?';
        confirmBtn.textContent = 'Block';
        confirmBtn.className = 'btn btn-warning';
        reasonGroup.style.display = 'block';
        reasonInput.value = ''; // Clear previous reason
    }
    
    document.getElementById('blockConfirmModal').classList.add('active');
}

// Function to delete a user
function deleteUser() {
    if (!window.currentUserId) {
        showNotification('No user selected', 'error');
        return;
    }
    
    // Get reason from form
    const reasonInput = document.getElementById('deleteReason');
    if (!reasonInput) {
        showNotification('Delete reason input not found', 'error');
        return;
    }
    
    const reason = reasonInput.value.trim();
    
    if (!reason) {
        showNotification('Please provide a reason for deletion', 'error');
        return;
    }
    
    // Close confirmation modal
    closeDeleteConfirmModal();
    
    // Show loading spinner in user details
    const userDetailsContent = document.getElementById('userDetailsContent');
    userDetailsContent.innerHTML = '<div class="loading-container"><div class="loading-spinner"></div><p>Deleting user...</p></div>';
    
    // Call API to delete user with reason
    apiCall(`/api/admin/users/${window.currentUserId}`, 'DELETE', { reason: reason })
        .then(() => {
            // Close modal
            closeUserDetailsModal();
            
            // Show success notification
            showNotification('User deleted successfully', 'success');
            
            // Refresh users table
            fetchUsers();
        })
        .catch(error => {
            console.error('Error deleting user:', error);
            
            // Show error in modal
            userDetailsContent.innerHTML = `
                <div class="error-container">
                    <p>Error deleting user: ${error.message}</p>
                    <button class="btn btn-primary" onclick="showUserDetails(${window.currentUserId})">Retry</button>
                </div>
            `;
        });
}

// Function to toggle user block status
function toggleUserBlock() {
    if (!window.currentUserId) {
        showNotification('No user selected', 'error');
        return;
    }
    
    // Find user in our data
    const user = window.usersData.find(user => user.id === window.currentUserId);
    
    if (!user) {
        showNotification('User not found', 'error');
        return;
    }
    
    // Determine action based on current status
    const action = user.is_blocked ? 'unblock' : 'block';
    
    // For blocking, get reason from form
    let requestData = {};
    if (action === 'block') {
        const reasonInput = document.getElementById('blockReason');
        if (!reasonInput) {
            showNotification('Block reason input not found', 'error');
            return;
        }
        
        const reason = reasonInput.value.trim();
        
        if (!reason) {
            showNotification('Please provide a reason for blocking', 'error');
            return;
        }
        
        requestData.reason = reason;
    }
    
    // Close confirmation modal
    closeBlockConfirmModal();
    
    // Show loading spinner in user details
    const userDetailsContent = document.getElementById('userDetailsContent');
    userDetailsContent.innerHTML = `<div class="loading-container"><div class="loading-spinner"></div><p>${action === 'block' ? 'Blocking' : 'Unblocking'} user...</p></div>`;
    
    // Call API to toggle user status
    apiCall(`/api/admin/users/${window.currentUserId}/${action}`, 'PATCH', Object.keys(requestData).length > 0 ? requestData : null)
        .then(() => {
            // Close modal
            closeUserDetailsModal();
            
            // Show success notification
            showNotification(`User ${action === 'block' ? 'blocked' : 'unblocked'} successfully`, 'success');
            
            // Refresh users table
            fetchUsers();
        })
        .catch(error => {
            console.error(`Error ${action}ing user:`, error);
            
            // Show error in modal
            userDetailsContent.innerHTML = `
                <div class="error-container">
                    <p>Error ${action}ing user: ${error.message}</p>
                    <button class="btn btn-primary" onclick="showUserDetails(${window.currentUserId})">Retry</button>
                </div>
            `;
        });
}