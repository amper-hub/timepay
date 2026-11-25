<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Leave Application {{ $leaveRequest->id }}</title>
    <style>
        @page { size: A4 portrait; margin: 16mm; }
        body { color: #17211d; font-family: DejaVu Sans, sans-serif; font-size: 10pt; line-height: 1.45; }
        .leave-form { color: #17211d; }
        .form-header { border-bottom: 2px solid #176b4b; margin-bottom: 22px; padding-bottom: 14px; text-align: center; }
        .company-name { color: #52645b; font-size: 9pt; font-weight: bold; letter-spacing: 1px; margin: 0 0 7px; text-transform: uppercase; }
        .form-header h1 { color: #153d2e; font-size: 16pt; letter-spacing: 1px; margin: 0; }
        .form-section { border: 1px solid #ccd8d1; border-radius: 5px; margin-bottom: 13px; padding: 13px; page-break-inside: avoid; }
        .form-section h2 { border-bottom: 1px solid #d9e2dd; color: #176b4b; font-size: 9pt; letter-spacing: 1px; margin: 0 0 11px; padding-bottom: 7px; text-transform: uppercase; }
        .field-grid { border-collapse: collapse; display: table; table-layout: fixed; width: 100%; }
        .field { display: table-cell; padding: 4px 10px 10px 0; vertical-align: top; width: 50%; }
        .field-block { display: block; padding-right: 0; width: auto; }
        .field-label { color: #63746b; display: block; font-size: 8pt; font-weight: bold; letter-spacing: .4px; margin-bottom: 3px; text-transform: uppercase; }
        .field-value { color: #17211d; display: block; font-size: 10pt; font-weight: bold; min-height: 15px; }
        .reason-value { font-weight: normal; min-height: 30px; white-space: pre-wrap; }
        .status-value { color: #176b4b; }
        .signature-grid { border-collapse: collapse; display: table; margin-top: 22px; table-layout: fixed; width: 100%; }
        .signature-block { display: table-cell; padding-right: 20px; width: 50%; }
        .signature-line { border-bottom: 1px solid #52645b; display: block; height: 21px; margin-bottom: 6px; }
        .signature-block span:last-child { color: #52645b; font-size: 8pt; }
        .form-footnote { color: #63746b; font-size: 8pt; margin: 14px 0 0; text-align: center; }
    </style>
</head>
<body>
    @include('leave-applications._form')
</body>
</html>
