/**
 * TimePay Login Screen
 * Modern employee authentication UI with Laravel validation-safe error handling.
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
import { LoginCredentials, UserSession } from "../types";
import { apiService, getApiErrorMessage } from "../services/api";

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
}

const LoginScreen: React.FC<LoginScreenProps> = ({ onLoginSuccess }) => {
  const [form, setForm] = useState<FormState>({
    email: "",
    password: "",
  });
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [apiError, setApiError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const validateForm = useCallback((): boolean => {
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

  const handleLogin = useCallback(async () => {
    setApiError(null);

    if (!validateForm()) {
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
      onLoginSuccess(userSession);
    } catch (error) {
      setApiError(
        getApiErrorMessage(
          error,
          "We could not sign you in. Please check your credentials and try again."
        )
      );
    } finally {
      setLoading(false);
    }
  }, [form, onLoginSuccess, validateForm]);

  return (
    <SafeAreaView style={styles.safeArea}>
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
            <View style={styles.brandMark}>
              <Text style={styles.brandMarkText}>TP</Text>
            </View>

            <Text style={styles.logoText}>TimePay</Text>
            <Text style={styles.heading}>Sign in to your workspace</Text>
            <Text style={styles.subheading}>
              Track attendance, verify location, and keep your day moving.
            </Text>

            {apiError ? (
              <View style={styles.alertBox}>
                <Text style={styles.alertTitle}>Sign in failed</Text>
                <Text style={styles.alertMessage}>{apiError}</Text>
              </View>
            ) : null}

            <View style={styles.formCard}>
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
                  styles.signInButton,
                  loading ? styles.signInButtonDisabled : null,
                ]}
              >
                {loading ? (
                  <ActivityIndicator color="#ffffff" />
                ) : (
                  <Text style={styles.signInButtonText}>Sign In</Text>
                )}
              </TouchableOpacity>
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
    backgroundColor: "#f8fafc",
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
  brandMark: {
    width: 56,
    height: 56,
    borderRadius: 16,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#4f46e5",
    shadowColor: "#4f46e5",
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.22,
    shadowRadius: 18,
    elevation: 5,
  },
  brandMarkText: {
    color: "#ffffff",
    fontSize: 18,
    fontWeight: "800",
    letterSpacing: 0.5,
  },
  logoText: {
    marginTop: 18,
    fontSize: 34,
    fontWeight: "800",
    color: "#0f172a",
  },
  heading: {
    marginTop: 12,
    fontSize: 22,
    fontWeight: "700",
    color: "#111827",
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
  formCard: {
    borderWidth: 1,
    borderColor: "#e2e8f0",
    borderRadius: 24,
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
    borderColor: "#cbd5e1",
    borderRadius: 14,
    backgroundColor: "#ffffff",
    paddingHorizontal: 15,
    color: "#0f172a",
    fontSize: 16,
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
  signInButton: {
    minHeight: 54,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 14,
    backgroundColor: "#4f46e5",
    shadowColor: "#4f46e5",
    shadowOffset: { width: 0, height: 12 },
    shadowOpacity: 0.22,
    shadowRadius: 16,
    elevation: 4,
  },
  signInButtonDisabled: {
    opacity: 0.68,
  },
  signInButtonText: {
    color: "#ffffff",
    fontSize: 16,
    fontWeight: "800",
  },
});

export default LoginScreen;
