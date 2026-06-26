<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderAccount extends Model
{
    protected $fillable = [
        'company_id', 'status', 'display_name', 'timezone', 'default_capacity',
        'capabilities', 'metadata',
    ];

    protected $casts = [
        'default_capacity' => 'integer',
        'capabilities' => 'array',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(ProviderAccountUser::class, 'company_id', 'company_id');
    }
}
