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
import {
  AuthResponse,
  ApiErrorResponse,
  LoginCredentials,
  UserSession,
  isLaravelAuthResponse,
} from "../types";

/**
 * Configure this with your local Laravel server IP address
 * Example: http://192.168.1.100:8000/api
 * 
 * IMPORTANT: Update this to match your development machine's IP
 * Find your IP: Windows (ipconfig) | Mac/Linux (ifconfig)
 */
const BASE_URL = "http://192.168.254.139:8000/api";

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

/**
 * Display comprehensive network troubleshooting alert
 */
export const showNetworkAlert = (error: AxiosError<ApiErrorResponse>) => {
  const title = "Network Connection Error";
  
  // Detailed troubleshooting message based on error type
  let message = "";
  
  if (error.code === "ECONNREFUSED") {
    message =
      "❌ Connection Refused\n\n" +
      "Laravel server is not responding. Make sure to:\n" +
      "1. Start Laravel: php artisan serve --host=0.0.0.0 --port=8000\n" +
      "2. Keep the server terminal open\n" +
      "3. Verify BASE_URL in src/services/api.ts is correct";
  } else if (error.code === "ECONNTIMEDOUT" || error.code === "ETIMEDOUT") {
    message =
      "⏱️ Connection Timeout\n\n" +
      "Server not responding in time. Check:\n" +
      "1. Laravel server is running\n" +
      "2. Network is stable\n" +
      "3. Firewall isn't blocking port 8000\n" +
      "4. BASE_URL is correct: " + BASE_URL;
  } else if (error.code === "ENOTFOUND") {
    message =
      "🌐 DNS Resolution Failed\n\n" +
      "Cannot resolve IP address. Verify:\n" +
      "1. BASE_URL IP is correct: " + BASE_URL + "\n" +
      "2. Device is on same WiFi network\n" +
      "3. IP hasn't changed (run: ipconfig)";
  } else if (error.code === "ERR_NETWORK" || !error.response) {
    message =
      "📡 Network Error (Status: 0)\n\n" +
      "Common causes:\n" +
      "1. Windows Firewall blocking port 8000\n" +
      "   → Allow port 8000 in Windows Firewall\n" +
      "2. Incorrect IP address\n" +
      "   → Run 'ipconfig' and update BASE_URL\n" +
      "3. Laravel not running\n" +
      "   → Start: php artisan serve --host=0.0.0.0 --port=8000\n" +
      "4. Different WiFi networks\n" +
      "   → Mobile and PC must use same WiFi\n\n" +
      "Run: node diagnose-network.js for detailed diagnostics";
  } else if (error.response?.status === 0) {
    message =
      "🔌 Connection Failed (Status Code 0)\n\n" +
      "Network handshake incomplete. Try:\n" +
      "1. Restart both mobile app and Laravel server\n" +
      "2. Check device WiFi connection\n" +
      "3. Run network diagnostics\n" +
      "4. Verify firewall settings";
  } else {
    message =
      `Error: ${error.message}\n\n` +
      `URL: ${BASE_URL}\n` +
      `Code: ${error.code}`;
  }

  // Show alert to user
  if (typeof Alert !== "undefined") {
    Alert.alert(title, message, [{ text: "OK" }]);
  } else {
    console.error(`${title}\n${message}`);
  }
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
  
  // Local development specific settings
  ...(isDevelopment && {
    // Disable SSL verification for self-signed certificates (dev only!)
    // In production, NEVER do this
    validateStatus: (status) => status < 500, // Don't throw on 4xx
  }),
});

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
        console.log(`[API Payload]`, config.data);
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
      console.log(
        `[API Response] ${response.status} ${response.config.url}`,
        response.data
      );
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
      console.error("[API Error Response]", {
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
          user: response.data.user,
          company: response.data.company,
        };
        
        return userSession;
      } else {
        // Server responded but response format is unexpected
        const errorMsg = "Login failed: Unexpected response format from server";
        console.error("[API] Login failed:", errorMsg);
        console.error("[API] Received response:", response.data);
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
      if (!axiosError.response && axiosError.request) {
        showNetworkAlert(axiosError);
      }

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
      
      if (!axiosError.response && axiosError.request) {
        showNetworkAlert(axiosError);
      }
      
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
      
      if (!axiosError.response && axiosError.request) {
        showNetworkAlert(axiosError);
      }
      
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
      
      if (!axiosError.response && axiosError.request) {
        showNetworkAlert(axiosError);
      }
      
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
      
      if (!axiosError.response && axiosError.request) {
        showNetworkAlert(axiosError);
      }
      
      throw error;
    }
  },
};

export default apiClient;
