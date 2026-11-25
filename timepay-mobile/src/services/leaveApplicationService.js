import * as FileSystem from "expo-file-system/legacy";
import * as Print from "expo-print";
import * as Sharing from "expo-sharing";
import { getApiBaseUrl, getAuthToken } from "./api";

const downloadLeaveApplication = async (leaveRequestId) => {
  const token = getAuthToken();

  if (!token) {
    throw new Error("Sign in again to access this leave application.");
  }

  if (!FileSystem.documentDirectory) {
    throw new Error("Device document storage is not available.");
  }

  const safeId = encodeURIComponent(String(leaveRequestId));
  const uri = `${FileSystem.documentDirectory}Leave-Application-${safeId}.pdf`;
  const url = `${getApiBaseUrl()}/employee/leaves/${safeId}/application.pdf`;
  const result = await FileSystem.downloadAsync(url, uri, {
    idempotent: true,
    headers: {
      Accept: "application/pdf",
      Authorization: `Bearer ${token}`,
    },
  });

  if (result.status < 200 || result.status >= 300) {
    await FileSystem.deleteAsync(result.uri, { idempotent: true }).catch(() => undefined);
  }

  if (result.status === 401 || result.status === 403) {
    throw new Error("You are not authorized to access this leave application.");
  }

  if (result.status === 404) {
    throw new Error("This leave request is no longer available.");
  }

  if (result.status < 200 || result.status >= 300) {
    throw new Error("Unable to download this leave application. Please try again.");
  }

  return result.uri;
};

export const previewAndPrintLeaveApplication = async (leaveRequestId) => {
  const uri = await downloadLeaveApplication(leaveRequestId);
  await Print.printAsync({ uri });
  return uri;
};

export const shareLeaveApplication = async (leaveRequestId) => {
  const uri = await downloadLeaveApplication(leaveRequestId);
  const canShare = await Sharing.isAvailableAsync();

  if (!canShare) {
    throw new Error("PDF sharing is not available on this device.");
  }

  await Sharing.shareAsync(uri, {
    dialogTitle: "Leave Application Form",
    mimeType: "application/pdf",
    UTI: "com.adobe.pdf",
  });

  return uri;
};
