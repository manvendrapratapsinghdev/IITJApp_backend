-- Campus App - Complete Database Schema (OPTIMIZED)
-- This is the unified schema file containing all tables and structure
-- 
-- NOTIFICATION OPTIMIZATION:
-- - Posts table: Contains all post content with is_notification flag
-- - Notifications table: Only stores user tracking (read/unread status) with post_id reference
-- - No duplicate data: notification content comes from JOIN with posts table
-- 
-- Drop existing database and recreate with this schema for clean setup

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  email_verified BOOLEAN NOT NULL DEFAULT TRUE,
  phone VARCHAR(50) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  google_id VARCHAR(100) NULL UNIQUE,
  apple_user_id VARCHAR(255) NULL UNIQUE,
  role ENUM('user', 'admin', 'super_admin', 'faculty') NOT NULL DEFAULT 'user',
  is_onboarding_done BOOLEAN NOT NULL DEFAULT FALSE,
  auth_token VARCHAR(500) NULL,
  token_expires_at TIMESTAMP NULL,
  bio TEXT NULL,
  links JSON NULL,
  whatsapp VARCHAR(20) NULL,
  age VARCHAR(50) NULL,
  company VARCHAR(255) NULL,
  expertise TEXT NULL,
  interests TEXT NULL,
  experience VARCHAR(50) NULL,
  linkedin_url VARCHAR(500) NULL,
  github_url VARCHAR(500) NULL,
  profile_picture VARCHAR(500) NULL,
  admin_request BOOLEAN DEFAULT FALSE,
  admin_status ENUM('pending', 'approved', 'rejected') DEFAULT NULL,
  admin_request_reason TEXT NULL,
  admin_request_date TIMESTAMP NULL,
  device_token VARCHAR(500) NULL COMMENT 'Device token for push notifications',
  is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
  is_blocked BOOLEAN NOT NULL DEFAULT FALSE,
  deleted_at TIMESTAMP NULL,
  blocked_at TIMESTAMP NULL,
  deleted_by INT NULL,
  delete_reason TEXT NULL,
  blocked_by INT NULL,
  block_reason TEXT NULL,
  last_active TIMESTAMP NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_auth_token (auth_token),
  INDEX idx_apple_user_id (apple_user_id),
  INDEX idx_role (role),
  INDEX idx_company (company),
  INDEX idx_last_active (last_active),
  INDEX idx_device_token (device_token),
  INDEX idx_is_deleted (is_deleted),
  INDEX idx_is_blocked (is_blocked),
  FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (blocked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `subjects`
-- --------------------------------------------------------

CREATE TABLE subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(200) NOT NULL,
  `order` INT NULL,
  faculty_id INT NULL,
  credits INT NOT NULL DEFAULT 3,
  semester VARCHAR(50) NOT NULL DEFAULT 'Fall 2025',
  description TEXT NULL,
  syllabus_url VARCHAR(500) NULL,
  class_schedule JSON NULL,
  class_links JSON NULL,
  saturday_status ENUM('Confirm', 'Not Confirm', 'Cancelled') NOT NULL DEFAULT 'Not Confirm',
  sunday_status ENUM('Confirm', 'Not Confirm', 'Cancelled') NOT NULL DEFAULT 'Not Confirm',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_code (code),
  INDEX idx_faculty (faculty_id),
  FOREIGN KEY (faculty_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `enrollments`
-- --------------------------------------------------------

CREATE TABLE enrollments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  subject_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_enroll (user_id, subject_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `notes`
-- --------------------------------------------------------

CREATE TABLE notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  file_url VARCHAR(500) NOT NULL,
  file_type VARCHAR(50) NOT NULL,
  file_size VARCHAR(20) NULL,
  user_id INT NOT NULL,
  subject_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_subject (subject_id),
  INDEX idx_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `posts`
-- --------------------------------------------------------

CREATE TABLE posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  link VARCHAR(500) NULL,
  user_id INT NOT NULL,
  is_announcement BOOLEAN NOT NULL DEFAULT FALSE,
  view_count INT NOT NULL DEFAULT 0,
  link_clicks INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_announcement (is_announcement),
  INDEX idx_created (created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `schedules`
-- --------------------------------------------------------

CREATE TABLE schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('quiz', 'assignment') NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  subject_id INT NULL,
  date DATETIME NOT NULL,
  duration_minutes INT NULL,
  location VARCHAR(255) NULL,
  instructions TEXT NULL,
  submission_link VARCHAR(500) NULL,
  max_marks INT NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_type (type),
  INDEX idx_subject (subject_id),
  INDEX idx_date (date),
  INDEX idx_created_by (created_by),
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `connections`
-- --------------------------------------------------------

CREATE TABLE connections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  other_user_id INT NOT NULL,
  status ENUM('pending','accepted','blocked') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_other_user (other_user_id),
  INDEX idx_status (status),
  UNIQUE KEY uniq_connection (user_id, other_user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (other_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `expertise`
-- --------------------------------------------------------

CREATE TABLE expertise (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `post_reads`
-- --------------------------------------------------------

CREATE TABLE post_reads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  post_id INT NOT NULL,
  read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_reads (user_id),
  INDEX idx_post_reads (post_id),
  UNIQUE KEY unique_user_post (user_id, post_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `user_notification_preferences`
-- --------------------------------------------------------

CREATE TABLE user_notification_preferences (
    user_id INT PRIMARY KEY,
    master_notifications BOOLEAN DEFAULT TRUE,
    post_notifications BOOLEAN DEFAULT TRUE,
    notes_notifications BOOLEAN DEFAULT TRUE,
    announcement_notifications BOOLEAN DEFAULT TRUE,
    connection_notifications BOOLEAN DEFAULT TRUE,
    schedule_notifications BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_notifications (user_id, master_notifications),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Set default preferences for existing users
INSERT INTO user_notification_preferences (user_id, master_notifications)
SELECT id, TRUE FROM users
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- --------------------------------------------------------
-- Table structure for table `post_likes`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS post_likes (
    like_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    CONSTRAINT fk_like_post FOREIGN KEY (post_id) 
        REFERENCES posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_like_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    
    -- Ensure one user can only like a post once
    CONSTRAINT unique_user_post_like UNIQUE KEY (post_id, user_id),
    
    -- Indexes for performance
    INDEX idx_like_post_id (post_id),
    INDEX idx_like_user_id (user_id),
    INDEX idx_like_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 
COMMENT='Stores user likes for posts - one like per user per post';

-- --------------------------------------------------------
-- Table structure for table `post_replies`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS post_replies (
    reply_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted TINYINT(1) DEFAULT 0 COMMENT 'Soft delete flag',
    deleted_at TIMESTAMP NULL,
    deleted_by INT NULL COMMENT 'User ID who deleted the comment',
    
    -- Foreign key constraints
    CONSTRAINT fk_reply_post FOREIGN KEY (post_id) 
        REFERENCES posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_reply_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reply_deleted_by FOREIGN KEY (deleted_by) 
        REFERENCES users(id) ON DELETE SET NULL,
    
    -- Indexes for performance
    INDEX idx_reply_post_id (post_id),
    INDEX idx_reply_user_id (user_id),
    INDEX idx_reply_created_at (created_at),
    INDEX idx_reply_is_deleted (is_deleted),
    
    -- Composite index for common queries
    INDEX idx_post_active_replies (post_id, is_deleted, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 
COMMENT='Stores comments/replies on posts with soft delete support';

-- --------------------------------------------------------
-- Table structure for table `email_verifications`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS email_verifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  otp VARCHAR(6) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  INDEX idx_email (email),
  INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COMMENT='Stores OTP codes for email verification during Apple Sign-In';

-- --------------------------------------------------------
-- Table structure for table `otp_rate_limits`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS otp_rate_limits (
  email VARCHAR(255) PRIMARY KEY,
  request_count INT NOT NULL DEFAULT 1,
  window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_window_start (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COMMENT='Rate limiting for OTP requests to prevent abuse';

-- --------------------------------------------------------
-- Restore character set settings
-- --------------------------------------------------------

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
