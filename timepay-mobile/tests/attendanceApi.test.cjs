const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");
const ts = require("typescript");

const values = new Map();
let networkLookup;
let postHandler;
const requests = [];
const secureStore = {
  getItemAsync: async (key) => values.get(key) ?? null,
  setItemAsync: async (key, value) => values.set(key, value),
  deleteItemAsync: async (key) => values.delete(key),
};
const crypto = { randomUUID: () => "10000000-0000-4000-8000-000000000001" };
const attendanceEvents = loadTs("../src/services/attendanceEvent.ts", {
  "expo-crypto": crypto,
  "expo-secure-store": secureStore,
  "react-native": { Platform: { OS: "android" } },
  "./authStorage": { loadUserSession: async () => session },
});
let session;
const axiosClient = {
  defaults: { headers: { common: {} } },
  interceptors: {
    request: { use: () => {} },
    response: { use: () => {} },
  },
  post: async (...args) => {
    requests.push(args);
    return postHandler(...args);
  },
  get: async () => ({ data: {} }),
  put: async () => ({ data: {} }),
  delete: async () => ({ data: {} }),
};
const axios = { create: () => axiosClient };

const api = loadTs("../src/services/api.ts", {
  axios: { __esModule: true, default: axios },
  "expo-network": { getNetworkStateAsync: () => networkLookup() },
  "react-native": { Alert: { alert: () => {} } },
  "./errorMessages": {
    getUserFriendlyError: () => "safe error",
    isSafeUserMessage: () => false,
  },
  "../types": {
    isLaravelAuthResponse: () => false,
  },
  "./attendanceEvent": attendanceEvents,
  "./attendanceDiagnostics": {
    getAttendanceFailureDiagnostics: () => ({}),
    sanitizeDiagnosticRequestUrl: () => "https://api.example.test/api/attendance",
  },
});

function loadTs(relativePath, moduleMocks = {}) {
  const source = fs.readFileSync(path.join(__dirname, relativePath), "utf8");
  const compiled = ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 },
  }).outputText;
  const result = { exports: {} };
  new Function("require", "module", "exports", "__DEV__", compiled)(
    (name) => {
      if (Object.hasOwn(moduleMocks, name)) return moduleMocks[name];
      throw new Error(`Unexpected import: ${name}`);
    },
    result,
    result.exports,
    false
  );
  return result.exports;
}

const input = () => ["clock_in", 14.5, 121, "file:///selfie.jpg"];
const eventStorageKey = "timepay.attendance.event.v1.3.9.clock_in";
const submit = () => api.apiService.submitAttendancePunch(...input());

test.beforeEach(() => {
  values.clear();
  requests.length = 0;
  session = { user: { id: 9 }, company: { id: 3 }, token: "test-token" };
  networkLookup = async () => ({ isConnected: true, isInternetReachable: true });
  postHandler = async () => ({ status: 200, data: { success: true } });
});

test("explicit disconnected or unreachable status blocks the attendance POST", async (t) => {
  for (const state of [
    { isConnected: false, isInternetReachable: true },
    { isConnected: true, isInternetReachable: false },
  ]) {
    await t.test(JSON.stringify(state), async () => {
      networkLookup = async () => state;
      await assert.rejects(submit(), /internet connection is required/i);
      assert.equal(requests.length, 0);
    });
  }
});

test("unknown connectivity and a failed connectivity lookup proceed to Axios", async (t) => {
  for (const lookup of [
    async () => ({}),
    async () => ({ isConnected: null, isInternetReachable: null }),
    async () => { throw new Error("network status unavailable"); },
  ]) {
    await t.test("connectivity hint is inconclusive", async () => {
      networkLookup = lookup;
      const response = await submit();
      assert.deepEqual(response, { success: true });
      assert.equal(requests.length, 1);
    });
  }
});

test("successful response completes only matching event metadata", async () => {
  const first = await attendanceEvents.getOrCreateAttendanceEvent("clock_in");
  const other = {
    eventId: "10000000-0000-4000-8000-000000000099",
    capturedAt: "2026-10-22T08:00:00.000Z",
  };
  const result = await api.apiService.submitAttendancePunch(...input(), undefined, other);
  assert.deepEqual(result, { success: true });
  assert.deepEqual(await attendanceEvents.getOrCreateAttendanceEvent("clock_in"), first);
});

test("ambiguous connection failure retains event ID and timestamp for a stable retry", async () => {
  const timeout = Object.assign(new Error("timeout of 120000ms exceeded"), {
    code: "ECONNABORTED",
    request: {},
  });
  postHandler = async () => { throw timeout; };

  await assert.rejects(submit(), (error) => error === timeout);
  const original = JSON.parse(values.get(eventStorageKey));
  postHandler = async (...args) => ({ status: 200, data: { eventId: args[1].get("event_id") } });
  const response = await submit();

  assert.equal(requests.length, 2);
  assert.equal(requests[0][1].get("event_id"), original.eventId);
  assert.equal(requests[0][1].get("captured_at"), original.capturedAt);
  assert.equal(requests[1][1].get("event_id"), original.eventId);
  assert.equal(requests[1][1].get("captured_at"), original.capturedAt);
  assert.equal(response.eventId, original.eventId);
  assert.equal(values.has(eventStorageKey), false);
});

test("HTTP server failure propagates and does not complete the event", async () => {
  const serverError = Object.assign(new Error("Request failed with status code 503"), {
    response: { status: 503, data: { success: false } },
  });
  postHandler = async () => { throw serverError; };

  await assert.rejects(submit(), (error) => error === serverError);
  const stored = JSON.parse(values.get(eventStorageKey));
  assert.equal(requests.length, 1);
  assert.match(stored.eventId, /^[0-9a-f-]{36}$/i);
  assert.ok(Number.isFinite(Date.parse(stored.capturedAt)));
});

test("Face Verification submission lock rejects repeated taps while a submission is active", () => {
  const screen = fs.readFileSync(
    path.join(__dirname, "../src/screens/FaceVerification/index.js"),
    "utf8"
  );
  assert.match(screen, /if\s*\(!cameraRef\.current\s*\|\|\s*!cameraReady\s*\|\|\s*submittingRef\.current\)\s*\{\s*return;\s*\}/);
  assert.match(screen, /submittingRef\.current\s*=\s*true;/);
  assert.match(screen, /finally\s*\{[\s\S]*?submittingRef\.current\s*=\s*false;/);
});

