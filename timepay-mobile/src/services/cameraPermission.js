/**
 * Ask for camera access only from an explicit user action. Android stops
 * showing the prompt after repeated denials, so that state must route to OS
 * settings instead of calling the request API again.
 */
const requestCameraAccess = async ({
  permission,
  requestPermission,
  openSettings,
}) => {
  if (permission?.canAskAgain === false) {
    await openSettings();
    return "settings";
  }

  const result = await requestPermission();
  if (!result.granted && result.canAskAgain === false) {
    await openSettings();
    return "settings";
  }

  return result.granted ? "granted" : "denied";
};

module.exports = { requestCameraAccess };
