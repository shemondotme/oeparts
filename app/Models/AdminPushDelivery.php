<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminPushDelivery extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['admin_id', 'subscription_id', 'topic', 'status', 'http_status', 'error'];
}
