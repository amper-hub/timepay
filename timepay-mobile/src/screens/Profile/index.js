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
import { Ionicons } from "@expo/vector-icons";
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
  const [activeSection, setActiveSection] = useState(null);
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
    if (activeSection === "payslips") {
      loadPayslips();
    }
  }, [activeSection, loadPayslips]);

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

  const sectionTitle = {
    editName: "Edit Name",
    accountDetails: "Account Details",
    password: "Update Password",
    facialRecognition: "Facial Recognition",
    payslips: "My Payslips",
  }[activeSection] ?? "Settings";

  return (
    <SafeAreaView style={styles.safeArea}>
      <KeyboardAvoidingView
        behavior={Platform.OS === "ios" ? "padding" : undefined}
        style={styles.flex}
      >
        <View style={styles.topBar}>
          {activeSection ? (
            <TouchableOpacity
              onPress={() => setActiveSection(null)}
              activeOpacity={0.75}
              accessibilityRole="button"
              accessibilityLabel="Back to Profile"
              style={styles.backButton}
            >
              <Text style={styles.backButtonText}>← Back</Text>
            </TouchableOpacity>
          ) : (
            <View style={styles.headerSpacer} />
          )}
          <Text style={styles.topBarTitle}>{sectionTitle}</Text>
          <View style={styles.headerSpacer} />
        </View>

        <ScrollView
          contentContainerStyle={styles.scrollContent}
          showsVerticalScrollIndicator={false}
          keyboardShouldPersistTaps="handled"
        >
          {!activeSection ? (
            <>
              <SectionHeading title="General" />
              <View style={styles.group}>
                <SettingsRow
                  icon="person-outline"
                  label="Edit Name"
                  value={name || "Add your name"}
                  onPress={() => setActiveSection("editName")}
                />
                <SettingsRow
                  icon="mail-outline"
                  label="Account Details"
                  value={userSessionData?.user?.email ?? "View your account"}
                  onPress={() => setActiveSection("accountDetails")}
                />
                <SettingsRow
                  icon="receipt-outline"
                  label="My Payslips"
                  value="View payroll history"
                  isLast
                  onPress={() => setActiveSection("payslips")}
                />
              </View>

              <SectionHeading title="Secure" />
              <View style={styles.group}>
                <SettingsRow
                  icon="key-outline"
                  label="Update Password"
                  onPress={() => setActiveSection("password")}
                />
                <SettingsRow
                  icon="scan-outline"
                  label="Facial Recognition"
                  value="Manage your Face ID baseline"
                  isLast
                  onPress={() => setActiveSection("facialRecognition")}
                />
              </View>

              <SectionHeading title="Account" />
              <View style={styles.group}>
                <SettingsRow
                  icon="log-out-outline"
                  label="Log Out"
                  danger
                  isLast
                  onPress={onLogout}
                />
              </View>
            </>
          ) : null}

          {activeSection === "editName" ? (
            <>
              <SectionHeading title="Personal information" />
              <View style={styles.group}>
                <View style={[styles.inputRow, styles.lastInputRow]}>
                  <Text style={styles.fieldLabel}>Full Name</Text>
                  <TextInput
                    value={name}
                    onChangeText={setName}
                    placeholder="Full name"
                    placeholderTextColor="#9ca3af"
                    autoCapitalize="words"
                    returnKeyType="done"
                    accessibilityLabel="Full name"
                    style={styles.textInput}
                  />
                </View>
              </View>
              <TouchableOpacity
                disabled={savingName}
                onPress={handleSaveName}
                activeOpacity={0.86}
                style={[styles.primaryButton, savingName && styles.disabledButton]}
              >
                {savingName ? (
                  <ActivityIndicator color="#ffffff" />
                ) : (
                  <Text style={styles.primaryButtonText}>Save Name</Text>
                )}
              </TouchableOpacity>
            </>
          ) : null}

          {activeSection === "accountDetails" ? (
            <>
              <SectionHeading title="Your account" />
              <View style={styles.group}>
                <SettingsRow
                  icon="mail-outline"
                  label="Email"
                  value={userSessionData?.user?.email ?? "N/A"}
                />
                <SettingsRow
                  icon="business-outline"
                  label="Company"
                  value={userSessionData?.company?.name ?? "N/A"}
                  isLast
                />
              </View>
            </>
          ) : null}

          {activeSection === "password" ? (
            <>
              <SectionHeading title="Change your password" />
              <View style={styles.group}>
                {[
                  {
                    label: "Current Password",
                    field: "current_password",
                    contentType: "password",
                  },
                  {
                    label: "New Password",
                    field: "password",
                    contentType: "newPassword",
                  },
                  {
                    label: "Confirm Password",
                    field: "password_confirmation",
                    contentType: "newPassword",
                  },
                ].map((item, index, fields) => (
                  <View
                    key={item.field}
                    style={[
                      styles.inputRow,
                      index === fields.length - 1 && styles.lastInputRow,
                    ]}
                  >
                    <Text style={styles.fieldLabel}>{item.label}</Text>
                    <TextInput
                      value={passwordForm[item.field]}
                      onChangeText={(value) =>
                        updatePasswordField(item.field, value)
                      }
                      placeholder={item.label}
                      placeholderTextColor="#9ca3af"
                      secureTextEntry
                      autoCapitalize="none"
                      autoCorrect={false}
                      textContentType={item.contentType}
                      accessibilityLabel={item.label}
                      style={styles.textInput}
                    />
                  </View>
                ))}
              </View>
              <TouchableOpacity
                disabled={updatingPassword}
                onPress={handleUpdatePassword}
                activeOpacity={0.86}
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
            </>
          ) : null}

          {activeSection === "facialRecognition" ? (
            <>
              <SectionHeading title="Security" />
              <Text style={styles.description}>
                Reset your baseline photo if verification keeps failing or your
                appearance has changed. Your next attendance punch will enroll
                a new baseline.
              </Text>
              <View style={styles.group}>
                <SettingsRow
                  icon="refresh-outline"
                  label="Reset Face ID Baseline"
                  value="Re-enroll on your next attendance punch"
                  isLast
                  onPress={() => setConfirmFaceResetVisible(true)}
                />
              </View>
            </>
          ) : null}

          {activeSection === "payslips" ? (
            <>
              <View style={styles.sectionActionHeader}>
                <SectionHeading title="Payroll history" />
                <TouchableOpacity
                  disabled={loadingPayslips}
                  onPress={loadPayslips}
                  activeOpacity={0.8}
                  style={styles.refreshButton}
                >
                  <Ionicons name="refresh-outline" size={16} color="#374151" />
                  <Text style={styles.refreshButtonText}>Refresh</Text>
                </TouchableOpacity>
              </View>
              <View style={styles.group}>
                {loadingPayslips ? (
                  <View style={styles.centerState}>
                    <ActivityIndicator color="#059669" />
                  </View>
                ) : payslips.length === 0 ? (
                  <Text style={styles.emptyText}>
                    No payslips are available yet.
                  </Text>
                ) : (
                  payslips.map((payslip, index) => (
                    <View
                      key={payslip.id}
                      style={[
                        styles.payslipRow,
                        index === payslips.length - 1 && styles.lastPayslipRow,
                      ]}
                    >
                      <View style={styles.payslipDetails}>
                        <Text style={styles.payslipPeriod}>
                          {payslip.pay_period}
                        </Text>
                        <Text style={styles.payslipMeta}>
                          {payslip.regular_hours} hrs - Net{" "}
                          {payslip.formatted_net_pay}
                        </Text>
                      </View>
                      <TouchableOpacity
                        activeOpacity={0.86}
                        disabled={downloadingPayslipId === payslip.id}
                        onPress={() => handleViewPayslip(payslip.id)}
                        style={[
                          styles.viewPayslipButton,
                          downloadingPayslipId === payslip.id &&
                            styles.disabledButton,
                        ]}
                      >
                        {downloadingPayslipId === payslip.id ? (
                          <ActivityIndicator color="#ffffff" />
                        ) : (
                          <Text style={styles.viewPayslipButtonText}>View</Text>
                        )}
                      </TouchableOpacity>
                    </View>
                  ))
                )}
              </View>
            </>
          ) : null}
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
              This clears your current Face ID baseline. Your next attendance
              punch will become the new baseline photo.
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
                style={[
                  styles.dangerButton,
                  resettingFace && styles.disabledButton,
                ]}
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

function SectionHeading({ title }) {
  return <Text style={styles.sectionHeading}>{title}</Text>;
}

function SettingsRow({
  icon,
  label,
  value,
  onPress,
  isLast = false,
  danger = false,
}) {
  const content = (
    <>
      <View style={styles.rowLeading}>
        <Ionicons
          name={icon}
          size={21}
          color={danger ? "#dc2626" : "#111827"}
        />
        <View style={styles.rowText}>
          <Text style={[styles.rowLabel, danger && styles.dangerText]}>
            {label}
          </Text>
          {value ? <Text style={styles.rowValue}>{value}</Text> : null}
        </View>
      </View>
      {onPress ? (
        <Ionicons name="chevron-forward" size={19} color="#9ca3af" />
      ) : null}
    </>
  );

  return onPress ? (
    <TouchableOpacity
      onPress={onPress}
      activeOpacity={0.72}
      accessibilityRole="button"
      accessibilityLabel={label}
      style={[styles.settingRow, isLast && styles.lastSettingRow]}
    >
      {content}
    </TouchableOpacity>
  ) : (
    <View style={[styles.settingRow, isLast && styles.lastSettingRow]}>
      {content}
    </View>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: "#f7f7f7",
  },
  flex: {
    flex: 1,
  },
  topBar: {
    height: 52,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingHorizontal: 18,
    backgroundColor: "#f7f7f7",
  },
  backButton: {
    width: 64,
    height: 40,
    alignItems: "flex-start",
    justifyContent: "center",
  },
  backButtonText: {
    color: "#111827",
    fontSize: 14,
    fontWeight: "600",
  },
  headerSpacer: {
    width: 64,
    height: 40,
  },
  topBarTitle: {
    color: "#111111",
    fontSize: 20,
    fontWeight: "700",
    textAlign: "center",
  },
  scrollContent: {
    paddingHorizontal: 18,
    paddingTop: 12,
    paddingBottom: 32,
  },
  sectionHeading: {
    marginTop: 15,
    marginBottom: 10,
    color: "#252525",
    fontSize: 16,
    fontWeight: "700",
  },
  group: {
    overflow: "hidden",
    borderRadius: 18,
    backgroundColor: "#ffffff",
    shadowColor: "#000000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.035,
    shadowRadius: 9,
    elevation: 1,
  },
  settingRow: {
    minHeight: 64,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingHorizontal: 17,
    paddingVertical: 11,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: "#ededed",
  },
  lastSettingRow: {
    borderBottomWidth: 0,
  },
  rowLeading: {
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    gap: 15,
    paddingRight: 12,
  },
  rowText: {
    flex: 1,
  },
  rowLabel: {
    color: "#181818",
    fontSize: 15,
    fontWeight: "600",
  },
  rowValue: {
    marginTop: 3,
    color: "#777777",
    fontSize: 12,
    fontWeight: "500",
  },
  dangerText: {
    color: "#dc2626",
  },
  description: {
    marginTop: 0,
    marginBottom: 13,
    color: "#666666",
    fontSize: 14,
    lineHeight: 21,
  },
  inputRow: {
    paddingHorizontal: 17,
    paddingVertical: 11,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: "#ededed",
  },
  lastInputRow: {
    borderBottomWidth: 0,
  },
  fieldLabel: {
    color: "#777777",
    fontSize: 12,
    fontWeight: "600",
  },
  textInput: {
    minHeight: 32,
    paddingHorizontal: 0,
    paddingVertical: 5,
    color: "#171717",
    fontSize: 15,
    fontWeight: "500",
  },
  primaryButton: {
    minHeight: 50,
    alignItems: "center",
    justifyContent: "center",
    marginTop: 16,
    borderRadius: 14,
    backgroundColor: "#059669",
  },
  primaryButtonText: {
    color: "#ffffff",
    fontSize: 15,
    fontWeight: "800",
  },
  sectionActionHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  refreshButton: {
    flexDirection: "row",
    alignItems: "center",
    gap: 5,
    marginTop: 15,
    marginBottom: 10,
    paddingHorizontal: 10,
    paddingVertical: 7,
  },
  refreshButtonText: {
    color: "#374151",
    fontSize: 13,
    fontWeight: "600",
  },
  centerState: {
    minHeight: 90,
    alignItems: "center",
    justifyContent: "center",
  },
  emptyText: {
    paddingHorizontal: 17,
    paddingVertical: 20,
    color: "#666666",
    fontSize: 14,
  },
  payslipRow: {
    minHeight: 72,
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: "#ededed",
  },
  lastPayslipRow: {
    borderBottomWidth: 0,
  },
  payslipDetails: {
    flex: 1,
  },
  payslipPeriod: {
    color: "#171717",
    fontSize: 14,
    fontWeight: "700",
  },
  payslipMeta: {
    marginTop: 4,
    color: "#777777",
    fontSize: 12,
    fontWeight: "500",
  },
  viewPayslipButton: {
    minHeight: 38,
    minWidth: 64,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 11,
    backgroundColor: "#111827",
    paddingHorizontal: 12,
  },
  viewPayslipButtonText: {
    color: "#ffffff",
    fontSize: 12,
    fontWeight: "700",
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
    color: "#111827",
    fontSize: 20,
    fontWeight: "800",
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
    fontWeight: "800",
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
    fontWeight: "800",
  },
});

export default ProfileManagementScreen;
