// js/notes.js - JavaScript functionality for notes management module

// Use window object to avoid redeclaration errors
window.currentSubjectId = window.currentSubjectId || null;
window.notesData = window.notesData || [];
window.currentNoteId = window.currentNoteId || null;

function initNotesModule() {
    console.log('Notes module initializing...');
    
    // Load subjects dropdown
    loadSubjectsDropdown();
    
    // Initialize event listeners
    document.getElementById('subjectFilter').addEventListener('change', handleSubjectChange);
    document.getElementById('addNoteBtn').addEventListener('click', showAddNoteModal);
    document.getElementById('retryNotesBtn').addEventListener('click', () => {
        if (window.currentSubjectId) {
            fetchNotes(window.currentSubjectId);
        }
    });
    
    // Modal event listeners
    document.getElementById('closeNoteModal').addEventListener('click', closeNoteModal);
    document.getElementById('cancelNoteBtn').addEventListener('click', closeNoteModal);
    document.getElementById('saveNoteBtn').addEventListener('click', saveNote);
    
    // Delete modal event listeners
    document.getElementById('closeDeleteNoteModal').addEventListener('click', closeDeleteNoteModal);
    document.getElementById('cancelDeleteNoteBtn').addEventListener('click', closeDeleteNoteModal);
    document.getElementById('confirmDeleteNoteBtn').addEventListener('click', deleteNote);
    
    // Note type change handler
    document.getElementById('noteType').addEventListener('change', handleNoteTypeChange);
    
    console.log('Notes module initialized successfully');
}

// Make sure the function is available globally
window.initNotesModule = initNotesModule;

// Function to load subjects dropdown
function loadSubjectsDropdown() {
    const subjectFilter = document.getElementById('subjectFilter');
    console.log('Loading subjects dropdown...');
    // Check if apiCall function is available
    if (typeof window.apiCall !== 'function') {
        console.error('apiCall function not available');
        subjectFilter.innerHTML = '<option value="">Error: API function not available</option>';
        return;
    }
    
    window.apiCall('/api/semester/subjects')
        .then(data => {
            if (data.success && data.subjects) {
                console.log('Subjects loaded successfully:', data.subjects);
                subjectFilter.innerHTML = '<option value="">-- Select a Subject --</option>';
                data.subjects.forEach(subject => {
                    const option = document.createElement('option');
                    option.value = subject.subject_id;
                    option.textContent = `${subject.name} (${subject.code})`;
                    subjectFilter.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading subjects:', error);
            subjectFilter.innerHTML = '<option value="">Error loading subjects</option>';
        });
}

// Function to handle subject selection change
function handleSubjectChange() {
    const selectedSubjectId = document.getElementById('subjectFilter').value;
    
    if (selectedSubjectId) {
        window.currentSubjectId = selectedSubjectId;
        document.getElementById('addNoteBtn').disabled = false;
        fetchNotes(selectedSubjectId);
        hideAllStates();
        document.getElementById('notesLoading').style.display = 'flex';
    } else {
        window.currentSubjectId = null;
        document.getElementById('addNoteBtn').disabled = true;
        hideAllStates();
        document.getElementById('noSubjectSelected').style.display = 'block';
    }
}

// Function to hide all state elements
function hideAllStates() {
    document.getElementById('notesLoading').style.display = 'none';
    document.getElementById('notesError').style.display = 'none';
    document.getElementById('noSubjectSelected').style.display = 'none';
    document.getElementById('noNotesMessage').style.display = 'none';
    document.getElementById('notesTableContainer').style.display = 'none';
}

// Function to fetch notes for a subject
function fetchNotes(subjectId) {
    window.apiCall(`/api/semester/subjects/${subjectId}/notes`)
        .then(data => {
            hideAllStates();
            
            if (data.success && data.notes) {
                window.notesData = data.notes;
                
                if (window.notesData.length === 0) {
                    document.getElementById('noNotesMessage').style.display = 'block';
                } else {
                    renderNotesTable(window.notesData);
                    document.getElementById('notesTableContainer').style.display = 'block';
                }
            } else {
                document.getElementById('noNotesMessage').style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error fetching notes:', error);
            hideAllStates();
            document.getElementById('notesError').style.display = 'block';
        });
}

// Function to render notes table
function renderNotesTable(notes) {
    const tableBody = document.querySelector('#notesTable tbody');
    tableBody.innerHTML = '';
    
    notes.forEach(note => {
        const row = document.createElement('tr');
        
        // Format date
        const uploadDate = new Date(note.created_at).toLocaleDateString();
        
        // Determine note type and content
        let noteContent = note.title;
        let noteType = 'text';
        let fileSize = note.file_size || 'N/A';
        
        if (note.file_url) {
            if (note.file_url.startsWith('http')) {
                noteType = 'link';
                noteContent = `<a href="${note.file_url}" target="_blank" class="file-link">${note.title}</a>`;
            } else {
                noteType = 'file';
                noteContent = `<a href="${note.file_url}" target="_blank" class="file-link">${note.title}</a>`;
            }
        }
        
        row.innerHTML = `
            <td>${noteContent}</td>
            <td>${note.description || 'No description'}</td>
            <td><span class="note-type ${noteType}">${noteType}</span></td>
            <td>${fileSize}</td>
            <td>${note.uploaded_by ? note.uploaded_by.name : 'Unknown'}</td>
            <td>${uploadDate}</td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-primary btn-sm" onclick="editNote(${note.id})">Edit</button>
                    <button class="btn btn-danger btn-sm" onclick="showDeleteNoteConfirmation(${note.id})">Delete</button>
                </div>
            </td>
        `;
        
        tableBody.appendChild(row);
    });
}

// Function to show add note modal
function showAddNoteModal() {
    if (!window.currentSubjectId) {
        showNotification('Please select a subject first', 'error');
        return;
    }
    
    // Reset form
    document.getElementById('noteForm').reset();
    document.getElementById('noteModalTitle').textContent = 'Add Note';
    window.currentNoteId = null;
    
    // Show appropriate input groups
    handleNoteTypeChange();
    
    // Show modal
    document.getElementById('noteModal').classList.add('active');
}

// Function to handle note type change
function handleNoteTypeChange() {
    const noteType = document.getElementById('noteType').value;
    const fileGroup = document.getElementById('fileUploadGroup');
    const linkGroup = document.getElementById('linkInputGroup');
    
    // Hide all groups first
    fileGroup.style.display = 'none';
    linkGroup.style.display = 'none';
    
    // Show appropriate group
    if (noteType === 'file') {
        fileGroup.style.display = 'block';
    } else if (noteType === 'link') {
        linkGroup.style.display = 'block';
    }
}

// Function to edit note
function editNote(noteId) {
    const note = window.notesData.find(n => n.id === noteId);
    if (!note) {
        showNotification('Note not found', 'error');
        return;
    }
    
    // Set form values
    document.getElementById('noteTitle').value = note.title;
    document.getElementById('noteDescription').value = note.description || '';
    
    // Determine note type
    if (note.file_url) {
        if (note.file_url.startsWith('http')) {
            document.getElementById('noteType').value = 'link';
            document.getElementById('noteLink').value = note.file_url;
        } else {
            document.getElementById('noteType').value = 'file';
        }
    } else {
        document.getElementById('noteType').value = 'text';
    }
    
    handleNoteTypeChange();
    
    // Update modal title
    document.getElementById('noteModalTitle').textContent = 'Edit Note';
    window.currentNoteId = noteId;
    
    // Show modal
    document.getElementById('noteModal').classList.add('active');
}

// Function to close note modal
function closeNoteModal() {
    document.getElementById('noteModal').classList.remove('active');
}

// Function to save note
function saveNote() {
    if (!window.currentSubjectId) {
        showNotification('No subject selected', 'error');
        return;
    }
    
    const title = document.getElementById('noteTitle').value.trim();
    const description = document.getElementById('noteDescription').value.trim();
    const noteType = document.getElementById('noteType').value;
    
    // Validate required fields
    if (!title) {
        showNotification('Title is required', 'error');
        return;
    }
    
    // Disable save button
    document.getElementById('saveNoteBtn').disabled = true;
    
    // Prepare form data
    const formData = new FormData();
    formData.append('title', title);
    formData.append('description', description);
    
    if (noteType === 'file') {
        const fileInput = document.getElementById('noteFile');
        if (fileInput.files.length > 0) {
            formData.append('file', fileInput.files[0]);
        }
    } else if (noteType === 'link') {
        const link = document.getElementById('noteLink').value.trim();
        if (link) {
            formData.append('link', link);
        }
    }
    
    // Determine if this is an update (for future enhancement)
    const isUpdate = window.currentNoteId !== null;
    
    if (isUpdate) {
        // For now, editing is not implemented in the backend
        showNotification('Note editing is not yet implemented', 'error');
        document.getElementById('saveNoteBtn').disabled = false;
        return;
    }
    
    // Create new note - Use FormData with fetch since apiCall expects JSON
    fetch(`/api/semester/subjects/${window.currentSubjectId}/notes`, {
        method: 'POST',
        body: formData,
        headers: {
            'Authorization': `Bearer ${localStorage.getItem('adminToken')}`
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeNoteModal();
            showNotification('Note created successfully', 'success');
            fetchNotes(window.currentSubjectId);
        } else {
            showNotification(data.message || 'Failed to create note', 'error');
        }
    })
    .catch(error => {
        console.error('Error saving note:', error);
        showNotification('Error saving note', 'error');
    })
    .finally(() => {
        document.getElementById('saveNoteBtn').disabled = false;
    });
}

// Function to show delete confirmation
function showDeleteNoteConfirmation(noteId) {
    window.currentNoteId = noteId;
    document.getElementById('deleteNoteModal').classList.add('active');
}

// Function to close delete modal
function closeDeleteNoteModal() {
    document.getElementById('deleteNoteModal').classList.remove('active');
}

// Function to delete note
function deleteNote() {
    if (!window.currentNoteId || !window.currentSubjectId) {
        showNotification('Invalid note or subject', 'error');
        return;
    }
    
    // Disable delete button
    document.getElementById('confirmDeleteNoteBtn').disabled = true;
    
    window.apiCall(`/api/semester/subjects/${window.currentSubjectId}/notes/${window.currentNoteId}`, 'DELETE')
        .then(data => {
            if (data.success) {
                closeDeleteNoteModal();
                showNotification('Note deleted successfully', 'success');
                fetchNotes(window.currentSubjectId);
            } else {
                showNotification(data.message || 'Failed to delete note', 'error');
            }
        })
        .catch(error => {
            console.error('Error deleting note:', error);
            showNotification('Error deleting note', 'error');
        })
        .finally(() => {
            document.getElementById('confirmDeleteNoteBtn').disabled = false;
        });
}