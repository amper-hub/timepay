/**
 * Type definitions for TimePay Mobile Application
 */

export interface User {
  id: number;
  name: string;
  email: string;
  phone?: string;
  role?: string;
  daily_rate?: string;
  hourly_rate?: string;
  company_id?: number;
}

export interface Company {
  id: number;
  name: string;
  company_code?: string;
  pay_metric?: string;
  geofence_radius_meters?: number;
  latitude?: string;
  longitude?: string;
  industry?: string;
  address?: string;
}

export interface UserSession {
  user: User;
  company: Company;
  token: string;
}

export interface LoginCredentials {
  email: string;
  password: string;
}

/**
 * Raw response from Laravel API
 * The backend returns a flat structure with token, user, and company at root level
 */
export interface LaravelAuthResponse {
  token: string;
  user: User;
  company: Company;
}

/**
 * Standard API response wrapper (if backend includes it)
 * This is kept for backward compatibility
 */
export interface AuthResponse {
  success?: boolean;
  message?: string;
  data?: UserSession;
}

/**
 * Type guard to check if response is a Laravel auth response
 */
export const isLaravelAuthResponse = (
  data: unknown
): data is LaravelAuthResponse => {
  if (!data || typeof data !== "object") return false;
  const obj = data as Record<string, unknown>;
  return (
    typeof obj.token === "string" &&
    typeof obj.user === "object" &&
    typeof obj.company === "object"
  );
};

export interface ApiErrorResponse {
  success: boolean;
  message: string;
  errors?: Record<string, string[]>;
}

export interface AuthContextState {
  isAuthenticated: boolean;
  userSessionData: UserSession | null;
  loading: boolean;
  error: string | null;
}

/**
 * Attendance Punch API Request/Response Types
 */
export interface AttendancePunchRequest {
  type: 'clock_in' | 'clock_out';
  latitude: number;
  longitude: number;
  photoUri: string;
}

export interface GeofenceInfo {
  user_coordinates: {
    latitude: number;
    longitude: number;
  };
  office_coordinates: {
    latitude: number;
    longitude: number;
  };
  geofence_radius_meters: number;
  distance_from_office_meters: number;
}

export interface AttendanceLogResponse {
  id: number;
  user_id: number;
  timestamp: string;
  type: 'clock_in' | 'clock_out';
  status: 'verified' | 'rejected';
  distance_meters: number;
  photo_path: string | null;
}

export interface AttendancePunchResponse {
  success: boolean;
  message: string;
  attendance_log: AttendanceLogResponse;
  geofence_info: GeofenceInfo;
}
