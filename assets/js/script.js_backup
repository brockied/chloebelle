// Chloe Belle Website - Main JavaScript File
document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize all components
    initializeAuth();
    initializePasswordStrength();
    initializeLanguageSelector();
    initializeAnimations();
    initializeFormValidation();
    initializeGallery();
    initializeTooltips();
    
    // Authentication Form Toggle
    function initializeAuth() {
        const showSignupBtn = document.getElementById('showSignup');
        const showLoginBtn = document.getElementById('showLogin');
        const loginCard = document.getElementById('loginCard');
        const signupCard = document.getElementById('signupCard');
        
        if (showSignupBtn && showLoginBtn && loginCard && signupCard) {
            showSignupBtn.addEventListener('click', function(e) {
                e.preventDefault();
                loginCard.style.display = 'none';
                signupCard.style.display = 'block';
                signupCard.classList.add('animate-slideInRight');
            });
            
            showLoginBtn.addEventListener('click', function(e) {
                e.preventDefault();
                signupCard.style.display = 'none';
                loginCard.style.display = 'block';
                loginCard.classList.add('animate-slideInLeft');
            });
        }
    }
    
    // Password Strength Indicator
    function initializePasswordStrength() {
        const passwordInput = document.getElementById('signupPassword');
        const strengthBar = document.getElementById('passwordStrengthBar');
        const strengthText = document.getElementById('passwordStrengthText');
        
        if (passwordInput && strengthBar && strengthText) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const strength = checkPasswordStrength(password);
                updatePasswordStrengthUI(strength, strengthBar, strengthText);
            });
        }
        
        // Password visibility toggles
        const toggleLoginPassword = document.getElementById('toggleLoginPassword');
        const toggleSignupPassword = document.getElementById('toggleSignupPassword');
        
        if (toggleLoginPassword) {
            toggleLoginPassword.addEventListener('click', function() {
                togglePasswordVisibility('loginPassword', this);
            });
        }
        
        if (toggleSignupPassword) {
            toggleSignupPassword.addEventListener('click', function() {
                togglePasswordVisibility('signupPassword', this);
            });
        }
    }
    
    // Check password strength
    function checkPasswordStrength(password) {
        let score = 0;
        let feedback = [];
        
        // Length check
        if (password.length >= 8) score += 1;
        else feedback.push('at least 8 characters');
        
        // Lowercase check
        if (/[a-z]/.test(password)) score += 1;
        else feedback.push('lowercase letters');
        
        // Uppercase check
        if (/[A-Z]/.test(password)) score += 1;
        else feedback.push('uppercase letters');
        
        // Number check
        if (/\d/.test(password)) score += 1;
        else feedback.push('numbers');
        
        // Special character check
        if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) score += 1;
        else feedback.push('special characters');
        
        return {
            score: score,
            feedback: feedback,
            strength: getStrengthLevel(score)
        };
    }
    
    function getStrengthLevel(score) {
        if (score <= 1) return 'very-weak';
        if (score <= 2) return 'weak';
        if (score <= 3) return 'medium';
        if (score <= 4) return 'strong';
        return 'very-strong';
    }
    
    function updatePasswordStrengthUI(strength, bar, text) {
        const percentage = (strength.score / 5) * 100;
        bar.style.width = percentage + '%';
        
        // Remove all strength classes
        bar.classList.remove('strength-very-weak', 'strength-weak', 'strength-medium', 'strength-strong', 'strength-very-strong');
        
        // Add current strength class
        bar.classList.add('strength-' + strength.strength);
        
        // Update text
        const strengthTexts = {
            'very-weak': 'Very Weak',
            'weak': 'Weak',
            'medium': 'Medium',
            'strong': 'Strong',
            'very-strong': 'Very Strong'
        };
        
        if (strength.score === 0) {
            text.textContent = 'Enter a password';
            text.className = 'text-muted';
        } else {
            text.textContent = strengthTexts[strength.strength];
            text.className = 'text-' + (strength.score <= 2 ? 'danger' : strength.score <= 3 ? 'warning' : 'success');
            
            if (strength.feedback.length > 0) {
                text.textContent += ' - Need: ' + strength.feedback.join(', ');
            }
        }
    }
    
    function togglePasswordVisibility(inputId, button) {
        const input = document.getElementById(inputId);
        const icon = button.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
    
    // Language Selector
    function initializeLanguageSelector() {
        const languageSelect = document.getElementById('languageSelect');
        
        if (languageSelect) {
            // Set default language based on browser
            const browserLang = navigator.language.split('-')[0];
            const supportedLangs = ['en', 'es', 'fr', 'de'];
            
            if (supportedLangs.includes(browserLang)) {
                languageSelect.value = browserLang;
            }
            
            languageSelect.addEventListener('change', function() {
                const selectedLang = this.value;
                changeLanguage(selectedLang);
                
                // Store preference
                localStorage.setItem('preferredLanguage', selectedLang);
            });
            
            // Load saved preference
            const savedLang = localStorage.getItem('preferredLanguage');
            if (savedLang && supportedLangs.includes(savedLang)) {
                languageSelect.value = savedLang;
                changeLanguage(savedLang);
            }
        }
    }
    
    function changeLanguage(lang) {
        // This would typically load language files or make API calls
        // For now, we'll just show a notification
        showToast('Language changed to ' + getLanguageName(lang), 'info');
        
        // In a real implementation, you would:
        // 1. Load translation files
        // 2. Update all text content
        // 3. Potentially reload the page with new language parameter
    }
    
    function getLanguageName(code) {
        const languages = {
            'en': 'English',
            'es': 'Español',
            'fr': 'Français',
            'de': 'Deutsch'
        };
        return languages[code] || 'English';
    }
    
    // Animations
    function initializeAnimations() {
        // Intersection Observer for scroll animations
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fadeInUp');
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });
        
        // Observe elements for animation
        const animateElements = document.querySelectorAll('.gallery-item, .stat-item, .card');
        animateElements.forEach(el => observer.observe(el));
        
        // Parallax effect for hero image
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const heroImage = document.getElementById('heroImage');
            
            if (heroImage) {
                const speed = scrolled * 0.5;
                heroImage.style.transform = `translateY(${speed}px)`;
            }
        });
    }
    
    // Form Validation
    function initializeFormValidation() {
        const forms = document.querySelectorAll('form');
        
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!validateForm(this)) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                
                this.classList.add('was-validated');
            });
            
            // Real-time validation
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    validateField(this);
                });
            });
        });
        
        // Password confirmation validation
        const confirmPassword = document.getElementById('confirmPassword');
        const signupPassword = document.getElementById('signupPassword');
        
        if (confirmPassword && signupPassword) {
            confirmPassword.addEventListener('input', function() {
                if (this.value !== signupPassword.value) {
                    this.setCustomValidity('Passwords do not match');
                    this.classList.add('is-invalid');
                } else {
                    this.setCustomValidity('');
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });
        }
    }
    
    function validateForm(form) {
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!validateField(input)) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    function validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        
        // Required field check
        if (field.hasAttribute('required') && !value) {
            isValid = false;
            showFieldError(field, 'This field is required');
        }
        
        // Email validation
        if (field.type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                showFieldError(field, 'Please enter a valid email address');
            }
        }
        
        // Password validation
        if (field.id === 'signupPassword' && value) {
            const strength = checkPasswordStrength(value);
            if (strength.score < 3) {
                isValid = false;
                showFieldError(field, 'Password is too weak');
            }
        }
        
        // Username validation
        if (field.id === 'signupUsername' && value) {
            const usernameRegex = /^[a-zA-Z0-9_]{3,20}$/;
            if (!usernameRegex.test(value)) {
                isValid = false;
                showFieldError(field, 'Username must be 3-20 characters (letters, numbers, underscore only)');
            }
        }
        
        if (isValid) {
            showFieldSuccess(field);
        }
        
        return isValid;
    }
    
    function showFieldError(field, message) {
        field.classList.remove('is-valid');
        field.classList.add('is-invalid');
        
        // Remove existing feedback
        const existingFeedback = field.parentNode.querySelector('.invalid-feedback');
        if (existingFeedback) {
            existingFeedback.remove();
        }
        
        // Add error message
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.textContent = message;
        field.parentNode.appendChild(feedback);
    }
    
    function showFieldSuccess(field) {
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
        
        // Remove error message
        const existingFeedback = field.parentNode.querySelector('.invalid-feedback');
        if (existingFeedback) {
            existingFeedback.remove();
        }
    }
    
    // Gallery Functions
    function initializeGallery() {
        const galleryItems = document.querySelectorAll('.gallery-item');
        
        galleryItems.forEach(item => {
            item.addEventListener('click', function() {
                // Show subscription modal for non-subscribers
                showSubscriptionModal();
            });
        });
    }
    
    function showSubscriptionModal() {
        // This would show the subscription modal
        showToast('Please subscribe to view premium content', 'info');
    }
    
    // Initialize tooltips
    function initializeTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // AJAX Functions
    function makeAjaxRequest(url, data, method = 'POST') {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open(method, url, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            resolve(response);
                        } catch (e) {
                            reject(e);
                        }
                    } else {
                        reject(new Error('Request failed with status ' + xhr.status));
                    }
                }
            };
            
            xhr.send(JSON.stringify(data));
        });
    }
    
    // Form Submission Handlers
    const loginForm = document.getElementById('loginForm');
    const signupForm = document.getElementById('signupForm');
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
    
    if (signupForm) {
        signupForm.addEventListener('submit', handleSignup);
    }
    
    if (forgotPasswordForm) {
        forgotPasswordForm.addEventListener('submit', handleForgotPassword);
    }
    
    async function handleLogin(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const data = {
            email: formData.get('email'),
            password: formData.get('password'),
            remember: formData.get('remember') ? true : false
        };
        
        showLoading(true);
        
        try {
            const response = await makeAjaxRequest('auth/login.php', data);
            
            if (response.success) {
                showToast('Login successful! Redirecting...', 'success');
                setTimeout(() => {
                    window.location.href = response.redirect || 'feed.php';
                }, 1500);
            } else {
                showToast(response.message || 'Login failed', 'error');
            }
        } catch (error) {
            showToast('An error occurred. Please try again.', 'error');
            console.error('Login error:', error);
        } finally {
            showLoading(false);
        }
    }
    
    async function handleSignup(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const data = {
            username: formData.get('username'),
            email: formData.get('email'),
            password: formData.get('password'),
            confirm_password: formData.get('confirm_password'),
            agree_terms: formData.get('agree_terms') ? true : false
        };
        
        // Validate passwords match
        if (data.password !== data.confirm_password) {
            showToast('Passwords do not match', 'error');
            return;
        }
        
        // Validate terms agreement
        if (!data.agree_terms) {
            showToast('Please agree to the terms and conditions', 'error');
            return;
        }
        
        showLoading(true);
        
        try {
            const response = await makeAjaxRequest('auth/register.php', data);
            
            if (response.success) {
                showToast('Registration successful! Please check your email to verify your account.', 'success');
                e.target.reset();
                // Show login form
                document.getElementById('signupCard').style.display = 'none';
                document.getElementById('loginCard').style.display = 'block';
            } else {
                showToast(response.message || 'Registration failed', 'error');
            }
        } catch (error) {
            showToast('An error occurred. Please try again.', 'error');
            console.error('Signup error:', error);
        } finally {
            showLoading(false);
        }
    }
    
    async function handleForgotPassword(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const data = {
            email: formData.get('email') || document.getElementById('resetEmail').value
        };
        
        showLoading(true);
        
        try {
            const response = await makeAjaxRequest('auth/forgot-password.php', data);
            
            if (response.success) {
                showToast('Password reset link sent to your email', 'success');
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('forgotPasswordModal'));
                modal.hide();
                e.target.reset();
            } else {
                showToast(response.message || 'Failed to send reset link', 'error');
            }
        } catch (error) {
            showToast('An error occurred. Please try again.', 'error');
            console.error('Forgot password error:', error);
        } finally {
            showLoading(false);
        }
    }
    
    // Utility Functions
    function showLoading(show) {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = show ? 'flex' : 'none';
        }
    }
    
    function showToast(message, type = 'info', duration = 5000) {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast toast-${type} show position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.setAttribute('role', 'alert');
        
        toast.innerHTML = `
            <div class="toast-header">
                <i class="fas fa-${getToastIcon(type)} me-2"></i>
                <strong class="me-auto">${getToastTitle(type)}</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">${message}</div>
        `;
        
        document.body.appendChild(toast);
        
        // Auto remove after duration
        setTimeout(() => {
            toast.remove();
        }, duration);
        
        // Manual close button
        toast.querySelector('.btn-close').addEventListener('click', () => {
            toast.remove();
        });
    }
    
    function getToastIcon(type) {
        const icons = {
            'success': 'check-circle',
            'error': 'exclamation-circle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };
        return icons[type] || 'info-circle';
    }
    
    function getToastTitle(type) {
        const titles = {
            'success': 'Success',
            'error': 'Error',
            'warning': 'Warning',
            'info': 'Info'
        };
        return titles[type] || 'Info';
    }
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Security: Prevent right-click on images (basic protection)
    document.querySelectorAll('img').forEach(img => {
        img.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });
        
        img.addEventListener('dragstart', function(e) {
            e.preventDefault();
            return false;
        });
    });
    
    // Performance: Lazy loading for images
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
});

// Global error handler
window.addEventListener('error', function(e) {
    console.error('Global error:', e.error);
    // In production, you might want to send this to a logging service
});

// Service Worker registration (for PWA capabilities)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js')
            .then(function(registration) {
                console.log('ServiceWorker registration successful');
            })
            .catch(function(err) {
                console.log('ServiceWorker registration failed: ', err);
            });
    });
}
// Dropdown Z-Index Fix JavaScript
// Add this to any page with dropdown issues

document.addEventListener('DOMContentLoaded', function() {
    // Fix dropdown positioning and z-index issues
    function fixDropdowns() {
        const dropdowns = document.querySelectorAll('.dropdown');
        
        dropdowns.forEach(dropdown => {
            const toggle = dropdown.querySelector('.dropdown-toggle');
            const menu = dropdown.querySelector('.dropdown-menu');
            
            if (toggle && menu) {
                // Ensure proper z-index
                dropdown.style.position = 'relative';
                dropdown.style.zIndex = '10';
                menu.style.zIndex = '1055';
                menu.style.position = 'absolute';
                
                // Fix positioning for dropdowns in cards
                const card = dropdown.closest('.card, .post-card');
                if (card) {
                    card.style.overflow = 'visible';
                    menu.style.zIndex = '1060';
                }
                
                // Fix positioning for dropdowns in tables
                const table = dropdown.closest('.table');
                if (table) {
                    const tableResponsive = table.closest('.table-responsive');
                    if (tableResponsive) {
                        tableResponsive.style.overflow = 'visible';
                    }
                }
            }
        });
    }
    
    // Fix dropdowns on page load
    fixDropdowns();
    
    // Re-fix dropdowns when new content is added (for dynamic content)
    const observer = new MutationObserver(function(mutations) {
        let shouldFix = false;
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length > 0) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1 && 
                        (node.classList.contains('dropdown') || 
                         node.querySelector('.dropdown'))) {
                        shouldFix = true;
                    }
                });
            }
        });
        
        if (shouldFix) {
            setTimeout(fixDropdowns, 10);
        }
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Handle dropdown show events
    document.addEventListener('show.bs.dropdown', function(event) {
        const dropdown = event.target.closest('.dropdown');
        const menu = dropdown.querySelector('.dropdown-menu');
        
        if (menu) {
            // Ensure the dropdown menu is visible
            menu.style.zIndex = '1055';
            menu.style.position = 'absolute';
            
            // Special handling for dropdowns in specific containers
            const card = dropdown.closest('.card, .post-card');
            if (card) {
                menu.style.zIndex = '1060';
            }
            
            const table = dropdown.closest('.table');
            if (table) {
                menu.style.zIndex = '1055';
            }
            
            // Position the dropdown menu properly
            const rect = event.target.getBoundingClientRect();
            const menuRect = menu.getBoundingClientRect();
            const viewportHeight = window.innerHeight;
            
            // If dropdown would go off screen, show it above the button
            if (rect.bottom + menuRect.height > viewportHeight) {
                menu.classList.add('dropdown-menu-up');
            } else {
                menu.classList.remove('dropdown-menu-up');
            }
        }
    });
    
    console.log('✅ Dropdown z-index fixes applied');
});

// Additional CSS for dropdown positioning
const style = document.createElement('style');
style.textContent = `
    .dropdown-menu-up {
        transform: translateY(-100%) translateY(-0.5rem) !important;
        top: auto !important;
        bottom: 100% !important;
    }
    
    .dropdown-menu {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }
`;
document.head.appendChild(style);