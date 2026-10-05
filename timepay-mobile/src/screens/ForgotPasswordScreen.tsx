import React, { useState } from "react";
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
import TimePayLogo from "../components/TimePayLogo";
import { apiService, getApiErrorMessage } from "../services/api";

interface ForgotPasswordScreenProps {
  email: string;
  onEmailChange: (email: string) => void;
  onBack: () => void;
  onCodeSent: (email: string) => void;
}

const ForgotPasswordScreen: React.FC<ForgotPasswordScreenProps> = ({
  email,
  onEmailChange,
  onBack,
  onCodeSent,
}) => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async () => {
    const normalizedEmail = email.trim();

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalizedEmail)) {
      setError("Enter a valid email address.");
      return;
    }

    setLoading(true);
    setError(null);

    try {
      await apiService.requestPasswordResetOtp({ email: normalizedEmail });
      onEmailChange(normalizedEmail);
      onCodeSent(normalizedEmail);
    } catch (requestError) {
      setError(
        getApiErrorMessage(
          requestError,
          "We could not request a reset code. Please try again."
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
              accessibilityLabel="Back to sign in"
              disabled={loading}
              onPress={onBack}
              style={styles.backButton}
            >
              <Text style={styles.backButtonText}>← Back to sign in</Text>
            </TouchableOpacity>

            <TimePayLogo style={styles.logo} />
            <Text style={styles.heading}>Forgot password?</Text>
            <Text style={styles.subheading}>
              Enter your registered email and we’ll send a six-digit reset code
              if an account matches.
            </Text>

            <View style={styles.card}>
              <Text style={styles.label}>Email address</Text>
              <TextInput
                accessibilityLabel="Email address"
                value={email}
                onChangeText={(value) => {
                  onEmailChange(value);
                  setError(null);
                }}
                placeholder="you@company.com"
                placeholderTextColor="#94A3B8"
                autoCapitalize="none"
                autoComplete="email"
                autoCorrect={false}
                editable={!loading}
                keyboardType="email-address"
                returnKeyType="send"
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
                  <Text style={styles.primaryButtonText}>Send reset code</Text>
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

export default ForgotPasswordScreen;
