// class-status.js - JavaScript functionality for class status management module

let classStatusData = [];

function initClassStatusModule() {
    // Load class status data
    fetchClassStatuses();

    // Initialize event listeners
    document.getElementById('retryClassStatusBtn').addEventListener('click', fetchClassStatuses);
    document.getElementById('saveAllBtn').addEventListener('click', saveAllChanges);
}

// Function to fetch class statuses from API
function fetchClassStatuses() {
    const loadingElement = document.getElementById('classStatusLoading');
    const errorElement = document.getElementById('classStatusError');
    const noDataMessage = document.getElementById('noClassStatusMessage');
    const tableBody = document.querySelector('#classStatusTable tbody');

    // Check if elements exist
    if (!loadingElement || !errorElement || !noDataMessage || !tableBody) {
        console.error('Required DOM elements not found for class status module');
        return;
    }

    // Show loading spinner
    loadingElement.style.display = 'flex';
    errorElement.style.display = 'none';
    noDataMessage.style.display = 'none';
    tableBody.innerHTML = '';

    // Fetch class statuses from API
    apiCall('/api/admin/class-status')
        .then(data => {
            loadingElement.style.display = 'none';

            if (data && data.success && data.subjects && Array.isArray(data.subjects)) {
                classStatusData = data.subjects;
                renderClassStatusTable();
            } else {
                console.error('Invalid response format:', data);
                showError('Invalid response from server');
            }
        })
        .catch(error => {
            console.error('Error fetching class statuses:', error);
            loadingElement.style.display = 'none';
            showError('Failed to load class statuses');
        });
}

function renderClassStatusTable() {
    const tableBody = document.querySelector('#classStatusTable tbody');
    const noDataMessage = document.getElementById('noClassStatusMessage');

    if (classStatusData.length === 0) {
        noDataMessage.style.display = 'block';
        return;
    }

    noDataMessage.style.display = 'none';

    classStatusData.forEach(subject => {
        const row = document.createElement('tr');

        row.innerHTML = `
            <td>${subject.name}</td>
            <td>
                <select class="status-dropdown" data-subject-id="${subject.id}" data-day="saturday">
                    <option value="Confirm" ${subject.saturday_status === 'Confirm' ? 'selected' : ''}>Confirm</option>
                    <option value="Not Confirm" ${subject.saturday_status === 'Not Confirm' ? 'selected' : ''}>Not Confirm</option>
                    <option value="Cancelled" ${subject.saturday_status === 'Cancelled' ? 'selected' : ''}>Cancelled</option>
                </select>
            </td>
            <td>
                <select class="status-dropdown" data-subject-id="${subject.id}" data-day="sunday">
                    <option value="Confirm" ${subject.sunday_status === 'Confirm' ? 'selected' : ''}>Confirm</option>
                    <option value="Not Confirm" ${subject.sunday_status === 'Not Confirm' ? 'selected' : ''}>Not Confirm</option>
                    <option value="Cancelled" ${subject.sunday_status === 'Cancelled' ? 'selected' : ''}>Cancelled</option>
                </select>
            </td>
        `;

        tableBody.appendChild(row);
    });
}

function updateStatus(subjectId, day) {
    // This function can be used for immediate updates or just to mark as changed
    // For now, we'll handle saving on button click
    console.log(`Status changed for subject ${subjectId}, ${day}`);
}

function saveAllChanges() {
    const subjectUpdates = {};
    
    // Collect all current values
    const selects = document.querySelectorAll('select[data-subject-id]');
    selects.forEach(select => {
        const subjectId = parseInt(select.getAttribute('data-subject-id'));
        const day = select.getAttribute('data-day');
        const status = select.value;

        if (!subjectUpdates[subjectId]) {
            subjectUpdates[subjectId] = {
                subject_id: subjectId,
                saturday_status: null,
                sunday_status: null
            };
        }

        subjectUpdates[subjectId][day === 'saturday' ? 'saturday_status' : 'sunday_status'] = status;
    });

    // Check for actual changes and prepare complete updates
    const changes = [];
    Object.values(subjectUpdates).forEach(update => {
        const existingSubject = classStatusData.find(s => s.id === update.subject_id);
        if (existingSubject) {
            const saturdayChanged = update.saturday_status !== existingSubject.saturday_status;
            const sundayChanged = update.sunday_status !== existingSubject.sunday_status;
            
            if (saturdayChanged || sundayChanged) {
                changes.push({
                    subject_id: update.subject_id,
                    saturday_status: update.saturday_status,
                    sunday_status: update.sunday_status
                });
            }
        }
    });

    if (changes.length === 0) {
        showNotification('No changes to save', 'info');
        return;
    }

    // Show loading state
    const saveButton = document.getElementById('saveAllBtn');
    const originalText = saveButton.textContent;
    saveButton.textContent = 'Saving...';
    saveButton.disabled = true;

    // Send all changes to the server
    const promises = changes.map(change => {
        return apiCall('/api/admin/class-status', 'POST', change);
    });

    Promise.all(promises)
        .then(results => {
            const successCount = results.filter(result => result && result.success).length;
            if (successCount === changes.length) {
                showNotification('All changes saved successfully', 'success');
                // Refresh data
                fetchClassStatuses();
            } else {
                showNotification(`Saved ${successCount} out of ${changes.length} changes`, 'warning');
            }
        })
        .catch(error => {
            console.error('Error saving changes:', error);
            showNotification('Error saving changes', 'error');
        })
        .finally(() => {
            // Reset button state
            saveButton.textContent = originalText;
            saveButton.disabled = false;
        });
}

function showError(message) {
    const errorElement = document.getElementById('classStatusError');
    const loadingElement = document.getElementById('classStatusLoading');

    loadingElement.style.display = 'none';
    errorElement.style.display = 'block';
    errorElement.querySelector('p').textContent = message;
}

// Initialize the module when the page loads
document.addEventListener('DOMContentLoaded', function() {
    // The main.js will call initClassStatusModule when the module is activated
});