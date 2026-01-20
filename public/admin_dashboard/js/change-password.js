document.addEventListener('DOMContentLoaded', function() {
    const changePasswordForm = document.getElementById('changePasswordForm');
    const errorMessage = document.getElementById('errorMessage');
    const successMessage = document.getElementById('successMessage');

    changePasswordForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Reset messages
        errorMessage.style.display = 'none';
        successMessage.style.display = 'none';

        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;

        // Client-side validation
        if (newPassword !== confirmPassword) {
            errorMessage.textContent = 'New password and confirm password do not match';
            errorMessage.style.display = 'block';
            return;
        }

        if (newPassword.length < 6) {
            errorMessage.textContent = 'New password must be at least 6 characters long';
            errorMessage.style.display = 'block';
            return;
        }

        try {
            const response = await fetch('/api.php/auth/change-password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('token')}`
                },
                body: JSON.stringify({
                    current_password: currentPassword,
                    new_password: newPassword
                })
            });

            const data = await response.json();

            if (response.ok) {
                successMessage.textContent = 'Password updated successfully';
                successMessage.style.display = 'block';
                changePasswordForm.reset();
            } else {
                errorMessage.textContent = data.message || 'Failed to update password';
                errorMessage.style.display = 'block';
            }
        } catch (error) {
            errorMessage.textContent = 'An error occurred while updating the password';
            errorMessage.style.display = 'block';
            console.error('Error:', error);
        }
    });
});