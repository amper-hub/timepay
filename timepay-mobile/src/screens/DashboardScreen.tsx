/**
 * TimePay Dashboard Screen
 * Employee homepage with company info, attendance clock-in/out with biometric face capture and GPS geofencing
 */

import React, { useCallback, useRef, useState, useEffect } from "react";
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  ScrollView,
  SafeAreaView,
  Alert,
  ActivityIndicator,
  Modal,
} from "react-native";
import { CameraView, useCameraPermissions } from "expo-camera";
import * as Location from "expo-location";
import { UserSession, AttendancePunchResponse } from "../types";
import { apiService } from "../services/api";

interface DashboardScreenProps {
  userSessionData: UserSession | null;
  onLogout: () => void;
}

type PunchType = "clock_in" | "clock_out";

interface CameraState {
  isOpen: boolean;
  punchType: PunchType | null;
  isLoading: boolean;
}

const DashboardScreen: React.FC<DashboardScreenProps> = ({
  userSessionData,
  onLogout,
}) => {
  // Camera reference
  const cameraRef = useRef<any>(null);

  // Camera state
  const [cameraState, setCameraState] = useState<CameraState>({
    isOpen: false,
    punchType: null,
    isLoading: false,
  });

  // Camera and Location permissions
  const [cameraPermission, requestCameraPermission] = useCameraPermissions();
  const [locationPermission, setLocationPermission] = useState<boolean | null>(
    null
  );

  // Request location permission
  const requestLocationPermission = useCallback(async (): Promise<boolean> => {
    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      const isGranted = status === "granted";
      setLocationPermission(isGranted);
      return isGranted;
    } catch (error) {
      console.error("Location permission error:", error);
      return false;
    }
  }, []);

  // Request permissions on mount
  useEffect(() => {
    const initializePermissions = async () => {
      // Request camera permission if not already determined
      if (!cameraPermission) {
        await requestCameraPermission();
      }

      // Request location permission if not already determined
      if (locationPermission === null) {
        await requestLocationPermission();
      }
    };

    initializePermissions();
  }, [requestCameraPermission, requestLocationPermission, cameraPermission, locationPermission]);

  /**
   * Capture photo from camera and submit attendance punch
   */
  const handleCaptureFace = useCallback(async () => {
    if (!cameraRef.current || !cameraState.punchType) {
      Alert.alert("Error", "Camera is not ready");
      return;
    }

    try {
      setCameraState((prev) => ({ ...prev, isLoading: true }));

      console.log("[Dashboard] Capturing photo and GPS coordinates...");

      // Capture photo from camera
      const photo = await cameraRef.current.takePictureAsync({
        quality: 0.8,
        base64: false,
      });

      console.log("[Dashboard] Photo captured:", photo.uri);

      // Get high-accuracy GPS coordinates
      console.log("[Dashboard] Getting GPS coordinates...");
      const location = await Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.High,
      });

      const { latitude, longitude } = location.coords;
      console.log("[Dashboard] GPS coordinates obtained:", {
        latitude,
        longitude,
      });

      // Submit attendance punch to API
      console.log("[Dashboard] Submitting attendance punch...");
      const response = await apiService.submitAttendancePunch(
        cameraState.punchType,
        latitude,
        longitude,
        photo.uri
      );

      console.log("[Dashboard] Attendance punch response:", response);

      // Close camera and show result alert
      setCameraState({
        isOpen: false,
        punchType: null,
        isLoading: false,
      });

      // Show success/rejection alert
      const statusLabel = response.attendance_log.status === "verified" ? "✓ Verified" : "✗ Rejected";
      const distanceText = `Distance: ${response.geofence_info.distance_from_office_meters.toFixed(2)}m (Radius: ${response.geofence_info.geofence_radius_meters}m)`;

      Alert.alert(
        statusLabel,
        `${response.message}\n\n${distanceText}`,
        [{ text: "OK", onPress: () => {} }]
      );
    } catch (error) {
      console.error("[Dashboard] Error during capture/submission:", error);
      setCameraState((prev) => ({ ...prev, isLoading: false }));

      const errorMessage =
        error instanceof Error ? error.message : "Unknown error occurred";
      Alert.alert(
        "Attendance Submission Failed",
        `${errorMessage}\n\nPlease try again.`,
        [{ text: "OK", onPress: () => {} }]
      );
    }
  }, [cameraState.punchType]);

  /**
   * Handle clock in button press
   * Verifies permissions and opens camera
   */
  const handleClockIn = useCallback(async () => {
    // Check camera permission
    if (cameraPermission?.granted === false) {
      Alert.alert(
        "Camera Permission Required",
        "Please enable camera access in settings to use clock in",
        [
          { text: "Cancel", style: "cancel" },
          { text: "Request", onPress: () => requestCameraPermission() },
        ]
      );
      return;
    }

    // Check location permission
    if (locationPermission === false) {
      Alert.alert(
        "Location Permission Required",
        "Please enable location access in settings to use clock in",
        [
          { text: "Cancel", style: "cancel" },
          { text: "Request", onPress: () => requestLocationPermission() },
        ]
      );
      return;
    }

    // Open camera
    setCameraState({
      isOpen: true,
      punchType: "clock_in",
      isLoading: false,
    });
  }, [cameraPermission, locationPermission, requestCameraPermission, requestLocationPermission]);

  /**
   * Handle clock out button press
   * Verifies permissions and opens camera
   */
  const handleClockOut = useCallback(async () => {
    // Check camera permission
    if (cameraPermission?.granted === false) {
      Alert.alert(
        "Camera Permission Required",
        "Please enable camera access in settings to use clock out",
        [
          { text: "Cancel", style: "cancel" },
          { text: "Request", onPress: () => requestCameraPermission() },
        ]
      );
      return;
    }

    // Check location permission
    if (locationPermission === false) {
      Alert.alert(
        "Location Permission Required",
        "Please enable location access in settings to use clock out",
        [
          { text: "Cancel", style: "cancel" },
          { text: "Request", onPress: () => requestLocationPermission() },
        ]
      );
      return;
    }

    // Open camera
    setCameraState({
      isOpen: true,
      punchType: "clock_out",
      isLoading: false,
    });
  }, [cameraPermission, locationPermission, requestCameraPermission, requestLocationPermission]);

  /**
   * Close camera without capturing
   */
  const handleCloseCamera = useCallback(() => {
    setCameraState({
      isOpen: false,
      punchType: null,
      isLoading: false,
    });
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
  const isCameraReady = cameraPermission?.granted && locationPermission;

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

      {/* Camera Modal */}
      <Modal
        visible={cameraState.isOpen}
        animationType="slide"
        transparent={false}
        onRequestClose={handleCloseCamera}
      >
        <SafeAreaView style={styles.cameraModalContainer}>
          {/* Camera View */}
          {isCameraReady ? (
            <>
              <CameraView
                ref={cameraRef}
                style={styles.camera}
                facing="front"
              />

              {/* Camera Overlay with Controls */}
              <View style={styles.cameraOverlay}>
                {/* Top Bar - Close Button */}
                <View style={styles.cameraTopBar}>
                  <TouchableOpacity
                    style={styles.closeCameraButton}
                    onPress={handleCloseCamera}
                    disabled={cameraState.isLoading}
                  >
                    <Text style={styles.closeCameraButtonText}>✕</Text>
                  </TouchableOpacity>
                  <Text style={styles.cameraTitleText}>
                    {cameraState.punchType === "clock_in"
                      ? "Clock In"
                      : "Clock Out"}
                  </Text>
                  <View style={{ width: 44 }} />
                </View>

                {/* Bottom Bar - Capture Button */}
                <View style={styles.cameraBottomBar}>
                  <TouchableOpacity
                    style={[
                      styles.captureButton,
                      cameraState.isLoading && styles.captureButtonDisabled,
                    ]}
                    onPress={handleCaptureFace}
                    disabled={cameraState.isLoading}
                    activeOpacity={0.7}
                  >
                    {cameraState.isLoading ? (
                      <ActivityIndicator color="#fff" size="large" />
                    ) : (
                      <>
                        <Text style={styles.captureButtonText}>
                          Capture Face
                        </Text>
                        <Text style={styles.captureButtonSubtext}>
                          Take selfie
                        </Text>
                      </>
                    )}
                  </TouchableOpacity>
                </View>

                {/* Loading Spinner Overlay */}
                {cameraState.isLoading && (
                  <View style={styles.loadingOverlay}>
                    <ActivityIndicator color="#007AFF" size="large" />
                    <Text style={styles.loadingText}>
                      Processing...
                    </Text>
                  </View>
                )}
              </View>
            </>
          ) : (
            <View style={styles.cameraPermissionError}>
              <Text style={styles.permissionErrorText}>
                Camera or Location permission not granted
              </Text>
              <TouchableOpacity
                style={styles.permissionRequestButton}
                onPress={handleCloseCamera}
              >
                <Text style={styles.permissionRequestButtonText}>Close</Text>
              </TouchableOpacity>
            </View>
          )}
        </SafeAreaView>
      </Modal>

      {/* Dashboard Content */}
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

        {/* Clock In/Out Buttons */}
        <View style={styles.clockButtonsContainer}>
          {/* Clock In Button */}
          <TouchableOpacity
            style={[styles.clockButton, styles.clockInButtonStyle]}
            onPress={handleClockIn}
            activeOpacity={0.8}
            disabled={!isCameraReady}
          >
            <Text style={styles.clockButtonText}>Clock In</Text>
            <Text style={styles.clockButtonSubtext}>
              Start your shift
            </Text>
          </TouchableOpacity>

          {/* Clock Out Button */}
          <TouchableOpacity
            style={[styles.clockButton, styles.clockOutButtonStyle]}
            onPress={handleClockOut}
            activeOpacity={0.8}
            disabled={!isCameraReady}
          >
            <Text style={styles.clockButtonText}>Clock Out</Text>
            <Text style={styles.clockButtonSubtext}>
              End your shift
            </Text>
          </TouchableOpacity>
        </View>

        {/* Permission Status */}
        {!isCameraReady && (
          <View style={styles.permissionWarningBox}>
            <Text style={styles.permissionWarningTitle}>
              ⚠ Permissions Required
            </Text>
            <Text style={styles.permissionWarningText}>
              Camera and Location permissions are required for attendance
              tracking.
            </Text>
            <TouchableOpacity
              style={styles.permissionWarningButton}
              onPress={async () => {
                await requestCameraPermission();
                await requestLocationPermission();
              }}
            >
              <Text style={styles.permissionWarningButtonText}>
                Enable Permissions
              </Text>
            </TouchableOpacity>
          </View>
        )}

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

  // Camera Modal Styles
  cameraModalContainer: {
    flex: 1,
    backgroundColor: "#000",
  },
  camera: {
    flex: 1,
  },
  cameraOverlay: {
    position: "absolute",
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    justifyContent: "space-between",
  },
  cameraTopBar: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: "rgba(0, 0, 0, 0.5)",
  },
  closeCameraButton: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: "rgba(0, 0, 0, 0.6)",
    justifyContent: "center",
    alignItems: "center",
  },
  closeCameraButtonText: {
    fontSize: 24,
    color: "#fff",
    fontWeight: "700",
  },
  cameraTitleText: {
    fontSize: 18,
    fontWeight: "700",
    color: "#fff",
  },
  cameraBottomBar: {
    paddingHorizontal: 16,
    paddingBottom: 32,
    paddingTop: 16,
    backgroundColor: "rgba(0, 0, 0, 0.5)",
    alignItems: "center",
  },
  captureButton: {
    backgroundColor: "#007AFF",
    borderRadius: 12,
    paddingVertical: 20,
    paddingHorizontal: 32,
    minWidth: 200,
    alignItems: "center",
    justifyContent: "center",
    shadowColor: "#007AFF",
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 8,
  },
  captureButtonDisabled: {
    opacity: 0.6,
  },
  captureButtonText: {
    fontSize: 16,
    fontWeight: "700",
    color: "#fff",
  },
  captureButtonSubtext: {
    fontSize: 12,
    color: "rgba(255, 255, 255, 0.8)",
    marginTop: 4,
  },
  loadingOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: "rgba(0, 0, 0, 0.7)",
    justifyContent: "center",
    alignItems: "center",
  },
  loadingText: {
    color: "#fff",
    fontSize: 14,
    fontWeight: "600",
    marginTop: 12,
  },
  cameraPermissionError: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
    paddingHorizontal: 24,
  },
  permissionErrorText: {
    fontSize: 16,
    color: "#fff",
    textAlign: "center",
    marginBottom: 20,
  },
  permissionRequestButton: {
    backgroundColor: "#007AFF",
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 8,
  },
  permissionRequestButtonText: {
    color: "#fff",
    fontSize: 14,
    fontWeight: "600",
  },

  // Dashboard Styles
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
  clockButtonsContainer: {
    flexDirection: "row",
    gap: 12,
    marginBottom: 28,
  },
  clockButton: {
    flex: 1,
    borderRadius: 8,
    paddingVertical: 20,
    paddingHorizontal: 12,
    alignItems: "center",
    justifyContent: "center",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.08,
    shadowRadius: 4,
    elevation: 3,
  },
  clockInButtonStyle: {
    backgroundColor: "#007AFF",
  },
  clockOutButtonStyle: {
    backgroundColor: "#FF3B30",
  },
  clockButtonText: {
    fontSize: 16,
    fontWeight: "700",
    color: "#fff",
  },
  clockButtonSubtext: {
    fontSize: 11,
    color: "rgba(255, 255, 255, 0.8)",
    marginTop: 4,
    fontWeight: "500",
  },
  permissionWarningBox: {
    backgroundColor: "#FFF3CD",
    borderRadius: 8,
    paddingHorizontal: 16,
    paddingVertical: 12,
    marginBottom: 24,
    borderLeftWidth: 4,
    borderLeftColor: "#FFC107",
  },
  permissionWarningTitle: {
    fontSize: 14,
    fontWeight: "700",
    color: "#856404",
    marginBottom: 4,
  },
  permissionWarningText: {
    fontSize: 12,
    color: "#856404",
    marginBottom: 12,
    lineHeight: 18,
  },
  permissionWarningButton: {
    backgroundColor: "#FFC107",
    borderRadius: 6,
    paddingHorizontal: 12,
    paddingVertical: 6,
    alignSelf: "flex-start",
  },
  permissionWarningButtonText: {
    fontSize: 12,
    fontWeight: "600",
    color: "#fff",
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
