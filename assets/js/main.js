/**
 * KaamConnect - Core Client-Side JavaScript
 * Pure vanilla JavaScript with Bootstrap 5 integration
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });

    // 2. Client-side password confirmation validation
    const registerForm = document.querySelector('form#form-register');
    if (registerForm) {
        const pass = registerForm.querySelector('input[name="password"]');
        const confirmPass = registerForm.querySelector('input[name="confirm_password"]');
        
        registerForm.addEventListener('submit', (e) => {
            if (pass && confirmPass && pass.value !== confirmPass.value) {
                e.preventDefault();
                alert('Passwords do not match! Please check and try again.');
                confirmPass.focus();
            }
        });
    }

    // 3. Image file preview helper
    const imageInputs = document.querySelectorAll('input[type="file"][data-preview-target]');
    imageInputs.forEach(input => {
        input.addEventListener('change', (e) => {
            const targetSelector = input.getAttribute('data-preview-target');
            const previewEl = document.querySelector(targetSelector);
            if (previewEl && input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = (re) => {
                    previewEl.src = re.target.result;
                    previewEl.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        });
    });

    // 4. Role selector card interaction on register page
    const roleCards = document.querySelectorAll('.role-select-card');
    roleCards.forEach(card => {
        card.addEventListener('click', () => {
            roleCards.forEach(c => c.classList.remove('border-primary', 'bg-light'));
            card.classList.add('border-primary', 'bg-light');
            const radio = card.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
        });
    });
});

/**
 * Global AJAX JSON helper
 */
async function fetchJSON(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });
        return await response.json();
    } catch (err) {
        console.error('AJAX request failed:', err);
        return { success: false, error: 'Network communication failure.' };
    }
}
