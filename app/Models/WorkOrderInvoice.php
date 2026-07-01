<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrderInvoice extends Model
{
    protected $guarded = [];

    protected $casts = ['issued_at' => 'datetime', 'snapshot' => 'array'];
}
