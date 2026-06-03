# 🎯 Network Debug Quick Reference Card

## 🚀 First Step: Run Diagnostics

```bash
node diagnose-network.js
```

**Output tells you exactly what's wrong** → Jump to fix below

---

## 🔴 Error Type → Fix

### Error: "Status: 0" or "ERR_NETWORK"

**Most Common Cause: Windows Firewall**

```
Quick Fix:
1. Open Windows Defender Firewall
2. Click "Allow an app through firewall"
3. Click "Change settings"
4. Click "Allow another app"
5. Find and select php.exe (Laravel)
6. Make sure ✓ Private checkbox is checked
7. Click "Add" then "OK"
8. Restart mobile app
```

**If that doesn't work:**

```
2nd Check: Verify IP address
$ ipconfig | findstr IPv4
# Copy the IPv4 Address (should be 192.168.x.x or 10.x.x.x)
# NOT 127.0.0.1

Edit: src/services/api.ts line 18
const BASE_URL = "http://[YOUR_IP]:8000/api";
# Example: "http://192.168.254.139:8000/api"
```

---

### Error: "ECONNREFUSED" (Connection Refused)

**Fix: Restart Laravel correctly**

```bash
cd timepay-backend

# WRONG (won't work):
php artisan serve

# CORRECT (do this):
php artisan serve --host=0.0.0.0 --port=8000
```

**Keep this terminal open** ← Important!

---

### Error: "ECONNTIMEDOUT" (Timeout)

**Fix: Check network and firewall**

```
1. Verify WiFi connection:
   Mobile and PC on same WiFi network?
   Both showing same SSID?

2. Check firewall again:
   Windows Firewall > Inbound Rules
   Look for port 8000
   Make sure it's "Allowed"

3. Try increasing timeout:
   Edit: src/services/api.ts line 65
   Change: timeout: isDevelopment ? 30000 : 10000
   To: timeout: isDevelopment ? 60000 : 10000
   (increases from 30s to 60s)
```

---

### Error: "ENOTFOUND" (DNS Lookup Failed)

**Fix: Verify and update IP address**

```bash
# Get your IP:
$ ipconfig | findstr IPv4

# Copy IPv4 Address

# Edit: src/services/api.ts line 18
const BASE_URL = "http://192.168.254.139:8000/api";
#                              ^^^^^^^^^^^^^^ 
#                       Your IP address goes here
```

---

### Error: "Cannot reach server" (in app alert)

**Same as "Status: 0" above** → See Windows Firewall fix

---

## ✅ Verification Steps (After Any Fix)

```
1. Run diagnostic again:
   node diagnose-network.js

2. All green (✓) = Good!

3. Mobile app:
   - Click login button
   - Should load (not immediate error)
   - Should respond within 3-5 seconds
   - Should show either dashboard or error message
```

---

## 🧪 Manual Testing (If Diagnostic Won't Run)

```bash
# Test from command line:
curl -X GET http://192.168.254.139:8000/api

# Test in browser:
# Open: http://192.168.254.139:8000/api
# Replace 192.168.254.139 with YOUR IP

# Test from mobile phone browser:
# Same URL as above
# Should work on phone if working on desktop
```

---

## 📋 Checklist to Complete

```
Before troubleshooting:
☐ Laravel server running (see correct command above)
☐ Terminal shows "Laravel development server started"
☐ Mobile device on same WiFi as PC
☐ WiFi name (SSID) visible on both devices

After each fix:
☐ Run: node diagnose-network.js
☐ Check all tests show ✓ PASS
☐ Clear app cache: npm start -- --clear
☐ Try login again in app

Success indicators:
☐ Diagnostic script shows all green (✓ PASS)
☐ Login button responds immediately
☐ Gets dashboard or error message (not hanging)
☐ No "Status: 0" error alerts
```

---

## 🚨 If Still Not Working

**In order:**

```
1. Restart everything fresh:
   - Stop Laravel (Ctrl+C)
   - Close mobile app
   - Restart both

2. Rebuild node:
   rm -rf node_modules
   npm install
   npm start -- --clear

3. Check process on port 8000:
   netstat -ano | findstr :8000
   
   If something else is using port 8000:
   taskkill /PID [number] /F
   Then restart Laravel

4. Check Windows Event Viewer for errors:
   - Windows button > "Event Viewer"
   - Look for PHP or network errors

5. Try mobile hotspot instead of WiFi:
   - Connect PC and mobile to hotspot
   - Try again (rules out WiFi config issue)

6. Nuclear option - complete reset:
   cd timepay-mobile
   rm -rf node_modules .expo
   npm install
   npm start -- --clear
```

---

## 💡 Pro Tips

```
✓ Windows Firewall = #1 cause of "Status: 0"
  → Always check this first

✓ IP address changes when you restart
  → Verify with: ipconfig | findstr IPv4
  → Update BASE_URL if it changed

✓ Laravel needs --host=0.0.0.0
  → Default php artisan serve won't work

✓ Both devices need same WiFi
  → Different networks = connection fails

✓ Diagnostic script shows exactly what's failing
  → Run it first, read the output carefully

✓ Error "Status: 0" is almost always firewall
  → Try firewall fix before anything else
```

---

## 🔗 Related Documents

- **NETWORK_DEBUGGING.md** - Full debugging guide
- **QUICK_START.md** - Initial setup
- **TROUBLESHOOTING.md** - General issues
- **SETUP_GUIDE.md** - Complete documentation

---

**Print this page and keep it handy** 🖨️
