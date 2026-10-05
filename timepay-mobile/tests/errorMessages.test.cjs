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
