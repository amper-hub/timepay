import React, { useCallback, useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  SafeAreaView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";
import { CameraView, useCameraPermissions } from "expo-camera";
import * as ImageManipulator from "expo-image-manipulator";
import * as Location from "expo-location";
import apiClient, { getApiErrorMessage } from "../../services/api";
import {
  getHighAccuracyAttendanceLocation,
  LOCATION_FALLBACK_WARNING,
} from "../../services/location";

const MAX_SELFIE_WIDTH = 800;
const SELFIE_JPEG_QUALITY = 0.3;

const compressSelfie = async (photo) => {
  const shouldResize = photo?.width && photo.width > MAX_SELFIE_WIDTH;
  const actions = shouldResize
    ? [{ resize: { width: MAX_SELFIE_WIDTH } }]
    : [];

  return ImageManipulator.manipulateAsync(photo.uri, actions, {
    compress: SELFIE_JPEG_QUALITY,
    format: ImageManipulator.SaveFormat.JPEG,
  });
};

const FaceVerificationScreen = ({ navigation, route }) => {
  const cameraRef = useRef(null);
  const [cameraPermission, requestCameraPermission] = useCameraPermissions();
  const [submitting, setSubmitting] = useState(false);
  const [screenError, setScreenError] = useState(null);

  const action = route?.params?.action === "clock_out" ? "clock_out" : "clock_in";
  const actionLabel = action === "clock_out" ? "Clock Out" : "Clock In";

  useEffect(() => {
    if (cameraPermission && !cameraPermission.granted) {
      requestCameraPermission();
    }
  }, [cameraPermission, requestCameraPermission]);

  const submitSelfie = useCallback(
    async (photoUri, latitude, longitude) => {
      const formData = new FormData();
      formData.append("latitude", String(latitude));
      formData.append("longitude", String(longitude));
      formData.append("type", action);
      formData.append("selfie", {
        uri: photoUri,
        name: "selfie.jpg",
        type: "image/jpeg",
      });

      const endpoint =
        action === "clock_in" ? "/attendance/clock-in" : "/attendance/store";

      const response = await apiClient.post(endpoint, formData, {
        headers: {
          "Content-Type": "multipart/form-data",
        },
      });

      return response.data;
    },
    [action]
  );

  const handleSnapAndVerify = useCallback(async () => {
    if (!cameraRef.current || submitting) return;

    setSubmitting(true);
    setScreenError(null);

    try {
      const photo = await cameraRef.current.takePictureAsync({
        quality: SELFIE_JPEG_QUALITY,
        base64: false,
        skipProcessing: true,
      });

      if (!photo?.uri) {
        throw new Error("Unable to capture selfie. Please try again.");
      }

      const compressedPhoto = await compressSelfie(photo);

      const locationPermission =
        await Location.requestForegroundPermissionsAsync();

      if (locationPermission.status !== Location.PermissionStatus.GRANTED) {
        throw new Error("Location permission is required to submit attendance.");
      }

      const { location, usedLastKnownLocation } =
        await getHighAccuracyAttendanceLocation();

      if (usedLastKnownLocation) {
        setScreenError(LOCATION_FALLBACK_WARNING);
        Alert.alert("Location Warning", LOCATION_FALLBACK_WARNING);
      }

      const response = await submitSelfie(
        compressedPhoto.uri,
        location.coords.latitude,
        location.coords.longitude
      );

      Alert.alert(`${actionLabel} Successful`, response.message, [
        {
          text: "OK",
          onPress: () => navigation.navigate("Attendance"),
        },
      ]);
    } catch (error) {
      if (
        error?.response?.status === 422 &&
        error?.response?.data?.error === "face_mismatch"
      ) {
        const mismatchMessage =
          error.response.data.message ||
          "Face not recognized. Please try again.";

        setScreenError(mismatchMessage);
        Alert.alert("Verification Failed", "Face not recognized. Please try again.");
        return;
      }

      const errorMessage =
        error?.response?.data?.message ||
        getApiErrorMessage(
          error,
          `Unable to complete ${actionLabel.toLowerCase()}. Please try again.`
        );

      setScreenError(errorMessage);
      Alert.alert(`${actionLabel} Failed`, errorMessage);
    } finally {
      setSubmitting(false);
    }
  }, [actionLabel, navigation, submitSelfie, submitting]);

  if (!cameraPermission) {
    return (
      <SafeAreaView style={styles.safeArea}>
        <View style={styles.centeredState}>
          <ActivityIndicator color="#4f46e5" size="large" />
          <Text style={styles.centeredText}>Checking camera access...</Text>
        </View>
      </SafeAreaView>
    );
  }

  if (!cameraPermission.granted) {
    return (
      <SafeAreaView style={styles.safeArea}>
        <View style={styles.permissionContainer}>
          <Text style={styles.permissionTitle}>Camera Access Required</Text>
          <Text style={styles.permissionText}>
            TimePay needs the front camera to capture a live selfie before
            submitting your attendance.
          </Text>

          {screenError ? (
            <View style={styles.inlineAlert}>
              <Text style={styles.inlineAlertText}>{screenError}</Text>
            </View>
          ) : null}

          <TouchableOpacity
            activeOpacity={0.9}
            onPress={requestCameraPermission}
            style={styles.permissionButton}
          >
            <Text style={styles.permissionButtonText}>Grant Permission</Text>
          </TouchableOpacity>

          <TouchableOpacity
            onPress={() => navigation.navigate("Attendance")}
            style={styles.secondaryButton}
          >
            <Text style={styles.secondaryButtonText}>Back to Attendance</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <View style={styles.cameraContainer}>
      <CameraView ref={cameraRef} style={styles.camera} facing="front" />

      <View pointerEvents="none" style={styles.overlay}>
        <View style={styles.topScrim}>
          <Text style={styles.cameraTitle}>{actionLabel}</Text>
          <Text style={styles.cameraSubtitle}>
            Align your face inside the guide
          </Text>
        </View>

        <View style={styles.guideWrap}>
          <View style={styles.faceGuide} />
        </View>
      </View>

      <View style={styles.topBar}>
        <TouchableOpacity
          disabled={submitting}
          onPress={() => navigation.navigate("Attendance")}
          style={styles.closeButton}
        >
          <Text style={styles.closeButtonText}>Cancel</Text>
        </TouchableOpacity>
      </View>

      {screenError ? (
        <View style={styles.errorBanner}>
          <Text style={styles.errorBannerText}>{screenError}</Text>
        </View>
      ) : null}

      <View style={styles.bottomBar}>
        <TouchableOpacity
          activeOpacity={0.9}
          disabled={submitting}
          onPress={handleSnapAndVerify}
          style={[styles.snapButton, submitting && styles.disabledButton]}
        >
          {submitting ? (
            <ActivityIndicator color="#ffffff" />
          ) : (
            <Text style={styles.snapButtonText}>Snap & Verify</Text>
          )}
        </TouchableOpacity>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: "#f8fafc",
  },
  centeredState: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    padding: 24,
  },
  centeredText: {
    marginTop: 12,
    color: "#64748b",
    fontSize: 14,
    fontWeight: "700",
  },
  permissionContainer: {
    flex: 1,
    justifyContent: "center",
    padding: 24,
  },
  permissionTitle: {
    color: "#0f172a",
    fontSize: 28,
    fontWeight: "900",
    textAlign: "center",
  },
  permissionText: {
    marginTop: 12,
    color: "#64748b",
    fontSize: 15,
    lineHeight: 22,
    textAlign: "center",
  },
  inlineAlert: {
    marginTop: 18,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: "#fecaca",
    backgroundColor: "#fef2f2",
    padding: 12,
  },
  inlineAlertText: {
    color: "#b91c1c",
    fontSize: 13,
    fontWeight: "700",
    textAlign: "center",
  },
  permissionButton: {
    minHeight: 56,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 18,
    backgroundColor: "#4f46e5",
    paddingHorizontal: 18,
    marginTop: 24,
  },
  permissionButtonText: {
    color: "#ffffff",
    fontSize: 16,
    fontWeight: "900",
  },
  secondaryButton: {
    minHeight: 52,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 16,
    borderWidth: 1,
    borderColor: "#cbd5e1",
    backgroundColor: "#ffffff",
    paddingHorizontal: 18,
    marginTop: 12,
  },
  secondaryButtonText: {
    color: "#334155",
    fontSize: 15,
    fontWeight: "900",
  },
  cameraContainer: {
    flex: 1,
    backgroundColor: "#020617",
  },
  camera: {
    ...StyleSheet.absoluteFillObject,
  },
  overlay: {
    ...StyleSheet.absoluteFillObject,
  },
  topScrim: {
    alignItems: "center",
    paddingTop: 72,
    paddingBottom: 28,
    backgroundColor: "rgba(2, 6, 23, 0.42)",
  },
  cameraTitle: {
    color: "#ffffff",
    fontSize: 24,
    fontWeight: "900",
  },
  cameraSubtitle: {
    marginTop: 6,
    color: "#cbd5e1",
    fontSize: 14,
    fontWeight: "700",
  },
  guideWrap: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
  },
  faceGuide: {
    width: 230,
    height: 310,
    borderRadius: 150,
    borderWidth: 4,
    borderColor: "rgba(255, 255, 255, 0.96)",
    backgroundColor: "transparent",
  },
  topBar: {
    position: "absolute",
    left: 18,
    top: 54,
  },
  closeButton: {
    borderRadius: 999,
    backgroundColor: "rgba(15, 23, 42, 0.72)",
    paddingHorizontal: 14,
    paddingVertical: 9,
  },
  closeButtonText: {
    color: "#ffffff",
    fontSize: 13,
    fontWeight: "900",
  },
  errorBanner: {
    position: "absolute",
    left: 18,
    right: 18,
    bottom: 126,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: "#fecaca",
    backgroundColor: "#fef2f2",
    padding: 12,
  },
  errorBannerText: {
    color: "#b91c1c",
    fontSize: 13,
    fontWeight: "800",
    textAlign: "center",
  },
  bottomBar: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 0,
    paddingHorizontal: 22,
    paddingTop: 18,
    paddingBottom: 38,
    backgroundColor: "rgba(2, 6, 23, 0.56)",
  },
  snapButton: {
    minHeight: 58,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 18,
    backgroundColor: "#059669",
    paddingHorizontal: 18,
  },
  snapButtonText: {
    color: "#ffffff",
    fontSize: 17,
    fontWeight: "900",
  },
  disabledButton: {
    opacity: 0.62,
  },
});

export default FaceVerificationScreen;
