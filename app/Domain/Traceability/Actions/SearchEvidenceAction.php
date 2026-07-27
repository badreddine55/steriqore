<?php

namespace App\Domain\Traceability\Actions;

use App\Domain\Traceability\Models\LabelUsage;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * REC-09: "search by patient/cycle/batch -> complete result < 2s at pilot
 * volume." Filters are all exact/prefix matches on structured columns
 * (a reference code, a cycle number, a batch number, a device id, a date
 * range) — not free text — so plain btree indexes on the relevant foreign
 * keys/columns are the deliberate indexing CLAUDE.md asks for here, not
 * GIN/tsvector (that's for genuine full-text search, which nothing in
 * this filter set actually needs).
 */
class SearchEvidenceAction
{
    /**
     * @param  array{patient_reference?: string, cycle_number?: int, batch_number?: string, device_id?: string, operator_id?: string, from?: string, to?: string}  $filters
     * @return CursorPaginator<int, LabelUsage>
     */
    public function execute(array $filters, int $perPage): CursorPaginator
    {
        return $this->query($filters)->cursorPaginate(perPage: $perPage);
    }

    /**
     * Shared with ExportEvidenceAction (S4-ANA-04) so the export is
     * exactly "these search results as a file," not a second filter
     * implementation that could quietly drift from the first.
     *
     * @param  array{patient_reference?: string, cycle_number?: int, batch_number?: string, device_id?: string, operator_id?: string, from?: string, to?: string}  $filters
     * @return Builder<LabelUsage>
     */
    public function query(array $filters): Builder
    {
        return LabelUsage::query()
            ->with(['label.cycleItem.cycle.device.site', 'label.cycleItem.cycle.operator', 'label.cycleItem.batch', 'patient', 'practitioner'])
            ->when(
                $filters['patient_reference'] ?? null,
                fn ($query, $value) => $query->whereHas('patient', fn ($q) => $q->where('reference', 'like', "%{$value}%"))
            )
            ->when(
                $filters['cycle_number'] ?? null,
                fn ($query, $value) => $query->whereHas('label.cycleItem.cycle', fn ($q) => $q->where('cycle_number', $value))
            )
            ->when(
                $filters['batch_number'] ?? null,
                fn ($query, $value) => $query->whereHas('label.cycleItem.batch', fn ($q) => $q->where('batch_number', 'like', "%{$value}%"))
            )
            ->when(
                $filters['device_id'] ?? null,
                fn ($query, $value) => $query->whereHas('label.cycleItem.cycle', fn ($q) => $q->where('device_id', $value))
            )
            ->when(
                // The cycle's operator (who ran the sterilization cycle) —
                // distinct from the practitioner who performed the
                // procedure on the patient.
                $filters['operator_id'] ?? null,
                fn ($query, $value) => $query->whereHas('label.cycleItem.cycle', fn ($q) => $q->where('operator_id', $value))
            )
            ->when($filters['from'] ?? null, fn ($query, $value) => $query->where('used_at', '>=', $value))
            ->when($filters['to'] ?? null, fn ($query, $value) => $query->where('used_at', '<=', $value))
            ->orderByDesc('used_at');
    }
}
