const { test } = require("node:test");
const assert = require("node:assert/strict");
const {
  getAttendanceFailureDiagnostics,
  sanitizeDiagnosticBaseUrl,
  sanitizeDiagnosticRequestUrl,
} = require("../src/services/attendanceDiagnostics.js");

const localRequest = {
  apiBaseUrl: "http://127.0.0.1:8000/api",
  endpoint: "/attendance/clock-in",
  method: "POST",
  phase: "http_request",
};

test("records HTTP failure status and sanitized request information", () => {
  assert.deepEqual(
    getAttendanceFailureDiagnostics(
      {
        name: "AxiosError",
        message: "Request failed with status code 422",
        response: { status: 422 },
      },
      null,
      localRequest
    ),
    {
      failureKind: "http_response",
      responseReceived: true,
      requestPresent: false,
      status: 422,
      code: null,
      errorName: "AxiosError",
      message: "HTTP request failed",
      location: null,
      apiBaseUrl: "http://127.0.0.1:8000/api",
      endpoint: "http://127.0.0.1:8000/api/attendance/clock-in",
      method: "POST",
      phase: "http_request",
    }
  );
});

test("distinguishes a request without a response and keeps safe transport detail", () => {
  const diagnostics = getAttendanceFailureDiagnostics(
    {
      name: "AxiosError",
      request: {},
      code: "ERR_NETWORK",
      message: "Network Error",
    },
    null,
    localRequest
  );

  assert.equal(diagnostics.failureKind, "request_without_response");
  assert.equal(diagnostics.responseReceived, false);
  assert.equal(diagnostics.requestPresent, true);
  assert.equal(diagnostics.status, null);
  assert.equal(diagnostics.code, "ERR_NETWORK");
  assert.equal(diagnostics.message, "Network Error");
  assert.equal(diagnostics.phase, "http_request");

  const timeout = getAttendanceFailureDiagnostics(
    { request: {}, code: "ECONNABORTED", message: "timeout of 120000ms exceeded" },
    null,
    localRequest
  );
  assert.equal(timeout.message, "Request timed out");
  assert.equal(timeout.code, "ECONNABORTED");
  assert.equal(timeout.status, null);

  const statusZero = getAttendanceFailureDiagnostics({ response: { status: 0 }, request: {} });
  assert.equal(statusZero.failureKind, "request_without_response");
  assert.equal(statusZero.responseReceived, false);
  assert.equal(statusZero.status, null);
});

test("marks local event metadata failures before Axios creates a request", () => {
  assert.deepEqual(
    getAttendanceFailureDiagnostics(
      {
        name: "SecureStoreError",
        code: "E_SECURE_STORE",
        message: "Employee private account value: user@example.test",
      },
      null,
      { ...localRequest, phase: "event_metadata" }
    ),
    {
      failureKind: "local_or_setup",
      responseReceived: false,
      requestPresent: false,
      status: null,
      code: "E_SECURE_STORE",
      errorName: "SecureStoreError",
      message: "Unclassified error (message omitted)",
      location: null,
      apiBaseUrl: "http://127.0.0.1:8000/api",
      endpoint: "http://127.0.0.1:8000/api/attendance/clock-in",
      method: "POST",
      phase: "event_metadata",
    }
  );
});

test("keeps a safe hint and source location for missing native secure randomness", () => {
  const diagnostics = getAttendanceFailureDiagnostics(
    {
      name: "Error",
      message: "Secure random event IDs are unavailable on this device.",
      stack: "Error: Secure random event IDs are unavailable on this device.\n    at createAttendanceEventId (C:\\\\app\\\\src\\\\services\\\\attendanceEvent.ts:169:11?token=private)",
    },
    null,
    { ...localRequest, phase: "event_metadata" }
  );

  assert.equal(diagnostics.failureKind, "local_or_setup");
  assert.equal(diagnostics.requestPresent, false);
  assert.equal(diagnostics.message, "Secure random generator is unavailable");
  assert.equal(diagnostics.location, "attendanceEvent.ts:169:11");
  assert.equal(JSON.stringify(diagnostics).includes("token=private"), false);
  assert.equal(JSON.stringify(diagnostics).includes("C:\\\\app"), false);
});

test("redacts URL credentials and drops all query and fragment data", () => {
  const error = {
    name: "AxiosError",
    request: {},
    code: "ECONNREFUSED",
    message: "Network Error",
    config: {
      baseURL: "https://employee:private@api.example.test/api?access_token=secret",
      url: "/attendance/clock-in?email=person@example.test#private-fragment",
      method: "post",
    },
  };
  const diagnostics = getAttendanceFailureDiagnostics(error);
  const serialized = JSON.stringify(diagnostics);

  assert.equal(diagnostics.apiBaseUrl, "https://api.example.test/api");
  assert.equal(diagnostics.endpoint, "https://api.example.test/api/attendance/clock-in");
  assert.equal(diagnostics.method, "POST");
  assert.equal(diagnostics.phase, "unknown");
  for (const secret of ["employee", "private", "secret", "person@example.test", "access_token"]) {
    assert.equal(serialized.includes(secret), false);
  }
});

test("records provider and server errors as HTTP responses without exposing body text", () => {
  const diagnostics = getAttendanceFailureDiagnostics(
    {
      name: "AxiosError",
      code: "ERR_BAD_RESPONSE",
      message: "Request failed with status code 503",
      request: {},
      response: { status: 503, data: { message: "private employee/provider detail" } },
      config: { baseURL: "http://localhost:8000/api", url: "/attendance/clock-in", method: "post" },
    }
  );

  assert.equal(diagnostics.failureKind, "http_response");
  assert.equal(diagnostics.status, 503);
  assert.equal(diagnostics.code, "ERR_BAD_RESPONSE");
  assert.equal(diagnostics.message, "HTTP request failed");
  assert.equal(JSON.stringify(diagnostics).includes("private employee/provider detail"), false);
});

test("sanitizes standalone request URLs and omits malformed URLs", () => {
  assert.equal(
    sanitizeDiagnosticBaseUrl("https://name:password@api.example.test/api?token=hidden#hash"),
    "https://api.example.test/api"
  );
  assert.equal(
    sanitizeDiagnosticRequestUrl(
      "http://127.0.0.1:8000/api",
      "/employee/12345?email=private@example.test"
    ),
    "http://127.0.0.1:8000/api/employee/:id"
  );
  assert.equal(
    sanitizeDiagnosticRequestUrl(
      "http://127.0.0.1:8000/api",
      "/profile/private@example.test"
    ),
    "http://127.0.0.1:8000/api/profile/:id"
  );
  assert.equal(sanitizeDiagnosticRequestUrl("not a url", "/attendance/store"), "[unresolved URL]");
});

test("classifies local completion failure after a successful HTTP response", () => {
  const diagnostics = getAttendanceFailureDiagnostics(
    new Error("event metadata contains private information"),
    200,
    { ...localRequest, phase: "event_completion" }
  );

  assert.equal(diagnostics.failureKind, "local_after_http_response");
  assert.equal(diagnostics.responseReceived, true);
  assert.equal(diagnostics.status, 200);
  assert.equal(diagnostics.message, "Unclassified error (message omitted)");
  assert.equal(diagnostics.phase, "event_completion");
});
