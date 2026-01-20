# Product Requirements Document (PRD)
# Mobile App UI/UX - Comments & Likes Feature

**Document Version:** 1.0  
**Date:** October 10, 2025  
**Project:** Student Mobile App - Social Feed Enhancement  
**Feature:** Mobile UI/UX Implementation for Comments and Likes

---

## 1. Executive Summary

This PRD defines the mobile application user interface and user experience requirements for implementing the Comments and Likes feature in the Student Mobile App. The backend APIs are already implemented and tested. This document focuses on the mobile-side implementation including UI components, user flows, and interaction patterns.

---

## 2. Product Overview

### 2.1 Feature Scope
The mobile app will provide an intuitive and engaging interface for users to:
- View engagement metrics (likes and comments count) on post listings
- Like/unlike posts with visual feedback
- View top comments on post detail screens
- Access full comments list with pagination
- Add new comments via a modal/popup interface
- Edit and delete their own comments
- Admin users can delete any comment

### 2.2 Target Platforms
- **iOS:** Native (Swift/SwiftUI) or Flutter
- **Android:** Native (Kotlin/Jetpack Compose) or Flutter
- **Minimum iOS:** iOS 14.0+
- **Minimum Android:** Android 8.0+ (API 26)

---

## 3. User Stories

### 3.1 Post Listing Screen

**US-1: View Engagement Metrics**
```
As a user,
I want to see like and comment counts on each post in the feed,
So that I can quickly identify popular and engaging content.
```

**Acceptance Criteria:**
- Each post card displays a like icon (filled/unfilled based on user's like status)
- Like count is shown next to the like icon
- Comment icon with comment count is displayed
- Counts update in real-time after user actions

**US-2: Like/Unlike from List**
```
As a user,
I want to like or unlike a post directly from the feed,
So that I can quickly express my appreciation without opening the post.
```

**Acceptance Criteria:**
- Tapping the like icon toggles between liked (filled) and unliked (outline) states
- Like count increments/decrements immediately with optimistic UI update
- Visual feedback (animation) on like/unlike action
- If API fails, revert to previous state with error toast

---

### 3.2 Post Detail Screen

**US-3: View Post with Top Comments**
```
As a user,
I want to see the top 5 comments on the post detail screen,
So that I can quickly read the most relevant discussions without scrolling.
```

**Acceptance Criteria:**
- Post detail shows full post content at top
- Like button with count (same as listing)
- Top 5 comments displayed below the post
- Each comment shows: author name, profile picture, content, timestamp
- "Show More Comments" button/link if total comments > 5
- Empty state message if no comments exist

**US-4: Add New Comment**
```
As a user,
I want to add a comment on the post detail screen,
So that I can participate in discussions and share my thoughts.
```

**Acceptance Criteria:**
- "Add Comment" button/icon prominently displayed on detail screen
- Tapping opens a modal/bottom sheet with text input
- Text input has placeholder: "Write your comment..."
- Character counter shows "X/1000" characters
- "Cancel" and "Post" buttons in modal
- "Post" button disabled if comment is empty or exceeds 1000 chars
- Loading indicator while API call is in progress
- Success: Modal closes, new comment appears at top of list
- Error: Show error message, keep modal open

**US-5: View All Comments**
```
As a user,
I want to view all comments on a separate screen with pagination,
So that I can read all discussions without cluttering the post detail.
```

**Acceptance Criteria:**
- "Show More Comments" button navigates to full comments screen
- Full comments screen shows post summary at top (title + engagement stats)
- Comments list with pagination (load more on scroll)
- Pull-to-refresh to get latest comments
- Sort options: "Newest First" (default) / "Oldest First"
- Loading indicator for pagination
- Empty state if no comments

**US-6: Edit Own Comment**
```
As a user,
I want to edit my own comments,
So that I can correct mistakes or update my thoughts.
```

**Acceptance Criteria:**
- Three-dot menu icon on user's own comments only
- Menu options: "Edit" and "Delete"
- Tapping "Edit" opens same modal as add comment, pre-filled with current text
- Modal title changes to "Edit Comment"
- Button text changes to "Update"
- Success: Comment updates in place with "(edited)" label
- Character limit validation applies

**US-7: Delete Own Comment**
```
As a user,
I want to delete my own comments,
So that I can remove content I no longer want visible.
```

**Acceptance Criteria:**
- Three-dot menu shows "Delete" option on user's comments
- Tapping "Delete" shows confirmation dialog
- Dialog: "Delete Comment? This action cannot be undone."
- Options: "Cancel" and "Delete" (in red/destructive style)
- Success: Comment removed from list with fade-out animation
- Counts update immediately

**US-8: Admin Delete Any Comment**
```
As an admin,
I want to delete any user's comment,
So that I can moderate inappropriate content.
```

**Acceptance Criteria:**
- Admin users see three-dot menu on ALL comments (not just their own)
- Menu shows "Delete Comment" option (no edit for others' comments)
- Same confirmation dialog as US-7
- Success: Comment removed with moderation message (optional)

---

## 4. Screen Specifications

### 4.1 Post Listing Screen

#### Layout Structure
```
┌─────────────────────────────────────┐
│  📱 Feed / Stream                   │
├─────────────────────────────────────┤
│                                     │
│  ┌──────────────────────────────┐  │
│  │ 👤 User Name      • 2h ago   │  │
│  │                              │  │
│  │ Post Title                   │  │
│  │ Post description content...  │  │
│  │                              │  │
│  │ ❤️ 24  💬 12                │  │ ← Engagement Bar
│  └──────────────────────────────┘  │
│                                     │
│  ┌──────────────────────────────┐  │
│  │ 👤 Another User   • 5h ago   │  │
│  │                              │  │
│  │ Another Post Title           │  │
│  │ More content here...         │  │
│  │                              │  │
│  │ 🤍 18  💬 5                  │  │
│  └──────────────────────────────┘  │
│                                     │
└─────────────────────────────────────┘
```

#### Engagement Bar Details
- **Position:** Bottom of each post card
- **Height:** 44px (minimum touch target)
- **Layout:** Horizontal with equal spacing
- **Components:**
  1. **Like Button:**
     - Icon: Heart outline (unliked) / Filled heart (liked)
     - Color: Gray (unliked) / Red #FF3B30 (liked)
     - Animation: Scale + pop effect on tap (0.8x → 1.2x → 1.0x)
     - Label: Number next to icon
  2. **Comment Button:**
     - Icon: Chat bubble outline
     - Color: Gray #8E8E93
     - Label: Number next to icon
     - Action: Opens post detail screen

#### Interaction States
1. **Default State:** Heart outline, gray text
2. **Liked State:** Filled red heart, count highlighted
3. **Pressed State:** Slight scale down (0.95x)
4. **Loading State:** Subtle spinner overlay (optimistic update)
5. **Error State:** Revert with shake animation + toast

---

### 4.2 Post Detail Screen

#### Layout Structure
```
┌─────────────────────────────────────┐
│  ← Post Details                     │
├─────────────────────────────────────┤
│  ┌──────────────────────────────┐  │
│  │ 👤 User Name      • 2h ago   │  │
│  │                              │  │
│  │ Full Post Title              │  │
│  │                              │  │
│  │ Complete post description    │  │
│  │ with all content visible...  │  │
│  │                              │  │
│  │ 🔗 Link (if exists)          │  │
│  │                              │  │
│  │ ❤️ 24  💬 12                │  │
│  └──────────────────────────────┘  │
│                                     │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  │ ← Divider
│                                     │
│  Comments (12)          [+ Add]    │ ← Section Header
│                                     │
│  ┌──────────────────────────────┐  │
│  │ 👤 User A        • 1h ago ⋮  │  │ ← Three-dot menu
│  │ This is a great post! Very   │  │
│  │ informative and helpful.     │  │
│  └──────────────────────────────┘  │
│                                     │
│  ┌──────────────────────────────┐  │
│  │ 👤 User B        • 3h ago    │  │
│  │ Thanks for sharing this!     │  │
│  └──────────────────────────────┘  │
│                                     │
│  [... 3 more comments ...]          │
│                                     │
│  ┌──────────────────────────────┐  │
│  │ 📄 Show More Comments (7)    │  │ ← Load More Button
│  └──────────────────────────────┘  │
│                                     │
└─────────────────────────────────────┘
```

#### Section Breakdown

**A. Post Content Section**
- Background: White card with shadow
- Padding: 16px
- Border radius: 12px
- Includes: Author info, post content, engagement bar

**B. Comments Section Header**
- Text: "Comments ({count})"
- Font: 18px Bold
- Right aligned: "+ Add" button
- Background: Transparent
- Padding: 16px horizontal, 12px vertical

**C. Top Comments List (Max 5)**
- Each comment: White card with subtle border
- Spacing: 8px between comments
- Padding: 12px
- Layout:
  - Profile picture (32px circle) - left
  - Name + timestamp + menu (if applicable) - top right
  - Content - below name

**D. Show More Button**
- Display condition: If total comments > 5
- Style: Outlined button
- Text: "Show More Comments ({remaining_count})"
- Action: Navigate to full comments screen

**E. Add Comment Button**
- Position: Top right of comments section
- Style: Primary accent color
- Icon: "+" or edit icon
- Action: Opens add comment modal

---

### 4.3 Add/Edit Comment Modal

#### Layout Structure
```
┌─────────────────────────────────────┐
│                                     │
│  ╔═══════════════════════════════╗ │
│  ║ Add Comment          [Cancel] ║ │ ← Modal Header
│  ╠═══════════════════════════════╣ │
│  ║                               ║ │
│  ║  ┌─────────────────────────┐ ║ │
│  ║  │ Write your comment...   │ ║ │ ← Text Area
│  ║  │                         │ ║ │
│  ║  │                         │ ║ │
│  ║  │                         │ ║ │
│  ║  └─────────────────────────┘ ║ │
│  ║  45/1000                      ║ │ ← Character Counter
│  ║                               ║ │
│  ║  ┌─────────────────────────┐ ║ │
│  ║  │      Post Comment       │ ║ │ ← Action Button
│  ║  └─────────────────────────┘ ║ │
│  ╚═══════════════════════════════╝ │
│                                     │
└─────────────────────────────────────┘
```

#### Modal Specifications
- **Type:** Bottom sheet (Android) / Modal (iOS)
- **Height:** Auto, max 60% screen height
- **Background:** White with rounded top corners (16px)
- **Overlay:** Semi-transparent black (0.5 opacity)
- **Dismiss:** Tap outside, swipe down, or cancel button

#### Components

**1. Header**
- Title: "Add Comment" / "Edit Comment"
- Cancel button: Top right
- Border bottom: Light gray divider

**2. Text Input**
- Type: Multi-line text area
- Min height: 120px
- Max height: Expandable up to modal limit
- Placeholder: "Write your comment..."
- Auto-focus: Yes (keyboard appears automatically)
- Max characters: 1000

**3. Character Counter**
- Position: Below text area, right aligned
- Color: Gray (default), Red (if > 1000)
- Format: "X/1000"

**4. Post Button**
- Width: Full width (minus padding)
- Height: 48px
- Color: Primary accent (enabled), Gray (disabled)
- States:
  - Disabled: Empty or > 1000 chars
  - Loading: Spinner + "Posting..." text
  - Enabled: "Post Comment" / "Update Comment"

---

### 4.4 All Comments Screen

#### Layout Structure
```
┌─────────────────────────────────────┐
│  ← Comments (28)          ⋯ Sort   │ ← Header
├─────────────────────────────────────┤
│  ┌──────────────────────────────┐  │
│  │ 📝 Original Post Title       │  │ ← Post Summary
│  │ ❤️ 45  💬 28                │  │
│  └──────────────────────────────┘  │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  │
│                                     │
│  ┌──────────────────────────────┐  │
│  │ 👤 User A        • 1h ago ⋮  │  │
│  │ This is a great post! Very   │  │
│  │ informative and helpful.     │  │
│  │ (edited)                     │  │ ← Edited label
│  └──────────────────────────────┘  │
│                                     │
│  ┌──────────────────────────────┐  │
│  │ 👤 User B        • 2h ago ⋮  │  │
│  │ Thanks for sharing!          │  │
│  └──────────────────────────────┘  │
│                                     │
│  [... more comments ...]            │
│                                     │
│  [Loading more...]                  │ ← Pagination loader
│                                     │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│             [+ Add Comment]         │ ← Floating Action Button
└─────────────────────────────────────┘
```

#### Header Components
- **Back button:** Navigate to post detail
- **Title:** "Comments ({total_count})"
- **Sort button:** Opens sort menu
  - Options: "Newest First" (default), "Oldest First"
  - Selected option has checkmark

#### Post Summary Card
- Collapsed view of original post
- Shows: Title, engagement metrics
- Background: Light gray
- Action: Tap to expand (optional)

#### Comments List
- Vertical scroll
- Pull-to-refresh enabled
- Infinite scroll with pagination
- Load 20 comments per page
- Loading indicator at bottom while fetching

#### Floating Action Button (FAB)
- Position: Bottom right, fixed
- Icon: "+" or edit icon
- Color: Primary accent
- Shadow: Elevated
- Action: Opens add comment modal

---

### 4.5 Comment Context Menu

#### Menu Options

**For Own Comments:**
```
┌─────────────────────┐
│  ✏️  Edit Comment   │
│  🗑️  Delete Comment │
└─────────────────────┘
```

**For Admin Users (All Comments):**
```
┌─────────────────────┐
│  🗑️  Delete Comment │
└─────────────────────┘
```

#### Menu Specifications
- **Trigger:** Three-dot icon (⋮) on comment
- **Style:** Bottom sheet (Android) / Action sheet (iOS)
- **Animation:** Slide up with fade-in
- **Dismiss:** Tap outside or cancel

---

### 4.6 Delete Confirmation Dialog

```
┌─────────────────────────────────────┐
│                                     │
│  ╔═══════════════════════════════╗ │
│  ║                               ║ │
│  ║    Delete Comment?            ║ │
│  ║                               ║ │
│  ║    This action cannot be      ║ │
│  ║    undone.                    ║ │
│  ║                               ║ │
│  ║  ┌─────────────────────────┐ ║ │
│  ║  │       Cancel            │ ║ │
│  ║  └─────────────────────────┘ ║ │
│  ║  ┌─────────────────────────┐ ║ │
│  ║  │       Delete            │ ║ │ ← Red/Destructive
│  ║  └─────────────────────────┘ ║ │
│  ╚═══════════════════════════════╝ │
│                                     │
└─────────────────────────────────────┘
```

---

## 5. User Flows

### 5.1 Like/Unlike Flow

```
User on Feed Screen
       ↓
Taps Heart Icon
       ↓
   [UI Updates]
   - Icon fills/unfills
   - Count updates
   - Animation plays
       ↓
   [API Call]
   POST/DELETE /api/stream/posts/:id/like
       ↓
   ┌─ Success → Keep UI update
   │
   └─ Error → Revert UI + Show toast
```

### 5.2 View Comments Flow

```
User on Feed Screen
       ↓
Taps Post or Comment Icon
       ↓
Navigate to Post Detail Screen
       ↓
   [Load Post + Top 5 Comments]
   GET /api/stream/posts/:id
   GET /api/posts/:id/comments?limit=5
       ↓
Display Post + Top 5 Comments
       ↓
User taps "Show More Comments"
       ↓
Navigate to All Comments Screen
       ↓
   [Load All Comments with Pagination]
   GET /api/posts/:id/comments?page=1&limit=20
```

### 5.3 Add Comment Flow

```
User on Post Detail or All Comments Screen
       ↓
Taps "+ Add" Button
       ↓
Open Add Comment Modal
       ↓
   [User types comment]
       ↓
Validates (1-1000 chars)
       ↓
Taps "Post Comment"
       ↓
   [Show Loading]
       ↓
   [API Call]
   POST /api/posts/:id/comments
   { "content": "..." }
       ↓
   ┌─ Success →
   │  - Close modal
   │  - Add comment to top of list
   │  - Update comment count
   │  - Show success toast (optional)
   │
   └─ Error →
      - Keep modal open
      - Show error message
      - Allow retry
```

### 5.4 Edit Comment Flow

```
User viewing their own comment
       ↓
Taps three-dot menu (⋮)
       ↓
Selects "Edit Comment"
       ↓
Open Edit Comment Modal
       ↓
   [Pre-filled with current text]
       ↓
User modifies text
       ↓
Taps "Update Comment"
       ↓
   [Show Loading]
       ↓
   [API Call]
   PUT /api/comments/:reply_id
   { "content": "..." }
       ↓
   ┌─ Success →
   │  - Close modal
   │  - Update comment in list
   │  - Show "(edited)" label
   │  - Update timestamp
   │
   └─ Error →
      - Keep modal open
      - Show error message
```

### 5.5 Delete Comment Flow

```
User viewing their own comment (or Admin viewing any)
       ↓
Taps three-dot menu (⋮)
       ↓
Selects "Delete Comment"
       ↓
Show Confirmation Dialog
       ↓
   ┌─ User taps "Cancel" →
   │  Dismiss dialog
   │
   └─ User taps "Delete" →
      [Show Loading]
         ↓
      [API Call]
      DELETE /api/comments/:reply_id
         ↓
      ┌─ Success →
      │  - Remove comment from list (fade out)
      │  - Update comment count
      │  - Show success toast (optional)
      │
      └─ Error →
         - Keep comment visible
         - Show error toast
```

---

## 6. API Integration

### 6.1 API Endpoints Reference

All endpoints require Bearer token authentication:
```
Authorization: Bearer {auth_token}
```

#### GET /api/stream/posts
**Purpose:** Get posts list with engagement data

**Request:**
```
GET /api/stream/posts?page=1&limit=20&type=all
```

**Response:**
```json
{
  "success": true,
  "posts": [
    {
      "post_id": 4,
      "title": "Post Title",
      "description": "Post content...",
      "poster": {...},
      "likes_count": 24,
      "comments_count": 12,
      "is_liked": true,
      "created_at": "2025-10-10 10:00:00"
    }
  ],
  "pagination": {...}
}
```

**Mobile Usage:**
- Cache in list view
- Update counts optimistically on user actions
- Refresh on pull-to-refresh

---

#### GET /api/stream/posts/:id
**Purpose:** Get single post with engagement data

**Request:**
```
GET /api/stream/posts/4
```

**Response:**
```json
{
  "success": true,
  "post": {
    "post_id": 4,
    "title": "Post Title",
    "likes_count": 24,
    "comments_count": 12,
    "is_liked": true,
    ...
  }
}
```

**Mobile Usage:**
- Load on post detail screen navigation
- Update likes_count/is_liked after like/unlike

---

#### POST /api/stream/posts/:id/like
**Purpose:** Like a post

**Request:**
```
POST /api/stream/posts/4/like
```

**Response:**
```json
{
  "success": true,
  "message": "Post liked successfully",
  "data": {
    "post_id": 4,
    "likes_count": 25,
    "is_liked": true
  }
}
```

**Mobile Usage:**
- Optimistic update: Fill heart, increment count immediately
- On success: Keep changes
- On error: Revert UI, show toast

---

#### DELETE /api/stream/posts/:id/like
**Purpose:** Unlike a post

**Request:**
```
DELETE /api/stream/posts/4/like
```

**Response:**
```json
{
  "success": true,
  "message": "Post unliked successfully",
  "data": {
    "post_id": 4,
    "likes_count": 24,
    "is_liked": false
  }
}
```

**Mobile Usage:**
- Optimistic update: Unfill heart, decrement count immediately
- On success: Keep changes
- On error: Revert UI, show toast

---

#### GET /api/stream/posts/:id/likes
**Purpose:** Get list of users who liked (optional feature)

**Request:**
```
GET /api/stream/posts/4/likes?page=1&limit=20
```

**Response:**
```json
{
  "success": true,
  "likes": [
    {
      "user_id": 5,
      "full_name": "John Doe",
      "profile_picture": "...",
      "liked_at": "2025-10-10 09:00:00"
    }
  ],
  "pagination": {...}
}
```

**Mobile Usage (Optional):**
- Show users list when tapping on likes count
- Modal or new screen with user list

---

#### GET /api/posts/:post_id/comments
**Purpose:** Get comments for a post

**Request:**
```
GET /api/posts/4/comments?page=1&limit=20&sort=newest
```

**Parameters:**
- `page`: Page number (default: 1)
- `limit`: Items per page (default: 20, max: 100)
- `sort`: "newest" or "oldest" (default: newest)

**Response:**
```json
{
  "success": true,
  "post_id": 4,
  "comments": [
    {
      "reply_id": 15,
      "post_id": 4,
      "user_id": 5,
      "username": "john@example.com",
      "full_name": "John Doe",
      "profile_picture": "...",
      "role": "user",
      "content": "Great post!",
      "created_at": "2025-10-10 09:00:00",
      "updated_at": "2025-10-10 09:00:00",
      "is_edited": false,
      "is_own_comment": true
    }
  ],
  "pagination": {
    "total": 28,
    "page": 1,
    "limit": 20,
    "total_pages": 2,
    "has_next": true,
    "has_prev": false
  }
}
```

**Mobile Usage:**
- Post Detail: Load with limit=5 for top comments
- All Comments Screen: Load with limit=20, implement infinite scroll
- Use `is_own_comment` to show/hide edit/delete options

---

#### POST /api/posts/:post_id/comments
**Purpose:** Add a new comment

**Request:**
```
POST /api/posts/4/comments
Content-Type: application/json

{
  "content": "This is my comment text"
}
```

**Validation:**
- Content: Required, 1-1000 characters
- XSS protection applied on backend

**Response:**
```json
{
  "success": true,
  "message": "Comment added successfully",
  "data": {
    "reply_id": 16,
    "post_id": 4,
    "user_id": 5,
    "username": "john@example.com",
    "full_name": "John Doe",
    "content": "This is my comment text",
    "created_at": "2025-10-10 10:00:00",
    "updated_at": "2025-10-10 10:00:00",
    "is_deleted": false
  }
}
```

**Mobile Usage:**
- Show loading in modal
- On success: Close modal, prepend comment to list, update count
- On error: Show error in modal, allow retry

---

#### PUT /api/comments/:reply_id
**Purpose:** Edit an existing comment

**Request:**
```
PUT /api/comments/16
Content-Type: application/json

{
  "content": "Updated comment text"
}
```

**Authorization:**
- Only comment owner or admin can edit
- Backend validates ownership

**Response:**
```json
{
  "success": true,
  "message": "Comment updated successfully",
  "data": {
    "reply_id": 16,
    "content": "Updated comment text",
    "updated_at": "2025-10-10 10:15:00",
    ...
  }
}
```

**Mobile Usage:**
- Show loading in modal
- On success: Close modal, update comment in list, add "(edited)" label
- On error: Show error, allow retry

---

#### DELETE /api/comments/:reply_id
**Purpose:** Delete a comment (soft delete)

**Request:**
```
DELETE /api/comments/16
```

**Authorization:**
- Comment owner can delete their own
- Admin can delete any comment

**Response:**
```json
{
  "success": true,
  "message": "Comment deleted successfully"
}
```

**Mobile Usage:**
- Show loading overlay
- On success: Remove from list with fade animation, update count
- On error: Show error toast, keep comment visible

---

### 6.2 Error Handling

#### Common Error Responses

**401 Unauthorized:**
```json
{
  "success": false,
  "message": "Authentication required"
}
```
**Mobile Action:** Redirect to login screen

**403 Forbidden:**
```json
{
  "success": false,
  "message": "You can only edit your own comments"
}
```
**Mobile Action:** Show error toast, don't show edit option

**404 Not Found:**
```json
{
  "success": false,
  "message": "Comment not found"
}
```
**Mobile Action:** Remove comment from list, show toast

**400 Bad Request:**
```json
{
  "success": false,
  "message": "Comment content must be between 1 and 1000 characters"
}
```
**Mobile Action:** Show validation error in modal

**500 Server Error:**
```json
{
  "success": false,
  "message": "An error occurred while processing your request"
}
```
**Mobile Action:** Show generic error, allow retry

---

### 6.3 Optimistic UI Updates

Implement optimistic updates for better UX:

**Like/Unlike:**
```
1. User taps like button
2. Immediately update UI (fill heart, increment count)
3. Send API request in background
4. If success: Keep changes
5. If error: Revert changes, show toast
```

**Add Comment:**
```
1. User submits comment
2. Show loading in modal
3. Send API request
4. On success: 
   - Close modal
   - Add temporary comment to list (with loading indicator)
   - Replace with real comment when API returns
5. If error: Keep modal open, show error
```

**Delete Comment:**
```
1. User confirms delete
2. Immediately start fade-out animation
3. Send API request
4. If success: Complete removal
5. If error: Fade back in, show toast
```

---

## 7. UI/UX Specifications

### 7.1 Design System

#### Colors
```
Primary Accent: #007AFF (iOS) / #2196F3 (Android)
Like Color: #FF3B30 (Red)
Unlike Color: #8E8E93 (Gray)
Text Primary: #000000
Text Secondary: #6E6E73
Text Tertiary: #AEAEB2
Background: #F2F2F7
Card Background: #FFFFFF
Divider: #E5E5EA
Error: #FF3B30
Success: #34C759
```

#### Typography
```
Title: 20px Bold
Subtitle: 17px Regular
Body: 15px Regular
Caption: 13px Regular
Timestamp: 12px Regular (Gray)
```

#### Spacing
```
Screen Padding: 16px
Card Padding: 16px
Element Spacing: 8px
Section Spacing: 24px
```

#### Shadows
```
Card Shadow:
  - iOS: shadowOffset: (0, 2), shadowRadius: 8, shadowOpacity: 0.1
  - Android: elevation: 4dp

Button Shadow:
  - iOS: shadowOffset: (0, 4), shadowRadius: 12, shadowOpacity: 0.15
  - Android: elevation: 6dp
```

---

### 7.2 Animations

#### Like Animation
```
Duration: 300ms
Easing: ease-out
Sequence:
  1. Scale down to 0.8x (100ms)
  2. Scale up to 1.2x (100ms)
  3. Scale to 1.0x (100ms)
  4. Color transition (simultaneous)
```

#### Comment Delete Animation
```
Duration: 250ms
Easing: ease-in
Sequence:
  1. Fade opacity: 1.0 → 0.0
  2. Collapse height: full → 0
```

#### Modal Appearance
```
Duration: 300ms
Easing: ease-out
Animation:
  - iOS: Slide up from bottom
  - Android: Slide up with fade-in
```

#### Loading States
```
Skeleton Loading:
  - Shimmer effect for comment cards
  - Duration: 1500ms loop
  - Colors: #E0E0E0 → #F5F5F5 → #E0E0E0
```

---

### 7.3 Empty States

#### No Comments Yet
```
┌─────────────────────────────────────┐
│                                     │
│           💬                        │
│                                     │
│       No Comments Yet               │
│                                     │
│   Be the first to share your       │
│   thoughts on this post.           │
│                                     │
│  ┌─────────────────────────────┐  │
│  │     Add First Comment       │  │
│  └─────────────────────────────┘  │
│                                     │
└─────────────────────────────────────┘
```

#### No Likes Yet
```
When likes_count = 0:
- Show heart outline icon
- Display "0" or hide count
- Text: "Be the first to like"
```

#### Network Error
```
┌─────────────────────────────────────┐
│                                     │
│           ⚠️                        │
│                                     │
│     Couldn't Load Comments          │
│                                     │
│   Please check your connection     │
│   and try again.                   │
│                                     │
│  ┌─────────────────────────────┐  │
│  │        Try Again            │  │
│  └─────────────────────────────┘  │
│                                     │
└─────────────────────────────────────┘
```

---

### 7.4 Loading States

#### Skeleton Loader for Comments
```
┌──────────────────────────────┐
│ ⚪ ▓▓▓▓▓▓▓▓     ▓▓▓▓▓        │
│                              │
│ ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓      │
│ ▓▓▓▓▓▓▓▓▓▓▓▓▓                │
└──────────────────────────────┘
```

#### Inline Loaders
- Like button: Small spinner (16px) overlay
- Post comment: Button text changes to "Posting..." with spinner
- Pagination: Bottom loader (24px spinner)

---

### 7.5 Accessibility

#### Screen Reader Support
```
Like Button: 
  - Unlabled: "Like this post. 24 likes"
  - Labeled: "Unlike this post. 24 likes"

Comment Button:
  - Label: "View 12 comments"

Add Comment:
  - Label: "Add a comment"

Three-dot Menu:
  - Label: "Comment options"

Edit/Delete:
  - Label: "Edit comment" / "Delete comment"
```

#### Dynamic Type Support
- All text scales with system font size
- Minimum touch targets: 44x44 points (iOS) / 48x48 dp (Android)
- High contrast mode support

#### VoiceOver/TalkBack
- Logical reading order
- Group related elements
- Announce state changes ("Post liked", "Comment added")

---

## 8. Performance Requirements

### 8.1 Load Times
- Post list initial load: < 2 seconds
- Post detail load: < 1.5 seconds
- Comment submission: < 2 seconds
- Like/unlike action: < 500ms (optimistic) + background sync

### 8.2 Pagination
- Load 20 comments per page
- Preload next page when user scrolls to 80% of current list
- Cache previous pages in memory

### 8.3 Caching Strategy
```
Post List: 
  - Cache for 5 minutes
  - Invalidate on user action (like, comment)
  - Pull-to-refresh overwrites cache

Comments:
  - Cache per post for 3 minutes
  - Invalidate when user adds/edits/deletes
  - No cache for "All Comments" screen

Images:
  - Cache profile pictures for 24 hours
  - Use CDN URLs if available
```

### 8.4 Network Optimization
- Batch requests when possible
- Use HTTP/2 multiplexing
- Implement request debouncing (like button)
- Queue failed requests for retry

---

## 9. Analytics & Tracking

### 9.1 Events to Track

```
Event: post_liked
Parameters:
  - post_id
  - user_id
  - screen: "feed" / "detail"

Event: post_unliked
Parameters:
  - post_id
  - user_id
  - screen: "feed" / "detail"

Event: comment_viewed
Parameters:
  - post_id
  - comments_count
  - screen: "detail" / "all_comments"

Event: comment_added
Parameters:
  - post_id
  - comment_length
  - response_time_ms

Event: comment_edited
Parameters:
  - comment_id
  - original_length
  - new_length

Event: comment_deleted
Parameters:
  - comment_id
  - user_role: "owner" / "admin"

Event: show_more_comments_tapped
Parameters:
  - post_id
  - visible_comments
  - total_comments
```

---

## 10. Testing Requirements

### 10.1 Unit Tests
- Like/unlike state management
- Comment CRUD operations
- Validation logic (character count)
- API error handling
- Optimistic update rollback

### 10.2 UI Tests
- Like button animation
- Modal open/close
- Comment list pagination
- Pull-to-refresh
- Empty states
- Error states

### 10.3 Integration Tests
- Full like/unlike flow
- Add comment flow
- Edit comment flow
- Delete comment flow
- Pagination flow

### 10.4 Manual Test Cases

| Test Case | Steps | Expected Result |
|-----------|-------|-----------------|
| Like post from feed | 1. Tap heart icon<br>2. Observe changes | Heart fills, count increments, animation plays |
| Unlike post | 1. Tap filled heart<br>2. Observe changes | Heart empties, count decrements |
| View comments | 1. Tap comment icon<br>2. Check detail screen | Shows post + top 5 comments |
| Add comment | 1. Tap Add button<br>2. Type text<br>3. Submit | Modal opens, text validates, submits successfully |
| Edit own comment | 1. Tap three dots<br>2. Select Edit<br>3. Modify<br>4. Update | Pre-fills text, updates successfully, shows "(edited)" |
| Delete own comment | 1. Tap three dots<br>2. Select Delete<br>3. Confirm | Shows confirmation, deletes successfully |
| Admin delete | 1. Admin sees all menus<br>2. Delete any comment | Confirmation shown, deletes successfully |
| View all comments | 1. Tap "Show More"<br>2. Scroll to bottom | Navigates to new screen, loads more with pagination |
| Sort comments | 1. Open sort menu<br>2. Select option | Comments re-order correctly |
| Network error | 1. Disable network<br>2. Perform action | Shows error message, allows retry |
| Character limit | 1. Type > 1000 chars<br>2. Try to submit | Submit button disabled, shows error |

---

## 11. Security Considerations

### 11.1 Client-Side Validation
- Validate comment length (1-1000) before API call
- Sanitize input display (XSS protection)
- Validate auth token presence before actions

### 11.2 Authorization Checks
- Verify user owns comment before showing edit/delete
- Check admin role for delete-all permission
- Handle 403 errors gracefully

### 11.3 Data Privacy
- Don't cache sensitive user data
- Clear auth token on logout
- Use HTTPS for all API calls
- Implement certificate pinning (optional)

---

## 12. Platform-Specific Considerations

### 12.1 iOS Specifics
- Use SwiftUI modals with `.sheet` modifier
- Implement haptic feedback on like action
- Use SF Symbols for icons (heart.fill, bubble.left)
- Support dark mode
- Handle keyboard avoidance automatically

### 12.2 Android Specifics
- Use Material BottomSheet for modals
- Implement ripple effects on buttons
- Use Material Icons
- Support dark theme
- Handle soft keyboard with `windowSoftInputMode`

### 12.3 Cross-Platform (Flutter)
- Use platform-specific designs (Cupertino vs Material)
- Handle keyboard with `SingleChildScrollView`
- Use `showModalBottomSheet` for comments modal
- Implement platform-specific animations

---

## 13. Future Enhancements (Out of Scope)

### Phase 2 Features:
- Reply to comments (nested comments)
- Like individual comments
- Rich text formatting in comments
- Emoji reactions beyond likes
- Mention users with @ symbol
- Share comments
- Report inappropriate comments
- Push notifications for new comments
- Real-time comment updates (WebSocket)

---

## 14. Success Metrics

### 14.1 KPIs
- **Engagement Rate:** Increase in likes per post by 30%
- **Comment Rate:** Increase in comments per post by 40%
- **Response Time:** Like action < 500ms (perceived)
- **Error Rate:** < 1% of API calls fail
- **User Satisfaction:** 4.5+ rating for feature in app reviews

### 14.2 Monitoring
- Track API response times
- Monitor error rates per endpoint
- Track user engagement (likes/comments per session)
- Measure time spent on post detail screens
- Track modal abandonment rate

---

## 15. Dependencies

### 15.1 Backend APIs
- All 7 endpoints must be deployed and tested
- Authentication system must be functional
- Database migrations must be applied

### 15.2 Mobile App Requirements
- Networking library (Alamofire/Retrofit/Dio)
- Image loading library (Kingfisher/Glide/CachedNetworkImage)
- State management (Redux/Bloc/Provider)
- Navigation framework
- Analytics SDK

### 15.3 Design Assets
- Icon set (heart, chat, edit, delete, etc.)
- Loading animations
- Empty state illustrations
- Error state illustrations

---

## 16. Implementation Timeline

### Sprint 1 (Week 1-2): Feed Integration
- [ ] Implement like/unlike in post list
- [ ] Add engagement metrics display
- [ ] Implement optimistic updates
- [ ] Add error handling

### Sprint 2 (Week 3-4): Post Detail
- [ ] Create post detail screen
- [ ] Load top 5 comments
- [ ] Implement like on detail screen
- [ ] Add comment modal UI

### Sprint 3 (Week 5-6): Comment Management
- [ ] Implement add comment functionality
- [ ] Add edit comment feature
- [ ] Add delete comment feature
- [ ] Implement authorization logic

### Sprint 4 (Week 7-8): All Comments Screen
- [ ] Create all comments screen
- [ ] Implement pagination
- [ ] Add sort functionality
- [ ] Add FAB for quick comment

### Sprint 5 (Week 9): Polish & Testing
- [ ] Add animations
- [ ] Implement empty states
- [ ] Add analytics tracking
- [ ] Perform QA testing
- [ ] Fix bugs

---

## 17. Acceptance Criteria

### Feature Complete When:
- [x] Backend APIs integrated and working
- [ ] All user stories implemented
- [ ] Like/unlike works from feed and detail
- [ ] Comments display correctly
- [ ] Add/edit/delete comments functional
- [ ] Pagination works smoothly
- [ ] Admin permissions enforced
- [ ] All animations implemented
- [ ] Empty states designed
- [ ] Error handling complete
- [ ] Accessibility requirements met
- [ ] Performance targets achieved
- [ ] Analytics tracking active
- [ ] QA testing passed
- [ ] Product owner approval obtained

---

## 18. Appendix

### 18.1 API Base URL
```
Development: http://localhost:8000
Staging: https://staging-api.example.com
Production: https://api.example.com
```

### 18.2 Postman Collection
Import collection from: `docs/postman.json`

### 18.3 Design Mockups
See Figma: [Link to be added]

### 18.4 Backend PRD Reference
See: `docs/PRD_Comments_Likes_Feature.md`

---

## 19. Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | Oct 10, 2025 | Product Team | Initial draft |

---

## 20. Approval

**Product Manager:** ___________________ Date: __________

**Engineering Lead:** ___________________ Date: __________

**Design Lead:** ___________________ Date: __________

---

**Document Status:** ✅ Ready for Implementation  
**Priority:** High  
**Target Release:** Q4 2025
