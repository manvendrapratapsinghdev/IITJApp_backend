<?php
/**
 * Email Reminder Configuration
 * 
 * Configure the subject and content for reminder emails.
 * Use {name} placeholder to personalize the email.
 */

return [
    // Email subject - can include placeholders
    'subject' => 'Reminder: M.tech Project Sheet',
    
    // Email HTML body - supports placeholders and HTML
    'html_body' => '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reminder</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 20px;
        }
        .email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }
        .header::after {
            content: "";
            position: absolute;
            bottom: -20px;
            left: 0;
            right: 0;
            height: 40px;
            background-color: #ffffff;
            border-radius: 50% 50% 0 0 / 20px 20px 0 0;
        }
        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header-icon {
            width: 60px;
            height: 60px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 30px;
            line-height: 60px;
            text-align: center;
        }
        .content {
            padding: 50px 40px 40px;
            background-color: #ffffff;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 20px;
        }
        .message {
            font-size: 16px;
            color: #4a4a4a;
            line-height: 1.8;
            margin-bottom: 20px;
        }
        .highlight-box {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 25px 0;
            border-radius: 8px;
        }
        .highlight-box p {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .button {
            display: inline-block;
            padding: 14px 35px;
            background: linear-gradient(135deg, #cdd4f0 0%, #c6a6e6 100%);
            color: #ffffff;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: transform 0.2s;
        }
        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }
        .signature {
            margin-top: 35px;
            padding-top: 25px;
            border-top: 2px solid #e2e8f0;
        }
        .signature p {
            margin: 5px 0;
            color: #4a5568;
        }
        .team-name {
            font-weight: 700;
            color: #667eea;
            font-size: 17px;
        }
        .footer {
            background: linear-gradient(to bottom, #f7fafc, #edf2f7);
            padding: 30px 40px;
            text-align: center;
        }
        .footer-divider {
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            margin: 0 auto 20px;
            border-radius: 2px;
        }
        .footer p {
            font-size: 13px;
            color: #718096;
            margin: 8px 0;
            line-height: 1.6;
        }
        .footer-logo {
            font-size: 20px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }
        .social-links {
            margin-top: 20px;
        }
        .social-links a {
            display: inline-block;
            margin: 0 8px;
            width: 36px;
            height: 36px;
            background-color: #e2e8f0;
            border-radius: 50%;
            line-height: 36px;
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }
        @media only screen and (max-width: 600px) {
            body {
                padding: 20px 10px;
            }
            .content {
                padding: 40px 25px 30px;
            }
            .header h1 {
                font-size: 24px;
            }
            .footer {
                padding: 25px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="header">
            <div class="header-icon">🔔</div>
            <h1>Reminder</h1>
        </div>
        
        <div class="content">
            <p class="greeting">Dear Friends,</p>
            
            <p class="message">
                We hope this message finds you well. This is a friendly reminder regarding an important matter that requires your attention.
            </p>
            
            <div class="highlight-box">
                <p>⚡ Please fill the project detail in the Sheet as soon as possible.</p>
            </div>
            
           
            
            <div class="button-container">
                <a href="https://docs.google.com/spreadsheets/d/1XOkTmkqhMAUzdr0Uxvc9J_7K3ByFtswPXPATuyVgW5U/edit?gid=1434055535#gid=1434055535" class="button" target="_blank">Open Sheet</a>
            </div>
             <p class="message">
                Please ignore this email if you have already addressed this matter.
            </p>
            <div class="signature">
                <p>Best regards,</p>
                <p class="team-name">The AIGyan Connect Team</p>
                <p style="font-size: 14px; color: #718096;">This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>       
    </div>
</body>
</html>
    ',
    
    // Email plain text body - fallback for email clients that don't support HTML
    'text_body' => '
Dear Student,

This is a friendly reminder regarding an important action that requires your attention.

Please take the necessary action at your earliest convenience.

If you have any questions or concerns, please don\'t hesitate to reach out to us.

Best regards,
The AIGyan Team

---
This is an automated message. Please do not reply to this email.
© 2026 AIGyan Connect. All rights reserved.
    ',
    
    // Email sending configuration
    'batch_size' => 10,  // Number of emails to send in each batch
    'delay_between_batches' => 2,  // Seconds to wait between batches
    'delay_between_emails' => 0.5,  // Seconds to wait between individual emails
];
