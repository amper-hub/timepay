<article class="leave-form">
    <header class="form-header">
        @if ($leaveRequest->company)
            <p class="company-name">{{ $leaveRequest->company->name }}</p>
        @endif
        <h1>EMPLOYEE LEAVE APPLICATION FORM</h1>
    </header>

    <section class="form-section">
        <h2>Employee Information</h2>
        <div class="field-grid">
            <div class="field"><span class="field-label">Employee Name</span><span class="field-value">{{ $leaveRequest->user?->name ?? 'Unavailable' }}</span></div>
            @if ($leaveRequest->company)
                <div class="field"><span class="field-label">Company</span><span class="field-value">{{ $leaveRequest->company->name }}</span></div>
            @endif
        </div>
    </section>

    <section class="form-section">
        <h2>Leave Details</h2>
        <div class="field-grid">
            <div class="field"><span class="field-label">Nature of Leave</span><span class="field-value">{{ $leaveRequest->leave_type }}</span></div>
            <div class="field"><span class="field-label">Total Number of Calendar Leave Days</span><span class="field-value">{{ $leaveDays }}</span></div>
            <div class="field"><span class="field-label">Date of Leave — From</span><span class="field-value">{{ $leaveRequest->start_date->format('F d, Y') }}</span></div>
            <div class="field"><span class="field-label">Date of Leave — To</span><span class="field-value">{{ $leaveRequest->end_date->format('F d, Y') }}</span></div>
        </div>
        <div class="field field-block">
            <span class="field-label">Reason for Taking Leave</span>
            <span class="field-value reason-value">{{ $leaveRequest->reason }}</span>
        </div>
    </section>

    <section class="form-section">
        <h2>Application Details</h2>
        <div class="field-grid">
            <div class="field"><span class="field-label">Date Submitted</span><span class="field-value">{{ $leaveRequest->created_at?->format('F d, Y') ?? 'Unavailable' }}</span></div>
            <div class="field"><span class="field-label">Current Request Status</span><span class="field-value status-value">{{ str($leaveRequest->status)->title() }}</span></div>
        </div>
        <div class="signature-grid">
            <div class="signature-block"><span class="signature-line"></span><span>Applicant’s Signature</span></div>
            <div class="signature-block"><span class="signature-line"></span><span>Date</span></div>
        </div>
    </section>

    <section class="form-section manager-section">
        <h2>Manager Review</h2>
        <div class="field field-block">
            <span class="field-label">Manager’s Remarks</span>
            <span class="field-value reason-value">{{ $leaveRequest->admin_notes ?: '—' }}</span>
        </div>
        <div class="signature-grid manager-signature">
            <div class="signature-block"><span class="signature-line"></span><span>Manager / Authorized Signature</span></div>
            <div class="signature-block"><span class="signature-line"></span><span>Date</span></div>
        </div>
    </section>

    <p class="form-footnote">This form reflects the saved leave request and its current review status. Signature lines are for handwritten signatures.</p>
</article>
