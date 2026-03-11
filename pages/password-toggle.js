// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    initializePasswordToggles();
});

// Initialize all password toggles
function initializePasswordToggles() {
    console.log('Initializing password toggles');
    
    // Get all password input wrappers
    const passwordWrappers = document.querySelectorAll('.password-input-wrapper');
    
    passwordWrappers.forEach(wrapper => {
        const passwordInput = wrapper.querySelector('.password-input');
        const toggleBtn = wrapper.querySelector('.toggle-password');
        const eyeOffIcon = wrapper.querySelector('.eye-off');
        const eyeIcon = wrapper.querySelector('.eye-on');
        
        if (passwordInput && toggleBtn) {
            // Initially hide the toggle button
            toggleBtn.style.display = 'none';
            
            // Show toggle button when input is focused
            passwordInput.addEventListener('focus', function() {
                toggleBtn.style.display = 'flex';
            });
            
            // Hide toggle button when input loses focus AND is empty
            passwordInput.addEventListener('blur', function() {
                if (passwordInput.value === '') {
                    toggleBtn.style.display = 'none';
                }
            });
            
            // Keep toggle button visible if user starts typing
            passwordInput.addEventListener('input', function() {
                if (passwordInput.value !== '') {
                    toggleBtn.style.display = 'flex';
                }
            });
        }
        
        // Reset to default state (eye-off visible, eye hidden)
        if (eyeOffIcon && eyeIcon) {
            eyeOffIcon.style.display = 'inline';
            eyeIcon.style.display = 'none';
        }
    });
    
    // Reset all input fields
    resetAllFields();
}

// Reset all form fields
function resetAllFields() {
    const inputs = document.querySelectorAll('input');
    inputs.forEach(input => {
        if (input.type !== 'submit' && input.type !== 'button') {
            input.value = '';
        }
    });
    
    // Reset password fields to password type
    const passwordInputs = document.querySelectorAll('input[type="text"].password-input');
    passwordInputs.forEach(input => {
        input.type = 'password';
    });
}

// Toggle Password function
function togglePassword(button) {
    console.log('Toggle password clicked');
    
    // Find the wrapper containing this button
    const wrapper = button.closest('.password-input-wrapper');
    if (!wrapper) return;
    
    const passwordInput = wrapper.querySelector('.password-input');
    const eyeOffIcon = wrapper.querySelector('.eye-off');
    const eyeIcon = wrapper.querySelector('.eye-on');
    
    if (!passwordInput || !eyeOffIcon || !eyeIcon) {
        console.error('Password toggle elements not found');
        return;
    }
    
    if (passwordInput.type === "password") {
        // Password hidden -> show it
        passwordInput.type = "text";
        eyeOffIcon.style.display = "none";  // Hide slashed eye
        eyeIcon.style.display = "inline";    // Show open eye
    } else {
        // Password visible -> hide it
        passwordInput.type = "password";
        eyeOffIcon.style.display = "inline"; // Show slashed eye
        eyeIcon.style.display = "none";      // Hide open eye
    }
    
    // Keep the toggle button visible after clicking
    button.style.display = 'flex';
}

// Function to manually reset password toggle state (can be called from parent)
function resetPasswordToggle(wrapperId) {
    const wrapper = document.getElementById(wrapperId);
    if (wrapper) {
        const passwordInput = wrapper.querySelector('.password-input');
        const toggleBtn = wrapper.querySelector('.toggle-password');
        const eyeOffIcon = wrapper.querySelector('.eye-off');
        const eyeIcon = wrapper.querySelector('.eye-on');
        
        if (passwordInput) {
            passwordInput.type = 'password';
            passwordInput.value = '';
        }
        
        if (toggleBtn) {
            toggleBtn.style.display = 'none';
        }
        
        if (eyeOffIcon && eyeIcon) {
            eyeOffIcon.style.display = 'inline';
            eyeIcon.style.display = 'none';
        }
    }
}

// Add this to ensure icons are visible when iframe loads
window.addEventListener('load', function() {
    // Small delay to ensure DOM is ready
    setTimeout(function() {
        const eyeOffIcons = document.querySelectorAll('.eye-off');
        const eyeIcons = document.querySelectorAll('.eye-on');
        
        eyeOffIcons.forEach(icon => {
            icon.style.display = 'inline';
        });
        
        eyeIcons.forEach(icon => {
            icon.style.display = 'none';
        });
    }, 100);
});