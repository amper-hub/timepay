# Payroll statutory rules: implementation review

Reviewed against the official guidance available on 2026-10-19. Rule values live in the `payroll_statutory_rules` table and each calculation snapshots the selected configuration. This is a code review, not a legal or accounting opinion.

## SSS

- The seeded rule is effective 2025-01-01: employee 5%, employer 10%, monthly salary credit (MSC) PHP 5,000–35,000 in PHP 500 steps. The employer also pays EC: PHP 10 at MSC up to PHP 14,500 and PHP 30 at MSC PHP 15,000 and above.
- The implementation maps the monthly base to the nearest PHP 500 bracket using a PHP 250 offset and clamps to the floor/ceiling. It separates employee SS from employer SS and EC. It does not separately show the MPF split for MSC above PHP 20,000, although its combined EE/ER share math totals the same schedule rates.
- Source: [SSS Circular 2024-006 and 2025 business employer/employee table](https://www.sss.gov.ph/wp-content/uploads/2024/12/2025-SSS-Contribution-Table-rev.pdf), effective January 2025; [SSS employer contribution guidance](https://www.sss.gov.ph/pay-contribution/).

## PhilHealth

- The seeded rule is effective 2025-01-01: 5% of monthly basic salary, PHP 10,000 floor and PHP 100,000 ceiling, shared equally by employer and employee.
- The implementation clamps the computed monthly base, calculates the full premium, splits it 50/50, then allocates the amount across a payroll period by calendar days. Official guidance specifies monthly basic salary and excludes overtime, allowances, 13th-month pay, bonuses, and undertime/absence reductions. Hourly/daily employees are currently converted to a monthly equivalent using profile standard hours/days; this may not equal the official employee-specific fixed monthly basic salary. That basis and the day-proration method need payroll-professional confirmation before use for irregular schedules or split periods.
- Source: [PhilHealth Advisory 2025-0002](https://www.philhealth.gov.ph/advisories/2025/PA2025-0002.pdf), applicable January 2025; it states the 5% rate remains in effect for CY 2025.

## Pag-IBIG

- The seeded rule is effective 2024-02-01: employee 1% for fund salary up to PHP 1,500, employee 2% above PHP 1,500, employer 2%, with a PHP 10,000 maximum fund salary.
- The implementation uses the same thresholds and ceiling and keeps the employer counterpart out of employee deductions. Calendar-day proration for non-monthly payroll periods still needs professional confirmation.
- Source: [Pag-IBIG Circular 460, reproduced in a government advisory](https://dmw.gov.ph/archives/v1/resources/dsms/DMW/ISN-EXT/2025/DMW-ADVISORY-37-2025.pdf), effective February 2024; [Pag-IBIG employer collection guide](https://www.pagibigfund.gov.ph/document/pdf/payments/ESRS.pdf).

## BIR compensation withholding tax

- The seeded Annex E bands are effective 2023-01-01 and contain daily, weekly, semi-monthly, monthly, and annual thresholds. Calculations use integer centavos and round percentage calculations to the nearest centavo. Employee mandatory SSS, PhilHealth, and Pag-IBIG shares are subtracted from taxable compensation; employer contributions are not deducted from employee pay.
- The year-end adjustment annualizes taxable compensation and subtracts prior withholding. The calculation model does not currently represent all BIR-specific classifications and exclusions (including minimum-wage-earner status, de minimis benefit limits, and the employer’s complete taxable/non-taxable benefit treatment). The 13th-month path only knows the PHP 90,000 exclusion and approved basic-pay snapshots, so it cannot establish an employee’s remaining exemption against every other qualifying benefit or payment.
- Source: [BIR Revenue Regulations 11-2018, Annex E](https://bir-cdn.bir.gov.ph/local/pdf/Annex%20E%20RR%2011-2018.pdf), effective January 1, 2023 onwards; [BIR withholding tax calculator guidance](https://web-services.bir.gov.ph/tax_calculator/wt_calculator.html).

## 13th-month pay and open issues

- DOLE guidance describes the minimum as one-twelfth of total basic salary earned during the calendar year for covered rank-and-file employees who worked at least one month, including employees whose employment ended during the year.
- TimePay currently calculates from basic-pay lines in approved payroll periods. Draft or awaiting-review periods are excluded to avoid double counting, but that means the run can be incomplete until those periods are finalized. An explicit yearly 13th-month period can include a deactivated rank-and-file employee when approved basic-pay history exists; it requires a review note, snapshots the inactive status, and never reactivates the account. That exception covers only the 13th-month calculation. The application has no workflow for other outstanding final-pay components; do not treat the annual run as proof that final pay is complete.
- Source: [DOLE Bureau of Working Conditions 13th-month Q&A](https://dole.gov.ph/news/doles-bwc-issues-q-a-on-13th-month-pay/).

## Readiness limits

Current official pages reviewed showed the 2025 SSS, PhilHealth, and Pag-IBIG schedules above as applicable schedules, with no later 2026 contribution table found in those official sources on the review date. The BIR Annex E table remains effective from 2023. Before real payroll use, a qualified Philippine payroll professional should confirm monthly-base derivation, submonthly contribution allocation/rounding, tax classifications and exclusions, accumulated 13th-month/other-benefit exemption, and final-pay handling. No rule update or finalized payroll record is changed by this review.
