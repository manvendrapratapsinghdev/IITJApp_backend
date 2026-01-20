<?php
namespace Services;

/**
 * EmailService - Handles sending emails including OTP verification
 * 
 * This service provides email functionality for the application.
 * Currently uses PHP's mail() function for development.
 * For production, integrate with SendGrid, AWS SES, or similar service.
 */
class EmailService {
  private string $fromEmail;
  private string $fromName;

  public function __construct() {
    // Load .env file
    $this->loadEnv();
    
    // Load configuration from environment or config
    $this->fromEmail = $_ENV['EMAIL_FROM'] ?? 'noreply@aigyan.live';
    $this->fromName = $_ENV['EMAIL_FROM_NAME'] ?? 'AIGyan Connect';
  }

  /**
   * Load environment variables from .env file
   */
  private function loadEnv(): void {
    $envFile = __DIR__ . '/../../.env';
    if (file_exists($envFile)) {
      $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
      }
    }
  }

  /**
   * Send OTP verification email
   * @param string $to Recipient email address
   * @param string $otp 6-digit OTP code
   * @return bool Success status
   */
  public function sendOTP(string $to, string $otp): bool {
    $subject = 'Verify Your University Email - AIGyan Connect';
    $htmlBody = $this->getOTPEmailTemplate($otp);
    $textBody = $this->getOTPEmailTextTemplate($otp);

    return $this->sendEmail($to, $subject, $htmlBody, $textBody);
  }

  /**
   * Send email using available email service
   * @param string $to Recipient email
   * @param string $subject Email subject
   * @param string $htmlBody HTML email body
   * @param string $textBody Plain text email body (fallback)
   * @return bool Success status
   */
  private function sendEmail(string $to, string $subject, string $htmlBody, string $textBody = ''): bool {
    // Use SMTP with Gmail
    try {
      $smtpHost = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
      $smtpPort = $_ENV['SMTP_PORT'] ?? 587;
      $smtpSecure = $_ENV['SMTP_SECURE'] ?? 'tls';
      $password = $_ENV['EMAIL_PASSWORD'] ?? '';
      
      // Remove spaces from password (app passwords shouldn't have spaces)
      $password = str_replace(' ', '', $password);

      if (empty($password)) {
        error_log("Email password not configured in .env");
        return false;
      }

      // Log the email for debugging
      error_log("Sending email to: {$to}");
      error_log("Subject: {$subject}");
      error_log("From: {$this->fromEmail}");
      error_log("OTP Code: " . $this->extractOTPFromHTML($htmlBody));

      // Create SMTP connection
      $socket = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 30);
      
      if (!$socket) {
        error_log("Failed to connect to SMTP server: {$errstr} ({$errno})");
        return false;
      }

      // Helper function to read SMTP response
      $readResponse = function() use ($socket) {
        $response = '';
        while ($line = fgets($socket, 515)) {
          $response .= $line;
          if (substr($line, 3, 1) == ' ') break;
        }
        error_log("SMTP Response: " . trim($response));
        return $response;
      };

      // Helper function to send SMTP command
      $sendCommand = function($command, $hideInLog = false) use ($socket, $readResponse) {
        if (!$hideInLog) {
          error_log("SMTP Command: {$command}");
        }
        fwrite($socket, $command . "\r\n");
        return $readResponse();
      };

      // SMTP conversation
      $readResponse(); // Initial greeting
      $sendCommand("EHLO {$smtpHost}");
      $sendCommand("STARTTLS");
      
      // Upgrade to TLS
      if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        error_log("Failed to enable TLS encryption");
        fclose($socket);
        return false;
      }
      
      $sendCommand("EHLO {$smtpHost}");
      $sendCommand("AUTH LOGIN");
      $sendCommand(base64_encode($this->fromEmail));
      $authResponse = $sendCommand(base64_encode($password), true);
      
      if (strpos($authResponse, '235') === false) {
        error_log("SMTP authentication failed: {$authResponse}");
        fclose($socket);
        return false;
      }

      $sendCommand("MAIL FROM:<{$this->fromEmail}>");
      $sendCommand("RCPT TO:<{$to}>");
      $sendCommand("DATA");
      
      // Prepare email headers and body
      $emailContent = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
      $emailContent .= "To: <{$to}>\r\n";
      $emailContent .= "Subject: {$subject}\r\n";
      $emailContent .= "MIME-Version: 1.0\r\n";
      $emailContent .= "Content-Type: text/html; charset=UTF-8\r\n";
      $emailContent .= "\r\n";
      $emailContent .= $htmlBody;
      $emailContent .= "\r\n.\r\n";
      
      fwrite($socket, $emailContent);
      $dataResponse = $readResponse();
      
      $sendCommand("QUIT");
      fclose($socket);

      if (strpos($dataResponse, '250') !== false) {
        error_log("Email sent successfully to: {$to}");
        return true;
      } else {
        error_log("Failed to send email: {$dataResponse}");
        return false;
      }

    } catch (\Exception $e) {
      error_log("Email sending error: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Extract OTP from HTML for logging purposes
   * @param string $html HTML content
   * @return string OTP code or empty string
   */
  private function extractOTPFromHTML(string $html): string {
    preg_match('/<div class="code">(\d{6})<\/div>/', $html, $matches);
    return $matches[1] ?? '';
  }

  /**
   * Get HTML template for OTP email
   * @param string $otp 6-digit OTP code
   * @return string HTML email template
   */
  private function getOTPEmailTemplate(string $otp): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <style>
    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
    .header { text-align: center; padding: 20px 0; background-color: #007bff; color: white; border-radius: 8px 8px 0 0; }
    .header h1 { margin: 0; font-size: 28px; }
    .content { padding: 30px 20px; background-color: #f9f9f9; }
    .code-box { background: #ffffff; border: 2px solid #007bff; border-radius: 8px; 
                padding: 20px; text-align: center; margin: 20px 0; }
    .code { font-size: 36px; font-weight: bold; color: #007bff; letter-spacing: 8px; font-family: 'Courier New', monospace; }
    .footer { text-align: center; color: #666; font-size: 12px; margin-top: 30px; padding: 20px; background-color: #f0f0f0; border-radius: 0 0 8px 8px; }
    .warning { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 20px 0; }
    p { margin: 10px 0; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>AIGyan Connect</h1>
      <p style="margin: 5px 0; font-size: 16px;">Verify Your University Email</p>
    </div>
    
    <div class="content">
      <p>Hi there,</p>
      
      <p>You requested to verify your university email for AIGyan Connect. 
         Use the verification code below to complete your registration:</p>
      
      <div class="code-box">
        <div class="code">{$otp}</div>
      </div>
      
      <p style="text-align: center;"><strong>This code will expire in 10 minutes.</strong></p>
      
      <div class="warning">
        <strong>⚠️ Security Notice:</strong> If you didn't request this code, please ignore this email 
        or contact support if you have concerns about your account security.
      </div>
    </div>
    
    <div class="footer">
      <p><strong>AIGyan Connect</strong></p>
      <p>Network & Collaboration Platform for IIT Jodhpur</p>
      <p>This is an automated email. Please do not reply to this message.</p>
    </div>
  </div>
</body>
</html>
HTML;
  }

  /**
   * Get plain text template for OTP email (fallback)
   * @param string $otp 6-digit OTP code
   * @return string Plain text email template
   */
  private function getOTPEmailTextTemplate(string $otp): string {
    return <<<TEXT
AIGyan Connect - Verify Your University Email

Hi there,

You requested to verify your university email for AIGyan Connect. 
Use the verification code below to complete your registration:

Verification Code: {$otp}

This code will expire in 10 minutes.

If you didn't request this code, please ignore this email or contact support 
if you have concerns about your account security.

---
AIGyan Connect
Network & Collaboration Platform for IIT Jodhpur
This is an automated email. Please do not reply to this message.
TEXT;
  }

  /**
   * Send welcome email (optional, for future use)
   * @param string $to Recipient email
   * @param string $name User's name
   * @return bool Success status
   */
  public function sendWelcomeEmail(string $to, string $name): bool {
    $subject = 'Welcome to AIGyan Connect!';
    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <style>
    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
    .header { text-align: center; padding: 20px 0; background-color: #007bff; color: white; border-radius: 8px 8px 0 0; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>Welcome to AIGyan Connect!</h1>
    </div>
    <div style="padding: 30px 20px; background-color: #f9f9f9;">
      <p>Hi {$name},</p>
      <p>Welcome to AIGyan Connect! We're excited to have you join our community.</p>
      <p>Get started by completing your profile and connecting with your peers.</p>
    </div>
  </div>
</body>
</html>
HTML;

    return $this->sendEmail($to, $subject, $htmlBody);
  }
}
