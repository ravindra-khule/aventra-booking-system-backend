# Quick Reference: JWT Authentication

## Quick Start

### User Login (Frontend)

```typescript
import { AuthService } from './src/shared/services/auth.service';

// Login
try {
  const user = await AuthService.login('user@example.com', 'password123');
  console.log('Logged in as:', user.name);
  // Token automatically stored in localStorage
} catch (error) {
  console.error('Login failed:', error.message);
}
```

### Protected API Call (Frontend)

```typescript
// Method 1: Using AuthService helper
const headers = {
  'Authorization': AuthService.getAuthHeader(), // "Bearer <token>"
  'Content-Type': 'application/json'
};

const response = await fetch('/api/users.php', {
  method: 'GET',
  headers: headers
});

// Method 2: Manual token
const token = localStorage.getItem('auth_token');
const response = await fetch('/api/protected-endpoint.php', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
```

### Protected Endpoint (Backend)

```php
<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../lib/AuthMiddleware.php';

try {
    // This will die with 401 if token is invalid
    $user = AuthMiddleware::verifyToken();
    
    // Use user data
    $userId = $user['user_id'];
    $userRole = $user['role'];
    
    // Now fetch data or perform action
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'error' => $e->getMessage()], 401);
}
?>
```

### Admin-Only Endpoint (Backend)

```php
<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../lib/AuthMiddleware.php';

try {
    // Verify token and check role
    $user = AuthMiddleware::verifyTokenWithRole(['ADMIN', 'SUPER_ADMIN']);
    
    // Only admins will reach here
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'error' => 'Unauthorized'], 403);
}
?>
```

### User Logout (Frontend)

```typescript
// Logout
await AuthService.logout();
// Token and user data cleared from localStorage
```

### Check Authentication (Frontend)

```typescript
// Is logged in?
if (AuthService.isAuthenticated()) {
  const user = AuthService.getCurrentUser();
  console.log('Current user:', user);
}

// Is admin?
if (AuthService.isAdmin()) {
  // Show admin panel
}
```

### Token Refresh (Frontend)

```typescript
// Check and auto-refresh if expiring within 1 hour
const wasRefreshed = await AuthService.checkAndRefreshToken();

// Or manually refresh
try {
  const newToken = await AuthService.refreshToken();
  console.log('Token refreshed');
} catch (error) {
  console.error('Refresh failed:', error);
}
```

## Token Management

### Token Expiration

- **Duration**: 24 hours from login
- **Auto-refresh**: Call `checkAndRefreshToken()` periodically
- **On expiry**: Frontend gets 401, should redirect to login

### Token Storage (Frontend)

```typescript
// Token stored in localStorage under three keys:
localStorage.getItem('auth_token')        // JWT token string
localStorage.getItem('user')              // User object as JSON
localStorage.getItem('token_expires_at')  // Expiration timestamp (ms)
```

### Token Blacklist (Backend)

- Location: `storage/token_blacklist.json`
- Updated on: Logout, token invalidation
- Cleaned up: Automatically removes tokens older than 48 hours

## API Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/login.php` | No | Login: returns token |
| POST | `/api/logout.php` | Yes | Logout: blacklist token |
| GET | `/api/auth/me.php` | Yes | Current user info |
| POST | `/api/auth/refresh.php` | Yes | New token |

## Common Patterns

### Login Page Component

```typescript
const [email, setEmail] = useState('');
const [password, setPassword] = useState('');
const [error, setError] = useState('');

const handleLogin = async (e: React.FormEvent) => {
  e.preventDefault();
  try {
    const user = await AuthService.login(email, password);
    // Navigate to dashboard
    navigate('/dashboard');
  } catch (err) {
    setError(err.message);
  }
};
```

### Protected Route (Frontend)

```typescript
const ProtectedRoute = ({ children }: { children: React.ReactNode }) => {
  if (!AuthService.isAuthenticated()) {
    return <Navigate to="/login" />;
  }
  return <>{children}</>;
};
```

### Token Refresh Timer

```typescript
useEffect(() => {
  // Refresh token every 12 hours
  const interval = setInterval(() => {
    AuthService.checkAndRefreshToken();
  }, 12 * 60 * 60 * 1000);
  
  return () => clearInterval(interval);
}, []);
```

## Error Handling

### Token Expired (401)

```typescript
const response = await fetch(url, {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});

if (response.status === 401) {
  // Token expired
  localStorage.clear();
  window.location.href = '/login';
}
```

### Unauthorized (403)

```typescript
if (response.status === 403) {
  // User doesn't have permission
  console.error('Access denied');
}
```

## Debug Tips

### Check Token Contents

```javascript
// In browser console:
const token = localStorage.getItem('auth_token');
const parts = token.split('.');
const payload = JSON.parse(atob(parts[1]));
console.log(payload);
// Shows: { user_id, id, name, email, role, status, iat, exp }
```

### Check Token Expiration

```javascript
const payload = JSON.parse(atob(localStorage.getItem('auth_token').split('.')[1]));
const expiresAt = new Date(payload.exp * 1000);
console.log('Token expires at:', expiresAt);
```

### Test Backend Token Verification

```php
<?php
require_once __DIR__ . '/../lib/JWTHandler.php';

$token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $token);

$payload = JWTHandler::verifyToken($token);

if ($payload) {
    echo "Valid token: " . json_encode($payload);
} else {
    echo "Invalid token";
}
?>
```

## Environment Setup

### Backend (.env or server config)

```bash
# JWT configuration
JWT_SECRET=your-super-secret-key-here-change-in-production

# Database
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=aventra_db
```

### Frontend (.env)

```bash
VITE_REACT_APP_API_URL=http://localhost:5500
```

## Migration from Old Tokens

If you had a previous token system:

1. Old tokens won't work with new JWT verification
2. Users must login again to get new JWT
3. No data loss - JWT doesn't require token storage in database
4. Clean up old token tables (optional)

## File Structure

```
backend-laravel/
├── lib/
│   ├── JWTHandler.php          # Token generation/verification
│   └── AuthMiddleware.php       # Token validation middleware
├── public/api/
│   ├── login.php               # Login endpoint
│   ├── logout.php              # Logout endpoint
│   └── auth/
│       ├── me.php              # Get current user
│       └── refresh.php         # Refresh token
├── storage/
│   └── token_blacklist.json    # Blacklisted tokens (auto-created)
└── JWT_AUTH_GUIDE.md           # Full documentation

aventra-booking-system-ui/
└── src/shared/services/
    └── auth.service.ts         # Frontend auth service
```

## Checklist: Adding JWT to New Endpoint

- [ ] Add `require_once __DIR__ . '/../lib/AuthMiddleware.php';`
- [ ] Call `AuthMiddleware::verifyToken()` or `verifyTokenWithRole()`
- [ ] Use `$user['user_id']` or `$user['role']` from token
- [ ] Test with valid token in header: `Authorization: Bearer <token>`
- [ ] Test with invalid token (should return 401)
- [ ] Test with no header (should return 401)

## Common Issues & Solutions

| Issue | Solution |
|-------|----------|
| 401 Unauthorized | Check token is sent in `Authorization: Bearer <token>` header |
| Token expired | Call `AuthService.refreshToken()` or login again |
| CORS error | Check API has CORS headers enabled |
| Token not stored | Check localStorage is available (not in private mode) |
| Blacklist not working | Ensure `storage/` directory is writable |

---

**For more details, see:** [JWT_AUTH_GUIDE.md](JWT_AUTH_GUIDE.md)
