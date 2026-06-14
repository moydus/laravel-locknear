<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    protected $fillable = [
        'zip', 'service_type', 'phone', 'description', 'status',
        'ip_address', 'user_agent', 'source', 'assigned_at', 'completed_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(LeadAssignment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
