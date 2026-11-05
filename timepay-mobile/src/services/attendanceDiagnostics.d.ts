export type AttendanceFailureKind =
  | "http_response"
  | "request_without_response"
  | "local_or_setup"
  | "local_after_http_response";

export interface AttendanceFailureDiagnostics {
  failureKind: AttendanceFailureKind;
  responseReceived: boolean;
  requestPresent: boolean;
  status: number | null;
  code: string | null;
  errorName: string;
  message: string;
  location: string | null;
  apiBaseUrl: string | null;
  endpoint: string;
  method: string | null;
  phase: AttendanceFailurePhase;
}

export type AttendanceFailurePhase =
  | "event_metadata"
  | "connectivity_check"
  | "form_data"
  | "http_request"
  | "event_completion"
  | "axios_interceptor"
  | "unknown";

export interface AttendanceDiagnosticContext {
  apiBaseUrl?: string | null;
  endpoint?: string | null;
  method?: string | null;
  phase?: AttendanceFailurePhase;
}

export function getAttendanceFailureDiagnostics(
  error: unknown,
  completedResponseStatus?: number | null,
  context?: AttendanceDiagnosticContext
): AttendanceFailureDiagnostics;

export function sanitizeDiagnosticBaseUrl(value?: string | null): string | null;
export function sanitizeDiagnosticRequestUrl(
  baseUrl?: string | null,
  endpoint?: string | null
): string;
