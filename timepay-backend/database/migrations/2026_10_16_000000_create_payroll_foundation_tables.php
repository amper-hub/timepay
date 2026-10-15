<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Validate legacy compensation data before creating any payroll tables.
        // The legacy schema has hourly_rate and daily_rate but no monthly_rate or
        // per-employee salary basis, so infer only when exactly one positive rate exists.
        $this->assertLegacyCompensationIsUnambiguous();

        Schema::create('payroll_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('salary_type', ['hourly', 'daily', 'monthly'])->default('hourly');
            $table->enum('pay_frequency', ['hourly', 'daily', 'weekly', 'semimonthly', 'monthly', 'yearly'])->default('monthly');
            $table->boolean('payroll_eligible')->default(true);
            $table->boolean('overtime_eligible')->default(true);
            $table->boolean('rank_and_file')->default(true);
            $table->boolean('sss_enabled')->default(true);
            $table->boolean('philhealth_enabled')->default(true);
            $table->boolean('pagibig_enabled')->default(true);
            $table->boolean('withholding_tax_enabled')->default(true);
            $table->unsignedTinyInteger('standard_hours_per_day')->default(8);
            $table->unsignedTinyInteger('standard_work_days_per_month')->default(22);
            $table->timestamps();
            $table->unique('user_id');
            $table->index(['company_id', 'payroll_eligible']);
        });

        Schema::create('payroll_compensation_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('basis', ['hourly', 'daily', 'monthly']);
            $table->decimal('rate', 12, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'user_id', 'effective_from', 'effective_to'], 'payroll_rates_employee_dates_idx');
        });

        Schema::create('payroll_profile_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('category', ['earning', 'deduction']);
            $table->string('code', 60);
            $table->string('label');
            $table->decimal('amount', 12, 2);
            $table->boolean('taxable')->default(true);
            $table->string('authorization_reference', 120)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'user_id', 'effective_from', 'effective_to'], 'payroll_profile_items_dates_idx');
            $table->unique(['user_id', 'category', 'code', 'effective_from'], 'payroll_profile_item_version_unique');
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('pay_date')->nullable();
            $table->enum('frequency', ['hourly', 'daily', 'weekly', 'semimonthly', 'monthly', 'yearly']);
            $table->enum('status', ['draft', 'pending_review', 'approved', 'partially_paid', 'paid', 'voided'])->default('draft');
            $table->decimal('gross_total', 14, 2)->default(0);
            $table->decimal('deduction_total', 14, 2)->default(0);
            $table->decimal('employer_contribution_total', 14, 2)->default(0);
            $table->decimal('net_total', 14, 2)->default(0);
            $table->decimal('paid_total', 14, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'period_start', 'period_end', 'frequency'], 'payroll_period_identity_unique');
            $table->index(['company_id', 'status', 'period_start']);
        });

        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedSmallInteger('payable_days')->default(0);
            $table->decimal('gross_pay', 14, 2)->default(0);
            $table->decimal('employee_deductions', 14, 2)->default(0);
            $table->decimal('employer_contributions', 14, 2)->default(0);
            $table->decimal('net_pay', 14, 2)->default(0);
            $table->decimal('employer_cost', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->json('calculation_snapshot');
            $table->json('review_flags')->nullable();
            $table->timestamps();
            $table->unique(['payroll_period_id', 'user_id'], 'payroll_entry_period_employee_unique');
            $table->index(['company_id', 'user_id']);
        });

        Schema::create('payroll_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_entry_id')->constrained()->cascadeOnDelete();
            $table->enum('category', ['earning', 'employee_deduction', 'employer_contribution']);
            $table->string('code', 60);
            $table->string('label');
            $table->decimal('amount', 14, 2);
            $table->decimal('quantity', 12, 4)->nullable();
            $table->decimal('rate', 12, 4)->nullable();
            $table->boolean('taxable')->default(true);
            $table->json('details')->nullable();
            $table->timestamps();
            $table->index(['payroll_entry_id', 'category']);
        });

        Schema::create('payroll_entry_attendance_logs', function (Blueprint $table) {
            $table->foreignId('payroll_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_log_id')->constrained('attendance_logs')->restrictOnDelete();
            $table->primary(['payroll_entry_id', 'attendance_log_id'], 'payroll_entry_attendance_pk');
            $table->unique('attendance_log_id', 'payroll_attendance_source_unique');
        });

        Schema::create('payroll_statutory_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40);
            $table->string('name');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->json('configuration');
            $table->string('source_url', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['code', 'effective_from'], 'payroll_statutory_rule_version_unique');
            $table->index(['code', 'effective_from', 'effective_to']);
        });

        Schema::create('payroll_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_entry_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->enum('method', ['manual_cash', 'manual_bank_deposit', 'manual_cheque']);
            $table->string('reference_number', 120)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'reference_number'], 'payroll_payment_reference_unique');
            $table->index(['payroll_period_id', 'payroll_entry_id']);
        });

        Schema::create('payroll_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('entity_type', 80);
            $table->unsignedBigInteger('entity_id');
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'entity_type', 'entity_id']);
        });

        $this->seedStatutoryDefaults();
        $this->backfillExistingEmployees();
    }

    private function seedStatutoryDefaults(): void
    {
        $now = now();
        $rules = [
            [
                'code' => 'sss', 'name' => 'SSS Business Employer and Employee Schedule', 'effective_from' => '2025-01-01',
                'source_url' => 'https://www.sss.gov.ph/wp-content/uploads/2024/12/2025-SSS-Contribution-Table-rev.pdf',
                'configuration' => ['msc_min' => 5000, 'msc_max' => 35000, 'msc_step' => 500, 'msc_rounding_offset' => 250, 'employee_rate_bps' => 500, 'employer_rate_bps' => 1000, 'ec_threshold_msc' => 14500, 'ec_below_threshold' => 10, 'ec_at_or_above_threshold' => 30],
            ],
            [
                'code' => 'philhealth', 'name' => 'PhilHealth Direct Contributor Schedule', 'effective_from' => '2025-01-01',
                'source_url' => 'https://www.philhealth.gov.ph/advisories/2025/PA2025-0002.pdf',
                'configuration' => ['rate_bps' => 500, 'monthly_basic_salary_floor' => 10000, 'monthly_basic_salary_ceiling' => 100000, 'employee_share_bps' => 5000, 'employer_share_bps' => 5000],
            ],
            [
                'code' => 'pagibig', 'name' => 'Pag-IBIG Membership Savings Schedule', 'effective_from' => '2024-02-01',
                'source_url' => 'https://www.pagibigfund.gov.ph/document/pdf/payments/ESRS.pdf',
                'configuration' => ['employee_threshold' => 1500, 'employee_rate_below_or_equal_bps' => 100, 'employee_rate_above_bps' => 200, 'employer_rate_bps' => 200, 'monthly_fund_salary_ceiling' => 10000],
            ],
            [
                'code' => 'bir_compensation_tax', 'name' => 'BIR Revised Withholding Tax Table Annex E', 'effective_from' => '2023-01-01',
                'source_url' => 'https://bir-cdn.bir.gov.ph/local/pdf/Annex%20E%20RR%2011-2018.pdf',
                'configuration' => [
                    'periods' => [
                        'daily' => [[68500, 0, 0], [109500, 0, 1500], [219100, 6165, 2000], [547800, 28085, 2500], [2191700, 110260, 3000], [null, 603400, 3500]],
                        'weekly' => [[480800, 0, 0], [769100, 0, 1500], [1538400, 43260, 2000], [3846100, 197120, 2500], [15384500, 774045, 3000], [null, 4235565, 3500]],
                        'semimonthly' => [[1041700, 0, 0], [1666600, 0, 1500], [3333200, 93750, 2000], [8333200, 427070, 2500], [33333200, 1677070, 3000], [null, 9177070, 3500]],
                        'monthly' => [[2083300, 0, 0], [3333300, 0, 1500], [6666600, 187500, 2000], [16666600, 854180, 2500], [66666600, 3354180, 3000], [null, 18354180, 3500]],
                        'yearly' => [[25000000, 0, 0], [40000000, 0, 1500], [80000000, 2250000, 2000], [200000000, 10250000, 2500], [800000000, 40250000, 3000], [null, 220250000, 3500]],
                    ],
                ],
            ],
        ];

        foreach ($rules as $rule) {
            DB::table('payroll_statutory_rules')->updateOrInsert(
                ['code' => $rule['code'], 'effective_from' => $rule['effective_from']],
                [
                    'name' => $rule['name'], 'effective_to' => null,
                    'configuration' => json_encode($rule['configuration'], JSON_THROW_ON_ERROR),
                    'source_url' => $rule['source_url'], 'created_at' => $now, 'updated_at' => $now,
                ],
            );
        }
    }

    /**
     * Fail before schema changes when legacy pay rates cannot be safely migrated.
     *
     * Legacy users have hourly_rate and daily_rate only. A single positive rate
     * is the only available evidence of the employee's compensation basis.
     */
    private function assertLegacyCompensationIsUnambiguous(): void
    {
        if (! Schema::hasTable('users')
            || ! Schema::hasColumn('users', 'company_id')
            || ! Schema::hasColumn('users', 'role')
            || ! Schema::hasColumn('users', 'hourly_rate')
            || ! Schema::hasColumn('users', 'daily_rate')) {
            return;
        }

        $users = DB::table('users')
            ->whereNotNull('company_id')
            ->whereIn(DB::raw('LOWER(role)'), ['employee'])
            ->get(['id', 'company_id', 'hourly_rate', 'daily_rate']);

        $problems = [];
        foreach ($users as $user) {
            $hourly = (float) ($user->hourly_rate ?? 0);
            $daily = (float) ($user->daily_rate ?? 0);
            $hasHourly = $hourly > 0;
            $hasDaily = $daily > 0;

            if ($hasHourly === $hasDaily) {
                $problems[] = "user_id={$user->id} (hourly_rate={$hourly}, daily_rate={$daily})";
            }
        }

        if ($problems !== []) {
            throw new RuntimeException(
                'Payroll migration stopped before creating payroll tables because compensation basis/rate is missing or ambiguous for: '
                . implode('; ', $problems)
                . '. Correct each employee rate explicitly, then rerun the migration.'
            );
        }
    }

    private function backfillExistingEmployees(): void
    {
        if (! Schema::hasColumn('users', 'company_id') || ! Schema::hasColumn('users', 'role')) {
            return;
        }

        $users = DB::table('users')
            ->whereNotNull('company_id')
            ->whereIn(DB::raw('LOWER(role)'), ['employee'])
            ->get();

        foreach ($users as $user) {
            $hourly = (float) ($user->hourly_rate ?? 0);
            $daily = (float) ($user->daily_rate ?? 0);
            $hasHourly = $hourly > 0;
            $hasDaily = $daily > 0;

            // Repeat the validation defensively; never manufacture a zero-rate record.
            if ($hasHourly === $hasDaily) {
                throw new \RuntimeException(
                    "Cannot backfill payroll compensation for user_id={$user->id}: exactly one positive legacy rate is required."
                );
            }

            $salaryType = $hasDaily ? 'daily' : 'hourly';
            $rate = $hasDaily ? $daily : $hourly;
            $effectiveFrom = now()->toDateString();

            DB::table('payroll_profiles')->updateOrInsert(
                ['user_id' => $user->id],
                ['company_id' => $user->company_id, 'salary_type' => $salaryType, 'pay_frequency' => 'monthly', 'created_at' => now(), 'updated_at' => now()],
            );
            DB::table('payroll_compensation_histories')->updateOrInsert(
                ['user_id' => $user->id, 'effective_from' => $effectiveFrom],
                ['company_id' => $user->company_id, 'basis' => $salaryType, 'rate' => number_format($rate, 2, '.', ''), 'effective_to' => null, 'changed_by' => null, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_audits');
        Schema::dropIfExists('payroll_payments');
        Schema::dropIfExists('payroll_statutory_rules');
        Schema::dropIfExists('payroll_entry_attendance_logs');
        Schema::dropIfExists('payroll_entry_lines');
        Schema::dropIfExists('payroll_entries');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('payroll_profile_items');
        Schema::dropIfExists('payroll_compensation_histories');
        Schema::dropIfExists('payroll_profiles');
    }
};
