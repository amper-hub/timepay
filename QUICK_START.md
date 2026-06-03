# TimePay Mobile - Quick Start Guide (5 Minutes)

## ⚡ Having Network Issues?

**Before you start, if you're getting "Status: 0" network errors:**

```bash
# Run this to diagnose your setup:
node diagnose-network.js
```

This will tell you exactly what's wrong and how to fix it.

**See:** [DEBUG_QUICK_CARD.md](DEBUG_QUICK_CARD.md) for quick fixes

---

## 🚀 Get Running in 5 Minutes

### Step 1: Update API Configuration (1 minute)

**Find your computer's IP:**

Windows:
```powershell
ipconfig
# Look for "IPv4 Address" → copy the number (e.g., 192.168.1.100)
```

macOS/Linux:
```bash
ifconfig
# Look for "inet" (not 127.0.0.1) → copy the number
```

**Edit `src/services/api.ts` Line 12:**

```typescript
// BEFORE:
const BASE_URL = "http://YOUR_LOCAL_COMPUTER_IP:8000/api";

// AFTER (example - use YOUR IP):
const BASE_URL = "http://192.168.1.100:8000/api";
```

✅ **Done**

---

### Step 2: Start Laravel Server (1 minute)

Open terminal in your Laravel project:

```bash
cd timepay-backend
php artisan serve --host=0.0.0.0 --port=8000
```

You should see:
```
Laravel development server started: http://127.0.0.1:8000
```

✅ **Keep this terminal open**

---

### Step 3: Install Dependencies (1 minute)

Open NEW terminal in mobile project:

```bash
cd timepay-mobile
npm install
```

✅ **Done**

---

### Step 4: Start the App (1 minute)

```bash
npm start
```

You'll see:
```
Expo Go installed. To open your app with Expo Go, scan the QR code below.
```

✅ **Scan with Expo Go or press 'a' for Android**

---

### Step 5: Test Login (1 minute)

In the app:

1. **Enter test credentials:**
   - Email: `employee@example.com`
   - Password: `password` (or whatever is in your database)

2. **Click "Sign In"**

3. **You should see the dashboard with:**
   - Welcome message
   - Company name
   - Employee info

✅ **🎉 Success! App is running**

---

## ❌ Not Working? Quick Fixes

### "Cannot reach server" or "Status: 0" Error

**99% of the time: Windows Firewall blocking port 8000**

```
Quick Fix:
1. Open Windows Defender Firewall
2. Click "Allow an app through firewall"
3. Click "Change settings"
4. Click "Allow another app"
5. Find php.exe (Laravel server)
6. Make sure ✓ Private checkbox is checked
7. Click "Add"
8. Restart mobile app - should work now!

For more details:
→ See: DEBUG_QUICK_CARD.md
→ Or run: node diagnose-network.js
```

### "Cannot reach server"
```bash
# Check server is running
cd timepay-backend
php artisan serve --host=0.0.0.0 --port=8000

# Verify IP is correct
ipconfig  # Copy IPv4 Address
# Update BASE_URL in src/services/api.ts
```

### "Invalid email or password"
```bash
# Check user exists in database
cd timepay-backend
php artisan tinker
>>> User::where('email', 'employee@example.com')->first()

# If not found, seed the database:
>>> User::factory()->create(['email' => 'employee@example.com', 'password' => Hash::make('password')])
>>> exit
```

### TypeScript errors
```bash
# Clear cache and reinstall
rm -rf node_modules package-lock.json
npm install
npm start
```

### App not updating
```bash
# Clear Expo cache
npm start -- --clear
# or
expo start -c
```

---

## 📁 Project Structure (What Was Created)

```
src/
├── types/index.ts              ← All TypeScript types
├── services/api.ts             ← API client configuration
├── screens/LoginScreen.tsx     ← Login form
└── screens/DashboardScreen.tsx ← Dashboard

App.tsx                          ← Main app component
```

---

## 📚 Next Steps

### Having Network Issues?
→ See `DEBUG_QUICK_CARD.md` (quick fixes)  
→ Run `node diagnose-network.js` (detailed diagnostics)  
→ See `NETWORK_DEBUGGING.md` (complete guide)

### For Testing
→ See `QUICK_REFERENCE.md`

### For Detailed Setup
→ See `SETUP_GUIDE.md`

### For Troubleshooting
→ See `TROUBLESHOOTING.md`

### For Architecture Details
→ See `ARCHITECTURE.md`

---

## ✨ Features Implemented

✅ **Login**
- Email/password validation
- Error messages
- Loading spinner

✅ **Dashboard**
- Welcome banner
- Company information
- Status panels
- Employee details
- Logout button

✅ **API Integration**
- Axios HTTP client
- Bearer token authentication
- Automatic interceptors
- Error handling

✅ **Code Quality**
- Full TypeScript
- React Hooks
- Clean components
- Production-ready

---

## 🔐 Test Credentials

**Make sure these exist in your Laravel database:**

```sql
INSERT INTO users (name, email, password, company_id) 
VALUES ('John Axell', 'john@example.com', <hashed_password>, 1);
```

Or use tinker:
```bash
php artisan tinker
>>> User::factory()->create([
    'name' => 'John Axell',
    'email' => 'john@example.com',
    'password' => Hash::make('password123')
])
```

Then login with:
- **Email:** john@example.com
- **Password:** password123

---

## 🎯 What's Next? (Phase 2)

After you verify everything works:

1. **Camera Integration**
   - Capture attendance photos
   - Compress images

2. **GPS Integration**
   - Get location coordinates
   - Check geofence

3. **Attendance Submission**
   - Send photo + location to API
   - Show confirmation

---

## 💡 Useful Commands

```bash
# Start dev server
npm start

# Clear cache
npm start -- --clear

# Type check
npx tsc --noEmit

# Reset everything
rm -rf node_modules && npm install

# Test API endpoint
curl -X GET http://YOUR_IP:8000/api
```

---

## 📞 Support Quick Links

- **API Issues:** → `ENV_CONFIG.md` → "Network Debugging"
- **Component Not Working:** → `TROUBLESHOOTING.md`
- **How to add new API endpoint:** → `QUICK_REFERENCE.md` → "Common Tasks"
- **Architecture question:** → `ARCHITECTURE.md`

---

## ✅ Success Checklist

```
☐ BASE_URL updated with correct IP
☐ Laravel server running on port 8000
☐ npm install completed
☐ npm start running
☐ Logged in successfully
☐ Dashboard displayed
☐ Can see employee name, company, and info
```

---

**Estimated time to full setup: 5-10 minutes**

Questions? Check the documentation files or review the code comments.

Good luck! 🚀
