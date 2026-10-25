@extends('layouts.employer')

@section('title', 'Leave Requests - TimePay')
@section('header_title', 'Leave requests')

@section('content')
    @php
        $statusTone = [
            'pending' => 'border-amber-200 bg-amber-50 text-amber-900',
            'approved' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
            'rejected' => 'border-red-200 bg-red-50 text-red-900',
        ];
    @endphp

    <div class="space-y-6" data-leave-page>
        <header class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-emerald-700">People</p>
                <h2 class="employer-page-heading">Leave requests</h2>
                <p class="employer-page-description">Review requests and keep a clear record of each decision.</p>
            </div>
            <div data-pending-count class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-950" aria-live="polite">
                {{ number_format($pendingCount) }} pending {{ \Illuminate\Support\Str::plural('request', $pendingCount) }}
            </div>
        </header>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-base text-red-900" role="alert">
                <p class="font-semibold">The request could not be updated.</p>
                <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="employer-card overflow-hidden">
            <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-end sm:justify-between sm:px-6">
                <div>
                    <h3 class="employer-section-heading">Request queue</h3>
                    <p class="mt-1 text-sm text-slate-600">Showing {{ $leaveRequests->count() }} of {{ $leaveRequests->total() }} requests.</p>
                </div>
                <form method="GET" action="{{ route('employer.leaves.index') }}" class="flex gap-2">
                    <label for="leave-status-filter" class="sr-only">Filter by status</label>
                    <select id="leave-status-filter" name="status" class="employer-field-control mt-0 min-w-40">
                        <option value="">All statuses</option>
                        @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                            <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="employer-secondary-button">Filter</button>
                </form>
            </div>

            <div class="hidden overflow-x-auto lg:block">
                <table class="min-w-[980px] w-full divide-y divide-slate-200 text-left">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-5 py-3 text-sm font-semibold text-slate-700">Employee</th>
                            <th scope="col" class="px-5 py-3 text-sm font-semibold text-slate-700">Leave type and dates</th>
                            <th scope="col" class="px-5 py-3 text-sm font-semibold text-slate-700">Reason and notes</th>
                            <th scope="col" class="px-5 py-3 text-sm font-semibold text-slate-700">Status</th>
                            <th scope="col" class="px-5 py-3 text-sm font-semibold text-slate-700">Decision</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($leaveRequests as $leave)
                            <tr data-leave-row="{{ $leave->id }}" class="align-top hover:bg-slate-50/70">
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-slate-900">{{ $leave->user?->name ?? 'Unknown employee' }}</p>
                                    <p class="mt-0.5 text-sm text-slate-600">{{ $leave->user?->email }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-slate-900">{{ $leave->leave_type }}</p>
                                    <p class="mt-1 whitespace-nowrap text-sm text-slate-600">{{ $leave->start_date->format('M d, Y') }} – {{ $leave->end_date->format('M d, Y') }}</p>
                                </td>
                                <td class="min-w-64 px-5 py-4">
                                    <p class="text-sm leading-5 text-slate-700">{{ $leave->reason }}</p>
                                    <div data-leave-note @if (! $leave->admin_notes) hidden @endif class="mt-3 rounded-lg bg-slate-50 p-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Employer note</p>
                                        <p class="mt-1 whitespace-pre-line text-sm text-slate-800">{{ $leave->admin_notes }}</p>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <span data-leave-status class="inline-flex rounded-full border px-2.5 py-1 text-sm font-semibold {{ $statusTone[$leave->status] ?? $statusTone['pending'] }}">{{ str($leave->status)->title() }}</span>
                                </td>
                                <td data-leave-actions class="min-w-64 px-5 py-4">
                                    @if ($leave->status === 'pending')
                                        <form method="POST" action="{{ route('employer.leaves.update', $leave) }}" data-leave-decision class="space-y-2">
                                            @csrf
                                            @method('PATCH')
                                            <label for="admin-notes-{{ $leave->id }}" class="block text-sm font-medium text-slate-700">Decision note <span class="font-normal text-slate-500">(optional)</span></label>
                                            <textarea id="admin-notes-{{ $leave->id }}" name="admin_notes" rows="2" maxlength="2000" class="employer-field-control min-h-20 resize-y" placeholder="Add a note for the employee">{{ $leave->admin_notes }}</textarea>
                                            <div class="flex flex-wrap gap-2">
                                                <button type="submit" name="status" value="approved" class="inline-flex min-h-10 items-center rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700">Approve</button>
                                                <button type="submit" name="status" value="rejected" class="inline-flex min-h-10 items-center rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-semibold text-red-800 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700">Decline</button>
                                            </div>
                                        </form>
                                    @else
                                        <p class="text-sm font-medium text-slate-700">Decision recorded: {{ str($leave->status)->title() }}</p>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-12 text-center text-base text-slate-600">No leave requests match this filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-200 lg:hidden">
                @forelse ($leaveRequests as $leave)
                    <article data-leave-row="{{ $leave->id }}" class="space-y-4 p-4 sm:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h4 class="truncate font-semibold text-slate-900">{{ $leave->user?->name ?? 'Unknown employee' }}</h4>
                                <p class="break-all text-sm text-slate-600">{{ $leave->user?->email }}</p>
                            </div>
                            <span data-leave-status class="shrink-0 rounded-full border px-2.5 py-1 text-sm font-semibold {{ $statusTone[$leave->status] ?? $statusTone['pending'] }}">{{ str($leave->status)->title() }}</span>
                        </div>
                        <dl class="grid grid-cols-2 gap-3 border-y border-slate-200 py-3 text-sm">
                            <div><dt class="text-slate-600">Leave type</dt><dd class="mt-0.5 font-semibold text-slate-900">{{ $leave->leave_type }}</dd></div>
                            <div><dt class="text-slate-600">Dates</dt><dd class="mt-0.5 font-medium text-slate-900">{{ $leave->start_date->format('M d') }} – {{ $leave->end_date->format('M d, Y') }}</dd></div>
                        </dl>
                        <div><p class="text-sm font-semibold text-slate-700">Reason</p><p class="mt-1 whitespace-pre-line text-sm leading-5 text-slate-700">{{ $leave->reason }}</p></div>
                        <div data-leave-note @if (! $leave->admin_notes) hidden @endif class="rounded-lg bg-slate-50 p-3">
                            <p class="text-sm font-semibold text-slate-700">Employer note</p><p class="mt-1 whitespace-pre-line text-sm text-slate-800">{{ $leave->admin_notes }}</p>
                        </div>
                        <div data-leave-actions>
                            @if ($leave->status === 'pending')
                                <form method="POST" action="{{ route('employer.leaves.update', $leave) }}" data-leave-decision class="space-y-3">
                                    @csrf
                                    @method('PATCH')
                                    <label for="mobile-admin-notes-{{ $leave->id }}" class="employer-field-label">Decision note <span class="font-normal text-slate-500">(optional)</span>
                                        <textarea id="mobile-admin-notes-{{ $leave->id }}" name="admin_notes" rows="2" maxlength="2000" class="employer-field-control min-h-20 resize-y" placeholder="Add a note for the employee">{{ $leave->admin_notes }}</textarea>
                                    </label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <button type="submit" name="status" value="approved" class="employer-primary-button">Approve</button>
                                        <button type="submit" name="status" value="rejected" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-red-300 bg-white px-4 py-2.5 text-base font-semibold text-red-800 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700">Decline</button>
                                    </div>
                                </form>
                            @else
                                <p class="text-sm font-medium text-slate-700">Decision recorded: {{ str($leave->status)->title() }}</p>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="px-4 py-12 text-center text-base text-slate-600">No leave requests match this filter.</p>
                @endforelse
            </div>

            @if ($leaveRequests->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">{{ $leaveRequests->links() }}</div>
            @endif
        </section>
    </div>

    <script>
        (() => {
            const page = document.querySelector('[data-leave-page]');
            if (!page) return;

            const statusTone = {
                approved: 'border-emerald-200 bg-emerald-50 text-emerald-900',
                rejected: 'border-red-200 bg-red-50 text-red-900',
            };

            const applyConfirmedState = (rows, data) => {
                if (!['approved', 'rejected'].includes(data.status)) return false;

                const wasPending = rows.some((row) => row.querySelector('[data-leave-status]')?.textContent.trim().toLowerCase() === 'pending');
                rows.forEach((row) => {
                    row.querySelectorAll('[data-leave-status]').forEach((badge) => {
                        badge.textContent = data.status[0].toUpperCase() + data.status.slice(1);
                        badge.className = `inline-flex rounded-full border px-2.5 py-1 text-sm font-semibold ${statusTone[data.status]}`;
                    });

                    row.querySelectorAll('[data-leave-note]').forEach((note) => {
                        const text = note.querySelector('p:last-child');
                        if (text) text.textContent = data.admin_notes || '';
                        note.hidden = !data.admin_notes;
                    });

                    row.querySelectorAll('[data-leave-actions]').forEach((actions) => {
                        actions.replaceChildren();
                        const message = document.createElement('p');
                        message.className = 'text-sm font-medium text-slate-700';
                        message.textContent = `Decision recorded: ${data.status[0].toUpperCase() + data.status.slice(1)}`;
                        actions.appendChild(message);
                    });
                });

                if (wasPending) {
                    const pendingCount = page.querySelector('[data-pending-count]');
                    const currentCount = Number.parseInt(pendingCount?.textContent.match(/\d+/)?.[0] || '0', 10);
                    if (pendingCount) pendingCount.textContent = `${Math.max(0, currentCount - 1)} pending leave requests`;
                }

                return true;
            };

            page.querySelectorAll('[data-leave-decision]').forEach((form) => {
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    if (form.dataset.processing === 'true') return;

                    const submitter = event.submitter;
                    if (!submitter || !['approved', 'rejected'].includes(submitter.value)) return;
                    if (!window.confirm(submitter.value === 'approved' ? 'Approve this leave request?' : 'Decline this leave request?')) return;

                    const row = form.closest('[data-leave-row]');
                    const requestId = row?.dataset.leaveRow;
                    const matchingRows = [...page.querySelectorAll('[data-leave-row]')].filter((item) => item.dataset.leaveRow === requestId);
                    const actions = row?.querySelector('[data-leave-actions]');
                    const priorError = actions?.querySelector('[role="alert"]');
                    priorError?.remove();

                    form.dataset.processing = 'true';
                    form.querySelectorAll('button[type="submit"]').forEach((button) => {
                        button.disabled = true;
                        button.setAttribute('aria-busy', 'true');
                    });

                    try {
                        const body = new FormData(form);
                        body.set('status', submitter.value);
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            },
                            body,
                            credentials: 'same-origin',
                        });
                        const payload = await response.json().catch(() => ({}));

                        if (response.ok) {
                            if (!applyConfirmedState(matchingRows, payload.data || {})) throw new Error('The server response did not include a confirmed leave decision.');
                            return;
                        }

                        if (response.status === 409 && applyConfirmedState(matchingRows, payload.data || {})) {
                            const message = document.createElement('p');
                            message.className = 'mt-2 text-sm text-amber-800';
                            message.textContent = payload.message || 'This request was already reviewed. The current status is shown above.';
                            actions?.appendChild(message);
                            return;
                        }

                        const validationMessage = Object.values(payload.errors || {}).flat()[0];
                        throw new Error(validationMessage || payload.message || 'Unable to save the decision. Please try again.');
                    } catch (error) {
                        const message = document.createElement('p');
                        message.className = 'text-sm font-medium text-red-700';
                        message.setAttribute('role', 'alert');
                        message.textContent = error.message || 'Unable to save the decision. Please try again.';
                        actions?.appendChild(message);
                        form.dataset.processing = 'false';
                        form.querySelectorAll('button[type="submit"]').forEach((button) => {
                            button.disabled = false;
                            button.removeAttribute('aria-busy');
                        });
                    }
                });
            });
        })();
    </script>
@endsection
