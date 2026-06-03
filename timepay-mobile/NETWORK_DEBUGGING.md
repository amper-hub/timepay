# Network Diagnostics & Debugging Guide

## 🚀 Quick Start - Run Diagnostics

### Command Line Diagnostic (Recommended First Step)

```bash
# From your project root
node diagnose-network.js
```

This will:
- ✅ Check your local IP addresses
- ✅ Test port connectivity
- ✅ Verify DNS resolution
- ✅ Test HTTP connectivity
- ✅ Test API endpoint
- ✅ Provide specific remediation steps

---

## 🔍 Manual Testing Options

### Option 1: cURL Command (Windows PowerShell or Terminal)

```bash
# Test basic connectivity
curl -X GET http://192.168.254.139:8000/api

# Test with headers
curl -X GET http://192.168.254.139:8000/api/auth/me \
  -H "Content-Type: application/json" \
  -H "Accept: application/json"

# Test login endpoint
curl -X POST http://192.168.254.139:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password123"}'
```

### Option 2: Browser Test (Desktop)

```
1. Open your browser
2. Navigate to: http://192.168.254.139:8000/api
3. Should see JSON response or 404 error
4. If page won't load:
   - Check Laravel is running
   - Verify IP address
   - Check Windows Firewall
```

### Option 3: Postman Desktop

```
1. Create new request
2. Method: GET
3. URL: http://192.168.254.139:8000/api/auth/me
4. Headers:
   - Content-Type: application/json
   - Accept: application/json
5. Send
6. Check response status code
```

---

## 🛠️ Common Issues & Fixes

### Issue 1: "Status: 0" Error

**Symptoms:**
- Network request fails silently
- No response data
- Status code shows 0

**Causes (in order of likelihood):**
1. Windows Firewall blocking port 8000
2. Incorrect IP address in BASE_URL
3. Laravel server not running
4. Different WiFi networks

**Solutions:**

```
A. Allow port 8000 in Windows Firewall:
   1. Open Windows Defender Firewall
   2. Click "Allow an app through firewall"
   3. Click "Change settings"
   4. Click "Allow another app"
   5. Browse → php.exe (Laravel server)
   6. Click "Add"
   7. Make sure both Private and Public are checked

B. Verify IP address:
   1. Windows: ipconfig | findstr IPv4
   2. Copy the "IPv4 Address" line (e.g., 192.168.x.x)
   3. Update BASE_URL in src/services/api.ts
   4. Restart app

C. Restart Laravel server:
   1. Stop current server (Ctrl+C)
   2. Run: php artisan serve --host=0.0.0.0 --port=8000
   3. Verify "Laravel development server started: http://127.0.0.1:8000"

D. Check WiFi network:
   1. Mobile device WiFi settings
   2. Desktop WiFi settings
   3. Both must show same network name (SSID)
```

---

### Issue 2: "Connection Refused" (ECONNREFUSED)

**Symptoms:**
- Clear error message about connection being refused
- Laravel server might be running but misconfigured

**Solutions:**

```
1. Restart Laravel with correct host:
   php artisan serve --host=0.0.0.0 --port=8000
   
   (NOT: php artisan serve - this only binds to localhost)

2. Verify port isn't in use:
   Windows: netstat -ano | findstr :8000
   
   If another app is using port 8000:
   - Stop that app, OR
   - Use different port: php artisan serve --host=0.0.0.0 --port=9000
   - Update BASE_URL accordingly

3. Check Laravel is actually running:
   - You should see: "Laravel development server started"
   - Not: "listening on" without "development server started"
```

---

### Issue 3: "Connection Timeout" (ECONNTIMEDOUT)

**Symptoms:**
- Request takes forever then fails
- Eventually shows timeout error
- May take 30+ seconds

**Solutions:**

```
1. Network latency might be high:
   - Try again (sometimes transient)
   - Check WiFi signal strength

2. Firewall might be rate-limiting:
   - Temporarily disable Windows Firewall to test
   - If that fixes it, configure firewall properly

3. Large payload or slow server:
   - Increase timeout in src/services/api.ts
   - Current: 30000ms (30 seconds) for development
   - Try: 60000ms (60 seconds) if still failing

4. Router configuration:
   - Some routers block certain traffic patterns
   - Try connecting to mobile hotspot instead
```

---

### Issue 4: "DNS Resolution Failed" (ENOTFOUND)

**Symptoms:**
- Error mentions "getaddrinfo" or "DNS lookup"
- Cannot resolve IP address

**Solutions:**

```
1. Verify IP address is correct:
   Windows: ipconfig
   Mac: ifconfig
   
   Look for IPv4 Address like 192.168.x.x or 10.x.x.x
   (NOT 127.0.0.1 - that's localhost)

2. Update BASE_URL:
   Edit src/services/api.ts
   const BASE_URL = "http://YOUR_IP:8000/api";
   
   Example:
   const BASE_URL = "http://192.168.254.139:8000/api";

3. Verify both devices on same network:
   - Check WiFi name (SSID) matches
   - Both showing same local IP range (192.168.x.x or 10.x.x.x)

4. Flush DNS (if problem persists):
   Windows: ipconfig /flushdns
   Mac: sudo dscacheutil -flushcache
```

---

### Issue 5: "Network Error" (ERR_NETWORK)

**Generic network error - most likely firewall**

**Systematic Fix Process:**

```
Step 1: Test Laravel is accessible from your computer
  1. Open browser on desktop
  2. Go to: http://192.168.254.139:8000/api
  3. Should see JSON response or error
  4. If blank/no response:
     - Laravel not running
     - Firewall blocking
     - Wrong IP

Step 2: Test from mobile device browser
  1. Open browser on mobile phone
  2. Same URL: http://192.168.254.139:8000/api
  3. Should see same response
  4. If doesn't work:
     - Firewall blocking mobile traffic
     - Different WiFi network

Step 3: Test with diagnostic script
  node diagnose-network.js
  
  Tells you exactly what's failing

Step 4: Fix based on diagnostic results
  See: "Common Fixes by Error Code" below
```

---

## 📋 Common Fixes by Error Code

| Error Code | Meaning | Primary Cause | Fix |
|-----------|---------|---------------|-----|
| ECONNREFUSED | Connection refused | Server not listening | Start: `php artisan serve --host=0.0.0.0 --port=8000` |
| ECONNTIMEDOUT | Connection timeout | Firewall/network slow | Check firewall, increase timeout |
| ETIMEDOUT | Timeout | Server slow/unresponsive | Restart server or check server logs |
| ENOTFOUND | DNS lookup failed | Wrong IP address | Verify IP with `ipconfig`, update BASE_URL |
| 0 (No Status) | Network error | Usually firewall | Allow port 8000 in Windows Firewall |
| EHOSTUNREACH | Host unreachable | Network issue | Check both on same WiFi network |
| ENETUNREACH | Network unreachable | Network issue | Check WiFi connection |

---

## 🔧 Configuration Verification Checklist

```
Network Configuration:
☐ Laravel server running
☐ Command shows: "Laravel development server started"
☐ Not: "listening on" without "development server started"

IP Configuration:
☐ Run: ipconfig | findstr IPv4
☐ Copy IPv4 Address (format: 192.168.x.x or 10.x.x.x)
☐ Update BASE_URL in src/services/api.ts
☐ Example: const BASE_URL = "http://192.168.254.139:8000/api";

Mobile Device:
☐ On same WiFi network as PC
☐ WiFi name (SSID) matches
☐ No VPN active on mobile device
☐ No mobile data preferred over WiFi

Windows Configuration:
☐ Windows Firewall allows port 8000
☐ Antivirus not blocking Laravel
☐ No proxy configured that blocks traffic

Testing:
☐ Desktop browser can access: http://192.168.254.139:8000/api
☐ Mobile browser can access: http://192.168.254.139:8000/api
☐ Diagnostic script runs successfully: node diagnose-network.js
☐ App can login successfully
```

---

## 📊 Debugging Workflow

```
1. Run diagnostic:
   node diagnose-network.js

2. Identify failing test

3. Fix based on error type (see table above)

4. Re-run diagnostic

5. If still failing:
   A. Check Laravel logs: tail -f storage/logs/laravel.log
   B. Manually test with cURL
   C. Check firewall settings
   D. Try different WiFi (mobile hotspot)

6. Once diagnostic passes:
   - Clear app cache: npm start -- --clear
   - Restart mobile app
   - Try login again
```

---

## 🎯 Expected Behavior After Fixes

### Diagnostic Script Should Show:

```
✓ PASS - DNS Lookup
  Successfully resolved 192.168.254.139 to 192.168.254.139

✓ PASS - TCP Port Connection
  Port 8000 is open and accepting connections

✓ PASS - HTTP Connection
  Successfully reached HTTP endpoint

✓ PASS - API Endpoint
  API responded with status 401 (expected without token)
```

### Mobile App Should:

```
1. Load login screen without errors
2. Accept email and password
3. On submit:
   - Show loading spinner
   - No "Network Error (Status 0)" alert
4. Response within 3-5 seconds
5. Either:
   - Show dashboard (login success), OR
   - Show error message (wrong credentials)
```

---

## 🚨 Emergency Troubleshooting

If nothing works:

```
1. Restart everything:
   A. Stop Laravel server (Ctrl+C)
   B. Stop mobile app
   C. Disconnect from WiFi, reconnect
   D. Restart Laravel: php artisan serve --host=0.0.0.0 --port=8000
   E. Restart app: npm start -- --clear

2. Test connectivity step by step:
   A. Open browser: http://192.168.254.139:8000/api
   B. Open cURL: curl http://192.168.254.139:8000/api
   C. Run diagnostic: node diagnose-network.js
   D. Check mobile WiFi connection

3. Nuclear option (last resort):
   A. Stop Laravel server
   B. Kill any processes on port 8000: 
      Windows: netstat -ano | findstr :8000
      Then: taskkill /PID <pid> /F
   C. Start fresh: php artisan serve --host=0.0.0.0 --port=8000
   D. Clear all app cache: rm -rf node_modules && npm install
   E. Restart Expo: npm start -- --clear

4. Check firewall directly:
   A. Windows Defender Firewall
   B. Inbound rules for port 8000
   C. Allow "php.exe" through firewall
   D. Test immediately
```

---

## 📞 Further Help

### Check These Files:

1. **QUICK_START.md** - Basic setup
2. **SETUP_GUIDE.md** - Complete configuration
3. **TROUBLESHOOTING.md** - General issues
4. **src/services/api.ts** - API configuration (check BASE_URL)

### Debug Information to Collect:

If asking for help, provide:
- Output from: `node diagnose-network.js`
- Your IP address: `ipconfig | findstr IPv4`
- Error message from mobile app
- Laravel server log output
- Current BASE_URL in src/services/api.ts

---

**Last Updated**: June 3, 2026
**Status**: Ready for production debugging
