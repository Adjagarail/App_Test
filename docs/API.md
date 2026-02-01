# API Documentation

## Base URL
```
http://localhost:8080/api
```

## Authentication
All protected endpoints require a JWT token in the Authorization header:
```
Authorization: Bearer <token>
```

## Error Response Format
```json
{
  "error": {
    "code": "ERROR_CODE",
    "message": "Human readable message",
    "details": { /* optional additional info */ }
  }
}
```

## HTTP Status Codes
- `200` - Success (GET, PATCH)
- `201` - Created (POST)
- `204` - No Content (DELETE)
- `400` - Bad Request (validation errors)
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `409` - Conflict
- `429` - Too Many Requests

---

## Authentication Endpoints

### POST /login_check
Login and get JWT token.

**Body:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response 200:**
```json
{
  "token": "eyJ...",
  "refresh_token": "abc123..."
}
```

### POST /token/refresh
Refresh JWT token.

**Body:**
```json
{
  "refresh_token": "abc123..."
}
```

**Response 200:**
```json
{
  "token": "eyJ...",
  "refresh_token": "new_token..."
}
```

---

## Registration Endpoints

### POST /register
Create new user account.

**Body:**
```json
{
  "email": "user@example.com",
  "password": "password123",
  "nomComplet": "John Doe"
}
```

**Response 201:**
```json
{
  "message": "Utilisateur créé avec succès. Un email de vérification a été envoyé.",
  "user": {
    "id": 1,
    "email": "user@example.com",
    "roles": ["ROLE_USER"],
    "nomComplet": "John Doe",
    "isEmailVerified": false
  }
}
```

**Errors:**
- `400 MISSING_FIELDS` - Email and password required
- `400 INVALID_EMAIL` - Invalid email format
- `400 PASSWORD_TOO_SHORT` - Password must be at least 6 characters
- `409 EMAIL_EXISTS` - Email already in use

---

## Email Verification (Feature 7)

### GET /auth/verify-email?token=...
Verify email address.

**Query Parameters:**
- `token` (required) - Verification token from email

**Response 200:**
```json
{
  "message": "Votre email a été vérifié avec succès",
  "verified": true
}
```

### POST /auth/resend-verification
Resend verification email.

**Body:**
```json
{
  "email": "user@example.com"
}
```

**Response 200:**
```json
{
  "message": "Si l'adresse email existe et n'est pas vérifiée, un email de vérification a été envoyé"
}
```

**Errors:**
- `429 RATE_LIMITED` - Wait before requesting another email

---

## User Profile Endpoints (Feature 3)

### PATCH /me
Update user profile (email is read-only).

**Auth:** Required

**Body:**
```json
{
  "nomComplet": "New Name"
}
```

**Response 200:**
```json
{
  "message": "Profil mis à jour avec succès",
  "user": {
    "id": 1,
    "email": "user@example.com",
    "nomComplet": "New Name",
    "roles": ["ROLE_USER"],
    "dateInscription": "2024-01-15T10:30:00+00:00",
    "dateDerniereConnexion": "2024-01-20T14:00:00+00:00",
    "isEmailVerified": true
  }
}
```

### PATCH /me/password
Change password (forces re-login).

**Auth:** Required

**Body:**
```json
{
  "currentPassword": "oldpassword",
  "newPassword": "newpassword123",
  "confirmNewPassword": "newpassword123"
}
```

**Response 200:**
```json
{
  "message": "Mot de passe modifié avec succès. Veuillez vous reconnecter.",
  "requiresRelogin": true
}
```

**Errors:**
- `400 MISSING_FIELDS` - All fields required
- `400 PASSWORD_MISMATCH` - New passwords don't match
- `400 PASSWORD_TOO_SHORT` - Minimum 6 characters
- `400 INVALID_CURRENT_PASSWORD` - Wrong current password

---

## Account Deletion Request (Feature 2)

### POST /me/requests/delete-account
Request account deletion.

**Auth:** Required

**Body:**
```json
{
  "reason": "Optional reason for deletion"
}
```

**Response 201:**
```json
{
  "message": "Demande de suppression envoyée",
  "request": {
    "id": 1,
    "status": "PENDING",
    "createdAt": "2024-01-20T10:00:00+00:00"
  }
}
```

**Errors:**
- `409 REQUEST_ALREADY_EXISTS` - Pending request already exists

---

## Notifications (Feature 8)

### GET /me/notifications
Get user notifications with pagination.

**Auth:** Required

**Query Parameters:**
- `page` (default: 1)
- `limit` (default: 20, max: 100)
- `unread` (optional: true|false)

**Response 200:**
```json
{
  "notifications": [
    {
      "id": 1,
      "type": "PASSWORD_CHANGED",
      "payload": {
        "title": "Mot de passe modifié",
        "message": "Votre mot de passe a été modifié avec succès"
      },
      "isRead": false,
      "createdAt": "2024-01-20T10:00:00+00:00",
      "readAt": null
    }
  ],
  "unreadCount": 5,
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 50,
    "totalPages": 3
  }
}
```

### PATCH /me/notifications/{id}
Mark notification as read.

**Auth:** Required

**Response 200:**
```json
{
  "message": "Notification marquée comme lue",
  "notification": {
    "id": 1,
    "isRead": true,
    "readAt": "2024-01-20T11:00:00+00:00"
  }
}
```

### PATCH /me/notifications/mark-all-read
Mark all notifications as read.

**Auth:** Required

**Response 200:**
```json
{
  "message": "Toutes les notifications ont été marquées comme lues",
  "count": 5
}
```

---

## Data Export (Feature 10)

### GET /me/export
Export all user data as JSON.

**Auth:** Required

**Response 200:**
```json
{
  "exportedAt": "2024-01-20T10:00:00+00:00",
  "user": {
    "id": 1,
    "email": "user@example.com",
    "nomComplet": "John Doe",
    "roles": ["ROLE_USER"],
    "dateInscription": "2024-01-01T00:00:00+00:00",
    "dateDerniereConnexion": "2024-01-20T09:00:00+00:00",
    "isEmailVerified": true,
    "emailVerifiedAt": "2024-01-01T01:00:00+00:00"
  },
  "notifications": [...],
  "activityLog": [...]
}
```

**Errors:**
- `429 RATE_LIMITED` - Can only export once per hour

---

## Admin Endpoints

All admin endpoints require `ROLE_ADMIN`.

### GET /admin/users (Feature 9)
List users with search, filters, and pagination.

**Query Parameters:**
- `page` (default: 1)
- `limit` (default: 20, max: 100)
- `q` or `search` - Search by email or name
- `role` - Filter by role (e.g., ROLE_ADMIN)
- `inactiveSinceDays` - Filter inactive users
- `sort` - Sort by: createdAt, lastLogin, name, email
- `direction` - asc or desc
- `includeDeleted` - Include soft-deleted users

**Response 200:**
```json
{
  "users": [
    {
      "id": 1,
      "email": "user@example.com",
      "nomComplet": "John Doe",
      "roles": ["ROLE_USER"],
      "dateInscription": "2024-01-01T00:00:00+00:00",
      "dateDerniereConnexion": "2024-01-20T09:00:00+00:00",
      "isEmailVerified": true,
      "emailVerifiedAt": "2024-01-01T01:00:00+00:00",
      "isSuspended": false,
      "suspendedUntil": null,
      "suspensionReason": null,
      "isDeleted": false,
      "deletedAt": null
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 100,
    "totalPages": 5
  }
}
```

### GET /admin/users/{id} (Feature 1)
Get user profile for admin.

**Response 200:**
```json
{
  "user": { /* same structure as above */ }
}
```

### DELETE /admin/users/{id} (Feature 1, 6)
Soft delete a user.

**Response 200:**
```json
{
  "message": "Utilisateur supprimé avec succès"
}
```

**Errors:**
- `403 CANNOT_DELETE_SELF`
- `403 LAST_ADMIN` - Cannot delete last admin
- `404 USER_NOT_FOUND`

### PATCH /admin/users/{id}/roles (Feature 5)
Update user roles.

**Body:**
```json
{
  "roles": ["ROLE_USER", "ROLE_MODERATOR"]
}
```

**Response 200:**
```json
{
  "message": "Rôles mis à jour avec succès",
  "user": { /* full user object */ }
}
```

**Errors:**
- `403 CANNOT_MODIFY_SELF`
- `403 CANNOT_GRANT_SUPER_ADMIN`
- `403 LAST_ADMIN`
- `400 INVALID_ROLES`

### PATCH /admin/users/{id}/suspend (Feature 11)
Suspend a user account.

**Body:**
```json
{
  "reason": "Violation of terms",
  "suspendedUntil": "2024-02-01T00:00:00+00:00"
}
```

**Response 200:**
```json
{
  "message": "Utilisateur suspendu avec succès",
  "user": { /* full user object */ }
}
```

### PATCH /admin/users/{id}/unsuspend (Feature 11)
Unsuspend a user account.

**Response 200:**
```json
{
  "message": "Utilisateur réactivé avec succès",
  "user": { /* full user object */ }
}
```

### GET /admin/audit (Feature 4)
Get audit logs for a user.

**Query Parameters:**
- `userId` (required)
- `page` (default: 1)
- `limit` (default: 20, max: 100)

**Response 200:**
```json
{
  "logs": [
    {
      "id": 1,
      "action": "LOGIN",
      "actorUser": {
        "id": 1,
        "email": "user@example.com"
      },
      "metadata": null,
      "ip": "192.168.1.1",
      "userAgent": "Mozilla/5.0...",
      "createdAt": "2024-01-20T10:00:00+00:00"
    }
  ],
  "pagination": { /* ... */ }
}
```

### GET /admin/requests (Feature 2)
List account action requests.

**Query Parameters:**
- `status` - PENDING, APPROVED, REJECTED
- `type` - DELETE_ACCOUNT
- `page`, `limit`

**Response 200:**
```json
{
  "requests": [
    {
      "id": 1,
      "type": "DELETE_ACCOUNT",
      "status": "PENDING",
      "user": {
        "id": 5,
        "email": "user@example.com",
        "nomComplet": "John Doe"
      },
      "message": "User's reason",
      "createdAt": "2024-01-20T10:00:00+00:00",
      "handledAt": null,
      "handledBy": null
    }
  ],
  "pagination": { /* ... */ }
}
```

### PATCH /admin/requests/{id} (Feature 2)
Handle a request (approve/reject).

**Body:**
```json
{
  "status": "APPROVED",
  "message": "Optional message for rejection"
}
```

**Response 200:**
```json
{
  "message": "Demande traitée avec succès",
  "request": {
    "id": 1,
    "status": "APPROVED",
    "handledAt": "2024-01-20T11:00:00+00:00"
  }
}
```

### GET /admin/roles
Get available roles.

**Response 200:**
```json
{
  "roles": [
    {"code": "ROLE_USER", "label": "Utilisateur", "description": "..."},
    {"code": "ROLE_SEMI_ADMIN", "label": "Semi-Admin", "description": "..."},
    {"code": "ROLE_MODERATOR", "label": "Modérateur", "description": "..."},
    {"code": "ROLE_ANALYST", "label": "Analyste", "description": "..."},
    {"code": "ROLE_ADMIN", "label": "Administrateur", "description": "..."}
  ]
}
```

---

## Impersonation (Feature 12)

Requires `ROLE_SUPER_ADMIN`.

### POST /admin/users/{id}/impersonate
Start impersonating a user.

**Body:**
```json
{
  "reason": "Support request #12345"
}
```

**Response 200:**
```json
{
  "message": "Impersonation démarrée",
  "impersonation": {
    "id": "uuid-here",
    "targetUser": {
      "id": 5,
      "email": "user@example.com",
      "nomComplet": "John Doe"
    },
    "impersonatorId": 1,
    "expiresAt": "2024-01-20T10:10:00+00:00"
  },
  "impersonationToken": "eyJ..."
}
```

**Errors:**
- `403 CANNOT_IMPERSONATE_SELF`
- `403 CANNOT_IMPERSONATE_ADMIN`
- `403 USER_NOT_ACTIVE`
- `409 SESSION_ALREADY_EXISTS`

### POST /admin/impersonation/stop
Stop current impersonation.

**Response 200:**
```json
{
  "message": "Impersonation terminée",
  "sessionId": "uuid-here"
}
```

### GET /admin/impersonation/status
Get current impersonation status.

**Response 200:**
```json
{
  "isImpersonating": true,
  "session": {
    "id": "uuid-here",
    "targetUser": { /* ... */ },
    "createdAt": "2024-01-20T10:00:00+00:00",
    "expiresAt": "2024-01-20T10:10:00+00:00"
  }
}
```

---

## Password Reset

### POST /password/forgot
Request password reset email.

**Body:**
```json
{
  "email": "user@example.com"
}
```

**Response 200:**
```json
{
  "message": "Si l'email existe, un lien de réinitialisation a été envoyé"
}
```

### GET /password/verify/{token}
Verify reset token.

**Response 200:**
```json
{
  "valid": true,
  "email": "user@example.com"
}
```

### POST /password/reset
Reset password with token.

**Body:**
```json
{
  "token": "reset-token-here",
  "password": "newpassword123"
}
```

**Response 200:**
```json
{
  "message": "Mot de passe réinitialisé avec succès"
}
```

---

## Notification Types

| Type | Description |
|------|-------------|
| `ACCOUNT_DELETE_REQUESTED` | Admin notification when user requests deletion |
| `ACCOUNT_DELETE_APPROVED` | User notification when deletion approved |
| `ACCOUNT_DELETE_REJECTED` | User notification when deletion rejected |
| `PASSWORD_CHANGED` | Password was changed |
| `ROLES_UPDATED` | User roles were modified |
| `ACCOUNT_SUSPENDED` | Account was suspended |
| `ACCOUNT_UNSUSPENDED` | Account was reactivated |
| `EMAIL_VERIFIED` | Email was verified |
| `NEW_LOGIN` | New login detected |
| `SYSTEM` | System notification |

---

## Audit Log Actions

| Action | Description |
|--------|-------------|
| `LOGIN` | Successful login |
| `LOGOUT` | User logout |
| `LOGIN_FAILED` | Failed login attempt |
| `PASSWORD_CHANGE` | Password changed |
| `PASSWORD_RESET_REQUEST` | Password reset requested |
| `PASSWORD_RESET_COMPLETE` | Password reset completed |
| `DELETE_REQUEST` | Account deletion requested |
| `DELETE_REQUEST_APPROVED` | Deletion request approved |
| `DELETE_REQUEST_REJECTED` | Deletion request rejected |
| `SOFT_DELETE` | Account soft deleted |
| `HARD_DELETE` | Account permanently deleted |
| `ROLES_UPDATED` | User roles changed |
| `SUSPENDED` | Account suspended |
| `UNSUSPENDED` | Account unsuspended |
| `IMPERSONATION_START` | Impersonation started |
| `IMPERSONATION_STOP` | Impersonation stopped |
| `EMAIL_VERIFIED` | Email verified |
| `EMAIL_VERIFICATION_SENT` | Verification email sent |
| `DATA_EXPORTED` | User data exported |
| `PROFILE_UPDATED` | Profile updated |
