<?php require_once '../settings.php'; ?>
<?php
/**
 * Stripe Webhook Handler
 * Save as: api/stripe-webhook.php
 * 
 * Set this URL in your Stripe dashboard webhooks:
 * https://yoursite.com/api/stripe-webhook.php
 */

require_once '../config.php';
require_once '../settings.php';


// Required webhook events to listen for:
// - checkout.session.completed
// - customer.subscription.created
// - customer.subscription.updated
// - customer.subscription.deleted
// - invoice.payment_succeeded
// - invoice.payment_failed

header('Content-Type: application/json');

// Get the raw POST data
$payload = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Webhook secret (add this to your config.php)
if (!defined('STRIPE_WEBHOOK_SECRET')) {
    }

try {
    // Verify webhook signature
    $event = verifyWebhookSignature($payload, $sig_header, STRIPE_WEBHOOK_SECRET);
    
    // Log the event
    error_log("Stripe webhook received: " . $event['type']);
    
    // Connect to database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // Check if event already processed
    $stmt = $pdo->prepare("SELECT processed FROM webhook_events WHERE stripe_event_id = ?");
    $stmt->execute([$event['id']]);
    $existingEvent = $stmt->fetch();
    
    if ($existingEvent && $existingEvent['processed']) {
        http_response_code(200);
        echo json_encode(['status' => 'already_processed']);
        exit;
    }
    
    // Log the event
    $stmt = $pdo->prepare("
        INSERT INTO webhook_events (stripe_event_id, event_type, data, processed) 
        VALUES (?, ?, ?, 0)
        ON DUPLICATE KEY UPDATE data = VALUES(data)
    ");
    $stmt->execute([$event['id'], $event['type'], json_encode($event)]);
    
    // Handle the event
    switch ($event['type']) {
        case 'checkout.session.completed':
            handleCheckoutSessionCompleted($event['data']['object'], $pdo);
            break;
            
        case 'customer.subscription.created':
        case 'customer.subscription.updated':
            handleSubscriptionChange($event['data']['object'], $pdo);
            break;
            
        case 'customer.subscription.deleted':
            handleSubscriptionDeleted($event['data']['object'], $pdo);
            break;
            
        case 'invoice.payment_succeeded':
            handlePaymentSucceeded($event['data']['object'], $pdo);
            break;
            
        case 'invoice.payment_failed':
            handlePaymentFailed($event['data']['object'], $pdo);
            break;
            
        default:
            error_log('Unhandled webhook event: ' . $event['type']);
    }
    
    // Mark event as processed
    $stmt = $pdo->prepare("UPDATE webhook_events SET processed = 1, processed_at = NOW() WHERE stripe_event_id = ?");
    $stmt->execute([$event['id']]);
    
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    
} catch (Exception $e) {
    error_log('Webhook error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

function verifyWebhookSignature($payload, $sigHeader, $secret) {
    // Parse signature header
    $signatures = [];
    $elements = explode(',', $sigHeader);
    
    foreach ($elements as $element) {
        $parts = explode('=', $element, 2);
        if (count($parts) === 2) {
            $signatures[$parts[0]] = $parts[1];
        }
    }
    
    if (!isset($signatures['v1'])) {
        throw new Exception('No valid signature found');
    }
    
    // Verify signature
    $expectedSignature = hash_hmac('sha256', $payload, $secret);
    
    if (!hash_equals($expectedSignature, $signatures['v1'])) {
        throw new Exception('Invalid signature');
    }
    
    return json_decode($payload, true);
}

function handleCheckoutSessionCompleted($session, $pdo) {
    $userId = $session['metadata']['user_id'] ?? null;
    $planId = $session['metadata']['plan_id'] ?? null;
    
    if (!$userId || !$planId) {
        error_log('Missing metadata in checkout session');
        return;
    }
    
    try {
        // Get plan details
        $stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = ?");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();
        
        if (!$plan) {
            error_log("Plan not found: $planId");
            return;
        }
        
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
                ON DUPLICATE KEY UPDATE status = 'active'
            ");
            $stmt->execute([$userId, $planId, $session['customer']]);
        }
        
        // Record payment
        $stmt = $pdo->prepare("
            INSERT INTO payment_history (user_id, amount, status, stripe_payment_intent_id, description) 
            VALUES (?, ?, 'succeeded', ?, ?)
        ");
        $stmt->execute([
            $userId, 
            $session['amount_total'] / 100, 
            $session['payment_intent'] ?? null,
            "Payment for {$plan['name']}"
        ]);
        
        error_log("Checkout completed for user $userId, plan $planId");
        
    } catch (Exception $e) {
        error_log('Error handling checkout completion: ' . $e->getMessage());
        throw $e;
    }
}

function handleSubscriptionChange($subscription, $pdo) {
    try {
        // Get customer to find user_id
        $customerId = $subscription['customer'];
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE stripe_customer_id = ?");
        $stmt->execute([$customerId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            error_log("User not found for customer: $customerId");
            return;
        }
        
        $userId = $user['id'];
        
        // Determine plan from subscription
        $planId = determinePlanFromSubscription($subscription);
        
        // Update or create subscription record
        $stmt = $pdo->prepare("
            INSERT INTO subscriptions 
            (user_id, plan_id, stripe_subscription_id, stripe_customer_id, status, current_period_start, current_period_end) 
            VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?))
            ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            current_period_start = VALUES(current_period_start),
            current_period_end = VALUES(current_period_end),
            updated_at = NOW()
        ");
        
        $stmt->execute([
            $userId,
            $planId,
            $subscription['id'],
            $subscription['customer'],
            $subscription['status'],
            $subscription['current_period_start'],
            $subscription['current_period_end']
        ]);
        
        // Update user subscription status
        if ($subscription['status'] === 'active') {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET subscription_status = ?, subscription_expires = FROM_UNIXTIME(?)
                WHERE id = ?
            ");
            $stmt->execute([$planId, $subscription['current_period_end'], $userId]);
        }
        
        error_log("Subscription updated for user $userId: " . $subscription['status']);
        
    } catch (Exception $e) {
        error_log('Error handling subscription change: ' . $e->getMessage());
        throw $e;
    }
}

function handleSubscriptionDeleted($subscription, $pdo) {
    try {
        // Update subscription status
        $stmt = $pdo->prepare("
            UPDATE subscriptions 
            SET status = 'canceled', updated_at = NOW()
            WHERE stripe_subscription_id = ?
        ");
        $stmt->execute([$subscription['id']]);
        
        // Update user subscription status
        $stmt = $pdo->prepare("
            UPDATE users u
            JOIN subscriptions s ON u.id = s.user_id
            SET u.subscription_status = 'none', u.subscription_expires = NULL
            WHERE s.stripe_subscription_id = ?
        ");
        $stmt->execute([$subscription['id']]);
        
        error_log("Subscription canceled: " . $subscription['id']);
        
    } catch (Exception $e) {
        error_log('Error handling subscription deletion: ' . $e->getMessage());
        throw $e;
    }
}

function handlePaymentSucceeded($invoice, $pdo) {
    recordPayment($invoice, 'succeeded', $pdo);
}

function handlePaymentFailed($invoice, $pdo) {
    recordPayment($invoice, 'failed', $pdo);
    
    // Update subscription status if payment failed
    if (isset($invoice['subscription'])) {
        try {
            $stmt = $pdo->prepare("
                UPDATE subscriptions 
                SET status = 'past_due', updated_at = NOW()
                WHERE stripe_subscription_id = ?
            ");
            $stmt->execute([$invoice['subscription']]);
        } catch (Exception $e) {
            error_log('Error updating subscription for failed payment: ' . $e->getMessage());
        }
    }
}

function recordPayment($invoice, $status, $pdo) {
    try {
        // Get user from customer ID
        $stmt = $pdo->prepare("SELECT id FROM users WHERE stripe_customer_id = ?");
        $stmt->execute([$invoice['customer']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            error_log("User not found for payment customer: " . $invoice['customer']);
            return;
        }
        
        // Get subscription ID if exists
        $subscriptionId = null;
        if (isset($invoice['subscription'])) {
            $stmt = $pdo->prepare("SELECT id FROM subscriptions WHERE stripe_subscription_id = ?");
            $stmt->execute([$invoice['subscription']]);
            $sub = $stmt->fetch();
            $subscriptionId = $sub['id'] ?? null;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO payment_history 
            (user_id, subscription_id, amount, status, stripe_payment_intent_id, description, metadata) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['id'],
            $subscriptionId,
            $invoice['amount_paid'] / 100,
            $status,
            $invoice['payment_intent'] ?? null,
            $status === 'succeeded' ? 'Subscription payment' : 'Failed payment attempt',
            json_encode(['invoice_id' => $invoice['id']])
        ]);
        
        error_log("Payment $status recorded for user " . $user['id']);
        
    } catch (Exception $e) {
        error_log('Error recording payment: ' . $e->getMessage());
        throw $e;
    }
}

function determinePlanFromSubscription($subscription) {
    // Get the plan from the subscription items
    $items = $subscription['items']['data'] ?? [];
    
    if (!empty($items)) {
        $price = $items[0]['price'];
        $amount = $price['unit_amount'] / 100; // Convert from cents
        $interval = $price['recurring']['interval'] ?? null;
        
        // Map based on amount and interval
        if ($interval === 'month' && $amount >= 15 && $amount <= 25) {
            return 'monthly';
        } elseif ($interval === 'year' && $amount >= 150 && $amount <= 250) {
            return 'yearly';
        }
    }
    
    return 'monthly'; // Default fallback
}
?>