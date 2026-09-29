<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ReportsExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly Collection $reportRows)
    {
    }

    /**
     * @return Collection<int, array<int, string>>
     */
    public function collection(): Collection
    {
        return $this->reportRows->map(fn ($employer) => [
            $employer->company?->name ?? 'No company assigned',
            $employer->name,
            $employer->email,
            ucfirst($employer->status ?? 'active'),
            $employer->created_at?->format('Y-m-d H:i:s') ?? '',
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Company', 'Employer', 'Email', 'Status', 'Created At'];
    }
}
