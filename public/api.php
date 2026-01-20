<?php
// This file should be required by index.php which already has the $router variable defined
// The $router variable should be available from the requiring file

if (!isset($router) || !$router instanceof \Core\Router) {
    die('Router not properly initialized');
}

// Health Check
$router->get('/api/health', 'HealthController@index');

// Auth & Onboarding
$router->post('/api/auth/google', 'AuthController@googleAuth');
$router->post('/api/auth/apple', 'AuthController@appleAuth');
$router->post('/api/send-verification-otp', 'AuthController@sendVerificationOTP');
$router->post('/api/verify-email-apple-signin', 'AuthController@verifyEmailAppleSignin');
$router->post('/api/auth/admin-login', 'AuthController@adminLogin');
$router->post('/api/auth/logout', 'AuthController@logout');
$router->post('/api/auth/change-password', 'AuthController@changePassword');
$router->delete('/api/auth/self-delete', 'AuthController@selfDelete');
$router->get('/api/me', 'AuthController@me');
$router->post('/api/onboarding/complete', 'AuthController@completeOnboarding');
$router->post('/api/onboarding/reset', 'AuthController@resetOnboarding');
$router->get('/api/onboarding/status', 'AuthController@onboardingStatus');

// Device Token Management
$router->put('/api/auth/device-token', 'AuthController@updateDeviceToken');
$router->get('/api/auth/device-token', 'AuthController@getDeviceTokenStatus');

// User Permissions APIs (Security)
$router->get('/api/user/permissions', 'UserPermissionsController@getUserPermissions');
$router->get('/api/user/module/:module/access', 'UserPermissionsController@checkModuleAccess');

// User Profile APIs
$router->get('/api/user/profile', 'ProfileController@getProfile');
$router->put('/api/user/profile', 'ProfileController@updateProfile');
$router->post('/api/user/profile/picture', 'ProfileController@uploadProfilePicture');
$router->post('/api/user/admin-request', 'ProfileController@requestAdminRole');

// Legacy Profile APIs (for backward compatibility)
$router->put('/api/profile/basic', 'ProfileController@updateBasic');
$router->put('/api/profile/professional', 'ProfileController@updateProfessional');
$router->get('/api/profile/:id', 'ProfileController@getPublic');

// Stream APIs
$router->get('/api/stream/posts', 'StreamController@getPosts');
$router->post('/api/stream/posts', 'StreamController@createPost');
$router->get('/api/stream/posts/:id', 'StreamController@getPost');
$router->put('/api/stream/posts/:id', 'StreamController@updatePost');
$router->patch('/api/stream/posts/:id/announcement', 'StreamController@toggleAnnouncement');
$router->delete('/api/stream/posts/:id', 'StreamController@deletePost');

// Likes APIs
$router->post('/api/stream/posts/:id/like', 'StreamController@likePost');
$router->delete('/api/stream/posts/:id/like', 'StreamController@unlikePost');
$router->get('/api/stream/posts/:id/likes', 'StreamController@getPostLikes');

// Comments APIs
$router->get('/api/posts/:post_id/comments', 'CommentController@getComments');
$router->post('/api/posts/:post_id/comments', 'CommentController@addComment');
$router->put('/api/comments/:reply_id', 'CommentController@editComment');
$router->delete('/api/comments/:reply_id', 'CommentController@deleteComment');

// Legacy Stream APIs (for backward compatibility)
$router->get('/api/stream', 'StreamController@index');
$router->post('/api/stream', 'StreamController@create');

// Subject & Academic APIs
$router->get('/api/semester/subjects', 'SubjectController@getSubjects');
$router->get('/api/semester/subjects/:id', 'SubjectController@getSubjectDetails');
$router->post('/api/semester/subjects/:id/notes', 'SubjectController@uploadNote');
$router->get('/api/semester/subjects/:id/notes', 'SubjectController@getSubjectNotes');
$router->delete('/api/semester/subjects/:subject_id/notes/:note_id', 'SubjectController@deleteNote');
// Schedule APIs - User can view tab-wise schedules
$router->get('/api/semester/schedules', 'ScheduleController@getSchedules');
$router->get('/api/semester/schedules/:id', 'ScheduleController@getSchedule');

// Admin Schedule Management APIs
$router->post('/api/admin/schedules', 'ScheduleController@createSchedule');
$router->put('/api/admin/schedules/:id', 'ScheduleController@updateSchedule');
$router->delete('/api/admin/schedules/:id', 'ScheduleController@deleteSchedule');
$router->get('/api/admin/schedules/subjects', 'ScheduleController@getSubjectsForSchedule');

// Legacy Subject APIs (for backward compatibility)
$router->get('/api/subjects', 'SubjectController@index');
$router->post('/api/subjects', 'SubjectController@create');
$router->get('/api/subjects/:id', 'SubjectController@show');
$router->post('/api/subjects/:id/enroll', 'SubjectController@enroll');
$router->get('/api/subjects/:id/notes', 'SubjectController@notes');
$router->post('/api/subjects/:id/notes', 'SubjectController@addNote');

// Network APIs
$router->get('/api/network/users', 'NetworkController@getUsers');
$router->get('/api/network/users/:id', 'NetworkController@getUserProfile');
$router->get('/api/network/search', 'NetworkController@searchUsers');
$router->get('/api/network/filters', 'NetworkController@getFilters');

// Legacy Network APIs (for backward compatibility)
$router->post('/api/connect/:user_id', 'NetworkController@connectOrAccept');
$router->get('/api/network', 'NetworkController@list');

// Announcement APIs - Show announcement posts from stream
$router->get('/api/announcements', 'StreamController@getAnnouncements');

// User announcement management
$router->get('/api/user/announcements', 'AnnouncementController@getUserNotifications');
$router->patch('/api/user/announcements/:id/read', 'AnnouncementController@markAsRead');
$router->patch('/api/user/announcements/read-all', 'AnnouncementController@markAllAsRead');
$router->delete('/api/user/announcements/:id', 'AnnouncementController@deleteNotification');

// Legacy Announcement APIs (for backward compatibility)
$router->post('/api/announcements', 'AnnouncementController@create');

// Admin APIs
$router->post('/api/admin/create-super-admin', 'AdminController@createSuperAdmin');
$router->post('/api/admin/setup-database', 'AdminController@setupDatabase');
$router->get('/api/admin/quick-setup', 'AdminController@quickSetup');
$router->get('/api/admin/clear-all-data', 'AdminController@clearAllData');
$router->get('/api/admin/list', 'AdminController@getAdminList');
$router->get('/api/admin/requests', 'AdminController@getAdminRequests');
$router->patch('/api/admin/requests/:id', 'AdminController@updateAdminRequest');

// Class Status Management APIs
$router->get('/api/admin/class-status', 'AdminController@getClassStatuses');
$router->post('/api/admin/class-status', 'AdminController@updateClassStatus');

// User Management APIs (Super Admin only)
$router->delete('/api/admin/users/:id', 'UserManagementController@deleteUser');
$router->post('/api/admin/users/:id/restore', 'UserManagementController@restoreUser');
$router->patch('/api/admin/users/:id/block', 'UserManagementController@blockUser');
$router->patch('/api/admin/users/:id/unblock', 'UserManagementController@unblockUser');
$router->patch('/api/admin/users/:id/demote', 'AdminController@demoteAdminToUser');
$router->get('/api/admin/users/deleted', 'UserManagementController@getDeletedUsers');
$router->get('/api/admin/users/blocked', 'UserManagementController@getBlockedUsers');
$router->get('/api/admin/users/:id/status', 'UserManagementController@getUserStatus');
$router->patch('/api/admin/users/:id/delete-block', 'UserManagementController@deleteOrBlockUser');

// Admin Subject Management APIs
$router->post('/api/admin/subjects', 'SubjectController@createSubject');
$router->put('/api/admin/subjects/:id', 'SubjectController@updateSubject');
$router->delete('/api/admin/subjects/:id', 'SubjectController@deleteSubject');
$router->get('/api/admin/subjects/faculty', 'SubjectController@getFacultyUsers');

// Faculty Management APIs (Super Admin only)
$router->post('/api/admin/faculty', 'UserManagementController@createFaculty');
$router->get('/api/admin/faculty', 'UserManagementController@listFaculty');
$router->get('/api/admin/faculty/:id', 'UserManagementController@getFacultyDetails');
$router->put('/api/admin/faculty/:id', 'UserManagementController@updateFaculty');
$router->delete('/api/admin/faculty/:id', 'UserManagementController@deleteFaculty');

// Legacy Schedule APIs (for backward compatibility)
$router->get('/api/schedule', 'ScheduleController@index');
$router->post('/api/schedule', 'ScheduleController@create');

// Expertise Management APIs
$router->get('/api/expertise', 'ExpertiseController@getAllExpertise');
$router->get('/api/expertise/:id', 'ExpertiseController@getExpertiseById');
$router->post('/api/expertise', 'ExpertiseController@createExpertise');
$router->post('/api/expertise/bulk', 'ExpertiseController@createBulkExpertise');
$router->put('/api/expertise/:id', 'ExpertiseController@updateExpertise');
$router->delete('/api/expertise/:id', 'ExpertiseController@deleteExpertise');

// Notification Preferences APIs
$router->get('/api/notifications/preferences', 'NotificationPreferenceController@getPreferences');
$router->put('/api/notifications/preferences', 'NotificationPreferenceController@updatePreferences');

// Today's Classes API
$router->get('/api/today-classes', 'ScheduleController@getTodayClasses');
