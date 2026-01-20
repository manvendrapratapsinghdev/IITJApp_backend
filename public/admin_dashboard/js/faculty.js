// faculty.js - JavaScript functionality for faculty management module

// Use window object to avoid redeclaration errors
window.currentFacultyId = window.currentFacultyId || null;
window.facultyData = window.facultyData || [];

function initFacultyModule() {
    // Load faculty data
    fetchFaculty();
    
    // Initialize event listeners
    document.getElementById('facultySearch').addEventListener('input', filterFaculty);
    document.getElementById('retryFacultyBtn').addEventListener('click', fetchFaculty);
    document.getElementById('addFacultyBtn').addEventListener('click', showAddFacultyModal);
    
    // Modal event listeners
    document.getElementById('closeFacultyModal').addEventListener('click', closeFacultyModal);
    document.getElementById('cancelFacultyBtn').addEventListener('click', closeFacultyModal);
    document.getElementById('saveFacultyBtn').addEventListener('click', saveFaculty);
    
    document.getElementById('closeDeleteFacultyModal').addEventListener('click', closeDeleteFacultyModal);
    document.getElementById('cancelDeleteFacultyBtn').addEventListener('click', closeDeleteFacultyModal);
    document.getElementById('confirmDeleteFacultyBtn').addEventListener('click', deleteFaculty);
}

// Function to fetch faculty from API
function fetchFaculty() {
    const loadingElement = document.getElementById('facultyLoading');
    const errorElement = document.getElementById('facultyError');
    const noFacultyMessage = document.getElementById('noFacultyMessage');
    const tableBody = document.querySelector('#facultyTable tbody');
    
    // Check if elements exist
    if (!loadingElement || !errorElement || !noFacultyMessage || !tableBody) {
        console.error('Required DOM elements not found for faculty module');
        return;
    }
    
    // Show loading spinner
    loadingElement.style.display = 'flex';
    errorElement.style.display = 'none';
    noFacultyMessage.style.display = 'none';
    tableBody.innerHTML = '';
    
    // Fetch faculty from API
    apiCall('/api/admin/faculty?limit=100')
        .then(data => {
            // Handle response from faculty API
            let facultyArray = [];
            if (data && data.success && data.faculty && Array.isArray(data.faculty)) {
                facultyArray = data.faculty;
                // Store faculty data globally
                window.facultyData = facultyArray;
            } else if (Array.isArray(data)) {
                // Handle direct array response
                facultyArray = data;
                window.facultyData = facultyArray;
            } else {
                window.facultyData = [];
            }
            
            // Hide loading spinner
            loadingElement.style.display = 'none';
            
            // Check if we have faculty
            if (facultyArray.length === 0) {
                noFacultyMessage.style.display = 'block';
                return;
            }
            
            // Render faculty table
            renderFacultyTable(facultyArray);
        })
        .catch(error => {
            console.error('Error fetching faculty:', error);
            loadingElement.style.display = 'none';
            errorElement.style.display = 'block';
        });
}

// Function to render faculty table
function renderFacultyTable(faculty) {
    const tableBody = document.querySelector('#facultyTable tbody');
    tableBody.innerHTML = '';
    
    faculty.forEach(member => {
        const row = document.createElement('tr');
        
        // Create table cells
        row.innerHTML = `
            <td>${member.id}</td>
            <td>${member.name}</td>
            <td><span class="department-badge">${member.company || 'N/A'}</span></td>
            <td><span class="faculty-email" title="${member.email}">${member.email}</span></td>
            <td><span class="faculty-phone">${member.phone || 'N/A'}</span></td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-primary btn-sm" data-action="edit" data-faculty-id="${member.id}">Edit</button>
                    <button class="btn btn-danger btn-sm" data-action="delete" data-faculty-id="${member.id}">Delete</button>
                </div>
            </td>
        `;
        
        // Add event listeners for buttons
        row.querySelector('[data-action="edit"]').addEventListener('click', () => editFaculty(member.id));
        row.querySelector('[data-action="delete"]').addEventListener('click', () => showDeleteFacultyConfirmation(member.id));
        
        // Append row to table
        tableBody.appendChild(row);
    });
}

// Function to filter faculty
function filterFaculty() {
    const searchTerm = document.getElementById('facultySearch').value.toLowerCase();
    
    // Use global faculty data with fallback
    const currentFacultyData = window.facultyData || [];
    
    // Filter faculty based on search term
    const filteredFaculty = currentFacultyData.filter(member => 
        member.name.toLowerCase().includes(searchTerm) || 
        (member.company && member.company.toLowerCase().includes(searchTerm)) ||
        member.email.toLowerCase().includes(searchTerm)
    );
    
    // Show/hide no faculty message
    const noFacultyMessage = document.getElementById('noFacultyMessage');
    if (noFacultyMessage) {
        noFacultyMessage.style.display = filteredFaculty.length === 0 ? 'block' : 'none';
    }
    
    // Render filtered faculty
    renderFacultyTable(filteredFaculty);
}

// Function to show add faculty modal
function showAddFacultyModal() {
    // Reset form
    document.getElementById('facultyForm').reset();
    
    // Set default joining date to today
    const today = new Date();
    today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
    document.getElementById('facultyJoiningDate').value = today.toISOString().slice(0, 10);
    
    // Update modal title
    document.getElementById('facultyModalTitle').textContent = 'Add New Faculty';
    
    // Reset current faculty ID
    currentFacultyId = null;
    
    // Show modal
    document.getElementById('facultyModal').classList.add('active');
}

// Function to show edit faculty modal
function editFaculty(facultyId) {
    // Use global faculty data with fallback
    const currentFacultyData = window.facultyData || [];
    
    // Find faculty in our data
    const member = currentFacultyData.find(f => f.id === facultyId);
    
    if (!member) {
        showNotification('Faculty member not found', 'error');
        return;
    }
    
    // Update modal title
    document.getElementById('facultyModalTitle').textContent = 'Edit Faculty';
    
    // Format joining date if available
    let formattedDate = '';
    if (member.joining_date) {
        const joiningDate = new Date(member.joining_date);
        joiningDate.setMinutes(joiningDate.getMinutes() - joiningDate.getTimezoneOffset());
        formattedDate = joiningDate.toISOString().slice(0, 10);
    }
    
    // Set form values
    document.getElementById('facultyName').value = member.name;
    document.getElementById('facultyDepartment').value = member.company || '';
    document.getElementById('facultyEmail').value = member.email;
    document.getElementById('facultyPhone').value = member.phone || '';
    document.getElementById('facultyDesignation').value = member.designation || '';
    document.getElementById('facultyBio').value = member.bio || '';
    document.getElementById('facultyJoiningDate').value = formattedDate;
    
    // Set current faculty ID
    currentFacultyId = facultyId;
    
    // Show modal
    document.getElementById('facultyModal').classList.add('active');
}

// Function to close faculty modal
function closeFacultyModal() {
    document.getElementById('facultyModal').classList.remove('active');
}

// Function to save faculty (create or update)
function saveFaculty() {
    // Get form values
    const name = document.getElementById('facultyName').value;
    const department = document.getElementById('facultyDepartment').value;
    const email = document.getElementById('facultyEmail').value;
    const phone = document.getElementById('facultyPhone').value;
    const designation = document.getElementById('facultyDesignation').value;
    const bio = document.getElementById('facultyBio').value;
    const joining_date = document.getElementById('facultyJoiningDate').value;
    
    // Validate required fields
    if (!name || !email) {
        showNotification('Name and email are required', 'error');
        return;
    }
    
    // Validate email format
    if (!validateEmail(email)) {
        showNotification('Please enter a valid email address', 'error');
        return;
    }
    
    // Create faculty data object
    const facultyData = {
        name,
        email,
        phone: phone || '0000000000', // Default phone if not provided
        password: 'faculty@123' // Default password for all faculty
    };
    
    // Add optional fields if provided
    if (department) facultyData.department = department;
    if (designation) facultyData.designation = designation;
    if (bio) facultyData.bio = bio;
    if (joining_date) facultyData.joining_date = joining_date;
    
    // Determine if this is a create or update operation
    const isUpdate = currentFacultyId !== null;
    const url = isUpdate ? `/api/admin/faculty/${currentFacultyId}` : '/api/admin/faculty';
    const method = isUpdate ? 'PUT' : 'POST';
    
    // Disable form buttons
    document.getElementById('saveFacultyBtn').disabled = true;
    document.getElementById('cancelFacultyBtn').disabled = true;
    
    // Make API call
    apiCall(url, method, facultyData)
        .then(() => {
            // Close modal
            closeFacultyModal();
            
            // Show success notification
            showNotification(`Faculty ${isUpdate ? 'updated' : 'created'} successfully`, 'success');
            
            // Refresh faculty table
            fetchFaculty();
        })
        .catch(error => {
            console.error(`Error ${isUpdate ? 'updating' : 'creating'} faculty:`, error);
            showNotification(`Error ${isUpdate ? 'updating' : 'creating'} faculty: ${error.message}`, 'error');
        })
        .finally(() => {
            // Re-enable form buttons
            document.getElementById('saveFacultyBtn').disabled = false;
            document.getElementById('cancelFacultyBtn').disabled = false;
        });
}

// Function to validate email format
function validateEmail(email) {
    const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
}

// Function to show delete faculty confirmation
function showDeleteFacultyConfirmation(facultyId) {
    currentFacultyId = facultyId;
    // Clear the reason field
    document.getElementById('deletionReason').value = '';
    document.getElementById('deleteFacultyModal').classList.add('active');
}

// Function to close delete faculty modal
function closeDeleteFacultyModal() {
    document.getElementById('deleteFacultyModal').classList.remove('active');
    // Clear the reason field
    document.getElementById('deletionReason').value = '';
}

// Function to delete a faculty
function deleteFaculty() {
    if (!currentFacultyId) {
        showNotification('No faculty selected', 'error');
        return;
    }
    
    // Get and validate deletion reason
    const reason = document.getElementById('deletionReason').value.trim();
    if (!reason) {
        showNotification('Please provide a reason for deletion', 'error');
        return;
    }
    
    // Disable delete button
    document.getElementById('confirmDeleteFacultyBtn').disabled = true;
    
    // Delete the faculty with reason
    apiCall(`/api/admin/faculty/${currentFacultyId}`, 'DELETE', { reason: reason })
        .then(() => {
            // Close modal
            closeDeleteFacultyModal();
            
            // Show success notification
            showNotification('Faculty deleted successfully', 'success');
            
            // Refresh faculty table
            fetchFaculty();
        })
        .catch(error => {
            console.error('Error deleting faculty:', error);
            showNotification(`Error deleting faculty: ${error.message}`, 'error');
        })
        .finally(() => {
            // Re-enable delete button
            document.getElementById('confirmDeleteFacultyBtn').disabled = false;
        });
}