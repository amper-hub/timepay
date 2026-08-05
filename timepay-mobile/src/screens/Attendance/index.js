import React, { useCallback, useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  RefreshControl,
  SafeAreaView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";
import { fetchAttendanceHistory } from "../../services/attendanceService";
import { getApiErrorMessage } from "../../services/api";

const formatDate = (value) => {
  if (!value) return "—";

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return date.toLocaleDateString(undefined, {
    weekday: "short",
    month: "short",
    day: "numeric",
    year: "numeric",
  });
};

const formatTime = (value) => {
  if (!value) return "—";

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return date.toLocaleTimeString(undefined, {
    hour: "numeric",
    minute: "2-digit",
  });
};

const formatHours = (value) => {
  if (value === null || value === undefined) return "In progress";

  return `${Number(value).toFixed(2)} hrs`;
};

const AttendanceHistoryScreen = ({ navigation, userSessionData, onLogout }) => {
  const [records, setRecords] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [clockAction, setClockAction] = useState(null);
  const [screenError, setScreenError] = useState(null);

  const summary = useMemo(() => {
    const completed = records.filter((record) => record.total_hours !== null);
    const totalHours = completed.reduce(
      (sum, record) => sum + Number(record.total_hours || 0),
      0
    );

    return {
      days: records.length,
      hours: totalHours.toFixed(1),
      late: records.filter((record) => record.status === "late").length,
    };
  }, [records]);

  const loadHistory = useCallback(async ({ silent = false } = {}) => {
    if (!silent) setLoading(true);
    setScreenError(null);

    try {
      const data = await fetchAttendanceHistory();
      setRecords(data);
    } catch (error) {
      setScreenError(
        getApiErrorMessage(
          error,
          "Unable to load attendance history. Please try again."
        )
      );
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    loadHistory();
  }, [loadHistory]);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    loadHistory({ silent: true });
  }, [loadHistory]);

  const handleClockIn = useCallback(() => {
    navigation.navigate("FaceVerification", {
      action: "clock_in",
    });
  }, [navigation]);

  const handleClockOut = useCallback(() => {
    navigation.navigate("FaceVerification", {
      action: "clock_out",
    });
  }, [navigation]);

  const navigateToFaceVerification = useCallback(
    (type) => {
      if (clockAction) return;

      setClockAction(type);

      try {
        if (type === "clock_in") {
          handleClockIn();
        } else {
          handleClockOut();
        }
      } finally {
        setClockAction(null);
      }
    },
    [clockAction, handleClockIn, handleClockOut]
  );

  const renderRecord = ({ item }) => {
    const isLate = item.status === "late";

    return (
      <View style={styles.recordCard}>
        <View style={styles.recordTopRow}>
          <View>
            <Text style={styles.recordDate}>{formatDate(item.date)}</Text>
            <Text style={styles.recordHours}>{formatHours(item.total_hours)}</Text>
          </View>

          <View
            style={[
              styles.statusBadge,
              isLate ? styles.lateBadge : styles.onTimeBadge,
            ]}
          >
            <Text
              style={[
                styles.statusText,
                isLate ? styles.lateText : styles.onTimeText,
              ]}
            >
              {isLate ? "Late" : "On-Time"}
            </Text>
          </View>
        </View>

        <View style={styles.timeGrid}>
          <View style={styles.timeBox}>
            <Text style={styles.timeLabel}>Time In</Text>
            <Text style={styles.timeValue}>{formatTime(item.time_in)}</Text>
          </View>
          <View style={styles.timeBox}>
            <Text style={styles.timeLabel}>Time Out</Text>
            <Text style={styles.timeValue}>{formatTime(item.time_out)}</Text>
          </View>
        </View>
      </View>
    );
  };

  const header = (
    <View>
      <View style={styles.hero}>
        <Text style={styles.eyebrow}>Attendance History</Text>
        <Text style={styles.title}>Your workday timeline</Text>
        <Text style={styles.subtitle}>
          Review your recent clock-ins, clock-outs, and total hours worked.
        </Text>
        <Text style={styles.company}>
          {userSessionData?.company?.name ?? "TimePay"}
        </Text>
      </View>

      <View style={styles.summaryRow}>
        <View style={styles.summaryCard}>
          <Text style={styles.summaryValue}>{summary.days}</Text>
          <Text style={styles.summaryLabel}>Days</Text>
        </View>
        <View style={styles.summaryCard}>
          <Text style={styles.summaryValue}>{summary.hours}</Text>
          <Text style={styles.summaryLabel}>Hours</Text>
        </View>
        <View style={styles.summaryCard}>
          <Text style={styles.summaryValue}>{summary.late}</Text>
          <Text style={styles.summaryLabel}>Late</Text>
        </View>
      </View>

      <View style={styles.actionPanel}>
        <Text style={styles.actionTitle}>Quick Attendance</Text>
        <Text style={styles.actionSubtitle}>
          Start with geofence validation, then continue to Face ID verification.
        </Text>

        <View style={styles.clockButtonRow}>
          <TouchableOpacity
            activeOpacity={0.9}
            disabled={Boolean(clockAction)}
            onPress={() => navigateToFaceVerification("clock_in")}
            style={[
              styles.clockButton,
              styles.clockInButton,
              clockAction && styles.disabledButton,
            ]}
          >
            {clockAction === "clock_in" ? (
              <ActivityIndicator color="#ffffff" />
            ) : (
              <>
                <Text style={styles.clockButtonText}>Clock In</Text>
                <Text style={styles.clockButtonSubtext}>Check location</Text>
              </>
            )}
          </TouchableOpacity>

          <TouchableOpacity
            activeOpacity={0.9}
            disabled={Boolean(clockAction)}
            onPress={() => navigateToFaceVerification("clock_out")}
            style={[
              styles.clockButton,
              styles.clockOutButton,
              clockAction && styles.disabledButton,
            ]}
          >
            {clockAction === "clock_out" ? (
              <ActivityIndicator color="#ffffff" />
            ) : (
              <>
                <Text style={styles.clockButtonText}>Clock Out</Text>
                <Text style={styles.clockButtonSubtext}>Verify Face ID</Text>
              </>
            )}
          </TouchableOpacity>
        </View>
      </View>

      {screenError ? (
        <View style={styles.alert}>
          <Text style={styles.alertTitle}>History unavailable</Text>
          <Text style={styles.alertText}>{screenError}</Text>
        </View>
      ) : null}

      <View style={styles.sectionHeader}>
        <Text style={styles.sectionTitle}>Recent records</Text>
        <Text style={styles.sectionCount}>{records.length} entries</Text>
      </View>
    </View>
  );

  if (loading) {
    return (
      <SafeAreaView style={styles.safeArea}>
        <View style={styles.centeredState}>
          <ActivityIndicator color="#4f46e5" size="large" />
          <Text style={styles.centeredText}>Loading attendance history...</Text>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <FlatList
        data={records}
        keyExtractor={(item, index) => String(item.id ?? item.date ?? index)}
        renderItem={renderRecord}
        ListHeaderComponent={header}
        ListEmptyComponent={
          <View style={styles.emptyState}>
            <Text style={styles.emptyTitle}>No attendance records yet</Text>
            <Text style={styles.emptyText}>
              Once you clock in and out, your history will appear here.
            </Text>
          </View>
        }
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        showsVerticalScrollIndicator={false}
      />
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: "#f8fafc",
  },
  listContent: {
    paddingHorizontal: 20,
    paddingBottom: 28,
  },
  hero: {
    paddingTop: 22,
    paddingBottom: 18,
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
  company: {
    marginTop: 12,
    color: "#334155",
    fontSize: 14,
    fontWeight: "800",
  },
  summaryRow: {
    flexDirection: "row",
    gap: 10,
    marginBottom: 18,
  },
  actionPanel: {
    borderRadius: 24,
    borderWidth: 1,
    borderColor: "#e2e8f0",
    backgroundColor: "#ffffff",
    padding: 16,
    marginBottom: 18,
    shadowColor: "#0f172a",
    shadowOffset: { width: 0, height: 14 },
    shadowOpacity: 0.07,
    shadowRadius: 20,
    elevation: 3,
  },
  actionTitle: {
    color: "#0f172a",
    fontSize: 18,
    fontWeight: "900",
  },
  actionSubtitle: {
    marginTop: 6,
    color: "#64748b",
    fontSize: 13,
    lineHeight: 19,
  },
  clockButtonRow: {
    flexDirection: "row",
    gap: 12,
    marginTop: 15,
  },
  clockButton: {
    flex: 1,
    minHeight: 82,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 18,
    paddingHorizontal: 12,
  },
  clockInButton: {
    backgroundColor: "#059669",
  },
  clockOutButton: {
    backgroundColor: "#1f2937",
  },
  clockButtonText: {
    color: "#ffffff",
    fontSize: 18,
    fontWeight: "900",
  },
  clockButtonSubtext: {
    marginTop: 5,
    color: "rgba(255,255,255,0.76)",
    fontSize: 12,
    fontWeight: "800",
  },
  disabledButton: {
    opacity: 0.64,
  },
  summaryCard: {
    flex: 1,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: "#e2e8f0",
    backgroundColor: "#ffffff",
    padding: 14,
  },
  summaryValue: {
    color: "#0f172a",
    fontSize: 22,
    fontWeight: "900",
  },
  summaryLabel: {
    marginTop: 4,
    color: "#64748b",
    fontSize: 12,
    fontWeight: "800",
  },
  sectionHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 12,
  },
  sectionTitle: {
    color: "#0f172a",
    fontSize: 18,
    fontWeight: "900",
  },
  sectionCount: {
    color: "#64748b",
    fontSize: 12,
    fontWeight: "800",
  },
  recordCard: {
    borderRadius: 20,
    borderWidth: 1,
    borderColor: "#e2e8f0",
    backgroundColor: "#ffffff",
    padding: 16,
    marginBottom: 12,
  },
  recordTopRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    gap: 12,
  },
  recordDate: {
    color: "#0f172a",
    fontSize: 16,
    fontWeight: "900",
  },
  recordHours: {
    marginTop: 4,
    color: "#64748b",
    fontSize: 13,
    fontWeight: "800",
  },
  statusBadge: {
    alignSelf: "flex-start",
    borderRadius: 999,
    borderWidth: 1,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  onTimeBadge: {
    backgroundColor: "#ecfdf5",
    borderColor: "#a7f3d0",
  },
  lateBadge: {
    backgroundColor: "#fef2f2",
    borderColor: "#fecaca",
  },
  statusText: {
    fontSize: 11,
    fontWeight: "900",
    textTransform: "uppercase",
  },
  onTimeText: {
    color: "#047857",
  },
  lateText: {
    color: "#b91c1c",
  },
  timeGrid: {
    flexDirection: "row",
    gap: 10,
    marginTop: 14,
  },
  timeBox: {
    flex: 1,
    borderRadius: 14,
    backgroundColor: "#f8fafc",
    padding: 12,
  },
  timeLabel: {
    color: "#64748b",
    fontSize: 11,
    fontWeight: "900",
    textTransform: "uppercase",
  },
  timeValue: {
    marginTop: 6,
    color: "#0f172a",
    fontSize: 15,
    fontWeight: "900",
  },
  alert: {
    marginBottom: 16,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: "#fecaca",
    backgroundColor: "#fef2f2",
    padding: 14,
  },
  alertTitle: {
    color: "#991b1b",
    fontSize: 14,
    fontWeight: "900",
  },
  alertText: {
    marginTop: 4,
    color: "#b91c1c",
    fontSize: 13,
    lineHeight: 19,
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
  emptyState: {
    alignItems: "center",
    borderRadius: 18,
    borderWidth: 1,
    borderColor: "#e2e8f0",
    backgroundColor: "#ffffff",
    padding: 22,
  },
  emptyTitle: {
    color: "#0f172a",
    fontSize: 16,
    fontWeight: "900",
  },
  emptyText: {
    marginTop: 6,
    color: "#64748b",
    fontSize: 14,
    lineHeight: 20,
    textAlign: "center",
  },
});

export default AttendanceHistoryScreen;
