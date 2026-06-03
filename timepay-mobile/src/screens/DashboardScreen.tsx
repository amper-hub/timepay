/**
 * TimePay Dashboard Screen
 * Employee homepage with company info, status, and clock-in functionality
 */

import React, { useCallback } from "react";
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  ScrollView,
  SafeAreaView,
  Alert,
} from "react-native";
import { UserSession } from "../types";

interface DashboardScreenProps {
  userSessionData: UserSession | null;
  onLogout: () => void;
}

const DashboardScreen: React.FC<DashboardScreenProps> = ({
  userSessionData,
  onLogout,
}) => {
  /**
   * Handle attendance clock in button press
   * Placeholder for camera and GPS integration
   */
  const handleClockIn = useCallback(() => {
    Alert.alert(
      "Clock In",
      "This will trigger camera and GPS capture in the next phase.\n\nFor now, this is a placeholder.",
      [{ text: "OK", onPress: () => {} }]
    );
  }, []);

  /**
   * Handle logout
   */
  const handleLogout = useCallback(() => {
    Alert.alert(
      "Logout",
      "Are you sure you want to log out?",
      [
        {
          text: "Cancel",
          onPress: () => {},
          style: "cancel",
        },
        {
          text: "Logout",
          onPress: () => onLogout(),
          style: "destructive",
        },
      ]
    );
  }, [onLogout]);

  if (!userSessionData) {
    return (
      <SafeAreaView style={styles.container}>
        <Text style={styles.errorText}>No session data available</Text>
      </SafeAreaView>
    );
  }

  const { user, company, pay_metric, geofence_radius_meters } = userSessionData;

  return (
    <SafeAreaView style={styles.container}>
      {/* Header with Logout */}
      <View style={styles.headerBar}>
        <Text style={styles.headerTitle}>TimePay</Text>
        <TouchableOpacity
          style={styles.logoutButton}
          onPress={handleLogout}
          activeOpacity={0.7}
        >
          <Text style={styles.logoutButtonText}>Logout</Text>
        </TouchableOpacity>
      </View>

      <ScrollView
        style={styles.scrollView}
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        {/* Welcome Banner */}
        <View style={styles.welcomeBanner}>
          <Text style={styles.welcomeText}>
            Welcome back,{"\n"}
            <Text style={styles.welcomeName}>{user.name}</Text>
          </Text>
          <View style={styles.welcomeUnderline} />
        </View>

        {/* Company Tenant Header */}
        <View style={styles.companyCard}>
          <View style={styles.companyBadge}>
            <Text style={styles.companyBadgeText}>Company</Text>
          </View>
          <Text style={styles.companyName}>{company.name}</Text>
          {company.company_code && (
            <Text style={styles.companyCode}>ID: {company.company_code}</Text>
          )}
          {company.address && (
            <Text style={styles.companyAddress}>{company.address}</Text>
          )}
        </View>

        {/* Status Panels */}
        <View style={styles.statusGrid}>
          {/* Pay Metric Panel */}
          <View style={[styles.statusPanel, styles.panelPrimary]}>
            <Text style={styles.statusLabel}>Pay Metric</Text>
            <Text style={styles.statusValue}>
              {pay_metric || "N/A"}
            </Text>
          </View>

          {/* Geofence Radius Panel */}
          <View style={[styles.statusPanel, styles.panelSecondary]}>
            <Text style={styles.statusLabel}>Geofence Radius</Text>
            <Text style={styles.statusValue}>
              {geofence_radius_meters
                ? `${geofence_radius_meters}m`
                : "N/A"}
            </Text>
          </View>
        </View>

        {/* Clock In Button */}
        <View style={styles.clockInContainer}>
          <TouchableOpacity
            style={styles.clockInButton}
            onPress={handleClockIn}
            activeOpacity={0.8}
          >
            <Text style={styles.clockInButtonText}>Attendance Clock In</Text>
            <Text style={styles.clockInButtonSubtext}>
              Capture location & photo
            </Text>
          </TouchableOpacity>
        </View>

        {/* Quick Info Section */}
        <View style={styles.quickInfoSection}>
          <View style={styles.infoCard}>
            <Text style={styles.infoTitle}>Employee ID</Text>
            <Text style={styles.infoValue}>{user.id}</Text>
          </View>

          <View style={styles.infoCard}>
            <Text style={styles.infoTitle}>Email</Text>
            <Text style={styles.infoValue}>{user.email}</Text>
          </View>

          {user.phone && (
            <View style={styles.infoCard}>
              <Text style={styles.infoTitle}>Phone</Text>
              <Text style={styles.infoValue}>{user.phone}</Text>
            </View>
          )}
        </View>
      </ScrollView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#f8f9fa",
  },
  headerBar: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingHorizontal: 20,
    paddingVertical: 12,
    backgroundColor: "#fff",
    borderBottomWidth: 1,
    borderBottomColor: "#eee",
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: "700",
    color: "#1a1a1a",
  },
  logoutButton: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 4,
    backgroundColor: "#fee",
  },
  logoutButtonText: {
    color: "#c33",
    fontSize: 12,
    fontWeight: "600",
  },
  scrollView: {
    flex: 1,
  },
  scrollContent: {
    paddingHorizontal: 16,
    paddingTop: 24,
    paddingBottom: 32,
  },
  welcomeBanner: {
    marginBottom: 32,
  },
  welcomeText: {
    fontSize: 28,
    fontWeight: "700",
    color: "#1a1a1a",
    lineHeight: 36,
  },
  welcomeName: {
    color: "#007AFF",
    fontWeight: "800",
  },
  welcomeUnderline: {
    width: 60,
    height: 4,
    backgroundColor: "#007AFF",
    borderRadius: 2,
    marginTop: 8,
  },
  companyCard: {
    backgroundColor: "#fff",
    borderRadius: 8,
    padding: 16,
    marginBottom: 24,
    borderLeftWidth: 4,
    borderLeftColor: "#007AFF",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  companyBadge: {
    backgroundColor: "#e3f2fd",
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 4,
    alignSelf: "flex-start",
    marginBottom: 8,
  },
  companyBadgeText: {
    color: "#007AFF",
    fontSize: 11,
    fontWeight: "600",
    textTransform: "uppercase",
  },
  companyName: {
    fontSize: 18,
    fontWeight: "700",
    color: "#1a1a1a",
    marginBottom: 4,
  },
  companyCode: {
    fontSize: 12,
    color: "#666",
    marginBottom: 8,
    fontWeight: "500",
  },
  companyAddress: {
    fontSize: 12,
    color: "#999",
    fontWeight: "400",
    lineHeight: 18,
  },
  statusGrid: {
    flexDirection: "row",
    gap: 12,
    marginBottom: 28,
  },
  statusPanel: {
    flex: 1,
    borderRadius: 8,
    padding: 16,
    alignItems: "center",
    justifyContent: "center",
    minHeight: 120,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  panelPrimary: {
    backgroundColor: "#007AFF",
  },
  panelSecondary: {
    backgroundColor: "#34C759",
  },
  statusLabel: {
    fontSize: 12,
    fontWeight: "600",
    color: "#fff",
    opacity: 0.9,
    marginBottom: 8,
    textTransform: "uppercase",
    letterSpacing: 0.5,
  },
  statusValue: {
    fontSize: 20,
    fontWeight: "700",
    color: "#fff",
    textAlign: "center",
  },
  clockInContainer: {
    marginBottom: 28,
  },
  clockInButton: {
    backgroundColor: "#fff",
    borderRadius: 8,
    paddingVertical: 28,
    paddingHorizontal: 20,
    alignItems: "center",
    borderWidth: 2,
    borderColor: "#007AFF",
    shadowColor: "#007AFF",
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.15,
    shadowRadius: 8,
    elevation: 4,
  },
  clockInButtonText: {
    fontSize: 18,
    fontWeight: "700",
    color: "#007AFF",
    marginBottom: 4,
  },
  clockInButtonSubtext: {
    fontSize: 12,
    color: "#666",
    fontWeight: "400",
  },
  quickInfoSection: {
    gap: 12,
  },
  infoCard: {
    backgroundColor: "#fff",
    borderRadius: 8,
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderLeftWidth: 3,
    borderLeftColor: "#ddd",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.04,
    shadowRadius: 2,
    elevation: 1,
  },
  infoTitle: {
    fontSize: 11,
    fontWeight: "600",
    color: "#999",
    textTransform: "uppercase",
    letterSpacing: 0.5,
    marginBottom: 4,
  },
  infoValue: {
    fontSize: 15,
    fontWeight: "600",
    color: "#1a1a1a",
  },
  errorText: {
    fontSize: 16,
    color: "#c33",
    textAlign: "center",
    marginTop: 20,
  },
});

export default DashboardScreen;
