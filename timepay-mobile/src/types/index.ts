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
  role?: string;
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
  success?: boolean;
  message?: string;
  error?: string;
  user_id?: number;
  errors?: Record<string, string[] | string>;
}

export interface PasswordChangeRequiredResponse {
  error: "password_change_required";
  message: string;
  user_id: number;
}

export interface UpdateTemporaryPasswordRequest {
  user_id: number;
  current_password: string;
  new_password: string;
  new_password_confirmation: string;
}

export const isPasswordChangeRequiredResponse = (
  data: unknown
): data is PasswordChangeRequiredResponse => {
  if (!data || typeof data !== "object") return false;
  const obj = data as Record<string, unknown>;
  return (
    obj.error === "password_change_required" &&
    typeof obj.user_id === "number"
  );
};

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

export type AttendanceState = 'clocked_in' | 'clocked_out';
export type AttendancePunchType = 'clock_in' | 'clock_out';

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
  type: AttendancePunchType;
  status: 'verified' | 'rejected' | 'flagged';
  distance_meters: number;
  photo_path: string | null;
}

export interface AttendancePunchResponse {
  success: boolean;
  message: string;
  attendance_log: AttendanceLogResponse;
  geofence_info: GeofenceInfo;
  current_state?: AttendanceState;
  next_expected_punch?: AttendancePunchType;
}

export interface AttendanceStatusResponse {
  current_state: AttendanceState;
  last_punch: AttendanceLogResponse | null;
  next_expected_punch: AttendancePunchType;
}

export interface PendingPayResponse {
  pending_amount: number;
  currency_symbol: string;
  unpaid_hours: number;
}
