<?php

namespace App\Support;

final class PayrollReviewFlags
{
    /**
     * These issues can make a payroll amount incomplete or unsafe to pay.
     * A review note cannot waive them; correct the source and recalculate.
     *
     * @return list<string>
     */
    public static function critical(): array
    {
        return [
            'employee_status_requires_review',
            'attendance_already_used_or_paid',
            'incomplete_attendance_requires_review',
            'duplicate_attendance_requires_review',
            'missing_effective_pay_rate',
            'missing_effective_hourly_rate',
            'zero_compensation_rate_requires_review',
            'long_shift_overtime_requires_approval',
            'negative_net_pay_requires_review',
            'taxable_benefit_over_90000_includes_only_this_13th_month_payment',
        ];
    }
}
