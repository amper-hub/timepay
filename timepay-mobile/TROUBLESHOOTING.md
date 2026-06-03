# TimePay Mobile - Troubleshooting Checklist

## Pre-Launch Checklist

Before running `npm start`, verify:

### ✅ Dependencies & Environment
```
☐ Node.js installed (node --version)
☐ npm/yarn installed (npm --version)
☐ Expo CLI installed (expo --version)
☐ Physical device OR emulator ready
☐ npm install completed (node_modules exists)
☐ No TypeScript errors (check Problems panel)
```

### ✅ API Configuration
```
☐ BASE_URL updated in src/services/api.ts
☐ BASE_URL has correct IP (not YOUR_LOCAL_COMPUTER_IP)
☐ Laravel server running (php artisan serve)
☐ Port 8000 is accessible
☐ Mobile device on same WiFi as server
```

### ✅ File Verification
```
☐ src/services/api.ts exists
☐ src/screens/LoginScreen.tsx exists
☐ src/screens/DashboardScreen.tsx exists
☐ src/types/index.ts exists
☐ App.tsx updated (not original template)
☐ All imports use correct paths
```

---

## Common Issues & Solutions

### Issue 1: "Cannot find module 'react-native-gesture-handler'"

**Cause**: Package not installed

**Solution**:
```bash
npm install react-native-gesture-handler
npm start
```

---

### Issue 2: "http://YOUR_LOCAL_COMPUTER_IP:8000/api is not valid"

**Cause**: BASE_URL not updated

**Solution**:
1. Open `src/services/api.ts`
2. Find line with `const BASE_URL = ...`
3. Replace `YOUR_LOCAL_COMPUTER_IP` with actual IP (e.g., `192.168.1.100`)
4. Save file

**Example**:
```typescript
// Wrong:
const BASE_URL = "http://YOUR_LOCAL_COMPUTER_IP:8000/api";

// Correct:
const BASE_URL = "http://192.168.1.100:8000/api";
```

---

### Issue 3: "Network request failed" or "Cannot reach server"

**Cause**: Server not running or wrong IP

**Solution**:
```bash
# 1. Check Laravel is running
cd timepay-backend
php artisan serve --host=0.0.0.0 --port=8000

# 2. Find correct IP
# Windows: ipconfig | findstr IPv4
# Mac: ifconfig | grep inet
# Linux: hostname -I

# 3. Update BASE_URL with correct IP

# 4. Verify from mobile device
curl -X GET http://YOUR_IP:8000/api/auth/me
```

---

### Issue 4: "Invalid email or password" on correct credentials

**Cause**: Database issue or Laravel configuration

**Solution**:
```bash
# 1. Check database migrations
cd timepay-backend
php artisan migrate

# 2. Seed test user
php artisan db:seed

# 3. Check user exists in database
php artisan tinker
>>> User::all()

# 4. Check password hash
>>> user = User::first()
>>> Hash::check('password', user->password)

# 5. Verify Laravel auth config
# Check: config/auth.php
```

---

### Issue 5: "CORS error" or "OPTIONS request failed"

**Cause**: Laravel CORS middleware not configured

**Solution**:
```bash
# 1. Install CORS package
cd timepay-backend
composer require laravel/cors

# 2. Add to app/Http/Middleware/HandleCors.php
# or use built-in CORS package

# 3. Update config/cors.php
'paths' => ['api/*'],
'allowed_methods' => ['*'],
'allowed_origins' => ['*'],
```

---

### Issue 6: TypeScript errors in code

**Cause**: Type mismatches

**Solution**:
1. Check Problems panel (Ctrl+Shift+M)
2. Hover over error for details
3. Common fixes:
   ```typescript
   // Add type annotation
   const myVar: string = value;
   
   // Import missing type
   import { UserSession } from "../types";
   
   // Fix function signature
   const myFunction = (param: string): void => {};
   ```

---

### Issue 7: "Cannot find LoginScreen" or import errors

**Cause**: Wrong import path or file missing

**Solution**:
```typescript
// Wrong:
import LoginScreen from "../LoginScreen";

// Correct:
import LoginScreen from "../screens/LoginScreen";

// Or use index export:
import { LoginScreen } from "../screens";
```

---

### Issue 8: App stuck on login screen after "successful" login

**Cause**: State not updating or API response format wrong

**Solution**:
1. Check browser/console for errors
2. Verify API response has correct format:
   ```json
   {
     "success": true,
     "data": {
       "user": {...},
       "company": {...},
       "token": "eyJ..."
     }
   }
   ```
3. Check `handleLoginSuccess` is being called:
   ```typescript
   // Add console log in App.tsx
   const handleLoginSuccess = (userSession) => {
     console.log("Login success:", userSession);
     // ... rest of code
   };
   ```

---

### Issue 9: Logout not working or not redirecting to login

**Cause**: State not resetting

**Solution**:
1. Check `handleLogout` implementation in App.tsx
2. Verify it's being called:
   ```typescript
   const handleLogout = () => {
     console.log("Logging out...");
     setAuthToken(null);
     setAppState({
       isAuthenticated: false,
       userSessionData: null,
       loading: false,
     });
   };
   ```

---

### Issue 10: Dashboard shows but data is missing/blank

**Cause**: userSessionData is null or incomplete

**Solution**:
1. Check API response includes all fields
2. Verify prop is passed correctly:
   ```typescript
   <DashboardScreen
     userSessionData={userSessionData}  // ← Should not be null
     onLogout={handleLogout}
   />
   ```
3. Add fallback values in DashboardScreen:
   ```typescript
   const name = user?.name || "Employee";
   const company = company?.name || "Company";
   ```

---

### Issue 11: Form validation not showing errors

**Cause**: Validation logic not triggered

**Solution**:
```typescript
// Make sure validateForm is called:
const handleLogin = () => {
  if (!validateForm()) {  // ← Important!
    return;  // Exit if validation fails
  }
  // ... continue with API call
};
```

---

### Issue 12: Password field not hidden (shows characters)

**Cause**: Missing `secureTextEntry` prop

**Solution**:
```typescript
// Wrong:
<TextInput placeholder="••••••" value={form.password} />

// Correct:
<TextInput 
  placeholder="••••••" 
  secureTextEntry={true}  // ← Add this!
  value={form.password} 
/>
```

---

### Issue 13: TouchableOpacity buttons not responding

**Cause**: Missing or wrong event handler

**Solution**:
```typescript
// Wrong:
<TouchableOpacity onPress={handleLogin()}>  // ← Wrong!
  <Text>Login</Text>
</TouchableOpacity>

// Correct:
<TouchableOpacity onPress={handleLogin}>  // ← No ()!
  <Text>Login</Text>
</TouchableOpacity>

// Or use callback:
<TouchableOpacity onPress={() => handleLogin()}>
  <Text>Login</Text>
</TouchableOpacity>
```

---

### Issue 14: API request hanging or taking too long

**Cause**: Timeout not working or network issue

**Solution**:
```typescript
// Check timeout in api.ts
const apiClient = axios.create({
  timeout: 10000,  // 10 seconds
  // ...
});

// Or increase if needed:
timeout: 30000,  // 30 seconds

// Check network:
// 1. Test WiFi speed
// 2. Check server logs
// 3. Monitor network requests (DevTools)
```

---

### Issue 15: App crashes on startup

**Cause**: JavaScript error in App.tsx or imports

**Solution**:
1. Check console for error message
2. Add error boundary (if no crashes are caught):
   ```typescript
   try {
     // App code
   } catch (error) {
     console.error("App error:", error);
   }
   ```
3. Check all imports are correct
4. Verify no circular imports

---

## Debugging Tools & Commands

### Check Node Modules
```bash
npm list axios
npm list react-native-gesture-handler
```

### Clear Cache & Reinstall
```bash
rm -rf node_modules package-lock.json  # Windows: del instead of rm
npm install
npm start
```

### Reset Expo Cache
```bash
expo start --clear
# or
expo start -c
```

### View Console Logs
```
iOS: Open Debug menu → "Show Debug Console"
Android: View logs via:
  adb logcat | grep ReactNativeJS
```

### TypeScript Check
```bash
# Check for TypeScript errors
npx tsc --noEmit

# Or in package.json:
"check-types": "tsc --noEmit"
npm run check-types
```

---

## Network Debugging

### Test API Endpoint (from mobile device)

**Using cURL:**
```bash
curl -X POST http://YOUR_IP:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"password"}'
```

**Using Postman:**
1. Create POST request
2. URL: `http://YOUR_IP:8000/api/auth/login`
3. Body (JSON): `{"email":"test@test.com","password":"password"}`
4. Check response status and data

### Test from Development Machine

```bash
# Test server is running
curl -X GET http://localhost:8000/api

# Test login endpoint
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"password"}'
```

---

## Performance Debugging

### Memory Profiling
```typescript
// Add to App.tsx for debugging
import { useEffect } from 'react';

useEffect(() => {
  const interval = setInterval(() => {
    if (global.performance && global.performance.memory) {
      console.log('Memory:', global.performance.memory);
    }
  }, 5000);
  
  return () => clearInterval(interval);
}, []);
```

### Render Performance
```typescript
// Use React DevTools Profiler
// Profile > Record rendering performance
```

---

## Database Debugging (Laravel Side)

```bash
cd timepay-backend

# Check migrations
php artisan migrate:status

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Access database via Tinker
php artisan tinker
>>> User::all()
>>> User::find(1)->company
```

---

## Security Debugging

### Check Token Format
```typescript
// In LoginScreen console:
console.log(response.data.token);
// Should output something like:
// eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0...
```

### Verify Bearer Header
```typescript
// In api.ts after setAuthToken()
console.log(apiClient.defaults.headers.common.Authorization);
// Should output: Bearer eyJ...
```

---

## Quick Fixes Reference

| Problem | Command | Result |
|---------|---------|--------|
| Install missing package | `npm install [package-name]` | Adds to dependencies |
| Clear cache | `npm start -- --clear` | Resets Expo cache |
| Check types | `npx tsc --noEmit` | Shows TS errors |
| Reinstall deps | `npm ci` | Clean install |
| Reset node_modules | `rm -rf node_modules && npm i` | Fresh install |

---

## Getting Help

1. **Check Documentation**
   - `SETUP_GUIDE.md` - Setup instructions
   - `QUICK_REFERENCE.md` - Common tasks
   - `ENV_CONFIG.md` - Configuration help

2. **Check Console Logs**
   - API errors are logged automatically
   - Check browser console in Expo Go

3. **Reproduce Minimal Example**
   - Test API endpoint separately
   - Test component in isolation

4. **Check Laravel Logs**
   ```bash
   tail -f storage/logs/laravel.log
   ```

5. **Enable Debug Mode**
   - Add `console.log()` statements
   - Use Expo DevTools
   - Use React DevTools

---

## Checklist Before Asking for Help

```
☐ Read error message completely
☐ Checked console logs
☐ Verified BASE_URL is correct IP
☐ Confirmed Laravel server is running
☐ Checked all imports are correct
☐ Verified file paths exist
☐ Tested API endpoint with Postman
☐ Checked database has test user
☐ Cleared node_modules and reinstalled
☐ Restarted Expo
```

---

**Last Updated**: June 3, 2026
**Questions?** Check the documentation files or review the code comments
