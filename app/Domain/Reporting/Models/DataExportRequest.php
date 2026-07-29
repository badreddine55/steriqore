<?php

namespace App\Domain\Reporting\Models;

use App\Domain\Reporting\Enums\DataExportStatus;
use App\Models\User;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $requested_by_user_id
 * @property DataExportStatus $status
 * @property string|null $file_path
 * @property string|null $error
 * @property Carbon|null $completed_at
 * @property int|null $size_bytes
 * @property int|null $record_count
 * @property int|null $file_count
 * @property array<string, int>|null $table_manifest
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 */
class DataExportRequest extends Model
{
    use BelongsToTenant;
    use HasUuidv7;

    protected $fillable = [
        'tenant_id', 'requested_by_user_id', 'status', 'file_path', 'error', 'completed_at',
        'size_bytes', 'record_count', 'file_count', 'table_manifest', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DataExportStatus::class,
            'completed_at' => 'datetime',
            'size_bytes' => 'integer',
            'record_count' => 'integer',
            'file_count' => 'integer',
            'table_manifest' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
