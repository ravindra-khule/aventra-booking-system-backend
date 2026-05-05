# JWT Authentication Implementation Guide

## Overview

This document describes the JWT (JSON Web Token) based authentication system implemented for the Aventra Booking System. The system provides secure, stateless authentication with token refresh capabilities.

## Architecture

### Components

1. **JWT Handler** (`lib/JWTHandler.php`)
   - Generates JWT tokens with HS256 algorithm
   - Verifies and decodes JWT tokens
   - Handles token expiration

2. **Auth Middleware** (`lib/AuthMiddleware.php`)
   - Verifies JWT tokens in requests
   - Manages token blacklist for logout
   - Provides role-based access control (RBAC)

3. **Backend APIs**
   - `POST /api/login.php` - Authenticate and get JWT token
   - `POST /api/logout.php` - Invalidate token
   - `GET /api/auth/me.php` - Verify token and get user data
   - `POST /api/auth/refresh.php` - Refresh JWT token

4. **Frontend Service** (`src/shared/services/auth.service.ts`)
   - Manages login/logout flow
   - Stores token in localStorage
   - Provides token refresh and verification

## Token Structure

### JWT Payload

```json
{
  "user_id": 1,
  "id": "1",
  "name": "John Doe",
  "email": "john@example.com",
  "role": "ADMIN",
  "status": "ACTIVE",
  "type": "access_token",
  "iat": 1704067200,
  "exp": 1704153600
}
```

**Fields:**
- `user_id` - Database user ID (integer)
- `id` - User ID as string
- `name` - User full name
- `email` - User email address
- `role` - User role (SUPER_ADMIN, ADMIN, SUPPORT, ACCOUNTANT, DEVELOPER)
- `status` - User status (ACTIVE, INACTIVE, SUSPENDED, PENDING)
- `type` - Token type (access_token, refresh_token)
- `iat` - Token issued at (Unix timestamp)
- `exp` - Token expiration time (Unix timestamp)

### Token Expiration

- **Access Token**: 24 hours
- **Token Validation**: Checked on every API request
- **Token Blacklist**: Tokens are stored in `storage/token_blacklist.json` upon logout

## Authentication Flow

### Login Flow

```
1. User submits email and password
   ↓
2. Frontend calls: POST /api/login.php
   Request: { email, password }
   ↓
3. Backend validates credentials
   - Checks if user exists
   - Verifies password with bcrypt
   - Checks if user is ACTIVE
   ↓
4. Backend generates JWT token
   - Creates token payload with user data
   - Sets 24-hour expiration
   - Uses HS256 signature
   ↓
5. Returns response with token
   Response: { id, name, email, role, token, expiresIn }
   ↓
6. Frontend stores token and user data in localStorage
   - localStorage['auth_token'] = JWT
   - localStorage['user'] = JSON user data
   - localStorage['token_expires_at'] = expiration timestamp
```

### API Request Flow

```
1. Frontend makes API request with Authorization header
   Headers: { Authorization: "Bearer <JWT>" }
   ↓
2. Backend receives request
   - Extracts token from Authorization header
   - Verifies JWT signature
   - Checks token expiration
   - Checks if token is blacklisted
   ↓
3. If valid:
   - Extracts user data from token
   - Processes request
   - Returns response
   
4. If invalid:
   - Returns 401 Unauthorized
   - Frontend clears localStorage
   - User redirected to login
```

### Logout Flow

```
1. User clicks logout button
   ↓
2. Frontend calls: POST /api/logout.php
   Headers: { Authorization: "Bearer <JWT>" }
   ↓
3. Backend:
   - Verifies JWT token signature
   - Adds token to blacklist
   - Returns success response
   ↓
4. Frontend clears localStorage
   - Removes auth_token
   - Removes user data
   - Removes token_expires_at
```

### Token Refresh Flow

```
1. Frontend detects token about to expire (within 1 hour)
   ↓
2. Frontend calls: POST /api/auth/refresh.php
   Headers: { Authorization: "Bearer <old_JWT>" }
   ↓
3. Backend:
   - Verifies old token signature
   - Checks if user still active
   - Generates new token
   - Returns new token
   ↓
4. Frontend stores new token
   - Updates localStorage['auth_token']
   - Updates localStorage['token_expires_at']
```

## Security Features

### Token Security

1. **HMAC-SHA256 Signature**
   - Tokens are signed with a server secret
   - Secret should be set via environment variable: `JWT_SECRET`
   - Default secret in code (MUST be changed in production)

2. **Expiration**
   - Tokens include expiration timestamp (`exp`)
   - Backend validates expiration on every request
   - Expired tokens are rejected with 401 Unauthorized

3. **Signature Verification**
   - Every token signature is verified
   - Modified tokens are rejected
   - Uses `hash_equals()` for timing-safe comparison

### Token Blacklist

1. **Logout Invalidation**
   - When user logs out, token is added to blacklist
   - File: `storage/token_blacklist.json`
   - Contains mapping of token → expiration timestamp

2. **Cleanup**
   - Expired tokens (older than 48 hours) are automatically removed
   - Prevents blacklist from growing indefinitely

3. **Stateless Validation**
   - Optional: Can implement Redis-based blacklist for scalability
   - Current implementation uses JSON file for simplicity

### Password Security

1. **Password Hashing**
   - Passwords are hashed with bcrypt (`PASSWORD_BCRYPT`)
   - Never stored in plain text
   - Verified using `password_verify()` function

2. **Brute Force Protection**
   - Optional: Can implement rate limiting
   - Currently relies on database validation

## Implementation Details

### Backend Files

#### `/lib/JWTHandler.php`
- `generateToken($payload, $expiresIn)` - Generate JWT token
- `verifyToken($token)` - Verify and decode token
- `getTokenFromHeader()` - Extract token from Authorization header

#### `/lib/AuthMiddleware.php`
- `verifyToken()` - Verify token and die on failure
- `verifyTokenWithRole($roles)` - Verify token and check user role
- `blacklistToken($token, $expiresAt)` - Add token to blacklist
- `getCurrentUser()` - Get user from token without dying

#### `/public/api/login.php`
- Authenticates user with email/password
- Generates JWT token
- Returns user data with token

#### `/public/api/logout.php`
- Validates JWT token
- Adds token to blacklist
- Clears user session

#### `/public/api/auth/me.php`
- Verifies JWT token
- Returns current user data
- Checks if user is still active

#### `/public/api/auth/refresh.php`
- Verifies current token
- Generates new token
- Returns new token for storage

### Frontend Implementation

#### `src/shared/services/auth.service.ts`

```typescript
// Basic methods
AuthService.login(email, password) // Returns user with token
AuthService.logout() // Clears token and user data
AuthService.getCurrentUser() // Get user from localStorage
AuthService.getToken() // Get JWT token string

// Verification and refresh
AuthService.verifyToken() // Verify with backend
AuthService.refreshToken() // Get new token
AuthService.checkAndRefreshToken(warningTime) // Auto-refresh if expiring

// Utilities
AuthService.getAuthHeader() // Returns "Bearer <token>"
AuthService.isAuthenticated() // Check if logged in
AuthService.isAdmin() // Check if user is admin
```

## API Endpoints

### POST /api/login.php

**Request:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "id": "1",
    "name": "John Doe",
    "email": "user@example.com",
    "role": "ADMIN",
    "status": "ACTIVE",
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expiresIn": 86400
  }
}
```

**Error (401):**
```json
{
  "success": false,
  "error": "Email or password incorrect"
}
```

### POST /api/logout.php

**Request:**
```
Headers: Authorization: Bearer <jwt_token>
```

**Response (200):**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

**Error (401):**
```json
{
  "success": false,
  "error": "Invalid or expired token"
}
```

### GET /api/auth/me.php

**Request:**
```
Headers: Authorization: Bearer <jwt_token>
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "1",
    "name": "John Doe",
    "email": "user@example.com",
    "role": "ADMIN",
    "status": "ACTIVE"
  }
}
```

**Error (401):**
```json
{
  "success": false,
  "error": "Invalid or expired token"
}
```

### POST /api/auth/refresh.php

**Request:**
```
Headers: Authorization: Bearer <current_jwt_token>
```

**Response (200):**
```json
{
  "success": true,
  "message": "Token refreshed successfully",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expiresIn": 86400
  }
}
```

**Error (401):**
```json
{
  "success": false,
  "error": "Invalid or expired token"
}
```

## Usage Examples

### Frontend Login

```typescript
import { AuthService } from './src/shared/services/auth.service';

// Login
const response = await AuthService.login('user@example.com', 'password123');
console.log(response); // { id, name, email, role, token, expiresIn }

// Token is automatically stored in localStorage
// Make authenticated API calls
const headers = {
  'Authorization': AuthService.getAuthHeader(), // "Bearer <token>"
  'Content-Type': 'application/json'
};

// Check if authenticated
if (AuthService.isAuthenticated()) {
  const user = AuthService.getCurrentUser();
  console.log(user);
}

// Logout
await AuthService.logout();
```

### Backend Using Middleware

```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/AuthMiddleware.php';

// Verify token and get user (dies with 401 if invalid)
$user = AuthMiddleware::verifyToken();

// Use user data
echo "User ID: " . $user['user_id'];
echo "User role: " . $user['role'];

// Or verify specific role
try {
  $adminUser = AuthMiddleware::verifyTokenWithRole(['ADMIN', 'SUPER_ADMIN']);
} catch (Exception $e) {
  // User doesn't have required role
}

// Or get user without dying
$user = AuthMiddleware::getCurrentUser();
if ($user) {
  // User is authenticated
}
?>
```

## Security Best Practices

### Production Setup

1. **Set JWT Secret**
   ```bash
   export JWT_SECRET="your-long-random-secret-key-here"
   ```

2. **Use HTTPS**
   - Always transmit tokens over HTTPS
   - Prevents token interception

3. **CORS Configuration**
   - Restrict allowed origins
   - Don't allow credentials from all domains

4. **Token Blacklist Storage**
   - Use Redis for distributed systems
   - Use database for single-server setups
   - Current JSON file implementation is for development only

5. **Environment Variables**
   ```bash
   JWT_SECRET=your-secret-key
   DB_HOST=your-db-host
   DB_USER=your-db-user
   DB_PASS=your-db-password
   DB_NAME=aventra_db
   ```

### Frontend Best Practices

1. **Store Token Securely**
   - Current: localStorage (accessible via JavaScript)
   - Better: httpOnly cookie (harder to steal via XSS)
   - Never expose token in URL

2. **Auto Refresh**
   - Call `AuthService.checkAndRefreshToken()` periodically
   - Refresh before token expires

3. **Handle Errors**
   - Catch 401 errors and redirect to login
   - Clear localStorage on 401

4. **XSS Protection**
   - Sanitize all user inputs
   - Use React's built-in XSS protection
   - Never eval() user data

### API Best Practices

1. **Rate Limiting**
   - Implement rate limiting on `/api/login.php`
   - Prevent brute force attacks

2. **Audit Logging**
   - Log all login/logout events
   - Track failed login attempts

3. **Token Rotation**
   - Optionally use refresh tokens
   - Current implementation uses single token with auto-refresh

## Troubleshooting

### Token Verification Fails

1. **Check token is sent correctly**
   ```
   Authorization: Bearer <token>
   ```
   Not: `Authorization: <token>`

2. **Check token hasn't expired**
   - Verify `exp` claim in token
   - Token expiration is 24 hours from login

3. **Check JWT secret matches**
   - Ensure `JWT_SECRET` env var is set
   - Or change default in `JWTHandler.php`

4. **Check signature**
   ```php
   $payload = JWTHandler::verifyToken($token);
   if (!$payload) {
     echo "Token signature invalid or expired";
   }
   ```

### Token Blacklist Not Working

1. **Check directory exists**
   ```bash
   mkdir -p /path/to/storage
   chmod 755 /path/to/storage
   ```

2. **Check file permissions**
   ```bash
   chmod 644 /path/to/storage/token_blacklist.json
   ```

3. **Check JSON file format**
   ```json
   {
     "token1": 1704153600,
     "token2": 1704240000
   }
   ```

### Frontend Token Not Persisting

1. **Check localStorage is available**
   ```typescript
   console.log(localStorage.getItem('auth_token'));
   ```

2. **Check browser privacy mode**
   - LocalStorage may be disabled in private browsing

3. **Check for CORS errors**
   - Verify API is CORS-enabled

## Migration Guide

If migrating from the old token system:

1. **Old system**: Random 64-character hex token
2. **New system**: JWT token

### Steps:

1. Deploy new JWTHandler and AuthMiddleware files
2. Update login.php to use JWT
3. Update logout.php with blacklist
4. Update frontend AuthService
5. Users will automatically get new JWT on next login
6. Old tokens won't validate (fine since they're stateless)

## References

- [JWT.io](https://jwt.io) - JWT Introduction
- [RFC 7519](https://tools.ietf.org/html/rfc7519) - JSON Web Token (JWT)
- [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
- [OWASP Authorization Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html)

## Support

For issues or questions:
1. Check this guide's troubleshooting section
2. Review log files in `storage/` directory
3. Check PHP error logs
4. Verify all required files are present
