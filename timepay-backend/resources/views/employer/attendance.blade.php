@extends('layouts.employer')

@section('title', 'Attendance Log - TimePay Employer Portal')
@section('header_title', 'Attendance Log')

@section('content')
    <div class="space-y-6">
        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-2 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">Live Logs Matrix</h2>
                    <p class="mt-1 text-sm text-slate-500">Showing {{ $attendanceLogs->count() }} of {{ $attendanceLogs->total() }} attendance events.</p>
                </div>
                <button type="button" data-dtr-open-all class="inline-flex items-center justify-center rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800">
                    Export All Employees
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Employee</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Punch Type</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Timestamp</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Distance</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">DTR</th>
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
                                            <p class="text-sm font-semibold text-slate-950">{{ $log->user?->name ?? 'Unknown Employee' }}</p>
                                            <p class="text-xs text-slate-500">{{ $log->user?->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $log->type === 'clock_in' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-teal-50 text-teal-700 ring-1 ring-teal-200' }}">
                                        {{ $log->type === 'clock_in' ? 'Clock In' : 'Clock Out' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                                    {{ $log->timestamp?->timezone('Asia/Manila')->format('M d, Y g:i A') ?? '-' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                                    {{ number_format((float) $log->distance_meters, 1) }} meters away
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold @if ($log->status === 'verified') bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 @elseif ($log->status === 'flagged') bg-amber-50 text-amber-700 ring-1 ring-amber-200 @elseif ($log->status === 'rejected') bg-red-50 text-red-700 ring-1 ring-red-200 @else bg-red-50 text-red-700 ring-1 ring-red-200 @endif">
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
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    @if ($log->user && $log->user->role === \App\Models\User::ROLE_EMPLOYEE)
                                        <button
                                            type="button"
                                            data-dtr-open
                                            data-employee-id="{{ $log->user->id }}"
                                            class="rounded-md border border-emerald-200 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-50"
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
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">No attendance logs found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
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
                        <select id="dtr-employee" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                            <option value="">All employees</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }} (ID {{ $employee->id }})</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-medium text-slate-700">
                        Start date
                        <input id="dtr-start-date" type="date" required class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                    </label>
                    <label class="text-sm font-medium text-slate-700">
                        End date
                        <input id="dtr-end-date" type="date" required class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                    </label>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button id="dtr-preview-button" type="button" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Preview records</button>
                    <button id="dtr-pdf-button" type="button" class="rounded-md border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-50">Download PDF</button>
                    <button id="dtr-xlsx-button" type="button" class="rounded-md border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-50">Export Excel</button>
                    <button id="dtr-all-button" type="button" class="hidden rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Export All Employees (.xlsx)</button>
                </div>
                <p class="text-xs text-slate-500">Consolidated exports support up to 200 employees and 93 days per file.</p>
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
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
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
                    <p class="text-xs text-slate-500">Break times are not recorded and are not deducted. Only complete verified punch pairs count toward hours.</p>
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
