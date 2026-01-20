// schedules.js - JavaScript functionality for schedule management module

// Use window object to avoid redeclaration errors and ensure global scope
window.currentScheduleId = window.currentScheduleId || null;
window.schedulesData = window.schedulesData || [];
window.subjectsData = window.subjectsData || [];



// Define the initialization function
function initSchedulesModule() {
    console.log('Schedules module initializing...');
    // Load schedules data
    fetchSchedules();
    
    // Load subjects data for dropdowns
    fetchSubjectsForSchedules();
    
    // Add event delegation for dynamically added buttons
    document.querySelector('#schedulesTable').addEventListener('click', function(e) {
        const target = e.target;
        if (target.matches('[data-action="edit"]')) {
            const scheduleId = parseInt(target.getAttribute('data-schedule-id'));
            editSchedule(scheduleId);
        } else if (target.matches('[data-action="delete"]')) {
            const scheduleId = parseInt(target.getAttribute('data-schedule-id'));
            showDeleteScheduleConfirmation(scheduleId);
        }
    });
    
    // Initialize event listeners
    document.getElementById('scheduleSearch').addEventListener('input', filterSchedules);
    document.getElementById('typeFilter').addEventListener('change', filterSchedules);
    document.getElementById('subjectFilter').addEventListener('change', filterSchedules);
    document.getElementById('retrySchedulesBtn').addEventListener('click', fetchSchedules);
    document.getElementById('addScheduleBtn').addEventListener('click', showAddScheduleModal);
    
    // Modal event listeners
    document.getElementById('closeScheduleModal').addEventListener('click', closeScheduleModal);
    document.getElementById('cancelScheduleBtn').addEventListener('click', closeScheduleModal);
    document.getElementById('saveScheduleBtn').addEventListener('click', saveSchedule);
    
    document.getElementById('closeDeleteScheduleModal').addEventListener('click', closeDeleteScheduleModal);
    document.getElementById('cancelDeleteScheduleBtn').addEventListener('click', closeDeleteScheduleModal);
    document.getElementById('confirmDeleteScheduleBtn').addEventListener('click', deleteSchedule);
    
    // If subjects data is already available, populate dropdowns immediately
    if (window.subjectsData && window.subjectsData.length > 0) {
        populateSubjectDropdowns();
    }
}

// Function to fetch schedules from API
function fetchSchedules() {
    const loadingElement = document.getElementById('schedulesLoading');
    const errorElement = document.getElementById('schedulesError');
    const noSchedulesMessage = document.getElementById('noSchedulesMessage');
    const tableBody = document.querySelector('#schedulesTable tbody');
    
    // Show loading spinner
    loadingElement.style.display = 'flex';
    errorElement.style.display = 'none';
    noSchedulesMessage.style.display = 'none';
    tableBody.innerHTML = '';
    
    // Fetch schedules from API
    apiCall('/api/semester/schedules')
        .then(data => {
            // Handle response from schedules API
            let schedulesArray = [];
            if (data && data.success && data.schedules) {
                // Combine quiz and assignment schedules
                schedulesArray = [...(data.schedules.quiz || []), ...(data.schedules.assignment || [])];
                // Store schedules data globally
                window.schedulesData = schedulesArray;
            } else if (Array.isArray(data)) {
                // Handle direct array response
                schedulesArray = data;
                window.schedulesData = schedulesArray;
            } else {
                window.schedulesData = [];
            }
            
            // Hide loading spinner
            loadingElement.style.display = 'none';
            
            // Check if we have schedules
            if (schedulesArray.length === 0) {
                noSchedulesMessage.style.display = 'block';
                return;
            }
            
            // Render schedules table
            renderSchedulesTable(schedulesArray);
        })
        .catch(error => {
            console.error('Error fetching schedules:', error);
            loadingElement.style.display = 'none';
            errorElement.style.display = 'block';
        });
}

// Function to fetch subjects for dropdowns
function fetchSubjectsForSchedules() {
    // Fetch subjects from API
    apiCall('/api/semester/subjects')
        .then(data => {
            // Handle response from subjects API
            let subjectsArray = [];
            if (data && data.success && data.subjects && Array.isArray(data.subjects)) {
                // Map the response to match expected format
                subjectsArray = data.subjects.map(subject => ({
                    id: subject.subject_id,
                    name: subject.name,
                    code: subject.code
                }));
            } else if (Array.isArray(data)) {
                subjectsArray = data;
            }
            
            // Store subjects data globally
            window.subjectsData = subjectsArray;
            
            // Populate subject dropdowns only if the function exists and elements are present
            if (typeof populateSubjectDropdowns === 'function') {
                populateSubjectDropdowns();
            }
        })
        .catch(error => {
            console.error('Error fetching subjects:', error);
            window.subjectsData = [];
            // Still try to populate with empty data if function exists
            if (typeof populateSubjectDropdowns === 'function') {
                populateSubjectDropdowns();
            }
        });
}

// Function to populate subject dropdowns
function populateSubjectDropdowns() {
    // Make this function globally available with a namespaced name
    window.schedulesPopulateSubjectDropdowns = populateSubjectDropdowns;
    
    // Exit early if subjectsData is not available
    if (!window.subjectsData || !Array.isArray(window.subjectsData)) {
        return;
    }
    
    // Check if we're in the schedules module by looking for schedule-specific elements
    const isSchedulesModule = document.getElementById('subjectFilter') !== null || 
                              document.getElementById('scheduleSubject') !== null;
    
    // If we're not in the schedules module, don't try to populate schedule dropdowns
    if (!isSchedulesModule) {
        return;
    }
    
    // Populate filter dropdown (only if it exists in current module)
    const filterDropdown = document.getElementById('subjectFilter');
    if (filterDropdown) {
        // Clear existing options except the first one
        while (filterDropdown.options.length > 1) {
            filterDropdown.remove(1);
        }
        
        // Add subject options to filter dropdown
        window.subjectsData.forEach(subject => {
            const filterOption = document.createElement('option');
            filterOption.value = subject.id;
            filterOption.textContent = subject.name;
            filterDropdown.appendChild(filterOption);
        });
    }
    
    // Populate form dropdown (only if it exists in current module)
    const formDropdown = document.getElementById('scheduleSubject');
    if (formDropdown) {
        // Clear existing options except the first one
        while (formDropdown.options.length > 1) {
            formDropdown.remove(1);
        }
        
        // Add subject options to form dropdown
        window.subjectsData.forEach(subject => {
            const formOption = document.createElement('option');
            formOption.value = subject.id;
            formOption.textContent = subject.name;
            formDropdown.appendChild(formOption);
        });
    }
}

// Function to render schedules table
function renderSchedulesTable(schedules) {
    const tableBody = document.querySelector('#schedulesTable tbody');
    tableBody.innerHTML = '';
    
    schedules.forEach(schedule => {
        const row = document.createElement('tr');
        
        // Find subject name if subject_id exists
        let subjectName = 'N/A';
        if (schedule.subject_id && subjectsData.length > 0) {
            const subject = subjectsData.find(s => s.id === schedule.subject_id);
            if (subject) {
                subjectName = subject.name;
            }
        }
        
        // Format due date and determine if it's overdue, today, or upcoming
        const dueDate = new Date(schedule.date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        const formattedDate = dueDate.toLocaleString();
        
        let dueDateClass = 'upcoming';
        if (dueDate < today) {
            dueDateClass = 'overdue';
        } else if (dueDate.toDateString() === today.toDateString()) {
            dueDateClass = 'today';
        }
        
        // Create table cells
        row.innerHTML = `
            <td>${schedule.schedule_id}</td>
            <td>${schedule.title}</td>
            <td><span class="schedule-type ${schedule.type.toLowerCase()}">${schedule.type}</span></td>
            <td><div class="subject-name" title="${schedule.subject ? schedule.subject.name : 'N/A'}">${schedule.subject ? schedule.subject.name : 'N/A'}</div></td>
            <td><span class="due-date ${dueDateClass}">${formattedDate}</span></td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-primary btn-sm" data-action="edit" data-schedule-id="${schedule.schedule_id}">Edit</button>
                    <button class="btn btn-danger btn-sm" data-action="delete" data-schedule-id="${schedule.schedule_id}">Delete</button>
                </div>
            </td>
        `;
        
        // Add event listeners for buttons
        row.querySelector('[data-action="edit"]').addEventListener('click', () => editSchedule(schedule.schedule_id));
        row.querySelector('[data-action="delete"]').addEventListener('click', () => showDeleteScheduleConfirmation(schedule.schedule_id));
        
        // Append row to table
        tableBody.appendChild(row);
    });
}

// Function to filter schedules
function filterSchedules() {
    const searchTerm = document.getElementById('scheduleSearch').value.toLowerCase();
    const typeFilter = document.getElementById('typeFilter').value.toLowerCase();
    const subjectFilter = document.getElementById('subjectFilter').value;
    
    // Filter schedules based on search term, type, and subject
    const filteredSchedules = schedulesData.filter(schedule => {
        const matchesSearch = schedule.title.toLowerCase().includes(searchTerm);
        
        const matchesType = typeFilter === '' || schedule.type.toLowerCase() === typeFilter;
        
        const matchesSubject = 
            subjectFilter === '' || 
            (schedule.subject && schedule.subject.subject_id === Number(subjectFilter));
        
        return matchesSearch && matchesType && matchesSubject;
    });
    
    // Show/hide no schedules message
    document.getElementById('noSchedulesMessage').style.display = 
        filteredSchedules.length === 0 ? 'block' : 'none';
    
    // Render filtered schedules
    renderSchedulesTable(filteredSchedules);
}

// Function to show add schedule modal
function showAddScheduleModal() {
    // Reset form
    document.getElementById('scheduleForm').reset();
    
    // Set default due date to tomorrow in YYYY-MM-DD format
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const year = tomorrow.getFullYear();
    const month = String(tomorrow.getMonth() + 1).padStart(2, '0');
    const day = String(tomorrow.getDate()).padStart(2, '0');
    document.getElementById('scheduleDueDate').value = `${year}-${month}-${day}`;
    
    // Set default time to 09:00
    document.getElementById('scheduleDueTime').value = '09:00';
    
    // Update modal title
    document.getElementById('scheduleModalTitle').textContent = 'Add New Schedule';
    
    // Reset current schedule ID
    currentScheduleId = null;
    
    // Show modal
    document.getElementById('scheduleModal').classList.add('active');
}

// Function to show edit schedule modal
function editSchedule(scheduleId) {
    console.log('Edit clicked for schedule:', scheduleId);
    // Find schedule in our data
    const schedule = window.schedulesData.find(s => s.schedule_id === scheduleId);
    console.log('Found schedule:', schedule);
    
    if (!schedule) {
        showNotification('Schedule not found', 'error');
        return;
    }
    
    // Update modal title
    document.getElementById('scheduleModalTitle').textContent = 'Edit Schedule';
    
    // Validate that we have a valid schedule object
    if (typeof schedule !== 'object') {
        showNotification('Invalid schedule data', 'error');
        return;
    }
    
    // Format date for input - use date property instead of due_date
    try {
        // Set mandatory fields
    document.getElementById('scheduleTitle').value = schedule.title || '';
    document.getElementById('scheduleType').value = schedule.type ? schedule.type.toLowerCase() : '';
    document.getElementById('scheduleSubject').value = schedule.subject_id || (schedule.subject && schedule.subject.subject_id) || '';
    
    // Handle date and time parsing
    if (schedule.date) {
        const scheduleDate = new Date(schedule.date);
        // Set date (YYYY-MM-DD format)
        const year = scheduleDate.getFullYear();
        const month = String(scheduleDate.getMonth() + 1).padStart(2, '0');
        const day = String(scheduleDate.getDate()).padStart(2, '0');
        document.getElementById('scheduleDueDate').value = `${year}-${month}-${day}`;
        
        // Set time (HH:MM format)
        const hours = String(scheduleDate.getHours()).padStart(2, '0');
        const minutes = String(scheduleDate.getMinutes()).padStart(2, '0');
        document.getElementById('scheduleDueTime').value = `${hours}:${minutes}`;
    } else {
        document.getElementById('scheduleDueDate').value = '';
        document.getElementById('scheduleDueTime').value = '09:00';
    }
    
    // Set optional fields only if they exist
    if (schedule.description) document.getElementById('scheduleDescription').value = schedule.description;
    if (schedule.max_marks) document.getElementById('scheduleMaxMarks').value = schedule.max_marks;
    if (schedule.location) document.getElementById('scheduleLocation').value = schedule.location;
    if (schedule.instructions) document.getElementById('scheduleInstructions').value = schedule.instructions;
    // Removed: scheduleSubmissionLink input does not exist in the HTML form
    // if (schedule.submission_link) document.getElementById('scheduleSubmissionLink').value = schedule.submission_link;
    
    // Set current schedule ID
    currentScheduleId = scheduleId;
    
    // Show modal
    document.getElementById('scheduleModal').classList.add('active');
    } catch (error) {
        console.error('Error setting form values:', error);
        showNotification('Error loading schedule data', 'error');
    }
}

// Function to close schedule modal
function closeScheduleModal() {
    document.getElementById('scheduleModal').classList.remove('active');
}

// Function to save schedule (create or update)
function saveSchedule() {
    // Get form values
    const title = document.getElementById('scheduleTitle').value;
    const type = document.getElementById('scheduleType').value;
    const subject_id = document.getElementById('scheduleSubject').value;
    const due_date = document.getElementById('scheduleDueDate').value;
    const due_time = document.getElementById('scheduleDueTime').value;
    const description = document.getElementById('scheduleDescription').value;
    const max_marks = document.getElementById('scheduleMaxMarks').value;
    
    // Validate only mandatory fields (type, title, subject_id, date, time)
    const mandatoryFields = {
        'Title': title,
        'Type': type,
        'Subject': subject_id,
        'Date': due_date,
        'Time': due_time
    };

    const missingFields = Object.entries(mandatoryFields)
        .filter(([_, value]) => !value)
        .map(([field]) => field);

    if (missingFields.length > 0) {
        showNotification(`Please fill in required fields: ${missingFields.join(', ')}`, 'error');
        return;
    }
    
    // Combine date and time into a datetime string
    const datetime = `${due_date} ${due_time}:00`; // Add seconds for complete datetime format
    
    // Create schedule data object
    const scheduleData = {
        title,
        type,
        subject_id: Number(subject_id),
        date: datetime, // Send combined datetime
        description,
        location: document.getElementById('scheduleLocation').value,
        instructions: document.getElementById('scheduleInstructions').value,
    // submission_link: document.getElementById('scheduleSubmissionLink').value, // Removed: input does not exist
        max_marks: document.getElementById('scheduleMaxMarks').value ? Number(document.getElementById('scheduleMaxMarks').value) : null,
        duration_minutes: type === 'quiz' ? 60 : null // Default duration for quiz
    };
    
    // Add max_marks if provided
    if (max_marks) {
        scheduleData.max_marks = Number(max_marks);
    }
    
    // Determine if this is a create or update operation
    const isUpdate = currentScheduleId !== null;
    const url = isUpdate ? `/api/admin/schedules/${currentScheduleId}` : '/api/admin/schedules';
    const method = isUpdate ? 'PUT' : 'POST';
    
    // Disable form buttons
    document.getElementById('saveScheduleBtn').disabled = true;
    document.getElementById('cancelScheduleBtn').disabled = true;
    
    // Make API call
    apiCall(url, method, scheduleData)
        .then(() => {
            // Close modal
            closeScheduleModal();
            
            // Show success notification
            showNotification(`Schedule ${isUpdate ? 'updated' : 'created'} successfully`, 'success');
            
            // Refresh schedules table
            fetchSchedules();
        })
        .catch(error => {
            console.error(`Error ${isUpdate ? 'updating' : 'creating'} schedule:`, error);
            showNotification(`Error ${isUpdate ? 'updating' : 'creating'} schedule: ${error.message}`, 'error');
        })
        .finally(() => {
            // Re-enable form buttons
            document.getElementById('saveScheduleBtn').disabled = false;
            document.getElementById('cancelScheduleBtn').disabled = false;
        });
}

// Function to show delete schedule confirmation
function showDeleteScheduleConfirmation(scheduleId) {
    currentScheduleId = scheduleId;
    document.getElementById('deleteScheduleModal').classList.add('active');
}

// Function to close delete schedule modal
function closeDeleteScheduleModal() {
    document.getElementById('deleteScheduleModal').classList.remove('active');
}

// Function to delete a schedule
function deleteSchedule() {
    if (!currentScheduleId) {
        showNotification('No schedule selected', 'error');
        return;
    }
    
    // Disable delete button
    document.getElementById('confirmDeleteScheduleBtn').disabled = true;
    
    // Delete the schedule
    apiCall(`/api/admin/schedules/${currentScheduleId}`, 'DELETE')
        .then(() => {
            // Close modal
            closeDeleteScheduleModal();
            
            // Show success notification
            showNotification('Schedule deleted successfully', 'success');
            
            // Refresh schedules table
            fetchSchedules();
        })
        .catch(error => {
            console.error('Error deleting schedule:', error);
            showNotification(`Error deleting schedule: ${error.message}`, 'error');
        })
        .finally(() => {
            // Re-enable delete button
            document.getElementById('confirmDeleteScheduleBtn').disabled = false;
        });
}

// Make the initialization function globally available
window.initSchedulesModule = initSchedulesModule;