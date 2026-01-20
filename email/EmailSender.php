<?php
/**
 * EmailSender Class
 * 
 * Handles sending bulk reminder emails using the configured template
 */
class EmailSender {
    private string $fromEmail;
    private string $fromName;
    private array $config;
    private string $smtpHost;
    private int $smtpPort;
    private string $smtpSecure;
    private string $password;

    public function __construct() {
        $this->loadEnv();
        $this->loadConfig();
        
        // Load SMTP settings from .env
        $this->fromEmail = $_ENV['EMAIL_FROM'] ?? 'noreply@aigyan.live';
        $this->fromName = $_ENV['EMAIL_FROM_NAME'] ?? 'AIGyan Connect';
        $this->smtpHost = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $this->smtpPort = (int)($_ENV['SMTP_PORT'] ?? 587);
        $this->smtpSecure = $_ENV['SMTP_SECURE'] ?? 'tls';
        $this->password = str_replace(' ', '', $_ENV['EMAIL_PASSWORD'] ?? '');

        if (empty($this->password)) {
            throw new Exception("Email password not configured in .env file");
        }
    }

    /**
     * Load environment variables from .env file
     */
    private function loadEnv(): void {
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') === false) continue;
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value);
            }
        } else {
            throw new Exception(".env file not found at {$envFile}");
        }
    }

    /**
     * Load email configuration
     */
    private function loadConfig(): void {
        $configFile = __DIR__ . '/config.php';
        if (!file_exists($configFile)) {
            throw new Exception("Configuration file not found at {$configFile}");
        }
        $this->config = require $configFile;
    }

    /**
     * Read email addresses from ids.md file
     * @return array List of email addresses
     */
    public function readEmailList(): array {
        $idsFile = __DIR__ . '/ids.md';
        if (!file_exists($idsFile)) {
            throw new Exception("Email list file not found at {$idsFile}");
        }

        $emails = [];
        $lines = file($idsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $email = trim($line);
            // Validate email format
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                // Remove duplicates
                if (!in_array($email, $emails)) {
                    $emails[] = $email;
                }
            }
        }

        return $emails;
    }

    /**
     * Send reminder emails to all addresses in the list
     * @param bool $dryRun If true, just print what would be sent without actually sending
     * @return array Results of email sending
     */
    public function sendReminders(bool $dryRun = false): array {
        $emails = $this->readEmailList();
        $results = [
            'total' => count($emails),
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];

        echo "=== Email Reminder Sender ===\n";
        echo "Total emails to send: " . $results['total'] . "\n";
        echo "Batch size: " . $this->config['batch_size'] . "\n";
        echo "Mode: " . ($dryRun ? "DRY RUN (no emails will be sent)" : "LIVE") . "\n";
        echo "\n";

        if ($dryRun) {
            echo "Subject: " . $this->config['subject'] . "\n";
            echo "\nFirst 5 recipients:\n";
            foreach (array_slice($emails, 0, 5) as $email) {
                echo "  - {$email}\n";
            }
            echo "\n";
            return $results;
        }

        $batchCount = 0;
        $batchNumber = 1;

        foreach ($emails as $index => $email) {
            echo "[" . ($index + 1) . "/{$results['total']}] Sending to {$email}... ";

            try {
                $success = $this->sendEmail($email);
                
                if ($success) {
                    $results['sent']++;
                    echo "✓ SUCCESS\n";
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to send to {$email}";
                    echo "✗ FAILED\n";
                }
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = "{$email}: " . $e->getMessage();
                echo "✗ ERROR: " . $e->getMessage() . "\n";
            }

            $batchCount++;

            // Delay between individual emails
            if ($this->config['delay_between_emails'] > 0) {
                usleep($this->config['delay_between_emails'] * 1000000);
            }

            // Delay between batches
            if ($batchCount >= $this->config['batch_size'] && $index < count($emails) - 1) {
                echo "\nBatch {$batchNumber} completed. Waiting " . $this->config['delay_between_batches'] . " seconds...\n\n";
                sleep($this->config['delay_between_batches']);
                $batchCount = 0;
                $batchNumber++;
            }
        }

        echo "\n=== Summary ===\n";
        echo "Total: {$results['total']}\n";
        echo "Sent: {$results['sent']}\n";
        echo "Failed: {$results['failed']}\n";

        if (!empty($results['errors'])) {
            echo "\nErrors:\n";
            foreach ($results['errors'] as $error) {
                echo "  - {$error}\n";
            }
        }

        return $results;
    }

    /**
     * Send a single email using SMTP
     * @param string $to Recipient email address
     * @return bool Success status
     */
    private function sendEmail(string $to): bool {
        $subject = $this->config['subject'];
        $htmlBody = $this->config['html_body'];
        $textBody = $this->config['text_body'];

        try {
            // Create SMTP connection
            $socket = @fsockopen($this->smtpHost, $this->smtpPort, $errno, $errstr, 30);
            
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
                return $response;
            };

            // Helper function to send SMTP command
            $sendCommand = function($command) use ($socket, $readResponse) {
                fputs($socket, $command . "\r\n");
                return $readResponse();
            };

            // Read server greeting
            $readResponse();

            // EHLO
            $sendCommand("EHLO " . $this->smtpHost);

            // Start TLS if configured
            if ($this->smtpSecure === 'tls') {
                $sendCommand("STARTTLS");
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $sendCommand("EHLO " . $this->smtpHost);
            }

            // Authenticate
            $sendCommand("AUTH LOGIN");
            $sendCommand(base64_encode($this->fromEmail));
            $authResponse = $sendCommand(base64_encode($this->password));
            
            if (strpos($authResponse, '235') === false) {
                fclose($socket);
                error_log("SMTP authentication failed");
                return false;
            }

            // Send email
            $sendCommand("MAIL FROM:<{$this->fromEmail}>");
            $sendCommand("RCPT TO:<{$to}>");
            $sendCommand("DATA");

            // Build email headers and body
            $boundary = md5(time());
            $headers = [
                "From: {$this->fromName} <{$this->fromEmail}>",
                "To: <{$to}>",
                "Subject: {$subject}",
                "MIME-Version: 1.0",
                "Content-Type: multipart/alternative; boundary=\"{$boundary}\""
            ];

            $body = implode("\r\n", $headers) . "\r\n\r\n";
            
            // Plain text part
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $body .= trim($textBody) . "\r\n\r\n";
            
            // HTML part
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $body .= trim($htmlBody) . "\r\n\r\n";
            
            $body .= "--{$boundary}--\r\n";

            fputs($socket, $body);
            $sendCommand(".");
            $sendCommand("QUIT");

            fclose($socket);
            return true;

        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            if (isset($socket) && is_resource($socket)) {
                fclose($socket);
            }
            return false;
        }
    }
}
