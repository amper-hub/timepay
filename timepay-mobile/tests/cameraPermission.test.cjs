const { test } = require("node:test");
const assert = require("node:assert/strict");
const { requestCameraAccess } = require("../src/services/cameraPermission.js");

test("requests camera permission when the OS can still show the prompt", async () => {
  let requestCount = 0;
  const result = await requestCameraAccess({
    permission: { canAskAgain: true },
    requestPermission: async () => {
      requestCount += 1;
      return { granted: true, canAskAgain: true };
    },
    openSettings: async () => assert.fail("settings should not open"),
  });

  assert.equal(result, "granted");
  assert.equal(requestCount, 1);
});

test("opens system settings after the OS disables further prompts", async () => {
  let settingsOpened = false;
  const result = await requestCameraAccess({
    permission: { canAskAgain: false },
    requestPermission: async () => assert.fail("permission request should not repeat"),
    openSettings: async () => { settingsOpened = true; },
  });

  assert.equal(result, "settings");
  assert.equal(settingsOpened, true);
});

test("surfaces a denial that has not yet become permanent", async () => {
  const result = await requestCameraAccess({
    permission: { canAskAgain: true },
    requestPermission: async () => ({ granted: false, canAskAgain: true }),
    openSettings: async () => assert.fail("settings should not open"),
  });

  assert.equal(result, "denied");
});

test("opens settings immediately when a request becomes permanently denied", async () => {
  let settingsOpened = false;
  const result = await requestCameraAccess({
    permission: { canAskAgain: true },
    requestPermission: async () => ({ granted: false, canAskAgain: false }),
    openSettings: async () => { settingsOpened = true; },
  });

  assert.equal(result, "settings");
  assert.equal(settingsOpened, true);
});
