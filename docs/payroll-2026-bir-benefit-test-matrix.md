# 2026 Philippine BIR De Minimis Payroll Test Matrix

Status: **review specification only; not proof of implementation or tax/accounting approval.**

Source: BIR Revenue Regulations No. 29-2025, issued 22 December 2025. The BIR's published digest states that it increases the ceilings for listed non-taxable benefits. Verify the complete regulation and all applicable rules with a qualified Philippine payroll/tax reviewer before using these values in production.

Official source:
- BIR 2025 Revenue Regulations index: https://www.bir.gov.ph/2025-Revenue-Regulations
- BIR RR No. 29-2025 digest PDF: https://bir-cdn.bir.gov.ph/BIR/pdf/RR%20No.%2029-2025%20digest%20FINAL.pdf

## Required benefit-level test cases

For every supported benefit, tests must cover: below the limit, exactly at the limit, above the limit, cumulative payments across the relevant period, period/year boundary reset, retroactive corrections, and whether the benefit qualifies under all required conditions. The expected tax treatment must be approved by the payroll/tax reviewer; do not assume that an amount above an individual ceiling is automatically wholly taxable or wholly exempt.

| Benefit category | RR No. 29-2025 ceiling | Required aggregation / qualification |
|---|---:|---|
| Monetized unused private-employee vacation leave | Up to 12 days per year | Track eligible days and taxable year; confirm employee/benefit eligibility |
| Medical cash allowance for dependents | ₱2,000 per semester or ₱333 per month | Test monthly and semester limits and cumulative payments |
| Rice subsidy | ₱2,500 per month (or one 50 kg sack valued at no more than ₱2,500) | Aggregate per month and validate form/value |
| Uniform and clothing allowance | ₱8,000 per annum | Aggregate across the taxable year |
| Actual medical assistance | ₱12,000 per annum | Validate qualifying medical purpose and annual aggregate |
| Laundry allowance | ₱400 per month | Aggregate per month |
| Employee achievement awards | ₱12,000 per annum | Require qualifying established written plan and non-discriminatory conditions |
| Christmas / major-anniversary gifts | ₱6,000 per annum | Aggregate eligible gifts across the taxable year |
| Overtime / night / graveyard meal allowance | Up to 30% of regional basic minimum wage per day | Resolve applicable region, wage rate, and eligible workday |
| CBA and productivity incentives combined | ₱12,000 per taxable year | Aggregate both categories together, not separately |

## Implementation gap to resolve

Payroll profile items currently expose a generic `taxable` boolean. That flag alone cannot enforce category-specific ceilings, cumulative monthly/semester/annual limits, eligibility conditions, or the tax treatment of excess amounts. Do not treat a passing test of the boolean as proof of RR No. 29-2025 compliance.

Before coding these rules, agree on a stable benefit-code catalogue and the required evidence/qualifiers with the payroll owner. Then implement date-aware cumulative tracking and approved excess-treatment rules, with tests for boundary conditions and multiple benefit entries. If the application cannot reliably identify a benefit category, require manual review rather than automatically applying a tax exemption.

## Release gate

- [ ] Payroll/tax reviewer approves the benefit catalogue, qualifications, ceiling periods, and excess treatment.
- [ ] Automated tests cover every supported benefit below/at/above its ceiling and across period boundaries.
- [ ] Historical 2026 payroll recalculation behavior is defined and tested before any retroactive changes.
- [ ] CI passes on the exact release candidate.
- [ ] Backup restore and migration rehearsal succeeds on an isolated copy of production.
