# Payroll attendance allocation ledger

## Defect and policy

Attendance punches are immutable source events. Payroll periods use inclusive local calendar dates represented as half-open intervals: `[period start at 00:00, day after period end at 00:00)` in `Asia/Manila`. A completed verified shift is intersected with each period interval. Each non-empty intersection is an exact elapsed-time segment; segment seconds are retained without rounding. `worked_minutes` remains rounded half-up once per payroll entry from the sum of allocated seconds.

The previous implementation linked a punch to one payroll entry and rejected the whole shift in another period when either punch was already linked or marked paid. For an Oct 31 23:00–Nov 1 01:00 shift, that reserved the October 23:00–midnight hour and discarded the November midnight–01:00 hour.

## Ledger and duplicate prevention

`payroll_attendance_allocations` stores the company, employee, payroll entry and period; both original punch IDs and timestamps; the half-open segment start/end; elapsed seconds; and a deterministic SHA-256 allocation key. The same shift may have one row per period. The unique allocation key prevents the exact same segment from being inserted twice, and a transaction-locked source punch pair plus overlap validation prevents partially overlapping segments from being allocated to different periods. Punch links remain attached to the period containing each punch timestamp for the existing source audit and paid-punch workflow.

Each entry snapshot contains the same allocation rows. Approval checks the snapshot against the ledger, verifies the original punch pair, employee/company, timestamps, segment bounds, elapsed seconds, non-overlap, and rounded worked-minute total. Recalculating a draft deletes its entries and allocation rows together inside the calculation transaction, then deterministically creates them again. Approved, partially paid, paid, and voided period rows are not recalculated or released by this change. Payment balance and payment-reference enforcement remains on the existing payment workflow.

## Existing records and migration

The migration creates the allocation table but does not rewrite finalized payroll or backfill presumed time segments. If a source punch is already linked or marked paid but the corresponding completed shift has no valid allocation history, calculation adds the critical `attendance_allocation_history_requires_review` flag and approval remains blocked. An operator must reconcile that historical shift against the original punches and prior payroll before processing its remaining segment.

No production migration was run for this change. Before applying it to a deployed environment, take a database backup, verify schema compatibility and table privileges, and rehearse on a restored copy. The migration is additive. Its `down()` drops the allocation ledger and therefore destroys allocation audit evidence; do not roll it back after payroll periods have been calculated without exporting/reconciling those records and an approved recovery plan.

## Limits

The ledger allocates elapsed attendance seconds; it does not decide whether every second is legally payable, calculate overtime/night/holiday premiums, determine breaks, or resolve statutory eligibility. Existing Philippine statutory and compensation-input review blockers remain separate release blockers.
