/**
 * TimePay Login Screen
 * Employee authentication with forced temporary-password change flow.
 */

import React, { useCallback, useState } from "react";
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from "react-native";
import axios from "axios";
import {
  LoginCredentials,
  UserSession,
  isPasswordChangeRequiredResponse,
} from "../types";
import { apiService, getApiErrorMessage } from "../services/api";
import TimePayLogo from "../components/TimePayLogo";

interface LoginScreenProps {
  onLoginSuccess: (userSession: UserSession) => void;
}

interface FormState {
  email: string;
  password: string;
}

interface FieldErrors {
  email?: string;
  password?: string;
  newPassword?: string;
  confirmPassword?: string;
}

const LoginScreen: React.FC<LoginScreenProps> = ({ onLoginSuccess }) => {
  const [form, setForm] = useState<FormState>({
    email: "",
    password: "",
  });
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [apiError, setApiError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const [isChangingPassword, setIsChangingPassword] = useState(false);
  const [userId, setUserId] = useState<number | null>(null);
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");

  const validateLoginForm = useCallback((): boolean => {
    const nextErrors: FieldErrors = {};
    const email = form.email.trim();

    if (!email) {
      nextErrors.email = "Email is required.";
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      nextErrors.email = "Enter a valid email address.";
    }

    if (!form.password) {
      nextErrors.password = "Password is required.";
    }

    setFieldErrors(nextErrors);
    return Object.keys(nextErrors).length === 0;
  }, [form]);

  const updateField = useCallback(
    (field: keyof FormState, value: string) => {
      setForm((current) => ({ ...current, [field]: value }));
      setApiError(null);

      if (fieldErrors[field]) {
        setFieldErrors((current) => ({ ...current, [field]: undefined }));
      }
    },
    [fieldErrors]
  );

  const resetPasswordChangeState = useCallback(() => {
    setIsChangingPassword(false);
    setUserId(null);
    setNewPassword("");
    setConfirmPassword("");
    setFieldErrors({});
    setApiError(null);
  }, []);

  const handleCancelPasswordChange = useCallback(() => {
    resetPasswordChangeState();
    setForm({ email: "", password: "" });
  }, [resetPasswordChangeState]);

  const handleLogin = useCallback(async () => {
    setApiError(null);

    if (!validateLoginForm()) {
      return;
    }

    setLoading(true);

    try {
      const credentials: LoginCredentials = {
        email: form.email.trim(),
        password: form.password,
      };

      const userSession = await apiService.login(credentials);
      setForm({ email: "", password: "" });
      resetPasswordChangeState();
      onLoginSuccess(userSession);
    } catch (error) {
      if (axios.isAxiosError(error) && error.response?.status === 403) {
        const responseData = error.response.data;

        if (isPasswordChangeRequiredResponse(responseData)) {
          setUserId(responseData.user_id);
          setIsChangingPassword(true);
          setApiError(null);
          setFieldErrors({});
          return;
        }
      }

      setApiError(
        getApiErrorMessage(
          error,
          "We could not sign you in. Please check your credentials and try again."
        )
      );
    } finally {
      setLoading(false);
    }
  }, [form, onLoginSuccess, resetPasswordChangeState, validateLoginForm]);

  const handlePasswordUpdate = useCallback(async () => {
    setApiError(null);

    const nextErrors: FieldErrors = {};

    if (!newPassword) {
      nextErrors.newPassword = "New password is required.";
    } else if (newPassword.length < 8) {
      nextErrors.newPassword = "Password must be at least 8 characters.";
    }

    if (!confirmPassword) {
      nextErrors.confirmPassword = "Please confirm your new password.";
    } else if (newPassword !== confirmPassword) {
      nextErrors.confirmPassword = "Passwords do not match.";
    }

    if (Object.keys(nextErrors).length > 0) {
      setFieldErrors(nextErrors);
      return;
    }

    if (!userId) {
      setApiError("Unable to continue. Please sign in again.");
      resetPasswordChangeState();
      return;
    }

    setLoading(true);

    try {
      const userSession = await apiService.updateTemporaryPassword({
        user_id: userId,
        current_password: form.password,
        new_password: newPassword,
        new_password_confirmation: confirmPassword,
      });

      setForm({ email: "", password: "" });
      resetPasswordChangeState();
      onLoginSuccess(userSession);
    } catch (error) {
      setApiError(
        getApiErrorMessage(
          error,
          "We could not update your password. Please try again."
        )
      );
    } finally {
      setLoading(false);
    }
  }, [
    confirmPassword,
    form.password,
    newPassword,
    onLoginSuccess,
    resetPasswordChangeState,
    userId,
  ]);

  return (
    <SafeAreaView style={styles.safeArea}>
      <View pointerEvents="none" style={styles.topAccent}>
        <View style={styles.accentArcLarge} />
        <View style={styles.accentArcSmall} />
      </View>
      <KeyboardAvoidingView
        behavior={Platform.OS === "ios" ? "padding" : undefined}
        style={styles.keyboardView}
      >
        <ScrollView
          contentContainerStyle={styles.scrollContent}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          <View style={styles.shell}>
            <View style={styles.brandRow}>
              <TimePayLogo style={styles.loginLogo} />
            </View>

            {isChangingPassword ? (
              <>
                <Text style={styles.heading}>Secure your account</Text>
                <Text style={styles.subheading}>
                  Welcome! Please secure your account by setting a new permanent
                  password.
                </Text>
              </>
            ) : (
              <>
                <Text style={styles.heading}>Sign in to your workspace</Text>
                <Text style={styles.subheading}>
                  Track attendance, verify location, and keep your day moving.
                </Text>
              </>
            )}

            {apiError ? (
              <View style={styles.alertBox}>
                <Text style={styles.alertTitle}>
                  {isChangingPassword ? "Password update failed" : "Sign in failed"}
                </Text>
                <Text style={styles.alertMessage}>{apiError}</Text>
              </View>
            ) : null}

            <View style={styles.formCard}>
              {!isChangingPassword ? (
                <>
                  <View style={styles.fieldGroup}>
                    <Text style={styles.label}>Email</Text>
                    <TextInput
                      value={form.email}
                      onChangeText={(value) => updateField("email", value)}
                      placeholder="john@example.com"
                      placeholderTextColor="#94a3b8"
                      autoCapitalize="none"
                      autoComplete="email"
                      autoCorrect={false}
                      editable={!loading}
                      keyboardType="email-address"
                      style={[
                        styles.input,
                        fieldErrors.email ? styles.inputInvalid : null,
                      ]}
                    />
                    {fieldErrors.email ? (
                      <Text style={styles.fieldError}>{fieldErrors.email}</Text>
                    ) : null}
                  </View>

                  <View style={styles.fieldGroup}>
                    <Text style={styles.label}>Password</Text>
                    <TextInput
                      value={form.password}
                      onChangeText={(value) => updateField("password", value)}
                      placeholder="Enter your password"
                      placeholderTextColor="#94a3b8"
                      autoComplete="password"
                      editable={!loading}
                      secureTextEntry
                      style={[
                        styles.input,
                        fieldErrors.password ? styles.inputInvalid : null,
                      ]}
                    />
                    {fieldErrors.password ? (
                      <Text style={styles.fieldError}>{fieldErrors.password}</Text>
                    ) : null}
                  </View>

                  <TouchableOpacity
                    activeOpacity={0.88}
                    disabled={loading}
                    onPress={handleLogin}
                    style={[
                      styles.primaryButton,
                      loading ? styles.primaryButtonDisabled : null,
                    ]}
                  >
                    {loading ? (
                      <ActivityIndicator color="#ffffff" />
                    ) : (
                      <Text style={styles.primaryButtonText}>Sign In</Text>
                    )}
                  </TouchableOpacity>

                  <View style={styles.secondaryLinks}>
                    <Text style={styles.secondaryLinkText}>Forgot Password?</Text>
                    <Text style={styles.secondaryLinkDivider}>|</Text>
                    <Text style={styles.secondaryLinkText}>Contact Admin</Text>
                  </View>
                </>
              ) : (
                <>
                  <View style={styles.infoBox}>
                    <Text style={styles.infoTitle}>Temporary password active</Text>
                    <Text style={styles.infoMessage}>
                      Your temporary password from sign-in will be used to verify
                      this change.
                    </Text>
                    <TextInput
                      value={form.password.replace(/./g, "•")}
                      editable={false}
                      secureTextEntry={false}
                      style={[styles.input, styles.inputReadOnly]}
                    />
                  </View>

                  <View style={styles.fieldGroup}>
                    <Text style={styles.label}>New Password</Text>
                    <TextInput
                      value={newPassword}
                      onChangeText={(value) => {
                        setNewPassword(value);
                        setApiError(null);
                        if (fieldErrors.newPassword) {
                          setFieldErrors((current) => ({
                            ...current,
                            newPassword: undefined,
                          }));
                        }
                      }}
                      placeholder="Enter a new password"
                      placeholderTextColor="#94a3b8"
                      autoCapitalize="none"
                      autoComplete="password-new"
                      autoCorrect={false}
                      editable={!loading}
                      secureTextEntry
                      style={[
                        styles.input,
                        fieldErrors.newPassword ? styles.inputInvalid : null,
                      ]}
                    />
                    {fieldErrors.newPassword ? (
                      <Text style={styles.fieldError}>
                        {fieldErrors.newPassword}
                      </Text>
                    ) : null}
                  </View>

                  <View style={styles.fieldGroup}>
                    <Text style={styles.label}>Confirm New Password</Text>
                    <TextInput
                      value={confirmPassword}
                      onChangeText={(value) => {
                        setConfirmPassword(value);
                        setApiError(null);
                        if (fieldErrors.confirmPassword) {
                          setFieldErrors((current) => ({
                            ...current,
                            confirmPassword: undefined,
                          }));
                        }
                      }}
                      placeholder="Re-enter your new password"
                      placeholderTextColor="#94a3b8"
                      autoCapitalize="none"
                      autoComplete="password-new"
                      autoCorrect={false}
                      editable={!loading}
                      secureTextEntry
                      style={[
                        styles.input,
                        fieldErrors.confirmPassword ? styles.inputInvalid : null,
                      ]}
                    />
                    {fieldErrors.confirmPassword ? (
                      <Text style={styles.fieldError}>
                        {fieldErrors.confirmPassword}
                      </Text>
                    ) : null}
                  </View>

                  <TouchableOpacity
                    activeOpacity={0.88}
                    disabled={loading}
                    onPress={handlePasswordUpdate}
                    style={[
                      styles.primaryButton,
                      loading ? styles.primaryButtonDisabled : null,
                    ]}
                  >
                    {loading ? (
                      <ActivityIndicator color="#ffffff" />
                    ) : (
                      <Text style={styles.primaryButtonText}>Save & Login</Text>
                    )}
                  </TouchableOpacity>

                  <TouchableOpacity
                    activeOpacity={0.88}
                    disabled={loading}
                    onPress={handleCancelPasswordChange}
                    style={styles.secondaryButton}
                  >
                    <Text style={styles.secondaryButtonText}>Cancel</Text>
                  </TouchableOpacity>
                </>
              )}
            </View>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: "#F8FAFC",
    position: "relative",
  },
  topAccent: {
    position: "absolute",
    top: -120,
    left: -80,
    right: -80,
    height: 260,
    overflow: "hidden",
  },
  accentArcLarge: {
    position: "absolute",
    top: 0,
    left: 20,
    width: 320,
    height: 220,
    borderRadius: 160,
    backgroundColor: "#D1FAE5",
  },
  accentArcSmall: {
    position: "absolute",
    top: 72,
    right: 36,
    width: 180,
    height: 128,
    borderRadius: 90,
    backgroundColor: "#99F6E4",
    opacity: 0.72,
  },
  keyboardView: {
    flex: 1,
  },
  scrollContent: {
    flexGrow: 1,
    justifyContent: "center",
    paddingHorizontal: 24,
    paddingVertical: 32,
  },
  shell: {
    width: "100%",
    maxWidth: 420,
    alignSelf: "center",
  },
  brandRow: {
    flexDirection: "row",
    alignItems: "center",
    marginBottom: 24,
  },
  loginLogo: {
    width: 156,
    height: 52,
  },
  heading: {
    fontSize: 22,
    fontWeight: "700",
    color: "#0F172A",
  },
  subheading: {
    marginTop: 8,
    marginBottom: 24,
    color: "#64748b",
    fontSize: 15,
    lineHeight: 22,
  },
  alertBox: {
    marginBottom: 16,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: "#fecaca",
    backgroundColor: "#fef2f2",
    paddingHorizontal: 16,
    paddingVertical: 14,
  },
  alertTitle: {
    color: "#991b1b",
    fontSize: 14,
    fontWeight: "700",
  },
  alertMessage: {
    marginTop: 4,
    color: "#b91c1c",
    fontSize: 14,
    lineHeight: 20,
  },
  infoBox: {
    marginBottom: 18,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: "#A7F3D0",
    backgroundColor: "#ECFDF5",
    paddingHorizontal: 16,
    paddingVertical: 14,
  },
  infoTitle: {
    color: "#065F46",
    fontSize: 14,
    fontWeight: "700",
  },
  infoMessage: {
    marginTop: 4,
    marginBottom: 12,
    color: "#047857",
    fontSize: 13,
    lineHeight: 20,
  },
  formCard: {
    borderWidth: 1,
    borderColor: "#D1FAE5",
    borderRadius: 20,
    backgroundColor: "#ffffff",
    padding: 20,
    shadowColor: "#0f172a",
    shadowOffset: { width: 0, height: 18 },
    shadowOpacity: 0.08,
    shadowRadius: 24,
    elevation: 4,
  },
  fieldGroup: {
    marginBottom: 18,
  },
  label: {
    marginBottom: 8,
    color: "#334155",
    fontSize: 14,
    fontWeight: "700",
  },
  input: {
    minHeight: 52,
    borderWidth: 1,
    borderColor: "#D9E2EC",
    borderRadius: 14,
    backgroundColor: "#F8FAFC",
    paddingHorizontal: 15,
    color: "#0f172a",
    fontSize: 16,
  },
  inputReadOnly: {
    backgroundColor: "#F8FAFC",
    color: "#64748b",
  },
  inputInvalid: {
    borderColor: "#ef4444",
    backgroundColor: "#fff7f7",
  },
  fieldError: {
    marginTop: 7,
    color: "#dc2626",
    fontSize: 13,
    fontWeight: "600",
  },
  primaryButton: {
    minHeight: 54,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 16,
    backgroundColor: "#059669",
    shadowColor: "#059669",
    shadowOffset: { width: 0, height: 12 },
    shadowOpacity: 0.22,
    shadowRadius: 16,
    elevation: 4,
  },
  primaryButtonDisabled: {
    opacity: 0.68,
  },
  primaryButtonText: {
    color: "#ffffff",
    fontSize: 16,
    fontWeight: "800",
  },
  secondaryButton: {
    marginTop: 12,
    minHeight: 50,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 14,
    borderWidth: 1,
    borderColor: "#cbd5e1",
    backgroundColor: "#ffffff",
  },
  secondaryButtonText: {
    color: "#334155",
    fontSize: 15,
    fontWeight: "700",
  },
  secondaryLinks: {
    flexDirection: "row",
    justifyContent: "center",
    alignItems: "center",
    marginTop: 18,
  },
  secondaryLinkText: {
    color: "#059669",
    fontSize: 14,
    fontWeight: "700",
  },
  secondaryLinkDivider: {
    color: "#CBD5E1",
    marginHorizontal: 10,
    fontWeight: "700",
  },
});

export default LoginScreen;
