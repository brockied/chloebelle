<?php
/**
 * Subscription Success Page
 * Save as: subscription/success.php
 */

session_start();
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$sessionId = $_GET['session_id'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Get current user info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();

    if (!$currentUser) {
        header('Location: ../auth/login.php');
        exit;
    }

    // Get user's latest subscription
    $stmt = $pdo->prepare("
        SELECT s.*, sp.name as plan_name, sp.description, sp.billing_cycle, sp.price
        FROM subscriptions s
        JOIN subscription_plans sp ON s.plan_id = sp.id
        WHERE s.user_id = ?
        ORDER BY s.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$currentUser['id']]);
    $subscription = $stmt->fetch();

    // Get latest payment
    $stmt = $pdo->prepare("
        SELECT * FROM payment_history 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$currentUser['id']]);
    $latestPayment = $stmt->fetch();

} catch (Exception $e) {
    error_log("Success page error: " . $e->getMessage());
    $subscription = null;
    $latestPayment = null;
}

function getAvatarUrl($avatar, $useThumb = true) {
    if (!$avatar) {
        return '../assets/images/default-avatar.jpg';
    }
    
    $avatarPath = $useThumb ? '../uploads/avatars/thumb_' . $avatar : '../uploads/avatars/' . $avatar;
    
    if ($useThumb && !file_exists($avatarPath)) {
        $avatarPath = '../uploads/avatars/' . $avatar;
    }
    
    return file_exists($avatarPath) ? $avatarPath : '../assets/images/default-avatar.jpg';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to VIP! - Chloe Belle</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6c5ce7;
            --accent-color: #fd79a8;
            --gradient-primary: linear-gradient(135deg, #6c5ce7, #a29bfe);
            --gradient-accent: linear-gradient(135deg, #fd79a8, #fdcb6e);
            --gradient-success: linear-gradient(135deg, #00b894, #00cec9);
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .success-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.2);
            padding: 3rem;
            text-align: center;
            margin: 2rem 0;
        }
        
        .success-icon {
            width: 120px;
            height: 120px;
            background: var(--gradient-success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .crown-animation {
            color: #ffd700;
            animation: bounce 2s infinite;
            margin: 0 10px;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }
        
        .subscription-details {
            background: linear-gradient(135deg, #f8f9ff, #e8e5ff);
            border-radius: 20px;
            padding: 2rem;
            margin: 2rem 0;
            border: 3px solid var(--primary-color);
        }
        
        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        
        .benefit-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .benefit-card:hover {
            transform: translateY(-5px);
        }
        
        .benefit-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.5rem;
        }
        
        .btn-explore {
            background: var(--gradient-primary);
            border: none;
            border-radius: 50px;
            padding: 1rem 3rem;
            color: white;
            font-weight: bold;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            margin: 1rem;
        }
        
        .btn-explore:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(108, 92, 231, 0.4);
            color: white;
        }
        
        .current-user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            margin-right: 8px;
        }
        
        .navbar {
            backdrop-filter: blur(10px);
            background: rgba(108, 92, 231, 0.9) !important;
        }
        
        .celebration-confetti {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1000;
        }
        
        .payment-summary {
            background: rgba(40, 167, 69, 0.1);
            border: 2px solid #28a745;
            border-radius: 15px;
            padding: 1.5rem;
            margin: 2rem 0;
        }
        
        .next-steps {
            background: rgba(255, 255, 255, 0.7);
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
        }
        
        .social-share {
            margin: 2rem 0;
        }
        
        .social-share a {
            display: inline-block;
            margin: 0 0.5rem;
            padding: 0.5rem 1rem;
            background: #4267B2;
            color: white;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .social-share a:hover {
            transform: translateY(-2px);
            color: white;
        }
        
        .social-share a.twitter { background: #1DA1F2; }
        .social-share a.instagram { background: linear-gradient(45deg, #405DE6, #5851DB, #833AB4, #C13584, #E1306C, #FD1D1D); }
    </style>
</head>
<body>
    <!-- Confetti Animation -->
    <div class="celebration-confetti" id="confetti"></div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../index.php">
                <i class="fas fa-star me-2"></i>Chloe Belle
            </a>
            
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../feed/">
                            <i class="fas fa-home me-1"></i>Feed
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="plans.php">
                            <i class="fas fa-star me-1"></i>Subscription
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <img src="<?= getAvatarUrl($currentUser['avatar']) ?>" alt="<?= htmlspecialchars($currentUser['username']) ?>" class="current-user-avatar">
                            <?= htmlspecialchars($currentUser['username']) ?>
                            <i class="fas fa-crown text-warning ms-2"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../user/profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a></li>
                            <li><a class="dropdown-item" href="../user/settings.php">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5 pt-4">
        <div class="success-container">
            <!-- Success Icon -->
            <div class="success-icon">
                <i class="fas fa-check fa-3x text-white"></i>
            </div>

            <!-- Main Success Message -->
            <h1 class="display-4 fw-bold mb-3">
                <i class="fas fa-crown crown-animation"></i>
                Welcome to VIP!
                <i class="fas fa-crown crown-animation"></i>
            </h1>
            <p class="lead text-muted mb-4">
                🎉 Congratulations! Your subscription has been activated successfully. 
                You now have access to all exclusive content and VIP features.
            </p>

            <!-- Subscription Details -->
            <?php if ($subscription): ?>
                <div class="subscription-details">
                    <h3><i class="fas fa-star text-warning me-2"></i><?= htmlspecialchars($subscription['plan_name']) ?></h3>
                    <p class="text-muted mb-3"><?= htmlspecialchars($subscription['description']) ?></p>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <strong>Plan Type:</strong><br>
                            <span class="text-primary"><?= ucfirst($subscription['billing_cycle']) ?></span>
                        </div>
                        <div class="col-md-4">
                            <strong>Amount Paid:</strong><br>
                            <span class="text-success">$<?= number_format($subscription['price'], 2) ?></span>
                        </div>
                        <div class="col-md-4">
                            <strong>Status:</strong><br>
                            <span class="badge bg-success">Active</span>
                        </div>
                    </div>
                    
                    <?php if ($subscription['billing_cycle'] !== 'lifetime'): ?>
                        <hr>
                        <p class="mb-0">
                            <i class="fas fa-calendar me-2"></i>
                            <strong>Next billing:</strong> 
                            <?= $subscription['billing_cycle'] === 'monthly' ? 'Next month' : 'Next year' ?>
                        </p>
                    <?php else: ?>
                        <hr>
                        <p class="mb-0">
                            <i class="fas fa-infinity me-2"></i>
                            <strong>Lifetime access - Never pay again! ✨</strong>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Payment Confirmation -->
            <?php if ($latestPayment): ?>
                <div class="payment-summary">
                    <h5><i class="fas fa-check-circle text-success me-2"></i>Payment Confirmed</h5>
                    <p class="mb-2">
                        <strong>Amount:</strong> $<?= number_format($latestPayment['amount'], 2) ?>
                        <span class="ms-3"><strong>Status:</strong> <span class="text-success">Paid</span></span>
                    </p>
                    <small class="text-muted">
                        Transaction completed on <?= date('F j, Y \a\t g:i A', strtotime($latestPayment['created_at'])) ?>
                    </small>
                </div>
            <?php endif; ?>

            <!-- VIP Benefits -->
            <div class="benefits-grid">
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                    <h5>Exclusive Content</h5>
                    <p>Access to all premium posts, photos, and videos</p>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h5>VIP Community</h5>
                    <p>Join exclusive discussions and comment on all content</p>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h5>Early Access</h5>
                    <p>Be the first to see new content and features</p>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h5>Direct Messaging</h5>
                    <p>Send messages and get personal responses</p>
                </div>
            </div>

            <!-- Next Steps -->
            <div class="next-steps">
                <h4><i class="fas fa-rocket me-2"></i>What's Next?</h4>
                <div class="row text-start">
                    <div class="col-md-6">
                        <h6><i class="fas fa-1 me-2 text-primary"></i>Explore Premium Content</h6>
                        <p>Head to the feed and start enjoying exclusive posts marked with the crown icon!</p>
                        
                        <h6><i class="fas fa-2 me-2 text-primary"></i>Update Your Profile</h6>
                        <p>Add a profile picture and bio to connect with the VIP community.</p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-3 me-2 text-primary"></i>Join the Discussion</h6>
                        <p>You can now comment on all posts and engage with other VIP members.</p>
                        
                        <h6><i class="fas fa-4 me-2 text-primary"></i>Stay Updated</h6>
                        <p>Turn on notifications to never miss new exclusive content.</p>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex flex-wrap justify-content-center gap-3 my-4">
                <a href="../feed/" class="btn-explore">
                    <i class="fas fa-home me-2"></i>
                    Explore Premium Feed
                </a>
                <a href="../user/profile.php" class="btn-explore" style="background: var(--gradient-accent);">
                    <i class="fas fa-user me-2"></i>
                    Update Profile
                </a>
            </div>

            <!-- Social Share -->
            <div class="social-share">
                <h6>Share your VIP status:</h6>
                <a href="#" class="facebook" onclick="shareOnFacebook()">
                    <i class="fab fa-facebook-f me-1"></i>Facebook
                </a>
                <a href="#" class="twitter" onclick="shareOnTwitter()">
                    <i class="fab fa-twitter me-1"></i>Twitter
                </a>
                <a href="#" class="instagram">
                    <i class="fab fa-instagram me-1"></i>Instagram
                </a>
            </div>

            <!-- Receipt/Invoice -->
            <div class="mt-4">
                <p class="text-muted">
                    <i class="fas fa-receipt me-2"></i>
                    A receipt has been sent to your email address. 
                    You can manage your subscription anytime in your 
                    <a href="../user/settings.php">account settings</a>.
                </p>
            </div>

            <!-- Support -->
            <div class="mt-4 pt-4 border-top">
                <h6>Need Help?</h6>
                <p class="text-muted">
                    If you have any questions about your subscription or accessing content, 
                    feel free to <a href="mailto:support@chloebelle.vip">contact support</a>.
                </p>
            </div>
        </div>

        <!-- Testimonial -->
        <div class="text-center my-5">
            <div class="card" style="background: rgba(255,255,255,0.9); border-radius: 20px; border: none;">
                <div class="card-body p-4">
                    <i class="fas fa-quote-left fa-2x text-primary mb-3"></i>
                    <blockquote class="blockquote">
                        <p>"Joining VIP was the best decision! The exclusive content is amazing and the community is so supportive. Totally worth every penny!"</p>
                    </blockquote>
                    <figcaption class="blockquote-footer mt-3">
                        Sarah M., <cite title="VIP Member">VIP Member since 2024</cite>
                    </figcaption>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Confetti animation
        function createConfetti() {
            const confettiContainer = document.getElementById('confetti');
            const colors = ['#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#feca57', '#ff9ff3'];
            
            for (let i = 0; i < 100; i++) {
                const confetti = document.createElement('div');
                confetti.style.position = 'absolute';
                confetti.style.width = '10px';
                confetti.style.height = '10px';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.top = '-10px';
                confetti.style.borderRadius = Math.random() > 0.5 ? '50%' : '0';
                confetti.style.animation = `fall ${2 + Math.random() * 3}s linear forwards`;
                confetti.style.transform = `rotate(${Math.random() * 360}deg)`;
                confettiContainer.appendChild(confetti);
                
                // Remove confetti after animation
                setTimeout(() => {
                    if (confetti.parentNode) {
                        confetti.parentNode.removeChild(confetti);
                    }
                }, 5000);
            }
        }

        // CSS for confetti animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fall {
                from {
                    transform: translateY(-100px) rotate(0deg);
                    opacity: 1;
                }
                to {
                    transform: translateY(100vh) rotate(360deg);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // Start confetti when page loads
        window.addEventListener('load', function() {
            createConfetti();
            
            // Create more confetti every few seconds
            setTimeout(createConfetti, 2000);
            setTimeout(createConfetti, 4000);
        });

        // Social sharing functions
        function shareOnFacebook() {
            const url = encodeURIComponent(window.location.origin);
            const text = encodeURIComponent("Just joined Chloe Belle VIP! 🎉👑 Amazing exclusive content!");
            window.open(`https://www.facebook.com/sharer/sharer.php?u=${url}&quote=${text}`, '_blank', 'width=600,height=400');
        }

        function shareOnTwitter() {
            const url = encodeURIComponent(window.location.origin);
            const text = encodeURIComponent("Just joined @ChloeBelle VIP! 🎉👑 The exclusive content is incredible! #VIP #ChloeBelle");
            window.open(`https://twitter.com/intent/tweet?text=${text}&url=${url}`, '_blank', 'width=600,height=400');
        }

        // Auto-redirect to feed after 30 seconds
        let countdown = 30;
        const redirectTimer = setInterval(() => {
            countdown--;
            if (countdown <= 0) {
                clearInterval(redirectTimer);
                window.location.href = '../feed/';
            }
        }, 1000);

        // Success page analytics (optional)
        console.log('🎉 VIP subscription activated successfully!');
        console.log('👑 User now has access to premium content');
        console.log('💳 Payment processed successfully');

        // Show success message in console for debugging
        <?php if ($subscription): ?>
        console.log('Subscription details:', {
            plan: '<?= htmlspecialchars($subscription['plan_name']) ?>',
            billing: '<?= $subscription['billing_cycle'] ?>',
            price: '<?= $subscription['price'] ?>',
            status: 'active'
        });
        <?php endif; ?>
    </script>
</body>
</html>