<?php

namespace App\Domain\Prosthetics\Models;

use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Laboratory extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'email',
        'address',
        'contact_person',
        'specialties',
    ];

    protected $casts = [
        'specialties' => 'array',
    ];
}
