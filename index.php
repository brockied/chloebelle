

<?php
// Add this PHP code at the very top of your index.php file, before the DOCTYPE

// Get featured images from uploads directory
function getFeaturedImages() {
    $featuredImages = [];
    $featuredDir = 'uploads/featured';
    
    if (is_dir($featuredDir)) {
        $files = scandir($featuredDir);
        foreach ($files as $file) {
            if (preg_match('/^featured_[a-f0-9]+\.(jpg|jpeg|png|webp)$/i', $file)) {
                $featuredImages[] = [
                    'filename' => $file,
                    'url' => 'uploads/featured/' . $file,
                    'thumb_url' => 'uploads/featured/thumb_' . $file,
                    'square_url' => 'uploads/featured/square_' . $file,
                    'size' => file_exists($featuredDir . '/' . $file) ? filesize($featuredDir . '/' . $file) : 0,
                    'modified' => file_exists($featuredDir . '/' . $file) ? filemtime($featuredDir . '/' . $file) : 0
                ];
            }
        }
        // Sort by modification time (newest first)
        usort($featuredImages, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
    }
    
    return $featuredImages;
}

$featuredImages = getFeaturedImages();
?>
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
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --accent: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --dark: #1a1a2e;
            --light: #eee;
            --glass: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            overflow-x: hidden;
            background: var(--dark);
            color: white;
        }

        /* Animated Background */
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: var(--dark);
        }

        .animated-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(600px at 20% 30%, #667eea33 0%, transparent 50%),
                radial-gradient(800px at 80% 70%, #f093fb33 0%, transparent 50%),
                radial-gradient(400px at 40% 80%, #4facfe33 0%, transparent 50%);
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-20px) rotate(1deg); }
            66% { transform: translateY(10px) rotate(-1deg); }
        }

        /* Glass Navigation */
        .navbar-glass {
            background: rgba(26, 26, 46, 0.9);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border);
            transition: all 0.3s ease;
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.5rem;
            background: var(--primary);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 30px rgba(102, 126, 234, 0.5);
        }

        .nav-link {
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--accent);
            transition: width 0.3s ease;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            padding: 100px 0 50px;
        }

        .hero-content {
            z-index: 2;
        }

        .hero-title {
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            background: var(--primary);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 30px rgba(102, 126, 234, 0.5);
        }

        .hero-subtitle {
            font-size: 1.3rem;
            font-weight: 300;
            opacity: 0.9;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        /* Profile Image Container */
        .profile-container {
            position: relative;
            perspective: 1000px;
        }

        .profile-wrapper {
            position: relative;
            transform-style: preserve-3d;
            transition: transform 0.6s ease;
        }

        .profile-wrapper:hover {
            transform: rotateY(5deg) rotateX(5deg) scale(1.02);
        }

        .profile-image {
            width: 100%;
            max-width: 500px;
            height: 600px;
            object-fit: cover;
            border-radius: 20px;
            box-shadow: 
                0 30px 60px rgba(0, 0, 0, 0.4),
                0 0 100px rgba(102, 126, 234, 0.3);
            transition: all 0.6s ease;
        }

        .profile-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(102, 126, 234, 0.8), rgba(240, 147, 251, 0.8));
            border-radius: 20px;
            opacity: 0;
            transition: all 0.6s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .profile-wrapper:hover .profile-overlay {
            opacity: 1;
        }

        .profile-glow {
            position: absolute;
            top: -20px;
            left: -20px;
            right: -20px;
            bottom: -20px;
            background: var(--primary);
            border-radius: 30px;
            filter: blur(40px);
            opacity: 0.6;
            z-index: -1;
            animation: pulse 4s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(0.95); opacity: 0.6; }
            50% { transform: scale(1.05); opacity: 0.8; }
        }

        /* Glass Cards */
        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.6s ease;
        }

        .glass-card:hover::before {
            left: 100%;
        }

        .glass-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 255, 255, 0.3);
        }

        /* Form Styling */
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 15px 20px;
            color: white;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #667eea;
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.3);
            color: white;
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .input-group-text {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: rgba(255, 255, 255, 0.8);
        }

        /* Buttons */
        .btn-gradient {
            background: var(--primary);
            border: none;
            border-radius: 12px;
            padding: 15px 30px;
            font-weight: 600;
            font-size: 1rem;
            color: white;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-gradient::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }

        .btn-gradient:hover::before {
            left: 100%;
        }

        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
            background: var(--secondary);
        }

        .btn-outline-glass {
            background: transparent;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            padding: 15px 30px;
            color: white;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-outline-glass:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        /* Featured Gallery - 3 TikTok Images Only */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-top: 3rem;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .gallery-item {
            position: relative;
            aspect-ratio: 9/16; /* TikTok aspect ratio */
            border-radius: 20px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.4s ease;
            background: var(--glass);
        }

        .gallery-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(102, 126, 234, 0.8), rgba(240, 147, 251, 0.8));
            opacity: 0;
            transition: all 0.4s ease;
            z-index: 1;
        }

        .gallery-item:hover::before {
            opacity: 1;
        }

        .gallery-item:hover {
            transform: scale(1.02) rotateZ(0.5deg);
        }

        .gallery-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top; /* Focus on top of image for portraits */
            transition: transform 0.4s ease;
        }

        .gallery-item:hover .gallery-img {
            transform: scale(1.05);
        }

        .gallery-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            z-index: 2;
            opacity: 0;
            transition: all 0.4s ease;
            padding: 20px;
        }

        .gallery-item:hover .gallery-content {
            opacity: 1;
        }

        /* Fallback gallery styling */
        .gallery-fallback {
            text-align: center;
            padding: 60px 20px;
            background: var(--glass);
            border: 2px dashed rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            margin-top: 3rem;
        }

        .gallery-fallback i {
            font-size: 4rem;
            color: #667eea;
            margin-bottom: 1rem;
        }

        /* Alternative masonry-style gallery - REMOVED */

        /* Stats Section */
        .stats {
            padding: 100px 0;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
        }

        .stat-item {
            text-align: center;
            padding: 2rem;
            border-radius: 20px;
            background: var(--glass);
            border: 1px solid var(--glass-border);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--accent);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: -1;
        }

        .stat-item:hover::before {
            opacity: 0.1;
        }

        .stat-item:hover {
            transform: translateY(-10px);
            border-color: rgba(255, 255, 255, 0.4);
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            background: var(--accent);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 1.1rem;
            opacity: 0.8;
            font-weight: 500;
        }

        /* Floating Elements */
        .floating-element {
            position: absolute;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }

        .floating-element:nth-child(1) {
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }

        .floating-element:nth-child(2) {
            top: 60%;
            right: 10%;
            animation-delay: 2s;
        }

        .floating-element:nth-child(3) {
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }

        /* CTA Section */
        .cta-section {
            padding: 100px 0;
            text-align: center;
            position: relative;
        }

        .cta-title {
            font-size: clamp(2rem, 4vw, 3.5rem);
            font-weight: 800;
            margin-bottom: 1.5rem;
            background: var(--secondary);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Footer */
        .footer {
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(20px);
            border-top: 1px solid var(--glass-border);
            padding: 3rem 0;
        }

        .social-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 50%;
            color: white;
            text-decoration: none;
            margin: 0 10px;
            transition: all 0.3s ease;
        }

        .social-icon:hover {
            background: var(--primary);
            transform: translateY(-5px) scale(1.1);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }

        /* Auth Toggle Animation */
        .auth-container {
            position: relative;
            min-height: 600px;
        }

        .auth-form {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            transition: all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            opacity: 1;
            transform: translateX(0);
        }

        .auth-form.slide-out-left {
            opacity: 0;
            transform: translateX(-100%);
        }

        .auth-form.slide-out-right {
            opacity: 0;
            transform: translateX(100%);
        }

        .auth-form.slide-in-left {
            opacity: 1;
            transform: translateX(0);
        }

        .auth-form.slide-in-right {
            opacity: 1;
            transform: translateX(0);
        }

        /* Password Strength */
        .password-strength {
            margin-top: 10px;
        }

        .strength-bar {
            height: 4px;
            border-radius: 2px;
            background: rgba(255, 255, 255, 0.2);
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .strength-weak { background: #ff6b6b; }
        .strength-medium { background: #ffd93d; }
        .strength-strong { background: #6bcf7f; }
        .strength-very-strong { background: var(--accent); }

        /* Language Selector */
        .lang-selector {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            color: white;
            padding: 8px 12px;
        }

        .lang-selector:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #667eea;
            color: white;
        }

        .lang-selector option {
            background: white;
            color: black;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .gallery-grid {
                gap: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .hero {
                text-align: center;
                padding: 80px 0 40px;
            }
            
            .hero-title {
                font-size: 2.5rem;
            }
            
            .profile-image {
                max-width: 350px;
                height: 450px;
                margin-bottom: 3rem;
            }
            
            .gallery-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .glass-card {
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .gallery-grid {
                gap: 1rem;
            }
        }

        /* Loading Animation */
        .loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--dark);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.6s ease;
        }

        .loading.fade-out {
            opacity: 0;
            pointer-events: none;
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Scroll Animations */
        .scroll-reveal {
            opacity: 0;
            transform: translateY(50px);
            transition: all 0.8s ease;
        }

        .scroll-reveal.active {
            opacity: 1;
            transform: translateY(0);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary);
        }

        /* Gallery Style Toggle - REMOVED */
    </style>
</head>
<body>
    <!-- Loading Screen -->
    <div class="loading" id="loading">
        <div class="spinner"></div>
    </div>

    <!-- Animated Background -->
    <div class="animated-bg">
        <div class="floating-element">
            <i class="fas fa-star" style="font-size: 2rem; color: #667eea;"></i>
        </div>
        <div class="floating-element">
            <i class="fas fa-heart" style="font-size: 1.5rem; color: #f093fb;"></i>
        </div>
        <div class="floating-element">
            <i class="fas fa-crown" style="font-size: 2.5rem; color: #4facfe;"></i>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-glass fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-star me-2"></i>Chloe Belle
            </a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="#gallery">Gallery</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="#about">About</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <select class="form-select lang-selector" id="languageSelect">
                            <option value="en">🇬🇧 EN</option>
                            <option value="es">🇪🇸 ES</option>
                            <option value="fr">🇫🇷 FR</option>
                            <option value="de">🇩🇪 DE</option>
                        </select>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="container">
            <div class="row align-items-center g-5">
                <!-- Left Side - Content -->
                <div class="col-lg-5">
                    <div class="hero-content scroll-reveal">
                        <h1 class="hero-title">
                            Welcome to My
                            <span style="display: block;">Digital Universe</span>
                        </h1>
                        <p class="hero-subtitle">
                            Experience exclusive AI-generated content, premium photos, and intimate moments. 
                            Join thousands of subscribers in my private world.
                        </p>
                        
                        <!-- Quick Stats -->
                        <div class="row g-4 mb-4">
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="stat-number" style="font-size: 2rem;">1.2K+</div>
                                    <div class="stat-label" style="font-size: 0.9rem;">Exclusive Photos</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="stat-number" style="font-size: 2rem;">500+</div>
                                    <div class="stat-label" style="font-size: 0.9rem;">Premium Videos</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="stat-number" style="font-size: 2rem;">24/7</div>
                                    <div class="stat-label" style="font-size: 0.9rem;">New Content</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex flex-wrap gap-3">
                            <button class="btn-gradient" id="getStartedBtn">
                                <i class="fas fa-rocket me-2"></i>Get Started
                            </button>
                            <button class="btn-outline-glass" onclick="document.getElementById('gallery').scrollIntoView({behavior: 'smooth'})">
                                <i class="fas fa-images me-2"></i>View Gallery
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right Side - Profile & Auth -->
                <div class="col-lg-7">
                    <div class="row g-4">
                        <!-- Profile Image -->
                        <div class="col-md-5">
                            <div class="profile-container scroll-reveal">
                                <div class="profile-wrapper">
                                    <div class="profile-glow"></div>
                                    <img src="uploads/chloe/profile.jpg" alt="Chloe Belle" class="profile-image">
                                    <div class="profile-overlay">
                                        <div>
                                            <h3 class="mb-3">✨ Enter My World</h3>
                                            <p class="mb-0">Exclusive content awaits</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Auth Forms -->
                        <div class="col-md-7">
                            <div class="auth-container">
                                <!-- Login Form -->
                                <div class="glass-card auth-form" id="loginForm">
                                    <div class="text-center mb-4">
                                        <h4 class="fw-bold">Welcome Back</h4>
                                        <p class="opacity-75">Sign in to access your content</p>
                                    </div>
                                    
                                    <form method="POST" action="auth/login.php">
                                        <div class="mb-3">
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-envelope"></i>
                                                </span>
                                                <input type="email" class="form-control" name="email" placeholder="Email address" required>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-lock"></i>
                                                </span>
                                                <input type="password" class="form-control" name="password" placeholder="Password" required id="loginPassword">
                                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('loginPassword', this)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-4">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="rememberMe" name="remember">
                                                <label class="form-check-label" for="rememberMe">Remember me</label>
                                            </div>
                                            <a href="#" class="text-decoration-none" style="color: #667eea;" data-bs-toggle="modal" data-bs-target="#forgotModal">
                                                Forgot password?
                                            </a>
                                        </div>
                                        
                                        <button type="submit" class="btn-gradient w-100 mb-3">
                                            <i class="fas fa-sign-in-alt me-2"></i>Sign In
                                        </button>
                                    </form>
                                    
                                    <div class="text-center">
                                        <p class="mb-0">Don't have an account? 
                                            <a href="#" class="text-decoration-none fw-bold" style="color: #667eea;" id="showSignup">
                                                Join now
                                            </a>
                                        </p>
                                    </div>
                                </div>

                                <!-- Signup Form -->
                                <div class="glass-card auth-form slide-out-right" id="signupForm" style="opacity: 0; transform: translateX(100%);">
                                    <div class="text-center mb-4">
                                        <h4 class="fw-bold">Join Chloe Belle</h4>
                                        <p class="opacity-75">Create your exclusive account</p>
                                    </div>
                                    
                                    <form method="POST" action="auth/register.php">
                                        <div class="mb-3">
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-user"></i>
                                                </span>
                                                <input type="text" class="form-control" name="username" placeholder="Username" required>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-envelope"></i>
                                                </span>
                                                <input type="email" class="form-control" name="email" placeholder="Email address" required>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-lock"></i>
                                                </span>
                                                <input type="password" class="form-control" name="password" placeholder="Password" required id="signupPassword">
                                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('signupPassword', this)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                            <div class="password-strength">
                                                <div class="strength-bar">
                                                    <div class="strength-fill" id="strengthFill"></div>
                                                </div>
                                                <small id="strengthText" class="text-white-50">Enter a password</small>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-lock"></i>
                                                </span>
                                                <input type="password" class="form-control" name="confirm_password" placeholder="Confirm password" required>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="agreeTerms" name="agree_terms" required>
                                                <label class="form-check-label" for="agreeTerms">
                                                    I agree to the <a href="#" style="color: #667eea;">Terms</a> and <a href="#" style="color: #667eea;">Privacy Policy</a>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn-gradient w-100 mb-3">
                                            <i class="fas fa-user-plus me-2"></i>Create Account
                                        </button>
                                    </form>
                                    
                                    <div class="text-center">
                                        <p class="mb-0">Already have an account? 
                                            <a href="#" class="text-decoration-none fw-bold" style="color: #667eea;" id="showLogin">
                                                Sign in
                                            </a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats scroll-reveal">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-item">
                        <div class="stat-number">50K+</div>
                        <div class="stat-label">Active Members</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-item">
                        <div class="stat-number"><?= number_format(count($featuredImages) * 100) ?>+</div>
                        <div class="stat-label">Exclusive Photos</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-item">
                        <div class="stat-number">500+</div>
                        <div class="stat-label">Premium Videos</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-item">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">New Content</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Gallery -->
    <section id="gallery" class="py-5">
        <div class="container">
            <div class="text-center mb-5 scroll-reveal">
                <h2 class="display-4 fw-bold mb-3" style="background: var(--secondary); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;">
                    Featured Content
                </h2>
                <p class="lead opacity-75">Discover exclusive AI-generated experiences</p>
            </div>
            
            <div class="scroll-reveal">
                <?php if (!empty($featuredImages)): ?>
                    <!-- 3 TikTok Format Images -->
                    <div class="gallery-grid">
                        <?php foreach (array_slice($featuredImages, 0, 3) as $index => $image): ?>
                            <div class="gallery-item" data-image="<?= htmlspecialchars($image['filename']) ?>">
                                <?php 
                                // Use original image for best quality
                                $imageUrl = $image['url'];
                                ?>
                                <img src="<?= htmlspecialchars($imageUrl) ?>?v=<?= $image['modified'] ?>" 
                                     alt="Featured <?= $index + 1 ?>" 
                                     class="gallery-img"
                                     loading="lazy">
                                <div class="gallery-content">
                                    <h5 class="fw-bold mb-2">
                                        <?php
                                        $titles = [
                                            'Premium Collection',
                                            'Behind the Scenes', 
                                            'VIP Access'
                                        ];
                                        echo $titles[$index];
                                        ?>
                                    </h5>
                                    <p class="mb-3">
                                        <?php
                                        $descriptions = [
                                            'Exclusive content for subscribers',
                                            'Get an inside look',
                                            'For lifetime members only'
                                        ];
                                        echo $descriptions[$index];
                                        ?>
                                    </p>
                                    <button class="btn-gradient btn-sm">
                                        <i class="fas fa-unlock me-2"></i>Unlock
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (count($featuredImages) < 3): ?>
                        <div class="text-center mt-4">
                            <p class="text-muted">
                                <i class="fas fa-info-circle me-2"></i>
                                Showing <?= count($featuredImages) ?> of 3 featured spots
                            </p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Fallback content when no images are uploaded -->
                    <div class="gallery-fallback">
                        <i class="fas fa-images"></i>
                        <h4 class="mb-3">Coming Soon</h4>
                        <p class="text-muted mb-4">
                            3 exclusive featured images will be displayed here once uploaded. 
                            Check back soon for premium TikTok-format content!
                        </p>
                        <button class="btn-gradient" onclick="switchToSignup()">
                            <i class="fas fa-bell me-2"></i>Get Notified
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-5">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6 scroll-reveal">
                    <h2 class="display-5 fw-bold mb-4" style="background: var(--accent); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;">
                        About Chloe Belle
                    </h2>
                    <p class="lead mb-4 opacity-90">
                        Welcome to my exclusive digital world! I'm Chloe Belle, your AI companion creating 
                        unique and personalized content just for you.
                    </p>
                    <p class="mb-4 opacity-75">
                        Join my community to access premium photos, videos, and interactive experiences. 
                        Connect with me through posts, comments, and exclusive subscriber content.
                    </p>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <div class="glass-card text-center p-3">
                                <i class="fas fa-star mb-2" style="font-size: 2rem; color: #ffd93d;"></i>
                                <h5 class="fw-bold">Premium Quality</h5>
                                <p class="mb-0 opacity-75">High-resolution content</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="glass-card text-center p-3">
                                <i class="fas fa-lock mb-2" style="font-size: 2rem; color: #4facfe;"></i>
                                <h5 class="fw-bold">Exclusive Access</h5>
                                <p class="mb-0 opacity-75">Members-only content</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6 scroll-reveal">
                    <div class="glass-card">
                        <h5 class="fw-bold mb-3">
                            <i class="fas fa-crown me-2" style="color: #ffd93d;"></i>
                            Subscription Benefits
                        </h5>
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-check me-2" style="color: #6bcf7f;"></i>
                                Unlimited access to premium content
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check me-2" style="color: #6bcf7f;"></i>
                                Daily exclusive posts and updates
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check me-2" style="color: #6bcf7f;"></i>
                                Direct messaging capabilities
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check me-2" style="color: #6bcf7f;"></i>
                                Early access to new content
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check me-2" style="color: #6bcf7f;"></i>
                                Custom content requests
                            </li>
                        </ul>
                        
                        <button class="btn-gradient w-100 mt-3" onclick="document.getElementById('getStartedBtn').click()">
                            <i class="fas fa-rocket me-2"></i>Start Subscription
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section scroll-reveal">
        <div class="container">
            <div class="text-center">
                <h2 class="cta-title">Ready to Join My World?</h2>
                <p class="lead mb-5 opacity-75">
                    Get instant access to exclusive content and become part of an amazing community
                </p>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <button class="btn-gradient" style="font-size: 1.1rem; padding: 18px 40px;" onclick="switchToSignup()">
                        <i class="fas fa-star me-2"></i>Join Now - Free Trial
                    </button>
                    <button class="btn-outline-glass" style="font-size: 1.1rem; padding: 18px 40px;" onclick="document.getElementById('gallery').scrollIntoView({behavior: 'smooth'})">
                        <i class="fas fa-eye me-2"></i>Preview Content
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <i class="fas fa-star me-2" style="color: #667eea; font-size: 1.5rem;"></i>
                        <h5 class="mb-0 fw-bold">Chloe Belle</h5>
                    </div>
                    <p class="mb-0 opacity-75">Exclusive AI-generated content and experiences</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="mb-3">
                        <a href="#" class="social-icon">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="social-icon">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="social-icon">
                            <i class="fab fa-youtube"></i>
                        </a>
                        <a href="#" class="social-icon">
                            <i class="fab fa-tiktok"></i>
                        </a>
                    </div>
                    <small class="opacity-50">© 2025 Chloe Belle. All rights reserved.</small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Modals -->
    <div class="modal fade" id="forgotModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background: var(--dark); border: 1px solid var(--glass-border);">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-white">Reset Password</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label text-white">Email Address</label>
                            <input type="email" class="form-control" placeholder="Enter your email" required>
                        </div>
                        <button type="submit" class="btn-gradient w-100">Send Reset Link</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Page Loading
        window.addEventListener('load', function() {
            setTimeout(() => {
                document.getElementById('loading').classList.add('fade-out');
            }, 1000);
        });

        // Gallery Toggle Functions - REMOVED

        // Auth Form Switching
        function switchToSignup() {
            const loginForm = document.getElementById('loginForm');
            const signupForm = document.getElementById('signupForm');
            
            loginForm.style.opacity = '0';
            loginForm.style.transform = 'translateX(-100%)';
            
            setTimeout(() => {
                loginForm.style.display = 'none';
                signupForm.style.display = 'block';
                signupForm.style.opacity = '1';
                signupForm.style.transform = 'translateX(0)';
            }, 300);
        }

        function switchToLogin() {
            const loginForm = document.getElementById('loginForm');
            const signupForm = document.getElementById('signupForm');
            
            signupForm.style.opacity = '0';
            signupForm.style.transform = 'translateX(100%)';
            
            setTimeout(() => {
                signupForm.style.display = 'none';
                loginForm.style.display = 'block';
                loginForm.style.opacity = '1';
                loginForm.style.transform = 'translateX(0)';
            }, 300);
        }

        // Event Listeners
        document.getElementById('showSignup').addEventListener('click', (e) => {
            e.preventDefault();
            switchToSignup();
        });

        document.getElementById('showLogin').addEventListener('click', (e) => {
            e.preventDefault();
            switchToLogin();
        });

        document.getElementById('getStartedBtn').addEventListener('click', () => {
            switchToSignup();
        });

        // Password Toggle
        function togglePassword(inputId, button) {
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

        // Password Strength
        document.getElementById('signupPassword').addEventListener('input', function() {
            const password = this.value;
            const strength = checkPasswordStrength(password);
            updatePasswordStrength(strength);
        });

        function checkPasswordStrength(password) {
            let score = 0;
            if (password.length >= 8) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^a-zA-Z0-9]/.test(password)) score++;
            
            return score;
        }

        function updatePasswordStrength(score) {
            const fill = document.getElementById('strengthFill');
            const text = document.getElementById('strengthText');
            
            const percentage = (score / 5) * 100;
            fill.style.width = percentage + '%';
            
            if (score === 0) {
                fill.className = 'strength-fill';
                text.textContent = 'Enter a password';
            } else if (score <= 2) {
                fill.className = 'strength-fill strength-weak';
                text.textContent = 'Weak password';
            } else if (score <= 3) {
                fill.className = 'strength-fill strength-medium';
                text.textContent = 'Medium strength';
            } else if (score <= 4) {
                fill.className = 'strength-fill strength-strong';
                text.textContent = 'Strong password';
            } else {
                fill.className = 'strength-fill strength-very-strong';
                text.textContent = 'Very strong!';
            }
        }

        // Scroll Animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('active');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.scroll-reveal').forEach(el => {
            observer.observe(el);
        });

        // Navbar Scroll Effect
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar-glass');
            if (window.scrollY > 100) {
                navbar.style.background = 'rgba(26, 26, 46, 0.95)';
            } else {
                navbar.style.background = 'rgba(26, 26, 46, 0.9)';
            }
        });

        // Language Selection
        document.getElementById('languageSelect').addEventListener('change', function() {
            // In a real implementation, this would change the site language
            console.log('Language changed to:', this.value);
        });

        // Form Submissions with proper AJAX
        document.querySelector('#loginForm form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = {
                email: formData.get('email'),
                password: formData.get('password'),
                remember: formData.get('remember') ? true : false
            };
            
            try {
                const response = await fetch('auth/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Login successful! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = result.redirect || 'feed/';
                    }, 1500);
                } else {
                    showToast(result.message || 'Login failed', 'error');
                }
            } catch (error) {
                console.error('Login error:', error);
                showToast('Login error. Please try again.', 'error');
            }
        });

        document.querySelector('#signupForm form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
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
            
            try {
                const response = await fetch('auth/register.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Registration successful! Please check your email.', 'success');
                    this.reset();
                    switchToLogin();
                } else {
                    showToast(result.message || 'Registration failed', 'error');
                }
            } catch (error) {
                console.error('Signup error:', error);
                showToast('Registration error. Please try again.', 'error');
            }
        });

        // Toast notification function
        function showToast(message, type = 'info') {
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                padding: 15px 20px;
                border-radius: 12px;
                color: white;
                font-weight: 500;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                transform: translateX(100%);
                transition: transform 0.3s ease;
                max-width: 300px;
            `;
            
            if (type === 'success') {
                toast.style.background = 'linear-gradient(135deg, #6bcf7f, #44bd87)';
            } else if (type === 'error') {
                toast.style.background = 'linear-gradient(135deg, #ff6b6b, #ee5a52)';
            } else {
                toast.style.background = 'linear-gradient(135deg, #667eea, #764ba2)';
            }
            
            toast.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            // Animate in
            setTimeout(() => {
                toast.style.transform = 'translateX(0)';
            }, 100);
            
            // Remove after 5 seconds
            setTimeout(() => {
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        // Gallery Item Clicks
        document.querySelectorAll('.gallery-item').forEach(item => {
            item.addEventListener('click', function() {
                // Show subscription modal or redirect to signup
                showToast('Please sign up to view premium content!', 'info');
                switchToSignup();
            });
        });

        console.log('🌟 Chloe Belle - TikTok Optimized Homepage Loaded');
        console.log('📊 Featured images loaded:', <?= count($featuredImages) ?>);
        <?php if (!empty($featuredImages)): ?>
        console.log('🖼️ Images:', <?= json_encode(array_column($featuredImages, 'filename')) ?>);
        <?php endif; ?>
    </script>
</body>
</html>