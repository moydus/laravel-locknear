<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'company_id', 'lead_id', 'reviewer_name', 'reviewer_email',
        'rating', 'body', 'is_verified', 'is_published',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_published' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
