<?php require_once '../settings.php'; ?>
<?php
/**
 * Stripe Checkout Session Creator
 * Save as: api/create-checkout-session.php
 */

session_start();
require_once '../config.php';
require_once '../settings.php';

// Stripe configuration - ADD THESE TO YOUR config.php
if (!defined('STRIPE_SECRET_KEY')) {
    // Add these to your config.php file:
    // Replace with your actual secret key
    // Replace with your actual publishable key
    // Replace with your webhook secret
}


// Load Stripe keys from site settings if not defined in config.php
function loadStripeKeysFromSettings() {
    if (!defined('STRIPE_SECRET_KEY')) {
        $k = getSetting('stripe_secret_key', '');
        if (!empty($k)) { define('STRIPE_SECRET_KEY', $k); }
    }
    if (!defined('STRIPE_PUBLISHABLE_KEY')) {
        $k = getSetting('stripe_publishable_key', '');
        if (!empty($k)) { define('STRIPE_PUBLISHABLE_KEY', $k); }
    }
    if (!defined('STRIPE_WEBHOOK_SECRET')) {
        $k = getSetting('stripe_webhook_secret', '');
        if (!empty($k)) { define('STRIPE_WEBHOOK_SECRET', $k); }
    }
}
loadStripeKeysFromSettings();

// Include Stripe PHP library (you'll need to install this via Composer)
// For now, we'll use a simple cURL implementation
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
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

    // Get current user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    $planId = $input['plan_id'] ?? '';
    $planName = $input['plan_name'] ?? '';
    $price = floatval($input['price'] ?? 0);

    if (empty($planId) || $price <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid plan data']);
        exit;
    }

    // Get plan details from database
    $stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = ? AND active = 1");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();

    if (!$plan) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Plan not found']);
        exit;
    }

    // Create or get Stripe customer
    $customerId = createOrGetStripeCustomer($user, $pdo);

    // Create Stripe checkout session
    $checkoutSession = createStripeCheckoutSession($plan, $user, $customerId);

    if ($checkoutSession) {
        echo json_encode([
            'success' => true,
            'session_id' => $checkoutSession['id'],
            'url' => $checkoutSession['url']
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create checkout session']);
    }

} catch (Exception $e) {
    error_log("Checkout session error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

function createOrGetStripeCustomer($user, $pdo) {
    // Check if user already has a Stripe customer ID
    if (!empty($user['stripe_customer_id'])) {
        return $user['stripe_customer_id'];
    }

    // Create new Stripe customer
    $customerData = [
        'email' => $user['email'],
        'name' => $user['username'],
        'metadata' => [
            'user_id' => $user['id'],
            'username' => $user['username']
        ]
    ];

    $customer = makeStripeRequest('POST', 'customers', $customerData);

    if ($customer && isset($customer['id'])) {
        // Save customer ID to database
        $stmt = $pdo->prepare("UPDATE users SET stripe_customer_id = ? WHERE id = ?");
        $stmt->execute([$customer['id'], $user['id']]);
        
        return $customer['id'];
    }

    throw new Exception('Failed to create Stripe customer');
}

function createStripeCheckoutSession($plan, $user, $customerId) {
    $domain = 'https://' . $_SERVER['HTTP_HOST'];
    
    // Determine if this is a subscription or one-time payment
    $isSubscription = $plan['billing_cycle'] !== 'lifetime';
    
    $sessionData = [
        'customer' => $customerId,
        'payment_method_types' => ['card'],
        'mode' => $isSubscription ? 'subscription' : 'payment',
        'success_url' => $domain . '/subscription/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $domain . '/subscription/plans.php?canceled=true',
        'metadata' => [
            'user_id' => $user['id'],
            'plan_id' => $plan['id']
        ]
    ];

    if ($isSubscription) {
        // For recurring subscriptions
        $sessionData['line_items'] = [[
            'price_data' => [
                'currency' => 'usd',
                'product_data' => [
                    'name' => $plan['name'],
                    'description' => $plan['description']
                ],
                'unit_amount' => intval($plan['price'] * 100), // Convert to cents
                'recurring' => [
                    'interval' => $plan['billing_cycle'] === 'yearly' ? 'year' : 'month'
                ]
            ],
            'quantity' => 1
        ]];
    } else {
        // For one-time payments (lifetime)
        $sessionData['line_items'] = [[
            'price_data' => [
                'currency' => 'usd',
                'product_data' => [
                    'name' => $plan['name'],
                    'description' => $plan['description']
                ],
                'unit_amount' => intval($plan['price'] * 100) // Convert to cents
            ],
            'quantity' => 1
        ]];
    }

    return makeStripeRequest('POST', 'checkout/sessions', $sessionData);
}

function makeStripeRequest($method, $endpoint, $data = null) {
    $url = 'https://api.stripe.com/v1/' . $endpoint;
    
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . STRIPE_SECRET_KEY,
            'Content-Type: application/x-www-form-urlencoded'
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    if ($data && ($method === 'POST' || $method === 'PUT')) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($response === false) {
        throw new Exception('Stripe API request failed');
    }
    
    $decoded = json_decode($response, true);
    
    if ($httpCode >= 400) {
        $errorMessage = 'Stripe API error';
        if (isset($decoded['error']['message'])) {
            $errorMessage = $decoded['error']['message'];
        }
        throw new Exception($errorMessage);
    }
    
    return $decoded;
}

// Function to handle webhooks (call this from a separate webhook handler)
function handleStripeWebhook($payload, $signature) {
    // Verify webhook signature
    $computedSignature = hash_hmac('sha256', $payload, STRIPE_WEBHOOK_SECRET);
    
    if (!hash_equals($signature, $computedSignature)) {
        http_response_code(400);
        exit('Invalid signature');
    }
    
    $event = json_decode($payload, true);
    
    // Handle different event types
    switch ($event['type']) {
        case 'checkout.session.completed':
            handleCheckoutSessionCompleted($event['data']['object']);
            break;
            
        case 'customer.subscription.created':
            handleSubscriptionCreated($event['data']['object']);
            break;
            
        case 'customer.subscription.updated':
            handleSubscriptionUpdated($event['data']['object']);
            break;
            
        case 'customer.subscription.deleted':
            handleSubscriptionDeleted($event['data']['object']);
            break;
            
        case 'invoice.payment_succeeded':
            handlePaymentSucceeded($event['data']['object']);
            break;
            
        case 'invoice.payment_failed':
            handlePaymentFailed($event['data']['object']);
            break;
            
        default:
            error_log('Unhandled webhook event: ' . $event['type']);
    }
    
    // Mark event as processed
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $stmt = $pdo->prepare("
            INSERT INTO webhook_events (stripe_event_id, event_type, data, processed) 
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE processed = 1, processed_at = NOW()
        ");
        $stmt->execute([$event['id'], $event['type'], json_encode($event)]);
        
    } catch (Exception $e) {
        error_log('Failed to log webhook event: ' . $e->getMessage());
    }
    
    http_response_code(200);
    echo 'Webhook handled';
}

function handleCheckoutSessionCompleted($session) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $userId = $session['metadata']['user_id'];
        $planId = $session['metadata']['plan_id'];
        
        // Get plan details
        $stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = ?");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();
        
        if ($plan) {
            if ($plan['billing_cycle'] === 'lifetime') {
                // Handle lifetime subscription
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET subscription_status = 'lifetime', subscription_expires = NULL 
                    WHERE id = ?
                ");
                $stmt->execute([$userId]);
                
                // Create subscription record
                $stmt = $pdo->prepare("
                    INSERT INTO subscriptions (user_id, plan_id, stripe_customer_id, status) 
                    VALUES (?, ?, ?, 'active')
                ");
                $stmt->execute([$userId, $planId, $session['customer']]);
                
            } else {
                // Handle recurring subscription - will be updated when subscription.created webhook fires
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET subscription_status = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$planId, $userId]);
            }
            
            // Record payment
            $stmt = $pdo->prepare("
                INSERT INTO payment_history (user_id, amount, status, stripe_payment_intent_id, description) 
                VALUES (?, ?, 'succeeded', ?, ?)
            ");
            $stmt->execute([
                $userId, 
                $session['amount_total'] / 100, 
                $session['payment_intent'],
                "Payment for {$plan['name']}"
            ]);
        }
        
    } catch (Exception $e) {
        error_log('Failed to handle checkout completion: ' . $e->getMessage());
    }
}

function handleSubscriptionCreated($subscription) {
    // Handle when a subscription is created in Stripe
    updateSubscriptionInDatabase($subscription);
}

function handleSubscriptionUpdated($subscription) {
    // Handle when a subscription is updated in Stripe
    updateSubscriptionInDatabase($subscription);
}

function handleSubscriptionDeleted($subscription) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Update subscription status
        $stmt = $pdo->prepare("
            UPDATE subscriptions 
            SET status = 'canceled' 
            WHERE stripe_subscription_id = ?
        ");
        $stmt->execute([$subscription['id']]);
        
        // Update user subscription status
        $stmt = $pdo->prepare("
            UPDATE users u
            JOIN subscriptions s ON u.id = s.user_id
            SET u.subscription_status = 'none'
            WHERE s.stripe_subscription_id = ?
        ");
        $stmt->execute([$subscription['id']]);
        
    } catch (Exception $e) {
        error_log('Failed to handle subscription deletion: ' . $e->getMessage());
    }
}

function updateSubscriptionInDatabase($subscription) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Get customer to find user_id
        $customer = makeStripeRequest('GET', 'customers/' . $subscription['customer']);
        $userId = $customer['metadata']['user_id'] ?? null;
        
        if ($userId) {
            // Update or create subscription record
            $stmt = $pdo->prepare("
                INSERT INTO subscriptions 
                (user_id, plan_id, stripe_subscription_id, stripe_customer_id, status, current_period_start, current_period_end) 
                VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?))
                ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                current_period_start = VALUES(current_period_start),
                current_period_end = VALUES(current_period_end)
            ");
            
            // Determine plan_id from subscription
            $planId = determinePlanIdFromSubscription($subscription);
            
            $stmt->execute([
                $userId,
                $planId,
                $subscription['id'],
                $subscription['customer'],
                $subscription['status'],
                $subscription['current_period_start'],
                $subscription['current_period_end']
            ]);
            
            // Update user subscription status and expiry
            $stmt = $pdo->prepare("
                UPDATE users 
                SET subscription_status = ?, subscription_expires = FROM_UNIXTIME(?)
                WHERE id = ?
            ");
            $stmt->execute([$planId, $subscription['current_period_end'], $userId]);
        }
        
    } catch (Exception $e) {
        error_log('Failed to update subscription: ' . $e->getMessage());
    }
}

function determinePlanIdFromSubscription($subscription) {
    // Get the plan from the subscription items
    $items = $subscription['items']['data'];
    if (!empty($items)) {
        $priceId = $items[0]['price']['id'];
        
        // Map Stripe price ID to our plan ID
        // You'll need to update this mapping based on your actual Stripe price IDs
        $priceMapping = [
            'price_monthly' => 'monthly',
            'price_yearly' => 'yearly',
            // Add your actual Stripe price IDs here
        ];
        
        return $priceMapping[$priceId] ?? 'monthly';
    }
    
    return 'monthly'; // Default fallback
}

function handlePaymentSucceeded($invoice) {
    // Record successful payment
    recordPayment($invoice, 'succeeded');
}

function handlePaymentFailed($invoice) {
    // Record failed payment
    recordPayment($invoice, 'failed');
}

function recordPayment($invoice, $status) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Get customer to find user_id
        $customer = makeStripeRequest('GET', 'customers/' . $invoice['customer']);
        $userId = $customer['metadata']['user_id'] ?? null;
        
        if ($userId) {
            $stmt = $pdo->prepare("
                INSERT INTO payment_history 
                (user_id, amount, status, stripe_payment_intent_id, description) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $invoice['amount_paid'] / 100,
                $status,
                $invoice['payment_intent'],
                "Subscription payment"
            ]);
        }
        
    } catch (Exception $e) {
        error_log('Failed to record payment: ' . $e->getMessage());
    }
}
?>