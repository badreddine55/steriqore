<?php

namespace App\Domain\Labeling\Models;

use App\Models\User;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only (CLAUDE.md invariant #4) — never call ->update()/->delete();
 * the DB refuses it regardless via a trigger.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $label_id
 * @property string $printed_by_user_id
 * @property int $sequence
 * @property string|null $reason
 * @property Carbon $printed_at
 */
class LabelPrint extends Model
{
    use BelongsToTenant;
    use HasUuidv7;

    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'label_id', 'printed_by_user_id', 'sequence', 'reason', 'printed_at'];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'printed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Label, $this>
     */
    public function label(): BelongsTo
    {
        return $this->belongsTo(Label::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by_user_id');
    }
}
