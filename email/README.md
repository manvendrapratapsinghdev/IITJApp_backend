# Email Reminder System

This folder contains the email reminder functionality for sending bulk emails to students.

## Files

- **`ids.md`** - List of email addresses (one per line)
- **`config.php`** - Email template configuration (subject, HTML body, text body, sending options)
- **`EmailSender.php`** - Class that handles email sending logic
- **`send_reminders.php`** - Script to execute email sending

## Setup

1. Ensure your `.env` file is configured with email credentials:
   ```env
   EMAIL_FROM=your-email@gmail.com
   EMAIL_FROM_NAME=AIGyan Connect
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_SECURE=tls
   EMAIL_PASSWORD=your-app-password
   ```

2. Edit `config.php` to customize your reminder email:
   - Subject line
   - HTML body (supports HTML styling)
   - Plain text body (fallback)
   - Batch settings

3. Ensure `ids.md` contains the email addresses you want to send to (one per line)

## Usage

### Test Mode (Recommended First)
Run in dry-run mode to preview what will be sent without actually sending emails:
```bash
php send_reminders.php --dry-run
```

### Send Emails
Send reminder emails to all addresses in ids.md:
```bash
php send_reminders.php
```

### Get Help
View all available options:
```bash
php send_reminders.php --help
```

## Configuration Options

Edit `config.php` to customize:

```php
return [
    'subject' => 'Your Subject Here',
    'html_body' => '...',  // HTML email content
    'text_body' => '...',   // Plain text fallback
    'batch_size' => 10,     // Emails per batch
    'delay_between_batches' => 2,   // Seconds between batches
    'delay_between_emails' => 0.5,  // Seconds between emails
];
```

## Features

- ✅ Configurable subject and content
- ✅ HTML email support with CSS styling
- ✅ Plain text fallback for compatibility
- ✅ Batch sending to avoid rate limits
- ✅ Duplicate email removal
- ✅ Email validation
- ✅ Progress tracking
- ✅ Error reporting
- ✅ Dry-run mode for testing
- ✅ SMTP authentication with Gmail

## Email Template Customization

The default template in `config.php` includes:
- Professional header with styling
- Customizable content area
- Call-to-action button support
- Footer with branding
- Responsive design

Feel free to modify the HTML in `html_body` to match your needs.

## Troubleshooting

### "Email password not configured"
- Check that your `.env` file exists and contains `EMAIL_PASSWORD`
- For Gmail, use an App Password, not your regular password

### Emails not sending
- Verify SMTP credentials in `.env`
- Check that port 587 is not blocked
- Try dry-run mode first to test configuration

### Rate limiting
- Adjust `batch_size` and `delay_between_batches` in config.php
- Gmail has sending limits (typically 500/day for free accounts)

## Examples

### Example 1: Simple Reminder
```php
// In config.php
'subject' => 'Reminder: Submit Your Assignment',
'html_body' => '<h1>Don\'t forget!</h1><p>Your assignment is due tomorrow.</p>',
```

### Example 2: Event Notification
```php
'subject' => 'Upcoming Workshop Tomorrow',
'html_body' => '<p>Join us tomorrow at 3 PM for the AI workshop.</p>',
```

## Security Notes

- Never commit `.env` file to version control
- Use app passwords for Gmail, not your main password
- Keep email credentials secure
- Validate all email addresses before sending
