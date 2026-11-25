<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">My leave applications</h1>
            <p class="mt-1 text-sm text-slate-600">View or print the application form for a submitted leave request.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8">
            @forelse ($leaveRequests as $leave)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">{{ $leave->leave_type }} leave</h2>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ $leave->start_date->format('M d, Y') }} – {{ $leave->end_date->format('M d, Y') }}
                            </p>
                            @if ($leave->company)
                                <p class="mt-1 text-sm text-slate-600">{{ $leave->company->name }}</p>
                            @endif
                        </div>
                        <span class="rounded-full border px-3 py-1 text-sm font-semibold {{ $leave->status === 'approved' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : ($leave->status === 'rejected' ? 'border-red-200 bg-red-50 text-red-800' : 'border-amber-200 bg-amber-50 text-amber-900') }}">
                            {{ str($leave->status)->title() }}
                        </span>
                    </div>
                    <p class="mt-4 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $leave->reason }}</p>
                    @if ($leave->admin_notes)
                        <div class="mt-4 rounded-lg bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Manager remarks</p>
                            <p class="mt-1 whitespace-pre-line text-sm text-slate-800">{{ $leave->admin_notes }}</p>
                        </div>
                    @endif
                    <a href="{{ route('leave-applications.show', $leave) }}" class="mt-4 inline-flex min-h-10 items-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700">
                        View application form
                    </a>
                </article>
            @empty
                <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-600">
                    You have no submitted leave requests yet.
                </div>
            @endforelse

            @if ($leaveRequests->hasPages())
                <div>{{ $leaveRequests->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
