# Environment Configuration Template

## File: `src/services/api.ts`

### Step 1: Find Your Local Computer IP

#### Windows
```powershell
# Open PowerShell and run:
ipconfig

# Look for output like:
# IPv4 Address. . . . . . . . . . : 192.168.1.100

# Use the number after "IPv4 Address"
```

#### macOS
```bash
# Open Terminal and run:
ifconfig

# Look for output like:
# inet 192.168.1.100 netmask...

# Use the number after "inet" (NOT 127.0.0.1)
```

#### Linux
```bash
hostname -I
# Output: 192.168.1.100
```

### Step 2: Update BASE_URL

**Current:**
```typescript
// src/services/api.ts, Line ~12
const BASE_URL = "http://YOUR_LOCAL_COMPUTER_IP:8000/api";
```

**Update to your IP:**
```typescript
// Example for IP 192.168.1.100:
const BASE_URL = "http://192.168.1.100:8000/api";
```

### Step 3: Verify Laravel Server

Before starting the mobile app:

```bash
# On the computer running Laravel
cd /path/to/timepay-backend

# Start the Laravel development server
php artisan serve --host=0.0.0.0 --port=8000

# You should see:
# Laravel development server started: http://127.0.0.1:8000
```

### Step 4: Test Connection

**Using cURL from mobile device:**
```bash
curl -X GET http://YOUR_IP:8000/api/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Using Postman:**
1. Create new POST request
2. URL: `http://YOUR_IP:8000/api/auth/login`
3. Body (JSON):
   ```json
   {
     "email": "employee@example.com",
     "password": "password"
   }
   ```
4. Send and verify response

## Network Debugging

### Issue: Cannot Connect to Server

```typescript
// Add this temporary debug code in App.tsx:
useEffect(() => {
  fetch('http://YOUR_IP:8000/api/auth/me')
    .then(r => console.log('Server reachable:', r.status))
    .catch(e => console.error('Server unreachable:', e));
}, []);
```

### Common Issues & Solutions

| Problem | Check |
|---------|-------|
| "Cannot GET /api" | Laravel server not running |
| "Connection refused" | Wrong IP or port |
| "CORS error" | Laravel CORS middleware |
| "Timeout" | Firewall blocking port 8000 |
| "Invalid token" | Token expired or wrong format |

## Production Configuration

When deploying to production:

```typescript
// src/services/api.ts

// Development
const BASE_URL = "http://192.168.1.100:8000/api";

// Production
const BASE_URL = process.env.REACT_APP_API_URL || "https://api.timepay.com/api";
```

## Docker Configuration

If running Laravel in Docker:

```bash
# Start Laravel container
docker run -p 8000:8000 timepay-backend

# From mobile device, use container IP:
# http://172.17.0.2:8000/api (for Linux)
# http://host.docker.internal:8000/api (for Windows/Mac)
```

## VPN / Remote Server

For remote Laravel servers:

```typescript
const BASE_URL = "https://your-domain.com/api";
// Make sure:
// 1. HTTPS is enabled
// 2. SSL certificate is valid
// 3. CORS is configured for mobile domain
```

## Token Format

Expected JWT token format:
```
Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c
```

## Troubleshooting Checklist

```
☐ Laravel server running on your machine
☐ Port 8000 is accessible from mobile device
☐ Updated BASE_URL with correct IP
☐ Mobile device on same WiFi network as server
☐ Firewall not blocking port 8000
☐ CORS middleware enabled in Laravel
☐ User exists in database with correct password
☐ Database migrations ran successfully
☐ API routes properly configured in Laravel
```

## Performance Tuning

### API Timeout
```typescript
// In src/services/api.ts
const apiClient = axios.create({
  timeout: 10000, // 10 seconds - adjust as needed
});
```

### Request Retries
```typescript
// For unreliable connections:
import axios from "axios";
import axiosRetry from "axios-retry";

axiosRetry(apiClient, { 
  retries: 3,
  retryDelay: axiosRetry.exponentialDelay
});
```

## Security Checklist

- [ ] Use HTTPS in production
- [ ] Store tokens securely (not in localStorage)
- [ ] Implement token refresh mechanism
- [ ] Validate SSL certificates
- [ ] Use secure password hashing (bcrypt)
- [ ] Implement rate limiting on API
- [ ] Add CORS whitelist restrictions
- [ ] Regular security audits

---

For more help, refer to:
- `SETUP_GUIDE.md` - Complete setup instructions
- `QUICK_REFERENCE.md` - Common tasks and patterns
