<?php

namespace App\Domain\Identity\Models;

use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Traceability\Models\Patient;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Represents a patient portal credential account.
 * Structurally separate from staff `users` table to maintain strict isolation.
 * Contains only the patient's email & hashed password, linked
 * solely to their pseudonymized patient reference (PAT-XXXXXX).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $patient_id
 * @property string $email
 * @property string $password
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 * @property-read Patient $patient
 */
class PatientPortalAccount extends Authenticatable
{
    use HasApiTokens;
    use HasUuidv7;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'patient_portal_accounts';

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'email',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
