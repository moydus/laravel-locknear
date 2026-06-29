<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyMedia extends Model
{
    protected $fillable = [
        'company_id',
        'type',
        'url',
        'path',
        'disk',
        'source',
        'is_public',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
