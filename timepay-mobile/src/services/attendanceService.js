import apiClient from "./api";

const unwrapData = (payload) => {
  if (Array.isArray(payload)) {
    return payload;
  }

  if (Array.isArray(payload?.data)) {
    return payload.data;
  }

  return [];
};

export const fetchAttendanceHistory = async () => {
  const response = await apiClient.get("/attendance/history");
  return unwrapData(response.data);
};

export const clockInAfterFaceVerification = async ({
  latitude,
  longitude,
  photoUri,
}) => {
  const formData = new FormData();
  formData.append("latitude", String(latitude));
  formData.append("longitude", String(longitude));
  formData.append("selfie", {
    uri: photoUri,
    name: `clock_in_${Date.now()}.jpg`,
    type: "image/jpeg",
  });

  const response = await apiClient.post("/attendance/clock-in", formData, {
    headers: {
      "Content-Type": "multipart/form-data",
    },
  });

  return response.data;
};

export const submitFaceVerificationPunch = async ({
  action,
  latitude,
  longitude,
  photoUri,
}) => {
  const formData = new FormData();
  formData.append("latitude", String(latitude));
  formData.append("longitude", String(longitude));
  formData.append("selfie", {
    uri: photoUri,
    name: `${action}_${Date.now()}.jpg`,
    type: "image/jpeg",
  });

  const endpoint =
    action === "clock_in" ? "/attendance/clock-in" : "/attendance/store";

  if (action !== "clock_in") {
    formData.append("type", action);
  }

  const response = await apiClient.post(endpoint, formData, {
    headers: {
      "Content-Type": "multipart/form-data",
    },
  });

  return response.data;
};

export default {
  fetchAttendanceHistory,
  clockInAfterFaceVerification,
  submitFaceVerificationPunch,
};
