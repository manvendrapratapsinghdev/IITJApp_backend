#!/usr/bin/env php
<?php
/**
 * Send Reminder Emails Script
 * 
 * This script sends reminder emails to all email addresses listed in ids.md
 * using the template configured in config.php
 * 
 * Usage:
 *   php send_reminders.php          # Send emails
 *   php send_reminders.php --dry-run # Test mode (don't send)
 *   php send_reminders.php --help    # Show help
 */

require_once __DIR__ . '/EmailSender.php';

// Parse command line arguments
$dryRun = false;
$showHelp = false;

foreach ($argv as $arg) {
    if ($arg === '--dry-run' || $arg === '-d') {
        $dryRun = true;
    }
    if ($arg === '--help' || $arg === '-h') {
        $showHelp = true;
    }
}

// Show help
if ($showHelp) {
    echo <<<HELP
Send Reminder Emails Script

This script sends reminder emails to all addresses listed in email/ids.md
using the template configured in email/config.php

USAGE:
  php send_reminders.php [OPTIONS]

OPTIONS:
  --dry-run, -d    Run in test mode (show what would be sent without sending)
  --help, -h       Show this help message

CONFIGURATION:
  Edit email/config.php to customize:
    - Email subject
    - Email HTML body
    - Email text body (fallback)
    - Batch size and delays

REQUIREMENTS:
  - .env file with email credentials must be configured
  - email/ids.md must contain list of email addresses (one per line)

EXAMPLES:
  php send_reminders.php              # Send emails to all addresses
  php send_reminders.php --dry-run    # Preview what will be sent

HELP;
    exit(0);
}

// Main execution
try {
    $sender = new EmailSender();
    $results = $sender->sendReminders($dryRun);
    
    // Exit with appropriate code
    exit($results['failed'] > 0 ? 1 : 0);
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
