// main.js - Core functionality for the admin dashboard

// Global API Configuration
window.API_CONFIG = {
    // Base URL for all API requests
    baseUrl: window.location.hostname === 'localhost' ? 'http://localhost:8000' : '',
    // API prefix
    apiPrefix: '/api'
};

document.addEventListener('DOMContentLoaded', function() {
    // Initialize sidebar toggle for mobile
    initializeSidebarToggle();

    // Module Navigation
    initializeModuleNavigation();
    
    // Card navigation on home page
    initializeCardNavigation();
    
    // Logout functionality
    document.getElementById('logoutBtn').addEventListener('click', handleLogout);
    
    // Handle hash changes for navigation
    window.addEventListener('hashchange', handleHashChange);
    handleHashChange(); // Handle initial hash

    // Check authentication (this will also call updateUserInfo)
    checkAuthentication();
});

// Function to initialize module navigation
function initializeModuleNavigation() {
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get the module name from data attribute
            const moduleName = this.getAttribute('data-module');
            
            // Update active class on nav links
            navLinks.forEach(link => link.classList.remove('active'));
            this.classList.add('active');
            
            // Load the selected module
            loadModule(moduleName);
        });
    });
}

// Function to handle hash changes for navigation
function handleHashChange() {
    const hash = window.location.hash.substring(1);
    if (!hash) {
        // If no hash, default to home
        loadModule('home');
        return;
    }

    const moduleName = hash.split('?')[0];
    
    // Update active class on nav links
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('data-module') === moduleName) {
            link.classList.add('active');
        }
    });

    loadModule(moduleName);
}

// Function to initialize card navigation on home page
function initializeCardNavigation() {
    const cards = document.querySelectorAll('.dashboard-card');
    
    cards.forEach(card => {
        card.addEventListener('click', function() {
            // Get the module name from data attribute
            const moduleName = this.getAttribute('data-module');
            
            // Update active class on nav links
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('data-module') === moduleName) {
                    link.classList.add('active');
                }
            });
            
            // Load the selected module
            loadModule(moduleName);
        });
    });
}

// Function to load a module
async function loadModule(moduleName) {
    console.log('Loading module:', moduleName);
    
    // For home module, no access check needed
    if (moduleName === 'home') {
        showModule(moduleName);
        return;
    }
    
    // Verify access to the module before loading
    try {
        const hasAccess = await verifyModuleAccess(moduleName);
        if (!hasAccess) {
            showAccessDeniedMessage(moduleName);
            return;
        }
        
        showModule(moduleName);
        
    } catch (error) {
        console.error('Error verifying module access:', error);
        showAccessDeniedMessage(moduleName);
    }
}

// Function to verify module access with server
async function verifyModuleAccess(moduleName) {
    try {
        const response = await apiCall(`/user/module/${moduleName}/access`);
        return response.success && response.access_granted;
    } catch (error) {
        console.error('Module access verification failed:', error);
        return false; // Deny access on error
    }
}

// Function to show a module (extracted from loadModule)
function showModule(moduleName) {
    // Hide all module containers
    const moduleContainers = document.querySelectorAll('.module-container');
    moduleContainers.forEach(container => {
        container.classList.remove('active');
    });
    
    // Show the selected module container
    const selectedModule = document.getElementById(`${moduleName}-module`);
    console.log('Selected module element:', selectedModule);
    
    if (selectedModule) {
        selectedModule.classList.add('active');
        
        // If module is not home, load its content or re-initialize it
        if (moduleName !== 'home') {
            // If content is not loaded yet, fetch it.
            if (selectedModule.innerHTML.trim() === '') {
                loadModuleContent(moduleName, selectedModule);
            } else {
                // If content is already loaded, just re-run the init function.
                const camelCaseModuleName = moduleName.replace(/-(\w)/g, (match, letter) => letter.toUpperCase());
                const initFunctionName = `init${capitalize(camelCaseModuleName)}Module`;
                if (window[initFunctionName]) {
                    console.log(`Re-initializing module: ${moduleName}`);
                    window[initFunctionName]();
                }
            }
        }
    }
}

// Function to show access denied message
function showAccessDeniedMessage(moduleName) {
    // Hide all module containers
    const moduleContainers = document.querySelectorAll('.module-container');
    moduleContainers.forEach(container => {
        container.classList.remove('active');
    });
    
    // Show home module
    const homeModule = document.getElementById('home-module');
    if (homeModule) {
        homeModule.classList.add('active');
    }
    
    // Show notification
    showNotification(`Access denied to ${moduleName} module. Insufficient permissions.`, 'error');
    
    // Navigate back to home
    window.location.hash = 'home';
}

// Global function to navigate to a module (for use by other modules)
function navigateToModule(moduleName) {
    window.location.hash = moduleName;
}

// Function to load module content
function loadModuleContent(moduleName, container) {
    // Show loading spinner
    container.innerHTML = '<div class="loading-container"><div class="loading-spinner"></div><p>Loading module...</p></div>';
    
    // Fetch the module HTML content
    fetch(`modules/${moduleName}.html`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Failed to load module: ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            container.innerHTML = html;

            // Dynamically load the module's CSS if it exists
            const cssPath = `css/${moduleName}.css`;
            if (!document.querySelector(`link[href="${cssPath}"]`)) {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = cssPath;
                document.head.appendChild(link);
            }

            // Dynamically load the module's JavaScript if it exists
            const scriptPath = `js/${moduleName}.js`;
            if (!document.querySelector(`script[src="${scriptPath}"]`)) {
                const script = document.createElement('script');
                script.src = scriptPath;
                script.onload = () => initializeModule(moduleName, container);
                document.body.appendChild(script);
            } else {
                initializeModule(moduleName, container);
            }
        })
        .catch(error => {
            container.innerHTML = `
                <div class="error-container">
                    <h3>Error Loading Module</h3>
                    <p>${error.message}</p>
                    <button class="btn btn-primary" onclick="loadModule('${moduleName}')">Retry</button>
                </div>
            `;
            console.error('Error loading module:', error);
        });
}

function initializeModule(moduleName, container) {
    // Initialize module-specific functionality after DOM is ready
    const camelCaseModuleName = moduleName.replace(/-(\w)/g, (match, letter) => letter.toUpperCase());
    const initFunctionName = `init${capitalize(camelCaseModuleName)}Module`;

    if (window[initFunctionName]) {
        // Use requestAnimationFrame to ensure DOM elements are available
        requestAnimationFrame(() => {
            try {
                console.log(`Calling ${initFunctionName} for module ${moduleName}`);
                window[initFunctionName]();
            } catch (error) {
                console.error(`Error initializing ${moduleName} module:`, error);
                container.innerHTML += `
                    <div class="error-container">
                        <h3>Module Initialization Error</h3>
                        <p>${error.message}</p>
                        <button class="btn btn-primary" onclick="loadModule('${moduleName}')">Retry</button>
                    </div>
                `;
            }
        });
    } else {
        console.warn(`Initialization function ${initFunctionName} not found for module ${moduleName}. The module might run its own setup code.`);
    }
}

// Function to check authentication status
function checkAuthentication() {
    // Check if user is authenticated
    const token = localStorage.getItem('adminToken');
    const role = localStorage.getItem('adminRole');
    
    if (!token) {
        // Redirect to login page if no token is found
        window.location.href = 'login.html';
        return;
    }
    
    // Check if user is an admin or super admin
    if (!['admin', 'super_admin'].includes(role)) {
        // Clear token and redirect to login if not admin/super_admin
        localStorage.removeItem('adminToken');
        localStorage.removeItem('adminRole');
        localStorage.removeItem('adminName');
        window.location.href = 'login.html';
        return;
    }
    
    // Display stored user name immediately
    updateUserInfo();
    
    // Verify token with API
    fetch(`${API_CONFIG.baseUrl}${API_CONFIG.apiPrefix}/me`, {
        headers: {
            'Authorization': `Bearer ${token}`
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Invalid or expired token');
        }
        return response.json();
    })
    .then(data => {
        // Check for user in the response (API returns {user: ...})
        const user = data.user || data;
        
        // Double-check role from API response if available
        if (user && !['admin', 'super_admin'].includes(user.role)) {
            throw new Error('Access denied. Admin privileges required.');
        }
        
        // Update user info in the header if available
        updateUserInfo(user);
    })
    .catch(error => {
        console.error('Authentication error:', error);
        // Clear token and redirect to login
        localStorage.removeItem('adminToken');
        localStorage.removeItem('adminRole');
        localStorage.removeItem('adminName');
        window.location.href = 'login.html';
    });
}

// Function to update user info in the header
function updateUserInfo(user) {
    const userNameElement = document.getElementById('adminName');
    if (userNameElement) {
        // First try to use the user data from API response
        if (user && (user.name || user.email)) {
            userNameElement.textContent = user.name || user.email;
        } else {
            // Fall back to localStorage if API doesn't provide user info
            const storedName = localStorage.getItem('adminName');
            if (storedName) {
                userNameElement.textContent = storedName;
            } else {
                userNameElement.textContent = 'Admin';
            }
        }
    }

    // Fetch user permissions from server (secure approach)
    fetchUserPermissions();
}

// Function to fetch user permissions from server
async function fetchUserPermissions() {
    try {
        const response = await apiCall('/user/permissions');
        
        if (response.success) {
            const { permissions, user } = response;
            
            // Update UI based on server-provided permissions
            updateUIBasedOnPermissions(permissions.accessible_modules, permissions.capabilities);
            
            // Store user info for display purposes only (not for security decisions)
            if (user) {
                const userNameElement = document.getElementById('adminName');
                if (userNameElement && !userNameElement.textContent.includes('@')) {
                    // Only update if we don't already have a name
                    userNameElement.textContent = user.is_super_admin ? 'Super Admin' : 'Admin';
                }
            }
            
            console.log('UI permissions updated from server');
        }
    } catch (error) {
        console.error('Failed to fetch user permissions:', error);
        
        // If permissions fetch fails, hide all sensitive modules for security
        const allPossibleModules = ['users', 'approvals', 'expertise', 'faculty', 'subjects', 'schedules', 'posts', 'notes'];
        updateUIBasedOnPermissions([], {});
        
        showNotification('Failed to load permissions. Please refresh the page.', 'error');
    }
}

// Function to update UI based on server-provided permissions
function updateUIBasedOnPermissions(accessibleModules, capabilities) {
    // All possible admin modules
    const allModules = ['users', 'approvals', 'expertise', 'faculty', 'subjects', 'schedules', 'posts', 'notes', 'announcements', 'change-password', 'class-status'];

    allModules.forEach(module => {
        const isAccessible = accessibleModules.includes(module);
        
        // Handle sidebar menu items
        const menuItem = document.querySelector(`.nav-link[data-module="${module}"]`);
        if (menuItem && menuItem.parentElement) {
            if (isAccessible) {
                menuItem.parentElement.style.display = 'block';
                menuItem.parentElement.classList.remove('hidden');
            } else {
                menuItem.parentElement.style.display = 'none';
                menuItem.parentElement.classList.add('hidden');
            }
        }
        
        // Handle dashboard overview cards
        const card = document.querySelector(`.dashboard-card[data-module="${module}"]`);
        if (card) {
            if (isAccessible) {
                card.style.display = 'flex'; // Cards use flex display
                card.classList.remove('hidden');
            } else {
                card.style.display = 'none';
                card.classList.add('hidden');
            }
        }
    });
    
    // Store permissions for use by other modules (optional)
    window.userCapabilities = capabilities;
    
    console.log('UI visibility updated based on server permissions', { accessibleModules, capabilities });
}

// Function to handle logout
function handleLogout() {
    // Clear all authentication data
    localStorage.removeItem('adminToken');
    localStorage.removeItem('adminRole');
    localStorage.removeItem('adminName');
    
    // Redirect to login page
    window.location.href = 'login.html';
}

// Utility function to show notification
function showNotification(message, type = 'info') {
    const notification = document.getElementById('notification');
    
    // Set message and type
    notification.textContent = message;
    notification.className = `notification ${type}`;
    
    // Show notification
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Hide notification after 3 seconds
    setTimeout(() => {
        notification.classList.remove('show');
    }, 3000);
}

// Utility function to capitalize first letter
function capitalize(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

// Initialize sidebar toggle functionality
function initializeSidebarToggle() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.dashboard-sidebar');
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);

    // Toggle sidebar when button is clicked
    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('active');
        document.body.classList.toggle('sidebar-open');
    });

    // Close sidebar when clicking overlay
    overlay.addEventListener('click', () => {
        sidebar.classList.remove('active');
        document.body.classList.remove('sidebar-open');
    });

    // Close sidebar when clicking a link (mobile only)
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('active');
                document.body.classList.remove('sidebar-open');
            }
        });
    });
}

// Initialize change password module
function initChangePasswordModule() {
    // The initialization is handled in change-password.js
    console.log('Change password module initialized');
}

// Make apiCall globally available
window.apiCall = apiCall;

// Utility function for API calls with authentication
async function apiCall(endpoint, method = 'GET', data = null) {
    if (!endpoint) {
        throw new Error('Endpoint is required');
    }

    const token = localStorage.getItem('adminToken');
    
    // Construct the full URL with safety checks
    let fullUrl;
    try {
        if (endpoint.startsWith('http')) {
            fullUrl = endpoint;
        } else {
            const base = API_CONFIG.baseUrl || '';
            const prefix = endpoint.startsWith('/api') ? '' : API_CONFIG.apiPrefix;
            const path = endpoint.startsWith('/') ? endpoint : '/' + endpoint;
            fullUrl = `${base}${prefix}${path}`;
        }
    } catch (error) {
        console.error('Error constructing URL:', error);
        throw new Error(`Invalid endpoint: ${endpoint}`);
    }

    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
        }
    };
    
    if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH' || method === 'DELETE')) {
        options.body = JSON.stringify(data);
    }
    
    try {
        console.log('Making API call to:', fullUrl);
        const response = await fetch(fullUrl, options);
        
        if (!response.ok) {
            // If unauthorized, redirect to login
            if (response.status === 401) {
                localStorage.removeItem('adminToken');
                window.location.href = 'login.html';
                throw new Error('Unauthorized access');
            }
            
            // Handle other errors
            const errorData = await response.json();
            throw new Error(errorData.message || 'API request failed');
        }
        
        return await response.json();
    } catch (error) {
        console.error('API call error:', error);
        showNotification(error.message, 'error');
        throw error;
    }
}