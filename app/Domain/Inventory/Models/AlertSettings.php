<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Tenancy\Models\Tenant;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per tenant (UI-28). Not evidence-affecting — a plain audited
 * update (old/new values + reason), not versioned like the DLU rule table
 * or label format.
 *
 * @property string $id
 * @property string $tenant_id
 * @property int $near_expiry_days
 * @property int $default_low_stock_threshold
 * @property int|null $control_test_frequency_days
 * @property int|null $control_test_overdue_tolerance_days
 * @property int $failed_cycle_escalation_hours
 * @property bool $digests_enabled
 */
class AlertSettings extends Model
{
    use BelongsToTenant;
    use HasUuidv7;

    protected $fillable = [
        'tenant_id', 'near_expiry_days', 'default_low_stock_threshold',
        'control_test_frequency_days', 'control_test_overdue_tolerance_days',
        'failed_cycle_escalation_hours', 'digests_enabled',
    ];

    protected function casts(): array
    {
        return [
            'near_expiry_days' => 'integer',
            'default_low_stock_threshold' => 'integer',
            'control_test_frequency_days' => 'integer',
            'control_test_overdue_tolerance_days' => 'integer',
            'failed_cycle_escalation_hours' => 'integer',
            'digests_enabled' => 'boolean',
        ];
    }

    /**
     * Explicit defaults here (not just the migration's column defaults) —
     * firstOrCreate()'s in-memory model reflects whatever it was given,
     * not what Postgres actually filled in for omitted columns, so a
     * caller reading e.g. near_expiry_days right after creation would
     * otherwise see null/0, not the intended 30.
     */
    public static function forTenant(Tenant $tenant): self
    {
        return self::firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'near_expiry_days' => 30,
                'default_low_stock_threshold' => 0,
                'failed_cycle_escalation_hours' => 24,
                'digests_enabled' => true,
            ],
        );
    }
}
