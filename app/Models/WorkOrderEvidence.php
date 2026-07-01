<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrderEvidence extends Model
{
    protected $table = 'work_order_evidence';

    protected $guarded = [];

    protected $casts = ['expires_at' => 'datetime', 'deleted_at' => 'datetime', 'metadata' => 'array'];
}
