<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    private const DAYS_OF_WEEK = [
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday',
    ];

    /**
     * Display business scheduling settings.
     */
    public function index(): View
    {
        return view('employer.settings.index', [
            'company' => auth()->user()->company,
            'daysOfWeek' => self::DAYS_OF_WEEK,
        ]);
    }

    /**
     * Save strict schedule settings for the employer's company.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'work_start_time' => ['required', 'date_format:H:i'],
            'work_end_time' => ['required', 'date_format:H:i', 'different:work_start_time'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['required', 'string', Rule::in(self::DAYS_OF_WEEK)],
            'currency' => ['required', 'string', Rule::in(['PHP', 'USD'])],
        ]);

        $workingDays = array_values(array_unique($validated['working_days']));

        $company = auth()->user()->company;
        abort_unless($company, 404);

        $company->update([
            'work_start_time' => $validated['work_start_time'],
            'work_end_time' => $validated['work_end_time'],
            'working_days' => $workingDays,
            'currency' => $validated['currency'],
        ]);

        return redirect()
            ->route('employer.settings.index')
            ->with('success', 'Business settings updated successfully.');
    }
}
