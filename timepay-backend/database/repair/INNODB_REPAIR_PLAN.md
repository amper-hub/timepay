# TimePay MyISAM to InnoDB repair plan

**Status: prepared and rehearsed on disposable local copies only. Do not run against any live or production database.**

## Findings

The local MySQL server used for this rehearsal reported `@@default_storage_engine = MyISAM`. `config/database.php` leaves the Laravel MySQL `engine` option `null`, so migrations defer to the server default. The payroll migrations declare foreign keys, but MyISAM does not enforce them; the migration run therefore left ordinary indexes with Laravel-style `_foreign` names and zero actual payroll foreign-key constraints. This is a local observation and must be rechecked in the intended environment before planning a repair.

All 11 payroll tables and the referenced `companies`, `users`, and `attendance_logs` tables are MyISAM. The conversion must also include legacy tables that have declared references in their migrations: `attendance_records`, `leave_requests`, and `attendance_event_responses`.

`payroll_audits.entity_type/entity_id` is polymorphic and intentionally has no concrete FK. Keep it that way; a company or entity deletion must not rewrite historical audit records implicitly.

## Existing payroll indexes

All primary, secondary, and unique indexes are preserved by `ALTER TABLE ... ENGINE=InnoDB`. The actual inventory is available from `inspect_source_readonly.php`.

| Table | Unique keys (besides primary key) | Other indexes |
|---|---|---|
| payroll_profiles | `payroll_profiles_user_id_unique (user_id)` | `company_id, payroll_eligible` |
| payroll_compensation_histories | none | `changed_by`; `user_id`; `(company_id,user_id,effective_from,effective_to)` |
| payroll_profile_items | `(user_id,category,code,effective_from)` | `created_by`; `(company_id,user_id,effective_from,effective_to)` |
| payroll_periods | `(company_id,period_start,period_end,frequency)` | `created_by`; `calculated_by`; `approved_by`; `(company_id,status,period_start)` |
| payroll_entries | `(payroll_period_id,user_id)` | `user_id`; `(company_id,user_id)` |
| payroll_entry_lines | none | `(payroll_entry_id,category)` |
| payroll_entry_attendance_logs | `attendance_log_id`; primary `(payroll_entry_id,attendance_log_id)` | none |
| payroll_statutory_rules | `(code,effective_from)` | `created_by`; `(code,effective_from,effective_to)` |
| payroll_payments | `(company_id,reference_number)` | `payroll_entry_id`; `(payroll_period_id,payroll_entry_id)`; `recorded_by` |
| payroll_audits | none | `actor_id`; `(company_id,entity_type,entity_id)` |
| payroll_attendance_allocations | `allocation_key`; `(clock_in_log_id,clock_out_log_id,payroll_period_id)` | `clock_out_log_id`; `payroll_entry_id`; `payroll_period_id`; `user_id`; `(company_id,user_id,payroll_period_id)`; `(clock_in_log_id,clock_out_log_id)` |

The `_foreign`-suffixed keys are indexes, not constraints. No index or unique key should be dropped as part of this repair.

## Orphan handling

The rehearsal found orphaned payroll child records referencing a missing payroll entry. Record-level identifiers, timestamps, durations, employee/company references, and monetary values are intentionally omitted from this public plan. The source was inspected read-only; the rehearsal archived copies in its disposable schema before adding constraints. No source rows were removed.

**Recommendation:** before any live repair, inventory orphan relationships using an approved read-only process, preserve a protected backup, and obtain a documented retention decision. Archive affected records with provenance and integrity checks in an approved protected location. Do not delete or rewrite source records without explicit authorization.
## Exact conversion order

Convert the following tables parent-first, then add constraints only after every participating table is InnoDB:

1. `companies`
2. `users`
3. `attendance_logs`
4. `attendance_records`
5. `leave_requests`
6. `attendance_event_responses`
7. `payroll_profiles`
8. `payroll_compensation_histories`
9. `payroll_profile_items`
10. `payroll_periods`
11. `payroll_statutory_rules`
12. `payroll_entries`
13. `payroll_entry_lines`
14. `payroll_entry_attendance_logs`
15. `payroll_payments`
16. `payroll_audits`
17. `payroll_attendance_allocations`

Each entry is `child.column -> parent.column (ON DELETE rule)`. `ON UPDATE` remains the MySQL default (`NO ACTION`, equivalent to `RESTRICT` in InnoDB).

| Child table and column | Parent | ON DELETE |
|---|---|---|
| `users.company_id` | `companies.id` | CASCADE |
| `attendance_records.company_id`, `.user_id` | `companies.id`, `users.id` | CASCADE, CASCADE |
| `leave_requests.company_id`, `.user_id` | `companies.id`, `users.id` | CASCADE, CASCADE |
| `attendance_logs.user_id`, `.company_id` | `users.id`, `companies.id` | CASCADE, CASCADE |
| `attendance_event_responses.user_id`, `.company_id` | `users.id`, `companies.id` | CASCADE, CASCADE |
| `payroll_profiles.company_id`, `.user_id` | `companies.id`, `users.id` | CASCADE, CASCADE |
| `payroll_compensation_histories.company_id`, `.user_id`, `.changed_by` | `companies.id`, `users.id`, `users.id` | CASCADE, CASCADE, SET NULL |
| `payroll_profile_items.company_id`, `.user_id`, `.created_by` | `companies.id`, `users.id`, `users.id` | CASCADE, CASCADE, SET NULL |
| `payroll_periods.company_id`, `.created_by`, `.calculated_by`, `.approved_by` | `companies.id`, `users.id`, `users.id`, `users.id` | RESTRICT, SET NULL, SET NULL, SET NULL |
| `payroll_entries.company_id`, `.payroll_period_id`, `.user_id` | `companies.id`, `payroll_periods.id`, `users.id` | RESTRICT, RESTRICT, RESTRICT |
| `payroll_entry_lines.payroll_entry_id` | `payroll_entries.id` | CASCADE |
| `payroll_entry_attendance_logs.payroll_entry_id`, `.attendance_log_id` | `payroll_entries.id`, `attendance_logs.id` | CASCADE, RESTRICT |
| `payroll_statutory_rules.created_by` | `users.id` | SET NULL |
| `payroll_payments.company_id`, `.payroll_period_id`, `.payroll_entry_id`, `.recorded_by` | `companies.id`, `payroll_periods.id`, `payroll_entries.id`, `users.id` | RESTRICT, RESTRICT, RESTRICT, RESTRICT |
| `payroll_audits.company_id`, `.actor_id` | `companies.id`, `users.id` | RESTRICT, SET NULL |
| `payroll_attendance_allocations.company_id`, `.user_id`, `.payroll_period_id`, `.payroll_entry_id`, `.clock_in_log_id`, `.clock_out_log_id` | `companies.id`, `users.id`, `payroll_periods.id`, `payroll_entries.id`, `attendance_logs.id`, `attendance_logs.id` | RESTRICT, RESTRICT, RESTRICT, CASCADE, RESTRICT, RESTRICT |

## Safe application sequence

1. Freeze writes for the maintenance window. MyISAM cannot provide a point-in-time transactional snapshot across tables.
2. Take a verified full backup with table locks using the database name supplied by the approved operator; store it in protected storage and perform a restore drill into a separate disposable database. Do not copy a live database name or credentials into public commands or documentation.
3. Record per-table row counts, `SHOW CREATE TABLE`, index definitions, engine, and all orphan reports. Verify backup counts against source counts.
4. Resolve each orphan only through the approved archive/retention decision. Re-scan every proposed FK for orphans, including all non-payroll relationships listed above. Stop on any unexpected orphan.
5. Alter the 17 tables in the order above. Compare counts and unique keys after every conversion. `ALTER TABLE` commits independently; do not assume a Laravel transaction can roll it back.
6. Add all 40 single-column foreign keys with the rules in the map. Use short deterministic constraint names; preserve all existing indexes.
7. Validate every table engine, all 40 FK columns and delete rules, unique keys, row counts plus archive counts, and zero remaining orphan references.
8. Run transaction rollback, concurrent row-lock, unique-key, cascade, and restrict probes, then the payroll workflow and full backend suites.
9. If any stage fails, stop writes and restore the verified pre-change backup to a separate schema for diagnosis. Recovery is a backup restore/replacement procedure, not a `down()` migration; DDL is not transactionally reversible.

Tables needing transactions/row locks for payroll consistency include all 11 payroll tables, plus `companies`, `users`, and `attendance_logs` used by payroll scope, source attendance, or related locking. `attendance_records`, `leave_requests`, and `attendance_event_responses` also need InnoDB for their declared FK enforcement and transactional behavior. Laravel's `migrations`, queue, session, cache, token, and password-reset tables were outside this payroll FK conversion set and remain as they were in the rehearsals.

## Rehearsal artifacts and results

- `prepare_innodb_copy.php`: local rehearsal utility that creates only allowlisted, absent target schemas; copies schema or data; archives orphan copies; converts tables and adds constraints. It is not a production repair tool and must not be run without independently verifying the source and target.
- `inspect_source_readonly.php`: read-only source engine/index/FK/orphan inventory.
- `verify_innodb_behaviors.php`: transaction, lock, unique, restrict, and cascade probes against allowlisted copies.
- `mysql_test_preload.php`: used only to run PHPUnit against the pre-created InnoDB schema without `migrate:fresh` recreating MyISAM tables.

Historical MySQL/InnoDB rehearsal results: the data copy's row counts matched at copy time; orphan copies were archived; and the copy had 40 FK columns installed. The low-level behavior probes passed. `PayrollWorkflowTest` ran 47 tests on a converted empty MySQL/InnoDB test schema, with 286 assertions and 3 failures: approval-path tests for API attendance totals, prior-paid boundary allocation, and finalized payroll report observed changed attendance-allocation and compensation-history snapshots after calculation. These failures remain unresolved for MySQL/InnoDB until rerun against a separately verified disposable MySQL database.

Subsequent test results, **SQLite in-memory only**:

- `PayrollWorkflowTest`: 50 passed, 329 assertions.
- Full backend suite: 178 passed, 989 assertions.

These SQLite results do not establish MySQL/InnoDB compatibility and do not supersede the historical failures above. No MySQL/InnoDB verification is claimed by those runs. No live or production database operation is authorized by this document.
