<?php
/**
 * Settings Validator and Test Functions
 * Add this to your admin settings page to test various configurations
 */

class SettingsValidator {
    private $pdo;
    private $settings = [];
    
    public function __construct() {
        try {
            require_once __DIR__ . '/../config.php';
            
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            
            $this->loadSettings();
        } catch (Exception $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    private function loadSettings() {
        $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM site_settings");
        while ($row = $stmt->fetch()) {
            $this->settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    private function getSetting($key, $default = '') {
        return $this->settings[$key] ?? $default;
    }
    
    /**
     * Test all settings and return status
     */
    public function validateAllSettings() {
        $results = [
            'general' => $this->validateGeneralSettings(),
            'email' => $this->validateEmailSettings(),
            'content' => $this->validateContentSettings(),
            'subscription' => $this->validateSubscriptionSettings(),
            'system' => $this->validateSystemSettings()
        ];
        
        return $results;
    }
    
    /**
     * Validate general settings
     */
    public function validateGeneralSettings() {
        $issues = [];
        $warnings = [];
        
        // Check required settings
        if (empty($this->getSetting('site_name'))) {
            $issues[] = "Site name is required";
        }
        
        if (empty($this->getSetting('from_email'))) {
            $issues[] = "From email address is required";
        }
        
        // Check maintenance mode
        if ($this->getSetting('maintenance_mode') === '1') {
            $warnings[] = "Maintenance mode is currently enabled";
        }
        
        // Check Google Analytics ID format
        $gaId = $this->getSetting('google_analytics_id');
        if (!empty($gaId) && !preg_match('/^G-[A-Z0-9]+$/', $gaId)) {
            $warnings[] = "Google Analytics ID format may be incorrect (should start with G-)";
        }
        
        return [
            'status' => empty($issues) ? 'ok' : 'error',
            'issues' => $issues,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Validate email settings
     */
    public function validateEmailSettings() {
        $issues = [];
        $warnings = [];
        
        $emailMethod = $this->getSetting('email_method', 'php_mail');
        $fromEmail = $this->getSetting('from_email');
        
        if (empty($fromEmail)) {
            $issues[] = "From email address is required";
        } elseif (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $issues[] = "From email address is not valid";
        }
        
        if ($emailMethod === 'smtp') {
            $requiredSMTP = ['smtp_host', 'smtp_username', 'smtp_password'];
            foreach ($requiredSMTP as $setting) {
                if (empty($this->getSetting($setting))) {
                    $issues[] = "SMTP setting '$setting' is required when using SMTP method";
                }
            }
            
            $smtpPort = $this->getSetting('smtp_port', 587);
            if (!is_numeric($smtpPort) || $smtpPort < 1 || $smtpPort > 65535) {
                $issues[] = "SMTP port must be a valid number between 1 and 65535";
            }
        }
        
        return [
            'status' => empty($issues) ? 'ok' : 'error',
            'issues' => $issues,
            'warnings' => $warnings,
            'method' => $emailMethod
        ];
    }
    
    /**
     * Validate content settings
     */
    public function validateContentSettings() {
        $issues = [];
        $warnings = [];
        
        $maxPostLength = $this->getSetting('max_post_length', 5000);
        if (!is_numeric($maxPostLength) || $maxPostLength < 100) {
            $issues[] = "Maximum post length must be at least 100 characters";
        } elseif ($maxPostLength > 50000) {
            $warnings[] = "Very high post length limit may impact performance";
        }
        
        // Check uploads directory
        if (!is_writable(__DIR__ . '/../uploads')) {
            $issues[] = "Uploads directory is not writable";
        }
        
        return [
            'status' => empty($issues) ? 'ok' : 'error',
            'issues' => $issues,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Validate subscription settings
     */
    public function validateSubscriptionSettings() {
        $issues = [];
        $warnings = [];
        
        $paymentGateway = $this->getSetting('payment_gateway', 'stripe');
        
        // Validate pricing
        $prices = [
            'subscription_monthly_price_gbp',
            'subscription_monthly_price_usd',
            'subscription_yearly_price_gbp',
            'subscription_yearly_price_usd'
        ];
        
        foreach ($prices as $priceKey) {
            $price = $this->getSetting($priceKey);
            if (!empty($price) && (!is_numeric($price) || $price < 0)) {
                $issues[] = "Invalid price for $priceKey";
            }
        }
        
        // Check gateway configuration
        if ($paymentGateway === 'stripe') {
            if (empty($this->getSetting('stripe_publishable_key'))) {
                $warnings[] = "Stripe publishable key is not configured";
            }
            if (empty($this->getSetting('stripe_secret_key'))) {
                $warnings[] = "Stripe secret key is not configured";
            }
        } elseif ($paymentGateway === 'paypal') {
            if (empty($this->getSetting('paypal_client_id'))) {
                $warnings[] = "PayPal client ID is not configured";
            }
            if (empty($this->getSetting('paypal_secret'))) {
                $warnings[] = "PayPal secret is not configured";
            }
        }
        
        return [
            'status' => empty($issues) ? (empty($warnings) ? 'ok' : 'warning') : 'error',
            'issues' => $issues,
            'warnings' => $warnings,
            'gateway' => $paymentGateway
        ];
    }
    
    /**
     * Validate system settings
     */
    public function validateSystemSettings() {
        $issues = [];
        $warnings = [];
        
        // Check PHP version
        $phpVersion = PHP_VERSION;
        if (version_compare($phpVersion, '7.4', '<')) {
            $issues[] = "PHP version $phpVersion is too old. Minimum required: 7.4";
        } elseif (version_compare($phpVersion, '8.0', '<')) {
            $warnings[] = "PHP version $phpVersion is getting old. Consider upgrading to PHP 8.0+";
        }
        
        // Check required extensions
        $requiredExtensions = ['pdo', 'pdo_mysql', 'gd', 'fileinfo', 'json'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $issues[] = "Required PHP extension '$ext' is not loaded";
            }
        }
        
        // Check memory limit
        $memoryLimit = ini_get('memory_limit');
        $memoryBytes = $this->parseBytes($memoryLimit);
        if ($memoryBytes < 128 * 1024 * 1024) { // 128MB
            $warnings[] = "Memory limit ($memoryLimit) is quite low. Consider increasing to 256M or higher";
        }
        
        // Check upload limits
        $uploadLimit = ini_get('upload_max_filesize');
        $uploadBytes = $this->parseBytes($uploadLimit);
        if ($uploadBytes < 10 * 1024 * 1024) { // 10MB
            $warnings[] = "Upload file size limit ($uploadLimit) may be too low for media uploads";
        }
        
        return [
            'status' => empty($issues) ? (empty($warnings) ? 'ok' : 'warning') : 'error',
            'issues' => $issues,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Parse byte values from PHP ini settings
     */
    private function parseBytes($value) {
        $value = trim($value);
        $last = strtolower($value[strlen($value)-1]);
        $number = substr($value, 0, -1);
        
        switch($last) {
            case 'g':
                $number *= 1024;
            case 'm':
                $number *= 1024;
            case 'k':
                $number *= 1024;
        }
        
        return $number;
    }
    
    /**
     * Test email functionality
     */
    public function testEmail($testEmailAddress = null) {
        try {
            require_once __DIR__ . '/email_helper.php';
            $emailHelper = new EmailHelper();
            
            $testEmail = $testEmailAddress ?: $this->getSetting('from_email');
            
            if (empty($testEmail)) {
                return [
                    'success' => false,
                    'message' => 'No test email address available'
                ];
            }
            
            return $emailHelper->testEmailConfig($testEmail);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Email test failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate settings report
     */
    public function generateReport() {
        $validation = $this->validateAllSettings();
        
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'overall_status' => 'ok',
            'summary' => [
                'total_issues' => 0,
                'total_warnings' => 0
            ],
            'sections' => $validation
        ];
        
        // Calculate overall status
        foreach ($validation as $section => $result) {
            if ($result['status'] === 'error') {
                $report['overall_status'] = 'error';
            } elseif ($result['status'] === 'warning' && $report['overall_status'] !== 'error') {
                $report['overall_status'] = 'warning';
            }
            
            $report['summary']['total_issues'] += count($result['issues']);
            $report['summary']['total_warnings'] += count($result['warnings']);
        }
        
        return $report;
    }
}

// Usage example for AJAX endpoint
if (isset($_GET['action']) && $_GET['action'] === 'validate_settings') {
    header('Content-Type: application/json');
    
    try {
        $validator = new SettingsValidator();
        $report = $validator->generateReport();
        echo json_encode(['success' => true, 'data' => $report]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'test_email') {
    header('Content-Type: application/json');
    
    try {
        $validator = new SettingsValidator();
        $testEmail = $_GET['email'] ?? null;
        $result = $validator->testEmail($testEmail);
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>