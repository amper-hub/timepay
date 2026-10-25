@extends('layouts.employer')

@section('title', 'Attendance Log - TimePay Employer Portal')
@section('header_title', 'Attendance Log')

@section('content')
    <div class="space-y-6">
        <header class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-emerald-700">People</p>
                <h2 class="employer-page-heading">Attendance</h2>
                <p class="employer-page-description">Review employee clock events, verification results, and daily time records.</p>
            </div>
            <button type="button" data-dtr-open-all class="employer-primary-button">Export All Employees</button>
        </header>

        <details class="employer-card">
            <summary class="cursor-pointer px-5 py-4 text-base font-semibold text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-600">Filter attendance</summary>
            <form method="GET" action="{{ route('employer.attendance') }}" class="grid gap-4 border-t border-slate-200 p-5 sm:grid-cols-2 xl:grid-cols-5" aria-label="Filter attendance logs">
                <label for="attendance-employee" class="employer-field-label">Employee
                    <select id="attendance-employee" name="employee_id" class="employer-field-control">
                        <option value="">All employees</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((string) $filters['employee_id'] === (string) $employee->id)>{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label for="attendance-status" class="employer-field-label">Verification status
                    <select id="attendance-status" name="status" class="employer-field-control">
                        <option value="">All statuses</option>
                        @foreach (['verified' => 'Verified', 'flagged' => 'Flagged for review', 'rejected' => 'Rejected'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label for="attendance-from" class="employer-field-label">From date
                    <input id="attendance-from" name="from" type="date" value="{{ $filters['from'] }}" class="employer-field-control">
                </label>
                <label for="attendance-to" class="employer-field-label">To date
                    <input id="attendance-to" name="to" type="date" value="{{ $filters['to'] }}" class="employer-field-control">
                </label>
                <div class="flex items-end gap-2">
                    <button type="submit" class="employer-primary-button flex-1">Apply filters</button>
                    <a href="{{ route('employer.attendance') }}" class="employer-secondary-button">Clear</a>
                </div>
                @error('employee_id')<p class="text-sm font-medium text-red-700 sm:col-span-2 xl:col-span-5">{{ $message }}</p>@enderror
                @error('from')<p class="text-sm font-medium text-red-700 sm:col-span-2 xl:col-span-5">{{ $message }}</p>@enderror
                @error('to')<p class="text-sm font-medium text-red-700 sm:col-span-2 xl:col-span-5">{{ $message }}</p>@enderror
            </form>
        </details>

        <section class="employer-card overflow-hidden">
            <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h2 class="employer-section-heading">Attendance records</h2>
                    <p class="mt-1 text-sm text-slate-600">Showing {{ $attendanceLogs->count() }} of {{ $attendanceLogs->total() }} events.</p>
                </div>
            </div>

            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-5 py-3 text-left text-sm font-semibold text-slate-700">Employee</th>
                            <th scope="col" class="px-5 py-3 text-left text-sm font-semibold text-slate-700">Event</th>
                            <th scope="col" class="px-5 py-3 text-left text-sm font-semibold text-slate-700">Date and time</th>
                            <th scope="col" class="px-5 py-3 text-left text-sm font-semibold text-slate-700">Distance</th>
                            <th scope="col" class="px-5 py-3 text-left text-sm font-semibold text-slate-700">Status</th>
                            <th scope="col" class="px-5 py-3 text-right text-sm font-semibold text-slate-700">DTR</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($attendanceLogs as $log)
                            <tr class="hover:bg-slate-50 {{ $log->is_suspicious ? 'bg-amber-50/40' : '' }}">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-600 text-sm font-bold text-white">
                                            {{ strtoupper(substr($log->user?->name ?? '?', 0, 1)) }}
                                        </div>
                                        <div>
                                            <p class="text-base font-semibold text-slate-950">{{ $log->user?->name ?? 'Unknown employee' }}</p>
                                            <p class="text-sm text-slate-600">{{ $log->user?->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-sm font-semibold {{ $log->type === 'clock_in' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-700' }}">
                                        {{ $log->type === 'clock_in' ? 'Clock In' : 'Clock Out' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-base text-slate-700">
                                    {{ $log->timestamp?->timezone('Asia/Manila')->format('M d, Y g:i A') ?? '-' }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-base text-slate-700">
                                    {{ number_format((float) $log->distance_meters, 1) }} meters away
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-sm font-semibold @if ($log->status === 'verified') border-emerald-200 bg-emerald-50 text-emerald-800 @elseif ($log->status === 'flagged') border-amber-200 bg-amber-50 text-amber-900 @elseif ($log->status === 'rejected') border-red-200 bg-red-50 text-red-800 @else border-slate-200 bg-slate-100 text-slate-700 @endif">
                                        @if ($log->status === 'verified')
                                            Verified
                                        @elseif ($log->status === 'flagged')
                                            Flagged Review
                                        @elseif ($log->status === 'rejected')
                                            Rejected
                                        @else
                                            Out of Bounds Alert
                                        @endif
                                    </span>
                                    @if ($log->is_suspicious)
                                        <p class="mt-2 max-w-56 whitespace-normal text-sm font-medium text-amber-900">Exception: {{ $log->suspicion_reason ?: 'This punch was flagged for review.' }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    @if ($log->user && $log->user->role === \App\Models\User::ROLE_EMPLOYEE)
                                        <button
                                            type="button"
                                            data-dtr-open
                                            data-employee-id="{{ $log->user->id }}"
                                            class="min-h-10 rounded-lg border border-emerald-200 px-3 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600"
                                        >
                                            Download DTR
                                        </button>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-base text-slate-600">No attendance records match these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-200 md:hidden">
                @forelse ($attendanceLogs as $log)
                    <article class="space-y-3 px-4 py-4 {{ $log->is_suspicious ? 'bg-amber-50/50' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate font-semibold text-slate-900">{{ $log->user?->name ?? 'Unknown employee' }}</h3>
                                <p class="break-all text-sm text-slate-600">{{ $log->user?->email }}</p>
                            </div>
                            <span class="shrink-0 rounded-full border px-2.5 py-1 text-sm font-semibold {{ $log->status === 'verified' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : ($log->status === 'flagged' ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-red-200 bg-red-50 text-red-800') }}">{{ str($log->status)->replace('_', ' ')->title() }}</span>
                        </div>
                        <dl class="grid grid-cols-2 gap-3 border-t border-slate-200 pt-3 text-sm">
                            <div><dt class="text-slate-600">Event</dt><dd class="mt-0.5 font-medium text-slate-900">{{ str($log->type)->replace('_', ' ')->title() }}</dd></div>
                            <div><dt class="text-slate-600">Distance</dt><dd class="mt-0.5 font-medium text-slate-900">{{ number_format((float) $log->distance_meters, 1) }} meters</dd></div>
                            <div class="col-span-2"><dt class="text-slate-600">Date and time</dt><dd class="mt-0.5 font-medium text-slate-900">{{ $log->timestamp?->timezone('Asia/Manila')->format('M d, Y g:i A') ?? '-' }}</dd></div>
                        </dl>
                        @if ($log->is_suspicious)
                            <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-900">Exception: {{ $log->suspicion_reason ?: 'This punch was flagged for review.' }}</p>
                        @endif
                        @if ($log->user && strtolower((string) $log->user->role) === 'employee')
                            <button type="button" data-dtr-open data-employee-id="{{ $log->user->id }}" class="employer-secondary-button w-full">View employee DTR</button>
                        @endif
                    </article>
                @empty
                    <p class="px-4 py-10 text-center text-base text-slate-600">No attendance records match these filters.</p>
                @endforelse
            </div>

            @if ($attendanceLogs->hasPages())
                <div class="border-t border-slate-200 bg-slate-50 px-6 py-4">
                    {{ $attendanceLogs->links() }}
                </div>
            @endif
        </section>
    </div>

    <div
        id="dtr-modal"
        data-preview-template="{{ route('employer.attendance.dtr.show', ['employee' => '__employee__']) }}"
        data-pdf-template="{{ route('employer.attendance.dtr.pdf', ['employee' => '__employee__']) }}"
        data-xlsx-template="{{ route('employer.attendance.dtr.xlsx', ['employee' => '__employee__']) }}"
        data-all-url="{{ route('employer.attendance.dtr.all') }}"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/50 p-4"
        aria-hidden="true"
    >
        <section class="max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-xl bg-white shadow-xl" role="dialog" aria-modal="true" aria-labelledby="dtr-modal-title">
            <div class="flex items-start justify-between border-b border-slate-200 px-6 py-5">
                <div>
                    <h2 id="dtr-modal-title" class="text-lg font-semibold text-slate-950">Daily Time Record</h2>
                    <p class="mt-1 text-sm text-slate-500">Preview actual attendance punches before exporting.</p>
                </div>
                <button type="button" data-dtr-close aria-label="Close" class="rounded-md px-2 py-1 text-xl leading-none text-slate-500 hover:bg-slate-100">&times;</button>
            </div>

            <div class="space-y-5 px-6 py-5">
                <div class="grid gap-4 sm:grid-cols-3">
                    <label class="text-sm font-medium text-slate-700">
                        Employee
                        <select id="dtr-employee" class="employer-field-control">
                            <option value="">All employees</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }} (ID {{ $employee->id }})</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-medium text-slate-700">
                        Start date
                        <input id="dtr-start-date" type="date" required class="employer-field-control">
                    </label>
                    <label class="text-sm font-medium text-slate-700">
                        End date
                        <input id="dtr-end-date" type="date" required class="employer-field-control">
                    </label>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button id="dtr-preview-button" type="button" class="employer-primary-button bg-slate-900 hover:bg-slate-800 focus-visible:ring-slate-700">Preview records</button>
                    <button id="dtr-pdf-button" type="button" class="employer-secondary-button border-emerald-300 text-emerald-800 hover:bg-emerald-50">Download PDF</button>
                    <button id="dtr-xlsx-button" type="button" class="employer-secondary-button border-emerald-300 text-emerald-800 hover:bg-emerald-50">Export Excel</button>
                    <button id="dtr-all-button" type="button" class="hidden min-h-11 rounded-lg bg-emerald-700 px-4 py-2.5 text-base font-semibold text-white hover:bg-emerald-800">Export all employees (.xlsx)</button>
                </div>
                <p class="text-sm text-slate-600">Consolidated exports support up to 200 employees and 93 days per file.</p>
                <p id="dtr-feedback" class="min-h-5 text-sm text-slate-600" role="status" aria-live="polite"></p>

                <div id="dtr-preview" class="hidden space-y-3">
                    <div class="flex flex-wrap items-end justify-between gap-3 rounded-lg bg-emerald-50 px-4 py-3">
                        <div>
                            <p id="dtr-preview-employee" class="font-semibold text-slate-950"></p>
                            <p id="dtr-preview-period" class="text-sm text-slate-600"></p>
                        </div>
                        <p class="text-sm font-semibold text-emerald-800">Total: <span id="dtr-preview-total"></span> hours</p>
                    </div>
                    <div class="overflow-x-auto rounded-lg border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-base">
                            <thead class="bg-slate-50 text-left text-sm font-semibold text-slate-700">
                                <tr>
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3">Company schedule</th>
                                    <th class="px-4 py-3">Clock in</th>
                                    <th class="px-4 py-3">Clock out</th>
                                    <th class="px-4 py-3">Net hours</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Remarks</th>
                                </tr>
                            </thead>
                            <tbody id="dtr-preview-rows" class="divide-y divide-slate-100 bg-white"></tbody>
                        </table>
                    </div>
                    <p class="text-sm text-slate-600">Break times are not recorded and are not deducted. Only complete verified punch pairs count toward hours.</p>
                </div>
            </div>
        </section>
    </div>

    <script>
        (() => {
            const modal = document.getElementById('dtr-modal');
            const employeeSelect = document.getElementById('dtr-employee');
            const startInput = document.getElementById('dtr-start-date');
            const endInput = document.getElementById('dtr-end-date');
            const previewButton = document.getElementById('dtr-preview-button');
            const pdfButton = document.getElementById('dtr-pdf-button');
            const xlsxButton = document.getElementById('dtr-xlsx-button');
            const allButton = document.getElementById('dtr-all-button');
            const feedback = document.getElementById('dtr-feedback');
            const preview = document.getElementById('dtr-preview');
            const rows = document.getElementById('dtr-preview-rows');

            const pad = (value) => String(value).padStart(2, '0');
            const localDate = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
            const currentMonth = () => {
                const now = new Date();
                return [localDate(new Date(now.getFullYear(), now.getMonth(), 1)), localDate(new Date(now.getFullYear(), now.getMonth() + 1, 0))];
            };
            const selectedPeriod = () => ({ start_date: startInput.value, end_date: endInput.value });
            const withPeriod = (url) => {
                const params = new URLSearchParams(selectedPeriod());
                return `${url}?${params.toString()}`;
            };
            const templateFor = (key) => modal.dataset[`${key}Template`].replace('__employee__', encodeURIComponent(employeeSelect.value));
            const showFeedback = (message, failed = false) => {
                feedback.textContent = message;
                feedback.className = `min-h-5 text-sm ${failed ? 'text-red-700' : 'text-slate-600'}`;
            };
            const openModal = (employeeId = '') => {
                employeeSelect.value = employeeId;
                [startInput.value, endInput.value] = currentMonth();
                feedback.textContent = '';
                preview.classList.add('hidden');
                rows.replaceChildren();
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                modal.setAttribute('aria-hidden', 'false');
                updateActions();
            };
            const closeModal = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                modal.setAttribute('aria-hidden', 'true');
            };
            const updateActions = () => {
                const all = employeeSelect.value === '';
                previewButton.classList.toggle('hidden', all);
                pdfButton.classList.toggle('hidden', all);
                xlsxButton.classList.toggle('hidden', all);
                allButton.classList.toggle('hidden', !all);
                preview.classList.add('hidden');
                rows.replaceChildren();
            };
            const appendCell = (row, value) => {
                const cell = document.createElement('td');
                cell.className = 'px-4 py-3 text-slate-700';
                cell.textContent = value === null || value === undefined || value === '' ? '—' : String(value);
                row.appendChild(cell);
            };

            document.querySelectorAll('[data-dtr-open]').forEach((button) => button.addEventListener('click', () => openModal(button.dataset.employeeId)));
            document.querySelector('[data-dtr-open-all]')?.addEventListener('click', () => openModal(''));
            modal.querySelectorAll('[data-dtr-close]').forEach((button) => button.addEventListener('click', closeModal));
            modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(); });
            employeeSelect.addEventListener('change', updateActions);

            previewButton.addEventListener('click', async () => {
                const period = selectedPeriod();
                if (!period.start_date || !period.end_date || period.end_date < period.start_date) {
                    showFeedback('Choose a valid start date and end date.', true);
                    return;
                }

                previewButton.disabled = true;
                previewButton.textContent = 'Loading preview…';
                showFeedback('Loading attendance records…');
                try {
                    const response = await fetch(withPeriod(templateFor('preview')), {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    });
                    const payload = await response.json();
                    if (!response.ok) {
                        const validation = Object.values(payload.errors || {}).flat()[0];
                        throw new Error(validation || payload.message || 'Unable to load this DTR.');
                    }

                    const report = payload.data;
                    document.getElementById('dtr-preview-employee').textContent = `${report.employee.name} (ID ${report.employee.id}) · ${report.company?.name || 'Company'}`;
                    document.getElementById('dtr-preview-period').textContent = report.period;
                    document.getElementById('dtr-preview-total').textContent = Number(report.total_hours).toFixed(2);
                    rows.replaceChildren();
                    if (report.records.length === 0) {
                        const row = document.createElement('tr');
                        const cell = document.createElement('td');
                        cell.colSpan = 7;
                        cell.className = 'px-4 py-8 text-center text-slate-500';
                        cell.textContent = 'No attendance records for this period.';
                        row.appendChild(cell);
                        rows.appendChild(row);
                    } else {
                        report.records.forEach((record) => {
                            const row = document.createElement('tr');
                            appendCell(row, record.date);
                            appendCell(row, record.scheduled_shift);
                            appendCell(row, record.clock_in);
                            appendCell(row, record.clock_out);
                            appendCell(row, record.net_hours === null ? '—' : Number(record.net_hours).toFixed(2));
                            appendCell(row, record.status);
                            appendCell(row, record.remarks);
                            rows.appendChild(row);
                        });
                    }
                    preview.classList.remove('hidden');
                    showFeedback('Preview loaded.');
                } catch (error) {
                    showFeedback(error.message || 'Unable to load this DTR. Please try again.', true);
                } finally {
                    previewButton.disabled = false;
                    previewButton.textContent = 'Preview records';
                }
            });

            const startDownload = (url, maximumDays = null) => {
                const period = selectedPeriod();
                if (!period.start_date || !period.end_date || period.end_date < period.start_date) {
                    showFeedback('Choose a valid start date and end date.', true);
                    return;
                }
                if (maximumDays && (Date.parse(`${period.end_date}T00:00:00Z`) - Date.parse(`${period.start_date}T00:00:00Z`)) / 86400000 >= maximumDays) {
                    showFeedback(`Consolidated exports are limited to ${maximumDays} days at a time.`, true);
                    return;
                }
                showFeedback('Your DTR download is starting.');
                window.location.assign(withPeriod(url));
            };
            pdfButton.addEventListener('click', () => startDownload(templateFor('pdf')));
            xlsxButton.addEventListener('click', () => startDownload(templateFor('xlsx')));
            allButton.addEventListener('click', () => startDownload(modal.dataset.allUrl, 93));
        })();
    </script>
@endsection
