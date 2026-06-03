# 🧪 Login Fix - Testing Guide

## Quick Test (5 Minutes)

### Prerequisites
✅ Laravel backend running with database seeded
✅ Mobile app source code updated (changes applied)
✅ Device on same WiFi as PC

### Step 1: Clear and Rebuild App
```bash
cd timepay-mobile
rm -rf node_modules .expo
npm install
npm start -- --clear
```

Expected: App loads fresh without build errors

### Step 2: Access Login Screen
- App should show TimePay login screen
- Email and password fields visible
- Login button clickable

### Step 3: Test Login
**Email**: `john@example.com`  
**Password**: `password123`

### Expected Result ✅
1. **Immediately after clicking login**:
   - Loading spinner appears
   - No "Status: 0" error
   - No "Unknown error" alert

2. **Within 2-3 seconds**:
   - Loading spinner disappears
   - Dashboard screen appears
   - Shows employee information:
     - Name: "John Axell"
     - Email: "john@example.com"
   - Shows company information:
     - Company: "SNSU Main Campus"
     - Pay Metric: "hourly"
   - Logout button visible

3. **Console logs** (if you can see them):
   ```
   [API] Attempting login for: john@example.com
   [API Request] POST /auth/login
   [API Response] 200 http://192.168.254.139:8000/api/auth/login
   [API] Login successful
   ```

---

## If Login Still Fails

### Error: "Invalid email or password"
**Meaning**: Credentials rejected by server

**Fix**:
1. Verify database is seeded: `php artisan db:seed`
2. Verify user exists: Check database for john@example.com
3. Try different credentials if user doesn't exist

### Error: "Cannot reach server"
**Meaning**: Network problem

**Fix**:
1. Run: `node diagnose-network.js`
2. Follow specific remediation for failing test
3. Most likely: Windows Firewall blocking port 8000

### Error: "Unexpected response format from server"
**Meaning**: Server returned wrong structure

**Check**:
1. Test API directly: `curl -X POST http://192.168.254.139:8000/api/auth/login -H "Content-Type: application/json" -d '{"email":"john@example.com","password":"password123"}'`
2. Check response includes: `token`, `user`, `company`
3. Verify Laravel API hasn't changed

---

## Test Scenarios

### ✅ Happy Path Test
```
Credentials: john@example.com / password123
Expected: Login succeeds, dashboard shows
```

### ✅ Invalid Password Test
```
Credentials: john@example.com / wrongpassword
Expected: Shows "Invalid email or password" error
```

### ✅ Invalid Email Test
```
Credentials: notexist@example.com / password123
Expected: Shows "Invalid email or password" error
```

### ✅ Empty Form Test
```
Credentials: (empty) / (empty)
Expected: Shows validation errors on fields
```

### ✅ Logout Test
```
Steps:
1. Login successfully
2. Dashboard shows
3. Click "Logout" button
4. Confirm logout
Expected: Returns to login screen
```

---

## Debugging Checklist

```
Network:
☐ Laravel server running: php artisan serve --host=0.0.0.0 --port=8000
☐ Database seeded: php artisan db:seed
☐ Firewall allows port 8000: Windows Firewall settings

App State:
☐ Fresh rebuild: rm -rf node_modules .expo && npm install
☐ App cache cleared: npm start -- --clear
☐ Device on same WiFi as PC

Response Validation:
☐ Test API endpoint manually with curl
☐ Verify response includes token, user, company
☐ Check console logs for "[API] Login successful"
```

---

## Console Logs to Monitor

### Successful Login
```
[API] Attempting login for: john@example.com
[API Request] POST /auth/login
[API Payload] { email: 'john@example.com', password: 'password123' }
[API Response] 200 http://192.168.254.139:8000/api/auth/login
[API] Login successful
```

### Failed - Invalid Credentials
```
[API] Attempting login for: john@example.com
[API Request] POST /auth/login
[API Payload] { email: 'john@example.com', password: 'wrongpassword' }
[API Error Response] { status: 401, ... }
```

### Failed - Network Error
```
[API] Attempting login for: john@example.com
[API Request] POST /auth/login
[API Network Error] { code: 'ECONNREFUSED', message: 'Connection refused', ... }
```

---

## Success Indicators ✅

- [ ] Login button responds immediately (no hanging)
- [ ] Loading spinner shows during request
- [ ] Dashboard appears after successful login
- [ ] Employee name displays correctly
- [ ] Company information displays
- [ ] No error alerts on success
- [ ] Logout button works and returns to login
- [ ] Console shows "Login successful" message

---

## Need Help?

**Issue**: Still getting errors after fix?

**Steps**:
1. Check: [LOGIN_FIX_COMPLETE.md](LOGIN_FIX_COMPLETE.md)
2. Review: [DEBUG_QUICK_CARD.md](DEBUG_QUICK_CARD.md)
3. Run: `node diagnose-network.js`
4. Check: Database has user data
5. Verify: API response structure matches expected format

---

**Estimated Test Time**: 5-10 minutes  
**Success Rate**: Should be 100% if network is working  
**Next Step**: If login works, you're ready for Phase 3 (Camera & GPS)
