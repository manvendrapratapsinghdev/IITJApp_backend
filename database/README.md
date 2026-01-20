# Campus App Database

This directory contains the unified database schema for the Campus App.

## Files

- **`schema.sql`** - Complete unified database schema with all tables
- **`setup.sh`** - Database setup script for clean installation

## Database Structure

### Core Tables
- **`users`** - User accounts, profiles, and authentication
- **`subjects`** - Academic subjects/courses 
- **`enrollments`** - User enrollment in subjects

### Academic Features
- **`schedules`** - Quizzes, assignments, exams, classes, events
- **`notes`** - Study materials and file sharing
- **`posts`** - Social posts and announcements

### Admin & Management
- **`admin_requests`** - Admin role requests and approvals
- **`notifications`** - User notifications system
- **`notification_preferences`** - User notification settings
- **`connections`** - User connections/networking

## Setup Instructions

### Option 1: Automated Setup (Recommended)
```bash
cd database
./setup.sh
```

### Option 2: Manual Setup
```bash
# Create database
mysql -u root -p -e "CREATE DATABASE campus_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import schema
mysql -u root -p campus_app < schema.sql
```

### Option 3: API Setup (if database exists)
```
GET http://localhost:8000/api/admin/quick-setup
```

## Post-Setup

After database setup, create a super admin:

```
POST http://localhost:8000/api/admin/create-super-admin
{
  "master_id": "CAMPUS_SUPER_ADMIN_2025",
  "secret_key": "SA_2025_CampusApp_SecretKey_v1!",
  "name": "Super Admin",
  "email": "admin@example.com",
  "phone": "1234567890",
  "google_id": "your_google_id"
}
```

## Features Supported

- ✅ **Google Authentication** with domain restrictions
- ✅ **Role-based Access** (user, admin, super_admin, faculty)
- ✅ **Academic Management** (subjects, schedules, notes)
- ✅ **Social Features** (posts, connections, notifications)  
- ✅ **Admin System** (request/approval workflow)
- ✅ **Onboarding Process** for new users
- ✅ **Profile Management** with custom fields

## Notes

- All tables use `utf8mb4` charset for full Unicode support
- Foreign keys maintain referential integrity
- Indexes optimize common queries
- Timestamps track creation and updates
- Enum fields enforce data consistency