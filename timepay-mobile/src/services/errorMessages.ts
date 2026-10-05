export type ErrorContext =
  | "general"
  | "login"
  | "attendance"
  | "face"
  | "location"
  | "leave"
  | "passwordReset"
  | "profile"
  | "payroll";

export const USER_ERROR_MESSAGES = {
  network:
    "Unable to connect to TimePay. Please check your internet connection and try again.",
  server: "Something went wrong on our end. Please try again later.",
  unavailable:
    "This service is temporarily unavailable. Please try again shortly.",
  login: "The email or password you entered is incorrect.",
  unauthorized: "Your session has expired. Please sign in again.",
  forbidden: "You don't have permission to perform this action.",
  notFound: "We couldn't find what you're looking for.",
  validation: "Please check the information you entered and try again.",
  timeout: "The request took too long. Please try again.",
  face: "We couldn't verify your face. Please make sure your face is clearly visible and try again.",
  faceUnavailable:
    "Face verification is temporarily unavailable. Please try again later.",
  attendance: "We couldn't record your attendance. Please try again.",
  location:
    "We couldn't verify your location. Please enable location services and try again.",
  leave: "We couldn't submit your leave request. Please try again.",
  passwordReset:
    "We couldn't process your password reset request. Please try again.",
  emailDelivery:
    "We couldn't send the verification code. Please try again shortly.",
  unknown: "Something went wrong. Please try again.",
} as const;

type ErrorLike = {
  code?: unknown;
  name?: unknown;
  message?: unknown;
  request?: unknown;
  config?: { url?: unknown };
  response?: {
    status?: unknown;
    data?: unknown;
  };
};

const technicalContent =
  /SQLSTATE|SQL syntax|PDOException|Illuminate\\|Symfony\\|\b\w+Exception\b|\bER_[A-Z0-9_]+\b|\b(?:MySQL|MariaDB|PostgreSQL|SQLite)\b|\b(?:select\s+.+\s+from|insert\s+into|update\s+\w+\s+set|delete\s+from|alter\s+table|drop\s+table)\b|\b(?:unknown column|no such (?:table|column)|duplicate entry|connection refused|could not connect to (?:database|host))\b|AxiosError|Network Error|HTTP\s*[45]\d{2}|stack trace|traceback|https?:\/\/|file:\/\/|(?:[A-Za-z]:[\\/]|\/(?:var|home|srv|opt|app|vendor|Users)\/)|\b(?:\d{1,3}\.){3}\d{1,3}\b|\b(?:localhost|[\w.-]+\.(?:internal|local|lan))(?::\d{2,5})?\b|\b(?:[A-Z][A-Z0-9_]*(?:PASSWORD|SECRET|TOKEN|API_KEY|APP_KEY|DB_HOST))\s*=|\b(?:api[_ -]?key|token|password|secret|credential|authorization)\s*[:=]|\bBearer\s+[A-Za-z0-9._~-]{12,}\b|\beyJ[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\b/i;

export const isSafeUserMessage = (value: unknown): value is string => {
  if (typeof value !== "string") return false;

  const message = value.trim();
  if (
    !message ||
    message.length > 240 ||
    /[\r\n]/.test(message) ||
    message.startsWith("{") ||
    message.startsWith("[") ||
    /<\/?[a-z][^>]*>/i.test(message) ||
    technicalContent.test(message)
  ) {
    return false;
  }

  return true;
};

const getErrorContext = (
  error: ErrorLike,
  requestedContext?: ErrorContext
): ErrorContext => {
  if (requestedContext && requestedContext !== "general") {
    return requestedContext;
  }

  const name = typeof error.name === "string" ? error.name.toLowerCase() : "";
  if (name.includes("location")) return "location";

  const url = typeof error.config?.url === "string" ? error.config.url.toLowerCase() : "";
  if (url.includes("auth/login")) return "login";
  if (url.includes("attendance")) return "attendance";
  if (url.includes("employee/leaves")) return "leave";
  if (url.includes("forgot-password") || url.includes("reset-otp")) return "passwordReset";
  if (url.includes("profile")) return "profile";
  if (url.includes("payroll")) return "payroll";

  return requestedContext ?? "general";
};

const contextFallback = (context: ErrorContext): string => {
  switch (context) {
    case "login":
      return "We couldn't sign you in. Please try again.";
    case "attendance":
      return USER_ERROR_MESSAGES.attendance;
    case "face":
      return USER_ERROR_MESSAGES.face;
    case "location":
      return USER_ERROR_MESSAGES.location;
    case "leave":
      return USER_ERROR_MESSAGES.leave;
    case "passwordReset":
      return USER_ERROR_MESSAGES.passwordReset;
    case "profile":
      return "We couldn't update your profile. Please try again.";
    case "payroll":
      return "We couldn't load that payroll information. Please try again.";
    default:
      return USER_ERROR_MESSAGES.unknown;
  }
};

const safePayloadMessage = (data: unknown): string | null => {
  if (!data || typeof data !== "object") return null;

  const payload = data as Record<string, unknown>;
  const errors = payload.errors;
  if (errors && typeof errors === "object") {
    for (const messages of Object.values(errors as Record<string, unknown>)) {
      const candidates = Array.isArray(messages) ? messages : [messages];
      for (const message of candidates) {
        if (isSafeUserMessage(message)) return message.trim();
      }
    }
  }

  if (isSafeUserMessage(payload.message)) return payload.message.trim();

  return null;
};

export const getUserFriendlyError = (
  error: unknown,
  fallback?: string,
  requestedContext?: ErrorContext
): string => {
  const value = error && typeof error === "object" ? (error as ErrorLike) : {};
  const context = getErrorContext(value, requestedContext);
  const status = typeof value.response?.status === "number" ? value.response.status : null;
  const code = typeof value.code === "string" ? value.code.toUpperCase() : "";
  const rawMessage = typeof value.message === "string" ? value.message : "";

  if (value.name === "LocationTimeoutError" || context === "location") {
    return USER_ERROR_MESSAGES.location;
  }

  if (
    code.includes("TIMEOUT") ||
    code === "ECONNABORTED" ||
    /timed?\s*out|timeout/i.test(rawMessage) ||
    status === 408
  ) {
    return USER_ERROR_MESSAGES.timeout;
  }

  if (!status && (Boolean(value.request) || /network request failed|failed to fetch/i.test(rawMessage))) {
    return USER_ERROR_MESSAGES.network;
  }

  if (status === 503) {
    const payload = value.response?.data as Record<string, unknown> | undefined;
    if (typeof payload?.error === "string" && payload.error.startsWith("face_")) {
      return USER_ERROR_MESSAGES.faceUnavailable;
    }

    return context === "face"
      ? USER_ERROR_MESSAGES.faceUnavailable
      : context === "passwordReset"
        ? USER_ERROR_MESSAGES.emailDelivery
        : USER_ERROR_MESSAGES.unavailable;
  }

  if (status !== null && status >= 500) {
    return context === "face"
      ? USER_ERROR_MESSAGES.faceUnavailable
      : USER_ERROR_MESSAGES.server;
  }

  if (status === 401) {
    return context === "login" ? USER_ERROR_MESSAGES.login : USER_ERROR_MESSAGES.unauthorized;
  }

  if (status === 403) {
    if (context === "attendance" || context === "face") {
      return safePayloadMessage(value.response?.data) ?? contextFallback(context);
    }
    return USER_ERROR_MESSAGES.forbidden;
  }

  if (status === 404) return USER_ERROR_MESSAGES.notFound;

  if (status === 429) {
    return "Too many attempts. Please wait a moment and try again.";
  }

  if (status === 422 && context === "login") return USER_ERROR_MESSAGES.login;

  if (status === 422 && context === "face") {
    const payload = value.response?.data as Record<string, unknown> | undefined;
    if (payload?.error === "face_mismatch") return USER_ERROR_MESSAGES.face;
  }

  if (status === 400 || status === 422 || status === 409) {
    return safePayloadMessage(value.response?.data) ?? USER_ERROR_MESSAGES.validation;
  }

  if (status !== null) {
    return isSafeUserMessage(safePayloadMessage(value.response?.data))
      ? safePayloadMessage(value.response?.data)!
      : fallback ?? contextFallback(context);
  }

  return fallback ?? contextFallback(context);
};
