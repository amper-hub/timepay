export type CameraPermissionResult = "granted" | "denied" | "settings";

export function requestCameraAccess(options: {
  permission: { canAskAgain?: boolean } | null;
  requestPermission: () => Promise<{ granted: boolean; canAskAgain?: boolean }>;
  openSettings: () => Promise<unknown>;
}): Promise<CameraPermissionResult>;
