# Apple Sign-In Implementation - Quick Start

## ✅ Implementation Complete

All Apple Sign-In API endpoints have been successfully implemented following the requirements in `apple_signin_api_requirements.md`.

## 📋 What Was Implemented

### 1. **Database Schema** ✅
- Migration file: `database/migrations/002_apple_signin_support.sql`
- Added `apple_user_id` and `email_verified` columns to users table
- Created `email_verifications` table for OTP storage
- Created `otp_rate_limits` table for rate limiting

### 2. **Backend Services** ✅
- **EmailService** (`src/Services/EmailService.php`): Handles sending OTP emails
- **UserModel Updates** (`src/Models/UserModel.php`): Added 15+ methods for Apple Sign-In
- **AuthController Updates** (`src/Controllers/AuthController.php`): Added 3 new endpoints

### 3. **API Endpoints** ✅
1. `POST /api/auth/apple` - Initial Apple authentication
2. `POST /api/send-verification-otp` - Send OTP to email
3. `POST /api/verify-email-apple-signin` - Verify OTP and complete registration

### 4. **Security Features** ✅
- Email domain validation (@iitj.ac.in only)
- G24 prefix validation for MTech batch
- Rate limiting (3 requests per hour)
- OTP expiration (10 minutes)
- Maximum 3 verification attempts
- Account blocking/restoration handling

## 🚀 Next Steps

### Step 1: Run Database Migration
```bash
cd /Users/d111879/Documents/Project/DEMO/Student_mobile/api
mysql -u root -p campus_app < database/migrations/002_apple_signin_support.sql
```

### Step 2: Start the Server
```bash
php -S localhost:8000 -t public
```

### Step 3: Test the Implementation
```bash
# Make the test script executable
chmod +x scripts/test_apple_signin.php

# Run the test script
php scripts/test_apple_signin.php
```

## 📝 API Usage Examples

### Example 1: New User Flow
```bash
# 1. Initial Apple auth
curl -X POST http://localhost:8000/api/auth/apple \
  -H "Content-Type: application/json" \
  -d '{
    "apple_user_id": "001234.abc123def456.7890",
    "name": "John Doe"
  }'

# Response: {"success": true, "requires_email_verification": true, ...}

# 2. Send OTP
curl -X POST http://localhost:8000/api/send-verification-otp \
  -H "Content-Type: application/json" \
  -d '{
    "email": "g24john@iitj.ac.in"
  }'

# Response: {"success": true, "message": "Verification code sent...", ...}

# 3. Verify OTP and complete registration
curl -X POST http://localhost:8000/api/verify-email-apple-signin \
  -H "Content-Type: application/json" \
  -d '{
    "email": "g24john@iitj.ac.in",
    "otp": "123456",
    "apple_user_id": "001234.abc123def456.7890",
    "name": "John Doe"
  }'

# Response: {"success": true, "user": {..., "auth_token": "..."}}
```

### Example 2: Existing User Login
```bash
curl -X POST http://localhost:8000/api/auth/apple \
  -H "Content-Type: application/json" \
  -d '{
    "apple_user_id": "001234.abc123def456.7890",
    "name": "John Doe",
    "email": "g24john@iitj.ac.in"
  }'

# Response: {"success": true, "message": "Login successful", "user": {...}}
```

## 📂 Files Created/Modified

### New Files:
1. `/database/migrations/002_apple_signin_support.sql` - Database migration
2. `/src/Services/EmailService.php` - Email service for OTP
3. `/scripts/test_apple_signin.php` - Test script
4. `/docs/APPLE_SIGNIN_IMPLEMENTATION.md` - Detailed documentation
5. `/docs/IMPLEMENTATION_SUMMARY.md` - This file

### Modified Files:
1. `/src/Models/UserModel.php` - Added Apple Sign-In and OTP methods
2. `/src/Controllers/AuthController.php` - Added 3 new endpoints
3. `/public/api.php` - Registered new routes

## 🔒 Security Highlights

- ✅ Email domain validation (@iitj.ac.in)
- ✅ Batch validation (G24 prefix)
- ✅ Rate limiting (3 OTP requests/hour)
- ✅ OTP expiration (10 minutes)
- ✅ Maximum 3 verification attempts
- ✅ Blocked user detection
- ✅ Deleted user restoration
- ✅ Unique constraint on apple_user_id

## 📊 Database Schema Overview

```
users
  + apple_user_id VARCHAR(255) UNIQUE
  + email_verified BOOLEAN DEFAULT FALSE

email_verifications
  - id, email, otp, attempts, created_at, expires_at

otp_rate_limits
  - email, request_count, window_start
```

## 🧪 Testing

The test script (`scripts/test_apple_signin.php`) covers:
1. ✅ New user registration flow
2. ✅ OTP sending
3. ✅ OTP verification
4. ✅ Existing user login
5. ✅ Rate limiting
6. ✅ Invalid email domain
7. ✅ Invalid OTP

## 📚 Documentation

For detailed information, see:
- **Requirements**: `docs/apple_signin_api_requirements.md`
- **Implementation Guide**: `docs/APPLE_SIGNIN_IMPLEMENTATION.md`
- **Postman Collection**: `docs/postman.json` (update with new endpoints)

## ⚠️ Important Notes

1. **Email Service**: Currently uses PHP's `mail()` function. For production, integrate with SendGrid or AWS SES in `src/Services/EmailService.php`.

2. **OTP in Logs**: During development, OTPs are logged to error_log. Check your PHP error log to see the OTP codes:
   ```bash
   tail -f /path/to/php/error.log | grep "OTP Code"
   ```

3. **Rate Limiting**: Set to 3 requests per hour per email. Adjust in `AuthController::sendVerificationOTP()` if needed.

4. **Email Domain**: Currently restricted to @iitj.ac.in with G24 prefix. Modify validation in `AuthController::isIITDomain()` and `AuthController::isG24Email()` for other batches.

## 🎯 Integration with Mobile App

The mobile app should:

1. **Implement Apple Sign-In SDK** on iOS/Android
2. **Call `/api/auth/apple`** with `apple_user_id` and `name`
3. **If `requires_email_verification: true`**:
   - Show email input screen
   - Call `/api/send-verification-otp` with email
   - Show OTP input screen
   - Call `/api/verify-email-apple-signin` with email, OTP, and Apple ID
4. **Store `auth_token`** for subsequent API calls
5. **Handle errors** appropriately (rate limiting, invalid OTP, etc.)

## 🆘 Troubleshooting

### Issue: OTP not received
- Check server logs for "Sending email to" messages
- Verify email service configuration
- Check spam folder
- Ensure PHP `mail()` is configured or integrate with SendGrid

### Issue: Rate limit exceeded
- Wait 1 hour or manually clear: `DELETE FROM otp_rate_limits WHERE email = 'your@email.com'`

### Issue: OTP expired
- OTPs expire after 10 minutes
- Request a new OTP

### Issue: Database error
- Ensure migration was run successfully
- Check database connection in `config/config.php`

## ✨ Success!

Your Apple Sign-In API is now fully implemented and ready for testing! 🎉

For any issues or questions, refer to the detailed documentation in `docs/APPLE_SIGNIN_IMPLEMENTATION.md`.
