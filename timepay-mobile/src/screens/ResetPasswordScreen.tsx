import React, { useState } from "react";
import {
  ActivityIndicator,
  Alert,
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
import TimePayLogo from "../components/TimePayLogo";
import { apiService, getApiErrorMessage } from "../services/api";

interface ResetPasswordScreenProps {
  email: string;
  onBack: () => void;
  onComplete: () => void;
}

const ResetPasswordScreen: React.FC<ResetPasswordScreenProps> = ({
  email,
  onBack,
  onComplete,
}) => {
  const [otp, setOtp] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async () => {
    if (!/^\d{6}$/.test(otp)) {
      setError("Enter the six-digit code from your email.");
      return;
    }

    if (password.length < 8) {
      setError("Your new password must be at least 8 characters.");
      return;
    }

    if (password !== confirmPassword) {
      setError("The passwords do not match.");
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const response = await apiService.resetPasswordWithOtp({
        email,
        otp,
        password,
        password_confirmation: confirmPassword,
      });

      Alert.alert("Password updated", response.message, [
        { text: "Return to sign in", onPress: onComplete },
      ]);
    } catch (requestError) {
      setError(
        getApiErrorMessage(
          requestError,
          "We could not reset your password. Please try again."
        )
      );
    } finally {
      setLoading(false);
    }
  };

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
            <TouchableOpacity
              accessibilityRole="button"
              accessibilityLabel="Back to email entry"
              disabled={loading}
              onPress={onBack}
              style={styles.backButton}
            >
              <Text style={styles.backButtonText}>← Back</Text>
            </TouchableOpacity>

            <TimePayLogo style={styles.logo} />
            <Text style={styles.heading}>Create a new password</Text>
            <Text style={styles.subheading}>
              Enter the six-digit code sent to {email} and choose a new password.
            </Text>

            <View style={styles.card}>
              <Text style={styles.label}>Six-digit email code</Text>
              <TextInput
                accessibilityLabel="Six-digit email code"
                value={otp}
                onChangeText={(value) => {
                  setOtp(value.replace(/\D/g, "").slice(0, 6));
                  setError(null);
                }}
                placeholder="000000"
                placeholderTextColor="#94A3B8"
                keyboardType="number-pad"
                textContentType="oneTimeCode"
                maxLength={6}
                editable={!loading}
                style={[styles.input, styles.otpInput]}
              />

              <Text style={[styles.label, styles.passwordLabel]}>New password</Text>
              <TextInput
                accessibilityLabel="New password"
                value={password}
                onChangeText={(value) => {
                  setPassword(value);
                  setError(null);
                }}
                placeholder="At least 8 characters"
                placeholderTextColor="#94A3B8"
                autoCapitalize="none"
                autoComplete="new-password"
                autoCorrect={false}
                editable={!loading}
                secureTextEntry
                style={styles.input}
              />

              <Text style={[styles.label, styles.passwordLabel]}>
                Confirm new password
              </Text>
              <TextInput
                accessibilityLabel="Confirm new password"
                value={confirmPassword}
                onChangeText={(value) => {
                  setConfirmPassword(value);
                  setError(null);
                }}
                placeholder="Re-enter your new password"
                placeholderTextColor="#94A3B8"
                autoCapitalize="none"
                autoComplete="new-password"
                autoCorrect={false}
                editable={!loading}
                secureTextEntry
                returnKeyType="done"
                onSubmitEditing={handleSubmit}
                style={styles.input}
              />

              {error ? (
                <View accessibilityRole="alert" style={styles.errorBox}>
                  <Text style={styles.errorText}>{error}</Text>
                </View>
              ) : null}

              <TouchableOpacity
                accessibilityRole="button"
                activeOpacity={0.88}
                disabled={loading}
                onPress={handleSubmit}
                style={[styles.primaryButton, loading && styles.disabledButton]}
              >
                {loading ? (
                  <ActivityIndicator color="#FFFFFF" />
                ) : (
                  <Text style={styles.primaryButtonText}>Reset password</Text>
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
  safeArea: { flex: 1, backgroundColor: "#F8FAFC" },
  keyboardView: { flex: 1 },
  scrollContent: {
    flexGrow: 1,
    justifyContent: "center",
    paddingHorizontal: 24,
    paddingVertical: 28,
  },
  shell: { width: "100%", maxWidth: 420, alignSelf: "center" },
  backButton: { alignSelf: "flex-start", marginBottom: 24, paddingVertical: 8 },
  backButtonText: { color: "#047857", fontSize: 15, fontWeight: "600" },
  logo: { width: 150, height: 50, marginBottom: 26 },
  heading: { color: "#0F172A", fontSize: 25, fontWeight: "700" },
  subheading: {
    color: "#64748B",
    fontSize: 15,
    lineHeight: 22,
    marginTop: 8,
    marginBottom: 24,
  },
  card: {
    borderRadius: 20,
    backgroundColor: "#FFFFFF",
    borderWidth: 1,
    borderColor: "#E2E8F0",
    padding: 20,
    shadowColor: "#0F172A",
    shadowOffset: { width: 0, height: 12 },
    shadowOpacity: 0.06,
    shadowRadius: 22,
    elevation: 3,
  },
  label: {
    marginBottom: 8,
    color: "#334155",
    fontSize: 14,
    fontWeight: "600",
  },
  passwordLabel: { marginTop: 18 },
  input: {
    minHeight: 52,
    borderWidth: 1,
    borderColor: "#CBD5E1",
    borderRadius: 12,
    backgroundColor: "#FFFFFF",
    paddingHorizontal: 14,
    color: "#0F172A",
    fontSize: 15,
  },
  otpInput: {
    fontSize: 22,
    fontWeight: "700",
    letterSpacing: 8,
    textAlign: "center",
  },
  errorBox: {
    marginTop: 14,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: "#FECACA",
    backgroundColor: "#FEF2F2",
    padding: 12,
  },
  errorText: { color: "#B91C1C", fontSize: 13, lineHeight: 19 },
  primaryButton: {
    minHeight: 52,
    marginTop: 20,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 12,
    backgroundColor: "#059669",
  },
  disabledButton: { opacity: 0.65 },
  primaryButtonText: { color: "#FFFFFF", fontSize: 15, fontWeight: "700" },
});

export default ResetPasswordScreen;
