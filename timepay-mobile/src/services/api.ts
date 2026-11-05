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
import * as Network from "expo-network";
import { Alert } from "react-native";
import {
  ErrorContext,
  getUserFriendlyError,
  isSafeUserMessage,
} from "./errorMessages";
import {
  ApiErrorResponse,
  LoginCredentials,
  UserSession,
  User,
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
  CompanyGeofence,
} from "../types";
import { completeAttendanceEvent, getOrCreateAttendanceEvent } from "./attendanceEvent";
import {
  getAttendanceFailureDiagnostics,
  sanitizeDiagnosticRequestUrl,
} from "./attendanceDiagnostics";
import type { AttendanceFailurePhase } from "./attendanceDiagnostics";
import type { AttendanceEventMetadata } from "./attendanceEvent";
export type { AttendanceEventMetadata } from "./attendanceEvent";

const isDevelopment = __DEV__;

const resolveApiBaseUrl = (
  configuredUrl: string | undefined,
  development: boolean
): { baseUrl: string | null; error: string | null } => {
  const value = configuredUrl?.trim();

  if (!value) {
    return {
      baseUrl: null,
      error:
        "The TimePay API URL is not configured. Set EXPO_PUBLIC_API_BASE_URL and rebuild the app.",
    };
  }

  try {
    const parsed = new URL(value);
    const isHttp = parsed.protocol === "http:";

    if (
      (!isHttp && parsed.protocol !== "https:") ||
      (isHttp && !development) ||
      !parsed.hostname ||
      parsed.username ||
      parsed.password ||
      parsed.search ||
      parsed.hash
    ) {
      throw new Error("invalid URL");
    }

    const apiPath = parsed.pathname
      .replace(/(?:\/api)+\/?$/i, "")
      .replace(/\/+$/, "");

    return {
      baseUrl: `${parsed.origin}${apiPath}/api`,
      error: null,
    };
  } catch {
    return {
      baseUrl: null,
      error: development
        ? "The TimePay API URL is invalid. Use an absolute HTTP or HTTPS URL reachable from this device."
        : "The production TimePay API URL is invalid. Set EXPO_PUBLIC_API_BASE_URL to the Laravel service's public HTTPS URL and rebuild the app.",
    };
  }
};

const apiUrlConfiguration = resolveApiBaseUrl(
  process.env.EXPO_PUBLIC_API_BASE_URL,
  isDevelopment
);
const BASE_URL = apiUrlConfiguration.baseUrl ?? "";

/**
 * The current session is persisted by authStorage. Keep only the active bearer
 * token in memory here; App hydrates it from SecureStore when it starts.
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

export const getApiBaseUrl = (): string => {
  if (apiUrlConfiguration.error) {
    throw new Error(apiUrlConfiguration.error);
  }

  return BASE_URL;
};

export const getApiConfigurationError = (): string | null =>
  apiUrlConfiguration.error;

export interface AttendanceLivenessFrames {
  open: string;
  closed: string;
  reopened: string;
}

export type AttendanceLivenessMode = "blink" | "head_turn";

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
        /password|otp|token|secret|email|phone|photo|selfie|image|latitude|longitude/i.test(key)
          ? "[REDACTED]"
          : redactSensitivePayload(value),
      ])
    );
  }

  return payload;
};

const requestSensitiveValues = (payload: unknown): string[] => {
  let value = payload;
  if (typeof value === "string") {
    try {
      value = JSON.parse(value);
    } catch {
      return [];
    }
  }

  if (!value || typeof value !== "object") return [];

  const sensitiveValues: string[] = [];
  for (const [key, item] of Object.entries(value as Record<string, unknown>)) {
    if (/password|otp|token|secret|email|phone/i.test(key) && typeof item === "string" && item) {
      sensitiveValues.push(item);
    }
  }
  return sensitiveValues;
};

const safeDiagnosticMessage = (message: unknown, requestData: unknown): string | undefined => {
  if (typeof message !== "string") return undefined;

  let sanitized = message;
  for (const sensitiveValue of requestSensitiveValues(requestData)) {
    sanitized = sanitized.split(sensitiveValue).join("[REDACTED]");
  }
  return isSafeUserMessage(sanitized) ? sanitized : undefined;
};

const safeValidationDiagnostics = (error: AxiosError<ApiErrorResponse>) => {
  const body = error.response?.data;
  if (!body || typeof body !== "object") return {};

  const safeErrors: Record<string, string[]> = {};
  for (const [field, messages] of Object.entries(body.errors ?? {})) {
    if (!/^[a-z][a-z0-9_.]{0,63}$/i.test(field)) continue;

    const candidates = Array.isArray(messages) ? messages : [messages];
    const safeMessages = candidates
      .map((message) => safeDiagnosticMessage(message, error.config?.data))
      .filter((message): message is string => Boolean(message));
    if (safeMessages.length) safeErrors[field] = safeMessages;
  }

  return {
    message: safeDiagnosticMessage(body.message, error.config?.data),
    validationFields: Object.keys(safeErrors),
    validationMessages: safeErrors,
  };
};

const requestUrlForDiagnostics = (config: InternalAxiosRequestConfig): string => {
  return sanitizeDiagnosticRequestUrl(config.baseURL || BASE_URL, config.url);
};

const diagnosticsContextForConfig = (config?: {
  baseURL?: string;
  url?: string;
  method?: string;
}) => ({
  apiBaseUrl: process.env.EXPO_PUBLIC_API_BASE_URL || config?.baseURL || BASE_URL,
  endpoint: config?.url,
  method: config?.method,
  phase: "axios_interceptor" as const,
});

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
    if (apiUrlConfiguration.error) {
      throw new Error(apiUrlConfiguration.error);
    }

    // Attach token if it exists
    if (authToken) {
      config.headers.Authorization = `Bearer ${authToken}`;
    }

    // Log request details for debugging (dev only)
    if (isDevelopment) {
      console.log(`[API Request] ${config.method?.toUpperCase()} ${requestUrlForDiagnostics(config)}`);
      if (config.data && !(typeof FormData !== "undefined" && config.data instanceof FormData)) {
        console.log(`[API Payload]`, redactSensitivePayload(config.data));
      }
    }

    return config;
  },
  (error: AxiosError) => {
    if (__DEV__) {
      console.warn(
        "[API Request Error]",
        getAttendanceFailureDiagnostics(error, null, diagnosticsContextForConfig(error.config))
      );
    }
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
      console.log(
        `[API Response] ${response.status} ${sanitizeDiagnosticRequestUrl(
          response.config.baseURL || BASE_URL,
          response.config.url
        )}`
      );
    }
    return response;
  },
  (error: AxiosError<ApiErrorResponse>) => {
    const diagnostics = getAttendanceFailureDiagnostics(
      error,
      null,
      diagnosticsContextForConfig(error.config)
    );

    // Comprehensive error logging
    if (error.response) {
      // Server responded with error status
      const logResponseError =
        error.response.status >= 500 ? console.error : console.warn;

      if (__DEV__) {
        logResponseError("[API Error Response]", {
          ...diagnostics,
          ...(error.response.status === 422
            ? safeValidationDiagnostics(error)
            : {}),
        });
      }
    } else if (error.request) {
      // Request made but no response received
      if (__DEV__) console.warn("[API Network Error]", diagnostics);
    } else {
      // Error in request setup
      if (__DEV__) console.warn("[API Client Error]", diagnostics);
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
      if (__DEV__) console.log("[API] Attempting login");
      
      const response = await apiClient.post<unknown>(
        "/auth/login",
        credentials
      );

      // Check if response is the Laravel auth response format
      if (isLaravelAuthResponse(response.data)) {
        if (__DEV__) console.log("[API] Login successful");
        
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
        if (__DEV__) console.warn("[API] Login failed:", errorMsg);
        throw new Error(errorMsg);
      }
    } catch (error) {
      const axiosError = error as AxiosError<ApiErrorResponse>;

      // Log detailed error information
      if (__DEV__) console.warn("[API] Login request failed:", {
        code: axiosError.code,
        status: axiosError.response?.status,
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
      if (__DEV__) console.log("[API] Updating temporary password");

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
      if (__DEV__) console.warn("[API] Password update failed:", errorMsg);
      throw new Error(errorMsg);
    } catch (error) {
      const axiosError = error as AxiosError<ApiErrorResponse>;

      if (__DEV__) console.warn("[API] Temporary password update failed:", {
        code: axiosError.code,
        status: axiosError.response?.status,
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
      if (__DEV__) console.log("[API] Attempting logout");
      
      await apiClient.post("/auth/logout");
      setAuthToken(null);
      
      if (__DEV__) console.log("[API] Logout successful");
    } catch (error) {
      const axiosError = error as AxiosError<ApiErrorResponse>;
      
      // Log but don't fail on logout errors (just clear token anyway)
      if (__DEV__) console.warn("[API] Logout request failed.", axiosError.code);
      setAuthToken(null);
      
      throw error;
    }
  },

  /**
   * GET /auth/me
   * Fetch current authenticated user data
   */
  getCurrentUser: async (): Promise<User> => {
    try {
      if (__DEV__) console.log("[API] Fetching current user");
      
      const response = await apiClient.get<User>("/user");
      return response.data;
    } catch (error) {
      const axiosError = error as AxiosError<ApiErrorResponse>;
      if (__DEV__) console.warn("[API] Current user request failed:", axiosError.response?.status);
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

  /** GET /employee/company-geofence: fetch current settings for this employee's company. */
  getCompanyGeofence: async (): Promise<CompanyGeofence> => {
    const response = await apiClient.get<CompanyGeofence>(
      "/employee/company-geofence"
    );
    return response.data;
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
    photoUri: string,
    livenessFrames?: AttendanceLivenessFrames,
    eventMetadata?: AttendanceEventMetadata,
    livenessMode: AttendanceLivenessMode = "blink"
  ): Promise<AttendancePunchResponse> => {
    let completedResponseStatus: number | null = null;
    let failurePhase: AttendanceFailurePhase = "event_metadata";
    const endpoint = type === "clock_in"
      ? "/attendance/clock-in"
      : "/attendance/store";

    try {
      if (__DEV__) console.log("[API] Submitting attendance punch", type);
      failurePhase = "connectivity_check";
      try {
        const networkState = await Network.getNetworkStateAsync();
        if (networkState.isConnected === false || networkState.isInternetReachable === false) {
          throw new Error("An internet connection is required to record attendance.");
        }
      } catch (error) {
        if (error instanceof Error && error.message === "An internet connection is required to record attendance.") {
          throw error;
        }
        // Connectivity hints can be unavailable; let Axios produce the authoritative result.
      }
      const event = eventMetadata ?? await getOrCreateAttendanceEvent(type);

      // Build FormData payload
      failurePhase = "form_data";
      const formData = new FormData();
      formData.append('type', type);
      formData.append('latitude', latitude.toString());
      formData.append('longitude', longitude.toString());
      formData.append('liveness_mode', livenessMode);
      formData.append('event_id', event.eventId);
      formData.append('captured_at', event.capturedAt);
      formData.append('occurred_at', event.capturedAt);

      // Convert photoUri to file object
      const filename = `selfie_${Date.now()}.jpg`;
      const photoFile = {
        uri: photoUri,
        name: filename,
        type: 'image/jpeg',
      };

      // Append the file to FormData using the backend's Face++ field name.
      formData.append('selfie', photoFile as any);

      if (livenessFrames) {
        (['open', 'closed', 'reopened'] as const).forEach((phase) => {
          formData.append(`liveness_${phase}`, {
            uri: livenessFrames[phase],
            name: `${livenessMode}_${phase}_${Date.now()}.jpg`,
            type: 'image/jpeg',
          } as any);
        });
      }

      // Create a custom axios config for FormData
      const config = {
        timeout: 120000,
        headers: {
          "Content-Type": undefined,
          Accept: "application/json",
        },
      };

      // Make the request with FormData
      failurePhase = "http_request";
      const response = await apiClient.post<AttendancePunchResponse>(
        endpoint,
        formData,
        config
      );
      completedResponseStatus = response.status;

      failurePhase = "event_completion";
      await completeAttendanceEvent(type, event.eventId);
      return response.data;
    } catch (error) {
      if (__DEV__) {
        console.warn(
          "[API] Attendance punch failed:",
          getAttendanceFailureDiagnostics(error, completedResponseStatus, {
            apiBaseUrl: process.env.EXPO_PUBLIC_API_BASE_URL || BASE_URL,
            endpoint,
            method: "POST",
            phase: failurePhase,
          })
        );
      }

      throw error;
    }
  },
};

export default apiClient;
