<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrderDispute extends Model
{
    protected $guarded = [];

    protected $casts = ['resolved_at' => 'datetime'];
}
