# TimePay Mobile Application - Setup & Architecture Guide

## Project Structure

```
timepay-mobile/
├── src/
│   ├── types/
│   │   └── index.ts              # TypeScript interfaces and types
│   ├── services/
│   │   └── api.ts                # Axios API client configuration
│   ├── screens/
│   │   ├── LoginScreen.tsx       # Authentication screen
│   │   └── DashboardScreen.tsx   # Employee dashboard
│   └── hooks/                    # Custom React hooks (future)
├── App.tsx                       # Root component with state management
├── package.json
├── tsconfig.json
└── ...other config files
```

## Getting Started

### 1. Install Dependencies

```bash
npm install
# or
yarn install
```

This includes all necessary dependencies:
- `axios` - HTTP client for API requests
- `react-native-gesture-handler` - Gesture support (required for Expo)

### 2. Configure API Base URL

**IMPORTANT**: Before running the application, you must configure the `BASE_URL` to connect to your local Laravel server.

#### Finding Your Local Computer IP

**Windows:**
```powershell
ipconfig
# Look for "IPv4 Address" under your network adapter (usually something like 192.168.x.x)
```

**macOS/Linux:**
```bash
ifconfig
# Look for "inet" address (not 127.0.0.1)
```

#### Update the Base URL

Edit **`src/services/api.ts`** and update the `BASE_URL` constant:

```typescript
// Before:
const BASE_URL = "http://YOUR_LOCAL_COMPUTER_IP:8000/api";

// After (example):
const BASE_URL = "http://192.168.1.100:8000/api";
```

### 3. Run the Application

```bash
npm start
# or
yarn start
```

Then follow Expo's instructions to open on a physical device or emulator.

## Architecture Overview

### Authentication Flow

1. **User enters credentials** in `LoginScreen`
2. **Form validation** checks email format and password length
3. **API request** is sent via `apiService.login()`
4. **Success response** contains: `user`, `company`, `token`
5. **Token is stored** via `setAuthToken()` function
6. **App state is updated** via `handleLoginSuccess()`
7. **Dashboard renders** instead of login screen

### State Management

**Current State Location**: `App.tsx` (root component)

```typescript
interface AppState {
  isAuthenticated: boolean;      // Authentication status
  userSessionData: UserSession | null; // User + Company + Token data
  loading: boolean;              // Loading state (for future use)
}
```

**State Updates:**
- `handleLoginSuccess()` - Called when user logs in successfully
- `handleLogout()` - Called when user logs out or loses session

### API Service Layer

**File**: `src/services/api.ts`

**Features:**
- ✅ Pre-configured Axios instance
- ✅ Request interceptor (automatically adds Bearer token)
- ✅ Response interceptor (error handling)
- ✅ Generic HTTP methods (GET, POST, PUT, DELETE)
- ✅ Specialized authentication methods (login, logout, getCurrentUser)

**Using the API Service:**

```typescript
import { apiService } from "../services/api";

// Login
const response = await apiService.login({
  email: "user@example.com",
  password: "password123"
});

// Generic GET request
const data = await apiService.get<User>("/users/123");

// Generic POST request
const result = await apiService.post<any>("/endpoint", { data: "value" });

// Set token manually (called automatically after login)
import { setAuthToken } from "../services/api";
setAuthToken("your-token-here");
```

## Component Documentation

### LoginScreen (`src/screens/LoginScreen.tsx`)

**Props:**
```typescript
interface LoginScreenProps {
  onLoginSuccess: (userSession: UserSession) => void;
}
```

**Features:**
- ✅ Email and password input fields
- ✅ Real-time form validation
- ✅ Error banner for API errors
- ✅ Loading spinner during request
- ✅ Handles 401 (invalid credentials), 422 (validation errors), and network errors
- ✅ Modern SaaS-style UI

**Validation:**
- Email: Required, must be valid format
- Password: Required, minimum 6 characters

### DashboardScreen (`src/screens/DashboardScreen.tsx`)

**Props:**
```typescript
interface DashboardScreenProps {
  userSessionData: UserSession | null;
  onLogout: () => void;
}
```

**Features:**
- ✅ Welcome banner with employee name
- ✅ Company information card
- ✅ Status panels (pay_metric, geofence_radius)
- ✅ "Attendance Clock In" button (placeholder for camera/GPS)
- ✅ Quick info section (Employee ID, Email, Phone)
- ✅ Logout functionality with confirmation

### Type Definitions (`src/types/index.ts`)

**Key Interfaces:**

```typescript
interface User {
  id: number;
  name: string;
  email: string;
  phone?: string;
  company_id: number;
}

interface Company {
  id: number;
  name: string;
  company_code: string;
  industry?: string;
  address?: string;
}

interface UserSession {
  user: User;
  company: Company;
  token: string;
  pay_metric?: string;
  geofence_radius_meters?: number;
}

interface LoginCredentials {
  email: string;
  password: string;
}

interface AuthResponse {
  success: boolean;
  message: string;
  data: UserSession;
}
```

## Testing the API

### Using Postman

1. **Set up the request:**
   - **Method**: POST
   - **URL**: `http://YOUR_LOCAL_COMPUTER_IP:8000/api/auth/login`
   - **Headers**: 
     ```
     Content-Type: application/json
     Accept: application/json
     ```
   - **Body**:
     ```json
     {
       "email": "employee@example.com",
       "password": "password123"
     }
     ```

2. **Expected Success Response (200):**
   ```json
   {
     "success": true,
     "message": "Login successful",
     "data": {
       "user": {
         "id": 1,
         "name": "John Axell",
         "email": "john@example.com",
         "company_id": 1
       },
       "company": {
         "id": 1,
         "name": "SNSU Main Campus",
         "company_code": "SNSU-001"
       },
       "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
       "pay_metric": "Hourly",
       "geofence_radius_meters": 50
     }
   }
   ```

3. **Expected Failure Response (401):**
   ```json
   {
     "success": false,
     "message": "Invalid email or password"
   }
   ```

## Styling System

**Color Scheme:**
- Primary Blue: `#007AFF` (buttons, highlights)
- Success Green: `#34C759` (status panels)
- Error Red: `#c33` (errors, warnings)
- Dark Gray: `#1a1a1a` (text)
- Light Gray: `#f8f9fa` (backgrounds)

**Typography:**
- Headings: 36px, weight 700 (bold)
- Title: 18px, weight 700
- Body: 16px, weight 400
- Label: 14px, weight 600
- Caption: 12px, weight 400

## Error Handling

### Login Screen Error Handling

| Status | Error Message |
|--------|---------------|
| 401 | "Invalid email or password. Please try again." |
| 422 | Server validation errors (displayed from response) |
| 429 | "Too many login attempts. Please try again later." |
| Network Error | "Unable to reach the server. Please check your connection..." |

### API Service Error Handling

All errors are logged to console with:
- HTTP status code
- Response data
- Request details
- Error message

## Next Steps

### Phase 2: Camera & GPS Integration
- Replace `handleClockIn()` placeholder in DashboardScreen
- Integrate `expo-camera` for photo capture
- Integrate `expo-location` for GPS coordinates
- Create attendance submission endpoint

### Phase 3: State Persistence
- Add secure token storage (SecureStore)
- Persist user session data locally
- Implement token refresh mechanism
- Add session recovery on app restart

### Phase 4: Advanced Features
- Attendance history view
- Leave request management
- Real-time notifications
- Offline mode support

## Troubleshooting

### Issue: "Network Error - Cannot reach server"

**Solution:**
1. Verify Laravel server is running
2. Check your `BASE_URL` configuration
3. Ensure mobile device is on same network as server
4. Try using device IP instead of `localhost`

### Issue: "Invalid email or password" even with correct credentials

**Solution:**
1. Check Laravel database for user record
2. Verify password hasn't changed
3. Check if user account is active
4. Review Laravel authentication logs

### Issue: "CORS errors" or "OPTIONS request fails"

**Solution:**
1. Ensure Laravel CORS middleware is properly configured
2. Add mobile client domain to CORS allowed origins
3. Restart Laravel server

## Performance Tips

- Components use `useCallback()` hooks to prevent unnecessary re-renders
- Form validation is optimized to only clear errors when user types
- API requests include proper timeouts (10 seconds)
- Axios interceptors efficiently handle token injection

## Security Considerations

- ✅ Bearer token authentication via Authorization header
- ✅ HTTPS ready (configure for production)
- ⚠️ Tokens currently stored in memory (consider SecureStore for production)
- ⚠️ Password is sent plaintext over HTTPS (secure, but ensure HTTPS in production)
- ⚠️ No token refresh mechanism yet (implement for long-lived sessions)

## Support & Documentation

For more information:
- [Expo Documentation](https://docs.expo.dev/)
- [React Native Documentation](https://reactnative.dev/)
- [Axios Documentation](https://axios-http.com/)
- [TypeScript Documentation](https://www.typescriptlang.org/)
