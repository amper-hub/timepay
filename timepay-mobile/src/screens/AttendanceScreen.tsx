/**
 * TimePay Attendance Screen
 * Expo Camera + Location punch flow with Sanctum-authenticated API submission.
 */

import React, { useCallback, useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Linking,
  Modal,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";
import { CameraView, useCameraPermissions } from "expo-camera";
import * as Location from "expo-location";
import {
  AttendancePunchResponse,
  AttendancePunchType,
  AttendanceState,
  UserSession,
} from "../types";
import { apiService, getApiErrorMessage } from "../services/api";

interface AttendanceScreenProps {
  userSessionData: UserSession | null;
  onLogout: () => void;
}

type PermissionState = "checking" | "granted" | "denied";

const AttendanceScreen: React.FC<AttendanceScreenProps> = ({
  userSessionData,
  onLogout,
}) => {
  const cameraRef = useRef<CameraView | null>(null);
  const [cameraPermission, requestCameraPermission] = useCameraPermissions();

  const [locationPermission, setLocationPermission] =
    useState<PermissionState>("checking");
  const [attendanceStatus, setAttendanceStatus] =
    useState<AttendanceState | null>(null);
  const [statusLoading, setStatusLoading] = useState(true);
  const [cameraOpen, setCameraOpen] = useState(false);
  const [processing, setProcessing] = useState(false);
  const [screenError, setScreenError] = useState<string | null>(null);

  const hasCameraPermission = cameraPermission?.granted === true;
  const hasLocationPermission = locationPermission === "granted";
  const permissionsReady = hasCameraPermission && hasLocationPermission;
  const punchType: AttendancePunchType =
    attendanceStatus === "clocked_in" ? "clock_out" : "clock_in";
  const isClockOut = punchType === "clock_out";

  const requestPermissions = useCallback(async () => {
    setScreenError(null);

    const cameraResult = await requestCameraPermission();
    const locationResult = await Location.requestForegroundPermissionsAsync();

    setLocationPermission(
      locationResult.status === Location.PermissionStatus.GRANTED
        ? "granted"
        : "denied"
    );

    if (!cameraResult.granted || locationResult.status !== "granted") {
      setScreenError(
        "Camera and location permissions are required to verify attendance."
      );
    }
  }, [requestCameraPermission]);

  const loadAttendanceStatus = useCallback(async () => {
    setStatusLoading(true);
    setScreenError(null);

    try {
      const status = await apiService.getAttendanceStatus();
      setAttendanceStatus(status.current_state);
    } catch (error) {
      setScreenError(
        getApiErrorMessage(
          error,
          "Unable to load your attendance status. Please try again."
        )
      );
    } finally {
      setStatusLoading(false);
    }
  }, []);

  useEffect(() => {
    requestPermissions();
  }, [requestPermissions]);

  useEffect(() => {
    loadAttendanceStatus();
  }, [loadAttendanceStatus]);

  const openCamera = useCallback(() => {
    setScreenError(null);

    if (!permissionsReady) {
      setScreenError(
        "Camera and location access must be enabled before you can punch in or out."
      );
      return;
    }

    setCameraOpen(true);
  }, [permissionsReady]);

  const closeCamera = useCallback(() => {
    if (!processing) {
      setCameraOpen(false);
    }
  }, [processing]);

  const showPunchResult = useCallback(
    (response: AttendancePunchResponse) => {
      const typeLabel = response.attendance_log.type === "clock_in"
        ? "Clock In"
        : "Clock Out";
      const verified = response.attendance_log.status === "verified";
      const title = verified
        ? `${typeLabel} successful!`
        : "Punch recorded but flagged";

      Alert.alert(title, response.message, [{ text: "OK" }]);
    },
    []
  );

  const captureAndSubmit = useCallback(async () => {
    if (!cameraRef.current || processing) {
      return;
    }

    setProcessing(true);
    setScreenError(null);

    try {
      const photo = await cameraRef.current.takePictureAsync({
        quality: 0.85,
        skipProcessing: false,
      });

      if (!photo?.uri) {
        throw new Error("Unable to capture selfie. Please try again.");
      }

      const location = await Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.High,
      });

      const response = await apiService.submitAttendancePunch(
        punchType,
        location.coords.latitude,
        location.coords.longitude,
        photo.uri
      );

      const nextState =
        response.current_state ??
        (response.attendance_log.type === "clock_in"
          ? "clocked_in"
          : "clocked_out");

      setAttendanceStatus(nextState);
      setCameraOpen(false);
      showPunchResult(response);
    } catch (error) {
      setCameraOpen(false);
      setScreenError(
        getApiErrorMessage(
          error,
          "Unable to submit your punch. Please try again."
        )
      );
    } finally {
      setProcessing(false);
    }
  }, [processing, punchType, showPunchResult]);

  if (!userSessionData) {
    return (
      <SafeAreaView style={styles.safeArea}>
        <View style={styles.centeredState}>
          <Text style={styles.errorTitle}>Session unavailable</Text>
          <Text style={styles.mutedText}>Please sign in again.</Text>
        </View>
      </SafeAreaView>
    );
  }

  const buttonLabel = isClockOut ? "Proceed to Clock Out" : "Proceed to Clock In";
  const statusLabel =
    attendanceStatus === "clocked_in" ? "Clocked In" : "Clocked Out";

  return (
    <SafeAreaView style={styles.safeArea}>
      <ScrollView
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        <View style={styles.header}>
          <View>
            <Text style={styles.brand}>TimePay</Text>
            <Text style={styles.company}>{userSessionData.company.name}</Text>
          </View>
          <TouchableOpacity onPress={onLogout} style={styles.logoutButton}>
            <Text style={styles.logoutText}>Log Out</Text>
          </TouchableOpacity>
        </View>

        <View style={styles.hero}>
          <Text style={styles.eyebrow}>Attendance Verification</Text>
          <Text style={styles.title}>
            Hi, {userSessionData.user.name.split(" ")[0]}
          </Text>
          <Text style={styles.subtitle}>
            Capture a live selfie and GPS location to verify your next punch.
          </Text>
        </View>

        {screenError ? (
          <View style={styles.alert}>
            <Text style={styles.alertTitle}>Action needed</Text>
            <Text style={styles.alertText}>{screenError}</Text>
          </View>
        ) : null}

        {!permissionsReady ? (
          <View style={styles.permissionCard}>
            <Text style={styles.cardTitle}>Permissions required</Text>
            <Text style={styles.cardText}>
              Enable camera and location access so TimePay can verify your
              selfie and workspace coordinates.
            </Text>
            <View style={styles.permissionActions}>
              <TouchableOpacity
                onPress={requestPermissions}
                style={styles.secondaryButton}
              >
                <Text style={styles.secondaryButtonText}>Try Again</Text>
              </TouchableOpacity>
              <TouchableOpacity
                onPress={() => Linking.openSettings()}
                style={styles.primarySmallButton}
              >
                <Text style={styles.primarySmallButtonText}>Open Settings</Text>
              </TouchableOpacity>
            </View>
          </View>
        ) : (
          <View style={styles.statusCard}>
            <Text style={styles.statusCaption}>Current State</Text>
            {statusLoading ? (
              <View style={styles.statusLoadingRow}>
                <ActivityIndicator color="#4f46e5" />
                <Text style={styles.statusLoadingText}>Checking status...</Text>
              </View>
            ) : (
              <>
                <Text
                  style={[
                    styles.statusValue,
                    attendanceStatus === "clocked_in"
                      ? styles.clockedInText
                      : styles.clockedOutText,
                  ]}
                >
                  {statusLabel}
                </Text>
                <Text style={styles.cardText}>
                  Your next action is{" "}
                  {isClockOut ? "clocking out" : "clocking in"}.
                </Text>
              </>
            )}
          </View>
        )}

        <TouchableOpacity
          disabled={!permissionsReady || statusLoading}
          onPress={openCamera}
          activeOpacity={0.9}
          style={[
            styles.punchButton,
            isClockOut ? styles.clockOutButton : styles.clockInButton,
            (!permissionsReady || statusLoading) && styles.disabledButton,
          ]}
        >
          <Text style={styles.punchButtonText}>{buttonLabel}</Text>
          <Text style={styles.punchButtonSubtext}>
            Face++ identity check and GPS capture
          </Text>
        </TouchableOpacity>
      </ScrollView>

      <Modal
        visible={cameraOpen}
        animationType="slide"
        onRequestClose={closeCamera}
        presentationStyle="fullScreen"
      >
        <View style={styles.cameraContainer}>
          <CameraView ref={cameraRef} style={styles.camera} facing="front" />

          <View pointerEvents="none" style={styles.cameraScrim}>
            <View style={styles.faceGuide} />
            <Text style={styles.cameraInstruction}>
              Align your face inside the oval
            </Text>
          </View>

          <View style={styles.cameraTopBar}>
            <TouchableOpacity
              disabled={processing}
              onPress={closeCamera}
              style={styles.closeButton}
            >
              <Text style={styles.closeButtonText}>Close</Text>
            </TouchableOpacity>
            <Text style={styles.cameraTitle}>
              {isClockOut ? "Clock Out" : "Clock In"}
            </Text>
            <View style={styles.closeButtonSpacer} />
          </View>

          <View style={styles.cameraBottomBar}>
            <TouchableOpacity
              disabled={processing}
              onPress={captureAndSubmit}
              activeOpacity={0.85}
              style={[
                styles.shutterButton,
                processing && styles.disabledButton,
              ]}
            >
              <View style={styles.shutterInner} />
            </TouchableOpacity>
          </View>

          {processing ? (
            <View style={styles.processingOverlay}>
              <ActivityIndicator color="#ffffff" size="large" />
              <Text style={styles.processingTitle}>
                Verifying identity via Face++...
              </Text>
              <Text style={styles.processingText}>
                Keep the app open while we confirm your selfie and location.
              </Text>
            </View>
          ) : null}
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
  scrollContent: {
    flexGrow: 1,
    paddingHorizontal: 20,
    paddingVertical: 24,
  },
  header: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 28,
  },
  brand: {
    color: "#0f172a",
    fontSize: 24,
    fontWeight: "800",
  },
  company: {
    marginTop: 3,
    color: "#64748b",
    fontSize: 13,
    fontWeight: "600",
  },
  logoutButton: {
    borderRadius: 999,
    borderWidth: 1,
    borderColor: "#e2e8f0",
    backgroundColor: "#ffffff",
    paddingHorizontal: 14,
    paddingVertical: 9,
  },
  logoutText: {
    color: "#475569",
    fontSize: 13,
    fontWeight: "700",
  },
  hero: {
    marginBottom: 20,
  },
  eyebrow: {
    color: "#4f46e5",
    fontSize: 12,
    fontWeight: "800",
    letterSpacing: 0.7,
    textTransform: "uppercase",
  },
  title: {
    marginTop: 10,
    color: "#0f172a",
    fontSize: 32,
    fontWeight: "800",
  },
  subtitle: {
    marginTop: 10,
    color: "#64748b",
    fontSize: 15,
    lineHeight: 22,
  },
  alert: {
    marginBottom: 16,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: "#fecaca",
    backgroundColor: "#fef2f2",
    padding: 16,
  },
  alertTitle: {
    color: "#991b1b",
    fontSize: 14,
    fontWeight: "800",
  },
  alertText: {
    marginTop: 4,
    color: "#b91c1c",
    fontSize: 14,
    lineHeight: 20,
  },
  permissionCard: {
    borderRadius: 22,
    borderWidth: 1,
    borderColor: "#e2e8f0",
    backgroundColor: "#ffffff",
    padding: 18,
    marginBottom: 18,
  },
  statusCard: {
    borderRadius: 22,
    borderWidth: 1,
    borderColor: "#e2e8f0",
    backgroundColor: "#ffffff",
    padding: 20,
    marginBottom: 18,
    shadowColor: "#0f172a",
    shadowOffset: { width: 0, height: 14 },
    shadowOpacity: 0.07,
    shadowRadius: 20,
    elevation: 3,
  },
  cardTitle: {
    color: "#0f172a",
    fontSize: 18,
    fontWeight: "800",
  },
  cardText: {
    marginTop: 8,
    color: "#64748b",
    fontSize: 14,
    lineHeight: 20,
  },
  permissionActions: {
    flexDirection: "row",
    gap: 10,
    marginTop: 16,
  },
  secondaryButton: {
    flex: 1,
    alignItems: "center",
    borderRadius: 12,
    borderWidth: 1,
    borderColor: "#cbd5e1",
    paddingVertical: 12,
  },
  secondaryButtonText: {
    color: "#334155",
    fontSize: 14,
    fontWeight: "800",
  },
  primarySmallButton: {
    flex: 1,
    alignItems: "center",
    borderRadius: 12,
    backgroundColor: "#4f46e5",
    paddingVertical: 12,
  },
  primarySmallButtonText: {
    color: "#ffffff",
    fontSize: 14,
    fontWeight: "800",
  },
  statusCaption: {
    color: "#64748b",
    fontSize: 12,
    fontWeight: "800",
    letterSpacing: 0.6,
    textTransform: "uppercase",
  },
  statusLoadingRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    marginTop: 16,
  },
  statusLoadingText: {
    color: "#64748b",
    fontSize: 14,
    fontWeight: "600",
  },
  statusValue: {
    marginTop: 12,
    fontSize: 34,
    fontWeight: "900",
  },
  clockedInText: {
    color: "#dc2626",
  },
  clockedOutText: {
    color: "#059669",
  },
  punchButton: {
    minHeight: 76,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 20,
    paddingHorizontal: 18,
    shadowColor: "#0f172a",
    shadowOffset: { width: 0, height: 16 },
    shadowOpacity: 0.16,
    shadowRadius: 22,
    elevation: 5,
  },
  clockInButton: {
    backgroundColor: "#059669",
  },
  clockOutButton: {
    backgroundColor: "#dc2626",
  },
  disabledButton: {
    opacity: 0.62,
  },
  punchButtonText: {
    color: "#ffffff",
    fontSize: 18,
    fontWeight: "900",
  },
  punchButtonSubtext: {
    marginTop: 4,
    color: "rgba(255,255,255,0.78)",
    fontSize: 12,
    fontWeight: "700",
  },
  centeredState: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    padding: 24,
  },
  errorTitle: {
    color: "#991b1b",
    fontSize: 18,
    fontWeight: "800",
  },
  mutedText: {
    marginTop: 8,
    color: "#64748b",
    fontSize: 14,
  },
  cameraContainer: {
    flex: 1,
    backgroundColor: "#020617",
  },
  camera: {
    ...StyleSheet.absoluteFillObject,
  },
  cameraScrim: {
    ...StyleSheet.absoluteFillObject,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "rgba(2, 6, 23, 0.18)",
  },
  faceGuide: {
    width: 230,
    height: 310,
    borderRadius: 160,
    borderWidth: 3,
    borderColor: "rgba(255, 255, 255, 0.92)",
    backgroundColor: "transparent",
  },
  cameraInstruction: {
    marginTop: 22,
    overflow: "hidden",
    borderRadius: 999,
    backgroundColor: "rgba(15, 23, 42, 0.62)",
    paddingHorizontal: 16,
    paddingVertical: 9,
    color: "#ffffff",
    fontSize: 14,
    fontWeight: "800",
  },
  cameraTopBar: {
    position: "absolute",
    left: 0,
    right: 0,
    top: 0,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingHorizontal: 18,
    paddingTop: 54,
    paddingBottom: 14,
    backgroundColor: "rgba(2, 6, 23, 0.45)",
  },
  closeButton: {
    minWidth: 72,
    borderRadius: 999,
    backgroundColor: "rgba(255, 255, 255, 0.16)",
    paddingHorizontal: 14,
    paddingVertical: 9,
  },
  closeButtonText: {
    color: "#ffffff",
    fontSize: 13,
    fontWeight: "800",
    textAlign: "center",
  },
  closeButtonSpacer: {
    width: 72,
  },
  cameraTitle: {
    color: "#ffffff",
    fontSize: 17,
    fontWeight: "900",
  },
  cameraBottomBar: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 0,
    alignItems: "center",
    paddingTop: 18,
    paddingBottom: 44,
    backgroundColor: "rgba(2, 6, 23, 0.5)",
  },
  shutterButton: {
    width: 76,
    height: 76,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 38,
    borderWidth: 5,
    borderColor: "#ffffff",
    backgroundColor: "rgba(255,255,255,0.22)",
  },
  shutterInner: {
    width: 52,
    height: 52,
    borderRadius: 26,
    backgroundColor: "#ffffff",
  },
  processingOverlay: {
    ...StyleSheet.absoluteFillObject,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "rgba(2, 6, 23, 0.84)",
    paddingHorizontal: 32,
  },
  processingTitle: {
    marginTop: 18,
    color: "#ffffff",
    fontSize: 18,
    fontWeight: "900",
    textAlign: "center",
  },
  processingText: {
    marginTop: 8,
    color: "#cbd5e1",
    fontSize: 14,
    lineHeight: 20,
    textAlign: "center",
  },
});

export default AttendanceScreen;
