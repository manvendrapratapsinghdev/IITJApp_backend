// login.js - JavaScript functionality for super admin login

// Load API configuration from main.js
if (!window.API_CONFIG) {
    // Load main.js first if it hasn't been loaded
    const mainScript = document.createElement('script');
    mainScript.src = 'js/main.js';
    document.head.appendChild(mainScript);
}

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const loginButton = document.getElementById('loginButton');
    const errorMessage = document.getElementById('errorMessage');
    
    // Check if user is already logged in and verified as super admin
    if (localStorage.getItem('adminToken') && localStorage.getItem('adminRole') === 'super_admin') {
        window.location.href = 'index.html';
    } else {
        // Clear any existing tokens if they're not super admin
        localStorage.removeItem('adminToken');
        localStorage.removeItem('adminRole');
        localStorage.removeItem('adminName');
    }
    
    // Handle form submission
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        
        // Validate inputs
        if (!email || !password) {
            showError('Please enter both email and password');
            return;
        }
        
        // Reset error message
        hideError();
        
        // Show loading state
        setLoading(true);
        
        // Perform login API call
        authenticateSuperAdmin(email, password);
    });
    
    // Function to authenticate super admin
    function authenticateSuperAdmin(email, password) {
        // Use the dedicated admin login endpoint
        fetch(`${window.API_CONFIG.baseUrl}${window.API_CONFIG.apiPrefix}/auth/admin-login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                email: email, 
                password: password 
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Login failed. Please check your credentials.');
            }
            return response.json();
        })
        .then(data => {
            // Check if login was successful and user is admin/super_admin
            if (data.success && data.user && ['admin', 'super_admin'].includes(data.user.role)) {
                // Store token and user info in local storage
                localStorage.setItem('adminToken', data.token);
                localStorage.setItem('adminRole', data.user.role);
                localStorage.setItem('adminName', data.user.name || data.user.email);
                
                // Redirect to dashboard
                window.location.href = 'index.html';
            } else {
                throw new Error('Access denied. Admin privileges required.');
            }
        })
        .catch(error => {
            // Show error message
            showError(error.message);
            
            // Reset button state
            setLoading(false);
        });
    }
    
    // Function to show error message
    function showError(message) {
        errorMessage.textContent = message;
        errorMessage.style.display = 'block';
    }
    
    // Function to hide error message
    function hideError() {
        errorMessage.style.display = 'none';
    }
    
    // Function to set loading state
    function setLoading(isLoading) {
        if (isLoading) {
            loginButton.disabled = true;
            loginButton.classList.add('loading');
            loginButton.textContent = 'Logging in...';
        } else {
            loginButton.disabled = false;
            loginButton.classList.remove('loading');
            loginButton.textContent = 'Login as Super Admin';
        }
    }
});