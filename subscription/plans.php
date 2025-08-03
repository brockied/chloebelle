<?php
/**
 * Subscription Plans Page with Stripe Integration
 * Save as: subscription/plans.php
 */

session_start();
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

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

    // Get subscription plans from database
    $plans = $pdo->query("
        SELECT * FROM subscription_plans 
        WHERE active = 1 
        ORDER BY sort_order, price ASC
    ")->fetchAll();

} catch (Exception $e) {
    error_log("Subscription plans error: " . $e->getMessage());
    $plans = [];
}

// Default plans if database is empty
if (empty($plans)) {
    $plans = [
        [
            'id' => 'monthly',
            'name' => 'Monthly VIP',
            'description' => 'Full access to all premium content',
            'price' => 19.99,
            'billing_cycle' => 'monthly',
            'stripe_price_id' => 'price_monthly_placeholder',
            'features' => json_encode([
                'All premium posts and videos',
                'Exclusive photo sets',
                'Direct messaging access',
                'Live stream priority',
                'Monthly exclusive content',
                'Cancel anytime'
            ])
        ],
        [
            'id' => 'yearly',
            'name' => 'Yearly VIP',
            'description' => 'Best value - 2 months free!',
            'price' => 199.99,
            'billing_cycle' => 'yearly',
            'stripe_price_id' => 'price_yearly_placeholder',
            'features' => json_encode([
                'Everything in Monthly VIP',
                'Save $40 per year',
                'Priority customer support',
                'Exclusive yearly bonus content',
                'First access to new features',
                'Annual subscriber perks'
            ])
        ],
        [
            'id' => 'lifetime',
            'name' => 'Lifetime VIP',
            'description' => 'One-time payment, forever access',
            'price' => 499.99,
            'billing_cycle' => 'lifetime',
            'stripe_price_id' => 'price_lifetime_placeholder',
            'features' => json_encode([
                'Everything in Yearly VIP',
                'Lifetime access guarantee',
                'Exclusive lifetime member badge',
                'Special lifetime-only content',
                'VIP Discord channel access',
                'Personal thank you message',
                'Never pay again!'
            ])
        ]
    ];
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
    <title>VIP Subscription Plans - Chloe Belle</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        :root {
            --primary-color: #6c5ce7;
            --accent-color: #fd79a8;
            --gradient-primary: linear-gradient(135deg, #6c5ce7, #a29bfe);
            --gradient-accent: linear-gradient(135deg, #fd79a8, #fdcb6e);
            --gradient-gold: linear-gradient(135deg, #f39c12, #e74c3c);
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .navbar {
            backdrop-filter: blur(10px);
            background: rgba(108, 92, 231, 0.9) !important;
        }
        
        .subscription-header {
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 2rem;
            margin: 2rem 0;
            text-align: center;
            color: white;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
        }
        
        .plan-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: none;
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }
        
        .plan-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.2);
        }
        
        .plan-card.featured {
            transform: scale(1.05);
            border: 3px solid var(--accent-color);
            box-shadow: 0 30px 80px rgba(253, 121, 168, 0.3);
        }
        
        .plan-card.featured::before {
            content: "MOST POPULAR";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            background: var(--gradient-accent);
            color: white;
            text-align: center;
            padding: 0.5rem;
            font-weight: bold;
            font-size: 0.8rem;
            letter-spacing: 1px;
        }
        
        .plan-header {
            padding: 2rem 2rem 1rem;
            text-align: center;
            position: relative;
        }
        
        .plan-name {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .plan-description {
            color: #6c757d;
            margin-bottom: 1rem;
        }
        
        .plan-price {
            font-size: 3rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .plan-price .currency {
            font-size: 1.5rem;
            vertical-align: top;
        }
        
        .plan-price .period {
            font-size: 1rem;
            color: #6c757d;
            font-weight: normal;
        }
        
        .plan-savings {
            background: var(--gradient-accent);
            color: white;
            padding: 0.3rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .plan-features {
            padding: 0 2rem;
            margin-bottom: 2rem;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.8rem;
            padding: 0.5rem 0;
        }
        
        .feature-icon {
            color: var(--primary-color);
            margin-right: 0.8rem;
            width: 20px;
            text-align: center;
        }
        
        .plan-footer {
            padding: 0 2rem 2rem;
        }
        
        .btn-subscribe {
            width: 100%;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: bold;
            border-radius: 50px;
            border: none;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-subscribe.btn-monthly {
            background: var(--gradient-primary);
            color: white;
        }
        
        .btn-subscribe.btn-yearly {
            background: var(--gradient-accent);
            color: white;
        }
        
        .btn-subscribe.btn-lifetime {
            background: var(--gradient-gold);
            color: white;
        }
        
        .btn-subscribe:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .btn-subscribe:disabled {
            background: #6c757d;
            transform: none;
            box-shadow: none;
        }
        
        .current-subscription {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border: 2px solid #28a745;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .current-user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            margin-right: 8px;
        }
        
        .testimonials {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin: 3rem 0;
            color: white;
        }
        
        .testimonial-item {
            text-align: center;
            padding: 1rem;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 6px solid #f3f3f3;
            border-top: 6px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .faq-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 2rem;
            margin: 3rem 0;
        }
        
        .security-badges {
            text-align: center;
            margin: 2rem 0;
            color: rgba(255,255,255,0.8);
        }
        
        .security-badge {
            display: inline-block;
            margin: 0 1rem;
            padding: 0.5rem 1rem;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .plan-card.featured {
                transform: none;
                margin-bottom: 2rem;
            }
            
            .subscription-header {
                margin: 1rem 0;
                padding: 1.5rem;
            }
            
            .plan-price {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center text-white">
            <div class="loading-spinner mb-3"></div>
            <h5>Processing your subscription...</h5>
            <p>Please wait while we redirect you to secure payment...</p>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../index.php">
                <i class="fas fa-star me-2"></i>Chloe Belle
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../feed/">
                            <i class="fas fa-home me-1"></i>Feed
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="plans.php">
                            <i class="fas fa-star me-1"></i>Subscribe
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <img src="<?= getAvatarUrl($currentUser['avatar']) ?>" alt="<?= htmlspecialchars($currentUser['username']) ?>" class="current-user-avatar">
                            <?= htmlspecialchars($currentUser['username']) ?>
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
        <!-- Header Section -->
        <div class="subscription-header">
            <h1 class="display-4 fw-bold mb-3">
                <i class="fas fa-crown text-warning me-3"></i>
                Join the VIP Experience
            </h1>
            <p class="lead mb-4">
                Unlock exclusive content, behind-the-scenes access, and connect with me like never before
            </p>
            <div class="d-flex justify-content-center align-items-center flex-wrap">
                <span class="badge bg-warning text-dark me-3 mb-2">
                    <i class="fas fa-users me-1"></i>2,400+ Happy Subscribers
                </span>
                <span class="badge bg-success me-3 mb-2">
                    <i class="fas fa-lock me-1"></i>Secure Payments
                </span>
                <span class="badge bg-info text-dark mb-2">
                    <i class="fas fa-mobile-alt me-1"></i>Cancel Anytime
                </span>
            </div>
        </div>

        <!-- Current Subscription Status -->
        <?php if ($currentUser['subscription_status'] !== 'none'): ?>
            <div class="current-subscription">
                <h4><i class="fas fa-crown text-warning me-2"></i>Your Current Plan</h4>
                <p class="mb-2">
                    <strong><?= ucfirst($currentUser['subscription_status']) ?> VIP Subscription</strong>
                </p>
                <?php if ($currentUser['subscription_expires']): ?>
                    <p class="mb-0">
                        Expires: <?= date('F j, Y', strtotime($currentUser['subscription_expires'])) ?>
                    </p>
                <?php else: ?>
                    <p class="mb-0">Lifetime Access ✨</p>
                <?php endif; ?>
                <small class="text-muted">
                    Want to upgrade or change your plan? Choose a new plan below.
                </small>
            </div>
        <?php endif; ?>

        <!-- Subscription Plans -->
        <div class="row">
            <?php foreach ($plans as $index => $plan): ?>
                <?php 
                $features = json_decode($plan['features'] ?? '[]', true) ?: [];
                $isCurrentPlan = $currentUser['subscription_status'] === $plan['id'];
                $isFeatured = $plan['id'] === 'yearly';
                ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="plan-card <?= $isFeatured ? 'featured' : '' ?>">
                        <div class="plan-header">
                            <h3 class="plan-name"><?= htmlspecialchars($plan['name']) ?></h3>
                            <p class="plan-description"><?= htmlspecialchars($plan['description']) ?></p>
                            
                            <div class="plan-price">
                                <span class="currency">$</span><?= number_format($plan['price'], 2) ?>
                                <?php if ($plan['billing_cycle'] !== 'lifetime'): ?>
                                    <span class="period">/ <?= $plan['billing_cycle'] === 'yearly' ? 'year' : 'month' ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($plan['id'] === 'yearly'): ?>
                                <div class="plan-savings">Save $40 per year!</div>
                            <?php elseif ($plan['id'] === 'lifetime'): ?>
                                <div class="plan-savings">Best Value Forever!</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="plan-features">
                            <?php foreach ($features as $feature): ?>
                                <div class="feature-item">
                                    <i class="fas fa-check feature-icon"></i>
                                    <span><?= htmlspecialchars($feature) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="plan-footer">
                            <?php if ($isCurrentPlan): ?>
                                <button class="btn btn-subscribe" disabled>
                                    <i class="fas fa-check me-2"></i>Current Plan
                                </button>
                            <?php else: ?>
                                <button class="btn btn-subscribe btn-<?= $plan['id'] ?>" 
                                        onclick="subscribeToPlan('<?= $plan['id'] ?>', '<?= htmlspecialchars($plan['name']) ?>', <?= $plan['price'] ?>)">
                                    <i class="fas fa-crown me-2"></i>
                                    <?= $plan['billing_cycle'] === 'lifetime' ? 'Get Lifetime Access' : 'Subscribe Now' ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Security Badges -->
        <div class="security-badges">
            <div class="security-badge">
                <i class="fas fa-shield-alt me-2"></i>
                SSL Encrypted
            </div>
            <div class="security-badge">
                <i class="fab fa-cc-stripe me-2"></i>
                Stripe Secure
            </div>
            <div class="security-badge">
                <i class="fas fa-lock me-2"></i>
                PCI Compliant
            </div>
        </div>

        <!-- Testimonials -->
        <div class="testimonials">
            <h3 class="text-center mb-4">What VIP Members Are Saying</h3>
            <div class="row">
                <div class="col-md-4">
                    <div class="testimonial-item">
                        <i class="fas fa-quote-left fa-2x mb-3 text-warning"></i>
                        <p>"The exclusive content is absolutely worth it! Love the personal touch and behind-the-scenes access."</p>
                        <strong>- Sarah M.</strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-item">
                        <i class="fas fa-quote-left fa-2x mb-3 text-warning"></i>
                        <p>"Best decision I made! The community is amazing and Chloe really cares about her VIP members."</p>
                        <strong>- Mike J.</strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-item">
                        <i class="fas fa-quote-left fa-2x mb-3 text-warning"></i>
                        <p>"The lifetime plan was such a steal! Already got my money's worth in the first month."</p>
                        <strong>- Emma K.</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="faq-section">
            <h3 class="text-center mb-4">Frequently Asked Questions</h3>
            <div class="accordion" id="faqAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            Can I cancel my subscription anytime?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Yes! You can cancel your monthly or yearly subscription at any time. You'll continue to have access until the end of your current billing period.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            Is my payment information secure?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Absolutely! We use Stripe for payment processing, which is bank-level secure and PCI compliant. We never store your payment information on our servers.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            What happens if I upgrade my plan?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            When you upgrade, you'll be charged a prorated amount for the difference, and your new plan starts immediately with full access to all features.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Stripe
        const stripe = Stripe('pk_test_your_publishable_key_here'); // Replace with your actual publishable key

        function subscribeToPlan(planId, planName, price) {
            // Show loading overlay
            document.getElementById('loadingOverlay').style.display = 'flex';
            
            // Create checkout session
            fetch('../api/create-checkout-session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    plan_id: planId,
                    plan_name: planName,
                    price: price
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect to Stripe Checkout
                    stripe.redirectToCheckout({
                        sessionId: data.session_id
                    }).then(function (result) {
                        if (result.error) {
                            alert('Payment failed: ' + result.error.message);
                            document.getElementById('loadingOverlay').style.display = 'none';
                        }
                    });
                } else {
                    alert('Error: ' + (data.message || 'Failed to create checkout session'));
                    document.getElementById('loadingOverlay').style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                document.getElementById('loadingOverlay').style.display = 'none';
            });
        }

        // Handle URL parameters for success/cancel
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('success') === 'true') {
            // Show success message
            const successAlert = document.createElement('div');
            successAlert.className = 'alert alert-success alert-dismissible fade show';
            successAlert.innerHTML = `
                <strong>🎉 Welcome to VIP!</strong> Your subscription has been activated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.container').insertBefore(successAlert, document.querySelector('.subscription-header'));
        } else if (urlParams.get('canceled') === 'true') {
            // Show canceled message
            const cancelAlert = document.createElement('div');
            cancelAlert.className = 'alert alert-warning alert-dismissible fade show';
            cancelAlert.innerHTML = `
                Payment was canceled. You can try again anytime!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.container').insertBefore(cancelAlert, document.querySelector('.subscription-header'));
        }

        console.log('💳 Subscription plans loaded');
        console.log('🔐 Stripe integration ready');
    </script>
</body>
</html>