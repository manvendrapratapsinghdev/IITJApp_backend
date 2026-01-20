# AIGyan Connect - Student Networking Platform API

A comprehensive REST API backend for a student networking and collaboration platform built with PHP, providing authentication, content management, real-time notifications, and social networking features.

## 🚀 Features

### Core Functionality
- **User Authentication & Authorization**
  - Email/Password authentication
  - Google Sign-In integration
  - Apple Sign-In support
  - JWT-based token authentication
  - Role-based access control (User, Admin, Super Admin, Faculty)

### Content Management
- **Posts & Announcements**
  - Create, read, update, delete posts
  - Like/unlike functionality
  - View and link click tracking
  - Admin announcements with priority notifications

- **Comments System**
  - Nested comments on posts
  - Edit and delete capabilities
  - Soft delete support
  - Pagination support

- **Notes Management**
  - Subject-based notes organization
  - File upload support
  - Faculty and student contributions
  - Push notifications for new notes

- **Schedule Management**
  - Academic calendar integration
  - Event and deadline tracking
  - Subject-specific schedules
  - Upcoming events filtering

### Networking Features
- **User Profiles**
  - Comprehensive profile management
  - Professional information (expertise, experience, company)
  - Social links (LinkedIn, GitHub, WhatsApp)
  - Profile picture support

- **Network Discovery**
  - Advanced search and filtering
  - Company and expertise-based discovery
  - Experience-level filtering
  - Connection management

- **Admin Management**
  - User role management
  - Admin request approval workflow
  - User blocking/deletion capabilities
  - Faculty assignment

### Notifications
- **Push Notifications**
  - Firebase Cloud Messaging (FCM) integration
  - Granular notification preferences
  - Post, notes, announcement, and schedule notifications
  - Real-time updates

- **Email Notifications**
  - OTP-based email verification
  - SMTP support for transactional emails
  - Welcome emails and account notifications

## 📋 Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer (for dependencies)
- Web server (Apache/Nginx) or PHP built-in server
- Firebase account (for push notifications)
- SMTP server (for email notifications)

## 🛠️ Installation

### 1. Clone the Repository

```bash
git clone <repository-url>
cd demo_api
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Database Setup

Create a MySQL database and import the schema:

```bash
mysql -u root -p
CREATE DATABASE aigyan_db;
USE aigyan_db;
SOURCE database/schema.sql;
```

For Apple Sign-In support, also run the migration:

```bash
SOURCE database/migrations/002_apple_signin_support.sql;
```

### 4. Configuration

#### Database Configuration

Edit `config/config.php`:

```php
return [
  'db' => [
    'host' => 'localhost',
    'dbname' => 'aigyan_db',
    'user' => 'your_db_user',
    'pass' => 'your_db_password'
  ],
  'secret' => 'your-long-random-secret-key-for-jwt',
  'google' => [
    'client_id' => 'your-google-client-id'
  ],
  'apple' => [
    'client_id' => 'your-apple-service-id',
    'team_id' => 'your-apple-team-id',
    'key_id' => 'your-apple-key-id',
    'key_file' => __DIR__ . '/apple-private-key.p8'
  ]
];
```

#### Firebase Configuration

1. Download your Firebase service account JSON from Firebase Console
2. Save it as `config/firebase-service-account.json`
3. Update `config/firebase.php` with your project details

#### Email Configuration

Create a `.env` file in the root directory:

```env
EMAIL_FROM=noreply@yourdomain.com
EMAIL_FROM_NAME=AIGyan Connect
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_SECURE=tls
EMAIL_PASSWORD=your-app-password
```

### 5. Run the Application

#### Using PHP Built-in Server (Development)

```bash
php -S localhost:8000 -t public
```

#### Using Apache/XAMPP

1. Copy the project to your web server directory (e.g., `htdocs`)
2. Ensure `mod_rewrite` is enabled
3. Access via `http://localhost/demo_api/public`

#### Using VS Code Task (Recommended)

Press `Cmd+Shift+B` (Mac) or `Ctrl+Shift+B` (Windows/Linux) and select "Run PHP built-in server"

Access the API at: `http://localhost:8000`

## 📚 API Documentation

### Authentication Endpoints

#### Sign Up
```http
POST /api/auth/signup
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "1234567890",
  "password": "secure_password"
}
```

#### Login
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "secure_password"
}
```

#### Google Sign-In
```http
POST /api/auth/google
Content-Type: application/json

{
  "id_token": "google_id_token",
  "google_id": "google_user_id",
  "name": "John Doe",
  "email": "john@gmail.com"
}
```

#### Apple Sign-In
```http
POST /api/auth/apple
Content-Type: application/json

{
  "id_token": "apple_id_token",
  "user_id": "apple_user_id",
  "name": "John Doe",
  "email": "john@privaterelay.appleid.com"
}
```

### User Endpoints

#### Get Current User
```http
GET /api/me
Authorization: Bearer <jwt_token>
```

#### Update Profile
```http
PUT /api/profile
Authorization: Bearer <jwt_token>
Content-Type: application/json

{
  "name": "John Doe",
  "phone": "1234567890",
  "company": "Tech Corp",
  "expertise": "Backend Development",
  "bio": "Passionate developer"
}
```

### Posts Endpoints

#### Get All Posts
```http
GET /api/stream/posts?page=1&limit=20
Authorization: Bearer <jwt_token>
```

#### Create Post
```http
POST /api/stream/posts
Authorization: Bearer <jwt_token>
Content-Type: application/json

{
  "title": "Post Title",
  "description": "Post content",
  "link": "https://example.com",
  "is_announcement": false
}
```

#### Like/Unlike Post
```http
POST /api/posts/:id/like
Authorization: Bearer <jwt_token>
```

### Comments Endpoints

#### Get Comments
```http
GET /api/posts/:id/comments?page=1&limit=20&sort=asc
Authorization: Bearer <jwt_token>
```

#### Add Comment
```http
POST /api/posts/:id/comments
Authorization: Bearer <jwt_token>
Content-Type: application/json

{
  "content": "Great post!"
}
```

For complete API documentation, import `docs/postman.json` into Postman.

## 🏗️ Project Structure

```
demo_api/
├── config/                  # Configuration files
│   ├── config.php          # Database and app config
│   ├── firebase.php        # Firebase configuration
│   └── cacert.pem         # SSL certificates
├── database/               # Database files
│   ├── schema.sql         # Main database schema
│   └── migrations/        # Database migrations
├── docs/                   # Documentation
│   ├── postman.json       # Postman collection
│   └── *.md              # Feature documentation
├── public/                 # Public web directory
│   ├── api.php           # API entry point
│   ├── index.php         # Index page
│   └── admin_dashboard/  # Admin panel
├── src/                    # Source code
│   ├── Controllers/       # API controllers
│   ├── Core/             # Core classes
│   ├── Models/           # Data models
│   └── Services/         # Business services
├── scripts/               # Utility scripts
└── vendor/               # Composer dependencies
```

## 🔐 Security Features

- **JWT Authentication**: Secure token-based authentication
- **Password Hashing**: bcrypt password hashing
- **Role-Based Access Control**: Fine-grained permission system
- **SQL Injection Prevention**: Prepared statements
- **XSS Protection**: Input sanitization
- **CORS Configuration**: Configurable cross-origin requests
- **Rate Limiting**: OTP and API rate limiting

## 🎯 Role-Based Permissions

### User Roles
- **User**: Basic access to posts, notes, schedules
- **Admin**: Manage posts, notes, schedules, announcements
- **Super Admin**: Full system access, user management
- **Faculty**: Subject management, notes upload

### Access Control
Authorization is centralized in `src/Core/Authorization.php` with module and endpoint-level permissions.

## 🔧 Advanced Features

### Soft Delete
Users, posts, and comments support soft deletion for data recovery.

### Push Notifications
Firebase Cloud Messaging integration with user preferences:
- Post notifications
- Notes notifications
- Announcement notifications
- Schedule notifications
- Connection requests

### Email Verification
OTP-based email verification with rate limiting and expiration.

### Device Token Management
Automatic cleanup of invalid device tokens for efficient notification delivery.

## 🧪 Testing

### Health Check
```bash
curl http://localhost:8000/api/health
```

### Test Push Notifications
```bash
php scripts/test_post_notification.php
```

## 📱 Admin Dashboard

Access the admin dashboard at: `http://localhost:8000/admin_dashboard/`

Features:
- User management
- Admin request approvals
- Post and announcement management
- Subject and schedule management
- Faculty assignment
- System analytics

Default super admin credentials can be created via database seeding.

## 🚢 Deployment

### Production Checklist
- [ ] Set strong JWT secret in `config/config.php`
- [ ] Configure production database credentials
- [ ] Set up SSL certificates (HTTPS)
- [ ] Configure production Firebase credentials
- [ ] Set up email SMTP credentials
- [ ] Enable error logging (disable display_errors)
- [ ] Set up automated backups
- [ ] Configure proper file permissions
- [ ] Enable security headers
- [ ] Set up monitoring and alerts

### Environment Variables
For cloud deployment (AWS, Heroku, etc.), use environment variables:
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `JWT_SECRET`
- `FIREBASE_SERVICE_ACCOUNT` or `FIREBASE_SERVICE_ACCOUNT_PATH`
- `EMAIL_FROM`, `EMAIL_PASSWORD`, `SMTP_HOST`, `SMTP_PORT`

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is proprietary software. All rights reserved.

## 🐛 Troubleshooting

### Common Issues

**Database Connection Failed**
- Check database credentials in `config/config.php`
- Ensure MySQL service is running
- Verify database exists and schema is imported

**Firebase Notifications Not Working**
- Verify `firebase-service-account.json` is present
- Check Firebase project configuration
- Ensure device tokens are being saved correctly

**Email Sending Failed**
- Verify SMTP credentials in `.env`
- For Gmail, use App Passwords (not regular password)
- Check firewall settings for SMTP port

**JWT Token Invalid**
- Ensure `secret` is set in `config/config.php`
- Check token expiration (24 hours by default)
- Verify Authorization header format: `Bearer <token>`

## 📞 Support

For issues and questions:
- Create an issue in the repository
- Contact the development team

## 🙏 Acknowledgments

- Firebase for push notification infrastructure
- Google and Apple for OAuth integration
- PHP community for excellent libraries

---

**Built with ❤️ for Student Networking and Collaboration**
