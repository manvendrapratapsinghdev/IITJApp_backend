# Product Requirements Document (PRD)
## Comments & Likes Feature for Stream/Post System

**Document Version:** 1.0  
**Date:** October 10, 2025  
**Author:** Development Team  
**Status:** Draft

---

## 1. Executive Summary

This document outlines the requirements for implementing a comprehensive comments (replies) and likes system for the existing post/stream functionality in the Student Mobile API. The feature will enable users to interact with posts through likes and nested comments, enhancing user engagement and social interaction within the platform.

---

## 2. Objectives

### 2.1 Primary Goals
- Enable users to like/unlike posts with real-time like count display
- Allow users to comment on posts with full CRUD operations
- Track user engagement metrics (likes count, comments count)
- Provide personalized indicators showing which posts the current user has liked

### 2.2 Success Metrics
- User engagement rate increase (target: 30% within 3 months)
- Average comments per post (target: 2+)
- Like-to-view ratio (target: 15%+)
- API response time < 500ms for all endpoints

---

## 3. User Stories

### 3.1 Likes Feature
**As a user, I want to:**
- Like a post to show appreciation or agreement
- Unlike a post if I change my mind
- See the total number of likes on each post
- See if I have already liked a post (visual indicator)

### 3.2 Comments Feature
**As a user, I want to:**
- Add a comment/reply to any post
- Edit my own comments
- Delete my own comments
- View all comments on a post in chronological order
- See the total number of comments on each post
- See who wrote each comment with timestamp

### 3.3 Admin/Moderation
**As an admin, I want to:**
- Delete inappropriate comments
- View all comments across the platform
- Monitor engagement metrics
- Receive reports about problematic comments (future enhancement)

---

## 4. Technical Requirements

### 4.1 Database Schema

#### 4.1.1 New Table: `post_replies`
```sql
CREATE TABLE post_replies (
    reply_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted TINYINT(1) DEFAULT 0,
    
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    
    INDEX idx_post_id (post_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);
```

#### 4.1.2 New Table: `post_likes`
```sql
CREATE TABLE post_likes (
    like_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_user_post_like (post_id, user_id),
    INDEX idx_post_id (post_id),
    INDEX idx_user_id (user_id)
);
```

### 4.2 API Endpoints

#### 4.2.1 Posts/Stream Endpoints (Modified)

**GET /api/stream**
- **Purpose:** Fetch posts with engagement data
- **Response Enhancement:** Include `is_liked`, `likes_count`, `comments_count` for each post
- **Query Parameters:**
  - Existing parameters remain unchanged
  - All data fetched via SQL JOIN queries (no in-memory processing)

**GET /api/posts/{post_id}**
- **Purpose:** Fetch single post details, we already have this API nwow this need to modify 
- **Response Enhancement:** Include `is_liked`, `likes_count`, `comments_count`, top 5 comment

#### 4.2.2 Likes Endpoints (New)

**POST /api/posts/{post_id}/like**
- **Purpose:** Like a post
- **Authentication:** Required
- **Request Body:** None
- **Response:**
  ```json
  {
    "success": true,
    "message": "Post liked successfully",
    "data": {
      "post_id": 123,
      "likes_count": 45,
      "is_liked": true
    }
  }
  ```
- **Business Logic:**
  - If already liked, return error or treat as idempotent operation
  - Increment like count
  - Send notification to post owner (optional)

**DELETE /api/posts/{post_id}/like**
- **Purpose:** Unlike a post
- **Authentication:** Required
- **Response:**
  ```json
  {
    "success": true,
    "message": "Post unliked successfully",
    "data": {
      "post_id": 123,
      "likes_count": 44,
      "is_liked": false
    }
  }
  ```

**GET /api/posts/{post_id}/likes**
- **Purpose:** Get list of users who liked a post
- **Authentication:** Required
- **Query Parameters:**
  - `page` (default: 1)
  - `limit` (default: 20)
- **Response:**
  ```json
  {
    "success": true,
    "data": {
      "likes": [
        {
          "user_id": 456,
          "username": "john_doe",
          "full_name": "John Doe",
          "profile_picture": "url",
          "liked_at": "2025-10-10T10:30:00Z"
        }
      ],
      "total": 45,
      "page": 1,
      "limit": 20
    }
  }
  ```

#### 4.2.3 Comments/Replies Endpoints (New)

**GET /api/posts/{post_id}/comments**
- **Purpose:** Get all comments for a post
- **Authentication:** Required
- **Query Parameters:**
  - `page` (default: 1)
  - `limit` (default: 20)
  - `sort` (values: `asc`, `desc`, default: `asc`)
- **Response:**
  ```json
  {
    "success": true,
    "data": {
      "comments": [
        {
          "reply_id": 789,
          "post_id": 123,
          "user_id": 456,
          "username": "john_doe",
          "full_name": "John Doe",
          "profile_picture": "url",
          "content": "Great post!",
          "created_at": "2025-10-10T10:30:00Z",
          "updated_at": "2025-10-10T10:30:00Z",
          "is_edited": false,
          "is_own_comment": true
        }
      ],
      "total": 12,
      "page": 1,
      "limit": 20
    }
  }
  ```

**POST /api/posts/{post_id}/comments**
- **Purpose:** Add a new comment to a post
- **Authentication:** Required
- **Request Body:**
  ```json
  {
    "content": "This is my comment"
  }
  ```
- **Validation:**
  - `content` is required, min length: 1, max length: 1000 characters
  - Content must not be empty or only whitespace
- **Response:**
  ```json
  {
    "success": true,
    "message": "Comment added successfully",
    "data": {
      "reply_id": 789,
      "post_id": 123,
      "user_id": 456,
      "content": "This is my comment",
      "created_at": "2025-10-10T10:30:00Z"
    }
  }
  ```
- **Side Effects:**
  - Send notification to post owner
  - Increment comments count for the post

**PUT /api/comments/{reply_id}**
- **Purpose:** Edit an existing comment
- **Authentication:** Required
- **Authorization:** User must be comment owner or admin
- **Request Body:**
  ```json
  {
    "content": "Updated comment text"
  }
  ```
- **Validation:**
  - Same as POST validation
- **Response:**
  ```json
  {
    "success": true,
    "message": "Comment updated successfully",
    "data": {
      "reply_id": 789,
      "content": "Updated comment text",
      "updated_at": "2025-10-10T11:00:00Z"
    }
  }
  ```

**DELETE /api/comments/{reply_id}**
- **Purpose:** Delete a comment
- **Authentication:** Required
- **Authorization:** User must be comment owner or admin
- **Deletion Strategy:** Soft delete (set `is_deleted = 1`)
- **Response:**
  ```json
  {
    "success": true,
    "message": "Comment deleted successfully"
  }
  ```
- **Side Effects:**
  - Decrement comments count for the post

---

## 5. Data Flow & Query Optimization

### 5.1 Stream/Posts Query Enhancement

**Current State:** Posts fetched without engagement data

**New Query Structure:**
```sql
SELECT 
    p.*,
    u.username,
    u.full_name,
    u.profile_picture,
    COUNT(DISTINCT pl.like_id) as likes_count,
    COUNT(DISTINCT pr.reply_id) as comments_count,
    CASE 
        WHEN upl.user_id IS NOT NULL THEN 1 
        ELSE 0 
    END as is_liked
FROM posts p
LEFT JOIN users u ON p.user_id = u.user_id
LEFT JOIN post_likes pl ON p.post_id = pl.post_id
LEFT JOIN post_replies pr ON p.post_id = pr.post_id AND pr.is_deleted = 0
LEFT JOIN post_likes upl ON p.post_id = upl.post_id AND upl.user_id = ?
WHERE p.is_deleted = 0
GROUP BY p.post_id
ORDER BY p.created_at DESC
LIMIT ? OFFSET ?
```

**Performance Considerations:**
- Use prepared statements to prevent SQL injection
- Add appropriate indexes on foreign keys
- Implement query caching for frequently accessed posts
- Consider pagination to limit result set size

### 5.2 No In-Memory Processing
- All aggregations (counts, is_liked) calculated via SQL
- No array manipulation in PHP for counting
- Database handles all filtering and sorting

---

## 6. Security & Authorization

### 6.1 Authentication
- All endpoints require valid JWT token
- Token validation via existing AuthMiddleware

### 6.2 Authorization Rules

| Action | Permission Rule |
|--------|----------------|
| Like/Unlike post | Authenticated user |
| View likes | Authenticated user |
| Add comment | Authenticated user |
| Edit comment | Comment owner OR admin |
| Delete comment | Comment owner OR admin |
| View comments | Authenticated user |

### 6.3 Input Validation
- Sanitize all user input (HTML entities, XSS prevention)
- Validate comment length (1-1000 characters)
- Prevent SQL injection via prepared statements
- Rate limiting on comment/like creation (10 per minute per user)

### 6.4 Rate Limiting
```php
// Example rate limit rules
POST /api/posts/{post_id}/comments: 10 requests/minute
POST /api/posts/{post_id}/like: 20 requests/minute
DELETE /api/posts/{post_id}/like: 20 requests/minute
```

---

## 7. Error Handling

### 7.1 Common Error Responses

**400 Bad Request**
```json
{
  "success": false,
  "error": "Invalid input",
  "details": {
    "content": "Comment content is required"
  }
}
```

**401 Unauthorized**
```json
{
  "success": false,
  "error": "Authentication required"
}
```

**403 Forbidden**
```json
{
  "success": false,
  "error": "You don't have permission to perform this action"
}
```

**404 Not Found**
```json
{
  "success": false,
  "error": "Post not found"
}
```

**409 Conflict**
```json
{
  "success": false,
  "error": "You have already liked this post"
}
```

**429 Too Many Requests**
```json
{
  "success": false,
  "error": "Rate limit exceeded. Please try again later."
}
```

**500 Internal Server Error**
```json
{
  "success": false,
  "error": "An unexpected error occurred"
}
```

---

## 8. Implementation Plan

### Phase 1: Database Setup (Day 1)
- [ ] Create migration script for `post_replies` table
- [ ] Create migration script for `post_likes` table
- [ ] Add indexes for performance optimization
- [ ] Test migrations on development database
- [ ] Document rollback procedures

### Phase 2: Backend API - Likes (Days 2-3)
- [ ] Implement Like endpoint (POST /api/posts/{post_id}/like)
- [ ] Implement Unlike endpoint (DELETE /api/posts/{post_id}/like)
- [ ] Implement Get Likes endpoint (GET /api/posts/{post_id}/likes)
- [ ] Update StreamController to include likes data in posts
- [ ] Write unit tests for like functionality
- [ ] Test rate limiting

### Phase 3: Backend API - Comments (Days 4-6)
- [ ] Implement Get Comments endpoint (GET /api/posts/{post_id}/comments)
- [ ] Implement Add Comment endpoint (POST /api/posts/{post_id}/comments)
- [ ] Implement Edit Comment endpoint (PUT /api/comments/{reply_id})
- [ ] Implement Delete Comment endpoint (DELETE /api/comments/{reply_id})
- [ ] Update StreamController to include comments count
- [ ] Write unit tests for comment functionality
- [ ] Test authorization rules

### Phase 4: Integration & Testing (Days 7-8)
- [ ] Integration testing of all endpoints
- [ ] Performance testing with large datasets
- [ ] Security audit (SQL injection, XSS, authorization)
- [ ] Load testing for concurrent users
- [ ] API documentation in Postman
- [ ] Update API documentation

### Phase 5: Deployment (Day 9)
- [ ] Deploy to staging environment
- [ ] Conduct UAT (User Acceptance Testing)
- [ ] Fix bugs from UAT
- [ ] Deploy to production
- [ ] Monitor error logs and performance

### Phase 6: Monitoring & Optimization (Day 10+)
- [ ] Monitor API response times
- [ ] Track database query performance
- [ ] Gather user feedback
- [ ] Optimize slow queries
- [ ] Plan for future enhancements

---

## 9. Testing Requirements

### 9.1 Unit Tests
- Test like/unlike functionality with valid/invalid inputs
- Test comment CRUD operations
- Test authorization rules
- Test input validation
- Test duplicate like prevention

### 9.2 Integration Tests
- Test complete flow: create post → like → comment → unlike
- Test pagination for comments and likes
- Test concurrent likes on same post
- Test soft delete behavior

### 9.3 Performance Tests
- Load test with 1000+ concurrent users
- Test query performance with 10,000+ posts
- Test response time for posts with 100+ comments/likes
- Verify database connection pooling

### 9.4 Security Tests
- Test SQL injection prevention
- Test XSS prevention in comments
- Test authorization bypass attempts
- Test rate limiting effectiveness
- Test token validation

---

## 10. API Documentation

### 10.1 Postman Collection
- Create comprehensive Postman collection
- Include example requests/responses
- Add pre-request scripts for authentication
- Document all error scenarios

### 10.2 OpenAPI/Swagger Specification
- Generate OpenAPI 3.0 specification
- Include all new endpoints
- Document request/response schemas
- Add authentication requirements

---

## 11. Future Enhancements (Out of Scope)

### 11.1 Phase 2 Features
- Nested replies (replies to comments)
- Reactions (beyond just likes: love, laugh, angry, etc.)
- Comment mentions (@username)
- Comment hashtags
- Rich text formatting in comments
- Image/media attachments in comments

### 11.2 Phase 3 Features
- Real-time notifications via WebSocket
- Comment threading and sorting options
- User blocking/muting
- Content moderation AI
- Analytics dashboard for engagement metrics
- Export engagement data

---

## 12. Dependencies

### 12.1 Existing Systems
- Posts/Stream system (must be functional)
- User authentication system (JWT)
- Database (MySQL/MariaDB)
- Authorization system

### 12.2 Third-party Services
- Firebase (for push notifications - optional)
- None required for core functionality

---

## 13. Risks & Mitigation

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Database performance degradation with high traffic | High | Medium | Implement proper indexing, query optimization, caching |
| Spam comments/likes | Medium | High | Rate limiting, content moderation tools |
| Race conditions on like counts | Low | Medium | Use database transactions, optimistic locking |
| Security vulnerabilities | High | Low | Code review, security testing, input sanitization |
| API response time > 500ms | Medium | Medium | Query optimization, database indexing, caching |

---

## 14. Monitoring & Metrics

### 14.1 Key Performance Indicators (KPIs)
- API endpoint response times (avg, p95, p99)
- Database query execution times
- Like/unlike operations per second
- Comment creation rate
- Error rate per endpoint
- User engagement rate

### 14.2 Logging Requirements
- Log all API requests with timestamps
- Log all database errors
- Log authorization failures
- Log rate limit violations
- Log successful like/comment actions for analytics

### 14.3 Alerting
- Alert on error rate > 5%
- Alert on response time > 1 second
- Alert on database connection failures
- Alert on rate limit violations spike

---

## 15. Rollback Plan

### 15.1 Database Rollback
```sql
-- Rollback script
DROP TABLE IF EXISTS post_likes;
DROP TABLE IF EXISTS post_replies;
```

### 15.2 API Rollback
- Revert code changes via Git
- Remove new routes from Router
- Deploy previous version
- Update API documentation

---

## 16. Documentation Deliverables

- [x] This PRD document
- [ ] Database migration scripts with comments
- [ ] API endpoint documentation (Postman collection)
- [ ] Code documentation (PHPDoc comments)
- [ ] User guide for frontend developers
- [ ] Admin guide for moderation features
- [ ] Deployment guide
- [ ] Troubleshooting guide

---

## 17. Acceptance Criteria

### 17.1 Likes Feature
- ✅ Users can like/unlike posts
- ✅ Like count displays correctly on all posts
- ✅ `is_liked` indicator shows user's like status
- ✅ No duplicate likes allowed
- ✅ All queries execute in < 500ms

### 17.2 Comments Feature
- ✅ Users can add comments to posts
- ✅ Users can edit their own comments
- ✅ Users can delete their own comments
- ✅ Comments display with user info and timestamp
- ✅ Comment count displays correctly on posts
- ✅ Pagination works for comments list
- ✅ Soft delete implemented correctly

### 17.3 Security & Performance
- ✅ All inputs validated and sanitized
- ✅ Authorization rules enforced correctly
- ✅ Rate limiting prevents abuse
- ✅ No SQL injection vulnerabilities
- ✅ No XSS vulnerabilities
- ✅ API response time < 500ms (95th percentile)

---

## 18. Appendix

### 18.1 Database ER Diagram
```
posts (existing)
├── post_id (PK)
├── user_id (FK)
├── content
├── created_at
└── updated_at

post_likes (new)
├── like_id (PK)
├── post_id (FK) → posts.post_id
├── user_id (FK) → users.user_id
└── created_at

post_replies (new)
├── reply_id (PK)
├── post_id (FK) → posts.post_id
├── user_id (FK) → users.user_id
├── content
├── created_at
├── updated_at
└── is_deleted
```

### 18.2 Sample Queries

**Get post with engagement data:**
```sql
SELECT 
    p.*,
    COUNT(DISTINCT pl.like_id) as likes_count,
    COUNT(DISTINCT pr.reply_id) as comments_count,
    EXISTS(
        SELECT 1 FROM post_likes 
        WHERE post_id = p.post_id 
        AND user_id = ?
    ) as is_liked
FROM posts p
LEFT JOIN post_likes pl ON p.post_id = pl.post_id
LEFT JOIN post_replies pr ON p.post_id = pr.post_id AND pr.is_deleted = 0
WHERE p.post_id = ?
GROUP BY p.post_id;
```

**Get comments for a post:**
```sql
SELECT 
    pr.*,
    u.username,
    u.full_name,
    u.profile_picture,
    (pr.updated_at > pr.created_at) as is_edited,
    (pr.user_id = ?) as is_own_comment
FROM post_replies pr
JOIN users u ON pr.user_id = u.user_id
WHERE pr.post_id = ? 
AND pr.is_deleted = 0
ORDER BY pr.created_at ASC
LIMIT ? OFFSET ?;
```

---

## 19. Approval & Sign-off

| Role | Name | Date | Signature |
|------|------|------|-----------|
| Product Manager | | | |
| Tech Lead | | | |
| Backend Developer | | | |
| QA Lead | | | |
| DevOps | | | |

---

**Document End**

For questions or clarifications, please contact the development team.
