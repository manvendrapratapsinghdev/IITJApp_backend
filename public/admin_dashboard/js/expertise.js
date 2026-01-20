// expertise.js - JavaScript functionality for expertise management module

// Global state variables
window.currentExpertiseId = null;
window.expertiseData = [];

// Initialize module function (this function will be called by main.js)
function initExpertiseModule() {
    console.log('Expertise module initializing...');
    
    // Initialize event listeners
    document.getElementById('expertiseSearch')?.addEventListener('input', filterExpertise);
    document.getElementById('retryExpertiseBtn')?.addEventListener('click', fetchExpertise);
    document.getElementById('addExpertiseBtn')?.addEventListener('click', showAddExpertiseModal);
    document.getElementById('bulkAddExpertiseBtn')?.addEventListener('click', showBulkAddExpertiseModal);
    
    // Modal event listeners
    document.getElementById('closeExpertiseModal')?.addEventListener('click', closeExpertiseModal);
    document.getElementById('cancelExpertiseBtn')?.addEventListener('click', closeExpertiseModal);
    document.getElementById('saveExpertiseBtn')?.addEventListener('click', saveExpertise);
    
    // Bulk expertise modal event listeners
    document.getElementById('closeBulkExpertiseModal')?.addEventListener('click', closeBulkExpertiseModal);
    document.getElementById('cancelBulkExpertiseBtn')?.addEventListener('click', closeBulkExpertiseModal);
    document.getElementById('saveBulkExpertiseBtn')?.addEventListener('click', saveBulkExpertise);
    
    // Delete modal event listeners
    document.getElementById('closeDeleteExpertiseModal')?.addEventListener('click', closeDeleteExpertiseModal);
    document.getElementById('cancelDeleteExpertiseBtn')?.addEventListener('click', closeDeleteExpertiseModal);
    document.getElementById('confirmDeleteExpertiseBtn')?.addEventListener('click', deleteExpertise);
    
    // Form submit handler
    document.getElementById('expertiseForm')?.addEventListener('submit', (e) => {
        e.preventDefault();
        saveExpertise();
    });
    
    // Load initial expertise data
    fetchExpertise();
    
    console.log('Expertise module initialized successfully');
}

// Function to fetch expertise from API
function fetchExpertise() {
    const loadingElement = document.getElementById('expertiseLoading');
    const errorElement = document.getElementById('expertiseError');
    const noExpertiseMessage = document.getElementById('noExpertiseMessage');
    const tableBody = document.querySelector('#expertiseTable tbody');
    
    if (!loadingElement || !errorElement || !noExpertiseMessage || !tableBody) {
        console.error('Required elements not found for fetchExpertise');
        return;
    }
    
    // Show loading spinner
    loadingElement.style.display = 'flex';
    errorElement.style.display = 'none';
    noExpertiseMessage.style.display = 'none';
    tableBody.innerHTML = '';
    
    // Fetch expertise from API
    apiCall('/api/expertise')
        .then(data => {
            if (data.success && data.data) {
                window.expertiseData = data.data;
            } else {
                window.expertiseData = [];
            }
            
            loadingElement.style.display = 'none';
            
            if (window.expertiseData.length === 0) {
                noExpertiseMessage.style.display = 'block';
                return;
            }
            
            renderExpertiseTable(window.expertiseData);
        })
        .catch(error => {
            console.error('Error fetching expertise:', error);
            loadingElement.style.display = 'none';
            errorElement.style.display = 'block';
        });
}

// Function to render expertise table
function renderExpertiseTable(expertiseList) {
    const tableBody = document.querySelector('#expertiseTable tbody');
    if (!tableBody) return;
    
    tableBody.innerHTML = '';
    
    expertiseList.forEach(expertise => {
        const row = document.createElement('tr');
        const truncate = (text, maxWords) => {
            if (!text) return '';
            const words = text.split(' ');
            return words.length > maxWords ? words.slice(0, maxWords).join(' ') + '...' : text;
        };
        
        row.innerHTML = `
            <td>${expertise.id}</td>
            <td title="${expertise.name}">${truncate(expertise.name, 20)}</td>
            <td title="${expertise.description || ''}">${truncate(expertise.description || '', 10)}</td>
            <td>${new Date(expertise.created_at).toLocaleString()}</td>
            <td class="action-buttons">
                <button class="btn btn-primary btn-sm" onclick="editExpertise(${expertise.id})">Edit</button>
                <button class="btn btn-danger btn-sm" onclick="confirmDeleteExpertise(${expertise.id})">Delete</button>
            </td>
        `;
        
        tableBody.appendChild(row);
    });
}

// Function to filter expertise
function filterExpertise() {
    const searchTerm = document.getElementById('expertiseSearch')?.value.toLowerCase() || '';
    const filteredExpertise = window.expertiseData.filter(expertise => {
        return expertise.name.toLowerCase().includes(searchTerm) || 
               (expertise.description || '').toLowerCase().includes(searchTerm);
    });
    
    const noExpertiseMessage = document.getElementById('noExpertiseMessage');
    if (noExpertiseMessage) {
        noExpertiseMessage.style.display = filteredExpertise.length === 0 ? 'block' : 'none';
    }
    
    renderExpertiseTable(filteredExpertise);
}

// Function to show add expertise modal
function showAddExpertiseModal() {
    const form = document.getElementById('expertiseForm');
    const idInput = document.getElementById('expertiseId');
    const titleElement = document.getElementById('expertiseModalTitle');
    const modal = document.getElementById('expertiseModal');
    
    if (form) form.reset();
    if (idInput) idInput.value = '';
    if (titleElement) titleElement.textContent = 'Add New Expertise';
    
    window.currentExpertiseId = null;
    
    if (modal) modal.classList.add('active');
}

// Function to edit expertise
function editExpertise(id) {
    apiCall(`/api/expertise/${id}`)
        .then(response => {
            if (!response.success) {
                throw new Error(response.message || 'Failed to load expertise details');
            }

            const expertise = response.data;
            const idInput = document.getElementById('expertiseId');
            const nameInput = document.getElementById('expertiseName');
            const descInput = document.getElementById('expertiseDescription');
            const titleElement = document.getElementById('expertiseModalTitle');
            const modal = document.getElementById('expertiseModal');
            
            if (idInput) idInput.value = expertise.id;
            if (nameInput) nameInput.value = expertise.name;
            if (descInput) descInput.value = expertise.description || '';
            if (titleElement) titleElement.textContent = 'Edit Expertise';
            
            window.currentExpertiseId = expertise.id;
            
            if (modal) modal.classList.add('active');
        })
        .catch(error => {
            console.error('Error loading expertise details:', error);
            showNotification('Error loading expertise details: ' + error.message, 'error');
        });
}

// Function to close expertise modal
function closeExpertiseModal() {
    const modal = document.getElementById('expertiseModal');
    if (modal) modal.classList.remove('active');
}

// Function to close delete expertise modal
function closeDeleteExpertiseModal() {
    const modal = document.getElementById('deleteExpertiseModal');
    if (modal) modal.classList.remove('active');
}

// Function to show delete confirmation
function confirmDeleteExpertise(id) {
    window.currentExpertiseId = id;
    const modal = document.getElementById('deleteExpertiseModal');
    if (modal) modal.classList.add('active');
}

// Function to save expertise
function saveExpertise() {
    const nameInput = document.getElementById('expertiseName');
    const descInput = document.getElementById('expertiseDescription');
    const idInput = document.getElementById('expertiseId');
    
    if (!nameInput) {
        showNotification('Form elements not found', 'error');
        return;
    }
    
    const name = nameInput.value.trim();
    const description = descInput?.value.trim() || '';
    const id = idInput?.value;
    
    if (!name) {
        showNotification('Expertise name is required', 'error');
        return;
    }
    
    const expertiseData = { name, description };
    const url = id ? `/api/expertise/${id}` : '/api/expertise';
    const method = id ? 'PUT' : 'POST';
    
    const saveBtn = document.getElementById('saveExpertiseBtn');
    if (saveBtn) saveBtn.disabled = true;
    
    apiCall(url, method, expertiseData)
        .then(() => {
            closeExpertiseModal();
            showNotification(
                id ? 'Expertise updated successfully' : 'Expertise created successfully',
                'success'
            );
            fetchExpertise();
        })
        .catch(error => {
            console.error('Error saving expertise:', error);
            showNotification(`Error saving expertise: ${error.message}`, 'error');
        })
        .finally(() => {
            if (saveBtn) saveBtn.disabled = false;
        });
}

// Function to delete expertise
function deleteExpertise() {
    if (!window.currentExpertiseId) {
        showNotification('No expertise selected', 'error');
        return;
    }
    
    const confirmButton = document.getElementById('confirmDeleteExpertiseBtn');
    if (confirmButton) confirmButton.disabled = true;
    
    apiCall(`/api/expertise/${window.currentExpertiseId}`, 'DELETE')
        .then(() => {
            closeDeleteExpertiseModal();
            showNotification('Expertise deleted successfully', 'success');
            fetchExpertise();
        })
        .catch(error => {
            console.error('Error deleting expertise:', error);
            showNotification(`Error deleting expertise: ${error.message}`, 'error');
        })
        .finally(() => {
            if (confirmButton) confirmButton.disabled = false;
        });
}

// Make functions available globally for event handlers
window.editExpertise = editExpertise;
window.confirmDeleteExpertise = confirmDeleteExpertise;
window.showBulkAddExpertiseModal = showBulkAddExpertiseModal;
window.closeBulkExpertiseModal = closeBulkExpertiseModal;
window.saveBulkExpertise = saveBulkExpertise;

// Make the initialization function available globally (this is crucial for main.js)
// Function to show bulk add expertise modal
function showBulkAddExpertiseModal() {
    const modal = document.getElementById('bulkExpertiseModal');
    const textarea = document.getElementById('bulkExpertiseInput');
    if (textarea) textarea.value = '';
    if (modal) modal.classList.add('active');
}

// Function to close bulk expertise modal
function closeBulkExpertiseModal() {
    const modal = document.getElementById('bulkExpertiseModal');
    if (modal) modal.classList.remove('active');
}

// Function to save bulk expertise
function saveBulkExpertise() {
    const textarea = document.getElementById('bulkExpertiseInput');
    if (!textarea) {
        showNotification('Form elements not found', 'error');
        return;
    }

    const lines = textarea.value.split('\n')
        .map(line => line.trim())
        .filter(line => line.length > 0);

    if (lines.length === 0) {
        showNotification('Please enter at least one expertise', 'error');
        return;
    }

    const expertise = lines.map(name => ({ name }));
    const saveBtn = document.getElementById('saveBulkExpertiseBtn');
    if (saveBtn) saveBtn.disabled = true;

    apiCall('/api/expertise/bulk', 'POST', { expertise })
        .then(response => {
            if (response.success) {
                closeBulkExpertiseModal();
                showNotification(`Successfully created ${response.data.successful_entries} out of ${response.data.total_entries} expertise entries`, 'success');
                fetchExpertise();
            } else {
                throw new Error(response.message || 'Failed to create bulk expertise');
            }
        })
        .catch(error => {
            console.error('Error creating bulk expertise:', error);
            showNotification(`Error creating bulk expertise: ${error.message}`, 'error');
        })
        .finally(() => {
            if (saveBtn) saveBtn.disabled = false;
        });
}

window.initExpertiseModule = initExpertiseModule;