<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoreWebVitalsSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'lcp_ms', 'cls', 'inp_ms', 'lcp_rating', 'cls_rating', 'inp_rating', 'recorded_at',
    ];

    protected $casts = [
        'cls' => 'float',
        'recorded_at' => 'datetime',
    ];
}
