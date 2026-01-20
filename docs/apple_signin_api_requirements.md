# Apple Sign-In API Requirements

## Overview
This document outlines the API endpoints required to support Apple Sign-In with email verification in the mobile app. These endpoints handle authentication, email verification via OTP, and user registration/login flow.

---

## 1. Apple Authentication Endpoint

### **POST** `/api/auth/apple`

Authenticates a user using their Apple user ID. If the user exists and has a verified email, return their profile. If new or email not verified, create a temporary account pending email verification.

### Request

#### Headers
```json
{
  "Content-Type": "application/json"
}
```

#### Body Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `apple_user_id` | string | Yes | Unique identifier from Apple Sign-In |
| `name` | string | Yes | User's display name (default: "Apple User" if not provided by Apple) |
| `email` | string | No | User's email if provided by Apple (may be null or relay email) |
| `device_token` | string | No | FCM device token for push notifications |

#### Example Request
```json
{
  "apple_user_id": "001234.abc123def456.7890",
  "name": "John Doe",
  "email": "john.doe@university.edu",
  "device_token": "fK8xM9..."
}
```

### Response

#### Success Response (200 OK) - Existing User with Verified Email
```json
{
  "success": true,
  "message": "Login successful",
  "user": {
    "id": 123,
    "user_id": "USER123",
    "name": "John Doe",
    "email": "john.doe@university.edu",
    "role": "student",
    "profile_photo": "https://...",
    "is_onboarding_done": true,
    "email_verified": true,
    "auth_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "device_token": "fK8xM9..."
  }
}
```

#### Success Response (200 OK) - New User or Email Not Verified
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

#### Error Response (400 Bad Request)
```json
{
  "success": false,
  "error": "apple_user_id is required",
  "message": "Missing required fields"
}
```

#### Error Response (500 Internal Server Error)
```json
{
  "success": false,
  "error": "Database connection failed",
  "message": "An error occurred during authentication"
}
```

### Business Logic

1. **Validate Required Fields:**
   - `apple_user_id` must be present
   - `name` must be present (default to "Apple User")

2. **Check Existing User:**
   - Query database for user with matching `apple_user_id`
   - If found AND `email_verified = true`:
     - Update `device_token` if provided
     - Update last login timestamp
     - Generate new `auth_token` (JWT)
     - Return user profile with success=true

3. **Handle New/Unverified User:**
   - If user not found OR `email_verified = false`:
     - Check if email is provided and valid
     - If email provided and matches university domain:
       - Create/update temporary user record
       - Set `email_verified = false`
       - Return success with `requires_email_verification = true`
     - If no email or relay email:
       - Return success with `requires_email_verification = true`

4. **Domain Validation:**
   - If email provided, validate it matches approved domain patterns
   - Supported domains: `@youruniversity.edu`, `@university.ac.in`, etc.
   - Store domain patterns in configuration

---

## 2. Send Email Verification OTP

### **POST** `/api/send-verification-otp`

Sends a 6-digit OTP to the provided university email address for verification.

### Request

#### Headers
```json
{
  "Content-Type": "application/json"
}
```

#### Body Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `email` | string | Yes | University email address to verify |

#### Example Request
```json
{
  "email": "john.doe@university.edu"
}
```

### Response

#### Success Response (200 OK)
```json
{
  "success": true,
  "message": "Verification code sent to john.doe@university.edu",
  "expires_in": 600
}
```

#### Error Response (400 Bad Request) - Invalid Email
```json
{
  "success": false,
  "error": "Invalid email domain",
  "message": "Only @university.edu emails are allowed"
}
```

#### Error Response (429 Too Many Requests)
```json
{
  "success": false,
  "error": "Rate limit exceeded",
  "message": "Please wait 60 seconds before requesting another code"
}
```

#### Error Response (500 Internal Server Error)
```json
{
  "success": false,
  "error": "Failed to send email",
  "message": "An error occurred while sending verification code"
}
```

### Business Logic

1. **Validate Email:**
   - Email format validation (RFC 5322)
   - Check if email domain matches approved university domains
   - Reject if domain not in whitelist

2. **Check Rate Limiting:**
   - Allow max 3 OTP requests per email per hour
   - Return 429 error if limit exceeded
   - Track attempts in Redis/cache with TTL

3. **Generate OTP:**
   - Generate random 6-digit code
   - Store in database/cache with email as key
   - Set expiration: 10 minutes
   - Store: `{ email, otp, created_at, attempts: 0 }`

4. **Send Email:**
   - Use email service (SendGrid, AWS SES, etc.)
   - Email subject: "Verify Your University Email - AIGyan Connect"
   - Email body template:
     ```
     Your verification code is: 123456
     
     This code will expire in 10 minutes.
     
     If you didn't request this code, please ignore this email.
     ```

5. **Log Activity:**
   - Log OTP generation for audit trail
   - Include: timestamp, email, IP address, success/failure

---

## 3. Verify Email and Complete Apple Sign-In

### **POST** `/api/verify-email-apple-signin`

Verifies the OTP code and completes the Apple Sign-In registration by linking the verified email to the Apple user ID.

### Request

#### Headers
```json
{
  "Content-Type": "application/json"
}
```

#### Body Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `email` | string | Yes | University email address being verified |
| `otp` | string | Yes | 6-digit verification code |
| `apple_user_id` | string | Yes | Apple user ID from initial sign-in |
| `name` | string | Yes | User's display name |
| `device_token` | string | No | FCM device token for push notifications |

#### Example Request
```json
{
  "email": "john.doe@university.edu",
  "otp": "123456",
  "apple_user_id": "001234.abc123def456.7890",
  "name": "John Doe",
  "device_token": "fK8xM9..."
}
```

### Response

#### Success Response (200 OK) - New User Registration
```json
{
  "success": true,
  "message": "Email verified successfully",
  "user": {
    "id": 123,
    "user_id": "USER123",
    "name": "John Doe",
    "email": "john.doe@university.edu",
    "role": "student",
    "profile_photo": null,
    "is_onboarding_done": false,
    "email_verified": true,
    "auth_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "device_token": "fK8xM9...",
    "created_at": "2026-01-03T10:30:00Z"
  }
}
```

#### Success Response (200 OK) - Existing User Login
```json
{
  "success": true,
  "message": "Email verified successfully",
  "user": {
    "id": 123,
    "user_id": "USER123",
    "name": "John Doe",
    "email": "john.doe@university.edu",
    "role": "student",
    "profile_photo": "https://...",
    "is_onboarding_done": true,
    "email_verified": true,
    "auth_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "device_token": "fK8xM9..."
  }
}
```

#### Error Response (400 Bad Request) - Invalid OTP
```json
{
  "success": false,
  "error": "Invalid verification code",
  "message": "The code you entered is incorrect",
  "attempts_remaining": 2
}
```

#### Error Response (400 Bad Request) - Expired OTP
```json
{
  "success": false,
  "error": "Verification code expired",
  "message": "Please request a new code"
}
```

#### Error Response (409 Conflict) - Email Already Registered
```json
{
  "success": false,
  "error": "Email already registered",
  "message": "This email is already associated with another account"
}
```

#### Error Response (429 Too Many Requests) - Max Attempts
```json
{
  "success": false,
  "error": "Too many failed attempts",
  "message": "Please request a new verification code"
}
```

### Business Logic

1. **Validate Input:**
   - All required fields present
   - Email format valid
   - OTP is 6 digits
   - `apple_user_id` is valid format

2. **Verify OTP:**
   - Retrieve stored OTP for email from cache/database
   - Check if OTP exists and not expired (10 minutes)
   - Compare provided OTP with stored OTP
   - Track failed attempts (max 3 attempts)
   - If OTP invalid:
     - Increment attempt counter
     - Return error with attempts remaining
     - If max attempts reached, invalidate OTP

3. **Check Email Availability:**
   - Query if email already exists in users table
   - If email exists with different `apple_user_id`:
     - Return 409 Conflict error
   - If email exists with same `apple_user_id`:
     - Treat as login, update session

4. **Create/Update User:**
   - **New User:**
     - Generate unique `user_id`
     - Create user record with:
       - `apple_user_id`
       - `email`
       - `name`
       - `email_verified = true`
       - `is_onboarding_done = false`
       - `role = student` (default)
       - `created_at = NOW()`
     - Generate JWT `auth_token` (valid 30 days)
     - Store `device_token` if provided
   
   - **Existing User:**
     - Update user record:
       - `email_verified = true`
       - `last_login = NOW()`
       - Update `device_token` if provided
     - Generate new JWT `auth_token`

5. **Clean Up:**
   - Delete OTP from cache/database after successful verification
   - Clear any rate limit counters for this email

6. **Return User Profile:**
   - Include all user details
   - Include `auth_token` for session
   - Include `is_onboarding_done` to determine next screen

---

## Database Schema Requirements

### Users Table
```sql
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id VARCHAR(50) UNIQUE NOT NULL,
  apple_user_id VARCHAR(255) UNIQUE NULL,
  google_id VARCHAR(255) UNIQUE NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  name VARCHAR(255) NOT NULL,
  role ENUM('student', 'admin', 'faculty') DEFAULT 'student',
  profile_photo VARCHAR(500) NULL,
  email_verified BOOLEAN DEFAULT false,
  is_onboarding_done BOOLEAN DEFAULT false,
  device_token VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_login TIMESTAMP NULL,
  INDEX idx_apple_user_id (apple_user_id),
  INDEX idx_email (email),
  INDEX idx_user_id (user_id)
);
```

### OTP Verification Table (or Redis Cache)
```sql
CREATE TABLE email_verifications (
  id INT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL,
  otp VARCHAR(6) NOT NULL,
  attempts INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  INDEX idx_email (email),
  INDEX idx_expires_at (expires_at)
);
```

### Rate Limiting Table (or Redis Cache)
```sql
CREATE TABLE otp_rate_limits (
  email VARCHAR(255) PRIMARY KEY,
  request_count INT DEFAULT 1,
  window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_window_start (window_start)
);
```

---

## Security Considerations

1. **OTP Security:**
   - Use cryptographically secure random number generator
   - Never log OTP values in plain text
   - Implement automatic cleanup of expired OTPs
   - Consider using time-based OTP (TOTP) for enhanced security

2. **Rate Limiting:**
   - Implement per-IP rate limiting (max 10 requests/hour)
   - Implement per-email rate limiting (max 3 OTP requests/hour)
   - Use Redis for efficient rate limit tracking

3. **JWT Tokens:**
   - Use strong secret key (min 256 bits)
   - Include expiration claim (exp)
   - Include user_id and role in payload
   - Rotate tokens periodically
   - Implement token refresh mechanism

4. **Email Domain Validation:**
   - Maintain whitelist of approved university domains
   - Validate MX records to ensure domain exists
   - Consider verifying university affiliation via API if available

5. **Input Validation:**
   - Sanitize all inputs to prevent SQL injection
   - Validate email format strictly
   - Limit request body size
   - Implement CSRF protection

6. **Logging & Monitoring:**
   - Log all authentication attempts
   - Monitor failed login attempts
   - Alert on suspicious patterns (multiple failed OTPs)
   - Track IP addresses for security audits

---

## Testing Requirements

### Unit Tests
- Validate email domain checking logic
- Test OTP generation and expiration
- Test rate limiting logic
- Test JWT token generation and validation

### Integration Tests
- Test complete Apple Sign-In flow
- Test email verification flow
- Test error handling for all endpoints
- Test concurrent requests and race conditions

### Load Tests
- Test OTP generation under load
- Test database performance with concurrent verifications
- Test rate limiting effectiveness

---

## Configuration

### Environment Variables
```bash
# Email Service
EMAIL_SERVICE_API_KEY=your_sendgrid_api_key
EMAIL_FROM=noreply@aigyan.live
EMAIL_FROM_NAME=AIGyan Connect

# JWT
JWT_SECRET=your_256_bit_secret_key_here
JWT_EXPIRATION=30d

# OTP
OTP_EXPIRATION_MINUTES=10
OTP_MAX_ATTEMPTS=3
OTP_RATE_LIMIT_HOURLY=3

# University Domains (comma-separated)
ALLOWED_EMAIL_DOMAINS=@university.edu,@university.ac.in,@college.edu

# Redis (for caching)
REDIS_URL=redis://localhost:6379
REDIS_PASSWORD=your_redis_password
```

---

## Example Email Template

### Subject
```
Verify Your University Email - AIGyan Connect
```

### HTML Body
```html
<!DOCTYPE html>
<html>
<head>
  <style>
    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
    .header { text-align: center; padding: 20px 0; }
    .code-box { background: #f4f4f4; border: 2px solid #007bff; border-radius: 8px; 
                padding: 20px; text-align: center; margin: 20px 0; }
    .code { font-size: 32px; font-weight: bold; color: #007bff; letter-spacing: 5px; }
    .footer { text-align: center; color: #666; font-size: 12px; margin-top: 30px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>AIGyan Connect</h1>
      <p>Verify Your University Email</p>
    </div>
    
    <p>Hi there,</p>
    
    <p>You requested to verify your university email for AIGyan Connect. 
       Use the verification code below:</p>
    
    <div class="code-box">
      <div class="code">{{OTP_CODE}}</div>
    </div>
    
    <p><strong>This code will expire in 10 minutes.</strong></p>
    
    <p>If you didn't request this code, please ignore this email or contact support 
       if you have concerns.</p>
    
    <div class="footer">
      <p>AIGyan Connect - Network & Collaboration Platform</p>
      <p>This is an automated email. Please do not reply.</p>
    </div>
  </div>
</body>
</html>
```

---

## API Error Codes Summary

| Status Code | Error | Description |
|-------------|-------|-------------|
| 200 | - | Success |
| 400 | Invalid request | Missing or invalid parameters |
| 401 | Unauthorized | Invalid or expired auth token |
| 409 | Conflict | Email already registered to different account |
| 429 | Too many requests | Rate limit exceeded |
| 500 | Internal server error | Database or service error |

---

## Postman Collection

Import this collection to test the endpoints:

```json
{
  "info": {
    "name": "Apple Sign-In API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Apple Authentication",
      "request": {
        "method": "POST",
        "header": [{"key": "Content-Type", "value": "application/json"}],
        "url": "{{base_url}}/api/auth/apple",
        "body": {
          "mode": "raw",
          "raw": "{\n  \"apple_user_id\": \"001234.abc123def456.7890\",\n  \"name\": \"John Doe\",\n  \"email\": \"john.doe@university.edu\",\n  \"device_token\": \"fK8xM9...\"\n}"
        }
      }
    },
    {
      "name": "Send Verification OTP",
      "request": {
        "method": "POST",
        "header": [{"key": "Content-Type", "value": "application/json"}],
        "url": "{{base_url}}/api/send-verification-otp",
        "body": {
          "mode": "raw",
          "raw": "{\n  \"email\": \"john.doe@university.edu\"\n}"
        }
      }
    },
    {
      "name": "Verify Email Apple Sign-In",
      "request": {
        "method": "POST",
        "header": [{"key": "Content-Type", "value": "application/json"}],
        "url": "{{base_url}}/api/verify-email-apple-signin",
        "body": {
          "mode": "raw",
          "raw": "{\n  \"email\": \"john.doe@university.edu\",\n  \"otp\": \"123456\",\n  \"apple_user_id\": \"001234.abc123def456.7890\",\n  \"name\": \"John Doe\",\n  \"device_token\": \"fK8xM9...\"\n}"
        }
      }
    }
  ]
}
```

---

## Implementation Checklist

- [ ] Create/update users table with `apple_user_id` column
- [ ] Create email_verifications table
- [ ] Create rate_limits table or Redis integration
- [ ] Implement `/api/auth/apple` endpoint
- [ ] Implement `/api/send-verification-otp` endpoint
- [ ] Implement `/api/verify-email-apple-signin` endpoint
- [ ] Set up email service (SendGrid/AWS SES)
- [ ] Configure email templates
- [ ] Implement JWT token generation
- [ ] Implement rate limiting middleware
- [ ] Add domain whitelist configuration
- [ ] Write unit tests
- [ ] Write integration tests
- [ ] Set up monitoring/logging
- [ ] Deploy to staging environment
- [ ] Test complete flow end-to-end
- [ ] Deploy to production
- [ ] Update API documentation

---

**Document Version:** 1.0  
**Last Updated:** January 3, 2026  
**Contact:** Backend Team
