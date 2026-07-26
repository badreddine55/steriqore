<?php

namespace App\Domain\Sterilization\Models;

use App\Domain\Sterilization\Enums\ControlTestResult;
use App\Domain\Sterilization\Enums\ControlTestType;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The control-test logbook entry (vacuum/Bowie-Dick/Helix/biological,
 * CLAUDE.md). Evidence table: no update/delete route — a mistaken entry is
 * corrected by recording a new test, never by editing this one.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $cycle_id
 * @property ControlTestType $type
 * @property ControlTestResult $result
 * @property Carbon $performed_at
 * @property string|null $notes
 */
class ControlTest extends Model
{
    use BelongsToTenant;
    use HasUuidv7;

    protected $fillable = ['tenant_id', 'cycle_id', 'type', 'result', 'performed_at', 'notes'];

    protected function casts(): array
    {
        return [
            'type' => ControlTestType::class,
            'result' => ControlTestResult::class,
            'performed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Cycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }
}
