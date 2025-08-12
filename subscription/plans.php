<?php
session_start();
require_once '../config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Get current user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();

    if (!$currentUser) {
        session_destroy();
        header('Location: ../auth/login.php');
        exit;
    }

    // Get site settings for pricing
    $settings = [];
    try {
        $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
        while ($row = $settingsStmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        error_log("Settings query error: " . $e->getMessage());
    }

} catch (Exception $e) {
    error_log("Plans page error: " . $e->getMessage());
    $currentUser = null;
}

function getPlanPrice($settings, $plan, $currency = 'GBP') {
    $key = "subscription_{$plan}_price_" . strtolower($currency);
    return $settings[$key] ?? '9.99';
}

function getCurrencySymbol($currency) {
    return $currency === 'USD' ? '$' : '£';
}

function getFeaturesList($plan) {
    $features = [
        'monthly' => [
            'Unlimited access to premium content',
            'High-resolution photos and videos',
            'Exclusive weekly content drops',
            'Priority comment responses',
            'Mobile and desktop access',
            'Cancel anytime'
        ],
        'yearly' => [
            'Everything in Monthly plan',
            'Save 17% with yearly billing',
            'Early access to new content',
            'Behind-the-scenes content',
            'Exclusive live streams',
            'Birthday month bonus content',
            'Priority customer support'
        ],
        'lifetime' => [
            'Everything in Yearly plan',
            'One-time payment, lifetime access',
            'VIP status and badge',
            'Exclusive lifetime member content',
            'Direct messaging privileges',
            'Annual exclusive photo sets',
            'Forever locked-in pricing',
            'Priority access to new features'
        ]
    ];
    
    return $features[$plan] ?? [];
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
            border: 3px solid var(--accent-color);
            transform: scale(1.05);
        }
        
        .plan-card.featured:hover {
            transform: scale(1.08) translateY(-10px);
        }
        
        .plan-header {
            background: var(--gradient-primary);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }
        
        .plan-card.featured .plan-header {
            background: var(--gradient-accent);
        }
        
        .plan-card.lifetime .plan-header {
            background: var(--gradient-gold);
        }
        
        .popular-badge {
            position: absolute;
            top: -10px;
            right: 20px;
            background: var(--accent-color);
            color: white;
            padding: 5px 20px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .plan-price {
            font-size: 3rem;
            font-weight: bold;
            margin: 0;
        }
        
        .plan-period {
            opacity: 0.8;
            font-size: 1rem;
        }
        
        .plan-features {
            padding: 2rem;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.5rem 0;
        }
        
        .feature-icon {
            color: var(--primary-color);
            margin-right: 1rem;
            width: 20px;
        }
        
        .plan-card.featured .feature-icon {
            color: var(--accent-color);
        }
        
        .plan-card.lifetime .feature-icon {
            color: #f39c12;
        }
        
        .subscribe-btn {
            background: var(--gradient-primary);
            border: none;
            color: white;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: bold;
            transition: all 0.3s ease;
            width: 100%;
            margin: 1rem 0;
        }
        
        .subscribe-btn:hover {
            background: var(--gradient-accent);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .plan-card.lifetime .subscribe-btn {
            background: var(--gradient-gold);
        }
        
        .current-plan {
            background: linear-gradient(135deg, #00cec9, #55a3ff);
            color: white;
        }
        
        .testimonial {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin: 2rem 0;
            color: white;
            text-align: center;
        }
        
        .faq-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            margin: 2rem 0;
        }
        
        .savings-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: #e74c3c;
            color: white;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .current-user-avatar {
            width: 32px;
            height: 32px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../feed/index.php">
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
                            <img src="<?= getAvatarUrl($currentUser['avatar']) ?>" alt="<?= htmlspecialchars($currentUser['username']) ?>" class="current-user-avatar rounded-circle me-2">
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
        <!-- Header -->
        <div class="subscription-header">
            <h1 class="display-4 fw-bold mb-3">
                <i class="fas fa-crown me-3"></i>VIP Membership Plans
            </h1>
            <p class="lead mb-0">Unlock exclusive content and join my premium community</p>
        </div>

        <!-- Current Subscription Status -->
        <?php if ($currentUser['subscription_status'] !== 'none'): ?>
            <div class="alert alert-success text-center">
                <i class="fas fa-check-circle fa-2x mb-2"></i>
                <h5>You're a <?= ucfirst($currentUser['subscription_status']) ?> Member!</h5>
                <p class="mb-0">Thank you for your support! You have full access to all premium content.</p>
            </div>
        <?php endif; ?>

        <!-- Pricing Plans -->
        <div class="row justify-content-center">
            <!-- Monthly Plan -->
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="plan-card">
                    <div class="plan-header">
                        <h3 class="mb-3">Monthly VIP</h3>
                        <div class="plan-price"><?= getCurrencySymbol('GBP') ?><?= getPlanPrice($settings, 'monthly', 'GBP') ?></div>
                        <div class="plan-period">per month</div>
                    </div>
                    <div class="plan-features">
                        <?php foreach (getFeaturesList('monthly') as $feature): ?>
                            <div class="feature-item">
                                <i class="fas fa-check feature-icon"></i>
                                <span><?= htmlspecialchars($feature) ?></span>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if ($currentUser['subscription_status'] === 'monthly'): ?>
                            <button class="subscribe-btn current-plan" disabled>
                                <i class="fas fa-check me-2"></i>Current Plan
                            </button>
                        <?php else: ?>
                            <button class="subscribe-btn" onclick="subscribe('monthly')">
                                <i class="fas fa-credit-card me-2"></i>Subscribe Monthly
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Yearly Plan (Featured) -->
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="plan-card featured">
                    <div class="popular-badge">Most Popular</div>
                    <div class="savings-badge">Save 17%</div>
                    <div class="plan-header">
                        <h3 class="mb-3">Yearly VIP</h3>
                        <div class="plan-price"><?= getCurrencySymbol('GBP') ?><?= getPlanPrice($settings, 'yearly', 'GBP') ?></div>
                        <div class="plan-period">per year</div>
                        <small class="d-block mt-2 opacity-75">
                            That's just <?= getCurrencySymbol('GBP') ?><?= number_format(getPlanPrice($settings, 'yearly', 'GBP') / 12, 2) ?>/month
                        </small>
                    </div>
                    <div class="plan-features">
                        <?php foreach (getFeaturesList('yearly') as $feature): ?>
                            <div class="feature-item">
                                <i class="fas fa-check feature-icon"></i>
                                <span><?= htmlspecialchars($feature) ?></span>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if ($currentUser['subscription_status'] === 'yearly'): ?>
                            <button class="subscribe-btn current-plan" disabled>
                                <i class="fas fa-check me-2"></i>Current Plan
                            </button>
                        <?php else: ?>
                            <button class="subscribe-btn" onclick="subscribe('yearly')">
                                <i class="fas fa-crown me-2"></i>Subscribe Yearly
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Lifetime Plan -->
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="plan-card lifetime">
                    <div class="plan-header">
                        <h3 class="mb-3">Lifetime Access</h3>
                        <div class="plan-price"><?= getCurrencySymbol('GBP') ?><?= getPlanPrice($settings, 'lifetime', 'GBP') ?></div>
                        <div class="plan-period">one-time payment</div>
                    </div>
                    <div class="plan-features">
                        <?php foreach (getFeaturesList('lifetime') as $feature): ?>
                            <div class="feature-item">
                                <i class="fas fa-infinity feature-icon"></i>
                                <span><?= htmlspecialchars($feature) ?></span>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if ($currentUser['subscription_status'] === 'lifetime'): ?>
                            <button class="subscribe-btn current-plan" disabled>
                                <i class="fas fa-infinity me-2"></i>Lifetime Member
                            </button>
                        <?php else: ?>
                            <button class="subscribe-btn" onclick="subscribe('lifetime')">
                                <i class="fas fa-infinity me-2"></i>Get Lifetime Access
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Testimonial -->
        <div class="testimonial">
            <i class="fas fa-quote-left fa-3x mb-3 opacity-50"></i>
            <blockquote class="blockquote">
                <p class="mb-3">"The exclusive content and personal interaction make this subscription absolutely worth it. Chloe's creativity and engagement with her community is amazing!"</p>
            </blockquote>
            <div class="d-flex align-items-center justify-content-center">
                <img src="../assets/images/testimonial-avatar.jpg" alt="Happy Member" class="rounded-circle me-3" style="width: 50px; height: 50px; object-fit: cover;">
                <div>
                    <strong>Sarah M.</strong><br>
                    <small class="opacity-75">VIP Member since 2023</small>
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
                            What do I get with a VIP membership?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            VIP members get unlimited access to all premium photos, videos, and exclusive content. You'll also receive priority responses to comments and early access to new releases.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            Can I cancel my subscription anytime?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Yes! Monthly and yearly subscriptions can be cancelled at any time. You'll continue to have access until your current billing period ends.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            Is my payment information secure?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Absolutely! We use Stripe for secure payment processing. Your payment information is encrypted and never stored on our servers.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                            What's included in the lifetime membership?
                        </button>
                    </h2>
                    <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Lifetime membership includes everything in the yearly plan, plus exclusive lifetime-only content, VIP status, and guaranteed access to all future features and content - forever!
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Money Back Guarantee -->
        <div class="text-center mb-5">
            <div class="badge bg-success fs-6 p-3 mb-3">
                <i class="fas fa-shield-alt me-2"></i>30-Day Money Back Guarantee
            </div>
            <p class="text-white">Not satisfied? Get a full refund within 30 days, no questions asked.</p>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        
        function subscribe(plan) {
            (function() {
                const activeGateway = "<?= htmlspecialchars(getSetting('payment_gateway', 'stripe')) ?>";
                const publishableKey = "<?= htmlspecialchars(getSetting('stripe_publishable_key', '')) ?>";
                const currency = "<?= htmlspecialchars(getSiteCurrency()) ?>";
                const prices = {
                    monthly: "<?= htmlspecialchars(getPlanPrice($settings, 'monthly', getSiteCurrency())) ?>",
                    yearly: "<?= htmlspecialchars(getPlanPrice($settings, 'yearly', getSiteCurrency())) ?>",
                    lifetime: "<?= htmlspecialchars(getPlanPrice($settings, 'lifetime', getSiteCurrency())) ?>"
                };
                
                if (activeGateway === 'stripe') {
                    if (!publishableKey) {
                        alert("Stripe not configured. Add your Stripe keys in Admin → Settings → Subscriptions.");
                        return;
                    }
                    fetch("/api/create-checkout-session.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({
                            plan: plan,
                            price: prices[plan],
                            currency: currency
                        })
                    }).then(r => r.json()).then(data => {
                        if (data && data.success && data.url) {
                            window.location = data.url;
                        } else {
                            alert((data && data.message) ? data.message : "Could not start checkout. Check your Stripe settings.");
                        }
                    }).catch(() => alert("There was an error starting checkout."));
                    return;
                }
                
                if (activeGateway === 'paypal') {
                    alert("PayPal checkout is not fully set up in this build. Please switch to Stripe in Admin → Settings → Subscriptions or complete the PayPal integration.");
                    return;
                }
                
                alert("No payment gateway configured. Please ask the site admin to configure Stripe or PayPal.");
            })();
        }
subscription...`);
            
            // Example: redirect to payment processor
            // window.location.href = `payment.php?plan=${plan}`;
            
            // Or you could open Stripe Checkout directly here
            // stripe.redirectToCheckout({ sessionId: 'session_id_from_server' });
        }
        
        // Add some interactive effects
        document.querySelectorAll('.plan-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = this.classList.contains('featured') ? 'scale(1.08) translateY(-10px)' : 'translateY(-10px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = this.classList.contains('featured') ? 'scale(1.05)' : '';
            });
        });
    </script>
</body>
</html>
    <div id="paypal-button-container" data-plan-id="<?= htmlspecialchars(getSetting('paypal_plan_monthly')) ?>"></div>
    <script src="https://www.paypal.com/sdk/js?client-id=<?= urlencode(getSetting('paypal_client_id')) ?>&vault=true&intent=subscription&currency=<?= urlencode(getSiteCurrency()) ?>&components=buttons" data-namespace="paypal"></script>
    <script src="/assets/js/paypal.js"></script>
    