# TimePay Mobile Architecture Diagram

## System Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                          TIMEPAY MOBILE APP                         │
│                         (Expo + React Native)                       │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
        ┌─────────────────────────────────────────────────────┐
        │            App.tsx (Root Component)                 │
        │  ┌─────────────────────────────────────────────┐   │
        │  │ State Management:                           │   │
        │  │  • isAuthenticated: boolean                 │   │
        │  │  • userSessionData: UserSession | null      │   │
        │  │  • loading: boolean                         │   │
        │  │                                             │   │
        │  │ Callbacks:                                  │   │
        │  │  • handleLoginSuccess()                     │   │
        │  │  • handleLogout()                           │   │
        │  └─────────────────────────────────────────────┘   │
        │                       │                              │
        │        ┌──────────────┴──────────────┐               │
        │        │                             │               │
        │        ▼                             ▼               │
        │   ┌─────────────┐            ┌──────────────┐       │
        │   │ isAuth ===  │            │ isAuth ===   │       │
        │   │   false     │            │   true       │       │
        │   └──────┬──────┘            └──────┬───────┘       │
        │          │                          │                │
        │          ▼                          ▼                │
        │   ┌──────────────────┐    ┌──────────────────────┐ │
        │   │  LoginScreen     │    │  DashboardScreen     │ │
        │   │  ┌────────────┐  │    │  ┌────────────────┐  │ │
        │   │  │ Email Input│  │    │  │ Welcome Banner │  │ │
        │   │  │ Pass Input │  │    │  │ Company Info   │  │ │
        │   │  │ Validation │  │    │  │ Status Panels  │  │ │
        │   │  │ Error Msgs │  │    │  │ Clock In Btn   │  │ │
        │   │  │ Submit Btn │  │    │  │ Quick Info     │  │ │
        │   │  │ Loading UI │  │    │  │ Logout Btn     │  │ │
        │   │  └────────────┘  │    │  └────────────────┘  │ │
        │   └────────┬─────────┘    └──────────┬───────────┘ │
        │            │                         │              │
        │            │ onLoginSuccess()        │ onLogout()   │
        │            └────┬────────────────────┴──┐           │
        │                 │                       │           │
        │                 └───────────┬───────────┘           │
        │                             │                       │
        └─────────────────────────────┼───────────────────────┘
                                      │
                                      ▼
                      ┌───────────────────────────────┐
                      │   API Service Layer           │
                      │ (src/services/api.ts)         │
                      │                               │
                      │  ┌─────────────────────────┐  │
                      │  │ Axios Instance          │  │
                      │  │  • baseURL               │  │
                      │  │  • headers               │  │
                      │  │  • timeout               │  │
                      │  └────────┬────────────────┘  │
                      │           │                   │
                      │  ┌────────┴───────────────┐   │
                      │  │                        │   │
                      │  ▼                        ▼   │
                      │ ┌──────────────┐ ┌──────────┐ │
                      │ │   Request    │ │ Response │ │
                      │ │ Interceptor  │ │Interceptor
                      │ │              │ │          │ │
                      │ │ • Attach     │ │ • Handle │ │
                      │ │   Bearer     │ │   errors │ │
                      │ │   Token      │ │ • Log    │ │
                      │ │              │ │          │ │
                      │ └──────────────┘ └──────────┘ │
                      │                               │
                      │  Methods:                     │
                      │  • login()                    │
                      │  • logout()                   │
                      │  • get()                      │
                      │  • post()                     │
                      │  • put()                      │
                      │  • delete()                   │
                      └───────────────┬───────────────┘
                                      │
                                      ▼
                      ┌───────────────────────────────┐
                      │    Laravel Backend API        │
                      │                               │
                      │  POST /auth/login             │
                      │  POST /auth/logout            │
                      │  GET  /auth/me                │
                      │  GET  /users/{id}             │
                      │  (and more endpoints)         │
                      └───────────────┬───────────────┘
                                      │
                                      ▼
                      ┌───────────────────────────────┐
                      │    PostgreSQL Database        │
                      │  (User, Company, Auth data)   │
                      └───────────────────────────────┘
```

## Data Flow: Login Process

```
┌─────────────────────────────────────────────────────────────────────┐
│                        LOGIN FLOW (Happy Path)                      │
└─────────────────────────────────────────────────────────────────────┘

1. USER INPUT
   ├── User enters email: "john@example.com"
   └── User enters password: "password123"

2. FORM VALIDATION (LoginScreen)
   ├── Check email is required ✓
   ├── Check email format is valid ✓
   ├── Check password is required ✓
   └── Check password length >= 6 ✓

3. API REQUEST
   └── apiService.login({
        email: "john@example.com",
        password: "password123"
       })

4. REQUEST INTERCEPTOR
   ├── Add header: "Content-Type: application/json"
   ├── Add header: "Accept: application/json"
   └── (No token yet, so no Authorization header)

5. LARAVEL SERVER PROCESSES
   ├── Verify email exists
   ├── Verify password matches
   ├── Generate JWT token
   └── Return user + company + token

6. RESPONSE INTERCEPTOR
   ├── Receive AuthResponse
   └── Return data (no error, so no error handling)

7. LOGIN SUCCESS CALLBACK
   ├── setAuthToken(response.data.token)
   │   └── Stores token in memory
   │   └── Adds to future request Authorization headers
   └── handleLoginSuccess(response.data)
       └── Updates app state:
           ├── isAuthenticated = true
           └── userSessionData = response.data

8. CONDITIONAL RENDERING
   ├── App.tsx detects isAuthenticated = true
   └── Renders DashboardScreen instead of LoginScreen

9. DASHBOARD DISPLAY
   ├── Welcome banner shows: "Welcome back, John"
   ├── Company shows: "SNSU Main Campus"
   ├── Status panels show: pay_metric, geofence_radius
   └── User ready to use app
```

## Data Flow: Error Handling (Invalid Credentials)

```
┌─────────────────────────────────────────────────────────────────────┐
│                  LOGIN FLOW (Error Path - 401)                      │
└─────────────────────────────────────────────────────────────────────┘

1. USER INPUT
   └── Enter wrong password

2. FORM VALIDATION
   └── Passes validation ✓

3. API REQUEST
   └── apiService.login() sends request

4. LARAVEL RESPONSE
   └── 401 Unauthorized
       ├── message: "Invalid credentials"
       └── No token returned

5. RESPONSE INTERCEPTOR (Axios)
   ├── Detects error (status 401)
   ├── Logs error to console
   └── Rejects promise

6. CATCH BLOCK (LoginScreen)
   ├── Detects 401 status
   ├── Sets error message:
   │   "Invalid email or password. Please try again."
   └── Displays in error banner (red box)

7. USER SEES
   ├── Red error banner at top
   ├── Error message is clear
   ├── Form remains populated (except password cleared)
   └── Can retry login
```

## Type Flow: Data Structures

```
┌──────────────────────────────────────────────────────────────────┐
│                      TYPE DEFINITIONS                            │
└──────────────────────────────────────────────────────────────────┘

LoginCredentials
├── email: string
└── password: string
        │
        ▼
    API Request ──────►

                    API Response ◄────────┐
                                         │
                    AuthResponse         │
                    ├── success: boolean │
                    ├── message: string  │
                    └── data: UserSession
                                │
                                ▼
                            UserSession
                            ├── user: User
                            │   ├── id: number
                            │   ├── name: string
                            │   ├── email: string
                            │   ├── phone?: string
                            │   └── company_id: number
                            │
                            ├── company: Company
                            │   ├── id: number
                            │   ├── name: string
                            │   ├── company_code: string
                            │   ├── industry?: string
                            │   └── address?: string
                            │
                            ├── token: string (JWT)
                            ├── pay_metric?: string
                            └── geofence_radius_meters?: number
                                    │
                                    ▼
                            Stored in App State
                            ├── isAuthenticated = true
                            └── userSessionData = {...}
                                    │
                                    ▼
                            Passed as Props
                            └── DashboardScreen
```

## Component Composition

```
App.tsx
│
├─ State Management
│  ├─ isAuthenticated: boolean
│  ├─ userSessionData: UserSession | null
│  └─ Callbacks: handleLoginSuccess, handleLogout
│
└─ GestureHandlerRootView
   │
   ├─ [if !isAuthenticated]
   │  │
   │  └─ LoginScreen
   │     ├─ Props: { onLoginSuccess }
   │     ├─ Local State: { form, errors, loading, apiError }
   │     └─ Children:
   │        ├─ Header Section (logo, subtitle)
   │        ├─ Error Banner (conditional)
   │        ├─ Form Section
   │        │  ├─ Email Input + Error
   │        │  ├─ Password Input + Error
   │        │  └─ Submit Button
   │        └─ Footer Section (security message)
   │
   └─ [if isAuthenticated]
      │
      └─ DashboardScreen
         ├─ Props: { userSessionData, onLogout }
         ├─ SafeAreaView Wrapper
         └─ Children:
            ├─ Header Bar
            │  ├─ Title
            │  └─ Logout Button
            ├─ ScrollView (Main Content)
            │  ├─ Welcome Banner
            │  ├─ Company Card
            │  ├─ Status Grid
            │  │  ├─ Pay Metric Panel
            │  │  └─ Geofence Radius Panel
            │  ├─ Clock In Container
            │  │  └─ Clock In Button (clickable)
            │  └─ Quick Info Section
            │     ├─ Employee ID Card
            │     ├─ Email Card
            │     └─ Phone Card (conditional)
            └─ Logout Confirmation Dialog
```

## Request/Response Cycle

```
CLIENT SIDE                 NETWORK                 SERVER SIDE
─────────────────────────────────────────────────────────────────

User fills form
       │
       ▼
validateForm()
       │
       ▼
apiService.login()
       │
       ├─► Request Interceptor
       │   ├─ Add headers
       │   └─ Add token (if exists)
       │
       ▼
   ┌──────────────────────────┐
   │   HTTP POST Request      │
   │   /auth/login            │───────────────────┐
   │   Headers + Body         │                   │
   └──────────────────────────┘                   │
                                                  ▼
                                          ┌──────────────────┐
                                          │ Laravel Server   │
                                          │ • Validate       │
                                          │ • Hash password  │
                                          │ • Check DB       │
                                          │ • Generate JWT   │
                                          │ • Return data    │
                                          └──────────────────┘
                                                  │
                                                  ▼
   ┌──────────────────────────┐          ┌──────────────────┐
   │   HTTP Response          │◄─────────│ 200 OK           │
   │   JSON + Token           │          │ JSON Body        │
   └──────────────────────────┘          └──────────────────┘
       ▲                                          
       │
       ├─► Response Interceptor
       │   ├─ Check status
       │   ├─ Log errors
       │   └─ Handle response
       │
       ▼
   try block success
       │
       ▼
   handleLoginSuccess(data)
       │
       ├─ setAuthToken(token)
       │  └─ Store in memory
       │  └─ Add to future headers
       │
       ├─ Update App State
       │  ├─ isAuthenticated = true
       │  └─ userSessionData = data
       │
       ▼
   Render DashboardScreen
```

## Token Lifecycle

```
                    LOGIN SCREEN
                         │
                         │ User submits credentials
                         ▼
                    API receives response
                    with: { token: "eyJ..." }
                         │
                         │
         ┌───────────────┴───────────────┐
         │                               │
         ▼                               ▼
    [Memory Storage]              [Future: SecureStore]
    (Phase 1)                     (Phase 3)
    ├─ authToken: string          └─ Encrypted storage
    ├─ Cleared on logout           └─ Survives app restart
    └─ Lost on app restart
         │
         │
    setAuthToken(token)
    ├─ Store in authToken variable
    └─ Add to request headers:
       "Authorization: Bearer eyJ..."
         │
         ▼
    All Future API Requests
    ├─ GET /users
    ├─ POST /attendance
    ├─ PUT /profile
    └─ [all include token]
         │
         │ User clicks logout
         ▼
    setAuthToken(null)
    ├─ Clear authToken
    ├─ Remove from headers
    └─ Future requests fail (401)
         │
         ▼
    Redirect to LoginScreen
```

---

This architecture follows React best practices and is designed for easy expansion in future phases.
