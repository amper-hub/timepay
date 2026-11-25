<x-app-layout>
    <x-slot name="header">
        <div class="leave-form-toolbar flex flex-wrap items-center justify-between gap-3 print:hidden">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">Leave application form</h1>
                <p class="mt-1 text-sm text-slate-600">Request #{{ $leaveRequest->id }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="window.print()" class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Print form</button>
                <a href="{{ route('leave-applications.download', $leaveRequest) }}" class="inline-flex min-h-10 items-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Download PDF</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 print:py-0">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 print:max-w-none print:px-0">
            <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm print:rounded-none print:border-0 print:p-0 print:shadow-none">
                @include('leave-applications._form')
            </div>
        </div>
    </div>

    <style>
        .leave-form { color: #17211d; font: 14px/1.45 Arial, sans-serif; }
        .form-header { border-bottom: 2px solid #176b4b; margin-bottom: 24px; padding-bottom: 16px; text-align: center; }
        .company-name { color: #52645b; font-size: 12px; font-weight: 700; letter-spacing: .08em; margin: 0 0 8px; text-transform: uppercase; }
        .form-header h1 { color: #153d2e; font-size: 21px; letter-spacing: .07em; margin: 0; }
        .form-section { border: 1px solid #ccd8d1; border-radius: 7px; margin: 0 0 16px; padding: 16px; page-break-inside: avoid; }
        .form-section h2 { border-bottom: 1px solid #d9e2dd; color: #176b4b; font-size: 12px; letter-spacing: .08em; margin: 0 0 14px; padding-bottom: 8px; text-transform: uppercase; }
        .field-grid { display: table; table-layout: fixed; width: 100%; }
        .field { display: table-cell; padding: 5px 12px 12px 0; vertical-align: top; width: 50%; }
        .field-block { display: block; padding-right: 0; width: auto; }
        .field-label { color: #63746b; display: block; font-size: 10px; font-weight: 700; letter-spacing: .05em; margin-bottom: 4px; text-transform: uppercase; }
        .field-value { color: #17211d; display: block; font-size: 13px; font-weight: 600; min-height: 18px; }
        .reason-value { font-weight: 400; min-height: 38px; white-space: pre-wrap; }
        .status-value { color: #176b4b; }
        .signature-grid { display: table; margin-top: 28px; table-layout: fixed; width: 100%; }
        .signature-block { display: table-cell; padding-right: 24px; width: 50%; }
        .signature-line { border-bottom: 1px solid #52645b; display: block; height: 22px; margin-bottom: 7px; }
        .signature-block span:last-child { color: #52645b; font-size: 11px; }
        .form-footnote { color: #63746b; font-size: 10px; margin: 18px 0 0; text-align: center; }
        @media print { @page { size: A4 portrait; margin: 16mm; } body { background: #fff !important; } nav, .leave-form-toolbar { display: none !important; } }
    </style>
</x-app-layout>
