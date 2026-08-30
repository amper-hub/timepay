import React from "react";
import {
  SafeAreaView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";
import { UserSession } from "../types";

interface ProfileScreenProps {
  userSessionData: UserSession | null;
  onLogout: () => void;
}

const ProfileScreen: React.FC<ProfileScreenProps> = ({
  userSessionData,
  onLogout,
}) => {
  return (
    <SafeAreaView style={styles.safeArea}>
      <View style={styles.container}>
        <Text style={styles.eyebrow}>Profile</Text>
        <Text style={styles.title}>{userSessionData?.user.name ?? "Employee"}</Text>
        <Text style={styles.subtitle}>
          Profile details and Cloud Face ID self-management will live here.
        </Text>

        <View style={styles.infoCard}>
          <Text style={styles.label}>Email</Text>
          <Text style={styles.value}>{userSessionData?.user.email ?? "N/A"}</Text>
        </View>

        <TouchableOpacity
          activeOpacity={0.88}
          onPress={onLogout}
          style={styles.logoutButton}
        >
          <Text style={styles.logoutText}>Log Out</Text>
        </TouchableOpacity>
      </View>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: "#f8fafc",
  },
  container: {
    flex: 1,
    padding: 24,
    justifyContent: "center",
  },
  eyebrow: {
    color: "#059669",
    fontSize: 12,
    fontWeight: "900",
    letterSpacing: 0.6,
    textTransform: "uppercase",
  },
  title: {
    marginTop: 8,
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
  infoCard: {
    marginTop: 22,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: "#e2e8f0",
    backgroundColor: "#ffffff",
    padding: 16,
  },
  label: {
    color: "#64748b",
    fontSize: 12,
    fontWeight: "900",
    letterSpacing: 0.5,
    textTransform: "uppercase",
  },
  value: {
    marginTop: 6,
    color: "#0f172a",
    fontSize: 16,
    fontWeight: "800",
  },
  logoutButton: {
    marginTop: 18,
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
});

export default ProfileScreen;
