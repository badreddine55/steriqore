<?php

namespace App\Domain\Traceability\Models;

use App\Domain\Labeling\Models\Label;
use App\Models\User;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only (CLAUDE.md invariant #7) — never call ->update()/->delete();
 * the DB refuses it regardless via a trigger.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $label_id
 * @property string $patient_id
 * @property string $practitioner_id
 * @property string $procedure
 * @property Carbon $used_at
 * @property string|null $notes
 */
class LabelUsage extends Model
{
    use BelongsToTenant;
    use HasUuidv7;

    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'label_id', 'patient_id', 'practitioner_id', 'procedure', 'used_at', 'notes'];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
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
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'practitioner_id');
    }
}
