<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrderQuote extends Model
{
    protected $guarded = [];

    protected $casts = ['proposed_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime'];
}
