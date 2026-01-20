// approvals.js - JavaScript functionality for approval management module

let currentApprovalId = null;
let approvalsData = [];
let pendingAction = null;

function initApprovalsModule() {
    // Load approvals data
    fetchApprovals();

    // Initialize Change Role modal events
    document.getElementById('closeChangeRoleModal').addEventListener('click', closeChangeRoleModal);
    document.getElementById('cancelChangeRoleBtn').addEventListener('click', closeChangeRoleModal);
    document.getElementById('confirmChangeRoleBtn').addEventListener('click', processRoleChange);
    
    // Initialize event listeners
    document.getElementById('approvalSearch').addEventListener('input', filterApprovals);
    document.getElementById('statusFilter').addEventListener('change', filterApprovals);
    document.getElementById('retryApprovalsBtn').addEventListener('click', fetchApprovals);
    
    // Modal event listeners
    document.getElementById('closeApprovalDetailsModal').addEventListener('click', closeApprovalDetailsModal);
    document.getElementById('cancelApprovalDetailsBtn').addEventListener('click', closeApprovalDetailsModal);
    
    document.getElementById('closeConfirmActionModal').addEventListener('click', closeConfirmActionModal);
    document.getElementById('cancelActionBtn').addEventListener('click', closeConfirmActionModal);
    
    document.getElementById('approveRequestBtn').addEventListener('click', () => showConfirmAction('approve'));
    document.getElementById('rejectRequestBtn').addEventListener('click', () => showConfirmAction('reject'));
    document.getElementById('confirmActionBtn').addEventListener('click', processApprovalAction);
}

// Function to fetch approvals from API
function fetchApprovals() {
    const loadingElement = document.getElementById('approvalsLoading');
    const errorElement = document.getElementById('approvalsError');
    const noApprovalsMessage = document.getElementById('noApprovalsMessage');
    const tableBody = document.querySelector('#approvalsTable tbody');
    
    // Show loading spinner
    loadingElement.style.display = 'flex';
    errorElement.style.display = 'none';
    noApprovalsMessage.style.display = 'none';
    tableBody.innerHTML = '';
    
    // Fetch approvals from API
    apiCall('/admin/requests')
        .then(data => {
            // Handle response from admin requests API
            let approvalsArray = [];
            if (data?.success && Array.isArray(data?.requests)) {
                approvalsArray = data.requests;
                // Store approvals data globally
                approvalsData = approvalsArray;
            } else if (Array.isArray(data)) {
                // Handle direct array response
                approvalsArray = data;
                approvalsData = approvalsArray;
            } else {
                approvalsData = [];
            }
            
            // Hide loading spinner
            loadingElement.style.display = 'none';
            
            // Check if we have approvals
            if (approvalsArray.length === 0) {
                noApprovalsMessage.style.display = 'block';
                return;
            }
            
            // Render approvals table
            renderApprovalsTable(approvalsArray);
        })
        .catch(error => {
            console.error('Error fetching approvals:', error);
            loadingElement.style.display = 'none';
            errorElement.style.display = 'block';
        });
}

// Function to render approvals table
function renderApprovalsTable(approvals) {
    const tableBody = document.querySelector('#approvalsTable tbody');
    tableBody.innerHTML = '';
    
    approvals.forEach(approval => {
        const row = document.createElement('tr');
        
        // Format date
        const requestedDate = new Date(approval.requested_at).toLocaleString();
        
        // Create table cells
        row.innerHTML = `
            <td>${approval.request_id}</td>
            <td>${approval.user.name || `User #${approval.user.user_id}`}</td>
            <td><span class="request-type">Faculty Approval</span></td>
            <td>${requestedDate}</td>
            <td><span class="approval-status ${approval.status.toLowerCase()}">${approval.status}</span></td>
            <td class="action-buttons">
                <button class="btn btn-primary btn-sm" data-approval-id="${approval.request_id}">View Details</button>
                ${approval.status.toLowerCase() === 'approved' ? `
                    <button class="btn btn-warning btn-sm change-role-btn" data-user-id="${approval.user.user_id}" data-user-name="${approval.user.name}">Change Role</button>
                ` : ''}
                ${approval.status.toLowerCase() === 'pending' ? `
                    <button class="btn btn-success btn-sm" data-action="approve" data-approval-id="${approval.request_id}">Approve</button>
                    <button class="btn btn-danger btn-sm" data-action="reject" data-approval-id="${approval.request_id}">Reject</button>
                ` : ''}
            </td>
        `;
        
        // Add event listener to view button
        row.querySelector('button[data-approval-id]').addEventListener('click', () => showApprovalDetails(approval.request_id));

        // Add event listener to change role button if status is approved
        if (approval.status.toLowerCase() === 'approved') {
            row.querySelector('.change-role-btn').addEventListener('click', () => {
                showChangeRoleModal(approval.user.user_id, approval.user.name);
            });
        }
        
        // Add event listeners to action buttons if status is pending
        if (approval.status.toLowerCase() === 'pending') {
            row.querySelector('[data-action="approve"]').addEventListener('click', () => {
                currentApprovalId = approval.request_id;
                showConfirmAction('approve');
            });
            
            row.querySelector('[data-action="reject"]').addEventListener('click', () => {
                currentApprovalId = approval.request_id;
                showConfirmAction('reject');
            });
        }
        
        // Append row to table
        tableBody.appendChild(row);
    });
}

// Function to filter approvals
function filterApprovals() {
    const searchTerm = document.getElementById('approvalSearch').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
    
    // Filter approvals based on search term and status
    const filteredApprovals = approvalsData.filter(approval => {
        const matchesSearch = 
            approval.user?.name?.toLowerCase().includes(searchTerm) || 
            'Faculty Approval'.toLowerCase().includes(searchTerm);
        
        const matchesStatus = 
            statusFilter === '' || 
            approval.status.toLowerCase() === statusFilter;
        
        return matchesSearch && matchesStatus;
    });
    
    // Show/hide no approvals message
    const noApprovalsMessage = document.getElementById('noApprovalsMessage');
    noApprovalsMessage.style.display = filteredApprovals.length === 0 ? 'block' : 'none';
    
    // Render filtered approvals
    renderApprovalsTable(filteredApprovals);
}

// Function to show change role confirmation
function showChangeRoleModal(userId, userName) {
    currentApprovalId = userId;
    document.getElementById('changeRoleModal').classList.add('active');
}

// Function to close change role modal
function closeChangeRoleModal() {
    document.getElementById('changeRoleModal').classList.remove('active');
    document.getElementById('roleChangeReason').value = '';
}

// Function to process role change
function processRoleChange() {
    if (!currentApprovalId) {
        showNotification('Invalid user', 'error');
        return;
    }

    const reason = document.getElementById('roleChangeReason').value.trim();
    if (!reason) {
        showNotification('Please provide a reason for the role change', 'error');
        return;
    }

    // Disable button to prevent double submission
    document.getElementById('confirmChangeRoleBtn').disabled = true;

    // Make API call to change role
    apiCall(`/admin/users/${currentApprovalId}/demote`, 'PATCH', { reason })
        .then(response => {
            if (response.success) {
                closeChangeRoleModal();
                showNotification('User role changed successfully', 'success');
                fetchApprovals(); // Refresh the approvals list
            } else {
                throw new Error(response.message || 'Failed to change user role');
            }
        })
        .catch(error => {
            console.error('Error changing user role:', error);
            showNotification(error.message || 'Error changing user role', 'error');
        })
        .finally(() => {
            document.getElementById('confirmChangeRoleBtn').disabled = false;
        });
}

// Function to show approval details
function showApprovalDetails(approvalId) {
    currentApprovalId = approvalId;
    
    // Find approval in our data
    const approval = approvalsData.find(a => a.request_id === approvalId);
    
    if (!approval) {
        showNotification('Approval request not found', 'error');
        return;
    }
    
    // Update modal content
    const modalContentElement = document.getElementById('approvalDetailsContent');
    // Format requested date
    const requestedDate = new Date(approval.requested_at).toLocaleString();
    
    // Build details HTML
    const detailsHTML = `
        <div class="approval-details">
            <div class="detail-row">
                <div class="approval-details-label">ID:</div>
                <div class="approval-details-value">${approval.request_id}</div>
            </div>
            
            <div class="detail-row">
                <div class="approval-details-label">User:</div>
                <div class="approval-details-value">${approval.user.name}</div>
            </div>
            
            <div class="detail-row">
                <div class="approval-details-label">Email:</div>
                <div class="approval-details-value">${approval.user.email}</div>
            </div>
            
            <div class="detail-row">
                <div class="approval-details-label">Phone:</div>
                <div class="approval-details-value">${approval.user.phone}</div>
            </div>
            
            <div class="detail-row">
                <div class="approval-details-label">Company:</div>
                <div class="approval-details-value">${approval.user.company}</div>
            </div>
            
            <div class="detail-row">
                <div class="approval-details-label">Expertise:</div>
                <div class="approval-details-value">${approval.user.expertise}</div>
            </div>
            
            <div class="detail-row">
                <div class="approval-details-label">Experience:</div>
                <div class="approval-details-value">${approval.user.experience} years</div>
            </div>
            
            <div class="detail-row">
                <div class="approval-details-label">Status:</div>
                <div class="approval-details-value">
                    <span class="approval-status ${approval.status.toLowerCase()}">${approval.status}</span>
                </div>
            </div>
            
            <div class="detail-row">
                <div class="approval-details-label">Requested:</div>
                <div class="approval-details-value">${requestedDate}</div>
            </div>
        </div>
    `;
    
    modalContentElement.innerHTML = detailsHTML;
    
    // Update action buttons visibility based on status
    if (approval.status.toLowerCase() === 'pending') {
        document.getElementById('approveRequestBtn').style.display = 'block';
        document.getElementById('rejectRequestBtn').style.display = 'block';
    } else {
        document.getElementById('approveRequestBtn').style.display = 'none';
        document.getElementById('rejectRequestBtn').style.display = 'none';
    }
    
    // Show modal
    document.getElementById('approvalDetailsModal').classList.add('active');
}

// Function to close approval details modal
function closeApprovalDetailsModal() {
    document.getElementById('approvalDetailsModal').classList.remove('active');
}

// Function to show confirmation modal for approve/reject actions
function showConfirmAction(action) {
    pendingAction = action;
    
    // Update modal title and message
    const title = document.getElementById('confirmActionTitle');
    const message = document.getElementById('confirmActionMessage');
    const actionBtn = document.getElementById('confirmActionBtn');
    
    if (action === 'approve') {
        title.textContent = 'Confirm Approval';
        message.textContent = 'Are you sure you want to approve this request?';
        actionBtn.textContent = 'Approve';
        actionBtn.className = 'btn btn-success';
    } else {
        title.textContent = 'Confirm Rejection';
        message.textContent = 'Are you sure you want to reject this request?';
        actionBtn.textContent = 'Reject';
        actionBtn.className = 'btn btn-danger';
    }
    
    // Reset reason field
    document.getElementById('actionReason').value = '';
    
    // Show modal
    document.getElementById('confirmActionModal').classList.add('active');
}

// Function to close confirm action modal
function closeConfirmActionModal() {
    document.getElementById('confirmActionModal').classList.remove('active');
}

// Function to process approval action (approve or reject)
function processApprovalAction() {
    if (!currentApprovalId || !pendingAction) {
        showNotification('Invalid action', 'error');
        return;
    }
    
    // Get reason from form
    const reason = document.getElementById('actionReason').value.trim();
    
    // Disable action button
    document.getElementById('confirmActionBtn').disabled = true;
    
    // Prepare request data
    const requestData = {
        action: pendingAction,
        notes: reason || undefined
    };
    
    // Determine endpoint based on action
    const url = `/api/admin/requests/${currentApprovalId}`;
    
    // Make API call
    apiCall(url, 'PATCH', requestData)
        .then(() => {
            // Close modals
            closeConfirmActionModal();
            closeApprovalDetailsModal();
            
            // Show success notification
            showNotification(`Request ${pendingAction === 'approve' ? 'approved' : 'rejected'} successfully`, 'success');
            
            // Refresh approvals table
            fetchApprovals();
        })
        .catch(error => {
            console.error(`Error ${pendingAction}ing request:`, error);
            showNotification(`Error ${pendingAction}ing request: ${error.message}`, 'error');
        })
        .finally(() => {
            // Re-enable action button
            document.getElementById('confirmActionBtn').disabled = false;
        });
}