# TimePay Mobile - Quick Reference Guide

## File Structure Quick Navigation

```
src/
├── types/index.ts                    ← All TypeScript interfaces
├── services/
│   ├── api.ts                        ← Axios configuration & API methods
│   └── index.ts                      ← Service exports
├── screens/
│   ├── LoginScreen.tsx               ← Login form component
│   ├── DashboardScreen.tsx           ← Dashboard/home component
│   └── index.ts                      ← Screen exports
└── hooks/                            ← Custom hooks (for future)

App.tsx                               ← Root component with auth state
```

## Common Tasks

### 1. Adding a New API Endpoint

**In `src/services/api.ts`:**

```typescript
// Add method to apiService object
export const apiService = {
  // existing methods...
  
  // New endpoint method
  getAttendanceRecords: async (startDate: string, endDate: string) => {
    const response = await apiClient.get<AttendanceResponse>(
      `/attendance?start=${startDate}&end=${endDate}`
    );
    return response.data;
  },
};
```

**Use in your component:**

```typescript
import { apiService } from "../services/api";

const response = await apiService.getAttendanceRecords("2026-06-01", "2026-06-30");
```

### 2. Handling API Errors in Components

```typescript
import { AxiosError } from "axios";
import { ApiErrorResponse } from "../types";

try {
  const response = await apiService.someMethod();
} catch (error) {
  const axiosError = error as AxiosError<ApiErrorResponse>;
  
  if (axiosError.response?.status === 401) {
    // Handle unauthorized
    handleLogout();
  } else if (axiosError.response?.status === 422) {
    // Handle validation errors
    const validationErrors = axiosError.response.data.errors;
  } else {
    // Handle other errors
    console.error(axiosError.message);
  }
}
```

### 3. Adding Form Validation

**Pattern already implemented in LoginScreen:**

```typescript
const [form, setForm] = useState<FormState>({
  fieldName: "",
});

const [errors, setErrors] = useState<ValidationErrors>({});

const validateForm = useCallback((): boolean => {
  const newErrors: ValidationErrors = {};

  if (!form.fieldName.trim()) {
    newErrors.fieldName = "Field is required";
  }

  setErrors(newErrors);
  return Object.keys(newErrors).length === 0;
}, [form]);
```

### 4. Creating a New Screen Component

**Template:**

```typescript
import React, { useCallback } from "react";
import { View, Text, StyleSheet } from "react-native";

interface MyScreenProps {
  // Define props here
}

const MyScreen: React.FC<MyScreenProps> = ({}) => {
  // Component logic here
  
  return (
    <View style={styles.container}>
      <Text>My Screen</Text>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#f8f9fa",
  },
});

export default MyScreen;
```

## Testing Checklist

### ✅ Login Flow
- [ ] Enter valid email/password → Should login successfully
- [ ] Enter invalid credentials → Should show error message
- [ ] Leave email empty → Should show validation error
- [ ] Leave password empty → Should show validation error
- [ ] Enter invalid email format → Should show error
- [ ] Check network error handling → Should show connection message

### ✅ Dashboard Flow
- [ ] After login, dashboard displays
- [ ] Welcome message shows correct employee name
- [ ] Company name displays correctly
- [ ] Pay metric shows (or "N/A")
- [ ] Geofence radius shows with "m" suffix (or "N/A")
- [ ] Employee info displays correctly
- [ ] Clock In button is clickable

### ✅ Logout Flow
- [ ] Logout button visible
- [ ] Confirm dialog appears
- [ ] Clicking "Logout" returns to login screen
- [ ] App state is reset

## API Response Format

### Success Response (200)
```json
{
  "success": true,
  "message": "Operation successful",
  "data": {
    // Response data here
  }
}
```

### Error Response (4xx/5xx)
```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

## Token Management

### How Tokens Work

1. **After Login**: Token is received in response
2. **Auto-Attachment**: Interceptor automatically adds `Authorization: Bearer {token}` header
3. **Token Persistence**: Currently stored in memory (upgrade for production)
4. **Logout**: Token is cleared via `setAuthToken(null)`

### Manual Token Management

```typescript
import { setAuthToken } from "../services/api";

// Set token
setAuthToken("eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...");

// Clear token
setAuthToken(null);
```

## Styling Guide

### Colors

```typescript
const colors = {
  primary: "#007AFF",        // Blue
  success: "#34C759",        // Green
  error: "#c33",            // Red
  dark: "#1a1a1a",          // Text
  light: "#f8f9fa",         // Background
  border: "#ddd",           // Borders
  disabled: "#999",         // Disabled text
};
```

### Common Style Patterns

**Card Style:**
```typescript
{
  backgroundColor: "#fff",
  borderRadius: 8,
  padding: 16,
  shadowColor: "#000",
  shadowOffset: { width: 0, height: 2 },
  shadowOpacity: 0.05,
  shadowRadius: 4,
  elevation: 2,
}
```

**Button Style:**
```typescript
{
  backgroundColor: "#007AFF",
  paddingVertical: 14,
  borderRadius: 6,
  alignItems: "center",
}
```

## Console Debugging

### Enable API Logging

The API service logs all errors to console:
```
API Error Response: { status: 401, data: {...} }
API Request Error: {...}
API Error: Error message
```

### View Token Status

```typescript
// In browser/emulator console
import { apiClient } from "./src/services/api";
console.log(apiClient.defaults.headers.common.Authorization);
// Output: "Bearer eyJhbGciOi..."
```

## Common Errors & Solutions

| Error | Cause | Solution |
|-------|-------|----------|
| Network Error 500+ | Server error | Check Laravel logs |
| 401 Unauthorized | Invalid token | Re-login |
| 422 Validation Error | Bad request data | Check field values |
| CORS Error | Browser/server config | Add to CORS whitelist |
| "Cannot reach server" | Wrong BASE_URL | Update with correct IP:port |

## Performance Optimization Tips

1. **Use `useCallback`** for event handlers (prevents re-renders)
2. **Memoize heavy computations** with `useMemo`
3. **Keep state minimal** - only store what's necessary
4. **Use `ScrollView` with `contentContainerStyle`** for proper layout
5. **Lazy load images** when added in future

## TypeScript Patterns

### Typing API Responses

```typescript
interface ApiResponse<T> {
  success: boolean;
  data: T;
}

// Usage
const response = await apiClient.get<ApiResponse<User>>("/user/123");
```

### Typing Component Props

```typescript
interface ComponentProps {
  title: string;
  onPress: () => void;
  optional?: string;
}

const MyComponent: React.FC<ComponentProps> = ({ title, onPress }) => {
  // Component code
};
```

### Typing State

```typescript
interface State {
  count: number;
  items: Item[];
  error: string | null;
}

const [state, setState] = useState<State>({
  count: 0,
  items: [],
  error: null,
});
```

## Next Phase Integration Points

### Camera Integration (Phase 2)
- Import `expo-camera`
- Create `CameraScreen` component
- Call from `handleClockIn()` in DashboardScreen
- Capture image → Pass to API

### Location Integration (Phase 2)
- Import `expo-location`
- Request permissions
- Get GPS coordinates
- Attach to attendance submission

### Persistent Storage (Phase 3)
- Add `expo-secure-store`
- Store token securely
- Retrieve token on app startup
- Check token validity before showing dashboard

## File Size Reference

| File | Size | Purpose |
|------|------|---------|
| api.ts | ~5KB | API service configuration |
| LoginScreen.tsx | ~7KB | Login form component |
| DashboardScreen.tsx | ~8KB | Dashboard component |
| types/index.ts | ~2KB | TypeScript definitions |
| App.tsx | ~2KB | Root component |

---

**Last Updated**: June 2026
**Status**: Phase 1 - Complete
