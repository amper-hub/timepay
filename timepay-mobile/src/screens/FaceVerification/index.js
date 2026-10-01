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
import { useFocusEffect } from "@react-navigation/native";
import * as ImageManipulator from "expo-image-manipulator";
import * as FileSystem from "expo-file-system/legacy";
import * as Location from "expo-location";
import FaceDetection from "@react-native-ml-kit/face-detection";
import apiClient, { getApiErrorMessage } from "../../services/api";
import {
  getHighAccuracyAttendanceLocation,
  LOCATION_FALLBACK_WARNING,
} from "../../services/location";

const MAX_SELFIE_WIDTH = 1600;
const SELFIE_JPEG_QUALITY = 0.85;
const INITIAL_LIVENESS_INSTRUCTION = "Please blink your eyes to verify.";

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
  const [cameraReady, setCameraReady] = useState(false);
  const [cameraActive, setCameraActive] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [blinkDetected, setBlinkDetected] = useState(false);
  const [livenessMessage, setLivenessMessage] = useState(
    INITIAL_LIVENESS_INSTRUCTION
  );
  const [screenError, setScreenError] = useState(null);
  const submittingRef = useRef(false);
  const frameInFlightRef = useRef(false);
  const blinkPhaseRef = useRef("waiting_for_open");

  const action = route?.params?.action === "clock_out" ? "clock_out" : "clock_in";
  const actionLabel = action === "clock_out" ? "Clock Out" : "Clock In";

  useFocusEffect(
    useCallback(() => {
      blinkPhaseRef.current = "waiting_for_open";
      frameInFlightRef.current = false;
      submittingRef.current = false;
      setBlinkDetected(false);
      setLivenessMessage(INITIAL_LIVENESS_INSTRUCTION);
      setScreenError(null);
      setCameraActive(true);

      return () => setCameraActive(false);
    }, [])
  );

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
        name: `${action}_${Date.now()}.jpg`,
        type: "image/jpeg",
      });

      const endpoint =
        action === "clock_in" ? "/attendance/clock-in" : "/attendance/store";

      const response = await apiClient.post(endpoint, formData, {
        timeout: 45000,
        headers: {
          "Content-Type": "multipart/form-data",
        },
      });

      return response.data;
    },
    [action]
  );

  const handleTakePhoto = useCallback(async () => {
    if (!cameraRef.current || !cameraReady || submittingRef.current) {
      return;
    }

    submittingRef.current = true;
    setSubmitting(true);
    setScreenError(null);

    try {
      const photo = await cameraRef.current.takePictureAsync({
        quality: 0.95,
        skipProcessing: false,
        shutterSound: false,
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

      if (response?.face_verification?.enrolled) {
        setBlinkDetected(false);
        setLivenessMessage(
          "Face profile saved. Please blink again to verify your attendance."
        );
        return response;
      }

      navigation.navigate("Attendance");
      const flagged = response?.attendance_log?.status === "flagged";
      Alert.alert(
        flagged ? `${actionLabel} Flagged` : `${actionLabel} Successful`,
        response.message
      );
    } catch (error) {
      if (
        error?.response?.status === 422 &&
        error?.response?.data?.error === "face_mismatch"
      ) {
        const mismatchMessage =
          error.response.data.message ||
          "Face not recognized. Please try again.";

        setScreenError(mismatchMessage);
        Alert.alert("Verification Failed", mismatchMessage);
        return;
      }

      const errorMessage =
        error?.response?.data?.message ||
        getApiErrorMessage(
          error,
          `Unable to complete ${actionLabel.toLowerCase()}. Please try again.`
        );

      setScreenError(errorMessage);
      Alert.alert(
        `${actionLabel} Failed`,
        errorMessage || "Verification failed. Please try again."
      );
    } finally {
      submittingRef.current = false;
      setSubmitting(false);
    }
  }, [actionLabel, cameraReady, navigation, submitSelfie]);

  useEffect(() => {
    if (!cameraActive || !cameraReady) {
      return;
    }

    let cancelled = false;
    let scanTimer;

    const scanPreview = async () => {
      if (cancelled || !cameraRef.current || submittingRef.current) {
        return;
      }

      if (frameInFlightRef.current) {
        scanTimer = setTimeout(scanPreview, 200);
        return;
      }

      frameInFlightRef.current = true;
      let previewUri;
      let blinkSequenceComplete = false;

      try {
        const preview = await cameraRef.current.takePictureAsync({
          quality: 0.15,
          skipProcessing: false,
          shutterSound: false,
        });
        previewUri = preview?.uri;

        if (previewUri) {
          const faces = await FaceDetection.detect(previewUri, {
            performanceMode: "fast",
            classificationMode: "all",
          });

          if (faces.length !== 1) {
            setLivenessMessage(
              faces.length === 0
                ? "Center your face in the guide."
                : "Please make sure only your face is visible."
            );
          } else {
            const { leftEyeOpenProbability, rightEyeOpenProbability } = faces[0];

            if (
              typeof leftEyeOpenProbability === "number" &&
              typeof rightEyeOpenProbability === "number"
            ) {
              const eyesOpen =
                leftEyeOpenProbability > 0.8 && rightEyeOpenProbability > 0.8;
              const eyesClosed =
                leftEyeOpenProbability < 0.25 && rightEyeOpenProbability < 0.25;

              if (blinkPhaseRef.current === "waiting_for_open" && eyesOpen) {
                blinkPhaseRef.current = "waiting_for_close";
                setLivenessMessage("Eyes open detected. Now blink once.");
              } else if (
                blinkPhaseRef.current === "waiting_for_close" &&
                eyesClosed
              ) {
                blinkPhaseRef.current = "waiting_for_reopen";
                setLivenessMessage("Blink detected. Open your eyes.");
              } else if (
                blinkPhaseRef.current === "waiting_for_reopen" &&
                eyesOpen
              ) {
                blinkPhaseRef.current = "complete";
                blinkSequenceComplete = true;
              }
            } else {
              setLivenessMessage("Look directly at the camera.");
            }
          }
        }
      } catch (error) {
        console.error("[Liveness] ML Kit frame analysis failed:", error);
        if (!cancelled) {
          setScreenError(
            "On-device face detection is unavailable. Rebuild the Expo development client with the ML Kit module installed."
          );
        }
        return;
      } finally {
        if (previewUri) {
          await FileSystem.deleteAsync(previewUri, { idempotent: true }).catch(
            () => undefined
          );
        }
        frameInFlightRef.current = false;
      }

      if (cancelled) {
        return;
      }

      if (blinkSequenceComplete) {
        setBlinkDetected(true);
        setLivenessMessage("Blink verified. Capturing your selfie...");
        await new Promise((resolve) => setTimeout(resolve, 450));
        if (!cancelled) {
          const response = await handleTakePhoto();

          if (!response?.attendance_log && !cancelled) {
            blinkPhaseRef.current = "waiting_for_open";
            frameInFlightRef.current = false;
            setBlinkDetected(false);
            setLivenessMessage(response?.face_verification?.enrolled
              ? "Face profile saved. Please blink again to verify your attendance."
              : "Please blink again to retry the attendance check.");
            scanTimer = setTimeout(scanPreview, 250);
          }
        }
        return;
      }

      scanTimer = setTimeout(scanPreview, 200);
    };

    void scanPreview();

    return () => {
      cancelled = true;
      if (scanTimer) {
        clearTimeout(scanTimer);
      }
    };
  }, [cameraActive, cameraReady, handleTakePhoto]);

  if (!cameraPermission) {
    return (
      <SafeAreaView style={styles.safeArea}>
        <View style={styles.centeredState}>
          <ActivityIndicator color="#047857" size="large" />
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
            TimePay needs the front camera to capture a selfie before submitting
            your attendance.
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
      <CameraView
        ref={cameraRef}
        style={styles.camera}
        facing="front"
        active={cameraActive}
        onCameraReady={() => setCameraReady(true)}
      />

      <View pointerEvents="none" style={styles.overlay}>
        <View style={styles.topScrim}>
          <Text style={styles.cameraTitle}>{actionLabel}</Text>
          <Text style={styles.cameraSubtitle}>{livenessMessage}</Text>
          {blinkDetected && !submitting ? (
            <View style={styles.livenessSuccessBadge}>
              <Text style={styles.livenessSuccessText}>Blink verified</Text>
            </View>
          ) : null}
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
        {submitting ? (
          <View style={styles.scanningStatus}>
            <ActivityIndicator color="#ffffff" />
            <Text style={styles.captureButtonText}>
              Sending your verified selfie and location...
            </Text>
          </View>
        ) : (
          <Text style={styles.captureButtonText}>
            {cameraReady ? "Keep your face in the guide" : "Starting camera..."}
          </Text>
        )}
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
    backgroundColor: "#047857",
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
    backgroundColor: "rgba(4, 120, 87, 0.72)",
  },
  cameraTitle: {
    color: "#ffffff",
    fontSize: 24,
    fontWeight: "900",
  },
  cameraSubtitle: {
    marginTop: 6,
    color: "#d1fae5",
    fontSize: 15,
    fontWeight: "800",
    textAlign: "center",
    paddingHorizontal: 20,
  },
  livenessSuccessBadge: {
    marginTop: 12,
    borderRadius: 999,
    backgroundColor: "#065f46",
    paddingHorizontal: 14,
    paddingVertical: 7,
  },
  livenessSuccessText: {
    color: "#ffffff",
    fontSize: 13,
    fontWeight: "900",
  },
  guideWrap: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    paddingHorizontal: 18,
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
    alignItems: "center",
    paddingHorizontal: 22,
    paddingTop: 18,
    paddingBottom: 38,
    backgroundColor: "rgba(4, 120, 87, 0.74)",
  },
  scanningStatus: {
    minHeight: 42,
    alignItems: "center",
    justifyContent: "center",
    gap: 10,
  },
  captureButtonText: {
    color: "#ffffff",
    fontSize: 15,
    fontWeight: "900",
    textAlign: "center",
  },
});

export default FaceVerificationScreen;
