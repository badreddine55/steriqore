<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\AlertSeverity;
use App\Domain\Inventory\Enums\AlertState;
use App\Domain\Inventory\Enums\AlertType;
use App\Models\User;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property AlertType $type
 * @property AlertSeverity $severity
 * @property AlertState $state
 * @property string $subject_type
 * @property string $subject_id
 * @property string $message
 * @property string|null $resolved_by
 * @property Carbon|null $resolved_at
 */
class Alert extends Model
{
    use BelongsToTenant;
    use HasUuidv7;

    protected $fillable = [
        'tenant_id', 'type', 'severity', 'state', 'subject_type', 'subject_id',
        'message', 'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => AlertType::class,
            'severity' => AlertSeverity::class,
            'state' => AlertState::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
