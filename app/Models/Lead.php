<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    protected $fillable = [
        'user_id', 'preferred_company_id', 'public_id', 'zip', 'service_type', 'phone', 'email', 'description', 'status',
        'ip_address', 'user_agent', 'source', 'assigned_at', 'completed_at',
        'customer_token', 'latitude', 'longitude', 'customer_name', 'city', 'state',
        'google_place_id', 'formatted_address', 'address_components', 'place_source',
        'authorization_confirmed', 'authorization_confirmed_at', 'authorization_disclaimer_version',
        'work_order_number', 'dispatch_fee_cents', 'dispatch_fee_currency',
        'dispatch_fee_policy_version', 'dispatch_fee_acknowledged', 'dispatch_fee_acknowledged_at',
        'vehicle_make', 'vehicle_model', 'vehicle_year', 'vehicle_color', 'license_plate', 'vin',
        'vehicle_owned_or_authorized', 'registration_available', 'photo_id_available', 'document_names_match',
        'customer_cancelled_at', 'customer_cancellation_reason',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
        'address_components' => 'array',
        'authorization_confirmed' => 'boolean',
        'authorization_confirmed_at' => 'datetime',
        'dispatch_fee_cents' => 'integer',
        'dispatch_fee_acknowledged' => 'boolean',
        'dispatch_fee_acknowledged_at' => 'datetime',
        'vehicle_owned_or_authorized' => 'boolean',
        'registration_available' => 'boolean',
        'photo_id_available' => 'boolean',
        'document_names_match' => 'boolean',
        'customer_cancelled_at' => 'datetime',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(LeadAssignment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function preferredCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'preferred_company_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(LeadMessage::class);
    }
}
