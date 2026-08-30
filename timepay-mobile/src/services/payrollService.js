import * as FileSystem from "expo-file-system/legacy";
import * as Sharing from "expo-sharing";
import apiClient, { getApiBaseUrl, getAuthToken } from "./api";

export const getPayslips = async () => {
  const response = await apiClient.get("/payroll/payslips");
  return response.data.data ?? [];
};

export const downloadAndSharePayslip = async (payslipId) => {
  const token = getAuthToken();

  if (!token) {
    throw new Error("You must be signed in to download a payslip.");
  }

  if (!FileSystem.documentDirectory) {
    throw new Error("Device document storage is not available.");
  }

  const fileUri = `${FileSystem.documentDirectory}timepay-payslip-${payslipId}.pdf`;
  const downloadUrl = `${getApiBaseUrl()}/payroll/payslip/${payslipId}/download`;

  const result = await FileSystem.downloadAsync(downloadUrl, fileUri, {
    headers: {
      Accept: "application/pdf",
      Authorization: `Bearer ${token}`,
    },
  });

  if (result.status < 200 || result.status >= 300) {
    throw new Error("Unable to download this payslip.");
  }

  const canShare = await Sharing.isAvailableAsync();

  if (canShare) {
    await Sharing.shareAsync(result.uri, {
      dialogTitle: "View Payslip",
      mimeType: "application/pdf",
      UTI: "com.adobe.pdf",
    });
  }

  return result.uri;
};

export default {
  downloadAndSharePayslip,
  getPayslips,
};
