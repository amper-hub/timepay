const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");
const ts = require("typescript");

const storedValues = new Map();
const secureStore = {
  setItemAsync: async (key, value) => storedValues.set(key, value),
  getItemAsync: async (key) => storedValues.get(key) ?? null,
  deleteItemAsync: async (key) => storedValues.delete(key),
};

const source = fs.readFileSync(
  path.join(__dirname, "../src/services/authStorage.ts"),
  "utf8"
);
const compiled = ts.transpileModule(source, {
  compilerOptions: {
    module: ts.ModuleKind.CommonJS,
    target: ts.ScriptTarget.ES2020,
  },
}).outputText;
const authStorageModule = { exports: {} };
const mockRequire = (name) => {
  if (name === "react-native") return { Platform: { OS: "android" } };
  if (name === "expo-secure-store") return secureStore;
  throw new Error(`Unexpected module import in auth storage test: ${name}`);
};
new Function("require", "module", "exports", compiled)(
  mockRequire,
  authStorageModule,
  authStorageModule.exports
);

const { clearUserSession, loadUserSession, saveUserSession } =
  authStorageModule.exports;
const STORAGE_KEY = "timepay.auth.session.v1";
const session = {
  token: "unit-test-session-token",
  user: { id: 42 },
  company: { id: 7 },
};

test.beforeEach(() => storedValues.clear());

test("saves and retrieves the session through SecureStore", async () => {
  await saveUserSession(session);

  assert.equal(
    storedValues.get(STORAGE_KEY),
    JSON.stringify(session),
    "the existing session key is preserved"
  );
  assert.deepEqual(await loadUserSession(), session);
});

test("deletes the persisted session on sign-out", async () => {
  await saveUserSession(session);

  await clearUserSession();

  assert.equal(storedValues.has(STORAGE_KEY), false);
  assert.equal(await loadUserSession(), null);
});

test("treats malformed sessions and blank tokens as signed out", async () => {
  storedValues.set(STORAGE_KEY, "not-json");
  assert.equal(await loadUserSession(), null);
  assert.equal(storedValues.has(STORAGE_KEY), false);

  storedValues.set(
    STORAGE_KEY,
    JSON.stringify({ ...session, token: "   " })
  );
  assert.equal(await loadUserSession(), null);
  assert.equal(storedValues.has(STORAGE_KEY), false);
});

test("propagates SecureStore errors for the app to handle safely", async () => {
  const originalGet = secureStore.getItemAsync;
  const originalSet = secureStore.setItemAsync;
  const originalDelete = secureStore.deleteItemAsync;
  const storageError = new Error("keystore unavailable");

  try {
    secureStore.getItemAsync = async () => {
      throw storageError;
    };
    await assert.rejects(loadUserSession(), storageError);

    secureStore.setItemAsync = async () => {
      throw storageError;
    };
    await assert.rejects(saveUserSession(session), storageError);

    secureStore.deleteItemAsync = async () => {
      throw storageError;
    };
    await assert.rejects(clearUserSession(), storageError);
  } finally {
    secureStore.getItemAsync = originalGet;
    secureStore.setItemAsync = originalSet;
    secureStore.deleteItemAsync = originalDelete;
  }
});
