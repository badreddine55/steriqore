<?php

namespace App\Domain\Labeling\Models;

use App\Domain\Labeling\Enums\LabelStatus;
use App\Domain\Sterilization\Models\CycleItem;
use App\Domain\Traceability\Models\LabelUsage;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * The 2D payload encoded on the physical label is this row's own id:
 * urn:steriqore:label:<id> (CLAUDE.md) — opaque, never patient data.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $cycle_item_id
 * @property LabelStatus $status
 * @property string $packaging_type
 * @property string $storage_condition
 * @property int $shelf_life_days
 * @property Carbon $sterilized_at
 * @property Carbon $use_by_date
 * @property int $print_counter
 * @property string|null $dlu_rule_version_id
 */
class Label extends Model
{
    use BelongsToTenant;
    use HasUuidv7;

    protected $fillable = [
        'tenant_id', 'cycle_item_id', 'status', 'packaging_type', 'storage_condition',
        'shelf_life_days', 'sterilized_at', 'use_by_date', 'print_counter', 'dlu_rule_version_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => LabelStatus::class,
            'shelf_life_days' => 'integer',
            'sterilized_at' => 'datetime',
            'use_by_date' => 'date',
            'print_counter' => 'integer',
        ];
    }

    public function urn(): string
    {
        return "urn:steriqore:label:{$this->id}";
    }

    /**
     * @return BelongsTo<CycleItem, $this>
     */
    public function cycleItem(): BelongsTo
    {
        return $this->belongsTo(CycleItem::class);
    }

    /**
     * @return HasMany<LabelPrint, $this>
     */
    public function prints(): HasMany
    {
        return $this->hasMany(LabelPrint::class);
    }

    /**
     * @return HasOne<LabelUsage, $this>
     */
    public function usage(): HasOne
    {
        return $this->hasOne(LabelUsage::class);
    }

    /**
     * Which DLU rule version computed this label's own use_by_date/
     * shelf_life_days at generation time (UI-28) — the evidentiary link a
     * dossier reads, not something re-resolved live against dlu_rules.
     *
     * @return BelongsTo<DluRuleVersion, $this>
     */
    public function dluRuleVersion(): BelongsTo
    {
        return $this->belongsTo(DluRuleVersion::class);
    }
}
