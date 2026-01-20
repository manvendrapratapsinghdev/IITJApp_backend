-- Migration: Apple Sign-In Support
-- Date: 2026-01-03
-- Description: Adds apple_user_id and email_verified columns to users table
--              Creates email_verifications and otp_rate_limits tables

-- Add apple_user_id column to users table
ALTER TABLE users 
ADD COLUMN apple_user_id VARCHAR(255) NULL UNIQUE AFTER google_id,
ADD INDEX idx_apple_user_id (apple_user_id);

-- Add email_verified column to users table
ALTER TABLE users 
ADD COLUMN email_verified BOOLEAN NOT NULL DEFAULT FALSE AFTER email;

-- Create email_verifications table for OTP storage
CREATE TABLE IF NOT EXISTS email_verifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  otp VARCHAR(6) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  INDEX idx_email (email),
  INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create otp_rate_limits table for rate limiting
CREATE TABLE IF NOT EXISTS otp_rate_limits (
  email VARCHAR(255) PRIMARY KEY,
  request_count INT NOT NULL DEFAULT 1,
  window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_window_start (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Set email_verified to TRUE for all existing users (backward compatibility)
UPDATE users SET email_verified = TRUE WHERE email_verified = FALSE;
