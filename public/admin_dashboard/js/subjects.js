// subjects.js - JavaScript functionality for subject management module

let currentSubjectId = null;
let subjectsData = [];
let notesData = [];
let currentNoteId = null;
let facultyData = [];

// Function to load faculty data for dropdown
function loadFacultyData() {
    apiCall('/api/admin/faculty')
        .then(data => {
            if (data && data.success && data.faculty && Array.isArray(data.faculty)) {
                facultyData = data.faculty;
                populateFacultyDropdown();
            } else if (Array.isArray(data)) {
                facultyData = data;
                populateFacultyDropdown();
            }
        })
        .catch(error => {
            console.error('Error loading faculty data:', error);
        });
}

// Function to populate faculty dropdown
function populateFacultyDropdown() {
    const facultySelect = document.getElementById('subjectFaculty');
    if (!facultySelect) return;
    
    // Clear existing options except the first one
    facultySelect.innerHTML = '<option value="">Select Faculty</option>';
    
    // Add faculty options
    facultyData.forEach(faculty => {
        const option = document.createElement('option');
        option.value = faculty.id || faculty.user_id;
        option.textContent = faculty.name;
        facultySelect.appendChild(option);
    });
}

function initSubjectsModule() {
    // Load subjects data
    fetchSubjects();
    loadFacultyData();
    
    // Initialize event listeners
    document.getElementById('subjectSearch').addEventListener('input', filterSubjects);
    document.getElementById('retrySubjectsBtn').addEventListener('click', fetchSubjects);
    document.getElementById('addSubjectBtn').addEventListener('click', showAddSubjectModal);
    
    // Modal event listeners
    document.getElementById('closeSubjectModal').addEventListener('click', closeSubjectModal);
    document.getElementById('cancelSubjectBtn').addEventListener('click', closeSubjectModal);
    document.getElementById('saveSubjectBtn').addEventListener('click', saveSubject);
    
    // Delete subject modal event listeners
    document.getElementById('closeDeleteSubjectModal').addEventListener('click', closeDeleteSubjectModal);
    document.getElementById('cancelDeleteSubjectBtn').addEventListener('click', closeDeleteSubjectModal);
    document.getElementById('confirmDeleteSubjectBtn').addEventListener('click', deleteSubject);
}

// Function to fetch subjects from API
function fetchSubjects() {
    const loadingElement = document.getElementById('subjectsLoading');
    const errorElement = document.getElementById('subjectsError');
    const noSubjectsMessage = document.getElementById('noSubjectsMessage');
    const tableBody = document.querySelector('#subjectsTable tbody');
    
    // Check if elements exist
    if (!loadingElement || !errorElement || !noSubjectsMessage || !tableBody) {
        console.error('Required DOM elements not found for subjects module');
        return;
    }
    
    // Show loading spinner
    loadingElement.style.display = 'flex';
    errorElement.style.display = 'none';
    noSubjectsMessage.style.display = 'none';
    tableBody.innerHTML = '';
    
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
                    code: subject.code,
                    order: typeof subject.order !== 'undefined' ? subject.order : null,
                    faculty_id: subject.faculty ? subject.faculty.user_id : null,
                    faculty_name: subject.faculty ? subject.faculty.name : null,
                    description: subject.description,
                    notes_count: 0 // Will be updated when notes are loaded
                }));
                subjectsData = subjectsArray;
            } else if (Array.isArray(data)) {
                // Handle direct array response
                subjectsArray = data;
                subjectsData = subjectsArray;
            } else {
                subjectsData = [];
            }
            
            // Hide loading spinner
            loadingElement.style.display = 'none';
            
            // Check if we have subjects
            if (subjectsArray.length === 0) {
                noSubjectsMessage.style.display = 'block';
                return;
            }
            
            // Render subjects table
            renderSubjectsTable(subjectsArray);
        })
        .catch(error => {
            console.error('Error fetching subjects:', error);
            loadingElement.style.display = 'none';
            errorElement.style.display = 'block';
        });
}

// Function to render subjects table
function renderSubjectsTable(subjects) {
    const tableBody = document.querySelector('#subjectsTable tbody');
    tableBody.innerHTML = '';
    
    subjects.forEach((subject, index) => {
        const row = document.createElement('tr');
        // Use faculty name from subject data if available
        let facultyName = 'N/A';
        if (subject.faculty_name) {
            facultyName = subject.faculty_name;
        }
        // Create table cells with serial number
        row.innerHTML = `
            <td>${index + 1}</td>
            <td>${subject.name}</td>
            <td><span class="subject-order">${typeof subject.order !== 'undefined' && subject.order !== null ? subject.order : ''}</span></td>
            <td>${facultyName}</td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-primary btn-sm" data-action="edit" data-subject-id="${subject.id}">Edit</button>
                    <button class="btn btn-danger btn-sm" data-action="delete" data-subject-id="${subject.id}">Delete</button>
                </div>
            </td>
        `;
        // Add event listeners for buttons
        row.querySelector('[data-action="edit"]').addEventListener('click', () => editSubject(subject.id));
        row.querySelector('[data-action="delete"]').addEventListener('click', () => showDeleteSubjectConfirmation(subject.id));
        // Append row to table
        tableBody.appendChild(row);
    });
}

// Function to filter subjects
function filterSubjects() {
    const searchTerm = document.getElementById('subjectSearch').value.toLowerCase();
    
    // Filter subjects based on search term
    const filteredSubjects = subjectsData.filter(subject => 
        subject.name.toLowerCase().includes(searchTerm) || 
        subject.code.toLowerCase().includes(searchTerm)
    );
    
    // Show/hide no subjects message
    document.getElementById('noSubjectsMessage').style.display = 
        filteredSubjects.length === 0 ? 'block' : 'none';
    
    // Render filtered subjects
    renderSubjectsTable(filteredSubjects);
}

// Function to show add subject modal
function showAddSubjectModal() {
    // Reset form
    document.getElementById('subjectForm').reset();
    
    // Update modal title
    document.getElementById('subjectModalTitle').textContent = 'Add New Subject';
    
    // Reset current subject ID
    currentSubjectId = null;
    
    // Populate faculty dropdown
    populateFacultyDropdown();
    
    // Show modal
    document.getElementById('subjectModal').classList.add('active');
}

// Function to show edit subject modal
function editSubject(subjectId) {
    // Find subject in our data
    const subject = subjectsData.find(s => s.id === subjectId);
    
    if (!subject) {
        showNotification('Subject not found', 'error');
        return;
    }
    
    // Fetch full subject details from API
    apiCall(`/api/semester/subjects/${subjectId}`)
        .then(data => {
            const subjectDetails = data.subject || data;
            // Extract faculty_id and faculty_name from nested faculty object if present
            let faculty_id = '';
            if (subjectDetails.faculty && (subjectDetails.faculty.user_id || subjectDetails.faculty.id)) {
                faculty_id = subjectDetails.faculty.user_id || subjectDetails.faculty.id;
            } else if (subjectDetails.faculty_id) {
                faculty_id = subjectDetails.faculty_id;
            }
            // Update modal title
            document.getElementById('subjectModalTitle').textContent = 'Edit Subject';
            // Populate faculty dropdown first
            populateFacultyDropdown();
            // Set form values
            document.getElementById('subjectName').value = subjectDetails.name || '';
            document.getElementById('subjectCode').value = subjectDetails.code || '';
            document.getElementById('subjectOrder').value = subjectDetails.order || '';
            document.getElementById('subjectFaculty').value = faculty_id;
            document.getElementById('subjectCredits').value = subjectDetails.credits || '';
            document.getElementById('subjectSemester').value = subjectDetails.semester || '';
            document.getElementById('subjectDescription').value = subjectDetails.description || '';
            document.getElementById('subjectSyllabusUrl').value = subjectDetails.syllabus_url || '';
            // Set class schedule
            if (subjectDetails.class_schedule) {
                document.getElementById('saturdaySchedule').value = subjectDetails.class_schedule.saturday || '';
                document.getElementById('sundaySchedule').value = subjectDetails.class_schedule.sunday || '';
            }
            // Set class links
            if (subjectDetails.class_links) {
                document.getElementById('zoomLink').value = subjectDetails.class_links.zoom || '';
                document.getElementById('classroomLocation').value = subjectDetails.class_links.classroom || '';
            }
            // Set current subject ID
            currentSubjectId = subjectId;
            // Show modal
            document.getElementById('subjectModal').classList.add('active');
        })
        .catch(error => {
            console.error('Error fetching subject details:', error);
            showNotification('Error loading subject details', 'error');
        });
}

// Function to close subject modal
function closeSubjectModal() {
    document.getElementById('subjectModal').classList.remove('active');
}

// Function to save subject (create or update)
function saveSubject() {
    // Get form values
    const name = document.getElementById('subjectName').value.trim();
    const code = document.getElementById('subjectCode').value.trim();
    const order = document.getElementById('subjectOrder').value.trim();
    const faculty_id = document.getElementById('subjectFaculty').value;
    const credits = document.getElementById('subjectCredits').value;
    const semester = document.getElementById('subjectSemester').value.trim();
    const description = document.getElementById('subjectDescription').value.trim();
    const syllabus_url = document.getElementById('subjectSyllabusUrl').value.trim();
    // Validate required fields
    if (!name || !code) {
        showNotification('Subject name and code are required', 'error');
        return;
    }
    // Build class schedule object
    const class_schedule = {
        saturday: document.getElementById('saturdaySchedule').value.trim(),
        sunday: document.getElementById('sundaySchedule').value.trim()
    };
    // Remove empty schedule entries
    Object.keys(class_schedule).forEach(day => {
        if (!class_schedule[day]) {
            delete class_schedule[day];
        }
    });
    // Build class links object
    const class_links = {};
    const zoomLink = document.getElementById('zoomLink').value.trim();
    const classroomLocation = document.getElementById('classroomLocation').value.trim();
    if (zoomLink) class_links.zoom = zoomLink;
    if (classroomLocation) class_links.classroom = classroomLocation;
    // Create subject data object
    const subjectData = { 
        name, 
        code,
        description
    };
    if (order) subjectData.order = parseInt(order, 10);
    // Add optional fields if they have values
    if (faculty_id) subjectData.faculty_id = faculty_id;
    if (credits) subjectData.credits = parseInt(credits);
    if (semester) subjectData.semester = semester;
    if (syllabus_url) subjectData.syllabus_url = syllabus_url;
    if (Object.keys(class_schedule).length > 0) subjectData.class_schedule = class_schedule;
    if (Object.keys(class_links).length > 0) subjectData.class_links = class_links;
    
    // Determine if this is a create or update operation
    const isUpdate = currentSubjectId !== null;
    const url = isUpdate ? `/api/admin/subjects/${currentSubjectId}` : '/api/admin/subjects';
    const method = isUpdate ? 'PUT' : 'POST';
    
    // Disable form buttons
    document.getElementById('saveSubjectBtn').disabled = true;
    document.getElementById('cancelSubjectBtn').disabled = true;
    
    // Make API call
    apiCall(url, method, subjectData)
        .then(() => {
            // Close modal
            closeSubjectModal();
            
            // Show success notification
            showNotification(`Subject ${isUpdate ? 'updated' : 'created'} successfully`, 'success');
            
            // Refresh subjects table
            fetchSubjects();
        })
        .catch(error => {
            console.error(`Error ${isUpdate ? 'updating' : 'creating'} subject:`, error);
            showNotification(`Error ${isUpdate ? 'updating' : 'creating'} subject: ${error.message}`, 'error');
        })
        .finally(() => {
            // Re-enable form buttons
            document.getElementById('saveSubjectBtn').disabled = false;
            document.getElementById('cancelSubjectBtn').disabled = false;
        });
}



// Note: deleteNote function replaced with confirmDeleteNote and showDeleteNoteConfirmation
// for better user experience with confirmation modal

// Function to show delete subject confirmation
function showDeleteSubjectConfirmation(subjectId) {
    currentSubjectId = subjectId;
    document.getElementById('deleteSubjectModal').classList.add('active');
}

// Function to close delete subject modal
function closeDeleteSubjectModal() {
    document.getElementById('deleteSubjectModal').classList.remove('active');
}

// Function to delete a subject
function deleteSubject() {
    if (!currentSubjectId) {
        showNotification('No subject selected', 'error');
        return;
    }
    
    // Disable delete button
    document.getElementById('confirmDeleteSubjectBtn').disabled = true;
    
    // Delete the subject
    apiCall(`/api/admin/subjects/${currentSubjectId}`, 'DELETE')
        .then(() => {
            // Close modal
            closeDeleteSubjectModal();
            
            // Show success notification
            showNotification('Subject deleted successfully', 'success');
            
            // Refresh subjects table
            fetchSubjects();
        })
        .catch(error => {
            console.error('Error deleting subject:', error);
            showNotification(`Error deleting subject: ${error.message}`, 'error');
        })
        .finally(() => {
            // Re-enable delete button
            document.getElementById('confirmDeleteSubjectBtn').disabled = false;
        });
}

