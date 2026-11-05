const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");
const ts = require("typescript");

const values = new Map();
let generatedId = 0;
const expoCrypto = {
  randomUUID: () => {
    generatedId += 1;
    return `00000000-0000-4000-8000-${generatedId.toString(16).padStart(12, "0")}`;
  },
};
const secureStore = {
  getItemAsync: async (key) => values.get(key) ?? null,
  setItemAsync: async (key, value) => values.set(key, value),
  deleteItemAsync: async (key) => values.delete(key),
};
let session;
const events = loadTs("../src/services/attendanceEvent.ts", {
  "expo-crypto": expoCrypto,
  "expo-secure-store": secureStore,
  "react-native": { Platform: { OS: "android" } },
  "./authStorage": { loadUserSession: async () => session },
});

function loadTs(relativePath, moduleMocks = {}) {
  const source = fs.readFileSync(path.join(__dirname, relativePath), "utf8");
  const compiled = ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 },
  }).outputText;
  const result = { exports: {} };
  new Function("require", "module", "exports", compiled)(
    (name) => moduleMocks[name] ?? (() => { throw new Error(`Unexpected import: ${name}`); })(),
    result,
    result.exports
  );
  return result.exports;
}

test.beforeEach(() => {
  values.clear();
  session = { user: { id: 9 }, company: { id: 3 }, token: "test-token" };
});

test("a retry after an ambiguous request failure reuses its persisted event ID and timestamp", async () => {
  const first = await events.getOrCreateAttendanceEvent("clock_in");
  // A transport failure does not clear this SecureStore record.
  const retry = await events.getOrCreateAttendanceEvent("clock_in");
  const reloaded = loadTs("../src/services/attendanceEvent.ts", {
    "expo-crypto": expoCrypto,
    "expo-secure-store": secureStore,
    "react-native": { Platform: { OS: "android" } },
    "./authStorage": { loadUserSession: async () => session },
  });
  assert.match(first.eventId, /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i);
  assert.deepEqual(retry, first);
  assert.deepEqual(await reloaded.getOrCreateAttendanceEvent("clock_in"), first);
});

test("metadata creation uses Expo secure UUIDs when the runtime has no Web Crypto global", async () => {
  const descriptor = Object.getOwnPropertyDescriptor(globalThis, "crypto");
  try {
    Object.defineProperty(globalThis, "crypto", {
      configurable: true,
      value: undefined,
    });
    const event = await events.getOrCreateAttendanceEvent("clock_in");
    assert.match(event.eventId, /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i);
    assert.equal(typeof event.capturedAt, "string");
    assert.ok(Number.isFinite(Date.parse(event.capturedAt)));
  } finally {
    if (descriptor) Object.defineProperty(globalThis, "crypto", descriptor);
    else delete globalThis.crypto;
  }
});

test("separate clock-in and clock-out events receive distinct event IDs", async () => {
  const clockIn = await events.getOrCreateAttendanceEvent("clock_in");
  const clockOut = await events.getOrCreateAttendanceEvent("clock_out");
  assert.notEqual(clockIn.eventId, clockOut.eventId);
});

test("a completed event clears only its own ID before the next event", async () => {
  const first = await events.getOrCreateAttendanceEvent("clock_in");
  await events.completeAttendanceEvent("clock_in", "different-event-id");
  assert.deepEqual(await events.getOrCreateAttendanceEvent("clock_in"), first);
  await events.completeAttendanceEvent("clock_in", first.eventId);
  const next = await events.getOrCreateAttendanceEvent("clock_in");
  assert.notEqual(next.eventId, first.eventId);
});

