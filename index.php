<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chloe Belle - AI Influencer | Exclusive Content</title>
    <meta name="description" content="Join Chloe Belle's exclusive community for premium AI-generated content, photos, and videos. Subscribe for unlimited access.">
    <meta name="keywords" content="Chloe Belle, AI influencer, exclusive content, premium photos, subscription">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="Chloe Belle - AI Influencer">
    <meta property="og:description" content="Exclusive AI-generated content and premium experiences">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom Styles -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="fas fa-star text-warning me-2"></i>Chloe Belle
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#gallery">Gallery</a>
                    </li>
                    <li class="nav-item">
                        <select class="form-select form-select-sm bg-dark text-white border-secondary" id="languageSelect">
                            <option value="en">English</option>
                            <option value="es">Español</option>
                            <option value="fr">Français</option>
                            <option value="de">Deutsch</option>
                        </select>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="pt-5">
        <!-- Hero Section -->
        <section id="home" class="hero-section py-5">
            <div class="container">
                <div class="row align-items-center min-vh-100">
                    <!-- Left side - Chloe's Picture -->
                    <div class="col-lg-6 mb-4 mb-lg-0">
                        <div class="profile-container position-relative">
                            <div class="profile-image-wrapper">
                                <img src="uploads/chloe/profile.jpg" alt="Chloe Belle" class="img-fluid rounded-4 shadow-lg profile-image" id="heroImage">
                                <div class="profile-overlay">
                                    <div class="overlay-content text-center">
                                        <h2 class="text-white mb-3">Welcome to My World</h2>
                                        <p class="text-white-50">Exclusive AI-generated content awaits</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right side - Login/Signup Forms -->
                    <div class="col-lg-6">
                        <div class="auth-container">
                            <!-- Login Form -->
                            <div class="card shadow-lg border-0 mb-4" id="loginCard">
                                <div class="card-header bg-gradient-primary text-white text-center py-3">
                                    <h4 class="mb-0"><i class="fas fa-sign-in-alt me-2"></i>Welcome Back</h4>
                                </div>
                                <div class="card-body p-4">
                                    <form id="loginForm" method="POST" action="auth/login.php">
                                        <div class="mb-3">
                                            <label for="loginEmail" class="form-label">Email Address</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                                <input type="email" class="form-control" id="loginEmail" name="email" required>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="loginPassword" class="form-label">Password</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                                <input type="password" class="form-control" id="loginPassword" name="password" required>
                                                <button class="btn btn-outline-secondary" type="button" id="toggleLoginPassword">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="rememberMe" name="remember">
                                                <label class="form-check-label" for="rememberMe">Remember me</label>
                                            </div>
                                            <a href="#" class="text-decoration-none" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">Forgot password?</a>
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100 py-2">
                                            <i class="fas fa-sign-in-alt me-2"></i>Login
                                        </button>
                                    </form>
                                    <div class="text-center mt-3">
                                        <p class="mb-0">Don't have an account? 
                                            <a href="#" class="text-decoration-none" id="showSignup">Sign up here</a>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Signup Form -->
                            <div class="card shadow-lg border-0" id="signupCard" style="display: none;">
                                <div class="card-header bg-gradient-success text-white text-center py-3">
                                    <h4 class="mb-0"><i class="fas fa-user-plus me-2"></i>Join Chloe Belle</h4>
                                </div>
                                <div class="card-body p-4">
                                    <form id="signupForm" method="POST" action="auth/register.php">
                                        <div class="mb-3">
                                            <label for="signupUsername" class="form-label">Username</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                <input type="text" class="form-control" id="signupUsername" name="username" required>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="signupEmail" class="form-label">Email Address</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                                <input type="email" class="form-control" id="signupEmail" name="email" required>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="signupPassword" class="form-label">Password</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                                <input type="password" class="form-control" id="signupPassword" name="password" required>
                                                <button class="btn btn-outline-secondary" type="button" id="toggleSignupPassword">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                            <!-- Password Strength Indicator -->
                                            <div class="password-strength mt-2">
                                                <div class="progress" style="height: 5px;">
                                                    <div class="progress-bar" id="passwordStrengthBar" role="progressbar" style="width: 0%"></div>
                                                </div>
                                                <small class="text-muted" id="passwordStrengthText">Enter a password</small>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="confirmPassword" class="form-label">Confirm Password</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                                <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="agreeTerms" name="agree_terms" required>
                                                <label class="form-check-label" for="agreeTerms">
                                                    I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms of Service</a> and 
                                                    <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
                                                </label>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-success w-100 py-2">
                                            <i class="fas fa-user-plus me-2"></i>Create Account
                                        </button>
                                    </form>
                                    <div class="text-center mt-3">
                                        <p class="mb-0">Already have an account? 
                                            <a href="#" class="text-decoration-none" id="showLogin">Login here</a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Featured Gallery Section -->
        <section id="gallery" class="py-5 bg-light">
            <div class="container">
                <div class="text-center mb-5">
                    <h2 class="display-5 fw-bold text-dark">Featured Content</h2>
                    <p class="lead text-muted">Discover exclusive AI-generated content</p>
                </div>
                
                <div class="row g-4" id="featuredGallery">
                    <!-- Featured images will be loaded dynamically from admin panel -->
                    <?php
                    // This will be replaced with PHP code to load featured images
                    for ($i = 1; $i <= 6; $i++) {
                        echo '
                        <div class="col-lg-4 col-md-6">
                            <div class="gallery-item position-relative overflow-hidden rounded-4 shadow">
                                <img src="uploads/featured/sample' . $i . '.jpg" alt="Featured Content ' . $i . '" class="img-fluid gallery-image">
                                <div class="gallery-overlay">
                                    <div class="overlay-content text-center">
                                        <h5 class="text-white mb-2">Premium Content</h5>
                                        <p class="text-white-50 mb-3">Subscribe to unlock</p>
                                        <button class="btn btn-primary btn-sm">
                                            <i class="fas fa-unlock me-2"></i>View More
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>';
                    }
                    ?>
                </div>
            </div>
        </section>

        <!-- About Section -->
        <section id="about" class="py-5">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-6">
                        <h2 class="display-5 fw-bold mb-4">About Chloe Belle</h2>
                        <p class="lead mb-4">Welcome to my exclusive digital world! I'm Chloe Belle, your AI companion creating unique and personalized content just for you.</p>
                        <p class="mb-4">Join my community to access premium photos, videos, and interactive experiences. Connect with me through posts, comments, and exclusive subscriber content.</p>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="stat-item text-center p-3 bg-light rounded">
                                    <h4 class="fw-bold text-primary mb-1">1000+</h4>
                                    <small class="text-muted">Exclusive Photos</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-item text-center p-3 bg-light rounded">
                                    <h4 class="fw-bold text-primary mb-1">500+</h4>
                                    <small class="text-muted">Premium Videos</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <img src="uploads/chloe/about.jpg" alt="About Chloe Belle" class="img-fluid rounded-4 shadow-lg">
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-star text-warning me-2"></i>Chloe Belle</h5>
                    <p class="mb-0">Exclusive AI-generated content and experiences.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="social-links">
                        <a href="#" class="text-white me-3"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-youtube"></i></a>
                    </div>
                    <small class="text-muted">© 2025 Chloe Belle. All rights reserved.</small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Modals -->
    <!-- Forgot Password Modal -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="forgotPasswordForm">
                        <div class="mb-3">
                            <label for="resetEmail" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="resetEmail" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Send Reset Link</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms of Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Terms of service content will be loaded here...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Privacy Modal -->
    <div class="modal fade" id="privacyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Privacy Policy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Privacy policy content will be loaded here...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JavaScript -->
    <script src="assets/js/script.js"></script>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
</body>
</html>