import React, { useCallback, useState } from "react";
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
import { BottomTabScreenProps } from "@react-navigation/bottom-tabs";
import { useFocusEffect } from "@react-navigation/native";
import { EmployeeTabParamList } from "../navigation/AppNavigator";
import { getApiErrorMessage } from "../services/api";
import {
  downloadAndSharePayslip,
  getPayslips,
  getYearToDatePayroll,
} from "../services/payrollService";
import { colors } from "../theme/colors";
import { PayrollLine, PayrollPayslip, PayrollYearToDate } from "../types";

type PayrollScreenProps = BottomTabScreenProps<EmployeeTabParamList, "Payroll">;

const PayrollScreen: React.FC<PayrollScreenProps> = () => {
  const [payslips, setPayslips] = useState<PayrollPayslip[]>([]);
  const [yearToDate, setYearToDate] = useState<PayrollYearToDate | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [downloadingId, setDownloadingId] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const loadPayroll = useCallback(async (isRefresh = false) => {
    if (isRefresh) setRefreshing(true);
    else setLoading(true);
    setErrorMessage(null);

    try {
      const [slips, summary] = await Promise.all([
        getPayslips(),
        getYearToDatePayroll(),
      ]);
      setPayslips(slips as PayrollPayslip[]);
      setYearToDate(summary as PayrollYearToDate);
    } catch (error) {
      setErrorMessage(
        getApiErrorMessage(
          error,
          "Payroll is unavailable offline. Reconnect and pull down to refresh.",
          "payroll"
        )
      );
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      void loadPayroll();
    }, [loadPayroll])
  );

  const handleDownload = async (payslip: PayrollPayslip) => {
    setDownloadingId(payslip.id);
    setErrorMessage(null);
    try {
      await downloadAndSharePayslip(payslip.id);
    } catch (error) {
      setErrorMessage(
        getApiErrorMessage(error, "Unable to open this payslip.", "payroll")
      );
    } finally {
      setDownloadingId(null);
    }
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.safeArea}>
        <View style={styles.centeredState}>
          <ActivityIndicator color={colors.primary} size="large" />
          <Text style={styles.mutedText}>Loading your payroll…</Text>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <ScrollView
        contentContainerStyle={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={() => loadPayroll(true)} />
        }
      >
        <View style={styles.header}>
          <Text style={styles.eyebrow}>Employee portal</Text>
          <Text style={styles.title}>My Payroll</Text>
          <Text style={styles.subtitle}>
            Finalized payroll snapshots and employer-recorded payment status.
          </Text>
        </View>

        {errorMessage ? (
          <View style={styles.errorCard} accessibilityRole="alert">
            <Text style={styles.errorTitle}>Payroll could not be refreshed</Text>
            <Text style={styles.errorText}>{errorMessage}</Text>
            <TouchableOpacity
              accessibilityRole="button"
              onPress={() => void loadPayroll(true)}
              style={styles.retryButton}
            >
              <Text style={styles.retryButtonText}>Try again</Text>
            </TouchableOpacity>
          </View>
        ) : null}

        {yearToDate ? (
          <View style={styles.summaryCard}>
            <Text style={styles.sectionEyebrow}>{yearToDate.year} year to date</Text>
            <Text style={styles.summaryAmount}>{yearToDate.formatted_net_pay}</Text>
            <Text style={styles.summaryCaption}>Net pay in approved payroll periods</Text>
            <View style={styles.summaryGrid}>
              <SummaryValue label="Gross pay" value={yearToDate.formatted_gross_pay} />
              <SummaryValue label="Deductions" value={yearToDate.formatted_employee_deductions} />
              <SummaryValue label="Employer contributions" value={yearToDate.formatted_employer_contributions} />
              <SummaryValue label="Taxable compensation" value={yearToDate.formatted_taxable_compensation} />
            </View>
          </View>
        ) : null}

        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Payslips</Text>
          <Text style={styles.sectionCount}>{payslips.length}</Text>
        </View>

        {payslips.length === 0 ? (
          <View style={styles.emptyCard}>
            <Text style={styles.emptyTitle}>No finalized payslips yet</Text>
            <Text style={styles.mutedText}>
              Approved payroll periods will appear here after your employer completes a calculation.
            </Text>
          </View>
        ) : (
          payslips.map((payslip) => (
            <PayslipCard
              key={payslip.id}
              payslip={payslip}
              downloading={downloadingId === payslip.id}
              onDownload={() => void handleDownload(payslip)}
            />
          ))
        )}
      </ScrollView>
    </SafeAreaView>
  );
};

const SummaryValue: React.FC<{ label: string; value: string }> = ({ label, value }) => (
  <View style={styles.summaryValue}>
    <Text style={styles.summaryLabel}>{label}</Text>
    <Text style={styles.summaryValueText}>{value}</Text>
  </View>
);

const PayslipCard: React.FC<{
  payslip: PayrollPayslip;
  downloading: boolean;
  onDownload: () => void;
}> = ({ payslip, downloading, onDownload }) => (
  <View style={styles.payslipCard}>
    <View style={styles.payslipHeader}>
      <View style={styles.payslipHeading}>
        <Text style={styles.periodTitle}>{payslip.pay_period}</Text>
        <Text style={styles.periodMeta}>
          {payslip.pay_frequency.replaceAll("_", " ")} payout
          {payslip.pay_date ? ` · Pay date ${payslip.pay_date}` : ""}
        </Text>
      </View>
      <View style={[styles.statusPill, statusStyle(payslip.payment_status)]}>
        <Text style={[styles.statusText, statusTextStyle(payslip.payment_status)]}>
          {labelStatus(payslip.payment_status)}
        </Text>
      </View>
    </View>

    <View style={styles.metricsRow}>
      <Metric label="Gross" value={payslip.formatted_gross_pay} />
      <Metric label="Deductions" value={payslip.formatted_deductions} />
      <Metric label="Net pay" value={payslip.formatted_net_pay} highlight />
    </View>

    <View style={styles.activitySummary}>
      <Text style={styles.activityText}>{payslip.regular_hours.toFixed(2)} hours</Text>
      <Text style={styles.dot}>·</Text>
      <Text style={styles.activityText}>{payslip.payable_days} payable days</Text>
    </View>

    <PayrollLines title="Earnings" lines={payslip.earnings} />
    <PayrollLines title="Employee deductions" lines={payslip.deduction_lines} />
    <PayrollLines title="Employer contributions" lines={payslip.employer_contribution_lines} />

    <View style={styles.paymentSummary}>
      <Text style={styles.paymentSummaryLabel}>Recorded as paid</Text>
      <Text style={styles.paymentSummaryValue}>{payslip.formatted_amount_paid}</Text>
      <Text style={styles.paymentSummaryLabel}>Remaining balance</Text>
      <Text style={styles.paymentSummaryValue}>{payslip.formatted_remaining_balance}</Text>
      <Text style={styles.paymentNote}>
        Payment status reflects employer records. TimePay does not transfer money.
      </Text>
    </View>

    {payslip.review_flags.length > 0 ? (
      <View style={styles.reviewBox}>
        <Text style={styles.reviewTitle}>Payroll review notes</Text>
        {payslip.review_flags.map((flag) => (
          <Text key={flag} style={styles.reviewText}>• {flag.replaceAll("_", " ")}</Text>
        ))}
      </View>
    ) : null}

    <TouchableOpacity
      accessibilityRole="button"
      accessibilityLabel={`Download payslip for ${payslip.pay_period}`}
      disabled={downloading}
      onPress={onDownload}
      style={[styles.downloadButton, downloading && styles.buttonDisabled]}
    >
      {downloading ? (
        <ActivityIndicator color={colors.white} />
      ) : (
        <Text style={styles.downloadButtonText}>View or share PDF payslip</Text>
      )}
    </TouchableOpacity>
  </View>
);

const Metric: React.FC<{ label: string; value: string; highlight?: boolean }> = ({
  label,
  value,
  highlight = false,
}) => (
  <View style={styles.metric}>
    <Text style={styles.metricLabel}>{label}</Text>
    <Text style={[styles.metricValue, highlight && styles.netValue]}>{value}</Text>
  </View>
);

const PayrollLines: React.FC<{ title: string; lines: PayrollLine[] }> = ({ title, lines }) => (
  <View style={styles.linesSection}>
    <Text style={styles.linesTitle}>{title}</Text>
    {lines.length === 0 ? (
      <Text style={styles.noLines}>No {title.toLowerCase()} recorded.</Text>
    ) : (
      lines.map((line) => (
        <View key={`${line.category}:${line.code}:${line.label}`} style={styles.lineRow}>
          <Text style={styles.lineLabel}>{line.label}</Text>
          <Text style={styles.lineAmount}>{line.formatted_amount}</Text>
        </View>
      ))
    )}
  </View>
);

const labelStatus = (status: string): string =>
  status === "no_payment_due"
    ? "No payment due"
    : status.replaceAll("_", " ").replace(/\b\w/g, (letter) => letter.toUpperCase());

const statusStyle = (status: string) =>
  status === "paid" || status === "no_payment_due"
    ? styles.paidPill
    : status === "partially_paid"
      ? styles.partialPill
      : styles.unpaidPill;

const statusTextStyle = (status: string) =>
  status === "paid" || status === "no_payment_due"
    ? styles.paidText
    : status === "partially_paid"
      ? styles.partialText
      : styles.unpaidText;

const styles = StyleSheet.create({
  safeArea: { flex: 1, backgroundColor: colors.slate50 },
  content: { padding: 18, paddingBottom: 36, gap: 14 },
  centeredState: { flex: 1, alignItems: "center", justifyContent: "center", gap: 12, padding: 24 },
  mutedText: { color: colors.slate500, fontSize: 14, lineHeight: 20, textAlign: "center" },
  header: { paddingTop: 8, paddingBottom: 4 },
  eyebrow: { color: colors.primary, fontSize: 12, fontWeight: "800", letterSpacing: 1, textTransform: "uppercase" },
  title: { color: colors.slate900, fontSize: 28, fontWeight: "800", marginTop: 5 },
  subtitle: { color: colors.slate500, fontSize: 14, lineHeight: 20, marginTop: 5 },
  summaryCard: { backgroundColor: colors.primaryDark, borderRadius: 20, padding: 18 },
  sectionEyebrow: { color: "#D1FAE5", fontSize: 12, fontWeight: "800", letterSpacing: 0.8, textTransform: "uppercase" },
  summaryAmount: { color: colors.white, fontSize: 30, fontWeight: "800", marginTop: 8 },
  summaryCaption: { color: "#D1FAE5", fontSize: 12, marginTop: 2 },
  summaryGrid: { borderTopColor: "rgba(255,255,255,0.25)", borderTopWidth: 1, flexDirection: "row", flexWrap: "wrap", marginTop: 15, paddingTop: 12, rowGap: 14 },
  summaryValue: { width: "50%", paddingRight: 8 },
  summaryLabel: { color: "#D1FAE5", fontSize: 11 },
  summaryValueText: { color: colors.white, fontSize: 14, fontWeight: "700", marginTop: 3 },
  sectionHeader: { alignItems: "center", flexDirection: "row", gap: 8, marginTop: 4 },
  sectionTitle: { color: colors.slate900, fontSize: 19, fontWeight: "800" },
  sectionCount: { backgroundColor: colors.emeraldSoft, borderRadius: 12, color: colors.primaryDark, fontSize: 12, fontWeight: "800", overflow: "hidden", paddingHorizontal: 8, paddingVertical: 3 },
  emptyCard: { alignItems: "center", backgroundColor: colors.white, borderColor: colors.slate100, borderRadius: 16, borderWidth: 1, padding: 22, gap: 8 },
  emptyTitle: { color: colors.slate900, fontSize: 16, fontWeight: "700" },
  errorCard: { backgroundColor: "#FEF2F2", borderColor: "#FECACA", borderRadius: 14, borderWidth: 1, padding: 14 },
  errorTitle: { color: "#991B1B", fontSize: 14, fontWeight: "800" },
  errorText: { color: "#7F1D1D", fontSize: 13, lineHeight: 18, marginTop: 4 },
  retryButton: { alignSelf: "flex-start", borderColor: "#FCA5A5", borderRadius: 9, borderWidth: 1, marginTop: 10, paddingHorizontal: 12, paddingVertical: 8 },
  retryButtonText: { color: "#991B1B", fontSize: 13, fontWeight: "700" },
  payslipCard: { backgroundColor: colors.white, borderColor: colors.slate100, borderRadius: 18, borderWidth: 1, padding: 15, gap: 13 },
  payslipHeader: { alignItems: "flex-start", flexDirection: "row", justifyContent: "space-between", gap: 8 },
  payslipHeading: { flex: 1 },
  periodTitle: { color: colors.slate900, fontSize: 15, fontWeight: "800" },
  periodMeta: { color: colors.slate500, fontSize: 12, marginTop: 4, textTransform: "capitalize" },
  statusPill: { borderRadius: 12, paddingHorizontal: 9, paddingVertical: 5 },
  statusText: { fontSize: 10, fontWeight: "800", textTransform: "capitalize" },
  paidPill: { backgroundColor: colors.emeraldSoft },
  paidText: { color: colors.primaryDark },
  partialPill: { backgroundColor: "#FEF3C7" },
  partialText: { color: "#92400E" },
  unpaidPill: { backgroundColor: "#F1F5F9" },
  unpaidText: { color: colors.slate500 },
  metricsRow: { backgroundColor: colors.slate50, borderRadius: 12, flexDirection: "row", gap: 5, padding: 10 },
  metric: { flex: 1 },
  metricLabel: { color: colors.slate500, fontSize: 10 },
  metricValue: { color: colors.slate900, fontSize: 12, fontWeight: "700", marginTop: 4 },
  netValue: { color: colors.primaryDark },
  activitySummary: { alignItems: "center", flexDirection: "row", gap: 8 },
  activityText: { color: colors.slate500, fontSize: 12 },
  dot: { color: colors.slate300 },
  linesSection: { borderTopColor: colors.slate100, borderTopWidth: 1, paddingTop: 10, gap: 6 },
  linesTitle: { color: colors.slate900, fontSize: 12, fontWeight: "800" },
  noLines: { color: colors.slate500, fontSize: 12 },
  lineRow: { alignItems: "flex-start", flexDirection: "row", justifyContent: "space-between", gap: 12 },
  lineLabel: { color: colors.slate500, flex: 1, fontSize: 12 },
  lineAmount: { color: colors.slate900, fontSize: 12, fontWeight: "600" },
  paymentSummary: { backgroundColor: colors.slate50, borderRadius: 12, flexDirection: "row", flexWrap: "wrap", padding: 10, rowGap: 5 },
  paymentSummaryLabel: { color: colors.slate500, fontSize: 11, width: "55%" },
  paymentSummaryValue: { color: colors.slate900, fontSize: 11, fontWeight: "700", textAlign: "right", width: "45%" },
  paymentNote: { color: colors.slate500, fontSize: 10, lineHeight: 14, marginTop: 5, width: "100%" },
  reviewBox: { backgroundColor: "#FFFBEB", borderColor: "#FDE68A", borderRadius: 11, borderWidth: 1, padding: 10, gap: 4 },
  reviewTitle: { color: "#92400E", fontSize: 12, fontWeight: "800" },
  reviewText: { color: "#92400E", fontSize: 11 },
  downloadButton: { alignItems: "center", backgroundColor: colors.primary, borderRadius: 11, minHeight: 44, justifyContent: "center", paddingHorizontal: 14 },
  buttonDisabled: { opacity: 0.7 },
  downloadButtonText: { color: colors.white, fontSize: 13, fontWeight: "800" },
});

export default PayrollScreen;
