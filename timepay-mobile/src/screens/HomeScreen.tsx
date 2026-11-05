import React, { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
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
import { useFocusEffect } from "@react-navigation/native";
import { apiService, getApiErrorMessage } from "../services/api";
import {
  AttendanceState,
  AttendanceStatusResponse,
  CompanyGeofence,
  PendingPayResponse,
  UserSession,
} from "../types";
import {
  classifyGeofence,
  getDistanceMeters,
  isLocationFresh,
} from "../services/geofence";
import { EmployeeTabParamList } from "../navigation/AppNavigator";
import TimePayLogo from "../components/TimePayLogo";
import { colors } from "../theme/colors";

type HomeScreenProps = BottomTabScreenProps<EmployeeTabParamList, "Home"> & {
  userSessionData: UserSession | null;
};

type GeofenceState = "checking" | "inside" | "outside" | "unavailable" | "permission_required";

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

const formatLastPunch = (status: AttendanceStatusResponse | null): string => {
  const punch = status?.last_punch;
  if (!punch?.timestamp) return "No attendance activity yet";

  const date = new Date(punch.timestamp);
  if (Number.isNaN(date.getTime())) return "Latest punch recorded";

  const action = punch.type === "clock_in" ? "Clocked in" : "Clocked out";
  return `${action} · ${date.toLocaleDateString()} ${date.toLocaleTimeString([], {
    hour: "numeric",
    minute: "2-digit",
  })}`;
};

const formatPendingAmount = (
  currencySymbol: string | undefined,
  amount: number | undefined
): string => {
  const safeAmount = Number(amount ?? 0);
  const formattedAmount = new Intl.NumberFormat("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number.isFinite(safeAmount) ? safeAmount : 0);

  return `${currencySymbol ?? ""} ${formattedAmount}`.trim();
};

const HomeScreen: React.FC<HomeScreenProps> = ({
  navigation,
  userSessionData,
}) => {
  const shiftTimerRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const shiftStatusPollRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const invalidationHandledRef = useRef<number | null>(null);
  const [attendanceStatus, setAttendanceStatus] =
    useState<AttendanceStatusResponse | null>(null);
  const [pendingPay, setPendingPay] = useState<PendingPayResponse | null>(null);
  const [geofenceState, setGeofenceState] =
    useState<GeofenceState>("checking");
  const [distanceMeters, setDistanceMeters] = useState<number | null>(null);
  const [companyGeofence, setCompanyGeofence] = useState<CompanyGeofence | null>(null);
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
  const todayLabel = useMemo(
    () => new Intl.DateTimeFormat(undefined, { weekday: "long", month: "long", day: "numeric" }).format(new Date()),
    []
  );

  const stopShiftTimer = useCallback(() => {
    if (shiftTimerRef.current) {
      clearInterval(shiftTimerRef.current);
      shiftTimerRef.current = null;
    }

    setElapsedTime("00:00:00");
  }, []);

  const handleShiftTerminated = useCallback(
    async (status: AttendanceStatusResponse) => {
      const rejectedLogId = status.last_punch?.id ?? null;

      if (rejectedLogId && invalidationHandledRef.current === rejectedLogId) {
        return;
      }

      invalidationHandledRef.current = rejectedLogId;
      stopShiftTimer();
      setAttendanceStatus({
        ...status,
        current_state: "clocked_out",
        next_expected_punch: "clock_in",
      });

      try {
        const pendingPayData = await apiService.getPendingPay();
        setPendingPay(pendingPayData);
      } catch {
        // The termination alert is more important than blocking on a pay refresh.
      }

      Alert.alert(
        "Shift Terminated",
        status.termination_reason ||
          "Your shift was terminated. The employer rejected your clock-in photo."
      );
    },
    [stopShiftTimer]
  );

  const loadDashboard = useCallback(async () => {
    setErrorMessage(null);
    setLoading(true);
    setGeofenceState("checking");
    setDistanceMeters(null);

    try {
      const [status, pendingPayData, geofence, permission] = await Promise.all([
        apiService.getAttendanceStatus(),
        apiService.getPendingPay(),
        apiService.getCompanyGeofence().catch(() => null),
        Location.requestForegroundPermissionsAsync(),
      ]);

      setAttendanceStatus(status);

      if (status.shift_invalidated) {
        await handleShiftTerminated(status);
      }

      setPendingPay(pendingPayData);
      setCompanyGeofence(geofence);

      const rawLatitude = geofence?.latitude;
      const rawLongitude = geofence?.longitude;
      const companyLatitude = rawLatitude == null || rawLatitude === "" ? NaN : Number(rawLatitude);
      const companyLongitude = rawLongitude == null || rawLongitude === "" ? NaN : Number(rawLongitude);
      const radius = Number(geofence?.geofence_radius_meters);

      if (permission.status !== Location.PermissionStatus.GRANTED) {
        setGeofenceState("permission_required");
        setDistanceMeters(null);
        return;
      }

      if (!Number.isFinite(companyLatitude) || companyLatitude < -90 || companyLatitude > 90 ||
        !Number.isFinite(companyLongitude) || companyLongitude < -180 || companyLongitude > 180 ||
        !Number.isFinite(radius) || radius <= 0) {
        setGeofenceState("unavailable");
        setDistanceMeters(null);
        return;
      }

      setGeofenceState("checking");

      const currentLocation = await Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.High,
      });

      const { latitude, longitude, accuracy } = currentLocation.coords;
      if (!Number.isFinite(latitude) || latitude < -90 || latitude > 90 ||
        !Number.isFinite(longitude) || longitude < -180 || longitude > 180 ||
        accuracy == null || !Number.isFinite(accuracy) || accuracy < 0 ||
        !isLocationFresh(currentLocation.timestamp)) {
        setGeofenceState("unavailable");
        setDistanceMeters(null);
        return;
      }

      const distance = getDistanceMeters(
        {
          latitude,
          longitude,
        },
        {
          latitude: companyLatitude,
          longitude: companyLongitude,
        }
      );

      setDistanceMeters(distance);
      // Account for GPS uncertainty: show a definite result only when the
      // complete accuracy circle falls on one side of the company boundary.
      setGeofenceState(classifyGeofence(distance, accuracy, radius));
    } catch (error) {
      setCompanyGeofence(null);
      setDistanceMeters(null);
      setErrorMessage(
        getApiErrorMessage(
          error,
          "Unable to load your dashboard. Pull down to try again.",
          "attendance"
        )
      );
      setGeofenceState("unavailable");
    } finally {
      setLoading(false);
    }
  }, [
    handleShiftTerminated,
  ]);

  useFocusEffect(
    useCallback(() => {
      loadDashboard();
    }, [loadDashboard])
  );

  useEffect(() => {
    stopShiftTimer();
    setElapsedTime(formatElapsedTime(shiftStartedAt));

    if (!shiftStartedAt) {
      return undefined;
    }

    shiftTimerRef.current = setInterval(() => {
      setElapsedTime(formatElapsedTime(shiftStartedAt));
    }, 1000);

    return stopShiftTimer;
  }, [shiftStartedAt, stopShiftTimer]);

  useEffect(() => {
    if (shiftStatusPollRef.current) {
      clearInterval(shiftStatusPollRef.current);
      shiftStatusPollRef.current = null;
    }

    if (attendanceStatus?.current_state !== "clocked_in") {
      return undefined;
    }

    const pollAttendanceStatus = async () => {
      try {
        const status = await apiService.getAttendanceStatus();

        if (status.shift_invalidated) {
          await handleShiftTerminated(status);
          return;
        }

        setAttendanceStatus(status);
      } catch {
        if (__DEV__) {
          console.warn("[Attendance] Silent shift status polling failed.");
        }
      }
    };

    shiftStatusPollRef.current = setInterval(pollAttendanceStatus, 30000);

    return () => {
      if (shiftStatusPollRef.current) {
        clearInterval(shiftStatusPollRef.current);
        shiftStatusPollRef.current = null;
      }
    };
  }, [attendanceStatus?.current_state, handleShiftTerminated]);

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
      ? "Inside Workplace"
      : geofenceState === "outside"
        ? "Outside Workplace"
        : geofenceState === "checking"
          ? "Checking location"
          : geofenceState === "permission_required"
            ? "Location permission required"
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
          <Text style={styles.todayLabel}>{todayLabel}</Text>
          <Text style={styles.subtitle}>
            Here is your live attendance and shift snapshot.
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

        <View style={styles.pendingEarningsCard}>
          <Text style={styles.pendingEarningsLabel}>Pending Earnings</Text>
          <Text style={styles.pendingEarningsAmount}>
            {formatPendingAmount(
              pendingPay?.currency_symbol,
              pendingPay?.pending_amount
            )}
          </Text>
          <Text style={styles.pendingEarningsSubtext}>
            Based on {Number(pendingPay?.unpaid_hours ?? 0).toFixed(2)} unpaid hours
          </Text>
        </View>

        <View style={styles.metricsRow}>
          <View style={styles.metricCard}>
            <Text style={styles.cardLabel}>Location</Text>
            {loading && geofenceState === "checking" ? (
              <ActivityIndicator color="#059669" style={styles.metricSpinner} />
            ) : (
              <>
                <Text style={[styles.metricValue, geofenceTone]}>
                  {geofenceLabel}
                </Text>
                <Text style={styles.metricSubtext}>
                  {distanceMeters !== null
                    ? `${Math.round(distanceMeters)}m from workplace`
                    : geofenceState === "permission_required"
                      ? "Allow location access to check workplace status"
                      : "Workplace location or an accurate GPS fix is unavailable"}
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
              {companyGeofence?.geofence_radius_meters
                ? `${companyGeofence.geofence_radius_meters}m`
                : "Not set"}
            </Text>
          </View>
          <View style={styles.quickRow}>
            <Text style={styles.quickLabel}>Employee ID</Text>
            <Text style={styles.quickValue}>{userSessionData.user.id}</Text>
          </View>
          <View style={styles.quickRow}>
            <Text style={styles.quickLabel}>Recent activity</Text>
            <Text style={styles.quickValue}>{formatLastPunch(attendanceStatus)}</Text>
          </View>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: colors.background,
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
  todayLabel: {
    marginTop: 4,
    color: colors.muted,
    fontSize: 14,
    fontWeight: "600",
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
  pendingEarningsCard: {
    borderRadius: 20,
    borderWidth: 1,
    borderColor: "#a7f3d0",
    backgroundColor: "#ECFDF5",
    padding: 18,
    marginBottom: 14,
  },
  pendingEarningsLabel: {
    color: "#047857",
    fontSize: 12,
    fontWeight: "900",
    letterSpacing: 0.6,
    textTransform: "uppercase",
  },
  pendingEarningsAmount: {
    marginTop: 10,
    color: "#047857",
    fontSize: 32,
    fontWeight: "900",
  },
  pendingEarningsSubtext: {
    marginTop: 6,
    color: "#64748b",
    fontSize: 13,
    lineHeight: 18,
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
    flexShrink: 1,
    maxWidth: "65%",
    color: "#0f172a",
    fontSize: 14,
    fontWeight: "900",
    textAlign: "right",
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
