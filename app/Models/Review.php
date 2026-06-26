<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'lead_id', 'reviewer_name', 'reviewer_email',
        'rating', 'speed_rating', 'communication_rating', 'professionalism_rating',
        'price_rating', 'body', 'is_verified', 'is_published',
    ];

    protected $casts = [
        'rating' => 'integer',
        'speed_rating' => 'integer',
        'communication_rating' => 'integer',
        'professionalism_rating' => 'integer',
        'price_rating' => 'integer',
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
