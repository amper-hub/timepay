import React, { useCallback, useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  RefreshControl,
  SafeAreaView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from "react-native";
import leaveService, { fetchLeaves, submitLeave } from "../../services/leaveService";
import { getApiErrorMessage } from "../../services/api";

const LEAVE_TYPES = ["Sick", "Vacation", "Emergency", "Unpaid"];

const initialForm = {
  leave_type: "Sick",
  start_date: "",
  end_date: "",
  reason: "",
};

const statusStyles = {
  pending: {
    backgroundColor: "#fff7ed",
    color: "#c2410c",
    borderColor: "#fed7aa",
  },
  approved: {
    backgroundColor: "#ecfdf5",
    color: "#047857",
    borderColor: "#a7f3d0",
  },
  rejected: {
    backgroundColor: "#fef2f2",
    color: "#b91c1c",
    borderColor: "#fecaca",
  },
};

const formatDate = (value) => {
  if (!value) return "—";

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return date.toLocaleDateString(undefined, {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
};

const isIsoDate = (value) => /^\d{4}-\d{2}-\d{2}$/.test(value);

const LeaveScreen = ({ userSessionData }) => {
  const [leaves, setLeaves] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [modalVisible, setModalVisible] = useState(false);
  const [typePickerVisible, setTypePickerVisible] = useState(false);
  const [form, setForm] = useState(initialForm);
  const [screenError, setScreenError] = useState(null);

  const balances = useMemo(
    () => [
      { label: "Sick Leave", remaining: 8, total: 10, color: "#4f46e5" },
      { label: "Vacation Leave", remaining: 12, total: 15, color: "#0891b2" },
    ],
    []
  );

  const loadLeaves = useCallback(async ({ silent = false } = {}) => {
    if (!silent) setLoading(true);
    setScreenError(null);

    try {
      const data = await fetchLeaves();
      setLeaves(data);
    } catch (error) {
      setScreenError(
        getApiErrorMessage(
          error,
          "Unable to load leave requests. Please try again."
        )
      );
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    loadLeaves();
  }, [loadLeaves]);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    loadLeaves({ silent: true });
  }, [loadLeaves]);

  const openModal = useCallback(() => {
    setForm(initialForm);
    setModalVisible(true);
  }, []);

  const closeModal = useCallback(() => {
    if (!submitting) {
      setModalVisible(false);
      setTypePickerVisible(false);
    }
  }, [submitting]);

  const updateField = useCallback((field, value) => {
    setForm((current) => ({ ...current, [field]: value }));
  }, []);

  const validateForm = useCallback(() => {
    if (!LEAVE_TYPES.includes(form.leave_type)) {
      return "Please select a valid leave type.";
    }

    if (!isIsoDate(form.start_date) || !isIsoDate(form.end_date)) {
      return "Please enter dates using YYYY-MM-DD format.";
    }

    if (new Date(form.end_date) < new Date(form.start_date)) {
      return "End date must be the same as or later than the start date.";
    }

    if (form.reason.trim().length < 5) {
      return "Please add a short reason for your leave request.";
    }

    return null;
  }, [form]);

  const handleSubmit = useCallback(async () => {
    const validationError = validateForm();

    if (validationError) {
      Alert.alert("Check your request", validationError);
      return;
    }

    setSubmitting(true);

    try {
      const createdLeave = await submitLeave({
        ...form,
        reason: form.reason.trim(),
      });

      setLeaves((current) => [createdLeave, ...current]);
      setModalVisible(false);
      setForm(initialForm);
      Alert.alert("Request submitted", "Your leave request is now pending.");
    } catch (error) {
      Alert.alert(
        "Unable to submit",
        getApiErrorMessage(
          error,
          "Unable to submit your leave request. Please try again."
        )
      );
    } finally {
      setSubmitting(false);
    }
  }, [form, validateForm]);

  const renderLeave = ({ item }) => {
    const status = (item.status || "pending").toLowerCase();
    const badge = statusStyles[status] ?? statusStyles.pending;

    return (
      <View style={styles.historyCard}>
        <View style={styles.historyTopRow}>
          <View>
            <Text style={styles.leaveType}>{item.leave_type}</Text>
            <Text style={styles.leaveDates}>
              {formatDate(item.start_date)} - {formatDate(item.end_date)}
            </Text>
          </View>

          <View
            style={[
              styles.statusBadge,
              { backgroundColor: badge.backgroundColor, borderColor: badge.borderColor },
            ]}
          >
            <Text style={[styles.statusText, { color: badge.color }]}>
              {status}
            </Text>
          </View>
        </View>

        <Text style={styles.reasonText} numberOfLines={3}>
          {item.reason}
        </Text>

        {item.admin_notes ? (
          <View style={styles.adminNote}>
            <Text style={styles.adminNoteLabel}>Admin note</Text>
            <Text style={styles.adminNoteText}>{item.admin_notes}</Text>
          </View>
        ) : null}
      </View>
    );
  };

  const listHeader = (
    <View>
      <View style={styles.header}>
        <Text style={styles.eyebrow}>TimePay Leave</Text>
        <Text style={styles.title}>Plan time away without the paperwork fog.</Text>
        <Text style={styles.subtitle}>
          Submit requests, track approvals, and keep your leave history tidy.
        </Text>
        <Text style={styles.employeeName}>
          {userSessionData?.user?.name ?? "Employee"}
        </Text>
      </View>

      <View style={styles.balanceRow}>
        {balances.map((balance) => (
          <View key={balance.label} style={styles.balanceCard}>
            <View
              style={[
                styles.balanceIcon,
                { backgroundColor: `${balance.color}18` },
              ]}
            >
              <Text style={[styles.balanceIconText, { color: balance.color }]}>
                {balance.remaining}
              </Text>
            </View>
            <Text style={styles.balanceLabel}>{balance.label}</Text>
            <Text style={styles.balanceMeta}>
              {balance.remaining} of {balance.total} days left
            </Text>
          </View>
        ))}
      </View>

      {screenError ? (
        <View style={styles.alert}>
          <Text style={styles.alertTitle}>Couldn’t load leaves</Text>
          <Text style={styles.alertText}>{screenError}</Text>
        </View>
      ) : null}

      <TouchableOpacity
        activeOpacity={0.9}
        onPress={openModal}
        style={styles.requestButton}
      >
        <Text style={styles.requestButtonText}>Request Leave</Text>
        <Text style={styles.requestButtonSubtext}>Sick, vacation, emergency, or unpaid</Text>
      </TouchableOpacity>

      <View style={styles.sectionHeader}>
        <Text style={styles.sectionTitle}>Leave History</Text>
        <Text style={styles.sectionCount}>{leaves.length} requests</Text>
      </View>
    </View>
  );

  return (
    <SafeAreaView style={styles.safeArea}>
      {loading ? (
        <View style={styles.centeredState}>
          <ActivityIndicator size="large" color="#4f46e5" />
          <Text style={styles.centeredText}>Loading leave history...</Text>
        </View>
      ) : (
        <FlatList
          data={leaves}
          keyExtractor={(item, index) => String(item.id ?? index)}
          renderItem={renderLeave}
          ListHeaderComponent={listHeader}
          ListEmptyComponent={
            <View style={styles.emptyState}>
              <Text style={styles.emptyTitle}>No leave requests yet</Text>
              <Text style={styles.emptyText}>
                Your submitted requests will appear here once you send them.
              </Text>
            </View>
          }
          contentContainerStyle={styles.listContent}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
          }
          showsVerticalScrollIndicator={false}
        />
      )}

      <Modal
        visible={modalVisible}
        transparent
        animationType="slide"
        onRequestClose={closeModal}
      >
        <KeyboardAvoidingView
          behavior={Platform.OS === "ios" ? "padding" : undefined}
          style={styles.modalOverlay}
        >
          <Pressable style={styles.modalBackdrop} onPress={closeModal} />

          <View style={styles.modalCard}>
            <View style={styles.modalHandle} />
            <Text style={styles.modalTitle}>Request Leave</Text>
            <Text style={styles.modalSubtitle}>
              Dates use YYYY-MM-DD format, like 2026-08-20.
            </Text>

            <Text style={styles.inputLabel}>Leave type</Text>
            <TouchableOpacity
              style={styles.selectInput}
              activeOpacity={0.85}
              onPress={() => setTypePickerVisible(true)}
            >
              <Text style={styles.selectText}>{form.leave_type}</Text>
              <Text style={styles.chevron}>⌄</Text>
            </TouchableOpacity>

            <View style={styles.dateRow}>
              <View style={styles.dateColumn}>
                <Text style={styles.inputLabel}>Start date</Text>
                <TextInput
                  value={form.start_date}
                  onChangeText={(value) => updateField("start_date", value)}
                  placeholder="YYYY-MM-DD"
                  placeholderTextColor="#94a3b8"
                  style={styles.textInput}
                  autoCapitalize="none"
                />
              </View>

              <View style={styles.dateColumn}>
                <Text style={styles.inputLabel}>End date</Text>
                <TextInput
                  value={form.end_date}
                  onChangeText={(value) => updateField("end_date", value)}
                  placeholder="YYYY-MM-DD"
                  placeholderTextColor="#94a3b8"
                  style={styles.textInput}
                  autoCapitalize="none"
                />
              </View>
            </View>

            <Text style={styles.inputLabel}>Reason</Text>
            <TextInput
              value={form.reason}
              onChangeText={(value) => updateField("reason", value)}
              placeholder="Tell your manager why you need leave..."
              placeholderTextColor="#94a3b8"
              style={[styles.textInput, styles.reasonInput]}
              multiline
              textAlignVertical="top"
            />

            <View style={styles.modalActions}>
              <TouchableOpacity
                disabled={submitting}
                onPress={closeModal}
                style={styles.cancelButton}
              >
                <Text style={styles.cancelButtonText}>Cancel</Text>
              </TouchableOpacity>

              <TouchableOpacity
                disabled={submitting}
                onPress={handleSubmit}
                style={[styles.submitButton, submitting && styles.disabledButton]}
              >
                {submitting ? (
                  <ActivityIndicator color="#ffffff" />
                ) : (
                  <Text style={styles.submitButtonText}>Submit</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </KeyboardAvoidingView>
      </Modal>

      <Modal
        visible={typePickerVisible}
        transparent
        animationType="fade"
        onRequestClose={() => setTypePickerVisible(false)}
      >
        <Pressable
          style={styles.pickerOverlay}
          onPress={() => setTypePickerVisible(false)}
        >
          <View style={styles.pickerCard}>
            <Text style={styles.pickerTitle}>Choose leave type</Text>
            {LEAVE_TYPES.map((type) => (
              <TouchableOpacity
                key={type}
                style={[
                  styles.pickerOption,
                  form.leave_type === type && styles.pickerOptionActive,
                ]}
                onPress={() => {
                  updateField("leave_type", type);
                  setTypePickerVisible(false);
                }}
              >
                <Text
                  style={[
                    styles.pickerOptionText,
                    form.leave_type === type && styles.pickerOptionTextActive,
                  ]}
                >
                  {type}
                </Text>
              </TouchableOpacity>
            ))}
          </View>
        </Pressable>
      </Modal>
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
  header: {
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
    fontSize: 30,
    fontWeight: "900",
    lineHeight: 36,
  },
  subtitle: {
    marginTop: 10,
    color: "#64748b",
    fontSize: 15,
    lineHeight: 22,
  },
  employeeName: {
    marginTop: 14,
    color: "#334155",
    fontSize: 14,
    fontWeight: "800",
  },
  balanceRow: {
    flexDirection: "row",
    gap: 12,
    marginBottom: 18,
  },
  balanceCard: {
    flex: 1,
    borderRadius: 22,
    borderWidth: 1,
    borderColor: "#e2e8f0",
    backgroundColor: "#ffffff",
    padding: 16,
    shadowColor: "#0f172a",
    shadowOffset: { width: 0, height: 12 },
    shadowOpacity: 0.06,
    shadowRadius: 18,
    elevation: 2,
  },
  balanceIcon: {
    width: 48,
    height: 48,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 16,
  },
  balanceIconText: {
    fontSize: 20,
    fontWeight: "900",
  },
  balanceLabel: {
    marginTop: 14,
    color: "#0f172a",
    fontSize: 15,
    fontWeight: "900",
  },
  balanceMeta: {
    marginTop: 5,
    color: "#64748b",
    fontSize: 12,
    fontWeight: "700",
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
  requestButton: {
    borderRadius: 20,
    backgroundColor: "#4f46e5",
    paddingHorizontal: 18,
    paddingVertical: 18,
    shadowColor: "#4f46e5",
    shadowOffset: { width: 0, height: 14 },
    shadowOpacity: 0.22,
    shadowRadius: 20,
    elevation: 4,
  },
  requestButtonText: {
    color: "#ffffff",
    fontSize: 18,
    fontWeight: "900",
  },
  requestButtonSubtext: {
    marginTop: 4,
    color: "rgba(255,255,255,0.76)",
    fontSize: 12,
    fontWeight: "700",
  },
  sectionHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginTop: 26,
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
  historyCard: {
    borderRadius: 18,
    borderWidth: 1,
    borderColor: "#e2e8f0",
    backgroundColor: "#ffffff",
    padding: 16,
    marginBottom: 12,
  },
  historyTopRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    gap: 12,
  },
  leaveType: {
    color: "#0f172a",
    fontSize: 16,
    fontWeight: "900",
  },
  leaveDates: {
    marginTop: 4,
    color: "#64748b",
    fontSize: 13,
    fontWeight: "700",
  },
  statusBadge: {
    alignSelf: "flex-start",
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  statusText: {
    fontSize: 11,
    fontWeight: "900",
    textTransform: "uppercase",
  },
  reasonText: {
    marginTop: 12,
    color: "#475569",
    fontSize: 14,
    lineHeight: 20,
  },
  adminNote: {
    marginTop: 12,
    borderRadius: 14,
    backgroundColor: "#f1f5f9",
    padding: 12,
  },
  adminNoteLabel: {
    color: "#334155",
    fontSize: 12,
    fontWeight: "900",
    textTransform: "uppercase",
  },
  adminNoteText: {
    marginTop: 4,
    color: "#475569",
    fontSize: 13,
    lineHeight: 18,
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
  modalOverlay: {
    flex: 1,
    justifyContent: "flex-end",
  },
  modalBackdrop: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: "rgba(15, 23, 42, 0.42)",
  },
  modalCard: {
    maxHeight: "88%",
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    backgroundColor: "#ffffff",
    paddingHorizontal: 20,
    paddingTop: 12,
    paddingBottom: 24,
  },
  modalHandle: {
    alignSelf: "center",
    width: 44,
    height: 5,
    borderRadius: 999,
    backgroundColor: "#cbd5e1",
    marginBottom: 18,
  },
  modalTitle: {
    color: "#0f172a",
    fontSize: 24,
    fontWeight: "900",
  },
  modalSubtitle: {
    marginTop: 6,
    marginBottom: 18,
    color: "#64748b",
    fontSize: 13,
    lineHeight: 19,
  },
  inputLabel: {
    marginBottom: 8,
    color: "#334155",
    fontSize: 13,
    fontWeight: "900",
  },
  selectInput: {
    minHeight: 50,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    borderRadius: 14,
    borderWidth: 1,
    borderColor: "#cbd5e1",
    backgroundColor: "#f8fafc",
    paddingHorizontal: 14,
    marginBottom: 14,
  },
  selectText: {
    color: "#0f172a",
    fontSize: 15,
    fontWeight: "800",
  },
  chevron: {
    color: "#64748b",
    fontSize: 20,
    fontWeight: "900",
  },
  dateRow: {
    flexDirection: "row",
    gap: 12,
  },
  dateColumn: {
    flex: 1,
  },
  textInput: {
    minHeight: 50,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: "#cbd5e1",
    backgroundColor: "#f8fafc",
    paddingHorizontal: 14,
    color: "#0f172a",
    fontSize: 15,
    fontWeight: "700",
    marginBottom: 14,
  },
  reasonInput: {
    minHeight: 112,
    paddingTop: 13,
  },
  modalActions: {
    flexDirection: "row",
    gap: 12,
    marginTop: 4,
  },
  cancelButton: {
    flex: 1,
    alignItems: "center",
    borderRadius: 14,
    borderWidth: 1,
    borderColor: "#cbd5e1",
    paddingVertical: 14,
  },
  cancelButtonText: {
    color: "#334155",
    fontSize: 15,
    fontWeight: "900",
  },
  submitButton: {
    flex: 1,
    alignItems: "center",
    borderRadius: 14,
    backgroundColor: "#4f46e5",
    paddingVertical: 14,
  },
  submitButtonText: {
    color: "#ffffff",
    fontSize: 15,
    fontWeight: "900",
  },
  disabledButton: {
    opacity: 0.65,
  },
  pickerOverlay: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "rgba(15, 23, 42, 0.42)",
    padding: 24,
  },
  pickerCard: {
    width: "100%",
    borderRadius: 22,
    backgroundColor: "#ffffff",
    padding: 16,
  },
  pickerTitle: {
    color: "#0f172a",
    fontSize: 18,
    fontWeight: "900",
    marginBottom: 10,
  },
  pickerOption: {
    borderRadius: 14,
    paddingHorizontal: 14,
    paddingVertical: 14,
  },
  pickerOptionActive: {
    backgroundColor: "#eef2ff",
  },
  pickerOptionText: {
    color: "#334155",
    fontSize: 15,
    fontWeight: "800",
  },
  pickerOptionTextActive: {
    color: "#4f46e5",
  },
});

export default LeaveScreen;
