<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['actor_id', 'from_status', 'to_status', 'reason'])]
class OrderStatusHistory extends Model
{
    public const UPDATED_AT = null;
}
