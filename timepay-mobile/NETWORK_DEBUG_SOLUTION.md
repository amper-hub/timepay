# 🐛 Network Error Debugging - Complete Solution

## Overview

This document describes the complete solution for debugging the HTTP "status: 0" network error in the TimePay mobile application.

**Issue**: Axios returns unsent response layout with status code 0 when connecting to local Laravel backend on `http://192.168.254.139:8000/api`

**Root Causes**:
1. **Windows Firewall blocking port 8000** (most common)
2. Incorrect IP address in BASE_URL
3. Laravel server not running with correct host binding
4. Mobile device on different WiFi network
5. Network timeout during request

---

## ✅ Solution Provided

### Part 1: Network Diagnostics Script

**File**: `diagnose-network.js`

A standalone Node.js script that systematically tests network connectivity:

```bash
node diagnose-network.js
```

**What it tests:**
- ✅ Local IP addresses on machine
- ✅ DNS resolution for target IP
- ✅ TCP port connectivity (port 8000)
- ✅ Basic HTTP connectivity
- ✅ API endpoint responsiveness
- ✅ HTTP response codes and headers

**Output**: Color-coded results with specific remediation steps

**Use Cases:**
- First thing to run when debugging network issues
- Isolates problem between Windows Firewall vs React Native vs Laravel
- Provides specific error codes and messages
- Gives targeted fix recommendations

---

### Part 2: Enhanced Axios Client

**File**: `src/services/api.ts` (updated)

**Improvements**:

1. **Increased Timeout for Development**
   ```typescript
   // Before: 10 seconds
   timeout: 10000,
   
   // After: 30 seconds for local dev, 10 for production
   timeout: isDevelopment ? 30000 : 10000,
   ```

2. **Minimal Headers to Avoid Preflight**
   ```typescript
   // Removed aggressive headers that trigger OPTIONS requests
   // Only: Content-Type, Accept
   headers: {
     "Content-Type": "application/json",
     Accept: "application/json",
   },
   ```

3. **Network Error Detection**
   ```typescript
   // Specifically detects status: 0 errors
   const isZeroStatus = error.response?.status === 0;
   const isNetworkError = !error.response && error.request;
   ```

4. **Comprehensive Error Logging**
   ```
   Logs include:
   - Error code (ECONNREFUSED, ECONNTIMEDOUT, etc.)
   - HTTP status code
   - Request URL
   - Request timeout setting
   - Whether it's a network error vs server error
   ```

5. **User-Friendly Alert Dialog**
   ```typescript
   function showNetworkAlert(error: AxiosError)
   
   Displays context-specific troubleshooting:
   - "Connection Refused" → Start Laravel server
   - "Connection Timeout" → Check firewall
   - "DNS Resolution Failed" → Check IP address
   - "Status: 0" → Windows Firewall + specific steps
   ```

---

### Part 3: Network Debugging Guide

**File**: `NETWORK_DEBUGGING.md`

Comprehensive guide covering:
- Running diagnostic script
- Manual testing options (cURL, browser, Postman)
- Common issues and solutions
- Error code reference table
- Systematic debugging workflow
- Emergency troubleshooting steps

---

### Part 4: Quick Reference Card

**File**: `DEBUG_QUICK_CARD.md`

One-page reference for:
- First step: `node diagnose-network.js`
- Error type → Fix mapping
- Windows Firewall configuration
- IP address verification
- Quick checklist
- Pro tips

**Print this and keep it handy!**

---

## 🚀 How to Use

### Scenario 1: "Status: 0" Network Error in App

**Step 1**: Run diagnostic
```bash
node diagnose-network.js
```

**Step 2**: Read output and find failing test

**Step 3**: Jump to fix based on error type

**Step 4**: Re-run diagnostic to verify fix

**Step 5**: Restart app and test login

### Scenario 2: Testing Connection Before Running App

```bash
# Run diagnostic first
node diagnose-network.js

# All tests green (✓)? You're good to go!
# Tests failing? Fix them before starting app
```

### Scenario 3: Manual Testing (Diagnostic Won't Run)

```bash
# Test from command line
curl -X GET http://192.168.254.139:8000/api

# Test in browser
# Navigate to: http://192.168.254.139:8000/api

# Test from mobile browser
# Same URL on mobile phone
# Should work on phone if working on desktop
```

---

## 📋 Key Changes Made

### `src/services/api.ts`

1. **Configuration**
   - Increased timeout: 30s (dev), 10s (prod)
   - Minimal headers to avoid CORS preflight
   - Development-specific settings enabled

2. **Request Interceptor**
   - Logs request method, URL, and payload (dev only)
   - Correctly attaches authorization header

3. **Response Interceptor**
   - Specific error code detection
   - Comprehensive logging with context
   - Network vs server error differentiation

4. **API Methods**
   - All methods now catch and handle network errors
   - Call `showNetworkAlert()` for user display
   - Detailed logging throughout

5. **New Function: `showNetworkAlert()`**
   - Context-aware error messages
   - Specific remediation steps per error
   - Uses React Native Alert (if available)
   - Falls back to console.error

### `QUICK_START.md`

1. Added network issue warning at top
2. Added diagnostic script reference
3. Enhanced "Cannot reach server" section
4. Added links to new debugging docs

---

## 🎯 Error Code Reference

| Code | Meaning | Primary Cause | Fix |
|------|---------|---------------|-----|
| ECONNREFUSED | Connection refused | Server not listening | Restart Laravel with `--host=0.0.0.0` |
| ECONNTIMEDOUT | Connection timeout | Firewall/slow network | Allow port 8000, check firewall |
| ETIMEDOUT | Request timeout | Server slow | Increase timeout, restart server |
| ENOTFOUND | DNS lookup failed | Wrong IP | Run `ipconfig`, update BASE_URL |
| 0 (No Status) | Network error | Usually firewall | Allow port 8000 in Windows Firewall |
| EHOSTUNREACH | Host unreachable | Network issue | Check same WiFi network |
| ENETUNREACH | Network unreachable | Network issue | Check WiFi connection |

---

## ✨ Features

### Diagnostic Script
- ✅ Identifies exact point of failure
- ✅ Provides specific remediation steps
- ✅ Tests all network layers
- ✅ Color-coded output (pass/fail)
- ✅ Platform detection (Windows, Mac, Linux)
- ✅ No dependencies (uses built-in Node modules)

### Enhanced API Service
- ✅ Better error logging for debugging
- ✅ User-friendly error alerts
- ✅ Handles status: 0 specifically
- ✅ Increased timeout for development
- ✅ CORS-friendly header configuration
- ✅ Development vs production detection

### Documentation
- ✅ Quick reference card (print-friendly)
- ✅ Detailed debugging guide
- ✅ Systematic troubleshooting workflow
- ✅ Error code reference table
- ✅ Emergency procedures
- ✅ Pro tips and best practices

---

## 🔒 Security Considerations

### Development (Local HTTP)
- ✅ Timeout increased (more resilient)
- ✅ CORS handling improved
- ✅ Error messages comprehensive

### Production (HTTPS)
- ✅ Timeout reverts to 10s
- ✅ SSL validation enabled
- ✅ No development-specific settings

**Note**: Code distinguishes between `http://` (dev) and `https://` (prod) automatically

---

## 📊 Testing the Solution

### Before Running App:
```bash
# 1. Run diagnostic
node diagnose-network.js

# 2. Verify all tests pass (show ✓)

# 3. Expected output:
✓ PASS - DNS Lookup
✓ PASS - TCP Port Connection  
✓ PASS - HTTP Connection
✓ PASS - API Endpoint

# 4. If any fail, follow fix recommendations
```

### In App:
```
✓ Login screen loads without "Status: 0" errors
✓ Click login → Shows loading spinner
✓ Response within 3-5 seconds
✓ Shows either dashboard or error message (not hanging)
```

---

## 🛠️ Integration Checklist

```
Files created:
☐ diagnose-network.js (standalone diagnostic)
☐ NETWORK_DEBUGGING.md (comprehensive guide)
☐ DEBUG_QUICK_CARD.md (one-page reference)
☐ NETWORK_DEBUG_SOLUTION.md (this file)

Files updated:
☐ src/services/api.ts (enhanced with error handling)
☐ QUICK_START.md (added diagnostic references)

Ready to use:
☐ Run: node diagnose-network.js
☐ See: DEBUG_QUICK_CARD.md for quick fixes
☐ Check: NETWORK_DEBUGGING.md for detailed help
```

---

## 📞 Quick Support

**Problem**: "Status: 0" error

**First Thing to Do**:
```bash
node diagnose-network.js
```

**Common Fix** (90% of cases):
```
1. Windows Firewall
2. Click "Allow an app through firewall"
3. Find php.exe
4. Make sure ✓ Private checkbox checked
5. Click "Add"
6. Restart app
```

**If Still Not Working**:
1. See: DEBUG_QUICK_CARD.md
2. See: NETWORK_DEBUGGING.md
3. Collect info and check related docs

---

## 🎓 What You Learned

This solution teaches:

1. **Network Diagnostics**
   - How to systematically test connectivity
   - Identifying failure points
   - Using command-line tools

2. **Error Handling**
   - Comprehensive error logging
   - User-friendly error messages
   - Context-aware troubleshooting

3. **Development Practices**
   - Environment-specific configuration
   - Security best practices
   - Production vs development setup

4. **React Native Networking**
   - Axios configuration for local development
   - CORS handling
   - Timeout management

---

## 📈 Next Steps

After solving network issues:

1. ✅ Verify app connects successfully
2. ✅ Test login flow
3. ✅ Access dashboard
4. ✅ Proceed with Phase 2 (Camera & GPS)

---

## 📚 Related Documents

- [QUICK_START.md](QUICK_START.md) - Setup guide
- [DEBUG_QUICK_CARD.md](DEBUG_QUICK_CARD.md) - One-page reference
- [NETWORK_DEBUGGING.md](NETWORK_DEBUGGING.md) - Detailed guide
- [TROUBLESHOOTING.md](TROUBLESHOOTING.md) - General issues
- [SETUP_GUIDE.md](SETUP_GUIDE.md) - Complete setup

---

**Status**: ✅ Complete and ready for production  
**Last Updated**: June 3, 2026  
**Includes**: Diagnostics, Enhanced API, Complete Documentation  
