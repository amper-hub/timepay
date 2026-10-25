const SAFE_PHASES = new Set([
  "event_metadata",
  "connectivity_check",
  "form_data",
  "http_request",
  "event_completion",
  "axios_interceptor",
  "unknown",
]);
const SAFE_STACK_FILES = new Set([
  "attendanceEvent.ts",
  "authStorage.ts",
  "api.ts",
]);
const SAFE_ERROR_NAMES = new Set([
  "Error",
  "AxiosError",
  "CanceledError",
  "TypeError",
  "ReferenceError",
  "SyntaxError",
  "RangeError",
  "URIError",
  "EvalError",
  "AggregateError",
  "SecureStoreError",
]);

const safeErrorName = (name) =>
  typeof name === "string" && SAFE_ERROR_NAMES.has(name)
    ? name
    : "Error";

const safeMessage = (message) => {
  if (typeof message !== "string") return "Unclassified error (message omitted)";
  const value = message.trim();

  if (/^Network Error$/i.test(value)) return "Network Error";
  if (/^timeout(?: of \d+ms)? exceeded$/i.test(value)) return "Request timed out";
  if (/^Request failed with status code \d{3}$/i.test(value)) return "HTTP request failed";
  if (/^(?:Request aborted|canceled|cancelled)$/i.test(value)) return "Request canceled";
  if (/^The TimePay API URL is not configured\b/i.test(value)) return "API URL is not configured";
  if (/^The TimePay API URL is invalid\b/i.test(value)) return "API URL is invalid";
  if (/^Sign in again before submitting attendance\.$/i.test(value)) {
    return "Sign in again before submitting attendance";
  }
  if (/^Secure random event IDs are unavailable on this device\.$/i.test(value)) {
    return "Secure random generator is unavailable";
  }
  if (/^Unsupported protocol [a-z0-9+.-]+:/i.test(value)) return "Unsupported request protocol";
  if (/^Failed to (?:construct|execute).*FormData/i.test(value)) {
    return "FormData construction failed";
  }
  if (/^Cannot read propert(?:y|ies) of (?:undefined|null)/i.test(value)) {
    return "JavaScript setup error";
  }
  if (/^(?:SecureStore|AsyncStorage).*not available/i.test(value)) {
    return "Local secure storage is unavailable";
  }

  return "Unclassified error (message omitted)";
};

const safeStackLocation = (stack) => {
  if (typeof stack !== "string") return null;
  for (const line of stack.split(/\r?\n/)) {
    const match = line.match(/(?:^|[/\\])([A-Za-z]+(?:Event|Queue|Storage)?\.ts)(?::(\d+))(?::(\d+))?/);
    if (!match || !SAFE_STACK_FILES.has(match[1])) continue;
    return `${match[1]}:${match[2]}${match[3] ? `:${match[3]}` : ""}`;
  }
  return null;
};

const parsedHttpUrl = (value, baseUrl) => {
  if (typeof value !== "string" || !value.trim()) return null;
  try {
    const parsed = baseUrl ? new URL(value, baseUrl) : new URL(value);
    if (parsed.protocol !== "http:" && parsed.protocol !== "https:") return null;
    return parsed;
  } catch {
    return null;
  }
};

const sanitizePath = (path) => path
  .replace(/\/(?:\d+|[0-9a-f]{8}-[0-9a-f-]{27,})(?=\/|$)/gi, "/:id")
  .replace(/\/[^/]*@[^/]*(?=\/|$)/gi, "/:id")
  .replace(/\/[^/]{24,}(?=\/|$)/g, "/:id");

const sanitizeDiagnosticBaseUrl = (value) => {
  const parsed = parsedHttpUrl(value);
  if (!parsed) return value ? "[invalid API URL]" : null;
  return `${parsed.protocol}//${parsed.host}${sanitizePath(parsed.pathname.replace(/\/+$/, ""))}`;
};

const sanitizeDiagnosticRequestUrl = (baseUrl, endpoint) => {
  let parsed = parsedHttpUrl(endpoint);
  if (!parsed) {
    const base = parsedHttpUrl(baseUrl);
    if (!base || typeof endpoint !== "string" || !endpoint.trim()) {
      return "[unresolved URL]";
    }
    const basePath = base.pathname.replace(/\/+$/, "");
    const requestPath = endpoint.trim().replace(/^\/+/, "");
    parsed = parsedHttpUrl(`${base.origin}${basePath}/${requestPath}`);
  }
  if (!parsed) return "[unresolved URL]";

  const safePath = sanitizePath(parsed.pathname);
  return `${parsed.protocol}//${parsed.host}${safePath}`;
};

const safeMethod = (method) => {
  if (typeof method !== "string") return null;
  const normalized = method.toUpperCase();
  return /^(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS)$/.test(normalized)
    ? normalized
    : null;
};

const safePhase = (phase) => SAFE_PHASES.has(phase) ? phase : "unknown";

const getAttendanceFailureDiagnostics = (
  error,
  completedResponseStatus = null,
  context = {}
) => {
  const priorStatus = Number.isInteger(completedResponseStatus) &&
      completedResponseStatus >= 100 && completedResponseStatus <= 599
    ? completedResponseStatus
    : null;
  const errorStatus = Number.isInteger(error?.response?.status) &&
      error.response.status >= 100 && error.response.status <= 599
    ? error.response.status
    : null;
  const status = priorStatus ?? errorStatus;
  const responseReceived = status !== null;
  const requestPresent = Boolean(error?.request);
  const rawCode = typeof error?.code === "string" ? error.code.toUpperCase() : "";
  const code = /^[A-Z0-9_]{1,40}$/.test(rawCode) ? rawCode : null;
  const config = error?.config ?? error?.response?.config;
  const configuredBaseUrl = context.apiBaseUrl ?? config?.baseURL;
  const endpoint = context.endpoint ?? config?.url;

  return {
    failureKind: responseReceived
      ? priorStatus !== null && errorStatus === null
        ? "local_after_http_response"
        : "http_response"
      : requestPresent
        ? "request_without_response"
        : "local_or_setup",
    responseReceived,
    requestPresent,
    status,
    code,
    errorName: safeErrorName(error?.name),
    message: safeMessage(error?.message),
    location: safeStackLocation(error?.stack),
    apiBaseUrl: sanitizeDiagnosticBaseUrl(configuredBaseUrl),
    endpoint: sanitizeDiagnosticRequestUrl(configuredBaseUrl, endpoint),
    method: safeMethod(context.method ?? config?.method),
    phase: safePhase(context.phase),
  };
};

module.exports = {
  getAttendanceFailureDiagnostics,
  sanitizeDiagnosticBaseUrl,
  sanitizeDiagnosticRequestUrl,
};
