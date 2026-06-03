/**
 * TimePay Login Screen
 * Responsive authentication interface with modern SaaS styling
 */

import React, { useState, useCallback } from "react";
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  ScrollView,
  Alert,
  KeyboardAvoidingView,
  Platform,
} from "react-native";
import { AxiosError } from "axios";
import { LoginCredentials, UserSession, ApiErrorResponse } from "../types";
import { apiService } from "../services/api";

interface LoginScreenProps {
  onLoginSuccess: (userSession: UserSession) => void;
}

interface FormState {
  email: string;
  password: string;
}

interface ValidationErrors {
  email?: string;
  password?: string;
}

const LoginScreen: React.FC<LoginScreenProps> = ({ onLoginSuccess }) => {
  const [form, setForm] = useState<FormState>({
    email: "",
    password: "",
  });

  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<ValidationErrors>({});
  const [apiError, setApiError] = useState<string | null>(null);

  /**
   * Validate form inputs
   */
  const validateForm = useCallback((): boolean => {
    const newErrors: ValidationErrors = {};

    if (!form.email.trim()) {
      newErrors.email = "Email is required";
    } else if (!isValidEmail(form.email)) {
      newErrors.email = "Please enter a valid email address";
    }

    if (!form.password.trim()) {
      newErrors.password = "Password is required";
    } else if (form.password.length < 6) {
      newErrors.password = "Password must be at least 6 characters";
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  }, [form]);

  /**
   * Email validation helper
   */
  const isValidEmail = (email: string): boolean => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  };

  /**
   * Handle form input changes
   */
  const handleInputChange = useCallback(
    (field: keyof FormState, value: string) => {
      setForm((prev) => ({
        ...prev,
        [field]: value,
      }));
      // Clear field error when user starts typing
      if (errors[field]) {
        setErrors((prev) => ({
          ...prev,
          [field]: undefined,
        }));
      }
    },
    [errors]
  );

  /**
   * Handle login submission
   */
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

      // Login succeeded if we got a UserSession object
      if (userSession && userSession.token && userSession.user) {
        // Clear form on successful login
        setForm({ email: "", password: "" });
        // Trigger parent callback with session data
        onLoginSuccess(userSession);
      }
    } catch (error) {
      const axiosError = error as AxiosError<ApiErrorResponse>;

      let errorMessage = "An unexpected error occurred. Please try again.";

      if (axiosError.response) {
        const status = axiosError.response.status;
        const responseData = axiosError.response.data;

        if (status === 401) {
          errorMessage = "Invalid email or password. Please try again.";
        } else if (status === 422) {
          // Validation errors from server
          if (responseData.errors) {
            const errorMessages = Object.values(responseData.errors)
              .flat()
              .join("\n");
            errorMessage = errorMessages || responseData.message;
          } else {
            errorMessage = responseData.message || "Validation error occurred.";
          }
        } else if (status === 429) {
          errorMessage = "Too many login attempts. Please try again later.";
        } else {
          errorMessage = responseData.message || errorMessage;
        }
      } else if (axiosError.request) {
        errorMessage =
          "Unable to reach the server. Please check your connection and the server URL configuration.";
      }

      setApiError(errorMessage);
    } finally {
      setLoading(false);
    }
  }, [form, validateForm]);

  return (
    <KeyboardAvoidingView
      behavior={Platform.OS === "ios" ? "padding" : "height"}
      style={styles.container}
    >
      <ScrollView
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        {/* Header Section */}
        <View style={styles.headerSection}>
          <Text style={styles.appTitle}>TimePay</Text>
          <Text style={styles.appSubtitle}>Employee Attendance System</Text>
        </View>

        {/* API Error Banner */}
        {apiError && (
          <View style={styles.errorBanner}>
            <Text style={styles.errorBannerText}>{apiError}</Text>
          </View>
        )}

        {/* Form Section */}
        <View style={styles.formSection}>
          {/* Email Input */}
          <View style={styles.inputContainer}>
            <Text style={styles.inputLabel}>Email Address</Text>
            <TextInput
              style={[styles.input, errors.email && styles.inputError]}
              placeholder="you@example.com"
              placeholderTextColor="#999"
              keyboardType="email-address"
              autoCapitalize="none"
              autoCorrect={false}
              editable={!loading}
              value={form.email}
              onChangeText={(value) => handleInputChange("email", value)}
            />
            {errors.email && (
              <Text style={styles.fieldError}>{errors.email}</Text>
            )}
          </View>

          {/* Password Input */}
          <View style={styles.inputContainer}>
            <Text style={styles.inputLabel}>Password</Text>
            <TextInput
              style={[styles.input, errors.password && styles.inputError]}
              placeholder="••••••••"
              placeholderTextColor="#999"
              secureTextEntry
              editable={!loading}
              value={form.password}
              onChangeText={(value) => handleInputChange("password", value)}
            />
            {errors.password && (
              <Text style={styles.fieldError}>{errors.password}</Text>
            )}
          </View>

          {/* Login Button */}
          <TouchableOpacity
            style={[styles.loginButton, loading && styles.loginButtonDisabled]}
            onPress={handleLogin}
            disabled={loading}
            activeOpacity={0.8}
          >
            {loading ? (
              <ActivityIndicator color="#fff" size="small" />
            ) : (
              <Text style={styles.loginButtonText}>Sign In</Text>
            )}
          </TouchableOpacity>
        </View>

        {/* Footer Section */}
        <View style={styles.footerSection}>
          <Text style={styles.footerText}>
            Secured by{" "}
            <Text style={styles.footerTextBold}>TimePay Security</Text>
          </Text>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#f8f9fa",
  },
  scrollContent: {
    flexGrow: 1,
    justifyContent: "center",
    paddingHorizontal: 24,
    paddingVertical: 40,
  },
  headerSection: {
    alignItems: "center",
    marginBottom: 50,
  },
  appTitle: {
    fontSize: 36,
    fontWeight: "700",
    color: "#1a1a1a",
    marginBottom: 8,
  },
  appSubtitle: {
    fontSize: 16,
    color: "#666",
    fontWeight: "400",
  },
  errorBanner: {
    backgroundColor: "#fee",
    borderLeftWidth: 4,
    borderLeftColor: "#c33",
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderRadius: 4,
    marginBottom: 24,
  },
  errorBannerText: {
    color: "#c33",
    fontSize: 14,
    fontWeight: "500",
    lineHeight: 20,
  },
  formSection: {
    marginBottom: 40,
  },
  inputContainer: {
    marginBottom: 20,
  },
  inputLabel: {
    fontSize: 14,
    fontWeight: "600",
    color: "#1a1a1a",
    marginBottom: 8,
  },
  input: {
    backgroundColor: "#fff",
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 6,
    paddingHorizontal: 16,
    paddingVertical: 12,
    fontSize: 16,
    color: "#1a1a1a",
  },
  inputError: {
    borderColor: "#c33",
    backgroundColor: "#fff9f9",
  },
  fieldError: {
    color: "#c33",
    fontSize: 12,
    marginTop: 6,
    fontWeight: "500",
  },
  loginButton: {
    backgroundColor: "#007AFF",
    paddingVertical: 14,
    borderRadius: 6,
    alignItems: "center",
    justifyContent: "center",
    marginTop: 28,
  },
  loginButtonDisabled: {
    opacity: 0.7,
  },
  loginButtonText: {
    color: "#fff",
    fontSize: 16,
    fontWeight: "600",
  },
  footerSection: {
    alignItems: "center",
    marginTop: 20,
  },
  footerText: {
    fontSize: 12,
    color: "#999",
    fontWeight: "400",
  },
  footerTextBold: {
    fontWeight: "600",
    color: "#666",
  },
});

export default LoginScreen;
