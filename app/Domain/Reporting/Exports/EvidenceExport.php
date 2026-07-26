<?php

namespace App\Domain\Reporting\Exports;

use App\Domain\Traceability\Actions\SearchEvidenceAction;
use App\Domain\Traceability\Models\LabelUsage;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exactly the evidence search results (S4-ANA-03) as a file — shares
 * SearchEvidenceAction::query() rather than re-implementing the filters,
 * so the export can never quietly drift from what the search actually
 * returns.
 *
 * @implements WithMapping<LabelUsage>
 */
class EvidenceExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  array{patient_reference?: string, cycle_number?: int, batch_number?: string, device_id?: string, from?: string, to?: string}  $filters
     */
    public function __construct(private array $filters) {}

    /**
     * @return Builder<LabelUsage>
     */
    public function query(): Builder
    {
        return app(SearchEvidenceAction::class)->query($this->filters);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Label ID', 'Label status', 'Cycle number', 'Device', 'Site',
            'Batch number', 'Patient reference', 'Practitioner', 'Procedure', 'Used at',
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public function map($usage): array
    {
        $item = $usage->label->cycleItem;
        $cycle = $item->cycle;

        return [
            $usage->label_id,
            $usage->label->status->value,
            $cycle->cycle_number,
            $cycle->device->name,
            $cycle->device->site->name,
            $item->batch?->batch_number,
            $usage->patient->reference,
            $usage->practitioner->name,
            $usage->procedure,
            $usage->used_at->toIso8601String(),
        ];
    }
}
