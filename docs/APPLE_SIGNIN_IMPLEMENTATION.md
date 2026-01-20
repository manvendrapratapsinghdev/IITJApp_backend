# Apple Sign-In Implementation

## Overview
This implementation adds Apple Sign-In authentication with email verification to the Campus App API. Users can authenticate using their Apple ID and must verify their university email address using a 6-digit OTP.

## Implementation Date
January 3, 2026

## New Files Created

### 1. Database Migration
- **File**: `database/migrations/002_apple_signin_support.sql`
- **Description**: Adds necessary database schema changes
- **Changes**:
  - Adds `apple_user_id` column to users table
  - Adds `email_verified` column to users table
  - Creates `email_verifications` table for OTP storage
  - Creates `otp_rate_limits` table for rate limiting

### 2. Email Service
- **File**: `src/Services/EmailService.php`
- **Description**: Handles sending verification emails with OTP
- **Features**:
  - Professional HTML email templates
  - Plain text fallback
  - Configurable from address
  - Ready for SendGrid/AWS SES integration

## Modified Files

### 1. User Model
- **File**: `src/Models/UserModel.php`
- **New Methods**:
  - `findByAppleId()` - Find user by Apple user ID
  - `findByEmailAndAppleId()` - Find user by both email and Apple ID
  - `createWithApple()` - Create new user with Apple Sign-In
  - `updateAppleId()` - Update Apple user ID
  - `updateEmailVerified()` - Update email verification status
  - `storeOTP()` - Store OTP for verification
  - `verifyOTP()` - Verify OTP code
  - `deleteOTP()` - Delete OTP after verification
  - `cleanupExpiredOTPs()` - Clean up expired OTPs
  - `checkRateLimit()` - Check rate limiting
  - `clearRateLimit()` - Clear rate limit after success
  - `cleanupOldRateLimits()` - Clean up old rate limit records

### 2. Auth Controller
- **File**: `src/Controllers/AuthController.php`
- **New Methods**:
  - `appleAuth()` - POST /api/auth/apple
  - `sendVerificationOTP()` - POST /api/send-verification-otp
  - `verifyEmailAppleSignin()` - POST /api/verify-email-apple-signin

### 3. Routes
- **File**: `public/api.php`
- **New Routes**:
  - `POST /api/auth/apple` - Initial Apple authentication
  - `POST /api/send-verification-otp` - Send OTP to email
  - `POST /api/verify-email-apple-signin` - Verify OTP and complete registration

## API Endpoints

### 1. Apple Authentication
**Endpoint**: `POST /api/auth/apple`

**Request Body**:
```json
{
  "apple_user_id": "001234.abc123def456.7890",
  "name": "John Doe",
  "email": "john.doe@university.edu",
  "device_token": "fK8xM9..."
}
```

**Response (Existing User - Email Verified)**:
```json
{
  "success": true,
  "message": "Login successful",
  "user": {
    "id": 123,
    "name": "John Doe",
    "email": "john.doe@university.edu",
    "role": "user",
    "email_verified": true,
    "auth_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    ...
  }
}
```

**Response (New User or Email Not Verified)**:
```json
{
  "success": true,
  "message": "Email verification required",
  "requires_email_verification": true,
  "user": {
    "apple_user_id": "001234.abc123def456.7890",
    "name": "John Doe",
    "email": null
  }
}
```

### 2. Send Verification OTP
**Endpoint**: `POST /api/send-verification-otp`

**Request Body**:
```json
{
  "email": "john.doe@iitj.ac.in"
}
```

**Response (Success)**:
```json
{
  "success": true,
  "message": "Verification code sent to john.doe@iitj.ac.in",
  "expires_in": 600
}
```

**Response (Rate Limited)**:
```json
{
  "success": false,
  "error": "Rate limit exceeded",
  "message": "Please wait 60 minutes before requesting another code"
}
```

### 3. Verify Email and Complete Registration
**Endpoint**: `POST /api/verify-email-apple-signin`

**Request Body**:
```json
{
  "email": "john.doe@iitj.ac.in",
  "otp": "123456",
  "apple_user_id": "001234.abc123def456.7890",
  "name": "John Doe",
  "device_token": "fK8xM9..."
}
```

**Response (Success)**:
```json
{
  "success": true,
  "message": "Email verified successfully",
  "user": {
    "id": 123,
    "name": "John Doe",
    "email": "john.doe@iitj.ac.in",
    "role": "user",
    "email_verified": true,
    "is_onboarding_done": false,
    "auth_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    ...
  }
}
```

**Response (Invalid OTP)**:
```json
{
  "success": false,
  "error": "Invalid verification code",
  "message": "Invalid verification code",
  "attempts_remaining": 2
}
```

## Security Features

### 1. Email Domain Validation
- Only allows `@iitj.ac.in` domain
- Enforces G24 prefix for MTech Jan-2025 batch
- Test accounts bypass validation

### 2. Rate Limiting
- Maximum 3 OTP requests per email per hour
- Prevents abuse and spam
- Automatically resets after 1 hour window

### 3. OTP Security
- 6-digit random code
- 10-minute expiration
- Maximum 3 verification attempts
- Auto-cleanup of expired OTPs

### 4. Account Security
- Checks for blocked users
- Restores deleted users with reset onboarding
- Validates Apple user ID uniqueness
- Prevents email hijacking

## Database Schema

### Users Table Updates
```sql
ALTER TABLE users 
ADD COLUMN apple_user_id VARCHAR(255) NULL UNIQUE,
ADD COLUMN email_verified BOOLEAN NOT NULL DEFAULT FALSE,
ADD INDEX idx_apple_user_id (apple_user_id);
```

### Email Verifications Table
```sql
CREATE TABLE email_verifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  otp VARCHAR(6) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  INDEX idx_email (email),
  INDEX idx_expires_at (expires_at)
);
```

### OTP Rate Limits Table
```sql
CREATE TABLE otp_rate_limits (
  email VARCHAR(255) PRIMARY KEY,
  request_count INT NOT NULL DEFAULT 1,
  window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_window_start (window_start)
);
```

## Installation Steps

### 1. Run Database Migration
```bash
mysql -u root -p campus_app < database/migrations/002_apple_signin_support.sql
```

### 2. Configure Email Service (Optional)
For production, update `src/Services/EmailService.php` to use SendGrid or AWS SES:

```php
// Add to config/config.php or .env
'email' => [
  'from' => 'noreply@aigyan.live',
  'from_name' => 'AIGyan Connect',
  'sendgrid_api_key' => 'your_sendgrid_key'
]
```

### 3. Test the Endpoints
Use the Postman collection in `docs/postman.json` or test manually:

```bash
# 1. Initial Apple Auth
curl -X POST http://localhost:8000/api/auth/apple \
  -H "Content-Type: application/json" \
  -d '{"apple_user_id":"test123","name":"Test User"}'

# 2. Send OTP
curl -X POST http://localhost:8000/api/send-verification-otp \
  -H "Content-Type: application/json" \
  -d '{"email":"g24test@iitj.ac.in"}'

# 3. Verify OTP
curl -X POST http://localhost:8000/api/verify-email-apple-signin \
  -H "Content-Type: application/json" \
  -d '{"email":"g24test@iitj.ac.in","otp":"123456","apple_user_id":"test123","name":"Test User"}'
```

## Email Template

The OTP verification email includes:
- Professional HTML design
- Large, clear OTP display
- 10-minute expiration notice
- Security warning
- AIGyan Connect branding

Preview the email by checking the logs after sending an OTP (development mode).

## Error Handling

### Common Errors
- **400 Bad Request**: Missing required fields, invalid format
- **403 Forbidden**: Account blocked
- **409 Conflict**: Email already registered to different account
- **429 Too Many Requests**: Rate limit exceeded
- **500 Internal Server Error**: Database or email service error

### Error Response Format
```json
{
  "success": false,
  "error": "Error code",
  "message": "Human-readable error message"
}
```

## Testing Checklist

- [x] Database migration runs successfully
- [x] Apple auth endpoint accepts valid requests
- [x] Email domain validation works
- [x] OTP generation and storage works
- [x] Email service sends OTP (check logs)
- [x] OTP verification works with correct code
- [x] OTP verification fails with wrong code
- [x] Rate limiting prevents abuse
- [x] User creation works for new users
- [x] User login works for existing users
- [x] Device token is stored correctly

## Future Enhancements

1. **Email Service Integration**
   - Integrate with SendGrid or AWS SES for production
   - Add email templates system
   - Track email delivery status

2. **Enhanced Security**
   - Add CAPTCHA for OTP requests
   - Implement IP-based rate limiting
   - Add 2FA option

3. **User Experience**
   - Add email verification reminder notifications
   - Support email change flow
   - Add resend OTP with cooldown

4. **Monitoring**
   - Add analytics for authentication flow
   - Track failed attempts and block suspicious IPs
   - Monitor email delivery rates

## Support

For issues or questions, contact the backend team.

## References
- Requirements: `docs/apple_signin_api_requirements.md`
- Postman Collection: `docs/postman.json`
