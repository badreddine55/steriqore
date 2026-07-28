<?php

namespace App\Domain\Labeling\Models;

use App\Domain\Labeling\Enums\LabelSheetLayout;
use App\Domain\Labeling\Enums\PrimaryLabelCode;
use App\Models\User;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only (UI-28) — never call ->update()/->delete(); the DB refuses
 * it regardless via a trigger. One row per change to the tenant's label
 * format; the "current" one is the latest by effective_from. Recorded on
 * each label_prints row (not on Label itself) since format is a
 * print-time rendering concern, not frozen at label-creation time.
 *
 * @property string $id
 * @property string $tenant_id
 * @property PrimaryLabelCode $primary_code
 * @property LabelSheetLayout $sheet_layout
 * @property bool $show_batch_number
 * @property bool $show_product_name
 * @property string $actor_id
 * @property string $reason
 * @property Carbon $effective_from
 */
class LabelFormatVersion extends Model
{
    use BelongsToTenant;
    use HasUuidv7;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'primary_code', 'sheet_layout', 'show_batch_number',
        'show_product_name', 'actor_id', 'reason', 'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'primary_code' => PrimaryLabelCode::class,
            'sheet_layout' => LabelSheetLayout::class,
            'show_batch_number' => 'boolean',
            'show_product_name' => 'boolean',
            'effective_from' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
