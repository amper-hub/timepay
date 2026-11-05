const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");
const ts = require("typescript");

const sourcePath = path.join(__dirname, "..", "src", "services", "errorMessages.ts");
const source = fs.readFileSync(sourcePath, "utf8");
const compiled = ts.transpile(source, {
  module: ts.ModuleKind.CommonJS,
  target: ts.ScriptTarget.ES2020,
});
const parserModule = { exports: {} };
new Function("exports", "require", "module", compiled)(
  parserModule.exports,
  require,
  parserModule
);

const { getUserFriendlyError, USER_ERROR_MESSAGES } = parserModule.exports;

test("maps a disconnected device to the safe network message", () => {
  assert.equal(
    getUserFriendlyError({ code: "ERR_NETWORK", request: {} }),
    USER_ERROR_MESSAGES.network
  );
});

test("shows an actionable message when the public API URL is missing or invalid", () => {
  assert.equal(
    getUserFriendlyError(new Error("The TimePay API URL is not configured. Set EXPO_PUBLIC_API_BASE_URL and rebuild the app.")),
    "The TimePay API URL is not configured. Set EXPO_PUBLIC_API_BASE_URL and rebuild the app."
  );
  assert.equal(
    getUserFriendlyError(new Error("The production TimePay API URL is invalid. Set EXPO_PUBLIC_API_BASE_URL to the Laravel service's public HTTPS URL and rebuild the app.")),
    "The production TimePay API URL is invalid. Set EXPO_PUBLIC_API_BASE_URL to the Laravel service's public HTTPS URL and rebuild the app."
  );
});

test("maps timeouts and service errors without showing response details", () => {
  assert.equal(
    getUserFriendlyError({ code: "ECONNABORTED", message: "SQL timeout details" }),
    USER_ERROR_MESSAGES.timeout
  );
  assert.equal(
    getUserFriendlyError({ response: { status: 500, data: { message: "SQLSTATE[23000]" } } }),
    USER_ERROR_MESSAGES.server
  );
  assert.equal(
    getUserFriendlyError({ response: { status: 503, data: { message: "internal URL https://host" } } }),
    USER_ERROR_MESSAGES.unavailable
  );
});

test("keeps uncertain and outside GPS results out of the face-failure category", () => {
  assert.equal(
    getUserFriendlyError({ name: "LocationAccuracyError" }, undefined, "face"),
    USER_ERROR_MESSAGES.locationUnclear
  );
  assert.equal(
    getUserFriendlyError({ name: "GeofenceOutsideError" }, undefined, "face"),
    USER_ERROR_MESSAGES.geofenceOutside
  );
  assert.equal(
    getUserFriendlyError(new Error("Location permission was not granted."), undefined, "location"),
    USER_ERROR_MESSAGES.location
  );
});

test("maps common HTTP statuses to friendly messages", () => {
  assert.equal(getUserFriendlyError({ response: { status: 401 } }), USER_ERROR_MESSAGES.unauthorized);
  assert.equal(getUserFriendlyError({ response: { status: 403 } }), USER_ERROR_MESSAGES.forbidden);
  assert.equal(getUserFriendlyError({ response: { status: 404 } }), USER_ERROR_MESSAGES.notFound);
  assert.equal(
    getUserFriendlyError({ response: { status: 400, data: { message: "SQLSTATE[23000]" } } }),
    USER_ERROR_MESSAGES.validation
  );
});

test("keeps safe validation and attendance business messages", () => {
  assert.equal(
    getUserFriendlyError({
      response: {
        status: 422,
        data: { message: "Please check the information you entered and try again.", errors: { email: ["Enter a valid email address."] } },
      },
    }),
    "Enter a valid email address."
  );
  assert.equal(
    getUserFriendlyError({
      config: { url: "/attendance/store" },
      response: { status: 422, data: { message: "You are outside the allowed attendance location." } },
    }),
    "You are outside the allowed attendance location."
  );
});

test("uses the login-specific message for failed credentials", () => {
  assert.equal(
    getUserFriendlyError({ response: { status: 422, data: { errors: { email: ["The credentials do not match."] } } } }, undefined, "login"),
    USER_ERROR_MESSAGES.login
  );
});

test("uses safe sign-in guidance when the account is unavailable", () => {
  assert.equal(
    getUserFriendlyError({ response: { status: 403 } }, undefined, "login"),
    "We couldn't sign you in. Check your details or contact your employer for help."
  );
});

test("maps face verification, location, leave, and password-reset errors", () => {
  assert.equal(
    getUserFriendlyError({ response: { status: 422, data: { error: "face_mismatch" } } }, undefined, "face"),
    USER_ERROR_MESSAGES.face
  );
  assert.equal(
    getUserFriendlyError({ name: "LocationTimeoutError" }),
    USER_ERROR_MESSAGES.location
  );
  assert.equal(
    getUserFriendlyError({ response: { status: 503 } }, undefined, "face"),
    USER_ERROR_MESSAGES.faceUnavailable
  );
  assert.equal(
    getUserFriendlyError({ response: { status: 422 } }, undefined, "leave"),
    USER_ERROR_MESSAGES.validation
  );
  assert.equal(
    getUserFriendlyError({ response: { status: 503 } }, undefined, "passwordReset"),
    USER_ERROR_MESSAGES.emailDelivery
  );
});

test("distinguishes attendance selfie storage failures from provider outages", () => {
  assert.equal(
    getUserFriendlyError({
      response: {
        status: 500,
        data: {
          error: "selfie_storage_failed",
          message: "Internal path C:\\private\\selfie.jpg and exception details",
        },
      },
    }, undefined, "face"),
    USER_ERROR_MESSAGES.faceStorageFailed
  );
  assert.notEqual(
    USER_ERROR_MESSAGES.faceStorageFailed,
    USER_ERROR_MESSAGES.faceUnavailable
  );
});

test("maps provider unavailability to the existing safe provider message", () => {
  assert.equal(
    getUserFriendlyError({
      response: {
        status: 503,
        data: {
          error: "face_verification_unavailable",
          message: "Provider secret and internal URL",
        },
      },
    }, undefined, "face"),
    USER_ERROR_MESSAGES.faceUnavailable
  );
});

test("does not report profile reset server failures as Face verification outages", () => {
  const serverFailure = getUserFriendlyError({
    response: {
      status: 500,
      data: { message: "SQLSTATE details and private storage path" },
    },
  }, "Unable to reset facial recognition right now. Please try again.", "profile");
  const unavailable = getUserFriendlyError({
    response: {
      status: 503,
      data: {
        error: "face_verification_unavailable",
        message: "Provider details and internal URL",
      },
    },
  }, "Unable to reset facial recognition right now. Please try again.", "profile");

  assert.equal(serverFailure, USER_ERROR_MESSAGES.server);
  assert.equal(unavailable, USER_ERROR_MESSAGES.unavailable);
  assert.notEqual(serverFailure, USER_ERROR_MESSAGES.faceUnavailable);
  assert.notEqual(unavailable, USER_ERROR_MESSAGES.faceUnavailable);
});

test("preserves the safe server blink rejection message", () => {
  const message = "The blink verification did not pass. Please follow the on-screen instructions and try again.";
  assert.equal(
    getUserFriendlyError({
      response: {
        status: 422,
        data: { error: "face_liveness_failed", message },
      },
    }, undefined, "face"),
    message
  );
});

test("preserves the safe missing-baseline guidance", () => {
  const message = "Your saved face reference is unavailable. Please reset and enroll it again.";
  assert.equal(
    getUserFriendlyError({
      response: {
        status: 422,
        data: { error: "face_reference_unavailable", message },
      },
    }, undefined, "face"),
    message
  );
});

test("uses a generic safe message for unknown face server errors", () => {
  assert.equal(
    getUserFriendlyError({
      response: {
        status: 500,
        data: { error: "unexpected_internal_error", message: "SQLSTATE and private path" },
      },
    }, undefined, "face"),
    USER_ERROR_MESSAGES.faceUnavailable
  );
});

test("never returns raw exception text or technical response bodies", () => {
  assert.equal(
    getUserFriendlyError(new Error("SQLSTATE[23000] at C:\\app\\vendor\\file.php")),
    USER_ERROR_MESSAGES.unknown
  );
  assert.equal(
    getUserFriendlyError({ response: { status: 422, data: { message: "PDOException /srv/app/vendor/file.php" } } }),
    USER_ERROR_MESSAGES.validation
  );
  assert.equal(
    getUserFriendlyError({ response: { status: 422, data: { message: "Duplicate entry for key users.email at db.internal:3306" } } }),
    USER_ERROR_MESSAGES.validation
  );
  assert.equal(
    getUserFriendlyError({ response: { status: 422, data: { message: "DB_PASSWORD=hunter2" } } }),
    USER_ERROR_MESSAGES.validation
  );
});
