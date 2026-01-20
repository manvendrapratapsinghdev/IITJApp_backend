// posts.js - JavaScript functionality for posts management module

// Use window object to avoid redeclaration errors and ensure global scope
window.currentPostId = window.currentPostId || null;
window.postsData = window.postsData || [];

// Initialize module function
function initPostsModule() {
    console.log('Posts module initializing...');
    
    // Check if required elements exist before adding event listeners
    const elements = {
        search: document.getElementById('postSearch'),
        statusFilter: document.getElementById('postTypeFilter'),
        retryBtn: document.getElementById('retryPostsBtn'),
        addBtn: document.getElementById('addPostBtn'),
        closeModal: document.getElementById('closePostModal'),
        cancelBtn: document.getElementById('cancelPostBtn'),
        saveBtn: document.getElementById('savePostBtn'),
        closeDeleteModal: document.getElementById('closeDeletePostModal'),
        cancelDeleteBtn: document.getElementById('cancelDeletePostBtn'),
        confirmDeleteBtn: document.getElementById('confirmDeletePostBtn'),
        form: document.getElementById('postForm'),
        title: document.getElementById('postTitle'),
        description: document.getElementById('postDescription'),
        isAnnouncement: document.getElementById('postIsAnnouncement'),
        previewContent: document.getElementById('postPreviewContent'),
        previewModal: document.getElementById('postPreviewModal'),
        closePreviewModal: document.getElementById('closePostPreviewModal'),
        closePreviewBtn: document.getElementById('closePreviewBtn')
    };

    // Check if all required elements exist
    for (const [key, element] of Object.entries(elements)) {
        if (!element) {
            console.error(`Required element not found: ${key}`);
            showNotification('Some UI elements are missing. Please refresh the page.', 'error');
            return;
        }
    }

    try {
        // Initialize event listeners
        elements.search.addEventListener('input', filterPosts);
        elements.statusFilter.addEventListener('change', filterPosts);
        elements.retryBtn.addEventListener('click', fetchPosts);
        elements.addBtn.addEventListener('click', showAddPostModal);
        
        // Modal event listeners
        elements.closeModal.addEventListener('click', closePostModal);
        elements.cancelBtn.addEventListener('click', closePostModal);
        elements.saveBtn.addEventListener('click', savePost);
        
        // Delete modal event listeners
        elements.closeDeleteModal.addEventListener('click', closeDeletePostModal);
        elements.cancelDeleteBtn.addEventListener('click', closeDeletePostModal);
        elements.confirmDeleteBtn.addEventListener('click', deletePost);
        
        // Preview modal event listeners
        elements.closePreviewModal.addEventListener('click', closePostPreviewModal);
        elements.closePreviewBtn.addEventListener('click', closePostPreviewModal);
        
        // Load initial posts data
        fetchPosts();

        // Make the elements object available globally
        window.postsElements = elements;

        console.log('Posts module initialized successfully');
    } catch (error) {
        console.error('Error initializing posts module:', error);
        showNotification('Error initializing posts module. Please refresh the page.', 'error');
    }
}

// Make sure the function is available globally
window.initPostsModule = initPostsModule;

// Function to fetch posts from API
function fetchPosts() {
    const loadingElement = document.getElementById('postsLoading');
    const errorElement = document.getElementById('postsError');
    const noPostsMessage = document.getElementById('noPostsMessage');
    const tableBody = document.querySelector('#postsTable tbody');
    
    // Show loading spinner
    loadingElement.style.display = 'flex';
    errorElement.style.display = 'none';
    noPostsMessage.style.display = 'none';
    tableBody.innerHTML = '';
    
    // Fetch posts from API
    apiCall('/api/stream/posts?limit=50')
        .then(data => {
            console.log('Raw API response:', data); // Debug log
            // Handle response from posts API
            if (data?.success && data?.posts) {
                // Transform posts data to ensure proper boolean values
                const transformedPosts = data.posts.map(post => ({
                    ...post,
                    is_announcement: post.is_announcement === true || post.is_announcement === 1 || post.is_announcement === "1"
                }));
                // Store transformed posts data globally
                window.postsData = transformedPosts;
                console.log('Transformed posts:', transformedPosts); // Debug log
            } else {
                window.postsData = [];
            }
            
            // Hide loading spinner
            loadingElement.style.display = 'none';
            
            // Check if we have posts
            if (postsData.length === 0) {
                noPostsMessage.style.display = 'block';
                return;
            }
            
            // Render posts table
            renderPostsTable(postsData);
        })
        .catch(error => {
            console.error('Error fetching posts:', error);
            loadingElement.style.display = 'none';
            errorElement.style.display = 'block';
        });
}

// Function to render posts table
function renderPostsTable(posts) {
    const tableBody = document.querySelector('#postsTable tbody');
    tableBody.innerHTML = '';
    
    posts.forEach(post => {
        const row = document.createElement('tr');
        
        // Function to truncate text
        const truncate = (text, maxWords) => {
            if (!text) return '';
            const words = text.split(' ');
            if (words.length > maxWords) {
                return words.slice(0, maxWords).join(' ') + '...';
            }
            return text;
        };
        
        // Create table cells with truncated content
        row.innerHTML = `
            <td title="${post.title}">${truncate(post.title, 20)}</td>
            <td title="${post.description}">${truncate(post.description, 10)}</td>
            <td>${truncate(post.poster?.name || 'Unknown', 10)}</td>
            <td>
                <span class="post-status ${post.is_announcement ? 'announcement' : 'regular'}">
                    ${post.is_announcement ? 'true' : 'false'}
                </span>
            </td>
            <td class="action-buttons">
                <button class="btn btn-primary btn-sm" data-post-id="${post.post_id}">View Details</button>
                <button class="btn btn-danger btn-sm" data-post-id="${post.post_id}">Delete</button>
            </td>
        `;
        
        // Add event listeners to buttons
        const buttons = row.querySelectorAll('button');
        buttons[0].addEventListener('click', () => viewPost(post.post_id));
        buttons[1].addEventListener('click', () => showDeletePostConfirmation(post.post_id));
        
        // Append row to table
        tableBody.appendChild(row);
    });
}

// Function to filter posts
function filterPosts() {
    const searchTerm = document.getElementById('postSearch').value.toLowerCase();
    const statusFilter = document.getElementById('postTypeFilter').value.toLowerCase();
    
    // Filter posts based on search term and status
    const filteredPosts = window.postsData.filter(post => {
        const matchesSearch = 
            post.title.toLowerCase().includes(searchTerm) || 
            post.description?.toLowerCase().includes(searchTerm);
        
        const matchesStatus = 
            statusFilter === '' || 
            (statusFilter === 'announcement' && post.is_announcement) ||
            (statusFilter === 'regular' && !post.is_announcement);
        
        return matchesSearch && matchesStatus;
    });
    
    // Show/hide no posts message
    const noPostsMessage = document.getElementById('noPostsMessage');
    noPostsMessage.style.display = filteredPosts.length === 0 ? 'block' : 'none';
    
    // Render filtered posts
    renderPostsTable(filteredPosts);
}

// Function to close post preview modal
function closePostPreviewModal() {
    window.postsElements?.previewModal?.classList.remove('active');
}

// Function to view post details
function viewPost(postId) {
    // Find post in our data
    const post = window.postsData.find(p => p.post_id === postId);
    
    if (!post) {
        showNotification('Post not found', 'error');
        return;
    }
    
    if (!window.postsElements?.previewContent) {
        console.error('Post preview elements not found');
        return;
    }

    // Debug: Check the post data and notification status
    console.log('Post data:', post);
    console.log('Is announcement value:', post.is_announcement);
    
    // Ensure is_announcement is properly handled
    const isAnnouncement = post.is_announcement === true || post.is_announcement === 1 || post.is_announcement === "1";
    console.log('Processed announcement value:', isAnnouncement); // Additional debug log
    
    // Format the dates
    const createdDate = new Date(post.created_at).toLocaleString();
    const updatedDate = post.updated_at ? new Date(post.updated_at).toLocaleString() : 'Never';
    
    // Prepare the content with full details in a list format
    window.postsElements.previewContent.innerHTML = `
        <div class="post-preview">
            <div class="preview-section">
                <div class="section-label">Title:</div>
                <div class="section-content">${post.title}</div>
            </div>
            
            <div class="preview-section">
                <div class="section-label">Description:</div>
                <div class="section-content">${post.description}</div>
            </div>
            
            <div class="preview-section">
                <div class="section-label">Posted by:</div>
                <div class="section-content">${post.poster?.name || 'Unknown'}</div>
            </div>
            
            <div class="preview-section">
                <div class="section-label">Is Notification:</div>
                <div class="section-content">
                    <span class="post-status ${isAnnouncement ? 'announcement' : 'regular'}">
                        ${isAnnouncement.toString()}
                    </span>
                </div>
            </div>
            
            <div class="preview-section">
                <div class="section-label">Created:</div>
                <div class="section-content">${createdDate}</div>
            </div>
            
            ${post.updated_at ? `
            <div class="preview-section">
                <div class="section-label">Last Updated:</div>
                <div class="section-content">${updatedDate}</div>
            </div>` : ''}
            
            ${post.link ? `
            <div class="preview-section">
                <div class="section-label">Link:</div>
                <div class="section-content">
                    <a href="${post.link}" target="_blank" rel="noopener noreferrer">${post.link}</a>
                </div>
            </div>` : ''}
            
            <div class="preview-section">
                <div class="section-label">Statistics:</div>
                <div class="section-content">
                    Views: ${post.view_count || 0}
                    ${post.link ? `, Link clicks: ${post.link_clicks || 0}` : ''}
                </div>
            </div>
        </div>
    `;
    
    // Show the preview modal
    window.postsElements.previewModal.classList.add('active');
}

// Function to show add post modal
function showAddPostModal() {
    // Reset form
    document.getElementById('postForm').reset();
    
    // Update modal title
    document.getElementById('postModalTitle').textContent = 'Create New Post';
    
    // Reset current post ID
    window.currentPostId = null;
    
    // Show modal
    document.getElementById('postModal').classList.add('active');
}

// Function to close post modal
function closePostModal() {
    document.getElementById('postModal').classList.remove('active');
}

// Function to save post (create)
function savePost() {
    if (!window.postsElements?.title || !window.postsElements?.description || !window.postsElements?.isAnnouncement) {
        console.error('Required form elements not found in posts module');
        showNotification('Form elements not found. Please refresh the page.', 'error');
        return;
    }
    
    // Get form values
    const title = window.postsElements.title.value.trim();
    const description = window.postsElements.description.value.trim();
    const isAnnouncement = window.postsElements.isAnnouncement.checked;
    
    // Validate required fields
    if (!title) {
        showNotification('Post title is required', 'error');
        return;
    }
    
    // Create post data object
    const postData = { 
        title, 
        description,
        is_announcement: isAnnouncement
    };
    
    // Disable form buttons
    window.postsElements.saveBtn.disabled = true;
    window.postsElements.cancelBtn.disabled = true;
    
    // Make API call to create post
    apiCall('/api/stream/posts', 'POST', postData)
        .then(() => {
            // Close modal
            closePostModal();
            
            // Show success notification
            showNotification('Post created successfully', 'success');
            
            // Refresh posts table
            fetchPosts();
        })
        .catch(error => {
            console.error('Error creating post:', error);
            showNotification(`Error creating post: ${error.message}`, 'error');
        })
        .finally(() => {
            // Re-enable form buttons
            window.postsElements.saveBtn.disabled = false;
            window.postsElements.cancelBtn.disabled = false;
        });
}

// Function to show delete post confirmation
function showDeletePostConfirmation(postId) {
    window.currentPostId = postId;
    document.getElementById('deletePostModal').classList.add('active');
}

// Function to close delete post modal
function closeDeletePostModal() {
    document.getElementById('deletePostModal').classList.remove('active');
}

// Function to delete a post
function deletePost() {
    if (!window.currentPostId) {
        showNotification('No post selected', 'error');
        return;
    }
    
    // Disable delete button
    document.getElementById('confirmDeletePostBtn').disabled = true;
    
    // Delete the post
    apiCall(`/api/stream/posts/${window.currentPostId}`, 'DELETE')
        .then(() => {
            // Close modal
            closeDeletePostModal();
            
            // Show success notification
            showNotification('Post deleted successfully', 'success');
            
            // Refresh posts table
            fetchPosts();
        })
        .catch(error => {
            console.error('Error deleting post:', error);
            showNotification(`Error deleting post: ${error.message}`, 'error');
        })
        .finally(() => {
            // Re-enable delete button
            document.getElementById('confirmDeletePostBtn').disabled = false;
        });
}