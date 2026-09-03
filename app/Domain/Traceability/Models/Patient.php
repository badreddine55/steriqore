<?php

namespace App\Domain\Traceability\Models;

use App\Domain\Identity\Models\PatientPortalAccount;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $reference
 * @property-read PatientPortalAccount|null $portalAccount
 */
class Patient extends Model
{
    use BelongsToTenant;
    use HasUuidv7;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'reference',
        'first_name',
        'last_name',
        'birth_date',
        'email',
        'phone',
        'nir',
        'notes',
        'allergies',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'allergies' => 'array',
    ];

    /**
     * @return HasOne<PatientPortalAccount, $this>
     */
    public function portalAccount(): HasOne
    {
        return $this->hasOne(PatientPortalAccount::class);
    }
}
