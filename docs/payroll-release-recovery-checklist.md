# TimePay Payroll Release and Recovery Checklist

This checklist is a release gate, not evidence that all checks have passed. Do not deploy payroll changes until an authorized reviewer records the results.

## Candidate validation snapshot

- Candidate reviewed: `f9ab04c9aacb1a1c7f35ca405910270d9841bd5e`
- GitHub Actions payroll safety workflow: https://github.com/amper-hub/timepay/actions/runs/38703525353
- Result: **passed** for that exact commit, including payroll regression tests, migration safety tests, and the full backend test suite.
- This CI result does not certify statutory correctness, production data recovery, or deployment readiness. Any new commit requires checking CI for the new exact candidate.

## Before merge or deployment

- [ ] Confirm the exact candidate commit and that CI passed on that commit.
- [ ] Obtain independent review of SSS, PhilHealth, Pag-IBIG, BIR withholding, year-end reconciliation, and 13th-month calculations against current official rules and approved payroll test vectors.
- [ ] Verify taxable/non-taxable earning classification and de minimis benefit limits against current BIR rules, including RR No. 29-2025 (effective in 2026). Do not rely solely on a manually selected `taxable` flag for recurring profile items; test each supported benefit, its applicable limit, and any excess amount against an independently approved expected result. Official BIR 2025 Revenue Regulations index: https://www.bir.gov.ph/2025-Revenue-Regulations
- [ ] Confirm each legacy employee's compensation basis and historical effective date with the employer. The migration can infer only hourly versus daily from legacy fields; it cannot reconstruct prior salary history. Do not treat the migration date as proof of the historical effective date. Supply the reviewed mapping as `PAYROLL_LEGACY_RATE_EFFECTIVE_DATES` JSON keyed by employee user ID, e.g. `{"123":"2024-01-01","456":"2025-07-15"}`; the migration must stop before schema changes if any employee is missing a valid date.
- [ ] Obtain a fresh production database backup using the approved Railway/database process. Record the backup timestamp and verification/checksum where supported. Do not put credentials or connection strings in this document.
- [ ] Restore that backup to a separate, isolated non-production database. Verify representative row counts and key relationships before testing.
- [ ] Run the candidate migration against the isolated restored copy. Verify all payroll tables, seeded statutory rule versions, employee profile/rate backfills, and application smoke tests.
- [ ] Review migration failure behavior and recovery. The migration's `down()` drops payroll tables and therefore deletes payroll data created in them; do not use rollback as a routine production recovery plan after payroll data exists.
- [ ] Verify that the deployment platform's pre-deploy migration command and application release process target the intended environment and database.
- [ ] Record reviewer names, candidate commit, backup/restore result, migration result, and go/no-go decision in the release review.

## Go/no-go rules

**No-go** if the backup cannot be restored, any employee's rate/effective date is ambiguous, statutory calculations lack independent sign-off, taxable/non-taxable benefit handling is not verified against current BIR rules, migration results differ from expectations, or CI is not green for the exact candidate commit.

## Deployment and post-deployment

- [ ] Deploy only after the release owner explicitly approves the candidate.
- [ ] Confirm application health, authentication, payroll period creation/calculation, approval guardrails, payment recording, payslips, and exports with non-sensitive test data.
- [ ] Do not calculate or approve a real payroll until payroll owners reconcile a sample against independently prepared expected results.
- [ ] Monitor migration/application logs and preserve the pre-deployment backup according to the organization's retention policy.

## Recovery notes

- Prefer restoring a verified backup to a separate database for diagnosis before any production recovery action.
- Do not run `migrate:rollback` against production payroll tables without a documented, approved data-preservation plan.
- Escalate any discrepancy in employee rates, deductions, net pay, year-end credits, or statutory contributions; do not manually adjust production records without an audit trail.
