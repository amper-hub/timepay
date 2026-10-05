/**
 * Centralized API Service Configuration
 * Base configuration for Axios HTTP client
 * Enhanced for local development with resilience features
 */

import axios, {
  AxiosInstance,
  AxiosError,
  InternalAxiosRequestConfig,
} from "axios";
import { Alert } from "react-native";
import { ErrorContext, getUserFriendlyError } from "./errorMessages";
import {
  AuthResponse,
  ApiErrorResponse,
  LoginCredentials,
  UserSession,
  UpdateTemporaryPasswordRequest,
  ForgotPasswordRequest,
  ResetPasswordWithOtpRequest,
  PasswordResetMessageResponse,
  isLaravelAuthResponse,
  AttendancePunchRequest,
  AttendancePunchResponse,
  AttendancePunchType,
  AttendanceStatusResponse,
  PendingPayResponse,
} from "../types";

/**
 * Configure this with your local Laravel server IP address
 * Example: http://192.168.1.100:8000/api
 * 
 * IMPORTANT: Update this to match your development machine's IP
 * Find your IP: Windows (ipconfig) | Mac/Linux (ifconfig)
 */
const BASE_URL = "http://10.88.35.55:8000/api";

/**
 * Detect if running in local development (non-HTTPS)
 */
const isDevelopment = BASE_URL.startsWith("http://");

/**
 * Token storage - in production, this would be replaced with secure storage
 * For now, we manage tokens through the app's central state
 */
let authToken: string | null = null;

/**
 * Set the authentication token for subsequent requests
 */
export const setAuthToken = (token: string | null): void => {
  authToken = token;
  if (token) {
    apiClient.defaults.headers.common["Authorization"] = `Bearer ${token}`;
  } else {
    delete apiClient.defaults.headers.common["Authorization"];
  }
};

export const getAuthToken = (): string | null => authToken;

export const getApiBaseUrl = (): string => BASE_URL;

/**
 * Display comprehensive network troubleshooting alert
 */
export const showNetworkAlert = (error: AxiosError<ApiErrorResponse>) => {
  Alert.alert(
    "Connection problem",
    getUserFriendlyError(error, undefined, "general"),
    [{ text: "OK" }]
  );
};


/**
 * Create Axios instance with enhanced local development configuration
 */
const apiClient: AxiosInstance = axios.create({
  baseURL: BASE_URL,
  
  // Increased timeout for local development (30s instead of 10s)
  // Useful if Laravel server is slow or doing first-time operations
  timeout: isDevelopment ? 30000 : 10000,
  
  // Minimal headers to avoid preflight CORS issues on local subnet
  // Removed User-Agent and other headers that trigger OPTIONS requests
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
    // Note: Authorization header is added in request interceptor as needed
  },
  
  validateStatus: (status) => status >= 200 && status < 300,
});

const redactSensitivePayload = (payload: unknown): unknown => {
  if (typeof payload === "string") {
    try {
      return redactSensitivePayload(JSON.parse(payload));
    } catch {
      return "[request payload omitted]";
    }
  }

  if (Array.isArray(payload)) {
    return payload.map(redactSensitivePayload);
  }

  if (payload && typeof payload === "object") {
    return Object.fromEntries(
      Object.entries(payload as Record<string, unknown>).map(([key, value]) => [
        key,
        /password|otp|token|secret/i.test(key)
          ? "[REDACTED]"
          : redactSensitivePayload(value),
      ])
    );
  }

  return payload;
};

/**
 * Extract the best user-facing message from Laravel's standard error payload.
 * Handles 422 responses shaped like: { message: string, errors: { field: [] } }.
 */
export const getApiErrorMessage = (
  error: unknown,
  fallback?: string,
  context?: ErrorContext
): string => getUserFriendlyError(error, fallback, context);

/**
 * Request Interceptor
 * Automatically attach bearer token if it exists
 * Logs request details for debugging
 */
apiClient.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    // Attach token if it exists
    if (authToken) {
      config.headers.Authorization = `Bearer ${authToken}`;
    }

    // Log request details for debugging (dev only)
    if (isDevelopment) {
      console.log(`[API Request] ${config.method?.toUpperCase()} ${config.url}`);
      if (config.data) {
        console.log(`[API Payload]`, redactSensitivePayload(config.data));
      }
    }

    return config;
  },
  (error: AxiosError) => {
    console.error("[API Request Error]", error.message);
    return Promise.reject(error);
  }
);

/**
 * Response Interceptor
 * Handle errors with comprehensive diagnostics
 * Provides user-friendly error messages
 */
apiClient.interceptors.response.use(
  (response) => {
    // Log successful responses (dev only)
    if (isDevelopment) {
      console.log(`[API Response] ${response.status} ${response.config.url}`);
    }
    return response;
  },
  (error: AxiosError<ApiErrorResponse>) => {
    // Network error diagnostics
    const isNetworkError = !error.response && error.request;
    const isTimeoutError =
      error.code === "ECONNABORTED" ||
      error.code === "ETIMEDOUT" ||
      error.code === "ECONNTIMEDOUT";
    const isConnectionRefused = error.code === "ECONNREFUSED";
    const isZeroStatus = error.response?.status === 0;

    // Comprehensive error logging
    if (error.response) {
      // Server responded with error status
      const logResponseError =
        error.response.status >= 500 ? console.error : console.warn;

      logResponseError("[API Error Response]", {
        status: error.response.status,
        statusText: error.response.statusText,
        url: error.response.config.url,
        data: error.response.data,
      });
    } else if (error.request) {
      // Request made but no response received
      console.error("[API Network Error]", {
        code: error.code,
        message: error.message,
        url: error.config?.url,
        timeout: error.config?.timeout,
        isNetworkError,
        isTimeoutError,
        isConnectionRefused,
        isZeroStatus,
      });
    } else {
      // Error in request setup
      console.error("[API Client Error]", {
        message: error.message,
        code: error.code,
      });
    }

    return Promise.reject(error);
  }
);

/**
 * API Service methods
 */
export const apiService = {
  /**
   * POST /forgot-password
   * Request a one-time password reset code for the mobile app.
   */
  requestPasswordResetOtp: async (
    payload: ForgotPasswordRequest
  ): Promise<PasswordResetMessageResponse> => {
    const response = await apiClient.post<PasswordResetMessageResponse>(
      "/forgot-password",
      payload
    );

    return response.data;
  },

  /**
   * POST /verify-reset-otp
   * Verify the emailed code and set a new password.
   */
  resetPasswordWithOtp: async (
    payload: ResetPasswordWithOtpRequest
  ): Promise<PasswordResetMessageResponse> => {
    const response = await apiClient.post<PasswordResetMessageResponse>(
      "/verify-reset-otp",
      payload
    );

    return response.data;
  },

  /**
   * POST /auth/login
   * Authenticate user with email and password
   * Handles Laravel's flat response format: { token, user, company }
   * Returns UserSession object with all required fields
   */
  login: async (credentials: LoginCredentials): Promise<UserSession> => {
    try {
      console.log("[API] Attempting login for:", credentials.email);
      
      const response = await apiClient.post<unknown>(
        "/auth/login",
        credentials
      );

      // Check if response is the Laravel auth response format
      if (isLaravelAuthResponse(response.data)) {
        console.log("[API] Login successful");
        
        // Transform Laravel response to UserSession
        const userSession: UserSession = {
          token: response.data.token,
          user: {
            ...response.data.user,
            role: response.data.role ?? response.data.user.role,
          },
          company: response.data.company,
        };
        
        return userSession;
      } else {
        // Server responded but response format is unexpected
        const errorMsg = "Login failed: Unexpected response format from server";
        console.error("[API] Login failed:", errorMsg);
        throw new Error(errorMsg);
      }
    } catch (error) {
      const axiosError = error as AxiosError<ApiErrorResponse>;

      // Log detailed error information
      console.error("[API] Login error caught:", {
        code: axiosError.code,
        status: axiosError.response?.status,
        message: axiosError.message,
        responseData: axiosError.response?.data,
      });

      // Network-level error (status: 0 or no response)

      throw error;
    }
  },

  /**
   * POST /auth/update-temporary-password
   * Replace a temporary password and receive a fresh Sanctum token.
   */
  updateTemporaryPassword: async (
    payload: UpdateTemporaryPasswordRequest
  ): Promise<UserSession> => {
    try {
      console.log("[API] Updating temporary password for user:", payload.user_id);

      const response = await apiClient.post<unknown>(
        "/auth/update-temporary-password",
        payload
      );

      if (isLaravelAuthResponse(response.data)) {
        console.log("[API] Temporary password updated successfully");

        return {
          token: response.data.token,
          user: {
            ...response.data.user,
            role: response.data.role ?? response.data.user.role,
          },
          company: response.data.company,
        };
      }

      const errorMsg =
        "Password update failed: Unexpected response format from server";
      console.error("[API] Password update failed:", errorMsg);
      throw new Error(errorMsg);
    } catch (error) {
      const axiosError = error as AxiosError<ApiErrorResponse>;

      console.error("[API] Password update error caught:", {
        code: axiosError.code,
        status: axiosError.response?.status,
        message: axiosError.message,
        responseData: axiosError.response?.data,
      });

      throw error;
    }
  },

  /**
   * POST /auth/logout
   * Logout the authenticated user
   */
  logout: async (): Promise<void> => {
    try {
      console.log("[API] Attempting logout");
      
      await apiClient.post("/auth/logout");
      setAuthToken(null);
      
      console.log("[API] Logout successful");
    } catch (error) {
      const axiosError = error as AxiosError<ApiErrorResponse>;
      
      // Log but don't fail on logout errors (just clear token anyway)
      console.error("[API] Logout error (clearing token anyway):", axiosError.message);
      setAuthToken(null);
      
      throw error;
    }
  },

  /**
   * GET /auth/me
   * Fetch current authenticated user data
   */
  getCurrentUser: async (): Promise<AuthResponse> => {
    try {
      console.log("[API] Fetching current user");
      
      const response = await apiClient.get<AuthResponse>("/auth/me");
      return response.data;
    } catch (error) {
      console.error("[API] Get current user error:", error);
      throw error;
    }
  },

  /**
   * Generic GET request with error handling
   */
  get: async <T>(endpoint: string): Promise<T> => {
    try {
      const response = await apiClient.get<T>(endpoint);
      return response.data;
    } catch (error) {
      const axiosError = error as AxiosError<ApiErrorResponse>;
      
      throw error;
    }
  },

  /**
   * Generic POST request with error handling
   */
  post: async <T>(endpoint: string, data?: unknown): Promise<T> => {
    try {
      const response = await apiClient.post<T>(endpoint, data);
      return response.data;
    } catch (error) {
      const axiosError = error as AxiosError<ApiErrorResponse>;
      
      throw error;
    }
  },

  /**
   * Generic PUT request with error handling
   */
  put: async <T>(endpoint: string, data?: unknown): Promise<T> => {
    try {
      const response = await apiClient.put<T>(endpoint, data);
      return response.data;
    } catch (error) {
      const axiosError = error as AxiosError<ApiErrorResponse>;
      
      throw error;
    }
  },

  /**
   * Generic DELETE request with error handling
   */
  delete: async <T>(endpoint: string): Promise<T> => {
    try {
      const response = await apiClient.delete<T>(endpoint);
      return response.data;
    } catch (error) {
      const axiosError = error as AxiosError<ApiErrorResponse>;
      
      throw error;
    }
  },

  /**
   * GET /attendance/status
   * Read the employee's current clocked-in/out state.
   */
  getAttendanceStatus: async (): Promise<AttendanceStatusResponse> => {
    try {
      const response = await apiClient.get<AttendanceStatusResponse>(
        "/attendance/status"
      );

      return response.data;
    } catch (error) {
      const axiosError = error as AxiosError<ApiErrorResponse>;

      throw error;
    }
  },

  /**
   * GET /employee/pending-pay
   * Read the employee's unpaid verified payroll total.
   */
  getPendingPay: async (): Promise<PendingPayResponse> => {
    try {
      const response = await apiClient.get<PendingPayResponse>(
        "/employee/pending-pay"
      );

      return response.data;
    } catch (error) {
      const axiosError = error as AxiosError<ApiErrorResponse>;

      throw error;
    }
  },

  /**
   * POST /attendance/store
   * Submit attendance punch with biometric face capture and GPS coordinates
   * Builds FormData payload with image file for multipart/form-data upload
   */
  submitAttendancePunch: async (
    type: AttendancePunchType,
    latitude: number,
    longitude: number,
    photoUri: string
  ): Promise<AttendancePunchResponse> => {
    try {
      console.log("[API] Submitting attendance punch:", {
        type,
        latitude,
        longitude,
        photoUri: photoUri.substring(0, 50) + "...", // Log only first 50 chars
      });

      // Build FormData payload
      const formData = new FormData();
      formData.append('type', type);
      formData.append('latitude', latitude.toString());
      formData.append('longitude', longitude.toString());

      // Convert photoUri to file object
      const filename = `selfie_${Date.now()}.jpg`;
      const photoFile = {
        uri: photoUri,
        name: filename,
        type: 'image/jpeg',
      };

      // Append the file to FormData using the backend's Face++ field name.
      formData.append('selfie', photoFile as any);

      // Create a custom axios config for FormData
      const config = {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      };

      // Make the request with FormData
      const endpoint = type === 'clock_in'
        ? '/attendance/clock-in'
        : '/attendance/store';

      const response = await apiClient.post<AttendancePunchResponse>(
        endpoint,
        formData,
        config
      );

      return response.data;
    } catch (error) {
      const axiosError = error as AxiosError<ApiErrorResponse>;

      console.error("[API] Attendance punch error:", {
        status: axiosError.response?.status,
        message: axiosError.message,
        responseData: axiosError.response?.data,
      });

      throw error;
    }
  },
};

export default apiClient;
