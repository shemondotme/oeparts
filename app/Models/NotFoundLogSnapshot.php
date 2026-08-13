<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotFoundLogSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = ['unresolved_count', 'recorded_at'];

    protected $casts = [
        'unresolved_count' => 'integer',
        'recorded_at' => 'datetime',
    ];
}
