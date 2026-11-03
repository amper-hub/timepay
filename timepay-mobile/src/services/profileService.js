import apiClient from "./api";

export const updateName = async (name) => {
  const response = await apiClient.patch("/profile/name", { name });
  return response.data;
};

export const updatePassword = async (payload) => {
  const response = await apiClient.patch("/profile/password", payload);
  return response.data;
};

export default {
  updateName,
  updatePassword,
};
