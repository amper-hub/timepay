import apiClient from "./api";

const unwrapLeaves = (payload) => {
  if (Array.isArray(payload)) {
    return payload;
  }

  if (Array.isArray(payload?.data)) {
    return payload.data;
  }

  return [];
};

export const fetchLeaves = async () => {
  const response = await apiClient.get("/employee/leaves");
  return unwrapLeaves(response.data);
};

export const fetchLeaveBalance = async () => {
  const response = await apiClient.get("/employee/leave-balance");
  return response.data;
};

export const submitLeave = async (data) => {
  const response = await apiClient.post("/employee/leaves", data);
  return response.data?.data ?? response.data;
};

export default {
  fetchLeaveBalance,
  fetchLeaves,
  submitLeave,
};
