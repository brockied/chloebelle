<?php
/**
 * Email Helper Class
 * Supports both PHP Mail (cPanel default) and SMTP
 */

class EmailHelper {
    private $settings = [];
    
    public function __construct() {
        $this->loadSettings();
    }
    
    private function loadSettings() {
        try {
            require_once __DIR__ . '/../config.php';
            
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );

            $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE category = 'email'");
            while ($row = $stmt->fetch()) {
                $this->settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            error_log("Email settings load error: " . $e->getMessage());
        }
    }
    
    private function getSetting($key, $default = '') {
        return $this->settings[$key] ?? $default;
    }
    
    /**
     * Send email using configured method
     */
    public function sendEmail($to, $subject, $body, $isHtml = true) {
        $method = $this->getSetting('email_method', 'php_mail');
        
        if ($method === 'smtp') {
            return $this->sendSMTPEmail($to, $subject, $body, $isHtml);
        } else {
            return $this->sendPHPMail($to, $subject, $body, $isHtml);
        }
    }
    
    /**
     * Send email using PHP's built-in mail() function (cPanel default)
     */
    private function sendPHPMail($to, $subject, $body, $isHtml = true) {
        try {
            $fromEmail = $this->getSetting('from_email', 'noreply@' . $_SERVER['HTTP_HOST']);
            $fromName = $this->getSetting('from_name', 'Chloe Belle');
            
            // Build headers
            $headers = [];
            $headers[] = "From: $fromName <$fromEmail>";
            $headers[] = "Reply-To: $fromEmail";
            $headers[] = "X-Mailer: PHP/" . phpversion();
            
            if ($isHtml) {
                $headers[] = "MIME-Version: 1.0";
                $headers[] = "Content-Type: text/html; charset=UTF-8";
            } else {
                $headers[] = "Content-Type: text/plain; charset=UTF-8";
            }
            
            $headerString = implode("\r\n", $headers);
            
            // Send email
            $result = mail($to, $subject, $body, $headerString);
            
            if (!$result) {
                error_log("PHP Mail failed to send email to: $to");
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("PHP Mail error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email using SMTP (requires PHPMailer or similar)
     * This is a simplified SMTP implementation
     */
    private function sendSMTPEmail($to, $subject, $body, $isHtml = true) {
        try {
            // Check if PHPMailer is available
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                // Fallback to simple SMTP socket connection
                return $this->sendSimpleSMTP($to, $subject, $body, $isHtml);
            }
            
            // Use PHPMailer if available
            return $this->sendPHPMailerSMTP($to, $subject, $body, $isHtml);
            
        } catch (Exception $e) {
            error_log("SMTP error: " . $e->getMessage());
            // Fallback to PHP mail
            return $this->sendPHPMail($to, $subject, $body, $isHtml);
        }
    }
    
    /**
     * Simple SMTP implementation using sockets
     */
    private function sendSimpleSMTP($to, $subject, $body, $isHtml = true) {
        $host = $this->getSetting('smtp_host');
        $port = $this->getSetting('smtp_port', 587);
        $username = $this->getSetting('smtp_username');
        $password = $this->getSetting('smtp_password');
        $encryption = $this->getSetting('smtp_encryption', 'tls');
        $fromEmail = $this->getSetting('from_email');
        $fromName = $this->getSetting('from_name', 'Chloe Belle');
        
        if (empty($host) || empty($username) || empty($password) || empty($fromEmail)) {
            error_log("SMTP settings incomplete, falling back to PHP mail");
            return $this->sendPHPMail($to, $subject, $body, $isHtml);
        }
        
        try {
            // Create socket connection
            $socket = fsockopen($host, $port, $errno, $errstr, 30);
            if (!$socket) {
                throw new Exception("Cannot connect to SMTP server: $errstr ($errno)");
            }
            
            // Read server response
            $response = fgets($socket, 512);
            if (substr($response, 0, 3) != '220') {
                throw new Exception("SMTP server not ready: $response");
            }
            
            // Send EHLO
            fputs($socket, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
            $response = fgets($socket, 512);
            
            // Start TLS if required
            if ($encryption === 'tls' || $encryption === 'ssl') {
                fputs($socket, "STARTTLS\r\n");
                $response = fgets($socket, 512);
                if (substr($response, 0, 3) != '220') {
                    throw new Exception("STARTTLS failed: $response");
                }
                
                // Enable crypto
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception("Failed to enable TLS encryption");
                }
                
                // Send EHLO again after TLS
                fputs($socket, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
                $response = fgets($socket, 512);
            }
            
            // Authenticate
            fputs($socket, "AUTH LOGIN\r\n");
            $response = fgets($socket, 512);
            if (substr($response, 0, 3) != '334') {
                throw new Exception("AUTH LOGIN failed: $response");
            }
            
            fputs($socket, base64_encode($username) . "\r\n");
            $response = fgets($socket, 512);
            if (substr($response, 0, 3) != '334') {
                throw new Exception("Username authentication failed: $response");
            }
            
            fputs($socket, base64_encode($password) . "\r\n");
            $response = fgets($socket, 512);
            if (substr($response, 0, 3) != '235') {
                throw new Exception("Password authentication failed: $response");
            }
            
            // Send email
            fputs($socket, "MAIL FROM: <$fromEmail>\r\n");
            $response = fgets($socket, 512);
            
            fputs($socket, "RCPT TO: <$to>\r\n");
            $response = fgets($socket, 512);
            
            fputs($socket, "DATA\r\n");
            $response = fgets($socket, 512);
            
            // Build email content
            $emailContent = "From: $fromName <$fromEmail>\r\n";
            $emailContent .= "To: $to\r\n";
            $emailContent .= "Subject: $subject\r\n";
            $emailContent .= "Date: " . date('r') . "\r\n";
            
            if ($isHtml) {
                $emailContent .= "MIME-Version: 1.0\r\n";
                $emailContent .= "Content-Type: text/html; charset=UTF-8\r\n";
            }
            
            $emailContent .= "\r\n";
            $emailContent .= $body;
            $emailContent .= "\r\n.\r\n";
            
            fputs($socket, $emailContent);
            $response = fgets($socket, 512);
            
            // Quit
            fputs($socket, "QUIT\r\n");
            fclose($socket);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Simple SMTP error: " . $e->getMessage());
            if (isset($socket) && $socket) {
                fclose($socket);
            }
            // Fallback to PHP mail
            return $this->sendPHPMail($to, $subject, $body, $isHtml);
        }
    }
    
    /**
     * Send welcome email to new user
     */
    public function sendWelcomeEmail($userEmail, $username) {
        if ($this->getSetting('welcome_email_enabled', '1') !== '1') {
            return true; // Feature disabled
        }
        
        $subject = "Welcome to " . $this->getSetting('from_name', 'Chloe Belle') . "!";
        
        $body = $this->getWelcomeEmailTemplate($username);
        
        return $this->sendEmail($userEmail, $subject, $body, true);
    }
    
    /**
     * Get welcome email template
     */
    private function getWelcomeEmailTemplate($username) {
        $siteName = $this->getSetting('from_name', 'Chloe Belle');
        $siteUrl = 'https://' . $_SERVER['HTTP_HOST'];
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to $siteName!</h1>
                    <p>Your exclusive access awaits</p>
                </div>
                <div class='content'>
                    <h2>Hi " . htmlspecialchars($username) . ",</h2>
                    <p>Thank you for joining our exclusive community! Your account has been successfully created.</p>
                    
                    <p>Here's what you can do now:</p>
                    <ul>
                        <li>Browse exclusive content</li>
                        <li>Interact with posts through likes and comments</li>
                        <li>Consider subscribing for premium access</li>
                    </ul>
                    
                    <p style='text-align: center;'>
                        <a href='$siteUrl' class='button'>Start Exploring</a>
                    </p>
                    
                    <p>If you have any questions, feel free to reach out to our support team.</p>
                    
                    <p>Best regards,<br>The $siteName Team</p>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " $siteName. All rights reserved.</p>
                    <p>You received this email because you created an account on our platform.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Test email configuration
     */
    public function testEmailConfig($testEmail = null) {
        $testEmail = $testEmail ?: $this->getSetting('from_email');
        
        if (empty($testEmail)) {
            return ['success' => false, 'message' => 'No test email address provided'];
        }
        
        $subject = "Email Configuration Test - " . date('Y-m-d H:i:s');
        $body = "
        <h2>Email Test Successful!</h2>
        <p>This is a test email to verify your email configuration is working correctly.</p>
        <p><strong>Method:</strong> " . $this->getSetting('email_method', 'php_mail') . "</p>
        <p><strong>Sent at:</strong> " . date('Y-m-d H:i:s') . "</p>
        <p>If you received this email, your configuration is working properly.</p>
        ";
        
        $result = $this->sendEmail($testEmail, $subject, $body, true);
        
        return [
            'success' => $result,
            'message' => $result ? 'Test email sent successfully!' : 'Failed to send test email. Check error logs for details.'
        ];
    }
}
?>