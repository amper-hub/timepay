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

const ProfileManagementScreen = ({ userSessionData, onLogout }) => {
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

  useEffect(() => {
    setName(userSessionData?.user?.name ?? "");
  }, [userSessionData?.user?.name]);

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
            <Text style={styles.eyebrow}>Profile</Text>
            <Text style={styles.title}>{name || "Employee"}</Text>
            <Text style={styles.subtitle}>
              Keep your account details current and manage your facial recognition baseline.
            </Text>
          </View>

          <View style={styles.infoCard}>
            <Text style={styles.cardTitle}>Account</Text>
            <Text style={styles.label}>Email</Text>
            <Text style={styles.value}>{userSessionData?.user?.email ?? "N/A"}</Text>
            <Text style={styles.label}>Company</Text>
            <Text style={styles.value}>{userSessionData?.company?.name ?? "N/A"}</Text>
          </View>

          <View style={styles.card}>
            <Text style={styles.cardTitle}>Edit Name</Text>
            <TextInput
              value={name}
              onChangeText={setName}
              placeholder="Full name"
              placeholderTextColor="#94a3b8"
              style={styles.input}
            />
            <TouchableOpacity
              disabled={savingName}
              onPress={handleSaveName}
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
            <TextInput
              value={passwordForm.current_password}
              onChangeText={(value) => updatePasswordField("current_password", value)}
              placeholder="Current Password"
              placeholderTextColor="#94a3b8"
              secureTextEntry
              style={styles.input}
            />
            <TextInput
              value={passwordForm.password}
              onChangeText={(value) => updatePasswordField("password", value)}
              placeholder="New Password"
              placeholderTextColor="#94a3b8"
              secureTextEntry
              style={styles.input}
            />
            <TextInput
              value={passwordForm.password_confirmation}
              onChangeText={(value) =>
                updatePasswordField("password_confirmation", value)
              }
              placeholder="Confirm Password"
              placeholderTextColor="#94a3b8"
              secureTextEntry
              style={styles.input}
            />
            <TouchableOpacity
              disabled={updatingPassword}
              onPress={handleUpdatePassword}
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
              style={styles.faceButton}
            >
              <Text style={styles.faceButtonText}>Update Facial Recognition</Text>
              <Text style={styles.faceButtonSubtext}>Re-enroll Face ID</Text>
            </TouchableOpacity>
          </View>

          <TouchableOpacity
            activeOpacity={0.88}
            onPress={onLogout}
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
    paddingTop: 22,
    paddingBottom: 32,
  },
  hero: {
    marginBottom: 18,
  },
  eyebrow: {
    color: "#4f46e5",
    fontSize: 12,
    fontWeight: "900",
    letterSpacing: 0.8,
    textTransform: "uppercase",
  },
  title: {
    marginTop: 10,
    color: "#0f172a",
    fontSize: 31,
    fontWeight: "900",
  },
  subtitle: {
    marginTop: 10,
    color: "#64748b",
    fontSize: 15,
    lineHeight: 22,
  },
  infoCard: {
    borderRadius: 22,
    borderWidth: 1,
    borderColor: "#e2e8f0",
    backgroundColor: "#ffffff",
    padding: 18,
    marginBottom: 14,
  },
  card: {
    borderRadius: 22,
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
  label: {
    color: "#64748b",
    fontSize: 12,
    fontWeight: "900",
    letterSpacing: 0.5,
    textTransform: "uppercase",
  },
  value: {
    marginTop: 5,
    marginBottom: 14,
    color: "#0f172a",
    fontSize: 15,
    fontWeight: "800",
  },
  input: {
    minHeight: 52,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: "#cbd5e1",
    backgroundColor: "#f8fafc",
    paddingHorizontal: 14,
    color: "#0f172a",
    fontSize: 15,
    fontWeight: "700",
    marginBottom: 12,
  },
  primaryButton: {
    minHeight: 52,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 14,
    backgroundColor: "#4f46e5",
  },
  primaryButtonText: {
    color: "#ffffff",
    fontSize: 15,
    fontWeight: "900",
  },
  faceCard: {
    borderRadius: 24,
    backgroundColor: "#0f172a",
    padding: 20,
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
    borderRadius: 16,
    backgroundColor: "#ffffff",
    padding: 16,
  },
  faceButtonText: {
    color: "#0f172a",
    fontSize: 16,
    fontWeight: "900",
  },
  faceButtonSubtext: {
    marginTop: 4,
    color: "#64748b",
    fontSize: 12,
    fontWeight: "800",
  },
  logoutButton: {
    minHeight: 52,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 14,
    backgroundColor: "#ef4444",
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
    borderRadius: 24,
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
