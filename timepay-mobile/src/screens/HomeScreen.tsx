import React, { useCallback, useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  RefreshControl,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";
import * as Location from "expo-location";
import { BottomTabScreenProps } from "@react-navigation/bottom-tabs";
import { apiService, getApiErrorMessage } from "../services/api";
import {
  AttendanceState,
  AttendanceStatusResponse,
  UserSession,
} from "../types";
import { EmployeeTabParamList } from "../navigation/AppNavigator";
import TimePayLogo from "../components/TimePayLogo";

type HomeScreenProps = BottomTabScreenProps<EmployeeTabParamList, "Home"> & {
  userSessionData: UserSession | null;
};

type GeofenceState = "checking" | "inside" | "outside" | "unavailable";

const formatElapsedTime = (startedAt: string | null): string => {
  if (!startedAt) {
    return "00:00:00";
  }

  const startedTime = new Date(startedAt).getTime();

  if (Number.isNaN(startedTime)) {
    return "00:00:00";
  }

  const totalSeconds = Math.max(0, Math.floor((Date.now() - startedTime) / 1000));
  const hours = Math.floor(totalSeconds / 3600).toString().padStart(2, "0");
  const minutes = Math.floor((totalSeconds % 3600) / 60)
    .toString()
    .padStart(2, "0");
  const seconds = Math.floor(totalSeconds % 60).toString().padStart(2, "0");

  return `${hours}:${minutes}:${seconds}`;
};

const getDistanceMeters = (
  first: { latitude: number; longitude: number },
  second: { latitude: number; longitude: number }
) => {
  const earthRadiusMeters = 6371000;
  const toRadians = (value: number) => (value * Math.PI) / 180;
  const latitudeDelta = toRadians(second.latitude - first.latitude);
  const longitudeDelta = toRadians(second.longitude - first.longitude);
  const firstLatitude = toRadians(first.latitude);
  const secondLatitude = toRadians(second.latitude);

  const a =
    Math.sin(latitudeDelta / 2) * Math.sin(latitudeDelta / 2) +
    Math.cos(firstLatitude) *
      Math.cos(secondLatitude) *
      Math.sin(longitudeDelta / 2) *
      Math.sin(longitudeDelta / 2);

  return earthRadiusMeters * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
};

const HomeScreen: React.FC<HomeScreenProps> = ({
  navigation,
  userSessionData,
}) => {
  const [attendanceStatus, setAttendanceStatus] =
    useState<AttendanceStatusResponse | null>(null);
  const [geofenceState, setGeofenceState] =
    useState<GeofenceState>("checking");
  const [distanceMeters, setDistanceMeters] = useState<number | null>(null);
  const [elapsedTime, setElapsedTime] = useState("00:00:00");
  const [loading, setLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const shiftStartedAt =
    attendanceStatus?.current_state === "clocked_in"
      ? attendanceStatus.last_punch?.timestamp ?? null
      : null;

  const firstName = useMemo(() => {
    return userSessionData?.user.name.split(" ")[0] ?? "there";
  }, [userSessionData?.user.name]);

  const loadDashboard = useCallback(async () => {
    setErrorMessage(null);
    setLoading(true);

    try {
      const [status, permission] = await Promise.all([
        apiService.getAttendanceStatus(),
        Location.requestForegroundPermissionsAsync(),
      ]);

      setAttendanceStatus(status);

      const companyLatitude = Number(userSessionData?.company.latitude);
      const companyLongitude = Number(userSessionData?.company.longitude);
      const radius = userSessionData?.company.geofence_radius_meters;

      if (
        permission.status !== Location.PermissionStatus.GRANTED ||
        !Number.isFinite(companyLatitude) ||
        !Number.isFinite(companyLongitude) ||
        !radius
      ) {
        setGeofenceState("unavailable");
        setDistanceMeters(null);
        return;
      }

      setGeofenceState("checking");

      const currentLocation = await Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.Balanced,
      });

      const distance = getDistanceMeters(
        {
          latitude: currentLocation.coords.latitude,
          longitude: currentLocation.coords.longitude,
        },
        {
          latitude: companyLatitude,
          longitude: companyLongitude,
        }
      );

      setDistanceMeters(distance);
      setGeofenceState(distance <= radius ? "inside" : "outside");
    } catch (error) {
      setErrorMessage(
        getApiErrorMessage(
          error,
          "Unable to load your dashboard. Pull down to try again."
        )
      );
      setGeofenceState("unavailable");
    } finally {
      setLoading(false);
    }
  }, [
    userSessionData?.company.geofence_radius_meters,
    userSessionData?.company.latitude,
    userSessionData?.company.longitude,
  ]);

  useEffect(() => {
    loadDashboard();
  }, [loadDashboard]);

  useEffect(() => {
    setElapsedTime(formatElapsedTime(shiftStartedAt));

    const timer = setInterval(() => {
      setElapsedTime(formatElapsedTime(shiftStartedAt));
    }, 1000);

    return () => clearInterval(timer);
  }, [shiftStartedAt]);

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

  const isClockedIn: boolean =
    attendanceStatus?.current_state === ("clocked_in" as AttendanceState);
  const geofenceLabel =
    geofenceState === "inside"
      ? "Inside geofence"
      : geofenceState === "outside"
        ? "Outside geofence"
        : geofenceState === "checking"
          ? "Checking location"
          : "Location unavailable";

  const geofenceTone =
    geofenceState === "inside"
      ? styles.successText
      : geofenceState === "outside"
        ? styles.dangerText
        : styles.warningText;

  return (
    <SafeAreaView style={styles.safeArea}>
      <ScrollView
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl refreshing={loading} onRefresh={loadDashboard} />
        }
      >
        <View style={styles.header}>
          <View style={styles.headerCopy}>
            <TimePayLogo style={styles.headerLogo} />
            <Text style={styles.company}>{userSessionData.company.name}</Text>
          </View>
        </View>

        <View style={styles.hero}>
          <Text style={styles.eyebrow}>Employee Portal</Text>
          <Text style={styles.title}>Hi, {firstName}</Text>
          <Text style={styles.subtitle}>
            Here is your live shift snapshot for today.
          </Text>
        </View>

        {errorMessage ? (
          <View style={styles.alertBox}>
            <Text style={styles.alertTitle}>Dashboard needs attention</Text>
            <Text style={styles.alertText}>{errorMessage}</Text>
          </View>
        ) : null}

        <View style={styles.shiftCard}>
          <View style={styles.shiftHeader}>
            <Text style={styles.cardLabel}>Current Shift</Text>
            <View
              style={[
                styles.statusPill,
                isClockedIn ? styles.activePill : styles.inactivePill,
              ]}
            >
              <Text
                style={[
                  styles.statusPillText,
                  isClockedIn ? styles.activePillText : styles.inactivePillText,
                ]}
              >
                {isClockedIn ? "Clocked In" : "Clocked Out"}
              </Text>
            </View>
          </View>

          <Text style={styles.elapsedTime}>{elapsedTime}</Text>
          <Text style={styles.cardHint}>
            {isClockedIn
              ? "Elapsed since your latest clock in."
              : "Start your shift from the attendance flow."}
          </Text>

          <TouchableOpacity
            activeOpacity={0.88}
            onPress={() => navigation.navigate("Attendance")}
            style={styles.primaryButton}
          >
            <Text style={styles.primaryButtonText}>
              {isClockedIn ? "Go to Clock Out" : "Go to Clock In"}
            </Text>
          </TouchableOpacity>
        </View>

        <View style={styles.metricsRow}>
          <View style={styles.metricCard}>
            <Text style={styles.cardLabel}>Geofence</Text>
            {loading && geofenceState === "checking" ? (
              <ActivityIndicator color="#059669" style={styles.metricSpinner} />
            ) : (
              <>
                <Text style={[styles.metricValue, geofenceTone]}>
                  {geofenceLabel}
                </Text>
                <Text style={styles.metricSubtext}>
                  {distanceMeters !== null
                    ? `${Math.round(distanceMeters)}m from office`
                    : "Enable location and company coordinates"}
                </Text>
              </>
            )}
          </View>

          <View style={styles.metricCard}>
            <Text style={styles.cardLabel}>Next Action</Text>
            <Text style={styles.metricValue}>
              {attendanceStatus?.next_expected_punch === "clock_out"
                ? "Clock Out"
                : "Clock In"}
            </Text>
            <Text style={styles.metricSubtext}>Face ID and GPS required</Text>
          </View>
        </View>

        <View style={styles.quickCard}>
          <Text style={styles.quickTitle}>Today at a glance</Text>
          <View style={styles.quickRow}>
            <Text style={styles.quickLabel}>Pay metric</Text>
            <Text style={styles.quickValue}>
              {userSessionData.company.pay_metric ?? "Not set"}
            </Text>
          </View>
          <View style={styles.quickRow}>
            <Text style={styles.quickLabel}>Office radius</Text>
            <Text style={styles.quickValue}>
              {userSessionData.company.geofence_radius_meters
                ? `${userSessionData.company.geofence_radius_meters}m`
                : "Not set"}
            </Text>
          </View>
          <View style={styles.quickRow}>
            <Text style={styles.quickLabel}>Employee ID</Text>
            <Text style={styles.quickValue}>{userSessionData.user.id}</Text>
          </View>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: "#f8fafc",
  },
  scrollContent: {
    paddingHorizontal: 20,
    paddingTop: 22,
    paddingBottom: 32,
  },
  header: {
    flexDirection: "row",
    alignItems: "center",
    marginBottom: 26,
  },
  headerCopy: {
    flex: 1,
  },
  headerLogo: {
    width: 132,
    height: 42,
  },
  company: {
    marginTop: 2,
    color: "#64748b",
    fontSize: 13,
    fontWeight: "700",
  },
  hero: {
    marginBottom: 18,
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
    fontSize: 32,
    fontWeight: "900",
  },
  subtitle: {
    marginTop: 8,
    color: "#64748b",
    fontSize: 15,
    lineHeight: 22,
  },
  alertBox: {
    marginBottom: 16,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: "#fed7aa",
    backgroundColor: "#fff7ed",
    padding: 14,
  },
  alertTitle: {
    color: "#9a3412",
    fontSize: 14,
    fontWeight: "900",
  },
  alertText: {
    marginTop: 4,
    color: "#c2410c",
    fontSize: 13,
    lineHeight: 19,
  },
  shiftCard: {
    borderRadius: 24,
    backgroundColor: "#111827",
    padding: 20,
    marginBottom: 14,
    shadowColor: "#111827",
    shadowOffset: { width: 0, height: 18 },
    shadowOpacity: 0.18,
    shadowRadius: 24,
    elevation: 5,
  },
  shiftHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  cardLabel: {
    color: "#94a3b8",
    fontSize: 12,
    fontWeight: "900",
    letterSpacing: 0.6,
    textTransform: "uppercase",
  },
  statusPill: {
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  activePill: {
    backgroundColor: "#dcfce7",
  },
  inactivePill: {
    backgroundColor: "#e2e8f0",
  },
  statusPillText: {
    fontSize: 12,
    fontWeight: "900",
  },
  activePillText: {
    color: "#166534",
  },
  inactivePillText: {
    color: "#475569",
  },
  elapsedTime: {
    marginTop: 20,
    color: "#ffffff",
    fontSize: 46,
    fontWeight: "900",
  },
  cardHint: {
    marginTop: 6,
    color: "#cbd5e1",
    fontSize: 14,
    lineHeight: 20,
  },
  primaryButton: {
    marginTop: 20,
    minHeight: 54,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 16,
    backgroundColor: "#059669",
  },
  primaryButtonText: {
    color: "#ffffff",
    fontSize: 16,
    fontWeight: "900",
  },
  metricsRow: {
    flexDirection: "row",
    gap: 12,
    marginBottom: 14,
  },
  metricCard: {
    flex: 1,
    minHeight: 136,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: "#e2e8f0",
    backgroundColor: "#ffffff",
    padding: 16,
  },
  metricSpinner: {
    marginTop: 18,
    alignSelf: "flex-start",
  },
  metricValue: {
    marginTop: 14,
    color: "#0f172a",
    fontSize: 18,
    fontWeight: "900",
    lineHeight: 24,
  },
  metricSubtext: {
    marginTop: 8,
    color: "#64748b",
    fontSize: 13,
    lineHeight: 18,
  },
  successText: {
    color: "#059669",
  },
  warningText: {
    color: "#ca8a04",
  },
  dangerText: {
    color: "#dc2626",
  },
  quickCard: {
    borderRadius: 20,
    borderWidth: 1,
    borderColor: "#e2e8f0",
    backgroundColor: "#ffffff",
    padding: 18,
  },
  quickTitle: {
    color: "#0f172a",
    fontSize: 18,
    fontWeight: "900",
    marginBottom: 8,
  },
  quickRow: {
    minHeight: 42,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    borderTopWidth: 1,
    borderTopColor: "#f1f5f9",
  },
  quickLabel: {
    color: "#64748b",
    fontSize: 14,
    fontWeight: "700",
  },
  quickValue: {
    color: "#0f172a",
    fontSize: 14,
    fontWeight: "900",
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
    fontWeight: "900",
  },
  mutedText: {
    marginTop: 8,
    color: "#64748b",
    fontSize: 14,
  },
});

export default HomeScreen;
