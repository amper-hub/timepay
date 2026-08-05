import React from "react";
import { SafeAreaView, StyleSheet, Text, View } from "react-native";
import { UserSession } from "../types";

interface LeaveScreenProps {
  userSessionData: UserSession | null;
}

const LeaveScreen: React.FC<LeaveScreenProps> = ({ userSessionData }) => {
  return (
    <SafeAreaView style={styles.safeArea}>
      <View style={styles.container}>
        <Text style={styles.eyebrow}>Leave</Text>
        <Text style={styles.title}>Leave Management</Text>
        <Text style={styles.subtitle}>
          Balances, request forms, and approval history will live here.
        </Text>
        <Text style={styles.meta}>
          Employee: {userSessionData?.user.name ?? "Not signed in"}
        </Text>
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
    color: "#4f46e5",
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
  meta: {
    marginTop: 18,
    color: "#334155",
    fontSize: 14,
    fontWeight: "700",
  },
});

export default LeaveScreen;
