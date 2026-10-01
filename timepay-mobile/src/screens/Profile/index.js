import React, { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
  Modal,
  Platform,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from "react-native";
import {
  resetFaceBaseline,
  updateName,
  updatePassword,
} from "../../services/profileService";
import { getApiErrorMessage } from "../../services/api";
import {
  downloadAndSharePayslip,
  getPayslips,
} from "../../services/payrollService";

const ProfileManagementScreen = ({ userSessionData, onLogout }) => {
  const [activeTab, setActiveTab] = useState("account");
  const [name, setName] = useState(userSessionData?.user?.name ?? "");
  const [savingName, setSavingName] = useState(false);
  const [passwordForm, setPasswordForm] = useState({
    current_password: "",
    password: "",
    password_confirmation: "",
  });
  const [updatingPassword, setUpdatingPassword] = useState(false);
  const [confirmFaceResetVisible, setConfirmFaceResetVisible] = useState(false);
  const [resettingFace, setResettingFace] = useState(false);
  const [payslips, setPayslips] = useState([]);
  const [loadingPayslips, setLoadingPayslips] = useState(false);
  const [downloadingPayslipId, setDownloadingPayslipId] = useState(null);

  useEffect(() => {
    setName(userSessionData?.user?.name ?? "");
  }, [userSessionData?.user?.name]);

  const loadPayslips = useCallback(async () => {
    setLoadingPayslips(true);

    try {
      const payrollHistory = await getPayslips();
      setPayslips(payrollHistory);
    } catch (error) {
      Alert.alert(
        "Unable to load payslips",
        getApiErrorMessage(error, "Unable to load your payroll history.")
      );
    } finally {
      setLoadingPayslips(false);
    }
  }, []);

  useEffect(() => {
    if (activeTab === "payslips") {
      loadPayslips();
    }
  }, [activeTab, loadPayslips]);

  const updatePasswordField = useCallback((field, value) => {
    setPasswordForm((current) => ({ ...current, [field]: value }));
  }, []);

  const handleSaveName = useCallback(async () => {
    if (name.trim().length < 2) {
      Alert.alert("Name required", "Please enter your full name.");
      return;
    }

    setSavingName(true);

    try {
      await updateName(name.trim());
      Alert.alert("Profile updated", "Your name has been saved.");
    } catch (error) {
      Alert.alert(
        "Unable to update",
        getApiErrorMessage(error, "Unable to update your name. Please try again.")
      );
    } finally {
      setSavingName(false);
    }
  }, [name]);

  const handleUpdatePassword = useCallback(async () => {
    if (passwordForm.password.length < 8) {
      Alert.alert("Password too short", "New password must be at least 8 characters.");
      return;
    }

    if (passwordForm.password !== passwordForm.password_confirmation) {
      Alert.alert("Passwords do not match", "Please confirm your new password.");
      return;
    }

    setUpdatingPassword(true);

    try {
      await updatePassword(passwordForm);
      setPasswordForm({
        current_password: "",
        password: "",
        password_confirmation: "",
      });
      Alert.alert("Password updated", "Your password has been changed.");
    } catch (error) {
      Alert.alert(
        "Unable to update password",
        getApiErrorMessage(
          error,
          "Unable to update your password. Please check your current password."
        )
      );
    } finally {
      setUpdatingPassword(false);
    }
  }, [passwordForm]);

  const handleResetFace = useCallback(async () => {
    setResettingFace(true);

    try {
      await resetFaceBaseline();
      setConfirmFaceResetVisible(false);
      Alert.alert(
        "Face ID reset",
        "Your next attendance punch will re-enroll your facial baseline."
      );
    } catch (error) {
      Alert.alert(
        "Unable to reset Face ID",
        getApiErrorMessage(
          error,
          "Unable to reset facial recognition right now. Please try again."
        )
      );
    } finally {
      setResettingFace(false);
    }
  }, []);

  const handleViewPayslip = useCallback(async (payslipId) => {
    setDownloadingPayslipId(payslipId);

    try {
      await downloadAndSharePayslip(payslipId);
    } catch (error) {
      Alert.alert(
        "Unable to open payslip",
        getApiErrorMessage(error, "Unable to download your payslip right now.")
      );
    } finally {
      setDownloadingPayslipId(null);
    }
  }, []);

  return (
    <SafeAreaView style={styles.safeArea}>
      <KeyboardAvoidingView
        behavior={Platform.OS === "ios" ? "padding" : undefined}
        style={styles.flex}
      >
        <ScrollView
          contentContainerStyle={styles.scrollContent}
          showsVerticalScrollIndicator={false}
        >
          <View style={styles.hero}>
            <View style={styles.eyebrowBadge}>
              <Text style={styles.eyebrow}>PROFILE</Text>
            </View>
            <Text style={styles.title}>{name || "Employee"}</Text>
            <Text style={styles.subtitle}>
              Keep your account details current and manage your facial recognition baseline.
            </Text>
          </View>

          <View style={styles.tabBar}>
            <TouchableOpacity
              activeOpacity={0.86}
              onPress={() => setActiveTab("account")}
              accessibilityRole="tab"
              accessibilityState={{ selected: activeTab === "account" }}
              style={[
                styles.tabButton,
                activeTab === "account" && styles.activeTabButton,
              ]}
            >
              <Text
                style={[
                  styles.tabButtonText,
                  activeTab === "account" && styles.activeTabButtonText,
                ]}
              >
                Account
              </Text>
            </TouchableOpacity>
            <TouchableOpacity
              activeOpacity={0.86}
              onPress={() => setActiveTab("payslips")}
              accessibilityRole="tab"
              accessibilityState={{ selected: activeTab === "payslips" }}
              style={[
                styles.tabButton,
                activeTab === "payslips" && styles.activeTabButton,
              ]}
            >
              <Text
                style={[
                  styles.tabButtonText,
                  activeTab === "payslips" && styles.activeTabButtonText,
                ]}
              >
                My Payslips
              </Text>
            </TouchableOpacity>
          </View>

          {activeTab === "account" ? (
            <>
              <View style={styles.infoCard}>
                <Text style={styles.cardTitle}>Account Details</Text>
                <Text style={styles.label}>Email</Text>
                <Text style={styles.value}>
                  {userSessionData?.user?.email ?? "N/A"}
                </Text>
                <View style={styles.detailDivider} />
                <Text style={styles.label}>Company</Text>
                <Text style={[styles.value, styles.lastValue]}>
                  {userSessionData?.company?.name ?? "N/A"}
                </Text>
              </View>

              <View style={styles.card}>
                <Text style={styles.cardTitle}>Edit Name</Text>
                <Text style={styles.label}>Full Name</Text>
                <TextInput
                  value={name}
                  onChangeText={setName}
                  placeholder="Full name"
                  placeholderTextColor="#94a3b8"
                  autoCapitalize="words"
                  returnKeyType="done"
                  accessibilityLabel="Full name"
                  style={styles.input}
                />
                <TouchableOpacity
                  disabled={savingName}
                  onPress={handleSaveName}
                  activeOpacity={0.88}
                  style={[styles.primaryButton, savingName && styles.disabledButton]}
                >
                  {savingName ? (
                    <ActivityIndicator color="#ffffff" />
                  ) : (
                    <Text style={styles.primaryButtonText}>Save Name</Text>
                  )}
                </TouchableOpacity>
              </View>

              <View style={styles.card}>
                <Text style={styles.cardTitle}>Update Password</Text>
                <Text style={styles.label}>Current Password</Text>
                <TextInput
                  value={passwordForm.current_password}
                  onChangeText={(value) => updatePasswordField("current_password", value)}
                  placeholder="Current Password"
                  placeholderTextColor="#94a3b8"
                  secureTextEntry
                  autoCapitalize="none"
                  autoCorrect={false}
                  textContentType="password"
                  accessibilityLabel="Current password"
                  style={styles.input}
                />
                <Text style={styles.label}>New Password</Text>
                <TextInput
                  value={passwordForm.password}
                  onChangeText={(value) => updatePasswordField("password", value)}
                  placeholder="New Password"
                  placeholderTextColor="#94a3b8"
                  secureTextEntry
                  autoCapitalize="none"
                  autoCorrect={false}
                  textContentType="newPassword"
                  accessibilityLabel="New password"
                  style={styles.input}
                />
                <Text style={styles.label}>Confirm Password</Text>
                <TextInput
                  value={passwordForm.password_confirmation}
                  onChangeText={(value) =>
                    updatePasswordField("password_confirmation", value)
                  }
                  placeholder="Confirm Password"
                  placeholderTextColor="#94a3b8"
                  secureTextEntry
                  autoCapitalize="none"
                  autoCorrect={false}
                  textContentType="newPassword"
                  accessibilityLabel="Confirm password"
                  style={styles.input}
                />
                <TouchableOpacity
                  disabled={updatingPassword}
                  onPress={handleUpdatePassword}
                  activeOpacity={0.88}
                  style={[
                    styles.primaryButton,
                    updatingPassword && styles.disabledButton,
                  ]}
                >
                  {updatingPassword ? (
                    <ActivityIndicator color="#ffffff" />
                  ) : (
                    <Text style={styles.primaryButtonText}>Update Password</Text>
                  )}
                </TouchableOpacity>
              </View>

              <View style={styles.faceCard}>
                <Text style={styles.faceTitle}>Facial Recognition</Text>
                <Text style={styles.faceText}>
                  Reset your baseline photo if your verification keeps failing or your appearance has changed.
                </Text>
                <TouchableOpacity
                  activeOpacity={0.9}
                  onPress={() => setConfirmFaceResetVisible(true)}
                  accessibilityRole="button"
                  style={styles.faceButton}
                >
                  <Text style={styles.faceButtonText}>Update Facial Recognition</Text>
                  <Text style={styles.faceButtonSubtext}>Re-enroll Face ID</Text>
                </TouchableOpacity>
              </View>
            </>
          ) : (
            <View style={styles.card}>
              <View style={styles.cardHeader}>
                <Text style={styles.cardTitle}>My Payslips</Text>
                <TouchableOpacity
                  disabled={loadingPayslips}
                  onPress={loadPayslips}
                  activeOpacity={0.86}
                  style={styles.refreshButton}
                >
                  <Text style={styles.refreshButtonText}>Refresh</Text>
                </TouchableOpacity>
              </View>

              {loadingPayslips ? (
                <View style={styles.centerState}>
                  <ActivityIndicator color="#059669" />
                </View>
              ) : payslips.length === 0 ? (
                <Text style={styles.emptyText}>No payslips are available yet.</Text>
              ) : (
                payslips.map((payslip) => (
                  <View key={payslip.id} style={styles.payslipRow}>
                    <View style={styles.payslipDetails}>
                      <Text style={styles.payslipPeriod}>{payslip.pay_period}</Text>
                      <Text style={styles.payslipMeta}>
                        {payslip.regular_hours} hrs - Net {payslip.formatted_net_pay}
                      </Text>
                    </View>
                    <TouchableOpacity
                      activeOpacity={0.86}
                      disabled={downloadingPayslipId === payslip.id}
                      onPress={() => handleViewPayslip(payslip.id)}
                      style={[
                        styles.viewPayslipButton,
                        downloadingPayslipId === payslip.id && styles.disabledButton,
                      ]}
                    >
                      {downloadingPayslipId === payslip.id ? (
                        <ActivityIndicator color="#ffffff" />
                      ) : (
                        <Text style={styles.viewPayslipButtonText}>View Payslip</Text>
                      )}
                    </TouchableOpacity>
                  </View>
                ))
              )}
            </View>
          )}

          <TouchableOpacity
            activeOpacity={0.88}
            onPress={onLogout}
            accessibilityRole="button"
            style={styles.logoutButton}
          >
            <Text style={styles.logoutText}>Log Out</Text>
          </TouchableOpacity>
        </ScrollView>
      </KeyboardAvoidingView>

      <Modal
        visible={confirmFaceResetVisible}
        transparent
        animationType="fade"
        onRequestClose={() => setConfirmFaceResetVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>Reset facial baseline?</Text>
            <Text style={styles.modalText}>
              This clears your current Face ID baseline. Your next attendance punch will become the new baseline photo.
            </Text>
            <View style={styles.modalActions}>
              <TouchableOpacity
                disabled={resettingFace}
                onPress={() => setConfirmFaceResetVisible(false)}
                style={styles.cancelButton}
              >
                <Text style={styles.cancelButtonText}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity
                disabled={resettingFace}
                onPress={handleResetFace}
                style={[styles.dangerButton, resettingFace && styles.disabledButton]}
              >
                {resettingFace ? (
                  <ActivityIndicator color="#ffffff" />
                ) : (
                  <Text style={styles.dangerButtonText}>Reset Face ID</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: "#f8fafc",
  },
  flex: {
    flex: 1,
  },
  scrollContent: {
    paddingHorizontal: 20,
    paddingTop: 20,
    paddingBottom: 36,
  },
  hero: {
    marginBottom: 20,
  },
  eyebrowBadge: {
    alignSelf: "flex-start",
    borderRadius: 999,
    borderWidth: 1,
    borderColor: "#a7f3d0",
    backgroundColor: "#ecfdf5",
    paddingHorizontal: 10,
    paddingVertical: 5,
  },
  eyebrow: {
    color: "#059669",
    fontSize: 12,
    fontWeight: "900",
    letterSpacing: 0.8,
    textTransform: "uppercase",
  },
  title: {
    marginTop: 12,
    color: "#0f172a",
    fontSize: 30,
    fontWeight: "900",
  },
  subtitle: {
    marginTop: 10,
    color: "#64748b",
    fontSize: 15,
    lineHeight: 22,
  },
  tabBar: {
    flexDirection: "row",
    borderRadius: 14,
    borderWidth: 1,
    borderColor: "#e2e8f0",
    backgroundColor: "#ffffff",
    padding: 4,
    marginBottom: 16,
  },
  tabButton: {
    flex: 1,
    minHeight: 42,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 10,
  },
  activeTabButton: {
    backgroundColor: "#059669",
  },
  tabButtonText: {
    color: "#64748b",
    fontSize: 14,
    fontWeight: "900",
  },
  activeTabButtonText: {
    color: "#ffffff",
  },
  infoCard: {
    borderRadius: 16,
    borderWidth: 1,
    borderColor: "#e2e8f0",
    backgroundColor: "#ffffff",
    padding: 18,
    marginBottom: 14,
  },
  card: {
    borderRadius: 16,
    borderWidth: 1,
    borderColor: "#e2e8f0",
    backgroundColor: "#ffffff",
    padding: 18,
    marginBottom: 14,
  },
  cardTitle: {
    color: "#0f172a",
    fontSize: 18,
    fontWeight: "900",
    marginBottom: 14,
  },
  cardHeader: {
    alignItems: "center",
    flexDirection: "row",
    justifyContent: "space-between",
    marginBottom: 4,
  },
  label: {
    color: "#64748b",
    fontSize: 12,
    fontWeight: "900",
    letterSpacing: 0.5,
    textTransform: "uppercase",
    marginBottom: 6,
  },
  value: {
    marginBottom: 12,
    color: "#0f172a",
    fontSize: 15,
    fontWeight: "800",
  },
  detailDivider: {
    height: 1,
    backgroundColor: "#e2e8f0",
    marginBottom: 14,
  },
  lastValue: {
    marginBottom: 0,
  },
  input: {
    minHeight: 50,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: "#e2e8f0",
    backgroundColor: "#f8fafc",
    paddingHorizontal: 14,
    color: "#0f172a",
    fontSize: 15,
    fontWeight: "600",
    marginBottom: 12,
  },
  primaryButton: {
    minHeight: 50,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 12,
    backgroundColor: "#059669",
  },
  primaryButtonText: {
    color: "#ffffff",
    fontSize: 15,
    fontWeight: "900",
  },
  refreshButton: {
    borderRadius: 12,
    borderWidth: 1,
    borderColor: "#cbd5e1",
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  refreshButtonText: {
    color: "#334155",
    fontSize: 12,
    fontWeight: "900",
  },
  centerState: {
    alignItems: "center",
    justifyContent: "center",
    minHeight: 90,
  },
  emptyText: {
    color: "#64748b",
    fontSize: 14,
    fontWeight: "700",
    lineHeight: 20,
    paddingVertical: 16,
  },
  payslipRow: {
    alignItems: "center",
    borderTopWidth: 1,
    borderTopColor: "#e2e8f0",
    flexDirection: "row",
    gap: 12,
    paddingVertical: 14,
  },
  payslipDetails: {
    flex: 1,
  },
  payslipPeriod: {
    color: "#0f172a",
    fontSize: 14,
    fontWeight: "900",
  },
  payslipMeta: {
    color: "#64748b",
    fontSize: 12,
    fontWeight: "700",
    marginTop: 4,
  },
  viewPayslipButton: {
    alignItems: "center",
    justifyContent: "center",
    minHeight: 42,
    minWidth: 112,
    borderRadius: 12,
    backgroundColor: "#0f172a",
    paddingHorizontal: 12,
  },
  viewPayslipButtonText: {
    color: "#ffffff",
    fontSize: 12,
    fontWeight: "900",
  },
  faceCard: {
    borderRadius: 16,
    backgroundColor: "#0f172a",
    padding: 18,
    marginBottom: 14,
  },
  faceTitle: {
    color: "#ffffff",
    fontSize: 19,
    fontWeight: "900",
  },
  faceText: {
    marginTop: 8,
    color: "#cbd5e1",
    fontSize: 14,
    lineHeight: 20,
  },
  faceButton: {
    marginTop: 16,
    minHeight: 52,
    justifyContent: "center",
    borderRadius: 12,
    backgroundColor: "#059669",
    paddingHorizontal: 16,
    paddingVertical: 12,
  },
  faceButtonText: {
    color: "#ffffff",
    fontSize: 15,
    fontWeight: "900",
  },
  faceButtonSubtext: {
    marginTop: 4,
    color: "#d1fae5",
    fontSize: 12,
    fontWeight: "800",
  },
  logoutButton: {
    minHeight: 54,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 12,
    backgroundColor: "#dc2626",
    marginTop: 2,
  },
  logoutText: {
    color: "#ffffff",
    fontSize: 16,
    fontWeight: "900",
  },
  disabledButton: {
    opacity: 0.65,
  },
  modalOverlay: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "rgba(15, 23, 42, 0.48)",
    padding: 24,
  },
  modalCard: {
    width: "100%",
    borderRadius: 16,
    backgroundColor: "#ffffff",
    padding: 20,
  },
  modalTitle: {
    color: "#0f172a",
    fontSize: 21,
    fontWeight: "900",
  },
  modalText: {
    marginTop: 8,
    color: "#64748b",
    fontSize: 14,
    lineHeight: 21,
  },
  modalActions: {
    flexDirection: "row",
    gap: 12,
    marginTop: 18,
  },
  cancelButton: {
    flex: 1,
    alignItems: "center",
    borderRadius: 14,
    borderWidth: 1,
    borderColor: "#cbd5e1",
    paddingVertical: 14,
  },
  cancelButtonText: {
    color: "#334155",
    fontSize: 14,
    fontWeight: "900",
  },
  dangerButton: {
    flex: 1,
    alignItems: "center",
    borderRadius: 14,
    backgroundColor: "#dc2626",
    paddingVertical: 14,
  },
  dangerButtonText: {
    color: "#ffffff",
    fontSize: 14,
    fontWeight: "900",
  },
});

export default ProfileManagementScreen;
